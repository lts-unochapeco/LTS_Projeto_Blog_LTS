<?php
/*
	Copyright (C) 2015-25 CERBER TECH INC., https://wpcerber.com

    Licenced under the GNU GPL.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/*

*========================================================================*
|                                                                        |
|	       ATTENTION!  Do not change or edit this file!                  |
|                                                                        |
*========================================================================*

*/
/**
 * Handles all operations with the activity log.
 *
 *
 * @since 9.6.6.10
 */
final class CRB_Activity {
	private static $logged = array();
	private static $ignore = array();

	// HTTP query string parameters with mapping
	// Parameter name => DB table field if direct mapping applicable

	const FILTERING_PARAMS = array(
		'filter_activity'   => 'activity',
		'filter_status'     => 'ac_status',
		'filter_set'        => false,
		'filter_ip'         => 'ip',
		'filter_login'      => 'user_login',
		'filter_user'       => 'user_id',
		'search_activity'   => false,
		'filter_sid'        => 'session_id',
		'search_url'        => false,
		'filter_country'    => 'country',
		'filter_time_begin' => false,
		'filter_time_end'   => false,
	);

	/**
	 * Get records from the activity log using specified conditions
	 *
	 * @param array $activity List of activity IDs.
	 * @param array $user User parameters ('email' or 'id').
	 * @param array $order Order parameters ('DESC' => 'column_name' or 'ASC' => 'column_name').
	 * @param string $limit SQL LIMIT clause. Example: '10' or '0, 20'
	 *
	 * @return object[] Array of log record objects. An empty array if no records are found.
	 */
	static function get_log( array $activity = [], array $user = [], $order = [], $limit = '' ): array {

		$where = array();

		if ( $activity ) {
			$activity = array_map( 'absint', $activity );
			$where[]  = 'activity IN (' . implode( ', ', $activity ) . ')';
		}

		if ( ! empty( $user['email'] ) ) {
			$user_obj = get_user_by( 'email', $user['email'] );

			if ( ! $user_obj ) {
				return array();
			}

			$where[] = 'user_id = ' . absint( $user_obj->ID );
		}
		elseif ( ! empty( $user['id'] ) ) {
			$where[] = 'user_id = ' . absint( $user['id'] );
		}

		$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

		$order_sql = '';
		if ( $order ) {

			$order_key = key( $order );
			$order_column = current( $order );

			if ( in_array( $order_key, [ 'DESC', 'ASC' ], true ) && $order_column ) {
				$order_sql = ' ORDER BY ' . crb_sanitize_id( $order_column ) . ' ' . $order_key;
			}
		}

		$limit_sql = $limit ? ' LIMIT ' . preg_replace('/[^0-9,]/', '', $limit) : '';

		$sql = 'SELECT * FROM ' . CERBER_LOG_TABLE . ' ' . $where_sql . $order_sql . $limit_sql;
		$ret = cerber_db_get_results( $sql, MYSQL_FETCH_OBJECT );

		return $ret ?: array();
	}

	/**
	 * Log an event
	 *
	 * @param int $activity Activity ID
	 * @param string $login Login used or any additional information
	 * @param int $user_id User ID
	 * @param int $status Activity status
	 * @param string $ip Remote IP Address
	 *
	 * @return bool
	 *
	 * @since 3.0
	 */
	static function log( int $activity, string $login = '', int $user_id = 0, int $status = 0, string $ip = '' ): bool {
		global $user_ID;

		$wp_cerber = get_wp_cerber();

		$activity = absint( $activity );

		if ( empty( $user_id ) ) {
			$user_id = ( $user_ID ) ?: 0;
		}

		$user_id = absint( $user_id );

		$key = $activity . '-' . $user_id;

		if ( ( isset( self::$logged[ $key ] )
		       || isset( self::$ignore[ $activity ] ) )
		     && ! defined( 'CRB_ALLOW_MULTIPLE' ) ) {
			return false;
		}

		self::$logged[ $key ] = true;

		//$wp_cerber->setProcessed();

		if ( empty( $ip ) ) {
			$ip = cerber_get_remote_ip();
		}
		elseif ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		if ( cerber_is_ipv4( $ip ) ) {
			$ip_long = ip2long( $ip );
		}
		else {
			$ip_long = 1;
		}

		$stamp = microtime( true );

		$pos  = strpos( $_SERVER['REQUEST_URI'], '?' );
		$path = ( $pos ) ? substr( $_SERVER['REQUEST_URI'], 0, $pos ) : $_SERVER['REQUEST_URI'];
		$url  = strip_tags( $_SERVER['HTTP_HOST'] . $path );

		if ( ! $status ) {
			if ( $activity != 10 && $activity != 11 ) {
				$status = cerber_get_status( $ip, $activity );
			}
			elseif ( CRB_Globals::$blocked ) {
				$status = CRB_Globals::$blocked;
			}
		}

		$ac_bot = absint( CRB_Globals::$bot_status );
		$ac_by_user = absint( CRB_Globals::$user_id );

		$ctrl_txt = '';

		if ( $ctrl = CRB_Globals::get_ctrl_settings() ) {
			$ctrl_txt = implode( ',', $ctrl ); // @since 9.6.1.1
		}

		$details = $ctrl_txt . '|0|0|0|' . $url;

		$country = lab_get_country( $ip );
		$status = absint( $status ); // Note: @since 8.9.4 $status is stored in a separate "ac_status" column
		$login   = cerber_db_real_escape( $login );
		$details = cerber_db_real_escape( $details );
		$session_id = $wp_cerber->getRequestID();

		$ret = cerber_db_query( 'INSERT INTO ' . CERBER_LOG_TABLE . ' (ip, ip_long, user_login, user_id, stamp, activity, session_id, country, details, ac_status, ac_bot, ac_by_user) 
	    VALUES ("' . $ip . '",' . $ip_long . ',"' . $login . '",' . $user_id . ',"' . $stamp . '",' . $activity . ',"' . $session_id . '","' . $country . '","' . $details . '", ' . $status . ', ' . $ac_bot . ',' . $ac_by_user . ')' );

		if ( ! $ret ) {
			cerber_watchdog();
			$ret = false;
		}
		else {
			$ret = true;

			self::set_status( array( 'data_modified' => $stamp, 'hash' => sha1( $ip . $stamp . $session_id ) ) );
		}

		// Alerts for admin ---------------------------------------------------

		$alert_list = cerber_get_site_option( CRB_ALERTZ );

		if ( ! empty( $alert_list ) ) {

			$update_alerts = false;

			foreach ( $alert_list as $hash => $alert ) {

				$updated = false;

				// Check if all alert parameters match

				if ( ! empty( $alert[10] )
				     && $alert[10] != $status ) {
					continue;
				}

				if ( ! empty( $alert[1] )
				     && $alert[1] != $user_id
				     && $alert[1] != $ac_by_user ) {
					continue;
				}

				if ( ! empty( $alert[13] )
				     && ( $expires = absint( $alert[13] ) )
				     && $expires < time() ) {
					continue;
				}

				if ( ! empty( $alert[11] ) ) {
					if ( $alert[11] <= $alert[12] ) {
						continue;
					}

					$alert[12] ++;
					$updated = true;
				}

				if ( ! empty( $alert[0] ) ) {
					if ( ! in_array( $activity, $alert[0] ) ) {
						continue;
					}
				}

				if ( ! empty( $alert[3] )
				     && ( $ip_long < $alert[2] || $alert[3] < $ip_long ) ) {
					continue;
				}

				if ( ! empty( $alert[4] )
				     && $alert[4] != $ip ) {
					continue;
				}

				if ( ! empty( $alert[5] )
				     && $alert[5] != $login ) {
					continue;
				}

				if ( ! empty( $alert[9] )
				     && false === strpos( $url, $alert[9] ) ) {
					continue;
				}

				if ( ! empty( $alert[6] ) ) {
					$none = true;
					if ( false !== strpos( $ip, $alert[6] ) ) {
						$none = false;
					}
					elseif ( false !== mb_stripos( $login, $alert[6] ) ) {
						$none = false;
					}
					elseif ( $user_id ) {
						if ( ! $user = wp_get_current_user() ) {
							$user = crb_get_userdata( $user_id );
						}
						if ( false !== mb_stripos( $user->user_firstname, $alert[6] )
						     || false !== mb_stripos( $user->user_lastname, $alert[6] )
						     || false !== mb_stripos( $user->nickname, $alert[6] ) ) {
							$none = false;
						}
					}
					/*elseif ( $user_id && in_array( $user_id, $sub[8] ) ) {
						$none = false;
					}*/

					// No alert parameters match, continue to the next alert

					if ( $none ) {
						continue;
					}
				}

				// Alert parameters match, prepare and send an alert email

				$ac_lbl = crb_get_activity_label( $activity, $user_id, $ac_by_user, false );

				$status_lbl = '';

				if ( $status ) {
					$status_list = cerber_get_labels( 'status' ) + cerber_get_reason();
					if ( $status_lbl = $status_list[ $status ] ?? '' ) {
						$status_lbl = ' (' . $status_lbl . ')';
					}
				}

				$msg = array();

				$msg[] = __( 'Activity', 'wp-cerber' ) . ': ' . $ac_lbl . $status_lbl;
				$msg_masked = $msg;

				$coname = $country ? ' (' . crb_get_country_name( $country ) . ')' : '';

				$msg[] = __( 'IP address', 'wp-cerber' ) . ': ' . $ip . $coname;
				$msg_masked[] = __( 'IP address', 'wp-cerber' ) . ': ' . crb_mask_ip( $ip ) . $coname;

				if ( $user_id ) {
					$u = crb_get_userdata( $user_id );
					$msg[] = __( 'User', 'wp-cerber' ) . ': ' . $u->display_name;
					$msg_masked[] = __( 'User', 'wp-cerber' ) . ': ' . $u->display_name;
				}

				if ( $login ) {
					$msg[] = __( 'Username used', 'wp-cerber' ) . ': ' . $login;
					$msg_masked[] = __( 'Username used', 'wp-cerber' ) . ': ' . crb_mask_login( $login );
				}

				if ( ! empty( $alert['6'] ) ) {
					$msg[] = __( 'Search string', 'wp-cerber' ) . ': ' . $alert['6'];
					$msg_masked[] = __( 'Search string', 'wp-cerber' ) . ': ' . $alert['6'];
				}

				// Make links to the Activity log page and to delete this alert

				$args = array_intersect_key( crb_get_alert_params(), self::FILTERING_PARAMS );
				$keys = array_keys( $args );
				$values = array_values( $alert );
				$min_count = min( count( $keys ), count( $values ) );
				$activity_params = array_combine( array_slice( $keys, 0, $min_count ), array_slice( $values, 0, $min_count ) );

				// Note that the links are escaped for safety, and may not be properly rendered in a plain email.

				$more = __( 'View activity in the Dashboard', 'wp-cerber' ) . ': ' . crb_admin_link_for_html( 'activity', $activity_params );
				$more .= "\n\n" . __( 'To delete the alert, click here', 'wp-cerber' ) . ': ' . crb_admin_link_for_html( 'activity', [ 'unsubscribeme' => $hash ] );

				$ignore = $alert[14] ?? false;
				$use_email = ! empty( $alert[15] ) || ! empty( $alert[17] );
				$extra = array();

				if ( ! empty( $alert[17] ) ) {
					$extra = array( 'user_list' => array( $alert[17] ) );
				}

				$channels = array( 'email' => $use_email, 'pushbullet' => ! empty( $alert[16] ) );

				// Crucial for old alerts (no channels at all)

				if ( ! array_filter( $channels ) ) {
					$channels = array(); // All channels are in use
				}

				$sent = cerber_send_message( 'send_alert', array(
					'subj'        => $ac_lbl,
					'text'        => $msg,
					'text_masked' => $msg_masked,
					'more'        => $more,
					'ip'          => $ip
				), $channels, $ignore, $extra );

				if ( $sent && $updated ) {
					$update_alerts = true;
					$alert_list[ $hash ] = $alert;
				}

				break; // Just one notification letter per an HTTP request is allowed
			}

			if ( $update_alerts ) {
				if ( ! update_site_option( CRB_ALERTZ, $alert_list ) ) {
					cerber_error_log( 'Unable to update the list of alerts', 'ALERTS' );
				}
			}

		}

		// Lab --------------------------------------------------------------------

		if ( in_array( $activity, array( CRB_EV_CMS, CRB_EV_SCD, CRB_EV_SFD, 40, CRB_EV_PUR, CRB_EV_LDN, 55, 56, 71 ) ) ) {
			lab_save_push( $ip, $activity );
		}

		return $ret;
	}

	/**
	 * Do not log this activity
	 *
	 * @param int $activity Activity ID
	 *
	 * @return void
	 */
	static function set_ignore( int $activity ) {
		self::$ignore[ $activity ] = true;
	}

	/**
	 * Check if the given activities were logged during the current HTTP request
	 *
	 * @param int|int[] $what
	 *
	 * @return boolean
	 *
	 * @since 9.5.8
	 */
	static function is_logged( $what ) {
		if ( ! self::$logged ) {
			return false;
		}

		if ( is_array( $what ) ) {
			return ! empty( array_intersect( $what, self::$logged ) );
		}

		return in_array( $what, self::$logged );
	}

	/**
	 * Returns the list of activities logged during the current HTTP request
	 *
	 * @return array
	 *
	 * @since 9.6.6.10
	 */
	static function get_logged(): array {
		return self::$logged;
	}

	/**
	 * Is log empty?
	 *
	 * @return bool True if the activity log is empty (nothing is logged)
	 *
	 * @since 9.6.6.11
	 */
	static function is_empty(): bool {
		if ( self::get_status() ) {
			return false;
		}

		// cerber_db_is_empty()

		if ( cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' LIMIT 1' ) ) {
			self::set_status( [ 'not_empty' => time() ] );

			return false;
		}

		//self::set_status( [ 'not_empty' => 0 ] );

		return true;
	}

	/**
	 * Get global log status data
	 *
	 * @return array
	 *
	 * @since 9.6.6.11
	 */
	static function get_status(): array {
		$status = cerber_cache_get( CRB_ACT_HASH );

		if ( ! $status
		     || ! is_array( $status ) ) {
			$status = array();
		}

		return $status;
	}

	/**
	 * Set global log status data
	 *
	 * @param array $data Properties to set: ['name' => value]
	 *
	 * @return bool
	 *
	 * @since 9.6.6.11
	 */
	private static function set_status( array $data ): bool {

		$status = array_merge( self::get_status(), $data );

		return cerber_cache_set( CRB_ACT_HASH, $status );
	}

	/**
	 * Checks if the activity log has been modified since the last log update info stored in $status.
	 *
	 * @param array $status Data returned by self::get_status()
	 *
	 * @return bool True if the log has been modified since the last log update info stored in $status
	 *
	 * @see self::get_status() Use to save the status of the activity log for further comparing.
	 *
	 * @since 9.6.6.11
	 */
	static function is_modified( array $status ): bool {
		$current = self::get_status();

		if ( empty( $current['data_modified'] )
		     || empty( $status['data_modified'] ) ) {
			return true;
		}

		return ( $current['data_modified'] > $status['data_modified'] );
	}

	/**
	 * Checks if the activity log table has been modified since the specified time.
	 * It can be used for cache invalidation.
	 *
	 * @param float|int $stamp Unix timestamp
	 *
	 * @return bool True if modified
	 *
	 * @since 9.6.6.11
	 */
	static function is_modified_since( $stamp ): bool {
		if ( ! $status = self::get_status() ) {
			return true;
		}

		return ( $stamp < $status['data_modified'] ?? PHP_INT_MAX );
	}

	/**
	 * Retrieves log rows as an array of objects, using data from cache
	 * if the log was not updated from the previous invocation
	 *
	 * If the data is not in the cache, or if the cached data is outdated (based on the global log's
	 * modification status), the query is executed against the database, and
	 * the results are cached.
	 *
	 * @param string $query The SQL query to retrieve rows from the activity log.
	 * @param int|null &$num The number of rows in the resulting dataset. Passed by reference.
	 *
	 * @return array<object> An array of objects representing the log rows,
	 *                            or null if an error occurred (database query failed,
	 *                            could not get the number of found rows, or failed
	 *                            to set the cache).
	 *
	 * @since 9.6.6.11
	 */
	static function get_rows( string $query = '', ?int &$num = null ): array {

		$calculate_count = func_num_args() > 1; // We have to distinct calls with and without parameter $num

		$key = 'ac_cache_' . sha1( $query . '/' . ( $calculate_count ? 'A' : 'B' ) );

		if ( ! ( $cache = cerber_cache_get( $key ) )
		     || self::is_modified( $cache['status'] ?? [] ) ) {

			$cache = array();

			if ( ! $rows = cerber_db_get_results( $query, MYSQL_FETCH_OBJECT ) ) {
				$cache['rows'] = array();
				$cache['num'] = 0;
			}
			else {
				$cache['rows'] = $rows;

				if ( $calculate_count ) {
					$cache['num'] = (int) cerber_db_get_var( "SELECT FOUND_ROWS()" );
				}
				else {
					$cache['num'] = false;
				}
			}

			$cache['status'] = self::get_status();

			cerber_cache_set( $key, $cache, 24 * 3600 );
		}

		$num = $cache['num'];

		return $cache['rows'];
	}

	/**
	 * Parse query string arguments and create an SQL query for retrieving log rows from the activity log table
	 *
	 * @former cerber_activity_query()
	 *
	 * @param array $args Optional arguments to use them instead of using query string
	 *
	 * @return array
	 *
	 * @since 4.16
	 */
	static function parse_query( $args = array() ) {
		global $wpdb;

		$ret   = array_fill( 0, 9, '' );
		$where = array();

		$q = crb_admin_parse_query( array_keys( self::FILTERING_PARAMS ), $args );

		$falist = array();
		if ( ! empty( $q->filter_set ) ) {
			$falist = crb_get_filter_set( $q->filter_set );
		}
		elseif ( $q->filter_activity ) {
			$falist = crb_sanitize_int( $q->filter_activity );
		}
		if ( $falist ) {
			$where[] = 'log.activity IN (' . implode( ',', $falist ) . ')';
		}
		$ret[2] = $falist;

		if ( $q->filter_status ) {
			if ( $status = crb_sanitize_int( $q->filter_status ) ) {
				$where[] = 'log.ac_status IN (' . implode( ',', $status ) . ')';
			}
		}

		if ( $q->filter_ip ) {
			$range = cerber_any2range( $q->filter_ip );
			if ( is_array( $range ) ) {
				$where[] = '(log.ip_long >= ' . $range['begin'] . ' AND log.ip_long <= ' . $range['end'] . ')';
			}
			elseif ( cerber_is_ip_or_net( $q->filter_ip ) ) {
				$where[] = 'log.ip = "' . $q->filter_ip . '"';
			}
			else {
				$where[] = "ip = 'produce-no-result'";
			}
			$ret[3] = preg_replace( CRB_IP_NET_RANGE, ' ', $q->filter_ip );
		}

		if ( $q->filter_login ) {
			if ( strpos( $q->filter_login, '|' ) ) {
				$sanitize = preg_replace( '/["\'<>\/]+/', '', $q->filter_login );
				$logins = explode( '|', $sanitize );
				$where[] = '( log.user_login = "' . implode( '" OR log.user_login = "', $logins ) . '" )';
			}
			else {
				$where[] = $wpdb->prepare( 'log.user_login = %s', $q->filter_login );
			}
			$ret[4] = crb_escape_html( $q->filter_login );
		}

		if ( isset( $q->filter_user ) ) {
			if ( $q->filter_user == '*' ) {
				$where[] = 'log.user_id != 0';
				$ret[5]  = '';
			}
			elseif ( is_numeric( $q->filter_user ) ) {
				$user_id = absint( $q->filter_user );
				//$where[] = 'log.user_id = ' . $user_id;
				$where[] = '( log.user_id = ' . $user_id . ' OR log.ac_by_user =' . $user_id . ')';
				$ret[5]  = $user_id;
			}
		}

		if ( $q->search_activity ) {
			$search = stripslashes( $q->search_activity );
			$ret[6] = crb_escape_html( $search );
			$search = '%' . $search . '%';

			$escaped = cerber_db_real_escape( $search );
			$w = array();
			$w[] = 'log.user_login LIKE "' . $escaped . '"';
			$w[] = 'log.ip LIKE "' . $escaped . '"';

			$where[] = '(' . implode( ' OR ', $w ) . ')';
		}

		if ( $q->filter_sid ) {
			$ret[7] = $sid = preg_replace( '/[^\w]/', '', $q->filter_sid );
			$where[] = 'log.session_id = "' . $sid . '"';
		}

		if ( $q->search_url ) {
			$search  = stripslashes( $q->search_url );
			$ret[8]  = crb_escape_html( $search );
			$where[] = 'log.details LIKE "' . cerber_db_real_escape( '%' . $search . '%' ) . '"';
		}

		if ( $q->filter_country ) {
			$country = substr( $q->filter_country, 0, 3 );
			$ret[9]  = crb_escape_html( $country );
			$where[] = 'log.country = "' . cerber_db_real_escape( $country ) . '"';
		}

		if ( $begin = $q->filter_time_begin ) {
			$ret[10] = absint( $begin );
			$where[] = 'log.stamp >= ' . absint( $begin );
		}

		if ( $end = $q->filter_time_end ) {
			$ret[11] = absint( $end );
			$where[] = 'log.stamp <= ' . absint( $end );
		}

		$where = ( ! empty( $where ) ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Limits, if specified
		$per_page = ( isset( $args['per_page'] ) ) ? absint( $args['per_page'] ) : crb_admin_get_per_page();
		$ret[1]   = $per_page;

		$calc = ( empty( $args['no_navi'] ) ) ? 'SQL_CALC_FOUND_ROWS' : '';

		if ( $per_page ) {
			$limit = cerber_get_sql_limit( $per_page );
			$sql = 'SELECT ' . $calc . ' * FROM ' . CERBER_LOG_TABLE . ' log ' . $where . ' ORDER BY stamp DESC ' . $limit;
		}
		else {
			$sql = 'SELECT ' . $calc . '  log.*,u.display_name,u.user_login ulogin FROM ' . CERBER_LOG_TABLE . ' log LEFT JOIN ' . $wpdb->users . " u ON (log.user_id = u.ID) {$where} ORDER BY stamp DESC"; // "ORDER BY" is mandatory!
		}

		$ret[0] = $sql;

		return $ret;
	}

	/**
	 * Deletes rows from the activity table using specified values of the given fields.
	 *
	 * @param array $key_fields An associative array of conditional fields for the WHERE clause.
	 *                          Format: ['column_name' => 'value', ...]
	 *
	 * @return int The number of affected rows
	 *
	 * @since 9.6.6.12
	 */
	static function delete( $key_fields = array() ): int {

		if ( ! $key_fields ) {
			return 0;
		}

		if ( $ret = cerber_db_delete_rows( CERBER_LOG_TABLE, $key_fields ) ) {
			cerber_cache_set( CRB_ACT_HASH, [] );
		}

		return $ret;
	}
}