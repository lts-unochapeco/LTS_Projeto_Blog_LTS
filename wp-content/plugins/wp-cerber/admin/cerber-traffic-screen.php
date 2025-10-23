<?php
/**
 * Generates HTML code for the Traffic Log admin page to view, filter, and search HTTP requests log entry
 *
 * This file contains the refactored class for rendering the Traffic Log screen.
 * The main entry point is CRB_Traffic_Log_Screen::render_screen().
 *
 * @since 9.6.9.7
 *
 */
class CRB_Traffic_Log_Screen {

	/**
	 * Renders and outputs the HTTP Traffic Log admin screen.
	 *
	 * This is the main orchestrator method. It fetches data and calls other
	 * rendering methods to build the final HTML output for the screen.
	 *
	 * @param array $args Optional arguments to control UI, filtering and limiting log rows to show.
	 * @param bool $echo Whether to echo the result or return as a string.
	 *
	 * @return void|string
     *
     * @since 9.6.9.7
	 */
	public static function render_screen( $args = array(), $echo = true ) {
		$logging_enabled = (bool) crb_get_settings( 'timode' );
		$ui_controls = empty( $args['no_navi'] );

		// 1. Fetch main log data

		list( $traffic_log_rows, $total_rows, $query_params ) = self::fetch_traffic_log_rows( $args );

		$is_log_empty = ! ( cerber_db_get_var( 'SELECT stamp FROM ' . CERBER_TRAF_TABLE . ' LIMIT 1' ) );
		$result_html = '';

		// 2. Render Toolbar and Info Blocks (they can be displayed even if no results)

		if ( $ui_controls ) {
			$toolbar_html = self::render_toolbar( $is_log_empty, (int) crb_get_settings( 'tithreshold' ), (bool) $traffic_log_rows );
			$info_blocks_html = self::render_info_blocks( $query_params['filter_ip'], $query_params['filter_user'] );
			$form_html = self::render_search_form();

			$result_html .= '<div class="cerber-margin">' . $toolbar_html . $form_html . $info_blocks_html . '</div>';
		}

		// 3. Render the log table or empty state message

		if ( $traffic_log_rows ) {
			// 3a. Fetch and prepare all related data
			$activity_rows = self::fetch_activity_events( $traffic_log_rows );
			$grouped_events = self::group_activity_events( $activity_rows );
			$enriched_data = self::enrich_log_data( $traffic_log_rows );

			// 3b. Render the main content table
			$table_html = self::render_log_table( $traffic_log_rows, $enriched_data, $grouped_events, $args );

			// 3c. Add pagination
			if ( $ui_controls ) {
				$table_html .= cerber_page_navi( $total_rows, $query_params['per_page'] );
			}

			$result_html .= $table_html;

			if ( ! $logging_enabled ) {
				$result_html = '<p class="cerber-margin" style="padding: 0.5em; font-weight: 600;">Logging is currently disabled, you are viewing historical information.</p>' . $result_html;
			}
		}
		else {
			// 3d. Render the "no results" message
			$result_html .= self::render_empty_state( $query_params['is_search'], $logging_enabled, $is_log_empty );
		}

		if ( $echo ) {
			echo $result_html;
		}
		else {
			return $result_html;
		}
	}

	/**
	 * Fetches and prepares traffic log records from the database.
	 * Decodes serialized data within the log rows.
	 *
	 * @param array $args The arguments for building the query.
	 *
	 * @return array An array containing: [ (array) log_rows, (int) total_rows, (array) query_params ].
     *
     * @since 9.6.9.7
	 */
	private static function fetch_traffic_log_rows( $args ) {
		list( $query, $found, $per_page, $filter_act, $filter_ip, $prc, $filter_user ) = cerber_build_traffic_query( $args );

		list( $traffic_log_rows, $total_rows ) = crb_q_cache_get( array(
			array( $query, MYSQL_FETCH_OBJECT_K ),
			array( $found )
		), CERBER_TRAF_TABLE );

		if ( is_object( $traffic_log_rows ) ) { // Due to possible JSON from cache
			$traffic_log_rows = get_object_vars( $traffic_log_rows );
		}

		// Decode serialized data once after fetching
		if ( $traffic_log_rows ) {
			foreach ( $traffic_log_rows as $key => $row ) {
				$traffic_log_rows[ $key ]->request_details = crb_auto_decode( $row->request_details );
				$traffic_log_rows[ $key ]->request_fields = crb_auto_decode( $row->request_fields );
				$traffic_log_rows[ $key ]->php_errors = crb_auto_decode( $row->php_errors );
			}
		}

		$total_rows = (int) ( $total_rows[0][0] ?? 0 );

		$query_params = array(
			'per_page'    => $per_page,
			'filter_ip'   => $filter_ip,
			'filter_user' => $filter_user,
			'is_search'   => (bool) ( array_intersect_key( CRB_TRF_PARAMS, crb_get_query_params() ) )
		);

		return array( $traffic_log_rows, $total_rows, $query_params );
	}

	/**
	 * Fetches related activity events for a given set of traffic log rows.
	 *
	 * @param array $log_rows The traffic log rows.
	 *
	 * @return array The fetched activity rows.
     *
     * @since 9.6.9.7
	 */
	private static function fetch_activity_events( $log_rows ) {
		if ( ! $log_rows ) {
			return array();
		}

		$session_ids = array_keys( $log_rows );
		$query = 'SELECT * FROM ' . CERBER_LOG_TABLE . ' WHERE session_id IN ("' . implode( '", "', array_map( 'cerber_db_real_escape', $session_ids ) ) . '" ) ORDER BY stamp DESC';

		$activity_rows = crb_q_cache_get( array(
			array( $query, MYSQL_FETCH_OBJECT )
		), CERBER_LOG_TABLE );

		return $activity_rows ?: array();
	}

	/**
	 * Groups activity events by session ID.
	 *
	 * @param array $activity_rows The activity rows to group.
	 *
	 * @return array The events grouped by session_id.
     *
     * @since 9.6.9.7
	 */
	private static function group_activity_events( $activity_rows ) {
		if ( ! $activity_rows ) {
			return array();
		}

		$events = array();
		foreach ( $activity_rows as $activity_row ) {
			$events[ $activity_row->session_id ][] = $activity_row;
		}

		return $events;
	}

	/**
	 * Enriches log data with user information, ACL status, and post titles.
	 *
	 * @param array $log_rows The traffic log rows.
	 *
	 * @return array An array of enriched data: ['users' => [], 'acl' => [], 'block' => [], 'entities' => []].
     *
     * @since 9.6.9.7
	 */
	private static function enrich_log_data( $log_rows ) {
		global $wpdb;

		$enriched_data = array(
			'users'    => array(),
			'acl'      => array(),
			'block'    => array(),
			'entities' => array(),
		);

		if ( ! $log_rows ) {
			return $enriched_data;
		}

		$roles = wp_roles()->roles;

		foreach ( $log_rows as $traffic_row ) {
			// Enrich user data
			if ( $traffic_row->user_id && ! isset( $enriched_data['users'][ $traffic_row->user_id ] ) ) {
				if ( $user_data = crb_get_userdata( $traffic_row->user_id ) ) {
					$user_roles = '';
					if ( ! is_multisite() && $user_data->roles ) {
						$role_names = array();
						foreach ( $user_data->roles as $role_key ) {
							if ( isset( $roles[ $role_key ]['name'] ) ) {
								$role_names[] = $roles[ $role_key ]['name'];
							}
						}
						$user_roles = '<span class="crb_act_role">' . implode( ', ', $role_names ) . '</span>';
					}
					$name = $user_data->display_name;
				}
				else {
					$name = __( 'Unknown', 'wp-cerber' ) . ' (' . $traffic_row->user_id . ')';
					$user_roles = '';
				}
				$enriched_data['users'][ $traffic_row->user_id ] = array( 'name' => $name, 'roles' => $user_roles );
			}

			// Enrich IP ACL status
			if ( ! isset( $enriched_data['acl'][ $traffic_row->ip ] ) ) {
				$enriched_data['acl'][ $traffic_row->ip ] = cerber_acl_check( $traffic_row->ip );
			}

			// Enrich IP block status
			if ( ! isset( $enriched_data['block'][ $traffic_row->ip ] ) ) {
				$enriched_data['block'][ $traffic_row->ip ] = cerber_block_check( $traffic_row->ip );
			}

			// Enrich WordPress entity (e.g., post title)
			if ( $traffic_row->wp_type == 601 && $traffic_row->wp_id > 0 && ! isset( $enriched_data['entities'][ $traffic_row->wp_id ] ) ) {
				$post_title = cerber_db_get_var( 'SELECT post_title FROM ' . $wpdb->posts . ' WHERE ID = ' . absint( $traffic_row->wp_id ) );
				if ( $post_title ) {
					$enriched_data['entities'][ $traffic_row->wp_id ] = apply_filters( 'the_title', $post_title, $traffic_row->wp_id );
				}
			}
		}

		return $enriched_data;
	}

	/**
	 * Renders the top toolbar with quick filters and action buttons.
	 *
	 * @param bool $is_log_empty Whether the log is completely empty.
	 * @param int $threshold The processing time threshold for a "long" request.
	 * @param bool $has_results Whether there are log rows to display.
	 *
	 * @return string The HTML for the toolbar.
     *
     * @since 9.6.9.7
	 */
	private static function render_toolbar( $is_log_empty, $threshold, $has_results ) {
		$filter_links = '';
		$search_button = '';
		$export_button = '';
		$refresh_button = '';

		if ( ! $is_log_empty ) {
			$nav_links = array(
				array( array(), __( 'View all', 'wp-cerber' ) ),
				array( array( 'filter_set' => 1 ), __( 'Suspicious requests', 'wp-cerber' ) ),
				array( array( 'filter_http_code' => 399, 'filter_http_code_mode' => 'GT' ), __( 'Errors', 'wp-cerber' ) ),
				array( array( 'filter_user' => '*' ), __( 'Users', 'wp-cerber' ) ),
				array( array( 'filter_user' => 0 ), __( 'Non-authenticated', 'wp-cerber' ) ),
				array( array( 'filter_method' => 'POST', 'filter_wp_type' => 520, 'filter_wp_type_mode' => 'GT' ), __( 'Form submissions', 'wp-cerber' ) ),
				array( array( 'filter_http_code' => 404 ), __( 'Page Not Found', 'wp-cerber' ) ),
				array( array( 'filter_wp_type' => 520 ), 'REST API' ),
				array( array( 'filter_wp_type' => 515 ), 'XML-RPC' ),
				array( array( 'filter_user' => get_current_user_id() ), __( 'My requests', 'wp-cerber' ) ),
				array( array( 'filter_ip' => cerber_get_remote_ip() ), __( 'My IP', 'wp-cerber' ) ),
			);

			if ( $threshold ) {
				$nav_links[] = array( array( 'filter_processing' => $threshold ), __( 'Longer than', 'wp-cerber' ) . ' ' . (int) $threshold . ' ms' );
			}

			$filter_links = crb_make_nav_links( $nav_links, 'traffic' );
			$search_button = '<a href="#" id="traffic-search-btn" class="button button-secondary cerber-button" title="Search in the request history"><i class="crb-icon crb-icon-bx-search"></i> ' . __( 'Advanced Search', 'wp-cerber' ) . '</a>';
		}

		if ( $has_results ) {
			// This URL is escaped, no escaping is needed
			$export_url = cerber_admin_link_add( array( 'cerber_admin_do' => 'export', 'type' => 'traffic' ), true );
			$export_button = '<a class="button button-secondary cerber-button" href="' . $export_url . '"><i class="crb-icon crb-icon-bx-download"></i> ' . __( 'Export', 'wp-cerber' ) . '</a>';
		}

		if ( crb_get_settings( 'timode' ) && $has_results ) {
			$refresh_button = '<a href="" style="white-space: pre;"><i class="dashicons dashicons-update" style=""></i> <span>' . __( 'Refresh', 'wp-cerber' ) . '</span></a>';
		}

		$right_actions = $search_button . ' ' . $export_button;

		return '<div id="activity-filter"><div><div style="display: table-cell; width: 80%;">' . $filter_links . '</div><div style="display: table-cell;">' . $refresh_button . '</div></div><div>' . $right_actions . '</div></div><br style="clear: both;">';
	}


	/**
	 * Renders additional info blocks when filtering by IP or user.
	 *
	 * @param string $filter_ip The IP being filtered, if any.
	 * @param int $filter_user The user ID being filtered, if any.
	 *
	 * @return string The HTML for the info blocks.
     *
     * @since 9.6.9.7
	 */
	private static function render_info_blocks( $filter_ip, $filter_user ) {
		$info_html = '';

		if ( $filter_ip ) {
			$info_html .= cerber_ip_extra_view( $filter_ip, 'traffic' );
		}

		if ( is_numeric( $filter_user ) && $filter_user > 0 ) {
			$info_html .= cerber_user_extra_view( $filter_user, 'traffic' );
		}

		return $info_html;
	}

	/**
	 * Renders the main log table.
	 *
	 * @param array $log_rows The traffic log rows.
	 * @param array $enriched_data Additional data for users, ACL, etc.
	 * @param array $events Grouped activity events.
	 * @param array $args Original arguments for rendering options.
	 *
	 * @return string The HTML for the log table.
     *
     * @since 9.6.9.7
	 */
	private static function render_log_table( $log_rows, $enriched_data, $events, $args ) {
		$columns = array(
			'crb_date_col'     => __( 'Date', 'wp-cerber' ),
			'crb_request_col'  => __( 'Request', 'wp-cerber' ),
			'crb_ip_col'       => '<div class="crb_act_icon"></div>' . __( 'IP Address', 'wp-cerber' ),
			'crb_hostinfo_col' => __( 'Host Info', 'wp-cerber' ),
			'crb_ua_col'       => __( 'User Agent', 'wp-cerber' ),
			'crb_user_col'     => __( 'Local User', 'wp-cerber' ),
		);

		$table_heading = '';
		foreach ( $columns as $id => $title ) {
			$table_heading .= '<th id="' . crb_attr_escape( $id ) . '">' . $title . '</th>';
		}

		$table_footer = '<th>' . implode( '</th><th>', $columns ) . '</th>';

		$table_body = '';
		$is_even_row = false;
		foreach ( $log_rows as $log_entry ) {
			$row_class = $is_even_row ? 'crb-even' : 'crb-odd';
			$table_body .= self::render_table_row( $log_entry, $enriched_data, $events, $row_class, $args );
			$is_even_row = ! $is_even_row;
		}

		return '<table id="crb-traffic" class="widefat crb-table cerber-margin"><thead><tr>' . $table_heading . '</tr></thead><tfoot><tr>' . $table_footer . '</tr></tfoot><tbody>' . $table_body . '</tbody></table>';
	}

	/**
	 * Renders a single row in the log table, including the hidden details row.
	 *
	 * @param object $log_entry A single log entry object.
	 * @param array $enriched_data Additional data for users, ACL, etc.
	 * @param array $events Grouped activity events.
	 * @param string $row_class The CSS class for the row (crb-even/crb-odd).
	 * @param array $args Original arguments for rendering options.
	 *
	 * @return string The HTML for a single `<tr>` pair.
     *
     * @since 9.6.9.7
	 */
	private static function render_table_row( $log_entry, $enriched_data, $events, $row_class, $args ) {
		$base_url = cerber_admin_link( 'traffic' );

		// Date cell
		if ( 'ago' == ( $args['date'] ?? '' ) ) {
			$date_cell = cerber_ago_time( $log_entry->stamp );
		}
		else {
			$date_cell = '<span title="' . crb_attr_escape( $log_entry->stamp . ' / ' . $log_entry->session_id ) . '">' . cerber_date( $log_entry->stamp ) . '</span>';
		}

		// User cell
		$user_cell = '';
		if ( $log_entry->user_id && isset( $enriched_data['users'][ $log_entry->user_id ] ) ) {
			$user = $enriched_data['users'][ $log_entry->user_id ];
			$user_cell = '<a href="' . crb_escape_url( $base_url . '&filter_user=' . $log_entry->user_id ) . '"><b>' . crb_escape_html( $user['name'] ) . '</b></a><br/>' . $user['roles'];
		}

		// Host Info cell
		$country_html = ( lab_lab() ) ? '<p style="">' . crb_country_html( $log_entry->country, $log_entry->ip ) . '</p>' : '';
		if ( ! empty( $log_entry->hostname ) ) {
			$hostname = $log_entry->hostname;
		}
		else {
			$ip_info = cerber_get_ip_info( $log_entry->ip );
			$hostname = $ip_info['hostname_html'] ?? crb_get_ajax_placeholder( 'hostname', cerber_get_id_ip( $log_entry->ip ) );
		}
		$host_info_cell = $hostname . $country_html;

		// User Agent cell
		$ua_cell = cerber_detect_browser( $log_entry->request_details[1] ?? '' );

		// IP cell
		$ip_cell = crb_admin_ip_cell( $log_entry->ip, $base_url . '&amp;filter_ip=' . $log_entry->ip );

		// Request cell (the most complex one)
		$request_details_html = self::render_request_details( $log_entry, $enriched_data, $events, $base_url );
		$toggle_class = $request_details_html['details'] ? 'crb-toggle' : '';

		// Build the final row HTML
		$visible_row = '<tr class="' . $row_class . ' ' . $toggle_class . '">
            <td>' . $date_cell . '</td>
            <td class="crb-request">' . $request_details_html['summary'] . '</td>
            <td>' . $ip_cell . '</td>
            <td>' . $host_info_cell . '</td>
            <td>' . $ua_cell . '</td>
            <td>' . $user_cell . '</td>
        </tr>';

		$hidden_row = '<tr class="' . $row_class . ' crb-request-details" style="display: none;">
            <td></td>
            <td colspan="5" class="crb-traffic-details" data-session-id="' . crb_attr_escape( $log_entry->session_id ) . '">
                <div>' . $request_details_html['details'] . '</div>
            </td>
        </tr>';

		return $visible_row . $hidden_row;
	}

	/**
	 * Formats URI data for display.
	 *
	 * @param string $raw_uri The raw URI from the log entry.
	 *
	 * @return array An array containing 'display_html' and 'card' data.
     *
     * @since 9.6.9.7
	 */
	private static function format_uri_data( $raw_uri ) {
		$full_uri = urldecode( $raw_uri );
		$display_uri = $full_uri;

		if ( ! defined( 'CERBER_FULL_URI' ) || ! CERBER_FULL_URI ) {
			$site_home_url = cerber_get_home_url();
			if ( 0 === strpos( $full_uri, $site_home_url ) ) {
				$display_uri = substr( $full_uri, strlen( rtrim( $site_home_url, '/' ) ) );
			}
		}

		$is_truncated = strlen( $display_uri ) > 220;
		$display_html = crb_escape_html( $is_truncated ? substr( $display_uri, 0, 220 ) : $display_uri );
		if ( $is_truncated ) {
			$display_html .= ' <span style="color: red;">&hellip;</span>';
		}

		$card = array();
		if ( $is_truncated ) {
			$card = array( 'title' => 'Full URL', 'content' => $full_uri, 'type' => 'monospace' );
		}

		return array(
			'display_html' => $display_html,
			'card'         => $card,
		);
	}

	/**
	 * Builds a structured array of request details data from a log entry.
	 *
	 * @param object $log_entry A single log entry object.
	 *
	 * @return array A structured array of data cards for rendering.
     *
     * @since 9.6.9.7
	 */
	private static function build_request_details_data( $log_entry ) {
		$details = $log_entry->request_details;
		$fields = $log_entry->request_fields;
		$cards = array();

		// Server headers and redirection
		$server_headers = array();
		if ( ! empty( $details[10] ) ) {
			foreach ( $details[10] as $item ) {
				$e = explode( ':', $item, 2 );
				if ( count( $e ) == 2 ) {
					$server_headers[ trim( $e[0] ) ] = trim( $e[1] );
				}
			}
		}
		if ( ! empty( $server_headers['Location'] ) ) {
			$cards[] = array( 'title' => 'Redirection URL', 'content' => urldecode( $server_headers['Location'] ), 'type' => 'monospace' );
		}

		// Common request data
		if ( ! empty( $details[2] ) ) {
			$cards[] = array( 'title' => 'Referrer', 'content' => urldecode( $details[2] ), 'type' => 'monospace' );
		}
		if ( ! empty( $details[1] ) ) {
			$cards[] = array( 'title' => 'User Agent', 'content' => $details[1], 'type' => 'monospace' );
		}

		// Request body data
		if ( ! empty( $fields[1] ) ) {
			crb_highlight_fields_type_two( $fields[1], 1 );
			$cards[] = array( 'title' => __( 'Form Fields', 'wp-cerber' ), 'content' => $fields[1], 'type' => 'table' );
		}
		if ( ! empty( $fields[4] ) ) {
			$cards[] = array( 'title' => __( 'JSON Payload', 'wp-cerber' ), 'content' => $fields[4], 'type' => 'table' );
		}
		if ( ! empty( $fields[2] ) ) {
			$cards[] = array( 'title' => __( 'Query Parameters', 'wp-cerber' ), 'content' => $fields[2], 'type' => 'table' );
		}

		// Files
		if ( ! empty( $fields[3] ) ) {
			$files_content = array();
			foreach ( $fields[3] as $field_name => $file ) {
				$f_err = is_array( $file['error'] ) ? array_shift( $file['error'] ) : $file['error'];
				if ( $f_err == UPLOAD_ERR_NO_FILE ) {
					continue;
				}
				$f_name = is_array( $file['name'] ) ? array_shift( $file['name'] ) : $file['name'];
				$f_size = is_array( $file['size'] ) ? array_shift( $file['size'] ) : $file['size'];
				$files_content[ $field_name ] = crb_escape_html( $f_name ) . ', size: ' . absint( $f_size ) . ' bytes';
			}
			if ( $files_content ) {
				$cards[] = array( 'title' => __( 'Files Submitted', 'wp-cerber' ), 'content' => $files_content, 'type' => 'table' );
			}
		}

		// Other details
		if ( ! empty( $details[5] ) ) {
			$cards[] = array( 'title' => __( 'XML-RPC Payload', 'wp-cerber' ), 'content' => $details[5], 'type' => 'monospace' );
		}
		if ( ! empty( $details[6] ) ) {
			$cards[] = array( 'title' => __( 'Client Request Headers', 'wp-cerber' ), 'content' => $details[6], 'type' => 'table' );
		}
		if ( ! empty( $server_headers ) ) {
			if ( ! empty( $details[11] ) ) {
				crb_highlight_fields_type_one( $server_headers, array( 'Location' => $details[11] ) );
			}
			$cards[] = array( 'title' => __( 'Server Response Headers', 'wp-cerber' ), 'content' => $server_headers, 'type' => 'table' );
		}
		if ( ! empty( $details[8] ) ) {
			crb_highlight_fields_type_two( $details[8], 2 );
			$cards[] = array( 'title' => __( 'Client Request Cookies', 'wp-cerber' ), 'content' => $details[8], 'type' => 'table' );
		}

		// Server Response Cookies
		if ( ! empty( $details[9] ) ) {
			$server_cookies = array();
			foreach ( $details[9] as $item ) {
				if ( 0 === stripos( $item, 'Set-Cookie:' ) ) {
					$item = substr( $item, 11 );
				}
				$parts = explode( '=', $item, 2 );
				if ( count( $parts ) == 2 ) {
					$pos = strpos( $parts[1], ';' );
					$server_cookies[ trim( $parts[0] ) ] = urldecode( substr( $parts[1], 0, $pos ) ) . substr( $parts[1], $pos );
				}
			}
			crb_highlight_fields_type_two( $server_cookies, 2 );
			$cards[] = array( 'title' => __( 'Server Response Cookies', 'wp-cerber' ), 'content' => $server_cookies, 'type' => 'table' );
		}

		if ( ! empty( $details[7] ) ) {
			$cards[] = array( 'title' => '$_SERVER', 'content' => $details[7], 'type' => 'table' );
		}

		// PHP Errors
		if ( $err_list = $log_entry->php_errors ) {
			$errors = array();
			foreach ( $err_list as $err ) {
				$errors[] = array(
					'type'   => cerber_get_err_level( $err[0] ) . ' (' . $err[0] . ')',
					'info'   => $err[1],
					'file'   => $err[2],
					'line'   => $err[3],
					'source' => crb_get_file_owner( $err[2] ) ?: ''
				);
			}
			$cards[] = array( 'title' => __( 'Software Errors', 'wp-cerber' ), 'content' => $errors, 'type' => 'table' );
		}

		return $cards;
	}

	/**
	 * Renders a string of HTML labels for the request summary line.
	 *
	 * @param object $log_entry A single log entry object.
	 * @param array $status_labels An array of status labels.
	 *
	 * @return string The concatenated HTML for the labels.
     *
     * @since 9.6.9.7
	 */
	private static function render_summary_labels( $log_entry, $status_labels ) {
		$labels_html = '';

		// Files label
		if ( ! empty( $log_entry->request_fields[3] ) ) {
			$labels_html .= '<span class="crb-ffile">F</span>';
		}

		// WP Type label
		if ( $wp_type = cerber_get_wp_type( $log_entry->wp_type ) ) {
			$labels_html .= '<span class="crb-wp-type-' . $log_entry->wp_type . '">' . $wp_type . '</span>';
		}

		// HTTP Code label
		$labels_html .= crb_get_http_code_label( (int) $log_entry->http_code );

		// Request Status label
		if ( ! empty( $log_entry->req_status ) ) {
			$status = (int) $log_entry->req_status;
			if ( isset( $status_labels[ $status ] ) ) {
				$labels_html .= '<span class="crb-req-status-' . $status . '" title="' . $status . '">' . $status_labels[ $status ] . '</span>';
			}
		}

		// PHP Errors label
		if ( $errors = $log_entry->php_errors ) {
			$labels_html .= '<span class="crb-php-error">SE &nbsp;' . count( $errors ) . '</span>';
		}

		return $labels_html;
	}

	/**
	 * Renders the summary and detailed view of a request from prepared data.
	 *
	 * @param object $log_entry A single log entry object.
	 * @param array $enriched_data Additional data for users, ACL, etc.
	 * @param array $events Grouped activity events.
	 * @param string $base_url The base URL for filter links.
	 *
	 * @return array The HTML for the request details, split into 'summary' and 'details'.
     *
     * @since 9.6.9.7
	 */
	private static function render_request_details( $log_entry, $enriched_data, $events, $base_url ) {
		$activity_labels = cerber_get_labels( 'activity' );
		$status_labels = cerber_get_labels( 'status' ) + cerber_get_reason();

		// Get formatted URI data and the main body of cards
		$uri_data = self::format_uri_data( $log_entry->uri );
		$cards_data = self::build_request_details_data( $log_entry );

		// Prepend the URI card if it exists
		if ( ! empty( $uri_data['card'] ) ) {
			array_unshift( $cards_data, $uri_data['card'] );
		}

		// Build Details HTML from the final list of cards
		$cards_html = '';
		foreach ( $cards_data as $card ) {
			$title_html = '<p style="font-weight: 600;">' . $card['title'] . '</p>';
			switch ( $card['type'] ) {
				case 'table':

					if ( $table_element = crb_ui_table_view( $card['title'], $card['content'] ) ) {
						$cards_html .= crb_ui_renderer()->render_element( $table_element );
					}

					break;
				case 'monospace':
					$cards_html .= '<div>' . $title_html . '<span class="crb-monospace">' . crb_escape_html( $card['content'] ) . '</span></div>';
					break;
			}
		}

		// Build summary HTML
		$display_uri_html = $uri_data['display_html'];
		$labels_html = self::render_summary_labels( $log_entry, $status_labels );

		$processing_time = '';
		if ( $log_entry->processing ) {
			$processing_time = (int) $log_entry->processing . ' ms';
			$threshold = (int) crb_get_settings( 'tithreshold' );
			if ( $threshold > 0
			     && $log_entry->processing > $threshold ) {
				$processing_time = '<span class="crb-processing crb-above">' . $processing_time . '</span>';
			}
		}

		$more_link = $cards_html ? '<a href="#" class="crb-traffic-more">' . __( 'Details', 'wp-cerber' ) . '</a>' : '';

		$summary_html = '<b>' . $display_uri_html . '</b>'
		                . '<p style="margin-top:1em;">'
		                . '<span class="crb-' . $log_entry->request_method . '">' . $log_entry->request_method . '</span>'
		                . $labels_html
		                . '<span>' . $processing_time . '</span> ' . $more_link . '</p>';

		// Append activity events to summary
		$activity_html = '';
		if ( ! empty( $events[ $log_entry->session_id ] ) ) {
			foreach ( $events[ $log_entry->session_id ] as $activity_row ) {
				$activity_html .= '<p class="' . crb_get_act_style( $activity_row->activity ) . '"><span><a href="' . crb_escape_url( $base_url . '&filter_activity=' . $activity_row->activity ) . '" title="' . crb_attr_escape( $activity_row->activity ) . '" data-no-js="1">' . crb_escape_html( $activity_labels[ $activity_row->activity ] ) . '</a></span>';
				$status = (int) $activity_row->ac_status;
				if ( ! empty( $status ) && $status != 500 ) { // 500 Whitelisted
					$activity_html .= ' &nbsp;<span class = "crb-log-status crb-status-' . $status . '" title="' . crb_attr_escape( $status_labels[ $status ] ) . '">' . crb_escape_html( $status_labels[ $status ] ) . '</span>';
				}
				$activity_html .= '</p>';
			}
		}
		$summary_html .= $activity_html;

		// Append WP entity to summary
		if ( isset( $enriched_data['entities'][ $log_entry->wp_id ] ) ) {
			$summary_html .= '<p><i>' . crb_escape_html( $enriched_data['entities'][ $log_entry->wp_id ] ) . '</i></p>';
		}

		return array(
			'summary' => $summary_html,
			'details' => $cards_html,
		);
	}

	/**
	 * Renders the UI for when no log entries are found.
	 *
	 * @param bool $is_search Whether the empty state is a result of a search.
	 * @param bool $logging_enabled Whether logging is currently active.
	 * @param bool $is_log_empty Whether the log table is completely empty.
	 *
	 * @return string The HTML for the empty state message.
     *
     * @since 9.6.9.7
	 */
	private static function render_empty_state( $is_search, $logging_enabled, $is_log_empty ) {
		$ui_hints = array();
		$base_url = cerber_admin_link( 'traffic' );

		if ( $is_search ) {
			$ui_hints[] = __( 'No requests found using the given search criteria', 'wp-cerber' );
			if ( ! $is_log_empty ) {
				$ui_hints[] = '<a href="' . crb_escape_url( $base_url ) . '">' . __( 'View all logged requests', 'wp-cerber' ) . '</a>';
			}
		}
		else {
			$ui_hints[] = __( 'No requests have been logged yet.', 'wp-cerber' );
		}

		if ( ! $logging_enabled ) {
			$ui_hints[] = __( 'Note: Logging is currently disabled', 'wp-cerber' );
			if ( ! $is_search ) {
				$ui_hints[] = '<a href="https://wpcerber.com/wordpress-traffic-logging/" target="_blank">' . __( 'Documentation', 'wp-cerber' ) . '</a>';
			}
		}

		// Self-repair if the log table is missing or corrupted
		if ( $is_log_empty ) {
			cerber_watchdog( true );
		}

		return '<div class="cerber-margin crb-rectangle"><p>' . implode( '</p><p>', $ui_hints ) . '</p></div>';
	}

	/**
	 * Builds and renders the traffic search form using CRB_UI_Form_Builder.
	 *
	 * This version is the most declarative and clean, fully abstracting
	 * the form's structure and layout into the builder class.
	 *
	 * @note It temporarily integrates legacy input fields via a raw_html block. Needs to be refactored ASAP.
	 *
	 * TODO: refactor legacy dropdown select inputs ASAP
     * 
     * TODO: in the next version it should return CRB_UI_Element The complete search form element
	 *
	 * @return string HTML code for the search form
	 *
	 * @since 9.6.9.8
	 */
	private static function render_search_form(): string {

		$form_builder = CRB_UI_Form_Builder::create( 'crb-traffic-form', 'get' )
		                                   ->add_hidden_field( 'page', 'cerber-traffic' );

		// --- Column 1 ---
		$form_builder
			->add_custom_element( crb_ui_element( 'p', [ 'style' => 'width: 100%;' ], [
				crb_ui_element( 'label', [], 'Activity' ),
				crb_ui_element( 'raw_html', [], '', [ 'content' => crb_get_activity_dd() ] ),
			] ) )
			->add_field( 'text', 'search_traffic[uri]', 'URL contains' )
			->add_select( 'filter_method', 'Request method', [
				'0'    => 'Any',
				'GET'  => 'GET',
				'POST' => 'POST',
			] )
			->add_field( 'text', 'search_traffic[fields]', 'Request fields contain', 'Search in POST, GET, REST API, JSON data' )
			->add_field( 'text', 'search_traffic[details]', 'Miscellaneous details contains', 'Search in cookies, headers, user agents, referrers' )
			->add_field( 'number', 'filter_http_code', 'HTTP Response Code equals' )
			->add_field( 'date', 'search_traffic[date_from]', 'Date from', '', [ 'placeholder' => 'month/day/year' ] )
			->add_field( 'date', 'search_traffic[date_to]', 'Date to', '', [ 'placeholder' => 'month/day/year' ] );

		// --- Column 2 ---
		$form_builder->next_column()
		             ->add_field( 'text', 'search_traffic[ip]', 'Remote IP address contains or equals' )
		             ->add_custom_element( crb_ui_element( 'p', [], [
			             crb_ui_element( 'label', [], 'User' ),
			             crb_ui_element( 'br' ),
			             crb_ui_element( 'raw_html', [], '', [ 'content' => cerber_select( 'filter_user', [], null, 'crb-select2-ajax', '', false, '', [ 'min_symbols' => 3 ] ) ] ),
		             ] ) )
		             ->add_field( 'text', 'filter_user_alt', 'User ID', '', [ 'placeholder' => 'Use comma to specify multiple IDs' ] )
		             ->add_select( 'filter_user_mode', 'User operator', [
			             '0' => 'Include',
			             '1' => 'Exclude',
		             ] )
		             ->add_field( 'text', 'filter_sid', 'RID', '', [ 'placeholder' => 'Request ID' ] )
		             ->add_field( 'text', 'search_traffic[errors]', 'Software errors contain' )
		             ->add_checkbox( 'filter_errors', 'Any software error' )
		             ->add_custom_element( crb_ui_element( 'p', [ 'style' => 'margin-top: 3em;' ], 'The search uses AND logic for all non-empty fields' ) )
		             ->add_submit( 'Search', [ 'style' => 'width: 8rem;' ] );


		$form_element = $form_builder->to_element();

		$the_form = crb_ui_element( 'div', [ 'id' => 'crb-traffic-search', 'style' => 'display: none;' ], [ $form_element ] );

		return crb_ui_renderer()->render_element( $the_form );
	}
}
