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

/**
 *
 * Periodically and occasionally used routines
 *
 */

require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

/**
 * Send email notification if  plugin is available
 *
 * @param bool $no_check_freq If true, ignore the frequency setting
 * @param bool $no_check_history If true, do not check sending history. Use for testing.
 * @param bool $result The results of sending
 * @param array $info Error messages if any
 *
 * @return integer|false false if there is no information about updates, otherwise the number of messages sent
 *
 * @since 9.4.3
 */
function crb_plugin_update_notifier( $no_check_freq = false, $no_check_history = false, &$result = false, &$info = array() ) {

	if ( ! crb_get_settings( 'notify_plugin_update' ) ) {
		return false;
	}

	$updates = get_site_transient( 'update_plugins' );
	$interval = ( ! lab_lab() ) ? 24 : (int) crb_get_settings( 'notify_plugin_update_freq' );
	$interval = HOUR_IN_SECONDS * ( ( $interval < 1 ) ? 1 : $interval );

	$prev = cerber_get_set( 'plugin_update_alerting_status' );

	if ( ! $no_check_freq
	     && isset( $prev[0] )
	     && $prev[0] > ( time() - $interval ) ) {
		return false;
	}

	if ( ! $updates
	     || empty( $updates->last_checked )
	     || empty( $updates->response )
	     || ( $updates->last_checked < ( time() - $interval ) ) ) {

		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		$updates = get_site_transient( 'update_plugins' );
	}

	$errors = 0;
	$sent = 0;

	if ( empty( $updates->response ) ) {
		cerber_update_set( 'plugin_update_alerting_status',
			array(
				time(),
				( $updates->last_checked ?? 0 ),
				( $updates->checked ?? 0 ),
				$errors,
				$sent
			) );

		$info[] = __( 'No updates found.', 'wp-cerber' );

		if ( empty( $updates->checked ) ) {
			$info[] = __( 'It seems outgoing Internet connections are not allowed on your website.', 'wp-cerber' );
		}

		return false;
	}

	$history = cerber_get_set( 'plugin_update_alerting' );

	if ( ! is_array( $history ) ) {
		$history = array();
	}

	$brief = ( ! lab_lab() ) ? 0 : crb_get_settings( 'notify_plugin_update_brf' );
	$active_plugins = get_option( 'active_plugins' );
	$result = false;

	require_once( ABSPATH . 'wp-admin/includes/plugin.php' ); // get_plugin_data()

	foreach ( $updates->response as $plugin => $new_data ) {
		if ( ! $no_check_history && isset( $history[ $plugin ][ $new_data->new_version ] ) ) {
			continue;
		}

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

		$name = htmlspecialchars_decode( $plugin_data['Name'] );

		$notes = array();

		if ( ! empty( $new_data->requires )
		     && ! crb_wp_version_compare( $new_data->requires ) ) {
			/* translators: Here %s is a version number like 6.1 */
			$notes[] = '[!] ' . sprintf( __( 'This update requires WordPress version %s or higher, you have %s', 'wp-cerber' ), $new_data->requires, ( $brief ? '*' : cerber_get_wp_version() ) );
		}

		if ( ! empty( $new_data->requires_php )
		     && version_compare( $new_data->requires_php, phpversion(), '>' ) ) {
			/* translators: Here %s is a version number like 6.1 */
			$notes[] = '[!] ' . sprintf( __( 'This update requires PHP version %s or higher, you have %s', 'wp-cerber' ), $new_data->requires_php, ( $brief ? '*' : phpversion() ) );
		}

		if ( ! empty( $new_data->tested )
		     && crb_wp_version_compare( $new_data->tested, '>' ) ) {
			$notes[] = '[!] ' . __( 'This update has not been tested with your version of WordPress', 'wp-cerber' );
		}

		$msg = array(
			__( 'There is an update to the plugin installed on your website.', 'wp-cerber' ),
		);

		if ( $notes ) {
			$msg = array_merge( $msg, $notes );
		}

		$active = ( in_array( $plugin, $active_plugins ) ) ? __( 'Yes', 'wp-cerber' ) : __( 'No', 'wp-cerber' );

		$msg = array_merge( $msg, array(
			__( 'Website:', 'wp-cerber' ) . ' ' . crb_get_blogname_decoded(),
			__( 'Plugin:', 'wp-cerber' ) . ' ' . $name,
			__( 'Active:', 'wp-cerber' ) . ' ' . $active,
			__( 'Installed version:', 'wp-cerber' ) . ' ' . ( $brief ? '*' : crb_boring_escape( $plugin_data['Version'] ) ),
			__( 'New version:', 'wp-cerber' ) . ' ' . $new_data->new_version,
		) );

		if ( ! empty( $new_data->tested ) ) {
			$msg[] = __( 'Tested up to:', 'wp-cerber' ) . ' WordPress ' . $new_data->tested;
		}

		$msg[] = __( 'Plugin page:', 'wp-cerber' ) . ' ' . $new_data->url;

		if ( ! $brief ) {
			$msg[] = __( 'Manage plugins on your website:', 'wp-cerber' ) . ' ' . admin_url( 'plugins.php' );
		}

		$args = ( ! lab_lab() ) ? array() : array( 'recipients_setting' => 'notify_plugin_update_to' );

		$result = cerber_send_message( 'generic', array(
			/* translators: Here %s is a name of software package (module). */
			'subj' => sprintf( __( 'A new version of %s is available', 'wp-cerber' ), $name ),
			'text' => $msg
		), array( 'email' => 1, 'pushbullet' => 0 ), true, $args );

		if ( $result ) {
			$sent ++;
			$history[ $plugin ][ $new_data->new_version ] = time();
			if ( ! $no_check_history ) {
				cerber_update_set( 'plugin_update_alerting', $history );
			}
		}
		else {
			$errors ++;
			cerber_add_issue( __FUNCTION__, 'Unable to send a notification email. Please check the notification settings.' );
		}
	}

	cerber_update_set( 'plugin_update_alerting_status',
		array(
			time(),
			( $updates->last_checked ?? 0 ),
			( $updates->checked ?? 0 ),
			$errors,
			$sent,
			( is_array( $result ) ? $result : 0 )
		) );

	return $sent;
}

/**
 * If WordPress core find an update earlier than WP Cerber,
 * notify admin (ASAP) using postponed tasks
 *
 */
add_action( 'set_site_transient_update_plugins', function () {
	cerber_update_set( 'event_wp_found_updates', 1, null, false );
} );

/**
 * @return void
 *
 * @since 9.4.2.4
 */
function crb_log_maintainer() {

	// Get non-cached settings since they can be filled with default values in case of a DB error

	if ( ! $settings = crb_get_settings( '', true, false ) ) {
		cerber_add_issue( __FUNCTION__,	'Log processing aborted. Unable to load WP Cerber settings from the website database.', array( 'details' => cerber_db_get_errors() ) );

		return;
	}

	// Settings are OK

	$time = time();

	$days = absint( $settings['keeplog'] ) ?: cerber_get_defaults( 'keeplog' );  // @since 8.5.6
	$days_auth = absint( $settings['keeplog_auth'] ?? false ) ?: $days; // It may be not configured by the admin yet, since it's introduced in 8.5.6

	if ( $days == $days_auth ) {
		CRB_Activity::delete( array( 'stamp' => array( '<', $time - $days * 24 * 3600 ) ) );
	}
	else {
		CRB_Activity::delete( [ 'user_id' => 0, 'stamp' => array( '<', $time - $days * 24 * 3600 ) ] );
		CRB_Activity::delete( [ 'user_id' => array( '!=', 0 ), 'stamp' => array( '<', $time - $days_auth * 24 * 3600 ) ] );
	}

	$days = absint( $settings['tikeeprec'] ) ?: cerber_get_defaults( 'tikeeprec' );  // @since 8.5.6
	$days_auth = absint( $settings['tikeeprec_auth'] ?? false ) ?: $days; // It may be not configured by the admin yet, since it's introduced in 8.5.6

	if ( $days == $days_auth ) {
		cerber_db_query( 'DELETE FROM ' . CERBER_TRAF_TABLE . ' WHERE stamp < ' . ( $time - $days * 24 * 3600 ) );
	}
	else {
		cerber_db_query( 'DELETE FROM ' . CERBER_TRAF_TABLE . ' WHERE user_id =0 AND stamp < ' . ( $time - $days * 24 * 3600 ) );
		cerber_db_query( 'DELETE FROM ' . CERBER_TRAF_TABLE . ' WHERE user_id !=0 AND stamp < ' . ( $time - $days_auth * 24 * 3600 ) );
	}

	// Other, non-log stuff

	cerber_db_query( 'DELETE FROM ' . CERBER_LAB_IP_TABLE . ' WHERE expires < ' . $time );

	if ( ( $settings['trashafter-enabled'] ?? 0 )
	     && $after = absint( crb_get_settings( 'trashafter' ) ) ) {

		$time = time() - DAY_IN_SECONDS * $after;

		if ( $list = get_comments( array( 'status' => 'spam' ) ) ) {
			foreach ( $list as $item ) {
				if ( $time > strtotime( $item->comment_date_gmt ) ) {
					wp_trash_comment( $item->comment_ID );
				}
			}
		}
	}
}

/**
 * Updating old activity log records to the new row format (introduced in v 3.1)
 *
 * @since 4.0
 */
function crb_once_upgrade_log() {

	if ( ! $ips = cerber_db_get_col( 'SELECT DISTINCT ip FROM ' . CERBER_LOG_TABLE . ' WHERE ip_long = 0 LIMIT 50' ) ) {
		return;
	}

	foreach ( $ips as $ip ) {
		$ip_long = cerber_is_ipv4( $ip ) ? ip2long( $ip ) : 1;
		cerber_db_query( 'UPDATE ' . CERBER_LOG_TABLE . ' SET ip_long = ' . $ip_long . ' WHERE ip = "' . $ip .'" AND ip_long = 0');
	}
}

/**
 * Copying last login data to the user sets in bulk
 *
 * @return void
 *
 * @since 9.4.2
 */
function crb_once_upgrade_cbla() {
	$status = cerber_get_set( 'cerber_db_status' ) ?: array();
	$lal = $status['lal'] ?? false;

	if ( 'done' == $lal ) {
		return;
	}

	$table = cerber_get_db_prefix() . CERBER_SETS_TABLE;

	if ( 'progress' != $lal ) {
		if ( ! cerber_db_query( 'UPDATE ' . $table . ' SET argo = 1 WHERE the_key = "' . CRB_USER_SET . '"' ) ) {
			$status['lal'] = 'done';
			cerber_update_set( 'cerber_db_status', $status );

			return;
		}
		$status['lal'] = 'progress';
		cerber_update_set( 'cerber_db_status', $status );
	}
	elseif ( ! cerber_db_get_var( 'SELECT the_key FROM ' . $table . ' WHERE the_key = "' . CRB_USER_SET . '" AND argo = 1 LIMIT 1' ) ) {
		$status['lal'] = 'done';
		cerber_update_set( 'cerber_db_status', $status );

		return;
	}

	if ( ! $users = cerber_db_get_col( 'SELECT the_id FROM ' . $table . ' WHERE the_key = "' . CRB_USER_SET . '" AND argo = 1 LIMIT 1000' ) ) {

		return;
	}

	cerber_cache_disable();

	foreach ( $users as $user_id ) {
		crb_get_last_user_login( $user_id );
		cerber_db_query( 'UPDATE ' . $table . ' SET argo = 0 WHERE the_key = "' . CRB_USER_SET . '" AND the_id = ' . $user_id );
	}

	if ( $db_errors = cerber_db_get_errors() ) {
		$db_errors = array_slice( $db_errors, 0, 10 );
		cerber_admin_notice( 'Database errors occurred while upgrading user sets to a new format.' );
		cerber_admin_notice( $db_errors );
	}
}

/**
 * Handles information about a given plugin
 *
 * @since 9.6.2.6
 */
class CRB_Plugin {
	/**
	 * The last network/repo error if any
	 *
	 * @var string
	 */
	private static $last_error;


	/**
	 * Generates an end-user plugin status report
	 *
	 * @param string $slug The plugin slug.
	 * @param bool $refresh
	 *
	 * @return array An array containing the plugin status level and the status messages.
	 */
	static function get_plugin_status( string $slug, bool $refresh = false ): array {

		$status = array();

		if ( crb_get_settings( 'scan_abon_pl' ) ) {
			$period = crb_get_settings( 'scan_abon_pl_period' );
			$status['plugin_abnd'] = self::get_plugin_repo_status( $slug, $period, $refresh );
		}

		return $status;
	}

	/**
	 * Retrieves plugin ownership data using wordpress.org plugin API and update history of changes if any occurs
	 *
	 * @param string $slug Plugin slug
	 *
	 * @return array|WP_Error
	 */
	static function get_plugin_owner_status( string $slug ) {

		$fresh_data = self::get_plugin_authors( $slug );

		if ( crb_is_wp_error( $fresh_data ) ) {
			return $fresh_data;
		}

		$plugin_data = self::get_plugin_data( $slug );
		$update = false;
		$ownership = $plugin_data['ownership'] ?? false;

		if ( $ownership && is_array( $ownership ) ) {
			if ( $ownership['last']['owner'] != $fresh_data['owner'] ) {

				$update = true; // New owner

				if ( count( $ownership['history'] ) > 50 ) {
					ksort( $ownership['history'] );
					$ownership['history'] = array_slice( $ownership['history'], - 50 );
				}
			}
		}
		else {
			$ownership = array();
			$update = true;
		}

		if ( $update ) {
			$ownership['history'][ time() ] = $fresh_data;
			$ownership['last'] = $fresh_data;
			self::update_plugin_data( $slug, array( 'ownership' => $ownership ) );
		}

		return $ownership;
	}
	/**
	 * Retrieves the author of a plugin from the WordPress.org plugin repository.
	 *
	 * @param string $slug The slug of the plugin.
	 *
	 * @return array|WP_Error The author of the plugin, or WP_Error object if there is an error.
	 */
	static function get_plugin_authors( string $slug ) {

		$plugin_info = plugins_api( 'plugin_information', array( 'slug' => $slug ) );

		if ( crb_is_wp_error( $plugin_info ) ) {
			return $plugin_info;
		}

		$author = '';

		if ( empty( $plugin_info->author_profile ) ) {
			return new WP_Error( 'invalid_plugin_api', 'Unable to retrieve authorship info due to invalid plugin API response received from wordpress.org.' );
		}

		foreach ( $plugin_info->contributors as $contributor => $data ) {
			if ( $data['profile'] == $plugin_info->author_profile ) {
				$author = $contributor;
				break;
			}
		}

		if ( ! $author ) {
			$author = $plugin_info->author_profile; // Way around
		}

		return array( 'owner' => $author, 'author_profile' => $plugin_info->author_profile, 'contributors' => $plugin_info->contributors );
	}

	/**
	 * Create a plugin abandonment status message based on the information in the plugin repo
	 *
	 * @param string $slug Plugin slug.
	 * @param int $period Number of months to consider the plugin as being abandoned
	 * @param bool $refresh Force to refresh data stored in the local DB
	 *
	 * @return array An array containing the plugin status level and the status messages.
	 */
	static function get_plugin_repo_status( string $slug, int $period, bool $refresh = false ): array {

		$status = array();
		$status['plugin_slug'] = $slug;
		$status['updated'] = 0;

		$one_month = 30 * DAY_IN_SECONDS;

		// Threshold in UTC rounded to midnight

		$threshold = floor( ( time() - $period * $one_month ) / DAY_IN_SECONDS ) * DAY_IN_SECONDS;

		$data = self::get_plugin_data( $slug );

		// Update stored plugin data if needed

		$update = false;

		if ( ! $repo_data = $data['repo'] ?? false ) {
			$update = true;
		}
		elseif ( $err_code = $repo_data['err_code'] ?? false ) {
			if ( in_array( $err_code, array( CRB_PL722, CRB_PL724 ) )
			     || $repo_data['updated_uts'] < ( time() - 24 * 3600 ) ) {
				$update = true;
			}
		}
		elseif ( $repo_data['updated_uts'] < $threshold ) {
			$update = true;
		}
		elseif ( $repo_data['modified_uts'] <= $threshold ) { // Abandoned candidate, double check it
			$update = true;
		}

		if ( $refresh
		     || ( $update
		          && ! ( ( $repo_data['updated_uts'] ?? 0 ) > ( time() - 12 * 3600 ) ) ) ) { // Reasonable threshold

			$repo_data = self::update_plugin_repo_data( $slug );

			$status['updated'] = 1;
		}

		// Generating plugin status message/report

		$msg = '';

		if ( $err_code = $repo_data['err_code'] ?? false ) {
			if ( in_array( $err_code, array( CRB_PL722, CRB_PL723 ) ) ) {
				$level = CRB_SEV_NOTICE;
				$code = CRB_SA221;
			}
			else {
				$level = CRB_SEV_CRITICAL;
				$code = CRB_SA222;
			}

			$msg = crb_get_error_msg( $err_code ) . ' ' . self::$last_error;
		}
		else {

			// We got valid plugin data from the repo

			if ( $repo_data['modified_uts'] < $threshold ) {
				$level = CRB_SEV_WARNING;
				$code = CRB_SA223;

				$time_diff = time() - $repo_data['modified_uts'];
				$one_year = 365 * DAY_IN_SECONDS;

				if ( $time_diff > $one_year ) {
					$msg = __( 'It appears this plugin is abandoned, as it has not received any updates for over a year.', 'wp-cerber' );
				}
				elseif ( $time_diff >= 2 * $one_month ) {
					$msg = __( 'It appears this plugin is abandoned, as it has not received any updates for several months.', 'wp-cerber' );
				}
				elseif ( $time_diff >= $one_month ) {
					$msg = __( 'It appears this plugin is abandoned, as it has not received any updates for over a month.', 'wp-cerber' );
				}

				/* translators: Here %s is the date. */
				$msg .= ' ' . sprintf( __( 'The last update was at %s', 'wp-cerber' ), cerber_date( $repo_data['modified_uts'], false ) );
			}
			else {
				$msg = 'OK';
				$level = CRB_SEV_OK;
				$code = CRB_SA224;
			}
		}

		// Ready to show to end-user

		$status['sts_code'] = $code; // Status ID
		$status['level'] = $level; // Severity
		$status['status_msg'] = $msg; // Text message
		$status['repo_data'] = $repo_data;

		return $status;
	}

	/**
	 * Retrieves data from the repo and updates the plugin data in the database for the given plugin slug.
	 *
	 * @param string $slug The slug of the plugin.
	 *
	 * @return array The plugin data retrieved from the repo.
	 */
	private static function update_plugin_repo_data( string $slug ): array {

		$repo_data = array();

		$result = self::retrieve_plugin_repo_data( $slug );

		if ( crb_is_wp_error( $result ) ) {
			$repo_data['err_code'] = $result->get_error_code();
		}
		else {
			$result = self::sanitize( $result );
			$raw = $result;
			$repo_data['modified_date'] = $result['dateModified'] ?? '';
			$repo_data['modified_uts'] = $repo_data['modified_date'] ? strtotime( $repo_data['modified_date'] ) : '';
			$repo_data['last_version'] = $last_ver = $result['softwareVersion'] ?? '';

			$repo_data['raw_data'] = $raw;

			// Save history of changes

			$log = $repo_data['raw_log'] ?? false;

			if ( ! is_array( $log ) ) {
				$log = array();
			}

			if ( ! crb_array_search_row( $log, 'vrs', $last_ver ) ) {
				$log[] = array( 'vrs' => $last_ver, 'raw' => $repo_data['raw_data'] );
				$log = array_slice( $log, -10 );
			}

			$repo_data['raw_log'] = $log;
		}

		$repo_data['updated_uts'] = time();

		self::update_plugin_data( $slug, array( 'repo' => $repo_data ) );

		return $repo_data;
	}

	/**
	 * Sanitize and convert all values to strings in the given multi-dimensional array and limit total elements
	 *
	 * @param array &$data The input array to be sanitized.
	 * @param int $max_elements The maximum number of elements allowed in the array.
	 * @param int $element_count The current count of elements in the array.
	 * @return array The sanitized and limited array.
	 *
	 */
	static function sanitize( array &$data, int $max_elements = 100, int &$element_count = 0 ): array {
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			if ( $element_count >= $max_elements ) {
				break;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize( $value, $max_elements, $element_count );
			}
			else {
				$value = (string) $value;
				$sanitized[ $key ] = substr( strip_tags( $value ), 0, 300 );
			}

			$element_count ++;
		}

		return $sanitized;
	}

	/**
	 * Returns the plugin data stored locally in the DB.
	 *
	 * @param string $slug The slug of the plugin.
	 *
	 * @return array Plugin data as an array, an empty array if no data.
	 */
	static function get_plugin_data( string $slug ): array {

		$key = substr( 'pl_data_' . $slug, 0, 255 );
		$data = cerber_get_set( $key );

		if ( ! $data || ! is_array( $data ) ) {
			$data = array();
		}

		return $data;
	}

	/**
	 * Updates the plugin data with the given update array.
	 *
	 * @param string $slug The slug of the plugin to update.
	 * @param array $update The update array to merge with the existing plugin data.
	 *
	 * @return bool Returns true if the plugin data is updated successfully, otherwise false.
	 */
	static function update_plugin_data( string  $slug, array $update ) {

		$key = substr( 'pl_data_' . $slug, 0, 255 );
		$data = cerber_get_set( $key );

		if ( ! $data || ! is_array( $data ) ) {
			$data = array();
		}

		$data = array_merge( $data, $update );

		return cerber_update_set( $key, $data );
	}

	/**
	 * Retrieves plugin data from the WP.ORG repository by the given plugin slug (which is the plugin folder).
	 *
	 * @param string $slug The slug of the plugin.
	 *
	 * @return array|WP_Error Returns the extracted JSON-LD plugin data from the plugin webpage, or WP_Error object if there is an error.
	 */
	static function retrieve_plugin_repo_data( string $slug ) {

		if ( ! $slug = preg_replace( '/[^a-z\-\d_]/i', '', $slug ) ) {
			return new WP_Error( CRB_PL721 );
		}

		$network = new CRB_Net();

		$url = 'https://wordpress.org/plugins/' . $slug . '/';

		$result = $network->http_get( array(
			'host' => 'wordpress.org',
			'path' => '/plugins/' . $slug . '/'
		),
			array(
				CURLOPT_FOLLOWLOCATION => false,
			),
			true );

		if ( crb_is_wp_error( $result ) ) {

			if ( $network->is_host_rate_limited() ) {
				$err_code = CRB_PL722;
			}
			else {
				switch ( $network->get_code() ) {
					case 404: // No plugin in the repo
					case 301: // No plugin in the repo
						$err_code = CRB_PL723;
						break;
					default:
						$err_code = CRB_PL724;
						self::$last_error = 'URL: ' . $url . ', ERROR: ' . $result->get_error_message();;
				}
			}

			return new WP_Error( $err_code );
		}

		$html = $network->get_body();

		unset( $network );

		// Extract data from the HTML content

		$json = self::extract_ld_json( $html );

		if ( crb_is_wp_error( $json ) ) {
			return $json;
		}

		return self::extract_wp_plugin_data( $json, $slug );
	}

	/**
	 * Extracts plugin data from an array of JSON strings.
	 *
	 * @param array $payload An array of JSON strings to look for plugin data.
	 *
	 * @return array|WP_Error Returns an array containing plugin data, or a WP_Error object if no valid plugin data found.
	 */
	private static function extract_wp_plugin_data( array $payload ) {

		foreach ( $payload as $json ) {
			$decoded = json_decode( $json, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				continue;
			}

			// WP.ORG format

			if ( ( $decoded[0]['applicationCategory'] ?? '' ) === 'Plugin' &&
			     ( $decoded[0]['operatingSystem'] ?? '' ) === 'WordPress' ) {
				return $decoded[0];
			}
		}

		return new WP_Error( CRB_PL725 );

	}

	/**
	 * Extracts JSON-LD data from HTML content.
	 *
	 * @param string $html_content The HTML content to extract JSON-LD data from.
	 *
	 * @return array|WP_Error The extracted JSON-LD data as an associative array, or a WP_Error object if extraction fails.
	 *
	 * @since 9.6.2.4
	 */
	private static function extract_ld_json( string $html_content ) {

		preg_match( '/<head>(.*?)<\/head>/is', $html_content, $matches_head );

		if ( empty( $matches_head[1] ) ) {
			return new WP_Error( CRB_PL726 );
		}

		$head_content = $matches_head[1];

		preg_match_all( '/<script type="application\/ld\+json">(.*?)<\/script>/is', $head_content, $matches_script );

		if ( empty( $matches_script[1] ) ) {
			return new WP_Error( CRB_PL727 );
		}

		return $matches_script[1];
	}
}

/**
 * Check the server environment and the WP Cerber configuration for possible issues
 *
 * @return void
 *
 * @since 9.5.1
 */
function cerber_issue_monitor() {
	static $checkers = array();

	if ( ! $checkers ) {
		$checkers = array(
			'cerber-security' => function () {
				$results = new WP_Error;

				if ( $issue = cerber_extract_remote_ip( true ) ) {
					$results->add( 'noipaddr', $issue, array( 'doc_page' => 'https://wpcerber.com/wordpress-ip-address-detection/' ) );
				}

				// -------

				$repo_link = '[ <a href="https://wpcerber.com/automatic-updates-for-wp-cerber/" target="_blank">Know more</a> ]';

				if ( crb_get_settings( 'cerber_sw_repo' )
				     && ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) ) {

					$issue = '';

					if ( defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
						if ( false === strpos( WP_ACCESSIBLE_HOSTS, 'downloads.wpcerber.com' )
						     && false === strpos( WP_ACCESSIBLE_HOSTS, '*.wpcerber.com' ) ) {

							$const_def = empty( WP_ACCESSIBLE_HOSTS ) ? __( 'Currently, the WP_ACCESSIBLE_HOSTS constant contains no allowed hosts', 'wp-cerber' ) : sprintf( __( 'Currently, the WP_ACCESSIBLE_HOSTS constant is defined as: %s', 'wp-cerber' ), crb_escape_html( WP_ACCESSIBLE_HOSTS ) );
							$issue = __( 'To enable WP Cerber updates, add "downloads.wpcerber.com" to the WP_ACCESSIBLE_HOSTS constant', 'wp-cerber' ) . ' ' . $repo_link . '<p>' . $const_def . '</p>';
						}
					}
					else {
						$issue = __( 'To enable WP Cerber updates, add the WP_ACCESSIBLE_HOSTS constant defined as "downloads.wpcerber.com" to your wp-config.php file', 'wp-cerber' ) . ' ' . $repo_link;
					}

					if ( $issue ) {
						$results->add( 'norepo', $issue );
					}
				}

				// -------

				if ( crb_get_settings( 'cerber_sw_auto' ) ) {
					crb_load_dependencies( 'wp_is_auto_update_enabled_for_type' );
					if ( ! wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
						$auto_const = ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) ? ' The WordPress AUTOMATIC_UPDATER_DISABLED constant is defined.' : '';
						$results->add( 'noauto', 'WP Cerber does not get automatic updates because automatic updates for plugins on this website are disabled. ' . $auto_const.' ' . $repo_link );
					}
				}

				// -------

				if ( cerber_get_mode() != crb_get_settings( 'boot-mode' ) ) {
					$results->add( 'booterr', 'WP Cerber is initialized in a different mode that does not match the plugin settings. Check the "Load security engine" setting.' );
				}

				// -------

				if ( $ler = cerber_get_set( 'last_email_error' ) ) {
					if ( $ler[0] > ( time() - WEEK_IN_SECONDS ) ) {

						$txt = $ler[2] . ' Error #' . $ler[3];
						$txt .= ( $ler[4] ? '. SMTP server: ' . $ler[4] : '' );
						$txt .= ( $ler[5] ? '. SMTP username: ' . $ler[5] : '' );
						$txt .= ( $ler[6] ? '. Recipient(s): ' . implode( ',', $ler[6] ) : '' );
						$txt .= ( $ler[7] ? '. Subject: "' . $ler[7] . '"' : '' );
						$txt .= '. Date: ' . cerber_date( $ler[0] );

						$results->add( 'emailerr', 'An error occurred while sending email. ' . $txt );
					}

					cerber_delete_set( 'last_email_error' );
				}

				return $results;
			},
			'cerber-integrity' => function () {
				if ( defined( 'CERBER_FOLDER_PATH' ) ) {
					return cerber_get_my_folder();
				}

				return false;
			},
			'cerber-shield' => function () {
				return CRB_DS::check_errors();
			},
			'*' => function () {
				$results = new WP_Error;

				if ( ! crb_get_settings( 'tienabled' ) ) {
					$results->add( 'noti', 'Traffic Inspector is disabled' );
				}

				$ex_list = get_loaded_extensions();

				if ( ! in_array( 'mbstring', $ex_list ) || ! function_exists( 'mb_convert_encoding' ) ) {
					$results->add( 'nombstring', 'Required PHP extension <b>mbstring</b> is not enabled on this website. Some plugin features do work properly. Please enable the PHP mbstring extension (multibyte string support) in your hosting control panel.' );
				}

				if ( ! in_array( 'curl', $ex_list ) ) {
					$results->add( 'nocurl', 'cURL PHP library is not enabled on this website.' );
				}
				else {
					$curl = @curl_init();

					if ( ! $curl
					     && ( $err_msg = curl_error( $curl ) ) ) {
						$results->add( 'nocurl', $err_msg );
					}

					curl_close( $curl );
				}

				return $results;
			}
		);
	}

	$notices = array();
	$page = crb_admin_get_page();

	// Part 1. We periodically run all the checks

	if ( cerber_get_set( '_check_env', 0, false ) < ( time() - 120 ) ) {

		cerber_update_set( '_check_env', time(), 0, false );

		foreach ( $checkers as $page_id => $check ) {
			if ( ! is_callable( $check ) || $page == $page_id ) {
				continue;
			}

			if ( crb_is_wp_error( $test = call_user_func( $check ) ) ) {

				$notices = array_merge( $notices, cerber_format_issue( $test ) );
			}
		}
	}

	// Part 2. Critical checks on a specific page (context)

	if ( ( $check = $checkers[ $page ] ?? false )
	     && is_callable( $check )
	     && crb_is_wp_error( $test = call_user_func( $check ) ) ) {

		$notices = array_merge( $notices, cerber_format_issue( $test ) );
	}

	// Part 3. Critical things we monitor continuously

	if ( version_compare( CERBER_REQ_PHP, phpversion(), '>' ) ) {
		$notices['php'] = sprintf( __( 'WP Cerber requires PHP version %s or higher, but your web server is currently running PHP %s.', 'wp-cerber' ), CERBER_REQ_PHP, phpversion() );
	}

	if ( ! crb_wp_version_compare( CERBER_REQ_WP ) ) {
		$notices['wordpress'] = sprintf( __( 'WP Cerber requires WordPress version %s or higher. Your WordPress version is %s. Please update your WordPress to the latest version.', 'wp-cerber' ), CERBER_REQ_WP, cerber_get_wp_version() );
	}

	if ( defined( 'CERBER_CLOUD_DEBUG' ) && CERBER_CLOUD_DEBUG ) {
		$notices['cloud'] = 'Diagnostic logging of cloud requests is enabled (CERBER_CLOUD_DEBUG).';
	}

	if ( $notices ) {

		foreach ( $notices as $code => $notice ) {
			cerber_add_issue( $code, $notice );
		}

		$notices = array_map( function ( $e ) {
			return '<b>' . __( 'Warning!', 'wp-cerber' ) . '</b> ' . $e;
		}, $notices );

		cerber_admin_notice( $notices );
	}
}

/**
 * Formats messages from a WP_Error object into an associative array.
 *
 * This function retrieves issue codes and their corresponding messages from a
 * provided WP_Error object. If issue data includes a `doc_page` URL, a link to
 * the WP Cerber documentation is appended to the error message.
 *
 * @param WP_Error $issues An instance of WP_Error containing error codes, messages,
 *                         and optional error data.
 *
 * @return array An associative array where the keys are error codes and the values
 *               are formatted error messages. Each message may include a link to
 *               documentation if a `doc_page` is present in the error data.
 *
 * @since 9.6.3.3
 */
function cerber_format_issue( $issues ) {

	$codes = $issues->get_error_codes();
	$ret = array();

	foreach ( $codes as $err_code ) {
		$msg = $issues->get_error_message( $err_code );

		if ( $data = $issues->get_error_data( $err_code ) ) {
			if ( $doc = $data['doc_page'] ?? false ) {
				$msg .= ' [ <a href="' . $doc . '" target="_blank">' . __( 'Documentation', 'wp-cerber' ) . '</a> ]';
			}
		}

		$ret[ $err_code ] = $msg;
	}

	return $ret;
}