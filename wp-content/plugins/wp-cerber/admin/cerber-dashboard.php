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


if ( ! defined( 'WPINC' ) ) {
	exit;
}

const CRB_ADMIN_ICONS = array(
	'settings'  => 'bx-slider',
	'activity'  => 'bx-pulse',
	'know_more' => 'bx-idea',
);

if ( ! is_multisite() ) {
	add_action( 'admin_menu', 'cerber_admin_menu' );
	define( 'CRB_ADMIN_CAP', 'manage_options' );
}
else {
	add_action( 'network_admin_menu', 'cerber_admin_menu' );  // only network wide menu allowed in multisite mode
	define( 'CRB_ADMIN_CAP', 'manage_network' );
}

function cerber_admin_menu() {

	$position = 100;

	if ( cerber_is_admin_page() ) {
		if ( crb_get_settings( 'top_admin_menu' ) ) {
			$position = 1;
		}
	}

	$hook = add_menu_page( 'WP Cerber Security', 'WP Cerber', CRB_ADMIN_CAP, 'cerber-security', 'cerber_render_admin_page', 'dashicons-shield', $position );
	add_action( 'load-' . $hook, 'crb_admin_screen_options' );
	add_submenu_page( 'cerber-security', __( 'WP Cerber Dashboard', 'wp-cerber' ), __( 'Dashboard', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-security', 'cerber_render_admin_page' );

	$hook = add_submenu_page( 'cerber-security', __( 'WP Cerber: Traffic Inspector', 'wp-cerber' ), __( 'Traffic Inspector', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-traffic', 'cerber_render_admin_page' );
	add_action( 'load-' . $hook, 'crb_admin_screen_options' );

	if ( lab_lab() ) {
		add_submenu_page( 'cerber-security', __( 'WP Cerber: Data Shield Policies', 'wp-cerber' ), __( 'Data Shield', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-shield', 'cerber_render_admin_page' );
		//add_submenu_page( 'cerber-security', __( 'Cerber Security Rules', 'wp-cerber' ), __( 'Security Rules', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-rules', 'cerber_render_admin_page' );
	}

	add_submenu_page( 'cerber-security', __( 'WP Cerber: Security Rules', 'wp-cerber' ), __( 'Security Rules', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-rules', 'cerber_render_admin_page' );

	add_submenu_page( 'cerber-security', __( 'WP Cerber: User Security', 'wp-cerber' ), __( 'User Policies', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-users', 'cerber_render_admin_page' );

	if ( cerber_get_upload_dir_mu() ) {
		$hook = add_submenu_page( 'cerber-security', 'WP Cerber: Site Integrity', __( 'Site Integrity', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-integrity', 'cerber_render_admin_page' );
		add_action( 'load-' . $hook, 'crb_admin_screen_options' );
	}

	add_submenu_page( 'cerber-security', __( 'WP Cerber: Anti-spam Settings', 'wp-cerber' ), __( 'Anti-spam', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-recaptcha', 'cerber_render_admin_page' );

	$hook = add_submenu_page( 'cerber-security', 'Cerber.Hub', 'Cerber.Hub', CRB_ADMIN_CAP, 'cerber-nexus', 'nexus_admin_page' );
	if ( nexus_is_main() ) {
		add_action( 'load-' . $hook, 'nexus_master_screen' );
	}

	if ( ! CRB_Addons::none() ) {
		add_submenu_page( 'cerber-security', __( 'WP Cerber: Add-ons', 'wp-cerber' ), __( 'Add-ons', 'wp-cerber' ), CRB_ADMIN_CAP, CRB_ADDON_PAGE, 'cerber_render_admin_page' );
	}

	add_submenu_page( 'cerber-security', __( 'WP Cerber: Tools', 'wp-cerber' ), __( 'Tools', 'wp-cerber' ), CRB_ADMIN_CAP, 'cerber-tools', 'cerber_render_admin_page' );

    /* We use an ugly hack to make a link in the admin menu */

	add_submenu_page( 'users.php', __( 'User Sessions', 'wp-cerber' ), __( 'User Sessions', 'wp-cerber' ), 'manage_options', 'wp-cerber-user-sessions', 'show_mass_page' );

	global $submenu;

    if ( ! empty( $submenu['users.php'] ) ) {
		foreach ( $submenu['users.php'] as &$item ) {
			if ( $item[2] == 'wp-cerber-user-sessions' ) {
				$item[2] = cerber_admin_link( 'sessions' );
				break;
			}
		}
	}

    /* End of ugly hack */

}

add_action( 'admin_bar_menu', 'cerber_admin_bar' );
function cerber_admin_bar( $wp_admin_bar ) {
	if ( ! is_multisite() ) {
		return;
	}
	$args = array(
		'parent' => 'network-admin',
		'id'     => 'cerber_admin',
		'title'  => 'WP Cerber',
		'href'   => cerber_admin_link(),
	);
	$wp_admin_bar->add_node( $args );
}

/**
 * Outputs WP Cerber admin headers
 *
 * @return void
 *
 * @since 9.1.1
 */
function crb_admin_headers() {
	if ( ! cerber_is_admin_page()
	     || cerber_is_wp_ajax() ) {
		return;
	}

	header( "Content-Security-Policy: base-uri 'self'; default-src 'self' 'unsafe-inline'; img-src https: data:; font-src https: data:; connect-src 'self'; object-src 'none';" );
}

/**
 * Wrapper for all admin pages
 *
 * @param $title string
 * @param $tabs array
 * @param $active_tab string
 * @param $renderer callable
 */
function cerber_show_admin_page( $title, $tabs = array(), $active_tab = null, $renderer = null ) {

	if ( ! $active_tab ) {
		$active_tab = crb_admin_get_tab( $tabs );
	}

	if ( nexus_is_valid_request() ) {
		$title .= nexus_request_data()->at_site;
	}

	cerber_issue_monitor();

	CRB_Wisdom::schedule_updating();

	?>
    <div id="crb-admin" class="wrap">

        <h1><?php echo $title; ?></h1>

		<?php

		cerber_show_admin_notice();

		cerber_show_tabs( $active_tab, $tabs );

		echo '<div id="crb-admin-content">';

		echo '<div id="crb-main" class="crb-tab-' . $active_tab . '">';

		if ( $active_tab == 'help' ) {
			cerber_show_help();
		}
        elseif ( is_callable( $renderer ) ) {
			$tab_data = crb_array_get( $tabs[ $active_tab ], 'tab_data' );
			call_user_func( $renderer, $active_tab, $tab_data );
		}

		echo '</div>';

		cerber_show_aside( $active_tab );

		echo '</div>';

		?>
    </div>
	<?php
}

/**
 * Displays lockouts in the Dashboard
 *
 * @param array $args
 * @param bool $echo
 *
 * @return string|void
 */
function cerber_show_lockouts( $args = array(), $echo = true ) {

	//$wp_cerber->deleteGarbage();

	$per_page = ( ! empty( $args['per_page'] ) ) ? $args['per_page'] : crb_admin_get_per_page();
	$limit = cerber_get_sql_limit( $per_page );
	$controls = empty( $args['no_navi'] );

	if ( $rows = cerber_db_get_results( 'SELECT * FROM ' . CERBER_BLOCKS_TABLE . ' ORDER BY block_until DESC ' . $limit, MYSQL_FETCH_OBJECT ) ) {

		if ( $controls ) {
			$total = cerber_blocked_num();
		}

		$table_rows = array();
		$base_url = cerber_admin_link( 'activity' );

	    $remove_base = cerber_admin_link( crb_admin_get_tab(), [], true );

	    foreach ( $rows as $row ) {
		    $ip = '<a href="' . $base_url . '&amp;filter_ip=' . $row->ip . '">' . $row->ip . '</a>';

		    $closing = crb_get_icon( 'activity' ) . '<a href="' . $base_url . '&amp;filter_set=1&amp;filter_ip=' . $row->ip . '">' . __( 'View the log of suspicious and malicious activity from this IP address', 'wp-cerber' ) . '</a>';

		    if ( ! $reason = CRB_Explainer::create_popup( 10, $row->reason_id, 0, '', '', $row->reason, $closing ) ) {
			    $reason = '<a href="' . $base_url . '&amp;filter_set=1&amp;filter_ip=' . $row->ip . '">' . $row->reason . '</a>';
		    }

		    $ip_info = cerber_get_ip_info( $row->ip );
		    if ( isset( $ip_info['hostname_html'] ) ) {
			    $hostname = $ip_info['hostname_html'];
		    }
		    else {
			    $ip_id = cerber_get_id_ip( $row->ip );
			    $hostname = crb_get_ajax_placeholder( 'hostname', $ip_id );
		    }

		    if ( lab_lab() ) {
			    $single_ip = str_replace( '*', '1', $row->ip );
			    $country   = '</td><td>' . crb_country_html( null, $single_ip );
		    }
		    else {
			    $country = '';
		    }

		    $the_row = '<td>' . $ip . '</td><td>' . $hostname . $country . '</td><td>' . cerber_date( $row->block_until ) . '</td><td>' . $reason . '</td>';

		    if ( $controls ) {
			    $the_row .= '<td><a href="' . $remove_base . '&amp;cerber_admin_do=lockdel&amp;ip=' . esc_attr( $row->ip ) . '">' . __( 'Remove', 'wp-cerber' ) . '</a></td>';
		    }

		    $table_rows[] = $the_row;
		}

	    $heading = array(
		    __( 'IP Address', 'wp-cerber' ),
		    __( 'Hostname', 'wp-cerber' ),
		    __( 'Country', 'wp-cerber' ),
		    __( 'Expires', 'wp-cerber' ),
		    __( 'Reason', 'wp-cerber' ),
		    __( 'Action', 'wp-cerber' ),
	    );

	    if ( ! lab_lab() ) {
		    unset( $heading[2] );
	    }

		if ( $controls ) {
			$navi = cerber_page_navi( $total, $per_page );
			$hint = '<div class="cerber-margin"><p>' . __( 'Click the IP address to see its activity', 'wp-cerber' ) . '</p></div>';
		}
		else {
			$navi = '';
			$hint = '';
			unset( $heading[5] );
        }

		$titles = '<tr><th>' . implode( '</th><th>', $heading ) . '</th></tr>';

		$table = '<table id="crb-locked-out" class="widefat crb-table"><thead>' . $titles . '</thead><tfoot>' . $titles . '</tfoot>' . implode( '</tr><tr>', $table_rows ) . '</tr></table>';
		$ret = $table . $navi . $hint;

    }
    else {
	    $ret = '<div class="cerber-margin crb-rectangle"><p>' . __( 'No lockouts at the moment. The sky is clear.', 'wp-cerber' ) . '</p></div>';
    }

	if ( $echo ) {
		echo $ret;

		return;
	}

	return $ret;
}

/**
 * @param string $ip
 *
 * @return int
 */
function cerber_block_delete( $ip ) {

	$result = 0;

	if ( cerber_db_query( 'DELETE FROM ' . CERBER_BLOCKS_TABLE . ' WHERE ip = "' . cerber_db_real_escape( $ip ) . '"' ) ) {
		$result = cerber_db_get_affected();
	}

	crb_event_handler( 'ip_event', array(
		'e_type' => 'unlocked',
		'ip'     => $ip,
		'result' => $result
	) );

	return $result;
}


/**
 * ACL management forms
 *
 * @return void
 */
function cerber_acl_form(){

	$w = '<div class="crb-title-plus"><div><h2>' . __( 'White IP Access List', 'wp-cerber' ) . '</h2></div>';
	$w .= '<div><span style="opacity: 0.6;">&#x2500;&#x2500;&nbsp;&nbsp;&nbsp;</span>';
	$w .= '<a href="' . cerber_activity_link( [], 500 ) . '">' . __( 'View Activity', 'wp-cerber' ) . '</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="' . cerber_admin_link( 'imex' ) . '#crb-bulk-load-acl">' . __( 'Import Entries', 'wp-cerber' ) . '</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a target="_blank" href="https://wpcerber.com/using-ip-access-lists-to-protect-wordpress/">How Access Lists work</a></div></div>';
	$w .= cerber_acl_get_table( 'W' );

	$w .= '<div class="crb-title-plus"><div><h2>' . __( 'Black IP Access List', 'wp-cerber' ) . '</h2></div>';
	$w .= '<div><span style="opacity: 0.6;">&#x2500;&#x2500;&nbsp;&nbsp;&nbsp;</span>';
	$w .= '<a href="' . cerber_activity_link( [], 14 ) . '">' . __( 'View Activity', 'wp-cerber' ) . '</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="' . cerber_admin_link( 'imex' ) . '#crb-bulk-load-acl">' . __( 'Import Entries', 'wp-cerber' ) . '</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a target="_blank" href="https://wpcerber.com/using-ip-access-lists-to-protect-wordpress/">How Access Lists work</a></div></div>';
	$w .= cerber_acl_get_table( 'B' );

	echo $w;

	$user_ip = cerber_get_remote_ip();
	$link = cerber_admin_link( 'activity' ) . '&amp;filter_ip=' . $user_ip;

	$name = '';
	if ( $country = lab_get_country( $user_ip, false ) ) {
		$name = ' [ ' . crb_get_country_name( $country ) . ' ] ';
	}

	echo '<div style="margin-bottom: 30px;">' . __( 'Your IP address', 'wp-cerber' ) . ' &nbsp;<b>' . $user_ip . '</b> ' . $name . '&nbsp;&nbsp;|&nbsp;&nbsp;<a href="' . $link . '">' . __( 'View Activity', 'wp-cerber' ) . '</a></div>';

	?>

    <table class="crb-acl-hints">
        <tr><td colspan="3">Use the following formats to add entries to the access lists</td></tr>
        <tr><td>IPv4</td><td>Single IPv4 address</td><td>192.168.5.22</td></tr>
        <tr><td>IPv4</td><td>Range specified with hyphen (dash)</td><td>192.168.1.45 - 192.168.22.165</td></tr>
        <tr><td>IPv4</td><td>Range specified with CIDR</td><td>192.168.128.0/20</td></tr>
        <tr><td>IPv4</td><td>Subnet Class C specified with CIDR</td><td>192.168.77.0/24</td></tr>
        <tr><td>IPv4</td><td>Any IPv4 address specified with CIDR</td><td>0.0.0.0/0</td></tr>
        <tr><td>IPv4</td><td>Subnet Class C specified with wildcard</td><td>192.168.77.*</td></tr>
        <tr><td>IPv4</td><td>Subnet Class B specified with wildcard</td><td>192.168.*.*</td></tr>
        <tr><td>IPv4</td><td>Subnet Class A specified with wildcard</td><td>192.*.*.*</td></tr>
        <tr><td>IPv4</td><td>Any IPv4 address specified with wildcard</td><td>*.*.*.*</td></tr>
        <tr><td>IPv6</td><td>Single IPv6 address</td><td>2001:0db8:85a3:0000:0000:8a2e:0370:7334</td></tr>
        <tr><td>IPv6</td><td>Range specified with hyphen (dash)</td><td>2001:db8::ff00:41:0 - 2001:db8::ff00:41:12ff</td></tr>
        <tr><td>IPv6</td><td>Range specified with CIDR</td><td>2001:db8::/46</td></tr>
        <tr><td>IPv6</td><td>Range specified with wildcard</td><td>2001:db8::ff00:41:*</td></tr>
        <tr><td>IPv6</td><td>Any IPv6 address</td><td>::/0</td></tr>
    </table>

	<?php
}
/*
	Create HTML to display ACL area: table + form
*/
function cerber_acl_get_table( $tag, $acl_slice = 0 ) {
	global $wpdb;

	$activity_url = cerber_admin_link( 'activity' );
	$acl_slice    = absint( $acl_slice );
	$tag          = preg_replace( '/[^W|B]/', '', $tag );

	if ( $rows = $wpdb->get_results( 'SELECT * FROM ' . CERBER_ACL_TABLE . " WHERE acl_slice = '.$acl_slice.' AND tag = '" . $tag . "' ORDER BY ip_long_begin, ip" ) ) {
		foreach ( $rows as $row ) {
			$links = '';
			if ( ! $row->ver6 || cerber_is_ipv6( $row->ip ) ) {
				$links = '<a class="crb-button-tiny" href="' . $activity_url . '&filter_ip=' . urlencode( cerber_ipv6_short( $row->ip ) ) . '">' . __( 'Check for activities', 'wp-cerber' ) . '</a> ' . cerber_traffic_link( array( 'filter_ip' => $row->ip ) );
			}

			$list[] = '<td>' . $row->ip . '</td><td>' . $row->comments . '</td><td>' . $links . '</td>
            <td><a class="delete_entry crb-button-tiny" href="javascript:void(0)" data-ip="' . $row->ip . '">' . __( 'Remove', 'wp-cerber' ) . '</a>
            </td>';
		}
		$ret = '<table id="acl_' . $tag . '" class="acl-table" data-acl-slice="' . $acl_slice . '"><tr>' . implode( '</tr><tr>', $list ) . '</tr></table>';
	}
	else {
		$ret = '<p style="text-align: center;">' . __( 'List is empty', 'wp-cerber' ) . '</p>';
	}
	$ret = '<div class="acl-wrapper"><div class="acl-items">'
	       . $ret . '</div>
            <form action="" method="post">
	        <table>
            <tr><td><input class="crb-monospace" type="text" name="add_acl" required maxlength="100" placeholder="' . __( 'IP address, range, wildcard, or CIDR', 'wp-cerber' ) . '"> 
            </td><td><input type="submit" class="button button-primary" value="' . __( 'Add Entry', 'wp-cerber' ) . '" ></td></tr>
            <tr><td><input class="crb-monospace" type="text" name="add_acl_comment" maxlength="250" placeholder="' . __( 'Optional comment for this entry', 'wp-cerber' ) . '"> 
            </td><td></td></tr>
            </table>
            <input type="hidden" name="cerber_admin_do" value="add2acl">
	        <input type="hidden" name="acl_tag" value="' . $tag . '">'
	       . cerber_nonce_field()
	       . '</form></div>';

	return $ret;
}
/*
	Handle actions with items in ACLs in the dashboard
*/
function cerber_acl_form_process( $post = array() ) {
	$tag = crb_array_get( $post, 'acl_tag', '', 'W|B' );
	$ip  = trim( crb_array_get( $post, 'add_acl' ) );
	$ip  = preg_replace( CRB_IP_NET_RANGE, ' ', $ip );
	$ip  = preg_replace( '/\s+/', ' ', $ip );
	$comment = strip_tags( stripslashes( crb_array_get( $post, 'add_acl_comment', '' ) ) );

	if ( $tag == 'B' ) {
		if ( ! cerber_can_be_listed( $ip ) ) {
			cerber_admin_notice( __( "You cannot add your IP address or network", 'wp-cerber' ) );

			return;
		}
		$ok = sprintf( __( 'IP address %s has been added to Black IP Access List', 'wp-cerber' ), $ip );
	}
	else {
		$ok = sprintf( __( 'IP address %s has been added to White IP Access List', 'wp-cerber' ), $ip );
	}

	$result = cerber_acl_add( $ip, $tag, $comment );

	if ( crb_is_wp_error( $result ) ) {
		crb_admin_error_notice( $result );
	}
	else {
		cerber_admin_message( $ok );
	}

	return;

}

/*
	AJAX admin requests are landing here
*/
add_action('wp_ajax_cerber_ajax', 'cerber_admin_ajax');
function cerber_admin_ajax() {

	$go = cerber_check_ajax_permissions( false );

	if ( $go === false ) {
		return false;
	}

	$admin = $go === true;

	$response = array();
	$request  = crb_get_request_fields();

	if ( $admin && ( $ip = crb_array_get( $request, 'acl_delete' ) ) ) {
		if ( cerber_acl_remove( $ip, crb_array_get( $request, 'slice' )  ) ) {
			$response['deleted_ip'] = $ip;
		}
		else {
			$response['error'] = 'Unable to delete the entry';
		}
	}
    elseif ( ( $slug = crb_array_get( $request, 'crb_ajax_slug' ) )
	         && $list = crb_array_get( $request, 'crb_ajax_list' ) ) {

	    $response['slug'] = $slug;
		$list = array_unique( $list );
	    $response_data = array();

		/*
		$list = array_map(function ($ip_id){
			return cerber_get_ip_id( $ip_id );
        }, $list);
		$list = array_filter( $list, function ( $ip ) {
			if (filter_var( $ip, FILTER_VALIDATE_IP )){
			    return true;
            }
		});*/

	    if ( $slug == 'hostname' || $slug == 'country' ) {
		    $ip_list = array();
		    foreach ( $list as $ip_id ) {
			    if ( $ip = filter_var( cerber_get_ip_id( $ip_id ), FILTER_VALIDATE_IP ) ) {
				    $ip_list[ $ip_id ] = $ip;
				    $response_data[ $ip_id ] = '';
			    }
			    else {
				    $response_data[ $ip_id ] = '-';
			    }
		    }
	    }

	    switch ( $slug ) {
		    case 'hostname':
			    foreach ( $ip_list as $ip_id => $ip ) {
				    $ip_info = cerber_get_ip_info( $ip, false );
				    $response_data[ $ip_id ] = $ip_info['hostname_html'];
			    }
			    break;
		    case 'country':
			    if ( $country_list = lab_get_country( $ip_list, false ) ) {
				    foreach ( $country_list as $ip_id => $country ) {
					    if ( $country ) {
						    $response_data[ $ip_id ] = crb_get_flag_html( $country, crb_get_country_name( $country ) );
					    }
					    else {
						    $response_data[ $ip_id ] = __( 'Unknown', 'wp-cerber' );
					    }
				    }
			    }
			    break;
		    case 'cbcc':
			    foreach ( $list as $user_id ) {
				    $response_data[ $user_id ] = 0;
				    $base = admin_url( 'edit-comments.php' );
				    // to get this working we added filter 'preprocess_comment'
				    if ( $com = get_comments( array( 'author__in' => $user_id ) ) ) {
					    $response_data[ $user_id ] = '<a href="' . $base . '?user_id=' . $user_id . '" target="_blank">' . count( $com ) . '</a>';
				    }
			    }
			    break;
		    case 'cbla':
			    $base = cerber_activity_link( array( CRB_EV_LIN ) );
			    foreach ( $list as $user_id ) {
				    $user_id = crb_absint( $user_id );

                    if ( $last_log = crb_get_last_user_login( $user_id ) ) {
					    $ip = $last_log['ip'];
                        $time = $last_log['ts'];
                        $date = ( $time ) ? cerber_date( $time ) : __( 'Unknown', 'wp-cerber' );

                        if ( $ip
                             && ( $country = crb_country_html( $last_log['cn'], $ip, false ) ) ) {
						    $country = '<br />' . $country;
					    }
					    else {
						    $country = '';
					    }

                        $val = '<a href="' . $base . '&amp;filter_user=' . $user_id . '">' . $date . '</a>' . $country;
				    }
				    else {
					    $val = __( 'Never', 'wp-cerber' );
				    }

				    $response_data[ $user_id ] = $val;
			    }
			    break;
		    case 'cbfl':
			    $base = cerber_activity_link( array( CRB_EV_LFL ) );
			    foreach ( $list as $user_id ) {
				    $u = crb_get_userdata( $user_id );
				    $val = 0;

				    $failed = crb_q_cache_get( 'SELECT COUNT(user_id) FROM ' . CERBER_LOG_TABLE . ' WHERE ( user_login = "' . $u->user_login . '" OR user_login = "' . $u->user_email . '" ) AND activity = ' . CRB_EV_LFL . ' AND stamp > ' . ( time() - 7 * 24 * 3600 ), CERBER_LOG_TABLE );

				    if ( ! empty( $failed[0][0] ) ) {
					    $val = '<a href="' . $base . '&amp;filter_login=' . $u->user_email . '|' . $u->user_login . '">' . $failed[0][0] . '</a>';
				    }

				    $response_data[ $user_id ] = $val;
			    }
			    break;
	    }

	    $response['data'] = $response_data;
	}
    elseif ( $admin && isset( $request['dismiss_info'] ) ) {
		if ( isset( $request['button_id'] ) && ( $request['button_id'] == 'lab_ok' || $request['button_id'] == 'lab_no' ) ) {
			lab_user_opt_in( $request['button_id'] );
		}
		else {
			delete_site_option( 'cerber_admin_info' );
		}

	    $response ['message'] = 'OK';
	}
    elseif ( $admin && ( $ajax_route = crb_sanitize_id( crb_array_get( $request, 'ajax_route' ) ) ) ) {

		$ref_params = crb_get_referrer_params();
	    $response = array( 'html' => '', 'error' => '' );
        $result = '';

		switch ( $ajax_route ) {
			case 'dashboard_analytics':
				cerber_widgets_init();
				$result = CRB_Widgets::render_widget( $request['ds_widget'] ?? '', true );
				break;
			case 'scanner_analytics':
				$result = cerber_generate_insights( $request['itype'] );
				break;
			case 'user_activity_analytics':
				$result = crb_generate_user_insights( absint( $request['user_id'] ), $ref_params['tab'] ?? '' );
				break;
			case 'ip_extra_info':
				$result = cerber_ip_extra_view_ajax( $request, $ref_params );
				break;
			case 'ip_quick_analytics':
				$result = crb_generate_ip_insights( $request['ip_address'], $ref_params['tab'] );
				break;
			case 'diagnostic_tools':
				$result = cerber_db_diag();
				break;
			default:
	            $response['error'] = 'Error: Unknown WP Cerber AJAX route (' . $ajax_route . ')';
		}

	    if ( crb_is_wp_error( $result ) ) {
		    $response ['error'] = $result->get_error_message();
	    }
	    else {
		    $response ['html'] = $result;
	    }
	}
    elseif ( $admin && ( $usearch = crb_array_get( $request, 'user_search' ) ) ) {
		$users    = get_users( array( 'search' => '*' . esc_attr( $usearch ) . '*', ) );
		if ( $users ) {
			foreach ( $users as $user ) {
				$data       = crb_get_userdata( $user->ID );
				$response[] = array(
					'id'   => $user->ID,
					'text' => crb_format_user_name( $data )
				);
			}
		}
	}
    elseif ( $admin && $action = $request['cerber_ajax_action'] ?? false ) {

	    $msg = '*';
	    $result = false;

	    switch ( $action ) {
		    case 'dashboard_save_sortable':

			    $order = array_map( function ( $id ) {
				    return crb_sanitize_id( $id );
			    }, $request['dash_order'] );

			    $result = CRB_Widgets::save_order( $order );

			    break;
	    }

	    if ( crb_is_wp_error( $result ) ) {
		    $response ['error'] = $result->get_error_message();
	    }
	    else {
		    $msg = 'OK';
	    }

	    $response ['message'] = $msg;
	}

	if ( empty( $response ) ) {
		$response['error'] = 'Unknown AJAX request. Check your code, it\'s time to show your debugging skills!';
	}

	echo json_encode( $response );

	if ( ! nexus_is_valid_request() ) {
		wp_die();
	}
}

add_action( 'wp_ajax_cerber_local_ajax', function () {
	check_ajax_referer( 'crb-ajax-admin', 'ajax_nonce' );
	if ( ! is_super_admin() ) {
		wp_die( 'Oops! Access denied.' );
	}

	$done  = array();

	switch ( crb_array_get( $_REQUEST, 'crb_ajax_do' ) ) {
		case 'bg_tasks_run':
			$done = cerber_bg_task_launcher( array_flip( crb_array_get( $_REQUEST, 'tasks', array() ) ) );
			break;
	}

	echo json_encode( array( 'done' => $done ) );
	exit;
} );

/**
 * @param string $group
 * @param string $item_id
 *
 * @return string
 */
function crb_get_ajax_placeholder( $group, $item_id ) {

	return '<img class="crb-ajax-load" data-ajax_group="' . $group . '" data-item_id="' . $item_id . '" src="' . CRB_Globals::assets_url( 'ajax-loader.gif' ) . '" />';
}

/*
 * Retrieve extended IP information
 * @since 2.2
 *
 */
function cerber_get_ip_info( $ip, $cache_only = true ) {

	$ip_id = 'info_' . cerber_get_id_ip( $ip );

	$ip_info = cerber_get_set( $ip_id );

	if ( $cache_only  ) {
		return $ip_info;
	}

	if ( empty( $ip_info['hostname_html'] ) ) {
		$ip_info = array();

		if ( ! $hostname = @gethostbyaddr( $ip ) ) {
			$hostname = __( 'unknown', 'wp-cerber' );
		}

        $ip_info['hostname'] = $hostname;

        if ( ! filter_var( $hostname, FILTER_VALIDATE_IP ) ) {
			$hostname = str_replace( '.', '.<wbr>', $hostname );
		}

        $ip_info['hostname_html'] = $hostname;

		cerber_update_set( $ip_id, $ip_info, null, true, time() + 24 * 3600 );
	}

	return $ip_info;
}


/*
	Admin dashboard actions
*/
add_action( 'wp_loaded', // 'wp_loaded' @since 5.6
	function () {
		if ( ! is_admin() ) {
			return;
		}

		cerber_admin_request();

	}, 0 );

/**
 * @param bool $is_post
 *
 * @return array|false|void
 *
 */
function cerber_admin_request( $is_post = false ) {
	global $wpdb;

	if ( ! nexus_is_valid_request()
	     && ! cerber_user_can_manage() ) {
		return;
	}

	$get  = crb_get_query_params();

	if ( ( ! $nonce = crb_array_get( $get, 'cerber_nonce' ) )
	     && ( ! $nonce = crb_get_post_fields( 'cerber_nonce' ) ) ) {

		return;
	}

	if ( ! wp_verify_nonce( $nonce, 'control' ) ) {
        // TODO: Investigate why is this fires while managing a remote website via Cerber.Hub
		//cerber_admin_notice( 'Nonce verification failed.' );

		return;
	}

	$post = crb_get_post_fields();

	//$q = crb_admin_parse_query( array( 'cerber_admin_do', 'ip' ) );

	$remove_args = array();

	if ( cerber_is_http_get() ) {
		if ( ( $do = crb_array_get( $get, 'cerber_admin_do' ) ) ) {
			switch ( $do ) {
				case 'lockdel':
					$err = '';
					$ip = crb_array_get( $get, 'ip' );

					if ( cerber_is_ip_or_net( $ip ) ) {
						if ( cerber_block_delete( $ip ) ) {
							cerber_admin_message( sprintf( __( 'Lockout for %s was removed', 'wp-cerber' ), $ip ) );
						}
						else {
							$err = 'No lockout for the specified IP address ' . $ip . ' found.';
						}
					}
					else {
						$err = 'An invalid IP address has been specified.';
					}

					if ( $err ) {
						cerber_admin_notice( 'Request failed. ' . $err );
					}

                    $remove_args[] = 'ip';
					break;
				case 'testnotify':
					$test_type = crb_array_get( $get, 'type', '', '\w+' );
					$msg = array();

					if ( $test_type == 'plugin_updates' ) {
						if ( false === crb_plugin_update_notifier( true, true, $sent, $msg ) ) {
							$sent = false;
						}
					}
					else {

						$test_chan = crb_array_get( $get, 'channel', '', '\w+' );
						$period = crb_array_get( $get, 'test_period', 'one_week', '\w+' );

						$channels = array();

						if ( $test_chan && ( $test_chan != 'email' || lab_lab() ) ) {
							$channels = array_fill_keys( array_keys( CRB_CHANNELS ), 0 );
							$channels = array_merge( $channels, array( $test_chan => 1 ) );
						}

						$sent = cerber_send_message( $test_type, array( 'subj' => ' *** ' . __( 'TEST MESSAGE', 'wp-cerber' ) . ' *** ' ), $channels, true, array( 'report_id' => $period ) );

					}

					if ( $sent !== false ) {
						$to = ' ' . implode( ', ', $sent );
						/* translators: Here %s is the name of a mobile device or/and email addresses. */
						cerber_admin_message( sprintf( __( 'A message has been sent to %s', 'wp-cerber' ), $to ) );
					}
                    elseif ( ! $msg ) {
						cerber_admin_notice( __( 'Unable to send a message', 'wp-cerber' ) );
					}
					else {
						cerber_admin_notice( $msg );
					}

					$remove_args[] = 'type';
					$remove_args[] = 'channel';
					break;
				case 'subscribe':
					$m = crb_array_get( $get, 'mode' );
					$mode = ( 'on' == $m ) ? 'on' : 'off';
					crb_admin_alerts_do( $mode );
					$remove_args = array_merge( $remove_args, CRB_NON_ALERT_PARAMS );
					$remove_args[] = 'subscribe';
					$remove_args[] = 'mode';
					break;
				case 'nexus_set_role':
					nexus_enable_role();
					crb_safe_redirect( cerber_admin_link( '', array( 'page' => 'cerber-nexus' ) ) );
					exit();
					break;
				case 'nexus_delete_slave':
					crb_safe_redirect( cerber_admin_link( 'nexus_sites' ) );
					exit();
					break;
				case 'nexus_site_table':
					if ( cerber_get_bulk_action() ) {
						nexus_do_bulk();
					}
					break;
				case 'scan_tegrity':
					$adm  = crb_array_get( $get, 'crb_scan_adm' );
					$file = crb_array_get( $get, 'crb_file_id' );
					if ( in_array( $adm, array( 'delete', 'restore' ) ) ) {
						cerber_quarantine_do( $adm, crb_array_get( $get, 'crb_scan_id' ), crb_array_get( $get, 'crb_file_id' ) );
					}
                    elseif ( $adm == 'remove_ignore' ) {
						if ( crb_remove_ignore( $file ) ) {
							cerber_admin_message( 'The file has been removed from the list' );
						}
					}
					$remove_args = array( 'crb_scan_adm', 'crb_scan_id', 'crb_file_id' );
					break;
				case 'manage_diag_log':
					cerber_manage_diag_log( crb_array_get( $get, 'do_this' ) );
					$remove_args[] = 'do_this';
					break;
				case 'terminate_session':
					crb_sessions_kill( crb_array_get( $get, 'id', null, '\w+' ), crb_array_get( $get, 'user_id' ) );
					$remove_args = array( 'user_id', 'id' );
					break;
				case 'crb_manage_sessions':
					if ( cerber_get_bulk_action() == 'bulk_session_terminate' ) {
						crb_sessions_kill( crb_array_get( $get, 'ids', array(), '\w+' ) );
					}
                    elseif ( cerber_get_bulk_action() == 'bulk_block_user' ) {
						if ( ( $sids = crb_array_get( $get, 'ids', array(), '\w+' ) )
						     && ( $users = cerber_db_get_col( 'SELECT user_id FROM ' . cerber_get_db_prefix() . CERBER_USS_TABLE . ' WHERE wp_session_token IN ("' . implode( '","', $sids ) . '")' ) ) ) {
							array_walk( $users, 'cerber_block_user' );
						}
					}
					break;
				case 'export':
					if ( nexus_is_valid_request() ) {
						return crb_admin_get_tokenized_link();
					}
					crb_admin_download_file( crb_array_get( $get, 'type' ) );
					break;
				case 'load_defaults':
					cerber_load_defaults();
					cerber_admin_message( __( 'Default settings have been loaded', 'wp-cerber' ) );
					break;
				case 'clear_cache':
					CRB_Cache::reset();
					break;
				default:
					return;
			}

			if ( nexus_is_valid_request() ) {
				return array( 'redirect' => true, 'remove_args' => $remove_args );
			}
			else {
				cerber_safe_redirect( $remove_args );
			}

		}

		// TODO: move to the switch above
        if ( cerber_get_get( 'citadel_do' ) == 'deactivate' ) {
			cerber_disable_citadel();
		}
		elseif ( isset( $_GET['force_repair_db'] ) ) {
			cerber_create_db();
			cerber_upgrade_db( true );
			cerber_admin_message( 'Cerber\'s database tables have been repaired and upgraded' );
			cerber_safe_redirect('force_repair_db');
		}
        elseif ( $table = cerber_get_get( 'truncate', '[a-z_]+' ) ) {
	        if ( 0 === strpos( $table, 'cerber_' ) ) {
		        if ( $wpdb->query( 'TRUNCATE TABLE ' . $table ) ) {

			        if ( $table == CERBER_LOG_TABLE ) {
				        cerber_cache_set( CRB_ACT_HASH, array() );
			        }

			        cerber_admin_message( 'Table ' . $table . ' has been truncated' );
		        }
		        else {
			        cerber_admin_notice( $wpdb->last_error );
		        }
	        }
	        cerber_safe_redirect( 'truncate' );
        }
        elseif ( isset( $_GET['force_check_nodes'] ) ) {
	        $best = lab_check_nodes( true );
	        cerber_admin_message( 'Connectivity to Cerber Security Cloud has been checked. The closest node: ' . $best );
	        cerber_safe_redirect( 'force_check_nodes' );
        }
        elseif ( isset( $_GET['clear_up_lab_cache'] ) ) {
	        lab_cleanup_cache();
	        cerber_admin_message( 'The Cerber Security Cloud cache has been cleared' );
	        cerber_safe_redirect( 'clear_up_lab_cache' );
        }
        elseif ( isset( $_GET['clear_up_the_cache'] ) ) {
	        lab_cleanup_cache();
	        CRB_Cache::reset();
	        CRB_Wisdom::clear_cache( true );
	        cerber_delete_expired_set( true );
	        cerber_remove_issues();
	        cerber_admin_message( 'The plugin cache has been cleared' );
	        cerber_safe_redirect( 'clear_up_the_cache' );
        }
	}

	if ( cerber_is_http_post() ) {

	    $redirect = false;

		if ( ( $do = crb_array_get( $post, 'cerber_admin_do' ) ) ) {
			switch ( $do ) {
				case 'save_widget_list':
                    cerber_widgets_init();
					CRB_Widgets::save_list( $post );
					$redirect = true;
					break;
				case 'update_role_policies':
					crb_settings_update_role_policies( $post );
					$redirect = true;
					break;
				case 'update_geo_rules':
					crb_settings_update_geo_rules( $post );
					break;
				case 'add2acl':
					cerber_acl_form_process( $post );
					break;
				case 'add_slave':
					nexus_add_client( crb_array_get( $post, 'new_slave_token' ) );
					break;
				case 'install_key':
					$lic = preg_replace( "/[^A-Z0-9]/i", '', crb_array_get( $post, 'cerber_license' ) );
					if ( ( strlen( $lic ) == LAB_KEY_LENGTH ) || empty( $lic ) ) {
						lab_cleanup_cache();
						cerber_delete_expired_set( true );
						lab_get_site_meta();

						lab_update_key( $lic );

						if ( $lic ) {
							if ( lab_validate_lic() ) {

								$msg = '<b>' . __( 'Great! Your license key is valid.', 'wp-cerber' ) . '</b>';
								$msg .= '<p>' . __( 'Now, whenever you see a green shield icon in the top right-hand corner of any WP Cerber admin page, it means the professional version works as intended, and your website is protected by WP Cerber Security Cloud.', 'wp-cerber' ) . '</p>';
								$msg .= '<p>' . sprintf( __( 'Please use our client portal to manage your subscription, license keys and get support at %s', 'wp-cerber' ), '<a target="_blank" href="https://my.wpcerber.com">https://my.wpcerber.com</a>' ) . '</p>';
								$msg .= '<p>' . __( 'Thank you for being our client.', 'wp-cerber' ) . '</p>';

								cerber_admin_message( $msg );

							}
							else {
								cerber_admin_notice( 'Error! You have entered an invalid or expired license key.' );
							}
						}

						$redirect = true;
					}
					break;
			}

			if ( $redirect ) {
				if ( nexus_is_valid_request() ) {
				    // No redirection is needed so far, we use a second 'get_page' request
					//return array( 'redirect' => true, 'remove_args' => $remove_args );
				}
				else {
					cerber_safe_redirect( $remove_args );
				}
			}
		}
	}

}

function crb_admin_download_file( $t, $query = array() ) {
	switch ( $t ) {
		case 'activity':
			cerber_export_activity( $query );
			break;
		case 'traffic':
			cerber_export_traffic( $query );
			break;
		case 'get_diag_log':
			cerber_manage_diag_log( 'download' );
			break;
	}
}

/**
 * Redirects safely to the current URL while removing specified query parameters.
 *
 * This function performs a safe local redirect using wp_safe_redirect() after removing the specified query
 * parameters from the current URL. It ensures that commonly used temporary query parameters are also removed.
 *
 * @param string|string[] $rem_args A single query parameter or an array of query parameters to be removed.
 *                                   If empty, only predefined, single-use parameters are removed.
 *
 * @return void
 */
function cerber_safe_redirect( $rem_args ) {
	if ( empty( $rem_args ) ) {
		$rem_args = array();
	}
    elseif ( ! is_array( $rem_args ) ) {
	    $rem_args = array( (string) $rem_args );
	}

	// These are most used "temporary" query parameters

	$rem_args = array_merge( $rem_args, array(
		'_wp_http_referer',
		'_wpnonce',
		'cerber_nonce',
		'ids',
		'cerber_admin_do',
		'action',
		'action2'
	) );

	$url = remove_query_arg( $rem_args );

	crb_safe_redirect( $url );
	exit();
}

function crb_admin_get_tokenized_link() {
	if ( empty( nexus_request_data()->get_params ) ) {
		return false;
	}
	$key = crb_random_string( 26 );
	if ( cerber_update_set( '_the_key_' . $key, array( 'query' => nexus_request_data()->get_params ), 1, true, time() + 60 ) ) {
		return array( 'redirect' => true, 'redirect_url' => home_url( '?cerber_magic_key=' . $key ) );
	}

	cerber_admin_notice( 'Unable to generate a tokenized URL' );

	return false;
}

function cerber_export_activity( $params = array() ) {

	crb_raise_limits( 512 );

	$args = array( 'per_page' => 0 );

	if ( $params ) {
		$args = array_merge( $params, $args );
	}

	list( $query, $per_page, $falist, $ip, $filter_login, $user_id, $search, $sid, $in_url ) = CRB_Activity::parse_query( $args );

	// We split into several requests to avoid PHP and MySQL memory limitations

	if ( defined( 'CERBER_EXPORT_CHUNK' ) && is_numeric( CERBER_EXPORT_CHUNK ) ) {
		$per_chunk = CERBER_EXPORT_CHUNK;
	}
	else {
		$per_chunk = 1000; 	// Rows per SQL request: a compromise between the size of SQL data at each iteration and script execution time
	}

	if ( ! $result = cerber_db_query( $query . ' LIMIT ' . $per_chunk ) ) {
		wp_die( 'Nothing to export' );
	}

	$total = cerber_db_get_var( "SELECT FOUND_ROWS()" );

	$info = array();

	if ( $ip ) {
		$info[] = '"Filter by IP:","' . $ip . '"';
	}

	if ( $user_id ) {
		$user    = crb_get_userdata( $user_id );
		$info[] = '"Filter by user:","' . $user->display_name . '"';
	}
	if ( $search ) {
		$info[] = '"Search results for:","' . $search . '"';
	}

	$heading = array(
		__( 'IP Address', 'wp-cerber' ),
		__( 'Date', 'wp-cerber' ),
		__( 'Event', 'wp-cerber' ),
		__( 'Status', 'wp-cerber' ),
		__( 'Local User', 'wp-cerber' ),
		__( 'User login', 'wp-cerber' ),
		__( 'User ID', 'wp-cerber' ),
		__( 'Username', 'wp-cerber' ),
		__( 'By user', 'wp-cerber' ),
		'Unix Timestamp',
		'Request ID',
		'URL',
	);

	cerber_send_csv_header( 'wp-cerber-activity', $total, $heading, $info );

	$labels = cerber_get_labels( 'activity' );
	$status_labels = cerber_get_labels( 'status' ) + cerber_get_reason();

	$placeholder = array( 0, '', '', '', '' );

	if ( crb_get_settings( 'plain_date' ) ) {
		function _crb_csv_date( $timestamp ) {
			static $gmt_offset;
			if ( $gmt_offset === null ) {
				$gmt_offset = get_option( 'gmt_offset' ) * 3600;
			}
			return date( 'Y-m-d H:i:s', $timestamp + $gmt_offset );
		}
	}
	else {
		function _crb_csv_date( $timestamp ) {
			return cerber_date( $timestamp, false );
		}
	}

	// The loop

	$i = 0;

	do {

		while ( $row = mysqli_fetch_object( $result ) ) {

			$values = array();

			if ( ! empty( $row->details ) ) {
				$details = explode( '|', $row->details );
			}
			else {
				$details = $placeholder;
			}

			$values[] = $row->ip;
			$values[] = _crb_csv_date( $row->stamp );
			$values[] = $labels[ $row->activity ];

			$values[] = $status_labels[ $row->ac_status ] ?? '';

			$values[] = $row->display_name;
			$values[] = $row->ulogin;
			$values[] = $row->user_id;
			$values[] = $row->user_login;

            $values[] = $row->ac_by_user;

			$values[] = $row->stamp;
			$values[] = $row->session_id;
			$values[] = $details[4];

			cerber_send_csv_line( $values );
		}

		mysqli_free_result( $result );

		$i ++;
		$offset = $per_chunk * $i;

	} while ( ( $result = cerber_db_query( $query . ' LIMIT ' . $offset . ', ' . $per_chunk ) )
	          && $result->num_rows );

	exit;

}

function cerber_send_csv_header( $f_name, $total, $heading = array(), $info = array() ) {

	$file_name = $f_name . '-' . crb_site_label() . '.csv';

	crb_file_headers( $file_name );

	$info[] = '"Generated by:","WP Cerber Security ' . CERBER_VER . '"';
	$info[] = '"Website:","' . crb_get_blogname_decoded() . '"';
	$info[] = '"Date:","' . cerber_date( time(), false ) . '"';
	$info[] = '"Rows in this file:","' . $total . '"';

	echo implode( "\r\n", $info ) . "\r\n\r\n";

	foreach ( $heading as &$item ) {
		$item = '"' . str_replace( '"', '""', trim( $item ) ) . '"';
	}

	echo implode( ',', $heading ) . "\r\n";

}

/**
 * Formats and outputs a CSV line
 *
 * @param string[] $row_values
 *
 * @return void
 */
function cerber_send_csv_line( array $row_values ) {

	foreach ( $row_values as &$value ) {
		$value = '"' . str_replace( '"', '""', trim( (string) $value ) ) . '"';
	}

	echo implode( ',', $row_values ) . "\r\n";
}

/**
 * Returns HTML code of a set of navigation links to be used for quick filtering logs.
 *
 * @param string $context Defines what links are included in the set
 *
 * @return string Escaped HTML code
 */
function crb_admin_activity_nav_links( $context = '' ) {

	$links = array();

	if ( ! $context ) {
		$links[] = array( array(), __( 'View all', 'wp-cerber' ) );

		$labels = cerber_get_labels( 'activity' );

		$set = array( CRB_EV_LIN );
		foreach ( $set as $item ) {
			$links[] = array( array( 'filter_activity' => $item ), $labels[ $item ] );
		}
	}

	if ( $context == 'users' ) {
		$links[] = array( array( 'filter_user' => '*' ), __( 'View all', 'wp-cerber' ) );
	}

	if ( $context != 'suspicious' ) {
		$links[] = array( array( 'filter_activity' => array( 1, 2 ) ), __( 'New users', 'wp-cerber' ) );
		$links[] = array( array( 'filter_set' => 2 ), __( 'Login issues', 'wp-cerber' ) );
	}

	if ( $context != 'users' ) {
		if ( ! $context ) {
			$txt = __( 'Suspicious activity', 'wp-cerber' );
		}
		else {
			$txt = __( 'View all', 'wp-cerber' );
		}

		$links[] = array( array( 'filter_set' => 1 ), $txt );
	}

	if ( ! $context ) {
		$links[] = array( array( 'filter_status' => array( CRB_STS_11, CRB_STS_532, 706 ) ), __( 'Spam Events', 'wp-cerber' ) );
		$links[] = array( array( 'filter_activity' => array( 10, 11 ) ), __( 'IP blocked', 'wp-cerber' ) );
		$links[] = array( array( 'filter_user' => '*' ), __( 'Users', 'wp-cerber' ) );
		$links[] = array( array( 'filter_user' => 0 ), __( 'Non-authenticated', 'wp-cerber' ) );
		if ( ! nexus_is_valid_request() ) {
			$links[] = array( array( 'filter_user' => get_current_user_id() ), __( 'My activity', 'wp-cerber' ) );
			$links[] = array( array( 'filter_ip' => cerber_get_remote_ip() ), __( 'My IP', 'wp-cerber' ) );
		}
	}

	return crb_make_nav_links( $links, 'activity' );

}

/**
 * Generates HTML code for a set of links
 *
 * @param array $link_list A list of link definitions
 * @param string $tab Admin page tab to generate links for
 * @param string $class CSS class for the link wrapper
 *
 * @return string Escaped HTML
 *
 * @since 8.8.3.2
 */
function crb_make_nav_links( $link_list, $tab = 'activity', $class = '' ) {

	$params = crb_get_referrer_params();
	unset( $params['page'], $params['tab'], $params['pagen'] );

	$ret = '';

	foreach ( $link_list as $link_config ) {
		$query_string = '';
		$selected = false;
		$other = $params;

		if ( $parameters = $link_config[0] ?? '' ) {

			$query_string .= '&' . http_build_query( $parameters );
            $selected = true;

			foreach ( $parameters as $name => $value ) {
				if ( ! is_array( $value ) ) {
					if ( crb_array_get( $params, $name ) !== (string) $value ) {
						$selected = false;
					}
					else {
						unset( $other[ $name ] );
					}
				}
				else {
					foreach ( $value as $key => $val ) {
						if ( crb_array_get( $params, array( $name, $key ) ) !== (string) $val ) {
							$selected = false;
						}
						else {
							unset( $other[ $name ][ $key ] );
						}
					}
				}
			}
		}

		$other = array_filter( $other, function ( $val ) {
			return $val === 0 || ! empty( $val );
		} );

		$anchor = crb_escape_html( $link_config[1] );

		if ( $selected & empty( $other ) ) {
			$ret .= '<span class="crb_selected">' . $anchor . '</span> ';
		}
		else {
			$ret .= '<a class="crb-nav-link" href="' . crb_escape_url( cerber_admin_link( $tab ) . $query_string ) . '">' . $anchor . '</a> ';
		}
	}

	return '<div class="crb-tag-buttons ' . crb_sanitize_alphanum( $class ) . '">' . $ret . '</div>';
}

/*
 * Display activities in the WP Dashboard
 *
 *
 */
function cerber_show_activity( $args = array(), $echo = true ) {

	$status_labels = cerber_get_labels( 'status' ) + cerber_get_reason();
	$base_url = cerber_activity_link();

	$is_log_empty = CRB_Activity::is_empty();

	$export_link = '';
	$ret = '';

	list( $query, $per_page, $falist, $filter_ip, $filter_login, $user_id, $search, $sid, $in_url ) = CRB_Activity::parse_query( $args );

	$sname = '';
	$info  = '';

	$_find = array();

	if ( $filter_login ) {
		$login = explode( '|', $filter_login );
		if ( is_email( $login[0] ) ) {
			$_find[] = array( 'email', $login[0] );
		}
		else {
			$_find[] = array( 'login', $login[0] );
		}

		if ( isset( $login[1] ) ) {
			if ( is_email( $login[1] ) ) {
				$_find[] = array( 'email', $login[1] );
			}
			else {
				$_find[] = array( 'login', $login[1] );
			}
		}
	}

	if ( $search ) {
		if ( cerber_is_ip( $search ) ) {
			$filter_ip = $search;
		}
		else {
			if ( is_email( $search ) ) {
				$_find[] = array( 'email', $search );
			}
			else {
				$_find[] = array( 'login', $search );
			}
		}
	}

	if ( ! $user_id && $_find ) {
		foreach ( $_find as $item ) {
			if ( $user = get_user_by( $item[0], $item[1] ) ) {
				$user_id = $user->ID;
				break;
			}
		}
	}

	if ( $user_id ) {
		$sname = crb_format_user_name( $user_id );
		$info .= cerber_user_extra_view( $user_id );
	}

	if ( $filter_ip ) {
		$info .= cerber_ip_extra_view( $filter_ip );
	}

	if ( empty( $args['no_navi'] ) ) {
		$rows = CRB_Activity::get_rows( $query, $total );
	}
	else {
		$rows = CRB_Activity::get_rows( $query );
	}

	if ( $rows ) {

		$no_results = false;

		if ( empty( $args['no_navi'] ) ) {
			// No cache
			//$total = cerber_db_get_var( "SELECT FOUND_ROWS()" );
		}

		$hidden = array_flip( $args['hide_columns'] ?? array() );

		if ( ! lab_lab() ) {
			$hidden['crb_country_col'] = true;
		}

		$tbody = '';

		if ( ! defined( 'CERBER_FULL_URI' ) || ! CERBER_FULL_URI ) {
			$short = true;
			$site_url = cerber_get_home_url();
			$site_url = substr( $site_url, strpos( $site_url, '://' ) + 3 );
			$start = strlen( rtrim( $site_url, '/' ) );
		}
		else {
			$short = false;
			$start = 0;
        }

		foreach ( $rows as $row ) {

			$country = '';
			$user_cell = '';
			$username_cell = '';
			$svg = '';
			$act = (int) $row->activity;

			$ip_id = cerber_get_id_ip( $row->ip );

			$activity = '<span class="crb-activity-single actv' . $act . '" title="' . $act . '">' . crb_get_activity_label( $act, $row->user_id, $row->ac_by_user ) . '</span>';

			if ( empty( $args['no_details'] ) ) {
				$status = (int) $row->ac_status;

				if ( $sts_label = crb_array_get( $status_labels, $status ) ) {
					$activity .= ' <span class = "crb-log-status crb-status-' . $status . '" title="' . $status . '">' . $sts_label . '</span>';
				}

				$details = explode( '|', $row->details );
				$uri = crb_array_get( $details, 4 );

                $svg = CRB_Explainer::create_popup( $row->activity, $row->ac_status, $row->user_id, crb_array_get( $details, 0, '' ), $row->ip );

				if ( $uri
				     && ( $act < 10 || $act > 12 ) ) {

					if ( $short && 0 === strpos( $uri, $site_url ) ) {
						$ac_uri = substr( $uri, $start );
					}
					else {
						$ac_uri = $uri;
					}

					$activity .= '<div class="crb_act_url" title="' . crb_attr_escape( $uri ) . '">' . str_replace( array( '-', '/' ), array(
							'<wbr>-',
							'<wbr>/'
						), crb_escape_html( urldecode( $ac_uri ) ) ) . '</div>';
				}

			}

			$activity = '<div class="' . crb_get_act_style( $act ) . '">' . $activity . '</div>';

			if ( ! isset( $hidden['crb_user_col'] ) ) {
				$user_cell = '<td>' . crb_admin_get_user_cell( $row->user_id, $base_url ) . '</td>';
			}

			if ( ! isset( $hidden['crb_username_col'] ) ) {
				if ( $row->user_login ) {
					$login_used = crb_escape_html( $row->user_login );
					$username_cell = '<a href="' . $base_url . '&amp;filter_login=' . $login_used . '">' . $login_used . '</a>';
				}

				$username_cell = '<td>' . $username_cell . '</td>';
			}

			$ip_info = cerber_get_ip_info( $row->ip );
			$hostname = $ip_info['hostname_html'] ?? crb_get_ajax_placeholder( 'hostname', $ip_id );

			if ( ! empty( $args['date'] ) && $args['date'] == 'ago' ) {
				$date = cerber_ago_time( $row->stamp );
			}
			else {
				$date = '<span title="' . $row->stamp . ' / ' . $row->session_id . ' / ' . $act . '">' . cerber_date( $row->stamp ) . '</span>';
			}

			if ( ! isset( $hidden['crb_country_col'] ) ) {
				$country = '<td>' . crb_country_html( $row->country, $row->ip ) . '</td>';
			}

			$tbody .= '<tr><td>' . crb_admin_ip_cell( $row->ip, $base_url . '&amp;filter_ip=' . $row->ip ) . '</td><td class="crb-hostname">' . $hostname . '</td>' . $country . '<td>' . $date . '</td><td class="crb-act-info"><div class="crb-act-holder">' . $activity . $svg . '</div></td>' . $user_cell . $username_cell . '</tr>';

		}

		$heading = array(
			'crb_ip_col'       => '<div class="crb_act_icon"></div>' . __( 'IP Address', 'wp-cerber' ),
			'crb_hostname_col' => __( 'Hostname', 'wp-cerber' ),
			'crb_country_col'  => __( 'Country', 'wp-cerber' ),
			'crb_date_col'     => __( 'Date', 'wp-cerber' ),
			'crb_event_col'    => __( 'Event', 'wp-cerber' ),
			'crb_user_col'     => __( 'Local User', 'wp-cerber' ),
			'crb_username_col' => __( 'Username', 'wp-cerber' )
		);

		if ( $hidden ) {
			$heading = array_diff_key( $heading, $hidden );
		}

		$table_header = '';
		$table_footer = '';

        foreach ( $heading as $id => $title ) {
			$table_header .= '<th id="' . $id . '" class="' . $id . '-class">' . $title . '</th>';
			$table_footer .= '<th class="' . $id . '-class">' . $title . '</th>';
		}

		$class = ' widefat crb-table cerber-margin crb-' . count( $heading ) . '-columns';

		if ( $context = $args['context'] ?? '' ) {
			$class .= ' crb-activity--' . crb_sanitize_id( $context );
		}

		$table = '<table id="crb-activity" class="' . $class . '"><thead><tr>' . $table_header . '</tr></thead><tfoot><tr>' . $table_footer . '</tr></tfoot><tbody>' . $tbody . '</tbody></table>';

		if ( empty( $args['no_navi'] ) ) {
			$table .= cerber_page_navi( $total, $per_page );
		}

		//$legend  = '<p>'.sprintf(__('Showing last %d records from %d','wp-cerber'),count($rows),$total);

		if ( empty( $args['no_export'] ) ) {
			$export_link .= '<a download class="button button-secondary cerber-button" href="' .
			                cerber_admin_link_add( array(
				                'cerber_admin_do' => 'export',
				                'type'            => 'activity',
			                ), true ) . '"><i class="crb-icon crb-icon-bx-download"></i> ' . __( 'Export', 'wp-cerber' ) . '</a>';
		}
	}
	else {

        // No results found -------------------------------------------------------------

        $no_results = true;

        // Is it a search (filter) request?

		$is_search = ( array_intersect_key( CRB_Activity::FILTERING_PARAMS, crb_get_query_params() ) );

		$hints = array();

		if ( ! $is_search ) {
			$hints[] = __( 'No activity has been logged yet.', 'wp-cerber' );
		}
		else {
			$hints[] = __( 'No events found using the given search criteria', 'wp-cerber' );

			if ( crb_admin_alert_exists() ) {
				$hints[] = __( 'You will be notified when such an event occurs', 'wp-cerber' ) . ' [ ' . crb_admin_alert_dialog( false, '', __( 'Delete', 'wp-cerber' ) ) . ' ]';
			}
            elseif ( $alert_link = crb_admin_alert_dialog( false, __( 'Get me notified when such an event occurs', 'wp-cerber' ) ) ) {
				$hints[] = $alert_link;
			}

			if ( ! $is_log_empty ) {
				$hints[] = '<a href="' . $base_url . '">' . __( 'View all logged events', 'wp-cerber' ) . '</a>';
			}

			$trf_params = array();
			if ( cerber_is_ip_or_net( $search ) ) {
				$trf_params['filter_ip'] = $search;
			}
			if ( $trf_params ) {
				$hints[] = '<p class="cerber-margin"><a href="' . cerber_admin_link( 'traffic', $trf_params ) . '">' . __( 'Check for requests from the IP address', 'wp-cerber' ) . ' ' . $trf_params['filter_ip'] . '</a></p>';
			}
		}

		$table = '<div class="cerber-margin crb-rectangle"><p>' . implode( '</p><p>', $hints ) . '</p></div>';
	}

	if ( empty( $args['no_navi'] ) ) {

		$display = ( $sid || $in_url ) ? '' : 'display: none;';

		$filters = '<form action="' . cerber_activity_link() . '"><div id="crb-activity-fields"><div>'

		           . crb_get_activity_dd()
		           . cerber_select( 'filter_user', ( $user_id ) ? array( $user_id => $sname ) : array(), $user_id, 'crb-select2-ajax', '', false, esc_html__( 'Filter by registered user', 'wp-cerber' ), array( 'min_symbols' => 3 ) )
		           . '<input type="text" value="' . $search . '" name="search_activity" placeholder="' . esc_html__( 'Search for IP or username', 'wp-cerber' ) . '">
	   
		   <div id="crb-more-activity-fields" style="' . $display . '">		           
		   <input type="text" value="' . $sid . '" name="filter_sid" placeholder="' . esc_html__( 'Request ID', 'wp-cerber' ) . '">
		   <input type="text" value="' . $in_url . '" name="search_url" placeholder="' . esc_html__( 'Search in URL', 'wp-cerber' ) . '">		           
		   </div>
		   
		   </div>
		   
		   <div class="crb-act-controls">
		   <button type="submit" class="cerber-button button button-primary">
		   ' . __( 'Filter', 'wp-cerber' ) . '
		   </button>		   
		   </div>
		   
		   <div class="crb-act-controls">
		   [&nbsp;<a href="#" class="crb-opener" data-target="crb-more-activity-fields">More</a>&nbsp;]		   
		   </div>
		   		   
		   </div>
		   		      
		   <input type="hidden" name="page" value="cerber-security" >
		   <input type="hidden" name="tab" value="activity">
		   </form>';

		$right_links = '';

		if ( ! $no_results ) {
			$right_links = crb_admin_alert_dialog();
		}

		$right_links .= $export_link;

		$top_bar = '<div id="activity-filter"><div>' . $filters . '</div><div>' . $right_links . '</div></div><br style="clear: both;">';

		$quick = '';
		if ( ! $is_log_empty ) {
			$quick = crb_admin_activity_nav_links();
		}

        $ret = '<div><div class="crb-quick-nav">' . $quick . '</div>' . $top_bar . $info . '</div>' . $ret;

	}

	$ret .= $table;

	if ( $echo ) {
		echo $ret;
	}
	else {
		return $ret;
	}

}

/**
 * Generates activity breakdown report
 *
 * @param bool $just_check If true, will return true if the activity log contains data for generating the report
 *
 * @return string HTML code of the report table
 *
 * @since 9.6.4.1
 */
function cerber_activity_summary( $just_check = false ) {

	$stamp = time() - 24 * 3600;

	$rows   = array();
	$not_in = ' activity NOT IN (' . crb_get_activity_set( 'blocked', true ) . ')';

	$logged = (bool) cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' WHERE stamp >= ' . $stamp . ' AND ' . $not_in . ' LIMIT 1' );

	if ( ! $logged ) {
		return '';
	}

	if ( $just_check ) {
		return true;
	}

	$events = cerber_db_get_results( 'SELECT activity, COUNT(activity) cnt FROM ' . CERBER_LOG_TABLE . ' WHERE stamp >= ' . $stamp . ' AND ' . $not_in . ' GROUP by activity ORDER BY cnt DESC', MYSQL_FETCH_OBJECT );

	$events = array_slice( $events, 0, 10 );

    $events_not = cerber_db_get_results( 'SELECT activity, COUNT(activity) cnt FROM ' . CERBER_LOG_TABLE . ' WHERE stamp >= ' . $stamp . ' AND user_id = 0 GROUP by activity', MYSQL_FETCH_OBJECT_K );
	$events_aut = cerber_db_get_results( 'SELECT activity, COUNT(activity) cnt FROM ' . CERBER_LOG_TABLE . ' WHERE stamp >= ' . $stamp . ' AND user_id > 0 GROUP by activity', MYSQL_FETCH_OBJECT_K );

    $lables = cerber_get_labels();
	$ac_base = cerber_admin_link( 'activity' );
	$ac_base_time = $ac_base . '&amp;filter_time_begin=' . $stamp;

	foreach ( $events as $a ) {
		if ( $a->cnt < 1 ) {
			break;
		}

		$act = absint( $a->activity );

		$no_user = 0;
		if ( $r = crb_array_get( $events_not, $a->activity, 0 ) ) {
			$no_user = '<a href="' . $ac_base_time . '&amp;filter_activity=' . $act. '&amp;filter_user=0">' . $r->cnt . '</a>';
		}

        $yes_user = 0;
		if ( $r = crb_array_get( $events_aut, $a->activity, 0 ) ) {
			$yes_user = '<a href="' . $ac_base_time . '&amp;filter_activity=' . $act . '&amp;filter_user=*">' . $r->cnt . '</a>';
		}

        $rows[] = array(
			'<a href="' . $ac_base . '&amp;filter_activity=' . $act . '">' . $lables[ $act ] . '</a>',
			$yes_user,
			$no_user,
	        '<a href="' . $ac_base_time . '&amp;filter_activity=' . $act . '">' . $a->cnt . '</a>'
		);
	}

	return cerber_make_table( $rows, array( __( 'Event', 'wp-cerber' ), __( 'Known Users', 'wp-cerber' ), __( 'Unauthenticated Users', 'w-cerber' ), __( 'Total', 'w-cerber' ) ), '', 'crb-table-align-right-2', '', '', false );
}

/**
 * Generates a report of the top 10 malicious IP addresses detected in the last 24 hours.
 *
 * The report includes the total number of incidents, spam activities, and blocked actions
 * for each IP address. Each entry also provides additional metadata such as hostname and
 * country (if available). The results are returned as an HTML table.
 *
 * @return string|array
 *
 * @since 9.6.4.7
 */
function crb_top_malicious_ip_report() {

	$stamp = time() - 24 * 3600;
	$in = crb_get_activity_set( 'suspicious', true );
	$in_spam = crb_get_activity_set( 'spam', true );

	$results = cerber_db_get_results(
		'SELECT ip, country, COUNT(*) AS total,
                SUM(activity IN (10, 11)) AS blocked,
                SUM(activity IN (' . $in_spam . ')) AS spam 
         FROM ' . CERBER_LOG_TABLE . ' 
         WHERE stamp >= ' . $stamp . ' AND activity IN (' . $in . ') 
         GROUP BY ip 
         ORDER BY total DESC 
         LIMIT 10',
		MYSQL_FETCH_OBJECT
	);

	if ( empty( $results ) ) {
		return array( 'no_data' => __( 'No malicious activity detected in the last 24 hours.', 'wp-cerber' ) );
	}

	$rows = [];

	$base_url = cerber_activity_link();
	$base_url_time = $base_url . '&amp;filter_time_begin=' . $stamp;
	$show_country = false;

	foreach ( $results as $row ) {

		$ip_id = cerber_get_id_ip( $row->ip );
		$ip_info = cerber_get_ip_info( $row->ip );
		$hostname = $ip_info['hostname_html'] ?? crb_get_ajax_placeholder( 'hostname', $ip_id );
		$country = crb_country_html( $row->country, $row->ip );

		$cells = [
			crb_admin_ip_cell( $row->ip, $base_url . '&amp;filter_ip=' . $row->ip ),
			array( 'cell' => $hostname, 'class' => 'crb-hostname' ),
		];

		if ( $country ) {
			$cells[] = $country;
			$show_country = true;
		}

		$link_base = $base_url_time . '&amp;filter_ip=' . $row->ip;

		$cells[] = '<a href="' . $link_base . '&amp;filter_set=1">' . $row->total . '</a>';
		$cells[] = $row->spam ? '<a href="' . $link_base . '&amp;filter_set=3">' . $row->spam . '</a>' : $row->spam;
		$cells[] = $row->blocked ? '<a href="' . $link_base . '&amp;filter_activity[0]=10&amp;filter_activity[1]=11">' . $row->blocked . '</a>' : $row->blocked;

		$rows[] = $cells;
	}

	$heading = [ '<div class="crb_act_icon"></div>' . __( 'IP Address', 'wp-cerber' ), __( 'Hostname', 'wp-cerber' ) ];

	if ( $show_country ) {
		$heading[] = __( 'Country', 'wp-cerber' );
	}

	$heading[] = __( 'Incidents', 'wp-cerber' );
	$heading[] = __( 'Spam', 'wp-cerber' );
	$heading[] = __( 'Blocked', 'wp-cerber' );

	return cerber_make_table(
		$rows,
		$heading,
		'',
		'crb-activity--dashboard crb-table-align-right-4',
		'',
		'',
		false
	);
}

/**
 * @param int $ac Activity ID
 *
 * @return string CSS class name
 *
 * @since 9.5.3.1
 */
function crb_get_act_style( $ac ) {
	static $green = array( CRB_EV_LIN );

	$ac = crb_absint( $ac );

	$class = '';

	if ( in_array( $ac, $green ) ) {
		$class = 'crb-act-granted';
	}
    elseif ( in_array( $ac, crb_get_activity_set( 'denied_by_crb' ) ) ) {
		$class = 'crb-act-denied';
	}

	return 'crb-act-' . $ac . ' ' . $class;
}

/**
 * Generates an HTML markup for a given IP address to be shown on the plugin admin pages
 *
 * @param string $ip Valid IP address
 * @param string $ip_link A link to wrap the IP address
 * @param string $html Any escaped HTML code or plain text
 *
 * @return string A formatted HTML view of a given IP address
 */
function crb_admin_ip_cell( $ip, $ip_link = '', $html = '' ) {
	static $cache = array();

	if ( isset( $cache[ $ip ] ) ) {
		return $cache[ $ip ];
	}

	$acl = cerber_acl_check( $ip, '', 0, $row );

	$tip = '';

	if ( ! empty( $row ) ) {
		$tip = $row->comments;
	}
	else {
		if ( $acl == 'W' ) {
			$tip = __( 'White IP Access List', 'wp-cerber' );
		}
        elseif ( $acl == 'B' ) {
			$tip = __( 'Black IP Access List', 'wp-cerber' );
		}
	}

	if ( $b = cerber_block_check( $ip ) ) {
		$block = ' crb_color_blocked ';
		$tip = ( $tip ) ? ' / ' . $b->reason : $b->reason;
	}
	else {
		$block = '';
	}

	if ( $ip_link ) {
		$ip = '<a href="' . crb_escape_url( $ip_link ) . '">' . $ip . '</a>';
	}

	$cache[ $ip ] = '<div class="crb_css_table"><div><span class="crb_act_icon crb_ip_acl' . $acl . ' ' . $block . '" title="' . crb_attr_escape( $tip ) . '"></span></div><div>' . $ip . $html . '</div></div>';

	return $cache[ $ip ];
}

/*
 * Additional information about IP address
 */
function cerber_ip_extra_view( $ip, $context = 'activity' ) {
	if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}

	$html = crb_generate_ip_extra_view( $ip, $context, true );
	$class = 'crb-extra-info';
	$ajax = '';

	if ( ! $html ) {
		$ajax = ' data-ajax_route="ip_extra_info" data-ip="' . $ip . '"';
		$class .= ' crb_async_content';
		$html = UIS_LOADER_HTML;
	}

	return '<div id="crb-extra-ip-info" class="' . $class . '" ' . $ajax . '>' . $html . '</div>';
}

/**
 * @param array $request
 * @param array $ref_params
 *
 * @return array
 */
function cerber_ip_extra_view_ajax( $request, $ref_params ) {
	$ip = filter_var( $request['ip'], FILTER_VALIDATE_IP );
	$tab = $ref_params['tab'] ?? '';

	return crb_generate_ip_extra_view( $ip, $tab );
}

function crb_generate_ip_extra_view( $ip, $context = 'activity', $cache_only = false ) {

	if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}

	$ip_navs = '';

	if ( $context == 'activity' ) {
		$ip_navs .= cerber_traffic_link( array( 'filter_ip' => $ip ) );
	}
	else {
		$ip_navs .= ' <a class="crb-button-tiny" href="' . cerber_admin_link( 'activity', array( 'filter_ip' => $ip ) ) . '">' . __( 'Check for activities', 'wp-cerber' ) . '</a>';
	}

	$acl = cerber_acl_check( $ip, '', 0, $row );

	$comments = ( $row ) ? crb_attr_escape( $row->comments ) : '';

	$ip_status = '';

	if ( $acl == 'W' ) {
		$ip_status .= '<span title="' . $comments . '" class="crb_color_green crb_ip_info_label">' . __( 'White IP Access List', 'wp-cerber' ) . '</span>';
	}
    elseif ( $acl == 'B' ) {
		$ip_status .= '<span title="' . $comments . '" class="crb_color_black crb_ip_info_label">' . __( 'Black IP Access List', 'wp-cerber' ) . '</span>';
	}

	$key_cache = '_cache_ipx_' . $ip . $context . (string) $acl;

	if ( $block = cerber_block_check( $ip ) ) {
		$ip_status .= '<span class="crb_color_blocked crb_ip_info_label">' . __( 'Locked out', 'wp-cerber' ) . '</span><span class="crb-log-status crb-status-' . $block->reason_id . '">' . $block->reason . '</span>';
		$key_cache .= '_blocked';
	}

	if ( $cache = cerber_get_set( $key_cache, null, false, true ) ) {
		return $cache;
	}
    elseif ( $cache_only ) {
        return '';
	}

	$rdap_info = '';
	$country = '';
	$abuse = '';
	$network = '';
	$network_info = '';
	$network_navs = '';
	$to_cache = true;

	if ( crb_get_settings( 'ip_extra' ) ) {

		$ip_rdap = CRB_RDAP_Client::get_parsed_ip_info( $ip );

		if ( crb_is_wp_error( $ip_rdap ) ) {
			$rdap_info = $ip_rdap->get_error_message();
			$to_cache = false;
		}
		else {

			$rdap_info = CRB_RDAP_Client::render_html_view( $ip_rdap );

			if ( $country = $ip_rdap->country ) {
				$country = crb_get_flag_html( $country, '<b>' . crb_get_country_name( $country ) . ' (' . $country . ')</b>' );
			}

			if ( $network = $ip_rdap->cidr ) {
				$range = cerber_any2range( $network );
				$network_info = __( 'Network:', 'wp-cerber' ) . ' ' . $network;
				$network_navs = '<a class="crb-button-tiny" href="' . cerber_admin_link( 'activity', array( 'filter_ip' => $range['range'] ) ) . '">' . __( 'Check for activities', 'wp-cerber' ) . '</a> ' . cerber_traffic_link( array( 'filter_ip' => $range['range'] ) );
			}
		}
	}

	$form = '';

	if ( ! cerber_is_myip( $ip ) && ! cerber_acl_check( $ip ) ) {

		if ( $network ) {
			$net_button = '<button type="submit" value="' . $network . '" name="add_acl" class="button button-secondary cerber-button">';
		}
		else {
			$net_button = '<button disabled="disabled" class="button button-secondary cerber-button">';
		}

		$net_button .= __( 'Add network to the Black List', 'wp-cerber' ) . '</button> ';

		$form = '<form id="add_acl_black" action="" method="post">
                    <input type="hidden" name="cerber_admin_do" value="add2acl">
				    <input type="hidden" name="add_acl_comment" value="">
				    <input type="hidden" name="acl_tag" value="B">
				    <button type="submit" value="' . $ip . '" name="add_acl" class="crb-button-tiny">' . __( 'Add IP to the Black List', 'wp-cerber' ) . '</button>' .
		        '<!-- <p>' . $net_button . '</p>-->' .
		        cerber_nonce_field( 'control' ) .
		        '</form>';
	}

	$ip_info = '<span id="crb-ip-address">' . $ip . '</span><span id="crb-ip-country">' . $country . '</span>';

	$ip_insights = crb_generate_ip_insights( $ip, $context );

	$ret = '
		<div class="crb_extra_info_row">
		
		<div id="crb_the_summary">
		    <div><div style="white-space: nowrap;">' . $ip_info . '</div><div class="crb-nav-row">' . $ip_status . ' ' . $ip_navs . ' ' . $form . '</div></div>
		    <div><div style="white-space: nowrap;">' . $network_info . '</div><div class="crb-nav-row">' . $network_navs . '</div></div>' . $abuse . '
		</div>
		<div class="crb_async_content" data-ajax_route="ip_quick_analytics" data-ip_address="' . $ip . '" data-the_active_tab="' . $context . '">' . $ip_insights . '
		</div>
						
		</div>
		
		<div id="crb-whois-data">' . $rdap_info . '</div>		
		';

	if ( $to_cache ) {
		cerber_update_set( $key_cache, $ret, 0, false, time() + 7200, true );
	}

	return $ret;
}

/**
 * @param string $ip
 * @param string $tab
 * @param false $cache_only
 *
 * @return string|false
 */
function crb_generate_ip_insights( $ip, $tab = 'activity', $cache_only = false ) {

	if ( $tab != 'activity' ) {
		$tab = 'traffic';
	}

	$result = '';
	$links = array();

	$labels = cerber_get_labels();

	if ( $data = crb_q_cache_get( 'SELECT COUNT(activity) as cnt, activity FROM ' . CERBER_LOG_TABLE . ' WHERE ip = "' . $ip . '" GROUP BY activity', CERBER_LOG_TABLE, $cache_only ) ) {
		$links[] = array( array( 'filter_ip' => $ip ), __( 'All' ) );
		foreach ( $data as $item ) {
			$links[] = array( array( 'filter_activity' => $item[1], 'filter_ip' => $ip ), crb_array_get( $labels, $item[1], 'Unknown' ) . ' - ' . $item[0] );
		}
	}

	if ( $links ) {
		$result = '<div id="crb-quick-insights">' . crb_make_nav_links( $links, $tab ) . '</div>';
	}

	if ( ! $result ) {
		if ( $cache_only ) {
			return false;
		}

		$result = __( 'No activity has been logged yet.', 'wp-cerber' );
	}

	return $result;
}

/**
 * Prepare a short report (extra information) about a given user
 *
 * @param int $user_id
 * @param string $context
 *
 * @return string
 */
function cerber_user_extra_view( int $user_id, string $context = 'activity' ): string {
	global $wpdb;

	if ( ! $user = crb_get_userdata( $user_id ) ) {
		return '';
	}

	$ret = '';
	$class = '';
	$user_profile = '';
	$user_summary = array();

	if ( $avatar = (string) get_avatar( $user_id, 96 ) ) {
		$user_profile = '<div style="margin-bottom: 1em;">' . $avatar . '</div>';
	}

	if ( ! is_multisite() && $user->roles ) {
		$roles = array();
		$wp_roles = wp_roles();
		foreach ( $user->roles as $role ) {
			$roles[] = $wp_roles->roles[ $role ]['name'];
		}
		$roles = '<span class="crb_act_role">' . implode( ', ', $roles ) . '</span>';
	}
	else {
		$roles = '';
	}

	if ( ! nexus_is_valid_request() ) {
		$name = '<a href="' . get_edit_user_link( $user_id ) . '" target="_blank">' . $user->display_name . '</a>';
	}
	else {
		$name = $user->display_name;
	}

	$name = '<span class="crb-user-name">' . $name . '</span><p>' . $roles . '</p>';

	$user_profile .= '<div>' . $name . '</div>';

	// Last seen

	$seen = array();
	$seen[0] = $wpdb->get_row( 'SELECT stamp,ip FROM  ' . CERBER_TRAF_TABLE . ' WHERE user_id = ' . $user_id . ' ORDER BY stamp DESC LIMIT 1' );
	$seen[1] = $wpdb->get_row( 'SELECT stamp,ip FROM  ' . CERBER_LOG_TABLE . ' WHERE user_id = ' . $user_id . ' AND ac_by_user = 0 ORDER BY stamp DESC LIMIT 1' );
	$seen[2] = $wpdb->get_row( 'SELECT stamp,ip FROM  ' . CERBER_LOG_TABLE . ' WHERE ac_by_user = ' . $user_id . ' ORDER BY stamp DESC LIMIT 1' );

	$tmp = array();
	foreach ( $seen as $key => $log_row ) {
		$tmp[ $key ] = ( $log_row ) ? $log_row->stamp : 0;
	}

	if ( $max = max( $tmp ) ) {
		$max_keys = array_keys( $tmp, $max );
		$last_log = $seen[ $max_keys[0] ];

		$last_seen = '<span title="' . cerber_date( $last_log->stamp, false ) . '">' . cerber_ago_time( $last_log->stamp ) . '</span>';
		$country = crb_country_html( null, $last_log->ip );
		$user_summary[] = array( __( 'Last seen', 'wp-cerber' ), $last_seen, $country );
	}

	// Last login

	$last_login = crb_get_last_user_login( $user_id );

	if ( ! empty( $last_login ) ) {
		$country = crb_country_html( $last_login['cn'], $last_login['ip'] );
		$user_summary[] = array( __( 'Last login', 'wp-cerber' ), cerber_date( $last_login['ts'], false ), $country );
	}

	// Registered (created)

	if ( $time = strtotime( cerber_db_get_var( "SELECT user_registered FROM  {$wpdb->users} WHERE id = " . $user_id ) ) ) {
		$reg_date = cerber_auto_date( $time, false );
		$reg_event = __( 'Registered', 'wp-cerber' );
		$country = '';

        if ( $reg_meta = get_user_meta( $user_id, '_crb_reg_', true ) ) {
			if ( $reg_meta['IP'] ?? false ) {
				$country = crb_country_html( null, $reg_meta['IP'] );
			}
			if ( $reg_meta['user'] ?? false ) {
				$reg_event = crb_get_activity_label( 1, $user_id, $reg_meta['user'] );
			}
		}

		$user_summary[] = array( $reg_event, $reg_date, $country );
	}

	// Activated - BuddyPress

	if ( $log = CRB_Activity::get_log( array( 200 ), array( 'id' => $user_id ) ) ) {
		$acted = $log[0];
		$activated = cerber_auto_date( $acted->stamp );
		$country = crb_country_html( null, $acted->ip );

		$user_summary[] = array( __( 'Activated', 'wp-cerber' ), $activated, $country );
	}

	$usn = crb_sessions_get_num( $user_id );
	$sess_link = $usn ? '<a href="' . cerber_admin_link( 'sessions', array( 'filter_user' => $user_id ) ) . '">' . $usn . '</a>' : '0';
    $user_summary[] = array(
		'<div style="white-space: nowrap;">' . __( 'Active sessions', 'wp-cerber' ) . '</div>',
	    $sess_link
	);

    // Make a link to switch to other log

	if ( $context == 'activity' ) {
		$link = cerber_traffic_link( array( 'filter_user' => $user_id ) , 2 );
	}
	else {
		$link = ' <a href="' . cerber_admin_link( 'activity', array( 'filter_user' => $user_id ) ) . '">' . __( 'Check for activities', 'wp-cerber' ) . '</a>';
	}

	$user_summary[] = array( $link );

	$summary = '';
	foreach ( $user_summary as $row ) {
		$row = array_pad( $row, 3, '' );
		$summary .= '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>';
	}

	if ( $us_sts = crb_get_user_auth_status( $user ) ) {
		$summary .= '<tr><td colspan="3"><div>' . crb_format_user_status( $us_sts ) . '</div></td></tr>';
	}

	$summary = '<table id="crb-user-summary">' . $summary . '</table>';

	$ins_class = '';
	if ( ! $ins = crb_generate_user_insights( $user_id, $context, true ) ) {
		$ins_class = 'crb_async_content';
	}

	$ret .= '<div class="crb_extra_info_row"><div>' . $user_profile . '</div><div id="crb_the_summary">' . $summary . '</div><div class="' . $ins_class . '" data-ajax_route="user_activity_analytics" data-user_id="' . $user_id . '" data-the_active_tab="' . $context . '">' . $ins . '</div></div>';

	return '<div class="crb-extra-info ' . $class . '" id="crb-user-extra-info">' . $ret . '</div>';

}

// Users -------------------------------------------------------------------------------------

add_filter('users_list_table_query_args' , function ($args) {
	if ( crb_get_settings( 'usersort' ) && empty( $args['orderby'] ) ) {
		$args['orderby'] = 'user_registered';
		$args['order'] = 'desc';
    }
    return $args;
});

/*
	Add custom columns to the Users admin screen
*/
add_filter( 'manage_users_columns', function ( $columns ) {
	return array_merge( $columns,
		array(
			'cbcc' => __( 'Comments', 'wp-cerber' ),
			'cbla' => __( 'Last login', 'wp-cerber' ),
			'cbfl' => '<span title="In last 24 hours">' . __( 'Failed login attempts', 'wp-cerber' ) . '</span>',
			'cbdr' => __( 'Registered', 'wp-cerber' )
		) );
} );
add_filter( 'manage_users_sortable_columns', function ( $sortable_columns ) {
	$sortable_columns['cbdr'] = 'user_registered';

	return $sortable_columns;
} );
/*
	Display custom columns on the Users screen
*/
add_filter( 'manage_users_custom_column', function ( $value, $column, $user_id ) {
	global $wpdb, $user_ID;

    $ret = $value;

    switch ( $column ) {
	    case 'cbcc' :
		case 'cbla' :
		case 'cbfl' :
			$ret = crb_get_ajax_placeholder( $column, $user_id );
			break;
		case 'cbdr' :
			$time = strtotime( cerber_db_get_var( "SELECT user_registered FROM  $wpdb->users WHERE id = " . $user_id ) );
			$ret = cerber_auto_date( $time );
			if ( $reg_data = get_user_meta( $user_id, '_crb_reg_', true ) ) {

                if ( $ip = filter_var( $reg_data['IP'], FILTER_VALIDATE_IP ) ) {
					$act_link = cerber_admin_link( 'activity', array( 'filter_ip' => $ip ) );
					$ret .= '<br /><a href="' . $act_link . '">' . $ip . '</a>';
					if ( $country = crb_country_html( null, $ip ) ) {
						$ret .= '<br />' . $country;
					}
				}

				if ( $uid = absint( $reg_data['user'] ) ) {
					$name = cerber_db_get_var( 'SELECT meta_value FROM ' . $wpdb->usermeta . ' WHERE user_id  = ' . $uid . ' AND meta_key = "nickname"' );
					if ( ! $user_ID ) {
						$user_ID = get_current_user_id();
					}
					if ( $user_ID == $uid ) {
						$name .= ' (' . __( 'You', 'wp-cerber' ) . ')';
					}
					$ret .= '<br />' . $name;
				}
			}
			break;
	}

	return $ret;
}, 10, 3 );

/*
 	Registering WordPress Dashboard widgets
*/
if ( ! is_multisite() ) {
	add_action( 'wp_dashboard_setup', 'cerber_widgets' );
}
else {
	add_action( 'wp_network_dashboard_setup', 'cerber_widgets' );
}
function cerber_widgets() {
	if ( cerber_user_can_manage() ) {
		wp_add_dashboard_widget( 'cerber_quick', __( 'Cerber Quick View', 'wp-cerber' ), 'cerber_quick_w' );
	}
}
/*
	Cerber Quick View widget - WordPress Dashboard
*/
function cerber_quick_w() {

	$dash = cerber_admin_link();
	$act = cerber_admin_link( 'activity' );
	$traf = cerber_admin_link( 'traffic' );
	$scanner = cerber_admin_link( 'scan_main' );
	$acl = cerber_admin_link( 'acl' );
	$sess = cerber_admin_link( 'sessions' );
	$locks = cerber_admin_link( 'lockouts' );

	$s_count = cerber_db_get_var( 'SELECT COUNT(DISTINCT user_id) FROM ' . cerber_get_db_prefix() . CERBER_USS_TABLE );

	$failed = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (' . CRB_EV_LFL . ') AND stamp > ' . ( time() - 24 * 3600 ) );
	$failed_prev = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (' . CRB_EV_LFL . ') AND stamp > ' . ( time() - 48 * 3600 ) . ' AND stamp < ' . ( time() - 24 * 3600 ) );

	$failed_ch = cerber_percent( $failed_prev, $failed );

	$locked = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (10,11) AND stamp > ' . ( time() - 24 * 3600 ) );
	$locked_prev = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (10,11) AND stamp > ' . ( time() - 48 * 3600 ) . ' AND stamp < ' . ( time() - 24 * 3600 ) );

	$locked_ch = cerber_percent( $locked_prev, $locked );

	$lockouts = cerber_blocked_num();

    if ( $last = cerber_db_get_var( 'SELECT MAX(stamp) FROM ' . CERBER_LOG_TABLE . ' WHERE  activity IN (10,11)' ) ) {
		$last = cerber_ago_time( $last );
	}
	else {
		$last = __( 'Never', 'wp-cerber' );
	}

	$w_count = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_ACL_TABLE . ' WHERE tag ="W"' );
	$b_count = cerber_db_get_var( 'SELECT count(ip) FROM ' . CERBER_ACL_TABLE . ' WHERE tag ="B"' );

	if ( cerber_is_citadel() ) {
		$citadel = '<span style="color:#FF0000;">' . __( 'active', 'wp-cerber' ) . '</span> (<a href="' . cerber_admin_link_add( [ 'citadel_do' => 'deactivate' ] ) . '">' . __( 'deactivate', 'wp-cerber' ) . '</a>)';
	}
	else {
		if ( crb_get_settings( 'citadel_on' ) && crb_get_settings( 'ciperiod' ) ) {
			$citadel = __( 'not active', 'wp-cerber' );
		}
		else {
			$citadel = __( 'disabled', 'wp-cerber' );
		}
	}

	echo '<div class="cerber-widget">';

	echo '<table style="width:100%;"><tr><td style="width:50%; vertical-align:top;"><table><tr><td class="bigdig">' . $failed . '</td><td class="per">' . $failed_ch . '</td></tr></table><p>' . __( 'failed attempts', 'wp-cerber' ) . ' ' . __( 'in 24 hours', 'wp-cerber' ) . '<br/>(<a href="' . $act . '&amp;filter_activity=' . CRB_EV_LFL . '">' . __( 'view all', 'wp-cerber' ) . '</a>)</p></td>';
	echo '<td style="width:50%; vertical-align:top;"><table><tr><td class="bigdig">' . $locked . '</td><td class="per">' . $locked_ch . '</td></tr></table><p>' . __( 'lockouts', 'wp-cerber' ) . ' ' . __( 'in 24 hours', 'wp-cerber' ) . '<br/>(<a href="' . $act . '&amp;filter_activity[]=10&filter_activity[]=11">' . __( 'view all', 'wp-cerber' ) . '</a>)</p></td></tr></table>';

	echo '<table id="quick-info"><tr><td>' . __( 'Lockouts at the moment', 'wp-cerber' ) . '</td><td><b><a href="' . $locks . '">' . $lockouts . '</a></b></td></tr>';
	echo '<tr><td>' . __( 'Last lockout', 'wp-cerber' ) . '</td><td>' . $last . '</td></tr>';

	echo '<tr class="with-padding"><td>' . __( 'Logged-in users', 'wp-cerber' ) . '</td><td><b><a href="' . $sess . '">' . $s_count . ' ' . _n( 'user', 'users', $s_count, 'wp-cerber' ) . '</a></b></td></tr>';

	echo '<tr class="with-padding"><td>' . __( 'White IP Access List', 'wp-cerber' ) . '</td><td><b><a href="' . $acl . '">' . $w_count . ' ' . _n( 'entry', 'entries', $w_count, 'wp-cerber' ) . '</a></b></td></tr>';
	echo '<tr><td>' . __( 'Black IP Access List', 'wp-cerber' ) . '</td><td><b><a href="' . $acl . '">' . $b_count . ' ' . _n( 'entry', 'entries', $b_count, 'wp-cerber' ) . '</a></b></td></tr>';
	echo '<tr class="with-padding"><td>' . __( 'Citadel mode', 'wp-cerber' ) . '</td><td><b>' . $citadel . '</b></td></tr>';

	$status = ( ! crb_get_settings( 'tienabled' ) ) ? '<span style="color: red;">' . __( 'disabled', 'wp-cerber' ) . '</span>' : __( 'enabled', 'wp-cerber' );
	echo '<tr class="with-padding"><td>' . __( 'Traffic Inspector', 'wp-cerber' ) . '</td><td><b>' . $status . '</b></td></tr>';

	$lab = lab_lab();
	if ( $lab ) {
		$status = ( ! lab_is_cloud_ok() ) ? '<span style="color: red;">' . __( 'no connection', 'wp-cerber' ) . '</span>' : __( 'active', 'wp-cerber' );
		echo '<tr><td>Cloud Protection</td><td><b>' . $status . '</b></td></tr>';
	}

	$s = '';
	$scan = cerber_get_scan();
	if ( ! $scan || ( WEEK_IN_SECONDS < ( time() - $scan['finished'] ) ) ) {
		$s = 'style="color: red;"';
	}

	if ( $scan ) {
		$lms = $scan['mode_h'] . ' ' . cerber_auto_date( $scan['started'] );
	}
	else {
		$lms = __( 'Never', 'wp-cerber' );
	}

	echo '<tr ' . $s . '><td>' . _x( 'Last malware scan', 'Example: Last malware scan: 23 Jan 2018', 'wp-cerber' ) . '</td><td><a href="' . $scanner . '">' . $lms . '</a></td></tr>';

	$link = cerber_admin_link( 'scan_schedule' );
	$quick = ( ! $lab || ! $q = absint( crb_get_settings( 'scan_aquick' ) ) ) ? __( 'Disabled', 'wp-cerber' ) : cerber_get_qs( $q );
	echo '<tr><td>' . __( 'Quick Scan', 'wp-cerber' ) . '</td><td><a href="' . $link . '">' . $quick . '</a></td></tr>';
	$f = ( ! $lab || ! crb_get_settings( 'scan_afull-enabled' ) ) ? __( 'Disabled', 'wp-cerber' ) : crb_get_settings( 'scan_afull' );
	echo '<tr><td>' . __( 'Full Scan', 'wp-cerber' ) . '</td><td><a href="' . $link . '">' . $f . '</a></td></tr>';

	/*
	$dev = crb_get_settings('pbdevice');
	if (!$dev || $dev == 'N') echo '<tr><td style="padding-top:15px;">'.__('Push notifications','wp-cerber').'</td><td style="padding-top:15px;"><b>not configured</b></td></tr>';
	*/
	echo '</table></div>';

	echo '<div class="wilinks">
	<a href="' . $dash . '"><i class="crb-icon crb-icon-bxs-dashboard"></i> ' . __( 'Dashboard', 'wp-cerber' ) . '</a> |
	<a href="' . $act . '"><i class="crb-icon crb-icon-bx-pulse"></i> ' . __( 'Activity', 'wp-cerber' ) . '</a> |
	<a href="' . $traf . '"><i class="crb-icon crb-icon-bx-show"></i> ' . __( 'Traffic', 'wp-cerber' ) . '</a> |
	<a href="' . $scanner . '"><i class="crb-icon crb-icon-bx-radar"></i> ' . __( 'Integrity', 'wp-cerber' ) . '</a>
	</div>';

    if ( cerber_check_new_version() ) {
		echo '<div class="up-cerber">' . __( 'A new version is available', 'wp-cerber' ) . '</div>';
	}
}

/*
	Show Help tab screen
*/
function cerber_show_help() {
	switch ( crb_admin_get_page()){
		case 'cerber-integrity':
			cerber_show_scan_help();
			break;
		case 'cerber-nexus':
			cerber_show_nexus_help();
			break;
		case 'cerber-recaptcha':
			cerber_show_anti_help();
			break;
		default:
			cerber_show_general_help();
	}
}

function cerber_show_nexus_help() {

	?>
    <div id="crb-help">
        <table id="admin-help">
            <tr>
                <td>

                    <div>
                        <h2>How remote management works</h2>

                        <p>Our technology enables you to manage WP Cerber plugins, monitor activity, and upgrade plugins on multiple WordPress powered websites from a main Cerber.Hub website which is also called a main website.</p>

                        <p>To activate this technology, you need to enable a Cerber.Hub main mode on the main website and a managed mode on each website you want to connect to the main website.</p>

                        <p>Read more: <a href="https://wpcerber.com/manage-multiple-websites/" target="_blank">
                                Manage multiple WP Cerber instances from one dashboard</a></p>

                    </div>

                    <div>
                        <h2>A safety note</h2>

                        <p>All access tokens are stored in the databases of the main and managed websites in unencrypted form (plaintext). Store a backup copy of all websites in a safe and trusted place.</p>
                    </div>

                    <div>
                        <h2>Troubleshooting</h2>
                        <p>
                            If you’re unable to get it working, that may be caused by a number of reasons. Enable the diagnostic log on the main website and on the managed one to obtain more information. You can view the diagnostic log on the Tools admin page. Here is a list of the most common causes of issues on the managed website.
                        </p>

                        <ul>
                            <li>A security plugin on the managed website is interfering with the WP Cerber plugin</li>
                            <li>A security directive in the .htaccess file on the managed website is blocking incoming requests as suspicious</li>
                            <li>A firewall or a proxy service (like Cloudflare) is blocking (filtering out) incoming requests to the managed website</li>
                            <li>The IP address of the main website is locked out or in the Black Access List on the managed website</li>
                            <li>The managed mode on the remote website has been re-enabled making the security token saved on the main website invalid</li>
                            <li>The IP address of the main website does not match the one set in the access settings on the managed website</li>
                        </ul>

                    </div>

                </td>
                <td>

                    <div>
                        <h2>Getting started</h2>

                        <p>Start with activating main Cerber.Hub website. Go to the Cerber.Hub admin page and enable main Cerber.Hub mode. Once you’ve done this you can connect managed websites by using security tokens generated on managed websites.
                        </p>

                    </div>

                    <div>
                        <h2>Connecting managed websites</h2>

                        <p>To connect a remote website to the main website as a remote managed website, you need to enable the managed mode on that website. Go to the Cerber.Hub admin page and enable the managed mode. During the activation of the managed mode, a unique security access token is generated and saved into the database of the managed website. Keep the token secret.
                        </p>
                        <p>
                            Now, go to your main Cerber.Hub website and click the “Add” button on the “My Websites” admin page. Copy the token and paste it in the “Add a remote website” popup window.
                        </p>


                    </div>

                    <div>
                        <h2>Manage websites remotely</h2>

                        <p>Once you’ve connected all your remote websites to the main website, you can easily switch between them with a single click in the top navigation menu on the admin bar or by clicking the name of a remote website on the “My Websites” page. Once you’ve switched to a remote managed website, use the plugin menu and admin links the way like you do this normally. To switch back to the main website, click a X icon on the admin bar.
                        </p>
                        <p>
                            Note: when you’re managing remote website, the color of the admin bar is blue and the left admin menu on the main website is dimmed.
                        </p>
                    </div>

                </td>
            </tr>
        </table>
    </div>
	<?php

}

function cerber_show_scan_help() {

	?>
    <div id="crb-help">
        <table id="admin-help">
            <tr>
                <td>

                    <div>
                        <h2>Using the malware scanner</h2>

                        <p>To start scanning, click either the Start Quick Scan button or the Start Full Scan button. Do
                            not close the browser window while the scan is in progress. You may just open a new browser
                            tab to do something else on the website. Once the scan is finished you can close the window,
                            the results are stored in the DB until the next scan.</p>

                        <p>Depending on server performance and the number of files, the Quick scan may take about 3-5
                            minutes and the Full scan can take about ten minutes or less.</p>

                        <p>During the scan, the plugin verifies plugins, themes, and WordPress by trying to retrieve
                            checksum data from wordpress.org. If the integrity data is not available, you can upload an
                            appropriate source ZIP archive for a plugin or a theme. The plugin will use it to detect
                            changes in files. You need to do it once, after the first scan.</p>

                        <p>Read more: <a href="https://wpcerber.com/malware-scanner-settings/" target="_blank">Cerber
                                Security Scanner Settings</a></p>

                    </div>

                    <div>
                        <h2>Interpreting scan results</h2>

                        <p>The scanner shows you a list of issues and possible actions you can take. If the integrity of
                            an object has been verified, you see a green mark Verified. If you see the “Integrity data
                            not found” message, you need to upload a reference ZIP archive by clicking “Resolve issue”.
                            For all other issues, click on an appropriate issue link. To view the content of a file,
                            click on its name.</p>
                    </div>

                    <div>
                        <h2>Troubleshooting</h2>

                        <p>If the scanner window stops responding or updating, it usually means the process of scanning on the server is hung. This might happen due to a number of reasons but typically this happens due to a misconfigured server or it’s caused by some hosting limitations. Do the following:</p>

                        <ul>
                            <li>Open the browser console (use F12 key on PC or Cmd + Option + J on Mac) and check it for CERBER ERROR messages</li>
                            <li>Try to disable scanning the session directory or the temp directory (or both) in the scanner settings</li>
                            <li>Enable diagnostic logging in the scanner settings and check the log after scanning</li>
                        </ul>

                        <p>Note: The scanner requires the cURL library to be enabled for PHP scripts. Usually, it's enabled by
                            default.</p>

                        <p>Read more: <a href="https://wpcerber.com/wordpress-security-scanner/" target="_blank">Malware
                                Scanner & Integrity Checker</a></p>

                    </div>

                    <div>
                        <h2>What's the Quick Scan?</h2>

                        <p>During the Quick Scan, the scanner verifies the integrity and inspects the code of all files
                            with executable extensions only.</p>

                        <h2>What's the Full Scan?</h2>

                        <p>During the Full Scan, the scanner verifies the integrity and inspects the content of all
                            files on the website. All media files are scanned for malicious payload.</p>

                        <p>Read more: <a href="https://wpcerber.com/wordpress-security-scanner-scan-malware-detect/"
                                         target="_blank">What Cerber Security Scanner scans and detects</a>
                    </div>

                </td>
                <td>

                    <div>
                        <h2>Configuring scheduled scans</h2>

                        <p>In the Automated recurring scan schedule section you set up your schedule. Select the desired
                            frequency of the Quick Scan and specify the time of the Full Scan. By default, all automated
                            recurring scans are turned off.
                        </p>

                        <p>The Scan results reporting section is about reporting. Here you can easily and flexibly
                            configure conditions for generating and sending reports.
                        </p>

                        <p>The email report will only include issues that match conditions in the Report an issue if any
                            of the following is true filter. So this setting works as a filter for issues you want to
                            get in a email report. The report will only be sent if there are issues to report and the
                            following condition is true.</p>

                        <p>The second condition is configured with Send email report. The report will be sent if a
                            selected condition is true. The last option is the most restrictive.</p>

                        <p>Read more: <a href="https://wpcerber.com/automated-recurring-malware-scans/" target="_blank">Automated
                                recurring scans and email reporting</a></p>

                    </div>

                    <div>
                        <h2>Automatic cleanup of malware</h2>

                        <p>The plugin can automatically delete malicious and suspicious files. Automatic removal
                            policies are enforced at the end of every scheduled scan based on its results. The list of
                            files to be deleted depends on the scanner settings. By default automatic removal is
                            disabled. It's advised to enable it at least for unattended files and files in the media
                            uploads folder for files with the High severity risk. The plugin deletes only files that
                            have malicious or suspicious code payload. All detected malicious and suspicious files are
                            moved to the quarantine.
                        </p>

                        Read more: <a href="https://wpcerber.com/automatic-malware-removal-wordpress/" target="_blank">Automatic cleanup of malware and suspicious files</a>

                    </div>

                    <div>
                        <h2>Deleting files</h2>

                        <p>Usually, you can delete any suspicious or malicious file if it has a checkbox in its row in
                            the leftmost cell. Before deleting a file, click the issue link in its row to see an
                            explanation. When you delete a file WP Cerber moves it to a quarantine folder.</p>
                    </div>

                    <div>
                        <h2>Restoring deleted files</h2>

                        <p>If you delete an important file by chance, you can restore the file from the quarantine. To
                            restore one or more files from within the WordPress dashboard, click the Quarantine tab.
                            Find the filename in the File column and click Restore in the Action column. The file will
                            be restored to its original location.</p>

                        <p>To restore a file manually, you need to use a file manager in your hosting control panel. All
                            deleted files are stored in a special quarantine folder. The location of the folder is shown
                            on the Tools / Diagnostic admin page. The original name and location of a deleted file are
                            saved in a .restore file. It’s a text file. Open it in a browser or a file viewer, find the
                            filename you need to restore in a list of deleted files and copy the file back to its
                            location by using the original name and location of the file.
                        </p>

                    </div>

                </td>
            </tr>
        </table>
    </div>
	<?php

}

function cerber_show_anti_help() {

	?>
    <div id="crb-help">
        <table id="admin-help">
            <tr>
                <td>
	                <?php

	                cerber_help();

	                ?>

                </td>
                <td>
                    <h3>Setting up anti-spam protection</h3>

                    <p>
                        The Cerber anti-spam and bot detection engine is capable to protect virtually any form on a website. It’s a great alternative to reCAPTCHA.
                        Tested with Caldera Forms, Gravity Forms, Contact Form 7, Ninja Forms, Formidable Forms, Fast Secure Contact Form, Contact Form by WPForms and WooCommerce forms.
                    </p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/how-to-stop-spam-user-registrations-wordpress/">How to stop spam user registrations on your WordPress</a></p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/antispam-for-wordpress-contact-forms/">How to stop spam form submissions on your WordPress</a></p>

                    <h3>Configuring exceptions for the anti-spam engine</h3>

                    <p>
                        Usually, you need to specify an exception if you use a plugin or some technology that communicates with your website by submitting forms or sending POST requests programmatically. In this case, Cerber can block these legitimate requests because it recognizes them as generated by bots. This may lead to multiple false positives which you can see on the Activity tab. These entries are marked as <b>Spam form submission denied</b>.
                    </p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a href="https://wpcerber.com/antispam-exception-for-specific-http-request/" target="_blank">Configuring exceptions for the anti-spam engine</a></p>

                    <h3>How to set up reCAPTCHA</h3>

                    <p>

                        Before you can start using reCAPTCHA on the website, you have to obtain a Site key and a Secret key on the Google website. To get the keys you have to have Google account.

                        Register your website and get both keys here: <a href="" target="_blank" rel="noopener noreferrer">https://www.google.com/recaptcha/admin</a>

                        Note: If you are going to use an invisible version, you must get and use Site key and a Secret key for the invisible version only.

                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/how-to-setup-recaptcha/">How to set up reCAPTCHA in details</a></p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/why-recaptcha-does-not-protect-wordpress/">Why does reCAPTCHA not protect WordPress against bots and brute-force attacks?</a></p>
                    </p>

                </td>
            </tr>
        </table>
    </div>
	<?php

}

function cerber_show_general_help() {

	?>
	<div id="crb-help">
        <table id="admin-help">
            <tr><td>

                    <?php

                    cerber_help();

                    ?>


                    <h3>Troubleshooting</h3>

                    <p><a href="https://wpcerber.com/antispam-exception-for-specific-http-request/" target="_blank">Configuring exceptions for the anti-spam engine</a></p>

                    <p><a href="https://wpcerber.com/wordpress-probing-for-vulnerable-php-code/" target="_blank">I’m getting "Probing for vulnerable code"</a></p>

                    <p><a href="https://wpcerber.com/firewall-http-requests-are-being-blocked/" target="_blank">Some legitimate HTTP requests are being blocked</a></p>

                    <p><a href="https://wpcerber.com/php-warning-cannot-modify-header-information/" target="_blank">PHP Warning: Cannot modify header information – headers already sent in</a></p>

                    <h3>Traffic Inspector</h3>

                    <p>Traffic Inspector is a set of specialized request inspection algorithms that acts as an additional protection layer (firewall) for your WordPress</p>

                    <p>
                        <span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank"
                                                                                               href="https://wpcerber.com/traffic-inspector-in-a-nutshell/">Traffic
                            Inspector in a nutshell</a>
                    </p>

                    <h3>What's new in this version of the plugin?</h3>

                    <p><a href="https://wpcerber.com/?plugin_version=<?php echo CERBER_VER; ?>" target="_blank">The release note</a></p>

                    <h3>Changelog</h3>

                    <p><a href="<?php echo cerber_admin_link( 'change-log' ); ?>">View the WP Cerber changelog</a></p>

                    <h3>Bug Bounty Program</h3>

                    <p>We are deeply committed to maintaining a secure and trustworthy approach to website protection. That is why our priority is to develop secure software solutions and that is why the WP Cerber bug bounty program came into existence.</p>

                    <p><a href="https://wpcerber.com/bug-bounty-program/" target="_blank">Know more</a></p>

                </td>
                <td>
                    <h3>Online Documentation</h3>

                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/toc/">Plugin documentation on wpcerber.com</a></p>
                    <p><span class="dashicons dashicons-before dashicons-shield-alt"></span> <a target="_blank" href="https://wpcerber.com/how-to-protect-wordpress-checklist/">How to protect WordPress effectively: a must-do list</a></p>

                    <h3>What is IP address of your computer?</h3>

                    <p>To find out your current IP address go to this page: <a target="_blank" href="https://wpcerber.com/what-is-my-ip/">What is my IP</a>. If you see a different IP address on the Activity tab for your login or logout events, try to enable <b><?php _e('My site is behind a reverse proxy','wp-cerber'); ?></b>.</p>
                    <p>
                        <span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/wordpress-ip-address-detection/">Solving problem with incorrect IP address detection</a>
                    </p>


                    <h3>Setting up anti-spam protection</h3>

                    <p>
                        The WP Cerber anti-spam and bot detection engine can effectively protect virtually any form on your website.
                        It’s a great alternative to reCAPTCHA.
                    </p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/how-to-stop-spam-user-registrations-wordpress/">How to stop spam user registrations on your WordPress</a></p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/antispam-for-wordpress-contact-forms/">Spam protection for contact forms</a></p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a href="https://wpcerber.com/antispam-exception-for-specific-http-request/" target="_blank">Configuring exceptions for the anti-spam engine</a></p>


                    <h3>Mobile and browser notifications with Pushbullet</h3>

                    <p>
                        WP Cerber enables you to effortlessly get desktop and mobile notifications on critical events on your website. In a desktop browser, you will receive popup messages even if you are logged out of your WordPress. To begin receiving notifications, you need to install the free Pushbullet mobile application on your mobile device or a free browser extension.
                    </p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span>
                        <a target="_blank" href="https://wpcerber.com/wordpress-mobile-and-browser-notifications-pushbullet/">A three steps instruction how to set up push notifications</a>
                    </p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span>
                        <a target="_blank" href="https://wpcerber.com/wordpress-notifications-made-easy/">How to get alerts for specific activity on your website</a>
                    </p>

                    <h3>WordPress security explained</h3>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/why-recaptcha-does-not-protect-wordpress/">Why reCAPTCHA does not protect WordPress against bots and brute-force attacks?</a></p>
                    <p><span class="dashicons dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/why-we-need-to-use-custom-login-url/">Why you need to use Custom login URL for your WordPress</a></p>
                    <p><span class="dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/why-its-important-to-restrict-access-to-rest-api/">Why it is important to restrict access to WordPress REST API</a></p>
                    <p><span class="dashicons-before dashicons-book-alt"></span> <a target="_blank" href="https://wpcerber.com/mitigating-brute-force-dos-and-ddos-attacks/">Brute-force, DoS, and DDoS attacks - what is the difference?</a></p>
                </td>
            </tr>
        </table>

		<h3>What is Cerber Lab?</h3>

		<p>
			Cerber Laboratory is a forensic team at Cerber Tech Inc. The team studies and analyzes
			patterns of hacker and botnet attacks, malware, vulnerabilities in major plugins and how they are
			exploitable on WordPress powered websites.
		</p>
			<p><span class="dashicons-before dashicons-info" style="vertical-align: middle;"></span>
			<a href="https://wpcerber.com/cerber-laboratory/">Know more</a>
			</p>

		<h3>Do you have an idea for a cool new feature that you would love to see in WP Cerber?</h3>

		<p>
			Feel free to submit your ideas here: <a href="https://wpcerber.com/new-feature-request/">New Feature
				Request</a>.
		</p>

		<h3>Are you ready to translate WP Cerber into your language?</h3>

		<p>We would appreciate that! Please, <a href="https://wpcerber.com/contact/">notify us</a></p>

	</div>
	<?php
}

function cerber_help() {

	if ( lab_lab() ) {
		$support = '<p style="margin: 2em 0 5em 0;">Submit a support ticket on our Help Desk: <a href="https://my.wpcerber.com/">https://my.wpcerber.com</a></p>';
	}
	else {
		$support = '<p>Support for the free version is provided on the <a target="_blank" href="https://talk.wpcerber.com">the community forum only</a>, though, please note, that it is free support hence it is
                        not always possible to answer all questions on a timely manner, although we do try.</p>';
	}

	?>

    <img style="width: 120px; float: left; margin-right: 30px; margin-bottom: 30px;" src="<?php CRB_Globals::assets_url( 'wrench.png', true ); ?>"/>

    <h3 style="font-size: 150%;">How to configure the plugin</h3>

    <p style="font-size: 120%;">To get the most out of WP Cerber Security, you need to configure the plugin properly</p>

    <p style="font-size: 120%;"><a target="_blank" href="https://wpcerber.com/getting-started/">Getting Started Guide</a></p>

    <p style="clear: both;"></p>

    <h3>Do you have a question or need help?</h3>

	<?php echo $support; ?>

    <p><span class="dashicons dashicons-before dashicons-format-chat"></span> <a target="_blank" href="https://talk.wpcerber.com">Get answers on the community support forum</a></p>

    <form style="margin-top: 2em;" action="https://wpcerber.com" target="_blank">
        <h3>Search plugin documentation on wpcerber.com</h3>
        <input type="text" style="width: 80%;" name="s" placeholder="Enter a word or phrase to search"><input type="submit" value="Search" class="button button-primary">
    </form>

	<?php
}

/**
 *
 * WP Cerber Dashboard
 *
 *
 */
function cerber_show_dashboard() {

	cerber_widgets_init();

	$all_widgets = CRB_Widgets::get_titles( true );

	echo '<div id="crb-dashboard-container">';

	foreach ( $all_widgets as $widget_id => $titles ) {

		$title = $titles[0];
		$subtitle = $titles[1] ? '<span class="crb-widget-subtitle"> &nbsp;&#x2500;&#x2500;&nbsp; ' . $titles[1] . '</span>' : '';

		$widget = CRB_Widgets::render_widget( $widget_id, false );

		$show_control = true;

		if ( crb_is_wp_error( $widget ) ) {
			$body = $widget->get_error_message();
		}
        elseif ( is_array( $widget ) ) {
			list( $body, $show_control ) = $widget;
		}
		else {
			$body = $widget;
		}

		if ( ! $body ) {
			continue;
		}

		$controls = CRB_Widgets::get_controls( $widget_id );

		$visibility = $show_control ? '' : ' style="visibility: hidden;" ';

		$heading = ( ! CRB_Widgets::hide_header( $widget_id ) && $title ) ? '<div class="crb-widget-heading"><div class="crb-widget-title-cell"><h2 class="crb-widget-title crb-dash-padding">' . $title . '</h2>' . $subtitle . '</div><div class="crb-widget-controls" ' . $visibility . '>' . $controls . '</div></div>' : '';

		echo '<div class="crb-dashboard-element" data-widget_id="' . $widget_id . '">' . $heading . '<div class="crb-element-content" data-from_cache="' . ( CRB_Widgets::$from_cache ? 1 : 0 ) . '">' . $body . '</div></div>';
	}

	echo '</div>';

	echo '<div style="text-align: right">' . crb_admin_screen_dialog() . '</div>';
}

/**
 * Initializing and registering dashboard widgets code.
 *
 * @return void
 *
 * @since 9.6.4.2
 */
function cerber_widgets_init() {

	CRB_Widgets::register( 'wgt_ac_kpi',
		__( 'Security Status Monitor', 'wp-cerber' ),
		'',
		'',
		function () {
			$kpi_list = cerber_calculate_kpi( time() - 24 * 3600, time() );

			$kpi_show = '';
			foreach ( $kpi_list as $kpi ) {
				$kpi_show .= '<td>' . $kpi[1] . '</td><td><span style="z-index: 10;">' . $kpi[0] . '</span></td>';
			}

			$kpi_show = '<table id="crb-kpi" class="" title="In the last 24 hours"><tr>' . $kpi_show . '</tr></table>';

			// TODO: add link "send daily report to my email"
			// $kpi_show .= '<p style="text-align: right; margin: 0;">' . __( 'in the last 24 hours', 'wp-cerber' ) . '</p>';
			return $kpi_show;

		},
        array( CRB_ACT_HASH ),
        true );

	CRB_Widgets::register( 'wgt_ac_breakdown',
		__( 'Activity Breakdown', 'wp-cerber' ),
		__( 'most frequent incidents and events in the last 24 hours', 'wp-cerber' ),
		'',
		function ( $ajax = false ) {

			return cerber_activity_summary();

			/* AJAX variant

			if ( $ajax ) {
				return cerber_activity_summary();
			}

			if ( cerber_activity_summary( true ) ) {
				return array( '', false, true );
			}

			return ''; */
		},
		array( CRB_ACT_HASH ) );

	CRB_Widgets::register( 'wgt_mali_top',
	__( 'Top Offending IP Addresses', 'wp-cerber' ),
	__( 'most active in the last 24 hours', 'wp-cerber' ),
	'',
	function ( $ajax = false ) {

		return crb_top_malicious_ip_report();
	},
	array( CRB_ACT_HASH ) );

	CRB_Widgets::register( 'wgt_new_users',
		__( 'New Users', 'wp-cerber' ),
		__( 'most recent user registrations', 'wp-cerber' ),
		crb_make_nav_links( array( array( array( 'filter_activity' => array( 1, 2 ) ), __( 'View all', 'wp-cerber' ) ) ), 'activity' ),
		function () {

			$logged = cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (1,2) LIMIT 1' );

			if ( ! $logged ) {
				return array( 'no_data' => __( 'No activity has been logged yet.', 'wp-cerber' ) );
			}

			return cerber_show_activity( array(
				'filter_activity' => array( 1, 2 ),
				'per_page'        => 10,
				'no_navi'         => true,
				'no_export'       => true,
				'no_details'      => true,
				'date'            => 'ago',
				'hide_columns'    => array( 'crb_username_col' ),
				'context'         => 'dashboard',
			), false );

		},
		array( CRB_ACT_HASH, 60 ) );

    CRB_Widgets::register( 'wgt_login_issues',
		__( 'Login Issues', 'wp-cerber' ),
		__( 'most recent authentication issues and requests', 'wp-cerber' ),
		crb_make_nav_links( array( array( array( 'filter_set' => '2' ), __( 'View all', 'wp-cerber' ) ) ), 'activity' ),
		function () {

			$in = crb_get_activity_set('login_issues', true);
			$logged = cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (' . $in . ') LIMIT 1' );

			if ( ! $logged ) {
				return array( 'no_data' => __( 'No activity has been logged yet.', 'wp-cerber' ) );
			}

			return cerber_show_activity( array(
				'filter_set'  => 2,
				'per_page'     => 10,
				'no_navi'      => true,
				'no_export'    => true,
				'no_details'   => true,
				'date'         => 'ago',
				'hide_columns' => array( 'crb_user_col' ),
				'context'      => 'dashboard',
			), false );

		},
		array( CRB_ACT_HASH, 60 ) );

	CRB_Widgets::register( 'wgt_user_activity',
		__( "Users' Activity", 'wp-cerber' ),
		__( 'most recent user events', 'wp-cerber' ),
		crb_admin_activity_nav_links( 'users' ),
		function () {

			$user_logged = cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' WHERE user_id !=0 LIMIT 1' );

			if ( ! $user_logged ) {
				return array( 'no_data' => __( 'No activity has been logged yet.', 'wp-cerber' ) );
			}

			return cerber_show_activity( array(
				'filter_user'  => '*',
				'per_page'     => 10,
				'no_navi'      => true,
				'no_export'    => true,
				'no_details'   => true,
				'date'         => 'ago',
				'hide_columns' => array( 'crb_username_col' ),
				'context'      => 'dashboard',
			), false );

		},
		array( CRB_ACT_HASH, 60 ) );

	CRB_Widgets::register( 'wgt_mali_activity',
		__( 'Malicious Activity', 'wp-cerber' ),
		__( 'most recent violations of security rules', 'wp-cerber' ),
		crb_admin_activity_nav_links( 'suspicious' ),
		function () {

			$in = crb_get_activity_set( 'suspicious', true );
			$bad_logged = cerber_db_get_var( 'SELECT ip FROM ' . CERBER_LOG_TABLE . ' WHERE activity IN (' . $in . ') LIMIT 1' );

			if ( ! $bad_logged ) {
				return array( 'no_data' => __( 'No activity has been logged yet.', 'wp-cerber' ) );
			}

			return cerber_show_activity( array(
				'filter_set' => 1,
				'per_page'   => 10,
				'no_navi'    => true,
				'no_export'  => true,
				'no_details' => true,
				'date'       => 'ago',
				'context'    => 'dashboard',
			), false );

		},
		array( CRB_ACT_HASH, 60 ) );

	CRB_Widgets::register( 'wgt_blocked_ips',
		__( 'Recently locked out IP addresses', 'wp-cerber' ),
        '',
		crb_make_nav_links( array( array( array(), __( 'View all', 'wp-cerber' ) ) ), 'lockouts' ),
		function () {

			$locked = cerber_blocked_num();

			if ( ! $locked ) {
				return array( 'no_data' => __( 'No lockouts at the moment. The sky is clear.', 'wp-cerber' ) );
			}

			return cerber_show_lockouts( array(
				'per_page' => 10,
				'no_navi'  => true
			), false );

		} );
}

/*
	Admin aside bar
*/
function cerber_show_aside( $tab ) {

	if ( in_array( $tab, array( 'nexus_sites', 'activity', 'lockouts', 'traffic' ) ) ) {
		return;
	}

	$aside = array();

	if ( $tab != 'scan_main' && ! lab_lab() && crb_was_activated( 1 * DAY_IN_SECONDS ) ) {
		/*$aside[] =
            '<a class="crb-button-one" style="background-color: #1DA1F2;" href="https://twitter.com/wpcerber" target="_blank"><span class="dashicons dashicons-twitter"></span> Follow Cerber on Twitter</a>';
		*/

        // <a class="crb-button-one" href="https://wpcerber.com/subscribe-newsletter/" target="_blank"><span class="dashicons dashicons-email-alt"></span> Subscribe to Cerber\'s newsletter</a>
        // <a class="crb-button-one" style="background-color: #3B5998;" href="https://www.facebook.com/wpcerber/" target="_blank"><span class="dashicons dashicons-facebook"></span> Follow Cerber on Facebook</a>
	    //';


		$banners = array(
			array( 'bn4ra.png', 'https://wpcerber.com/pro/' ),
			array( 'bn5ra.png', 'https://wpcerber.com/pro/' ),
			array( 'talk_b1.png', 'https://talk.wpcerber.com' ),
		);

		if ( ! crb_admin_get_tab() ) {
			$n = 2;
		}
		else {
			$d = (int) date( 'z' );
			$n = ( $d & 1 ) ? 1 : 0;
		}

		$banner = $banners[ $n ];

		$banner_image = CRB_Globals::assets_url( $banner[0] );

		$aside[] = '<a href="' . $banner[1] . '" target="_blank"><img src="' . $banner_image . '" class="crb-full-width" /></a>';

	}

	$context_links = '';
	$docs = crb_get_doc_links();

    foreach ( $docs as $doc ) {
		$context_links .= '<p><a href="' . $doc[0] . '" target="_blank">' . $doc[1] . '</a></p>';
	}

	$aside[] = '<div class="crb-box" id="crb-blog"><div class="crb-box-inner"> 
			<h3>Documentation & How To</h3>           
            ' . $context_links . '</div></div>';

	if ( ! lab_lab()
	     && crb_was_activated( 2 * WEEK_IN_SECONDS ) ) {

        $r = array();
		$r[0] = crb_get_review_url( 'tpilot' );
		$r[1] = crb_get_review_url( 'cap' );
		$r[2] = crb_get_review_url( 'tradius' );

        shuffle( $r );

		$aside[] = '<a href="' . $r[0] . '" target="_blank"><img class="crb-full-width" src="' . CRB_Globals::assets_url( 'fb2b.png' ) . '" /></a>';
	}

	echo '<div id="crb-aside">' . implode( ' ', $aside ) . '</div>';
}

/**
 * @return array A set of links to the plugin documentation web pages
 */
function crb_get_doc_links() {
	static $links = array(
		'cerber-integrity' => array(
			array( 'https://wpcerber.com/wordpress-security-scanner/', 'How to use the integrity checker and malware scanner' ),
			array( 'https://wpcerber.com/wordpress-security-scanner-scan-malware-detect/', 'What the scanner scans and detects' ),
			array( 'https://wpcerber.com/malware-scanner-settings/', 'Configuring the scanner settings' ),
			array( 'https://wpcerber.com/troubleshooting-malware-scanner/', 'Troubleshooting malware scanner issues' ),
			array( 'https://wpcerber.com/automatic-malware-removal-wordpress/', 'Automatic cleanup of malware and suspicious files' ),
			array( 'https://wpcerber.com/automated-recurring-malware-scans/', 'Automated recurring scans and email reporting' )
		),
		'*' => array(
			array( 'https://wpcerber.com/automatic-updates-for-wp-cerber/', 'How to enable automatic updates for WP Cerber' ),
			array( 'https://wpcerber.com/troubleshooting-login-issues/', 'Troubleshooting login issues' ),
			array( 'https://wpcerber.com/wordpress-application-passwords-how-to/', 'Managing WordPress application passwords a hassle-free way' ),
			array( 'https://wpcerber.com/how-to-protect-wordpress-checklist/', 'How to protect WordPress effectively: a must-do list' ),
			array( 'https://wpcerber.com/manage-multiple-websites/', 'Managing multiple WP Cerber instances from one dashboard' ),
			array( 'https://wpcerber.com/wordpress/gdpr/', 'Stay in compliance with GDPR' ),
			array( 'https://wpcerber.com/two-factor-authentication-for-wordpress/', 'How to protect user accounts with Two-Factor Authentication' ),
			array( 'https://wpcerber.com/limiting-concurrent-user-sessions-in-wordpress/', 'How to stop your users from sharing their WordPress accounts' ),
			array( 'https://wpcerber.com/wordpress-mobile-and-browser-notifications-pushbullet/', 'Be notified with mobile and browser notifications' ),
			array( 'https://wpcerber.com/wordpress-notifications-made-easy/', 'WordPress notifications made easy' ),
			array( 'https://wpcerber.com/restrict-access-to-wordpress-rest-api/', 'How to restrict access to REST API' ),
            array( 'https://wpcerber.com/automatic-malware-removal-wordpress/', 'Automatic cleanup of malware and suspicious files' ),
			array( 'https://wpcerber.com/automated-recurring-malware-scans/', 'Automated recurring scans and email reporting' ),
		),
	);

	static $get_started = array( 'https://wpcerber.com/getting-started/', '<b>Getting Started Guide</b>' );

	$ret = crb_array_get( $links, crb_admin_get_page() );
	$common = crb_array_get( $links, '*' );

	if ( ! $ret ) {
		array_unshift( $common, $get_started );

		return $common;
	}

	$ret = array_merge( $ret, array_slice( $common, 0, 5 ) );
	$ret[] = $get_started;

	return $ret;
}

/*
	Displaying notices in the dashboard
*/

function cerber_show_admin_notice() {
	global $cerber_shown;
	$cerber_shown = false;

	if ( nexus_get_context() ) {
		return;
	}

	if ( cerber_is_citadel() && cerber_user_can_manage() ) {
		echo '<div class="update-nag" style="display: block; background-color: #fff; border-left: 6px solid #ff0000;"><p>' .
		     __( 'Attention! Citadel mode is now active. Nobody is able to log in.', 'wp-cerber' ) .
		     ' &nbsp; <a href="' . cerber_admin_link_add( [ 'citadel_do' => 'deactivate' ] ) . '">' . __( 'Deactivate', 'wp-cerber' ) . '</a>' .
		     ' | <a href="' . cerber_admin_link( 'activity' ) . '">' . __( 'View Activity', 'wp-cerber' ) . '</a>' .
		     '</p></div>';
	}

	if ( ! nexus_is_valid_request() && ! cerber_is_admin_page( null, 'plugins' ) ) {
		return;
	}

	$all = array();

	if ( $notice = cerber_get_set( 'admin_notice' ) ) {
		if ( is_array( $notice ) ) {
			$notice = '<p>' . implode( '</p><p>', $notice ) . '</p>';
		}
		$all[] = array( $notice, 'crb-admin-warning' ); // red
		cerber_update_set( 'admin_notice', array() );
	}

	if ( $message = cerber_get_set( 'admin_message' ) ) {
		if ( is_array( $message ) ) {
			$message = '<p>' . implode( '</p><p>', $message ) . '</p>';
		}
		$all[] = array( $message, 'updated' ); // green
		cerber_update_set( 'admin_message', array() );
	}

	if ( $all ) {
		$cerber_shown = true;
		foreach ( $all as $notice ) {
			echo '<div id="" class="' . $notice[1] . ' notice is-dismissible crb-admin-message"> 
		' . $notice[0] . '<button type="button" class="notice-dismiss crb-notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		}
	}

	if ( $wide = cerber_get_set( 'cerber_admin_wide', null, false ) ) {
		crb_show_admin_announcement( $wide, false );
		$cerber_shown = true;
		cerber_update_set( 'cerber_admin_wide', '' );
	}

	if ( $cerber_shown || ! cerber_is_admin_page() ) {
		return;
	}

	if ( $msg = get_site_option( 'cerber_admin_info' ) ) {
		crb_show_admin_announcement( $msg );
		$cerber_shown = true;

		return;
	}

	lab_opt_in();
}

/**
 * Check if an alert with a given parameters exists
 *
 * @param array $params
 *
 * @return bool
 *
 * @since 8.9.5.6
 */
function crb_admin_alert_exists( $params = null ) {
	$alerts = cerber_get_site_option( CRB_ALERTZ );

	if ( ! $alerts ) {
		return false;
	}

	if ( ! $params ) {
		$params = crb_get_alert_params();
	}

	$hash = crb_get_alert_id( $params );

    if ( isset( $alerts[ $hash ] )
	     && ! crb_is_alert_expired( $alerts[ $hash ] ) ) {
		return true;
	}

	return false;
}

/**
 * Generates a link/button to display a dialog for creating an alert on the currently displaying Activity page
 *
 * @return string HTML code for using in the Dashboard HTML
 *
 * @since 8.9.6
 */
function crb_admin_alert_dialog( $button = true, $create_txt = null, $delete_txt = null ) {
	$alert_params = crb_get_alert_params();

	// Check if query parameters that are used in alerts are set and not empty.
	// All activities, without any filter are not allowed
	$empty = array_filter( array_values( $alert_params ) );

    if ( empty( $empty ) ) {
		return '';
	}

	$alerts = cerber_get_site_option( CRB_ALERTZ );

	// Limit to the number of subscriptions

	if ( $alerts && count( $alerts ) > 50 ) {
		return '';
	}

	$mode = ( crb_admin_alert_exists( $alert_params ) ) ? 'off' : 'on';

	if ( $mode == 'on' ) {
		$label = $create_txt ?? __( 'Create Alert', 'wp-cerber' );
		$icon = ( $button ) ? 'crb-icon-bx-bell' : '';
	}
	else {
		$label = $delete_txt ?? __( 'Delete Alert', 'wp-cerber' );
		$icon = ( $button ) ? 'crb-icon-bx-bell-off' : '';
		$link = cerber_admin_link_add( array( 'cerber_admin_do' => 'subscribe', 'mode' => 'off' ), true );

		if ( $button ) {
			return '<a class="button button-secondary cerber-button" href="' . $link . '" style="margin-right: 0.5em;"><i class="crb-icon ' . $icon . '"></i> ' . $label . '</a>';
		}

		return '<a href="' . $link . '">' . $label . '</a>';
	}

    // Create the alert dialog

	$em_list = cerber_get_email();
	$mob_name = cerber_pb_get_active();

	if ( lab_lab() ) {

		$atts = array( 'type' => 'checkbox', 'data-validation_group' => 'required_min_one', 'checked' => true );

		$channels = array(
			'title'          => __( 'Channels to send alerts', 'wp-cerber' ),
			'visible'        =>
				array(
					'al_send_emails' => array(
						/* translators: Here %s is the email address(es). */
						'label' => sprintf( __( 'Send email alerts to %s', 'wp-cerber' ), implode( ', ', $em_list ) ),
						'value' => '1',
						'atts'  => $atts,
					),
					'al_send_me'     => array(
						'label' => sprintf( __( 'Send email alerts to my email', 'wp-cerber' ), $mob_name ),
						'value' => get_current_user_id(),
						'atts'  => $atts,
					),
				),
			'error_messages' => array( 'required_min_one' => __( 'Please select at least one channel', 'wp-cerber' ) )
		);

		if ( $mob_name ) {
			$channels['visible']['al_send_pushbullet'] = array(
				/* translators: Here %s is the name of a mobile device. */
				'label' => sprintf( __( 'Send mobile alerts to %s', 'wp-cerber' ), $mob_name ),
				'value' => '1',
				'atts'  => $atts,
			);
		}

	}
	else {

		if ( $mob_name ) {
			/* translators: Here %s is the name of a mobile device. */
			$mobile = sprintf( __( 'Mobile alerts will be sent to %s', 'wp-cerber' ), $mob_name );
		}
		else {
			$mobile = __( 'Mobile alerts are not configured', 'wp-cerber' );
		}

		$recipients = ( 1 < count( $em_list ) ) ? __( 'Email alerts will be sent to these emails:', 'wp-cerber' ) : __( 'Email alerts will be sent to this email:', 'wp-cerber' );
		$recipients .= ' ' . implode( ', ', $em_list );
		$recipients = '<p>' . $recipients . '</p><p>' . $mobile . '</p>';
		$recipients .= '<p><a href="https://wpcerber.com/wordpress-notifications-made-easy/" target="_blank">' . __( 'Documentation', 'wp-cerber' ) . '</a></p>';

		$channels = array(
			'html' => $recipients,
		);
	}


    // Extract values of the alert fields

	$alert_fields = array_intersect_key( $alert_params, $_GET );

	$limits = array(
		'title'   => __( 'Optional alert limits', 'wp-cerber' ),
		'hidden'  => array_merge( $alert_fields, array(
			'cerber_admin_do' => 'subscribe',
			'mode'            => $mode
		) ),
		'visible' => array(
			'al_limit'       => array(
				'label' => __( 'Maximum number of alerts to send', 'wp-cerber' ),
				'value' => '',
				'atts'  => array(
					'type'        => 'text',
					'pattern'     => '\d+',
					'placeholder' => __( 'No limit', 'wp-cerber' ),
				),
			),
			'al_expires'     => array(
				'label' => __( 'Do not send alerts after this date', 'wp-cerber' ),
				'value' => '',
				'atts'  => array( 'type' => 'date', 'min' => date( 'Y-m-d' ) ),
			),
			'al_ignore_rate' => array(
				'label' => __( 'Ignore global rate limits', 'wp-cerber' ),
				'value' => '1',
				'atts'  => array( 'type' => 'checkbox' ),
			),
		),
	);

	return crb_create_popup_form( $limits, $channels, [ 'icon' => $icon, 'label' => $label ] );
}

/**
 * Managing the list of admin alerts
 *
 * @param string $mode Add or delete an alert
 * @param string $hash If specified, an alert with a given hash will be removed
 */
function crb_admin_alerts_do( $mode = 'on', $hash = null ) {
	if ( $hash ) {
		$mode = 'off';
	}
	else {
		$params = crb_get_alert_params();
		$values = array_values( $params );
		$hash = crb_get_alert_id( $params );
	}

	$alerts = get_site_option( CRB_ALERTZ );

	if ( ! $alerts || ! is_array( $alerts ) ) {
		$alerts = array();
	}

	if ( $mode == 'on' ) {
		if ( crb_admin_alert_exists() ) {
			cerber_admin_notice( 'An alert with given parameters exists.' );

			return;
		}
		$alerts[ $hash ] = $values;
		$msg = __( 'The alert has been created', 'wp-cerber' );
	}
	else {
		if ( ! isset( $alerts[ $hash ] ) ) {
			cerber_admin_notice( 'No alert with the given ID found' );

			return;
		}
		unset( $alerts[ $hash ] );
		$msg = __( 'The alert has been deleted', 'wp-cerber' );
	}

	if ( update_site_option( CRB_ALERTZ, $alerts ) ) {
		cerber_admin_message( $msg );
	}
    else {
	    cerber_admin_notice( 'Unable to update alerts!' );
    }
}

/**
 * Deletes an alert using an alert hash from the $_GET parameter
 *
 */
function crb_delete_alert() {
	if ( ( $hash = cerber_get_get( 'unsubscribeme', '[a-z\d]+' ) )
	     && cerber_user_can_manage() ) {
		crb_admin_alerts_do( 'off', $hash );

		crb_safe_redirect( remove_query_arg( 'unsubscribeme' ) );
		exit;
	}
}

/**
 * Generates pagination links
 *
 * @param int $total
 * @param int $per_page
 *
 * @return string
 */
function cerber_page_navi( $total, $per_page = 25 ) {

    $max_links = 10;
	$total = max( 0, intval( $total ) );
    $per_page = intval( $per_page );

	if ( $per_page <= 0 ) {
		$per_page = 25;
	}

    $page = cerber_get_pn();
	$base_url = crb_escape_url( remove_query_arg( 'pagen', add_query_arg( crb_get_query_params(), cerber_admin_link() ) ) );
	$last_page = ceil( $total / $per_page );

	if ( $last_page <= 1 ) {
        return '';
	}

	$links = array();
	$start = 1 + $max_links * intval( ( $page - 1 ) / $max_links );
	$end = $start + $max_links - 1;

	if ( $end > $last_page ) {
		$end = $last_page;
	}

	if ( $start > $max_links ) {
		$links[] = '<a href="' . $base_url . '&amp;pagen=' . ( $start - 1 ) . '" class="arrows"><b>&laquo;</b></a>';
	}

	for ( $i = $start; $i <= $end; $i ++ ) {
		if ( $page != $i ) {
			$links[] = '<a href="' . $base_url . '&amp;pagen=' . $i . '" >' . $i . '</a>';
		}
		else {
			$links[] = '<a class="active" style="font-size: 16px;">' . $i . '</a> ';
		}
	}

	if ( $end < $last_page ) {
		$links[] = '<a href="' . $base_url . '&amp;pagen=' . $i . '" class="arrows">&raquo;</a>';  // &#10141;
	}

	return '<table class="cerber-margin" style="margin-top:1em; border-collapse: collapse;"><tr><td><div class="pagination">' . implode( ' ', $links ) . '</div></td><td><span style="margin-left:2em;"><b>' . $total . ' ' . _n( 'entry', 'entries', $total, 'wp-cerber' ) . '</b></span></td></tr></table>';
}

/**
 * @return int
 */
function cerber_get_pn() {
	$q = crb_admin_parse_query( array( 'pagen' ) );

	return ( ! $q->pagen ) ? 1 : absint( $q->pagen );
}

/**
 * Return safe LIMIT clause
 *
 * @param integer $per_page
 *
 * @return string
 */
function cerber_get_sql_limit( $per_page = 25 ) {
	$per_page = crb_absint( $per_page );

	return ' LIMIT ' . ( cerber_get_pn() - 1 ) * $per_page . ',' . $per_page;
}

/*
	Plugins admin page links
*/
add_filter( 'plugin_action_links_' . CERBER_PLUGIN_ID, 'cerber_action_links' );
add_filter( 'network_admin_plugin_action_links_' . CERBER_PLUGIN_ID, 'cerber_action_links' );
function cerber_action_links( $actions ) {
	$links = array();

	$links[] = '<a href="' . cerber_admin_link() . '">' . __( 'Dashboard', 'wp-cerber' ) . '</a>';

	if ( lab_lab() ) {
		$links[] = '<a href="https://my.wpcerber.com" target="_blank">' . __( 'Support', 'wp-cerber' ) . '</a>';
	}
	else {
		$links[] = '<a href="https://talk.wpcerber.com" target="_blank">' . __( 'Support Forum', 'wp-cerber' ) . '</a>';
	}

	return array_merge( $links, $actions );
}

add_filter( 'plugin_row_meta', function ( $plugin_meta, $plugin_file, $plugin_data, $status ) {

    if ( $plugin_file == CERBER_PLUGIN_ID
         || $plugin_file == 'aaa-wp-cerber.php' ) {

	    // Fix broken link pointing to a non-existing wordpress.org repository plugin profile

 	    foreach ( $plugin_meta as &$link ) {
		    if ( strpos( $link, 'class="thickbox open-plugin-details-modal"' ) ) {
			    $ver = esc_attr( str_replace( '.', '-', $plugin_data['Version'] ) );
			    $link = preg_replace( '/^<a href="[^"]+"/u', '<a href="https://wpcerber.com/wp-cerber-security-' . $ver . '/?TB_iframe=true&width=600&height=550"', $link );
		    }
	    }

        // -----------------------------------------

		if ( lab_lab() ) {
			$plugin_meta[] = '<a href="https://my.wpcerber.com" target="_blank">' . __( 'Support', 'wp-cerber' ) . '</a>';
		}
        else {
	        $plugin_meta[] = '<a href="https://talk.wpcerber.com" target="_blank">' . __( 'Support Forum', 'wp-cerber' ) . '</a>';
        }
	}

	return $plugin_meta;
}, 10, 4 );

/*
function add_some_pointers() {
	?>
	<script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

		jQuery( function( $ ) {
			var options = {'content':'<h3>Info</h3><p>Cerber will request WHOIS database for extra information when you click on IP.</p>','position':{'edge':'right','align':'center'}};
			if ( ! options ) return;
			options = $.extend( options, {
				close: function() {
					//to do
				}
			});

			//$("#ip_extra").click(function(){
			//	$(this).pointer( options ).pointer('open');
			//});

			$('#subscribe-me').pointer( options ).pointer('open');

		});
	</script>
	<?php
}
add_action('admin_enqueue_scripts', 'cerber_admin_enqueue');
function cerber_admin_enqueue($hook) {
	wp_enqueue_style( 'wp-pointer' );
	wp_enqueue_script( 'wp-pointer' );
}
*/


add_action( 'admin_enqueue_scripts', 'cerber_admin_assets', 9999 );
function cerber_admin_assets() {

	$crb_assets_url = CRB_Globals::assets_url();

	if ( cerber_is_admin_page() ) {

		wp_register_style( 'crb_multi_css', $crb_assets_url . 'multi/multi.css', null, CERBER_VER );
		wp_enqueue_style( 'crb_multi_css' );
		wp_enqueue_script( 'crb_multi_js', $crb_assets_url . 'multi/multi.min.js', array(), CERBER_VER );

		wp_register_style( 'crb_tpicker_css', $crb_assets_url . 'tpicker/jquery.timepicker.min.css', null, CERBER_VER );
		wp_enqueue_style( 'crb_tpicker_css' );
		wp_enqueue_script( 'crb_tpicker', $crb_assets_url . 'tpicker/jquery.timepicker.min.js', array(), CERBER_VER );

		// Select2
		wp_register_style( 'select2css', $crb_assets_url . 'select2/dist/css/select2.min.css', null, null );
		wp_enqueue_style( 'select2css' );
		wp_enqueue_script( 'select2js', $crb_assets_url . 'select2/dist/js/select2.min.js', null, null, true );

		wp_register_style( 'crb_magnific_css', $crb_assets_url . 'magnific/magnific-popup.css', null, CERBER_VER );
		wp_enqueue_style( 'crb_magnific_css' );
		wp_enqueue_script( 'crb_magnific_js', $crb_assets_url . 'magnific/jquery.magnific-popup.min.js', null, CERBER_VER );

		wp_register_style( 'nexus_css', $crb_assets_url . 'nexus.css', null, CERBER_VER );
		wp_enqueue_style( 'nexus_css' );

		add_thickbox();

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-effects-bounce' );
		wp_enqueue_script( 'jquery-ui-sortable' );

	}

	if ( ! defined( 'CERBER_BETA' ) ) {

		wp_register_style( 'ui_stack_css', $crb_assets_url . 'ui-stack.css', null, CERBER_VER );
		wp_enqueue_style( 'ui_stack_css' );

		wp_enqueue_script( 'ui_stack_js', $crb_assets_url . 'ui-stack.js', array( 'jquery' ), CERBER_VER, true );
		wp_enqueue_script( 'cerber_js', $crb_assets_url . 'admin.js', array( 'jquery' ), CERBER_VER, true );

		if ( cerber_is_admin_page( array( 'page' => 'cerber-integrity' ) ) ) {
			wp_enqueue_script( 'cerber_scan', $crb_assets_url . 'scanner.js', array( 'jquery' ), CERBER_VER, true );
		}
	}

	wp_register_style( 'cerber_css_variables', $crb_assets_url . 'cerber-variables.css', null, CERBER_VER );
	wp_enqueue_style( 'cerber_css_variables' );

	wp_register_style( 'crb_icons_css', $crb_assets_url . 'icons/style.css', null, CERBER_VER );
	wp_enqueue_style( 'crb_icons_css' );

	wp_register_style( 'cerber_css', $crb_assets_url . 'admin.css', null, CERBER_VER );
	wp_enqueue_style( 'cerber_css' );

}

/*
 * JS & CSS for admin head
 *
 */
add_action('admin_head', 'cerber_admin_head' );
add_action('customize_controls_print_scripts', 'cerber_admin_head' ); // @since 5.8.1
function cerber_admin_head() {

	if ( cerber_is_admin_page() ) {
		remove_all_actions( 'admin_notices' ); // Remove aliens' notices on WP Cerber's page
	}
	else {
		add_action( 'admin_notices', 'cerber_show_admin_notice', 999 );
		add_action( 'network_admin_notices', 'cerber_show_admin_notice', 999 );
	}

	?>

    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

        var crb_admin_url = '<?php echo admin_url(); ?>';
        var crb_ajax_nonce = '<?php echo wp_create_nonce( 'crb-ajax-admin' ); ?>';
        var crb_ajax_loader = '<?php echo CRB_Globals::assets_url( 'ajax-loader.gif' ) ?>';
        var crb_lab_available = <?php echo lab_lab() ? 'true' : 'false'; ?>;

    </script>

    <style>
        #crb-admin-2fa-pins {
            border-left: solid 3px rgba(0, 121, 219, 0.63);
        }
        #crb-admin-2fa-pins table {
            border-collapse: collapse;
        }
        #crb-admin-2fa-pins table tr:first-child td {
            font-weight: bold;
        }
        #crb-admin-2fa-pins table td {
            padding: 6px 12px;
            background-color: rgba(255, 255, 255, 0.5);
        }
    </style>

	<?php

    global $pagenow;

	if ( 'users.php' === $pagenow ) :

		?>

        <style>

            /*.crb-user-blocked .crb-user-name,*/
            .crb-user-blocked td.column-username,
            .crb-user-prohibited td.column-username {
                color: red;
            }

            /* .crb-user-blocked .crb-user-name::after, */
            .crb-user-blocked td.column-username strong::after {
                content: ' <?php _e('BLOCKED','wp-cerber'); ?>';
            }

            .crb-user-prohibited td.column-username strong::after {
                content: ' <?php _e('PROHIBITED','wp-cerber'); ?>';
            }

        </style>

    <?php

	endif;

    if ( defined( 'CERBER_BETA' ) ) :
		?>
        <style id="wp-cerber-css-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
            <?php

             readfile( cerber_assets_dir().'/admin.css');
             readfile( cerber_assets_dir().'/ui-stack.css');

            ?>
        </style>
	<?php

	endif;

	if ( ! cerber_is_admin_page() ) {
		return;
	}

	?>

    <meta http-equiv="x-dns-prefetch-control" content="on"/>
    <link rel='dns-prefetch' href='//wpcerber.com'/>

    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

        <?php

        echo 'var crb_admin_page = "' . crb_admin_get_page() . '";' . "\n";
        echo 'var crb_admin_tab = "' . crb_admin_get_tab() . '";' . "\n";
        echo 'var crb_user_locale = "' . substr( get_user_locale(), 0, 6 ) . '";' . "\n";

        ?>

    </script>

    <style>

        /* Thickbox styles */
        #TB_title {
            background-color: #0085ba !important;
            color: #fff;
        }

        .tb-close-icon {
            color: #fff !important;
        }

        #TB_window {
            font-family: 'Roboto', sans-serif;
        }

        /* Hide aliens' messages on WP Cerber's admin pages */
        .update-nag,
        #update-nag,
        #setting-error-tgmpa,
        .pms-cross-promo,
        .vc_license-activation-notice,
        #wordfenceConfigWarning,
        #crb-admin div#message.updated {
            display: none;
        }

    </style>
	<?php
}
/*
 * JS & CSS for admin footer
 *
 */
add_action( 'admin_footer', 'cerber_admin_footer' );
function cerber_admin_footer() {

	//add_some_pointers();

	crb_load_wp_js();

    // Add buttons to the user profile page

    $uid = 0;
	if ( defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE ) {
		$uid = get_current_user_id();
	}
    elseif ( ! empty( $_GET['user_id'] ) && is_admin_user_edit() ) {
		$uid = absint( $_GET['user_id'] );
	}

	if ( $uid ) {

		if ( $user = crb_get_userdata( $uid ) ) {
			if ( crb_is_user_blocked( $uid ) ) {
				?>
                <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
                    document.querySelector('#profile-page hr.wp-header-end').insertAdjacentHTML('beforebegin', '<span class="page-title-action" style="border: none; border-radius: 0; color: white; background-color: #e4383d; cursor: auto;"><?php echo __( 'User is blocked', 'wp-cerber' ); ?></span>');
                </script>
				<?php
			}
			if ( crb_is_username_prohibited( $user->user_login ) ) {
				?>
                <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
                    document.querySelector('#profile-page hr.wp-header-end').insertAdjacentHTML('beforebegin', '<span class="page-title-action" style="border: none; border-radius: 0; color: white; background-color: #e4383d; cursor: auto;"><?php echo __( 'Username is prohibited', 'wp-cerber' ); ?></span>');
                </script>
				<?php
			}
		}

		$user_links = '<a href="' . cerber_admin_link( 'activity', array( 'filter_user' => $uid ) ) . '" class="page-title-action crb-title-button">' . __( 'View Activity', 'wp-cerber' ) . '</a>';
		if ( $uss = crb_sessions_get_num( $uid ) ) {
			$user_links .= '<a href="' . cerber_admin_link( 'sessions', array( 'filter_user' => $uid ) ) . '" class="page-title-action crb-title-button">' . __( 'Sessions', 'wp-cerber' ) . ' ' . $uss . '</a>';
        }
		?>
        <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
            document.querySelector('#profile-page h1.wp-heading-inline').insertAdjacentHTML('afterend','<?php echo $user_links; ?>');
        </script>
		<?php
	}

    // Link to the Sessions page

	?>
    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
        let html_target = document.querySelector('body.users-php #wpbody-content h1.wp-heading-inline')
        if (html_target) {
            html_target.insertAdjacentHTML('afterend', '<?php echo '<a href="' . cerber_admin_link( 'sessions' ) . '" class="page-title-action crb-title-button">' . __( 'Sessions', 'wp-cerber' ) . '</a>'; ?>');
        }
    </script>

	<?php

	// Check for blocked users

	$blocked = array();
	$prohibited = array();

	if ( $uids = crb_users_on_the_page() ) {
		foreach ( $uids as $uid => $login ) {
            if ( crb_is_user_blocked( $uid ) ) {
				$blocked[] = $uid;
			}
			elseif ( crb_is_username_prohibited( $login ) ) {
				$prohibited[] = $uid;
			}
		}
	}

    ?>

    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

        // Highlight blocked users
        let crb_blocked_users = <?php echo '[' . implode( ',', $blocked ) . ']'; ?>;
        let crb_prohibited_users = <?php echo '[' . implode( ',', $prohibited ) . ']'; ?>;

        for (let uid of crb_blocked_users) {
            document.getElementById('user-' + uid)?.classList.add("crb-user-blocked");
        }

        for (let uid of crb_prohibited_users) {
            document.getElementById('user-' + uid)?.classList.add("crb-user-prohibited");
        }

        // Switching inputs on the profile page

        jQuery( function( $ ) {

            // Old (in use)

            let mrow = $('#profile-page .crb_blocked_txt');

            $("#crb_user_blocked").on('change', function () {
                if ($(this).is(':checked')) {
                    mrow.show();
                }
                else {
                    mrow.hide();
                }
            });

            // Conditional form fields (new)

            let crb_user_edit_fields = $('#crb-wp-user-edit');

            crb_user_edit_fields.find('input,select').on('change', function () {
                let enabler_id = $(this).attr('id');
                let enabler_val;

                if ('checkbox' === $(this).attr('type')) {
                    enabler_val = !!$(this).is(':checked');
                }
                else {
                    enabler_val = $(this).val();
                }

                crb_user_edit_fields.find('[data-input_parent="' + enabler_id + '"]').each(function () {
                    let input_data = $(this).data();
                    let method = 'hide';

                    if (typeof input_data['input_parent_value'] !== "undefined") {
                        let target = input_data['input_parent_value'];
                        if (Array.isArray(target)) {
                            for (let i = 0; i < target.length; i++) {
                                if (String(enabler_val) === String(target[i])) {
                                    method = 'show';
                                    break;
                                }
                            }
                        }
                        else {
                            if (String(enabler_val) === String(input_data['input_parent_value'])) {
                                method = 'show';
                            }
                        }
                    }
                    else {
                        if (enabler_val) {
                            method = 'show';
                        }
                    }

                    let input_wrapper = $(this).closest('tr');

                    if (method === 'show') {
                        input_wrapper.fadeIn(500);
                    }
                    else if (method === 'hide') {
                        input_wrapper.fadeOut();
                    }

                });
            });

        });

    </script>
	<?php

	/* Background tasks */

    $list = cerber_bg_task_get_all();

	if ( $list ) {
		$list = array_slice( $list, 0, 20 );
		$list = array_keys( $list );
		?>

        <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

            let crb_bg_tasks = <?php echo '["' . implode( '","', $list ) . '"]'; ?>;

            jQuery( function( $ ) {

                if (crb_bg_tasks.length) {
                    console.log('Background tasks: ' + crb_bg_tasks.length);
                    $.post(ajaxurl, {
                            ajax_nonce: crb_ajax_nonce,
                            action: 'cerber_local_ajax',
                            crb_ajax_do: 'bg_tasks_run',
                            tasks: $(crb_bg_tasks).toArray(),
                        }
                    );
                }
            });
        </script>
		<?php
	}


    // Inter-Page Loader

	?>

    <div id="ui_page_overlay" style="display: none;"></div>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const containers = [
                document.getElementById('crb-admin'),
                document.getElementById('toplevel_page_cerber-security'),
                document.getElementById('cerber_quick'),

                document.querySelector('body.users-php #wpbody-content'),
                document.querySelector('#wpbody-content #profile-page'),
                document.querySelector('#adminmenu #menu-users'),
            ];

            containers.forEach(container => {
                if (container) {
                    const links = container.querySelectorAll('a');
                    links.forEach(link => {
                        link.classList.add('cerber-page-loader-candidate');
                    });
                }
            });

            // Helper function to parse query parameters
            const getQueryParams = (url) => {
                const queryString = url.split('?')[1] || '';
                return Object.fromEntries(new URLSearchParams(queryString));
            };

            const excludedSchemes = ['mailto:', 'tel:', 'sms:', 'ftp:', 'geo:', 'javascript:'];
            const overlay = document.getElementById('ui_page_overlay');

            document.addEventListener('click', function (event) {
                let link;

                if (event.ctrlKey || event.metaKey) {
                    return;
                }

                if (event.target.tagName === 'A') {
                    link = event.target;
                }
                else {
                    link = event.target.closest('a');
                }

                if (!link || !link.classList.contains('cerber-page-loader-candidate')) {
                    return;
                }

                const href = link.getAttribute('href') || '';
                const hasOnclick = link.hasAttribute('onclick');
                const target = link.getAttribute('target') || '';

                // Check for exceptions

                if (
                    !href ||
                    hasOnclick ||
                    href.startsWith('#') ||
                    target ||
                    excludedSchemes.some(scheme => href.startsWith(scheme))
                ) {
                    return;
                }

                // The link looks valid, let's check it for specific locations (URLs) we will activate the overlay loader for

                const params = getQueryParams(href);

                // Conditions for overlay activation
                const validPages = ['cerber-security', 'cerber-traffic'];
                const validTabs = ['dashboard', 'activity', 'sessions', 'traffic'];

                if (!link.classList.contains('crb-page-loader-force')) {
                    if (params.cerber_nonce
                        || !params.page
                        || !validPages.includes(params.page)
                        || (params.tab && !validTabs.includes(params.tab))) {
                        return;
                    }
                }

                event.preventDefault();
                overlay.style.display = 'flex';

                // Trigger delayed navigation with delay to avoid "flashing effect"
                setTimeout(() => {
                    window.location.href = href;
                }, 200);

            });

        });

    </script>


	<?php

	// ------------------------------------------------------

	if ( ! cerber_is_admin_page() ) {
		return;
	}

	if ( defined( 'CERBER_BETA' ) ) :
		?>
        <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

            <?php
            readfile( cerber_assets_dir() . '/admin.js' );
			readfile( cerber_assets_dir() . '/ui-stack.js' );

			if ( cerber_is_admin_page( array( 'page' => 'cerber-integrity' ) ) ) {
				readfile( cerber_assets_dir() . '/scanner.js' );
			}
			?>

        </script>
		<?php
	endif;

	// Time pickers
	$format = 'H:i';
	if ( cerber_is_ampm() ) {
		$format = 'h:i A';
	}
	?>
    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
        jQuery( function( $ ) {
            $('.crb-tpicker').timepicker({
                'step': 20,
                'forceRoundTime': true,
                'timeFormat': '<?php echo $format; ?>'
            });
        });
    </script>
	<?php

	if ( ! lab_lab()
         && cerber_is_admin_page( array( 'tab' => array( 'scan_schedule', 'scan_policy' ) ) ) ) :
		?>
        <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
            jQuery( function( $ ) {
                $('input').attr('disabled', 'disabled');
                $('select').attr('disabled', 'disabled');
                $('.crb-slider').css('background-color', '#888');
                $('th').add('td').add('h2').css('color', '#888');

                $('#crb-main h2').first().before('<?php echo str_replace( array(
		            "\n",
		            "\r"
	            ), '', crb_admin_cool_features() ); ?>');
            });
        </script>
		<?php
	endif;

	?>

    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">

		<?php

		if ( cerber_is_admin_page( array( 'page' => 'cerber-integrity' ) ) ) {

			echo crb_generate_safe_js_var( 'crb_scan_top_issues', crb_get_top_issue_ids() );
			echo crb_generate_safe_js_var( 'crb_scan_msg_steps', cerber_get_stage_titles_all() );
			echo crb_generate_safe_js_var( 'crb_scan_msg_issues', cerber_get_issue_title() );
			echo crb_generate_safe_js_var( 'crb_scan_msg_labels', cerber_get_issue_labels() );
			echo crb_generate_safe_js_var( 'crb_scan_msg_risks', cerber_get_risk_labels() );
			echo crb_generate_safe_js_var( 'crb_scan_msg_misc', crb_scan_messages() );
			echo crb_generate_safe_js_var( 'crb_txt_strings', cerber_get_strings() );
		}

		echo crb_generate_safe_js_var( 'crb_admin_messages', array( 'are_you_sure' => __( 'Are you sure?', 'wp-cerber' ) ) );


        // Copy to clipboard code for the Diagnostic page

		?>

        document.addEventListener('click', function (event) {

            if (event.target.matches('.crb-copy-to-clipboard')) {
                event.preventDefault();
                //event.stopImmediatePropagation();

                const copy_control = event.target;

                const targetClass = copy_control.dataset?.copy_clipboard_class;
                if (!targetClass) {
                    console.error("Required attribute 'data-copy_clipboard_class' not found.");
                    return;
                }

                const elements = document.querySelectorAll(`.${targetClass}`);
                if (!elements.length) {
                    console.error("No elements to copy. Check the class of the elements.");
                    return;
                }

                const isPlainText = copy_control.dataset?.plain_text === '1';

                const textToCopy = Array.from(elements)
                    .map(el => {
                        if (isPlainText) {
                            return el.textContent.trim();
                        }
                        else {
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = el.innerHTML.replace(/<br\s*\/?>/gi, '\n');
                            return tempDiv.textContent.trim();
                        }
                    })
                    .join('\n');

                if (!textToCopy) {
                    console.warn("Nothing to copy.");
                    return;
                }

                navigator.clipboard.writeText(textToCopy)
                    .then(() => {

                        const originalText = copy_control.textContent;
                        copy_control.textContent  = 'Copied!';

                        setTimeout(() => {
                            copy_control.textContent  = originalText;
                        }, 2000);
                    })
                    .catch(err => console.error('Unable to copy to clipboard:', err));
            }
        });

    </script>

	<?php

	echo CRB_Globals::$admin_footer_html;
}

function crb_load_wp_js() {
	echo '<script src="' . CRB_Globals::assets_url( 'wp-admin.js' ) . '"></script>';
}

add_action( 'admin_enqueue_scripts', 'cerber_dequeue_unwanted_scripts', PHP_INT_MAX );

/**
 * Prevents loading of enemy JS files on WP Cerber admin pages
 *
 * @return void
 *
 * @since 9.6.1.5
 */
function cerber_dequeue_unwanted_scripts() {
	global $wp_scripts;

	if ( ! cerber_is_admin_page() ) {
		return;
	}

	$current_queue = $wp_scripts->queue;

	foreach ( $current_queue as $script_handle ) {

		if ( ( $script = $wp_scripts->registered[ $script_handle ] ?? false )
		     && isset( $script->src )
		     && crb_is_enemy_plugin_script( $script->src ) ) {

			wp_dequeue_script( $script_handle );
		}
	}
}

/**
 * Checks for enemy JS scripts
 *
 * @param string $source The source of a JS script
 *
 * @return bool True if the source may not be included as an asset
 *
 * @since 9.6.1.5
 */
function crb_is_enemy_plugin_script( $source ) {
	static $dir_url, $dir, $allowed = array( 'wp-cerber', 'query-monitor' );

	if ( ! $source ) {
		return false;
	}

	if ( ! $dir_url ) {
		$dir_url = plugin_dir_url( '' );
	}

	if ( ! $dir ) {
		$dir = cerber_get_plugins_dir() . DIRECTORY_SEPARATOR;
	}

	// Type one: a URL to a file

	if ( 0 === strpos( $source, 'https://' )
	     || 0 === strpos( $source, 'http://' ) ) {

		if ( 0 === strpos( $source, $dir_url ) ) {

			foreach ($allowed as $slug) {
				if ( 0 === strpos( $source, $dir_url . $slug . '/' ) ) {

					return false;
				}
			}

			return true;
		}

		return false;
	}

	// Type 2: an absolute path to a file

	if ( 0 === strpos( $source, $dir ) ) {
		foreach ( $allowed as $slug ) {
			if ( 0 === strpos( $source, $dir . $slug . DIRECTORY_SEPARATOR ) ) {
				return false;
			}
		}

		return true;
	}

	// Type 3: a relative path to a file

	$source = ABSPATH . ltrim( $source, '/\\' );

	if ( 0 === strpos( $source, $dir ) ) {
		foreach ( $allowed as $slug ) {
			if ( 0 === strpos( $source, $dir . $slug . DIRECTORY_SEPARATOR ) ) {

				return false;
			}
		}

		return true;
	}

	return false;
}


add_filter( 'admin_footer_text', function ( $text ) {
	if ( ! cerber_is_admin_page() ) {
		return $text;
	}

	return 'If you like <strong>WP Cerber</strong>, <a target="_blank" href="' . crb_get_review_url( 'tradius' ) . '">please give it a &#9733;&#9733;&#9733;&#9733;&#9733; review</a>. Thanks!';
}, PHP_INT_MAX );

add_filter( 'update_footer', function ( $text ) {
	if ( ! cerber_is_admin_page() ) {
		return $text;
	}

	$pr = '';
	$pro = false;
	$mode = '';

	if ( $remote = nexus_get_context() ) {
		$ver = $remote->plugin_v;
		if ( ( $data = nexus_get_remote_data() )
		     && $data['extra']['versions'][6] ?? '' ) {
			$pro = true;
		}
	}
	else {

		$ver = CERBER_VER;
        $pro = lab_lab();
		$mode = ( 1 == cerber_get_mode() ) ? 'in the Standard mode' : 'in the Legacy mode';

	}

	if ( $pro ) {
		$pr = 'PRO';
		$support = '| <a target="_blank" href="https://my.wpcerber.com">Get Support</a>';
	}
	else {
		$support = '| <a target="_blank" href="https://talk.wpcerber.com">Support Forum</a>';
	}

	return 'WP Cerber Security ' . $pr . ' ' . $ver . '. ' . $mode . ' ' . $support;

}, PHP_INT_MAX );

/*
 * Add per screen user settings
 */
function crb_admin_screen_options() {
	if ( ! $id = crb_get_configurable_screen() ) {
		return;
	}

	add_filter( 'screen_settings', function ( $form, $screen ) {
		// Fixing an invalid WP redirection implemented using wp_get_raw_referer().
		// Instead of using the "Referer" header sent by a user browser, we use the proper value we need.
        // A browser may strip $_GET parameters from the header.
		$form .= '<input id="crb-fix-referer" type="hidden" name="_wp_http_referer" value="' . $_SERVER['REQUEST_URI'] . '" />';

		return $form;
	}, 0, 2 );

	add_screen_option( 'per_page', array(
		//'label' => __( 'Number of items per page:' ),
		'default' => 25,
		'option'  => 'cerber_screen_' . $id . '_page', // '_page' is mandatory since WP 5.4.2
	) );
}

/*
 * Enables saving screen options to the user's meta
 */
add_filter( 'set-screen-option', function ( $status, $option, $value ) {
	if ( $id = crb_get_configurable_screen() ) {
		if ( 'cerber_screen_' . $id . '_page' == $option ) {
			return $value;
		}
	}

	return $status;
}, 10, 3 );

/*
 * Returns false if the admin page has no screen options to configure
 *
 * @return false|string
 */
function crb_get_configurable_screen() {
	if ( ! $id = crb_admin_get_tab() ) {
		$id = crb_admin_get_page();
	}
	if ( $id == 'cerber-traffic' ) {
		$id = 'traffic';
	}
	if ( ( $id == 'cerber-nexus' ) && nexus_is_main() ) {
		return 'nexus_sites';
	}
	$ids = array( 'sessions', 'lockouts', 'activity', 'traffic', 'scan_quarantine', 'scan_ignore', 'nexus_sites' );
	if ( ! in_array( $id, $ids ) ) {
		return false;
	}

	return $id;
}

/*
 * Retrieve settings for the current screen
 *
 */
function crb_admin_get_per_page() {
	if ( is_multisite() ) {
		return 50;  // temporary workaround
	}

	if ( nexus_is_valid_request() ) {
		$per_page = nexus_request_data()->screen['per_page'];
	}
    elseif ( function_exists( 'get_current_screen' ) ) {
		set_current_screen();
		$screen = get_current_screen();

		if ( $screen_option = $screen->get_option( 'per_page', 'option' ) ) {
			$per_page = absint( get_user_meta( get_current_user_id(), $screen_option, true ) );
			if ( empty ( $per_page ) ) {
				$per_page = absint( $screen->get_option( 'per_page', 'default' ) );
			}
		}
	}

	if ( empty ( $per_page ) ) {
		$per_page = 25;
	}

	return absint( $per_page );
}

/**
 * @param array $tabs_config array of tabs: title, desc, contents and optional JS callback
 * @param string $submit Text for the submit button
 * @param array $hidden Hidden form fields
 * @param string $form_id
 *
 */
function crb_admin_show_vtabs( $tabs_config, $submit = '', $hidden = array(), $form_id = 'cerber_tabs' ) {

	$tablinks  = '';
	$tabs      = '';

	foreach ( $tabs_config as $tab_id => $tab ) {
		$tab_id = str_replace( '-', '_', $tab_id );
		$tablinks .= '<div class="tablinks" data-tab-id="' . $tab_id . '" data-callback="' . crb_array_get( $tab, 'callback', '' ) . '">' . $tab['title'] . '<br/><span>' . crb_array_get( $tab, 'desc', '' ) . '</span></div>';
		$tabs     .= '<div id="tab-' . $tab_id . '" class="vtabcontent">' . $tab['content'] . '</div>';
	}

	echo '<form id="' . $form_id . '" method="post" action="" class="crb-settings">';

	if ( ! $submit ) {
		$submit = __( 'Save Changes', 'wp-cerber' );
	}

	echo '<table class="vtable" id="crb_vtabs_container" style="width: 100%; border-collapse: collapse;"><tr><td id="crb-vtabs"><div class="vtabs">' . $tablinks . '</div></td><td id="crb-vtabcontent">' . $tabs . '
    </td></tr><tr><td></td><td><div style="padding-left: 3em;">' . crb_admin_submit_button( $submit ) . '</div></td></tr></table>';

	cerber_nonce_field( 'control', true );

	if ( $hidden ) {
		foreach ( $hidden as $name => $val ) {
			echo '<input type="hidden" name="' . $name . '" value="' . $val . '">';
		}
	}

	echo '</form>';

}

function crb_admin_show_geo_rules(){

	$rules = cerber_geo_rule_set();

	$tabs_config = array();

	foreach ( $rules as $rule_id => $rule ) {

		list( $desc, $content ) = crb_admin_geo_selector( $rule_id, $rule );

		if ( isset( $rule['multi_set'] ) ) {
			$names = array( '---first' => $rule['multi_top'] . ' — ' . $desc );
			foreach ( $rule['multi_set'] as $item_id => $item_title ) {
				$id = $rule_id . '_' . $item_id;
				list( $d, $c ) = crb_admin_geo_selector( $id, $rule, 'crb-display-none' );
				$content .= $c;
				if ( cerber_get_geo_rules( $id ) ) {
					$desc = __( 'Role-based rules are configured', 'wp-cerber' );
					$names[ $item_id ] = $item_title . ' — ' . $d;
				}
				else {
					$names[ $item_id ] = $item_title;
				}
			}
			$content = '<p>' . cerber_select( 'sw_' . $rule_id, $names, null, 'crb-geo-switcher', null, null, null, array( 'rule-id' => $rule_id ) ) . '</p>' . $content;
		}

		$tabs_config[ $rule_id ] = array(
			'title'    => $rule['name'],
			'desc'     => $desc,
			'content'  => '<div style="padding-top: 22px;">' . $content . '</div>',
			'callback' => 'geo_rules_activator',
		);
	}

	?>

    <script id="wp-cerber-js-<?php echo crb_sanitize_id( __FUNCTION__ . '_' . __LINE__ ); ?>">
        window.geo_rules_activator = function (rule_id) {

            if( typeof search_fields === 'undefined' ) {
                var search_fields = jQuery('form#crb-geo-rules .multi-wrapper input:text');
            }

            search_fields.each(function () {
                if (jQuery(this).val()) {
                    jQuery(this).val(''); // Reset "Search" field

                    if( typeof wrapper === 'undefined' ) {
                        var wrapper = jQuery('#crb-geo-rules .multi-wrapper');
                    }

                    if( typeof select === 'undefined' ) {
                        var select = jQuery('#crb-geo-rules .crb-mega-select');
                    }

                    wrapper.remove();
                    select.removeAttr('data-multijs');
                    //document.querySelector('#crb-geo-rules .crb-mega-select').removeAttribute('data-multijs');
                    //document.querySelectorAll('#crb-geo-rules .crb-mega-select').removeAttribute('data-multijs');
                }
            });

            if( typeof geo_selectors === 'undefined' ) {
                var geo_selectors = jQuery('form#crb-geo-rules select.crb-mega-select');
            }

            geo_selectors.multi({'search_placeholder': '<?php echo crb_escape_string_for_js_in_html( __( 'Start typing here to find a country', 'wp-cerber' ) ); ?>'});

        };
    </script>

	<?php

	crb_admin_show_vtabs( $tabs_config, __( 'Save all rules', 'wp-cerber' ), array( 'cerber_admin_do' => 'update_geo_rules' ), 'crb-geo-rules' );

}

function crb_admin_geo_selector( $rule_id, $rule, $class = '' ) {

	$config   = cerber_get_geo_rules( $rule_id );
	$selector = crb_geo_country_selector( $config, $rule_id, $rule );
	$opt      = crb_get_settings();

	if ( ! empty( $config['list'] ) ) {
		$num = count( $config['list'] );
		if ( $config['type'] == 'W' ) {
			$info = sprintf( _n( 'Permitted for one country', 'Permitted for %d countries', $num, 'wp-cerber' ), $num );
		}
		else {
			$info = sprintf( _n( 'Not permitted for one country', 'Not permitted for %d countries', $num, 'wp-cerber' ), $num );
		}
		if ( $num == 1 ) {
			$info .= ' (' . current( $config['list'] ) . ')';
			//$info .= ' (' . cerber_get_flag_html($c) . $c . ')';
		}
	}
	else {
		$info = __( 'No rule', 'wp-cerber' );
		$info = __( 'Any country is permitted', 'wp-cerber' );
	}

	$note = '';

    switch ( $rule_id ) {
		case 'geo_register':
			if ( ! get_option( 'users_can_register' ) ) {
				$note = __( 'User registration is disabled in the General WordPress Settings.', 'wp-cerber' );
			}
			break;
		case 'geo_restapi':
			if ( $opt['norest'] ) {
				$note = __( 'Access to REST API is restricted in the WP Cerber settings.', 'wp-cerber' );
			}
			break;
		case 'geo_xmlrpc':
			if ( $opt['xmlrpc'] ) {
				$note = __( 'XML-RPC is disabled in the Hardening settings of WP Cerber.', 'wp-cerber' );
			}
			break;
	}

    if ( $note ) {
		$note = '<p><span class="dashicons-before dashicons-warning"></span> ' . __( 'Heads up!', 'wp-cerber' ) . ' ' . $note . '</p>';
	}

	return array(
		$info,
		$note . '<div class="crb-geo-wrapper ' . $class . '" id="crb-geo-wrap_' . $rule_id . '">' . $selector . '</div>'
	);

}

/**
 * Generates GEO rule selector
 *
 * @param array $config Rule configuration
 * @param string $rule_id
 * @param array $rule
 *
 * @return string   HTML code of form
 */
function crb_geo_country_selector( $config = array(), $rule_id = '', $rule = array() ) {

	$ret = '<div class="crb-super-select"><select id="countries-' . $rule_id . '" name="crb-' . $rule_id . '-list[]" class="crb-mega-select" style="display: none;" multiple="multiple">';

	if ( ! empty( $config['list'] ) ) {
		$selected = $config['list'];
	}
	else {
		$selected = null;
	}

	foreach ( crb_get_country_list() as $code => $country ) {
		if ( $selected && in_array( $code, $selected ) ) {
			$sel = 'selected';
		}
		else {
			$sel = '';
		}
		$ret .= '<option ' . $sel . ' value="' . $code . '">' . $country . '</option>';
	}

	if ( ! empty( $config['type'] ) && $config['type'] == 'B' ) {
		$w = '';
		$b = 'checked="checked"';
	}
	else {
		$w = 'checked="checked"';
		$b = '';
	}

	if ( ! empty( $rule['desc'] ) ) {
		$desc = $rule['desc'];
	}
	else {
		$desc = '<span style="text-transform: lowercase;">' . $rule['name'] . '</span>';
	}


	$ret .= '
        </select>
        
        <div class="crb-super-details">
        <p><span style="opacity: 0.7;">' . __( 'Click on a country name to add it to the list of selected countries', 'wp-cerber' ) . '</span></p>
        
        <p style="margin-top: 2em;">
        <input type="radio" value="W" name="crb-' . $rule_id . '-type" id="geo-type-' . $rule_id . '-W" ' . $w . '>'
	        . '<label for="geo-type-' . $rule_id . '-W">'
	        /* translators: 'selected countries' refers to a list of countries. 'to' is the marker of an infinitive verb, e.g., "to use it". Placeholder %s will be replaced by an action/operation, such as "Submit forms". */
	        . sprintf( _x( 'Only selected countries are permitted to %s. All others are not.', 'to is a marker of infinitive, e.g. "to use it"', 'wp-cerber' ), $desc )
	        . '</label>
        <p>
        <input type="radio" value="B" name="crb-' . $rule_id . '-type" id="geo-type-' . $rule_id . '-B" ' . $b . '>
        <label for="geo-type-' . $rule_id . '-B">'
	        /* translators: 'selected countries' refers to a list of countries. 'to' is the marker of an infinitive verb, e.g., "to use it". Placeholder %s will be replaced by an action/operation, such as "Submit forms". */
	        . sprintf( _x( 'Selected countries are not permitted to %s. All others are allowed to.', 'to is a marker of infinitive, e.g. "to use it"', 'wp-cerber' ), $desc )
            . '</label>
        </p>
        </div>
        
        </div>';

	return $ret;

}

/**
 * A list of available GEO rules
 *
 * @return array
 */
function cerber_geo_rule_set() {
	$set = wp_roles()->role_names;

	$rules = array(
		'geo_login' => array(
			'name'      => __( 'Log into the website', 'wp-cerber' ),
			'multi_set' => $set,
			'multi_top' => __( 'All Users', 'wp-cerber' )
		),
		'geo_register' => array( 'name' => __( 'Register on the website', 'wp-cerber' ) ),
		'geo_submit'   => array( 'name' => __( 'Submit forms', 'wp-cerber' ) ),
		'geo_comment'  => array( 'name' => __( 'Post comments', 'wp-cerber' ) ),
		'geo_xmlrpc'   => array( 'name' => __( 'Use XML-RPC', 'wp-cerber' ) ),
		'geo_restapi'  => array( 'name' => __( 'Use REST API', 'wp-cerber' ) ),
	);

	return $rules;
}

function crb_settings_update_geo_rules( $post_fields = array() ) {
    global $check, $admin_country;

	if ( ! lab_lab() ) {
		return;
	}

	$check = array_keys( CERBER_COUNTRY_NAMES );

	// Prevent the admin country from being blocked

	if ( nexus_is_valid_request() ) {
		$admin_country = null;
	}
	else {
		$admin_country = lab_get_country( cerber_get_remote_ip(), false );
	}

	$geo = array();

	foreach ( cerber_geo_rule_set() as $rule_id => $rule ) {

		if ( $data = crb_admin_process_geo( $post_fields, $rule_id ) ) {
			$geo[ $rule_id ] = $data;
		}

		if ( isset( $rule['multi_set'] ) ) {
			foreach ( $rule['multi_set'] as $item_id => $item_title ) {
				$id = $rule_id . '_' . $item_id;
				if ( $data = crb_admin_process_geo( $post_fields, $id ) ) {
					$geo[ $id ] = $data;
				}
			}
		}
	}

	crb_sanitize_deep( $geo );

	if ( update_site_option( CERBER_GEO_RULES, $geo ) ) {
		cerber_admin_message( __( 'Security rules have been updated', 'wp-cerber' ) );
	}
}

function crb_admin_process_geo( $post, $rule_id ) {
	global $check, $admin_country;

	if ( empty( $post[ 'crb-' . $rule_id . '-list' ] ) || empty( $post[ 'crb-' . $rule_id . '-type' ] ) ) {
		return false;
	}

	$list = array_intersect( $post[ 'crb-' . $rule_id . '-list' ], $check );

	if ( $post[ 'crb-' . $rule_id . '-type' ] == 'B' ) {
		$type = 'B';
		if ( $admin_country && ( ( $key = array_search( $admin_country, $list ) ) !== false ) ) {
			unset( $list[ $key ] );
		}
	}
	else {
		$type = 'W';
		if ( $admin_country && ( ( $key = array_search( $admin_country, $list ) ) === false ) ) {
			array_push( $list, $admin_country );
		}
	}

	return array( 'list' => $list, 'type' => $type );
}

/**
 * Generates HTML for showing country in UI
 *
 * @param null $code
 * @param null $ip
 * @param bool $cache_only
 *
 * @return string
 */
function crb_country_html( $code = null, $ip = null, $cache_only = true ) {

	if ( ! lab_lab() ) {
		return '';
	}

	if ( ! $code ) {
		if ( $ip ) {
			$code = lab_get_country( $ip, $cache_only );
		}
		else {
			return '';
		}
	}

	if ( $code ) {
		$ret = crb_get_flag_html( $code, crb_get_country_name( $code ) );
	}
	else {
		$ip_id = cerber_get_id_ip( $ip );
		$ret = crb_get_ajax_placeholder( 'country', $ip_id );
	}

	return $ret;
}

// Traffic =====================================================================================

function cerber_export_traffic( $params = array() ) {

	crb_raise_limits( 512 );

	$args = array(
		'per_page' => 0,
		'columns'  => array( 'ip', 'stamp', 'uri', 'session_id', 'user_id', 'processing', 'request_method', 'http_code', 'wp_type', 'blog_id' )
	);

	if ( $params ) {
		$args = array_merge( $params, $args );
	}

	list( $query, $found ) = cerber_build_traffic_query( $args );

	// We split into several requests to avoid PHP and MySQL memory limitations

	if ( defined( 'CERBER_EXPORT_CHUNK' ) && is_numeric( CERBER_EXPORT_CHUNK ) ) {
		$per_chunk = CERBER_EXPORT_CHUNK;
	}
	else {
		$per_chunk = 1000; 	// Rows per SQL request, we assume that this number is not too small and not too big
	}

	if ( ! $result = cerber_db_query( $query . ' LIMIT ' . $per_chunk ) ) {
		wp_die( 'Nothing to export' );
	}

	$total = cerber_db_get_var( $found );

	$info = array();

	$heading = array(
		__( 'IP address', 'wp-cerber' ),
		__( 'Date', 'wp-cerber' ),
		'Method',
		'URI',
		'HTTP Code',
		'Request ID',
		__( 'User ID', 'wp-cerber' ),
		__( 'Page generation time', 'wp-cerber' ),
		'Blog ID',
		'Type',
		'Unix Timestamp',
	);

	cerber_send_csv_header( 'wp-cerber-http-requests', $total, $heading, $info );

	//$labels = cerber_get_labels( 'activity' );
	//$status = cerber_get_labels( 'status' );

	if ( crb_get_settings( 'plain_date' ) ) {
		function _crb_csv_date( $timestamp ) {
			static $gmt_offset;
			if ( $gmt_offset === null ) {
				$gmt_offset = get_option( 'gmt_offset' ) * 3600;
			}
			return date( 'Y-m-d H:i:s', $timestamp + $gmt_offset );
		}
	}
	else {
		function _crb_csv_date( $timestamp ) {
			return cerber_date( $timestamp, false );
		}
	}

	// The loop

	$i = 0;

	do {

		while ( $row = mysqli_fetch_assoc( $result ) ) {

			$values = array();

			/*
			$values[] = $row->ip;
			$values[] = cerber_date( $row->stamp );
			$values[] = $row->request_method;
			$values[] = $row->uri;
			$values[] = $row->http_code;
			$values[] = $row->session_id;
			$values[] = $row->user_id;
			$values[] = $row->processing;
			$values[] = $row->blog_id;
			$values[] = $row->wp_type;
			$values[] = $row->stamp;*/

			$values[] = $row['ip'];
			$values[] = _crb_csv_date( $row['stamp'] );
			$values[] = $row['request_method'];
			$values[] = $row['uri'];
			$values[] = $row['http_code'];
			$values[] = $row['session_id'];
			$values[] = $row['user_id'];
			$values[] = $row['processing'];
			$values[] = $row['blog_id'];
			$values[] = $row['wp_type'];
			$values[] = $row['stamp'];

			cerber_send_csv_line( $values );
		}

		mysqli_free_result( $result );

		$i++;
		$offset = $per_chunk * $i;

	} while ( ( $result = cerber_db_query( $query . ' LIMIT ' . $offset . ', ' . $per_chunk ) )
	          && $result->num_rows );

	exit;
}

/**
 * Generates a label for the given HTTP code
 *
 * @param int $http_code
 *
 * @return string HTML code of the label, safe in any context
 *
 * @since 9.6.6.4
 */
function crb_get_http_code_label( int $http_code ): string {

	$http_class = 'crb-http-' . $http_code . ' ' . ( ( $http_code < 400 ) ? 'crb-http-ok' : 'crb-http-error' );

	if ( $http_code == 302 || $http_code == 301 ) {
		$text = 'REDIRECT ' . $http_code;
	}
	else {
		$text = 'HTTP ' . $http_code . ' ' . get_status_header_desc( $http_code );
	}

    return '<span class="' . $http_class . '"> ' . $text . '</span>';
}

/**
 * Marks WP Cerber's fields by transforming values into objects.
 *
 * @param array $fields Fields to apply conditional highlighting
 * @param int $type
 *
 * @return void
 *
 * @since 9.5.3.4
 */
function crb_highlight_fields_type_two( &$fields, $type = 1 ) {
    static $crb_fields;

	if ( ! $crb_fields ) {

		if ( ! $anti = cerber_antibot_gene() ) {
			return;
		}

		$crb_fields[1] = array_flip( array_column( $anti[0], 0 ) );

        // All cookies

		$crb_cookies = array_column( $anti[1], 0 );

		foreach ( $crb_cookies as &$val ) {
			$val = cerber_get_cookie_prefix() . $val;
		}

		$crb_cookies[] = cerber_get_cookie_prefix() . CRB_GROOVE;

		$groove_x = cerber_get_groove_x();
		$crb_cookies[] = cerber_get_cookie_prefix() . CRB_GROOVE . '_x_' . $groove_x[0];

		$crb_cookies[] = cerber_get_cookie_prefix() . 'cerber_nexus_id';

		$crb_fields[2] = array_flip( $crb_cookies );
	}

	foreach ( $fields as $field_id => &$value ) {
		if ( ! $value
             || ! is_scalar( $value ) ) {
			continue;
		}

		if ( isset( $crb_fields[ $type ][ $field_id ] ) ) {
			$value = (object) array( 'element_class' => 'crb-highlight-this', 'element_value' => $value );
		}
	}
}

/**
 * Marks WP Cerber's fields by transforming values into objects.
 *
 * @param array $fields Fields to apply conditional highlighting
 * @param array $target These fields having these values will be highlighted
 *
 * @return void
 *
 * @since 9.6.6.4
 */
function crb_highlight_fields_type_one( array &$fields, array $target = array() ) {
	foreach ( $fields as $field_id => &$value  ) {
		if ( ! $value
		     || ! is_scalar( $value ) ) {
			continue;
		}

		if ( isset( $target[ $field_id ] )
		     && $value == $target[ $field_id ] ) {
			$value = (object) array( 'element_class' => 'crb-highlight-this', 'element_value' => $value );
		}
	}
}

/**
 * Simple and lightweight detector of known browsers/crawlers and platform in User Agent string
 *
 * @param string $ua
 *
 * @return string Sanitized and escaped browser name and platform on success, 'Unknown' on failure
 * @since 6.0
 */
function cerber_detect_browser( $ua ) {
	$ua  = trim( $ua );

	if ( empty( $ua ) ) {
		return __( 'Not specified', 'wp-cerber' );
	}

	if ( preg_match( '/\(compatible\;(.+)\)/i', $ua, $matches ) ) {
		$bot_info = explode( ';', $matches[1] );
		foreach ( $bot_info as $item ) {
			if ( stripos( $item, 'bot' )
			     || stripos( $item, 'crawler' )
			     || stripos( $item, 'spider' )
			     || stripos( $item, 'Yandex' )
			     || stripos( $item, 'Yahoo! Slurp' )
			) {
				if ( strpos( $ua, 'Android' ) ) {
					$item .= ' Mobile';
				}

				return crb_escape_html( $item );
			}
		}
		if ( strpos( $ua, 'Google-Read-Aloud' ) ) {
			return 'Google Read Aloud';
		}
	}
    elseif ( strpos( $ua, 'google.com' ) || strpos( $ua, 'Google' ) ) {
		// Various Google bots

		$ret = '';

		if ( false !== strpos( $ua, 'Googlebot' ) ) {
			if ( strpos( $ua, 'Android' ) ) {
				$ret = 'Googlebot Mobile';
			}
            elseif ( false !== strpos( $ua, 'Mozilla' ) ) {
				$ret = 'Googlebot Desktop';
			}
		}
        elseif ( preg_match( '/AdsBot-Google-Mobile|AdsBot-Google|APIs-Google|FeedFetcher-Google/', $ua, $matches ) ) {
			$ret = $matches[0];
		}
        elseif ( 0 === strpos( $ua, 'Googlebot' ) ) {
			if ( preg_match( '/Googlebot-\w+/', $ua, $matches ) ) {
				$ret = $matches[0];
			}
		}
        elseif ( 0 === strpos( $ua, 'Mediapartners-Google' ) ) {
			return 'AdSense Crawler';
		}
        elseif ( 0 === strpos( $ua, 'AdsBot-Google-Mobile-Apps' ) ) {
			return 'Mobile Apps Android';
		}
        elseif ( strpos( $ua, 'DuplexWeb-Google' ) ) {
			return 'Duplex on the Web by Google';
		}
        elseif ( strpos( $ua, 'Google Favicon' ) ) {
			return 'Google Favicon';
		}

		if ( $ret ) {
			return crb_escape_html( $ret );
		}
		else {
			return __( 'Unknown Google\'s bot', 'wp-cerber' );
		}
	}
    /*elseif ( 0 === strpos( $ua, 'Googlebot' ) ) {
		if ( preg_match( '/Googlebot-\w+/', $ua, $matches ) ) {
			return $matches[0];
		}
	}*/
    elseif ( 0 === strpos( $ua, 'WordPress/' ) ) {
		list( $ret ) = explode( ';', $ua, 2 );
		return crb_escape_html( $ret );
	}
    elseif ( 0 === strpos( $ua, 'PayPal IPN' ) ) {
		return 'PayPal Payment Notification';
	}
    elseif (0 === strpos( $ua, 'Wget/' )){
		return crb_escape_html( $ua );
	}
    elseif ( strpos( $ua, 'googleweblight' )){
		return 'Web Light by Google';
	}

	$browsers = array(
		'Firefox/'   => 'Firefox',
		'OPR/'       => 'Opera',
		'Opera/'     => 'Opera',
		'YaBrowser/' => 'Yandex Browser',
		'Trident/'   => 'Internet Explorer',
		'IE/'        => 'Internet Explorer',
		'Edge/'      => 'Microsoft Edge',
		'Edg/'       => 'Microsoft Edge',
		'Chrome/'    => 'Chrome',
		'Safari/'    => 'Safari',
		'Lynx/'      => 'Lynx',
	);

	$systems  = array( 'Android' , 'Linux', 'Windows', 'iPhone', 'iPad', 'Macintosh', 'OpenBSD', 'Unix' );

	$browser = '';
	foreach ( $browsers as $browser_id => $browser_name ) {
		if ( false !== strpos( $ua, $browser_id ) ) {
			$browser = $browser_name;
			break;
		}
	}

	$system = '';
	foreach ( $systems as $system_id ) {
		if ( false !== strpos( $ua, $system_id ) ) {
			$system = $system_id;
			break;
		}
	}

	if ( $browser == 'Lynx' && ! $system ) {
		$system = 'Linux';
	}
    elseif ( $system == 'Macintosh' ) {
		$system = 'Mac';
	}

	if ( $system == 'Android' ) {
		if ( preg_match( '/(Android\s+\d+);/', $ua, $matches ) ) {
			$system = $matches[1];
		}
	}

	if ( $browser && $system ) {
		$ret = $browser . ' on ' . $system;
	}
    elseif ( 0 === strpos( $ua, 'python-requests' ) ) {
		$ret = 'Python Script';
	}
    elseif ( 0 === strpos( $ua, 'ApacheBench' ) ) {
		$ret = $ua;
	}
	else {
		$ret = __( 'Unknown', 'wp-cerber' );
	}

	return crb_escape_html( $ret );
}

/**
 * Create a table view of an array to display it
 *
 * @param string $title
 * @param array $fields
 * @param bool $plain If true, treat $fields as a two-dimensional list
 * @param bool $nested
 *
 * @return string An escaped table view
 *
 * @deprecated 9.6.9.7 Use {@see crb_ui_table_view()}
 *
 */
function cerber_table_view( string $title, array $fields, $plain = false, $nested = false ) {
	if ( empty( $fields ) ) {
		return '';
	}

	$class = 'crb-fields-table ' . ( $nested ? 'crb-sub-table' : 'crb-top-table' );

	$view = '<table class="' . $class . '">';

	if ( $title ) {
		//$view .= '<tr><td colspan="2" class="crb-monospace-bold">' . $title . '</td></tr>';
		$view .= '<tr><td colspan="2">' . $title . '</td></tr>';
	}

	foreach ( $fields as $key => $value ) {

		$row_name = $key;
		$atts = '';

        if ( is_object( $value ) ) {
	        $atts = ' class="' . crb_boring_escape( $value->element_class ) . '"';
	        $value = $value->element_value;
		}

		if ( is_array( $value ) ) {
			if ( $plain
			     && is_scalar( $value[0] ) && is_scalar( $value[1] ) ) { // Make sure it is a two-dimensional list
				$row_name = $value[0];
				$content = '<div>' . nl2br( crb_escape_html( $value[1] ) ) . '</div>';
			}
			else {
				$content = cerber_table_view( '', $value, false, true );
			}
        }
		else {
			$content = '<div>' . nl2br( crb_escape_html( $value ) ) . '</div>';
		}

		$view .= '<tr><td ' . $atts . '>' . crb_escape_html( $row_name ) . '</td><td>' . $content . '</td></tr>';
	}

	$view .= '</table>';

	return $view;
}

/**
 * Parse arguments and create SQL query for retrieving rows from the traffic table
 *
 * @param array $args Optional arguments to use them instead of using $_GET
 *
 * @return array
 *
 * @since 6.0
 */
function cerber_build_traffic_query( $args = array() ) {
	global $wpdb;

	$ret = array_fill( 0, 8, '' );
	$where = array();
	$join = '';
	$join_act = false;

	$q = crb_admin_parse_query( array_keys( CRB_TRF_PARAMS ), $args );

	if ( preg_match( '/^\w+$/', $q->filter_sid ) ) {
		$where[] = 'log.session_id = "' . $q->filter_sid . '"';
	}

	$falist = array();
	if ( $q->filter_http_code ) { // Multiple codes can be requested this way: &filter_http_code[]=404&filter_http_code[]=405
		if ( is_array( $q->filter_http_code ) ) {
			$falist  = array_filter( array_map( 'absint', $q->filter_http_code ) );
			$where[] = 'log.http_code IN (' . implode( ',', $falist ) . ')';
		}
		else {
			$filter  = absint( $q->filter_http_code );
			$op = '=';
			if ( $q->filter_http_code_mode == 'GT' ) {
				$op = '>';
			}
			$where[] = 'log.http_code ' . $op . $filter;
			$falist  = array( $filter ); // for further using in links
		}
	}
	//$ret[3] = $falist;

	if ( $q->filter_ip ) {
		$range = cerber_any2range( $q->filter_ip );
		if ( is_array( $range ) ) {
			$where[] = '(log.ip_long >= ' . $range['begin'] . ' AND log.ip_long <= ' . $range['end'] . ')';
		}
        elseif ( cerber_is_ip_or_net( $q->filter_ip ) ) {
			$where[] = 'log.ip = "' . $q->filter_ip . '"';
		}
		else {
			$where[] = "log.ip = 'produce-no-result'";
		}
		$ret[4] = preg_replace( CRB_IP_NET_RANGE, '', $q->filter_ip );
	}

	if ( $q->filter_processing ) {
		$p       = absint( $q->filter_processing );
		$where[] = 'log.processing > ' . $p;
		$ret[5]  = $p;
	}

	$filter_user = $q->filter_user !== false ? $q->filter_user : ( $q->filter_user_alt !== false ? $q->filter_user_alt : '' );

	if ( $filter_user == '*' ) {
		$um = empty( $q->filter_user_mode ) ? '!=' : '=';
		$where[] = 'log.user_id ' . $um . ' 0';
		$ret[6] = '*';
	}
    elseif ( preg_match_all( '/\d+/', $filter_user, $matches ) ) {

		$users = implode( ',', $matches[0] );
	    $um = empty( $q->filter_user_mode ) ? 'IN' : 'NOT IN';
	    $where[] = 'log.user_id ' . $um . ' (' . $users . ')';

	    if ( $um === 'IN' ) {
		    $ret[6] = $users;
	    }
	}

	if ( $q->filter_wp_type ) {
		$t      = absint( $q->filter_wp_type );
		$op = ($q->filter_wp_type_mode === 'GT') ? '>' : '=';
		$where[] = 'log.wp_type ' . $op . $t;
		$ret[7] = $t;
	}

	if ( $q->search_traffic ) {
		$search = stripslashes_deep( $q->search_traffic );
		if ( $search['ip'] ) {
			if ( $ip = filter_var( $search['ip'], FILTER_VALIDATE_IP ) ) {
				$where[] = 'log.ip = "' . $ip . '"';
				$ret[4] = $ip;
			}
			else {
				$where[] = 'log.ip LIKE "%' . cerber_db_real_escape( $search['ip'] ) . '%"';
			}
		}
		if ( $search['uri'] ) {
			$where[] = 'log.uri LIKE "%' . cerber_db_real_escape( $search['uri'] ) . '%"';
		}
		if ( $search['fields'] ) {
			$where[] = 'log.request_fields LIKE "%' . cerber_db_real_escape( $search['fields'] ) . '%"';
		}
		if ( $search['details'] ) {
			$where[] = 'log.request_details LIKE "%' . cerber_db_real_escape( $search['details'] ) . '%"';
		}
		if ( $search['date_from'] ) {
			if ( $stamp = strtotime( 'midnight ' . $search['date_from'] ) ) {
				$gmt_offset = get_option( 'gmt_offset' ) * 3600;
				$where[]    = 'log.stamp >= ' . ( absint( $stamp ) - $gmt_offset );
			}
		}
		if ( $search['date_to'] ) {
			if ( $stamp = 24 * 3600 + strtotime( 'midnight ' . $search['date_to'] ) ) {
				$gmt_offset = get_option( 'gmt_offset' ) * 3600;
				$where[]    = 'log.stamp <= ' . ( absint( $stamp ) - $gmt_offset );
			}
		}
		if ( ! $q->filter_errors && $search['errors'] ) {
			$where[] = 'log.php_errors LIKE "%' . cerber_db_real_escape( $search['errors'] ) . '%"';
		}
	}

	if ( $q->filter_method ) {
		$where[] = $wpdb->prepare( 'log.request_method = %s', $q->filter_method );
	}

	$activity = null;
	if ( $q->filter_activity ) {
		$activity = absint( $q->filter_activity );
	}
	if ( $q->filter_set ) {
		$activity = implode( ',', crb_get_filter_set( $q->filter_set ) );
	}
	if ( $activity ) {
		$ret[3]   = $activity;
		$where[]  = 'act.activity IN (' . $activity . ')';
		$join_act = true;
	}

	if ( $q->filter_errors ) {
		$where[] = 'http_code = 500 OR log.php_errors != ""';
	}

	// ---------------------------------------------------------------------------------

    $where = ( ! empty( $where ) ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

	// Limits, if specified
	$per_page = $args['per_page'] ?? crb_admin_get_per_page();
	$per_page = absint( $per_page );
	$ret[2]   = $per_page;

	$cols = 'log.*';
	if ( ! empty( $args['columns'] ) ) {
		$cols = '';
		foreach ( $args['columns'] as $col_name ) {
			$cols .= ',log.' . preg_replace( '/[^A-Z_\d]/i', '', $col_name );
		}
		$cols = trim( $cols, ',' );
	}

	if ( $join_act ) {
		$join    = ' JOIN ' . CERBER_LOG_TABLE . ' act ON (log.session_id = act.session_id)';
	}

	if ( $per_page ) {
		$limit = cerber_get_sql_limit( $per_page );
		//$ret[0] = 'SELECT SQL_CALC_FOUND_ROWS log.session_id,log.* FROM ' . CERBER_TRAF_TABLE . " log USE INDEX FOR ORDER BY (stamp) {$where} ORDER BY stamp DESC {$limit}";
		$ret[0] = 'SELECT log.session_id,' . $cols . ' FROM ' . CERBER_TRAF_TABLE . " log {$join} {$where} ORDER BY log.stamp DESC {$limit}";
		//$ret[0] = 'SELECT SQL_CALC_FOUND_ROWS log.*,act.activity FROM ' . CERBER_TRAF_TABLE . ' log USE INDEX FOR ORDER BY (stamp) LEFT JOIN '.CERBER_LOG_TABLE." act ON (log.session_id = act.session_id) {$where} ORDER BY log.stamp DESC {$limit}";
	}
	else {
		$ret[0] = 'SELECT log.session_id,' . $cols . ' FROM ' . CERBER_TRAF_TABLE . " log {$join} {$where} ORDER BY stamp DESC"; // "ORDER BY" is mandatory!
	}

	$ret[1] = 'SELECT COUNT(log.stamp) FROM ' . CERBER_TRAF_TABLE . " log {$join} {$where}";

	return $ret;
}

function cerber_get_wp_type( $wp_type ) {
	$types = array(
		515 => 'XML-RPC',
		520 => 'REST API'
	);

	if ( isset( $types[ $wp_type ] ) ) {
		return $types[ $wp_type ];
	}

	return '';
}

/**
 * Returns the name of a PHP error level constant (e.g., E_WARNING) based on its numeric value.
 *
 * @param int $level Numeric PHP error level.
 *
 * @return string Name of the error level, or 'Unknown (<code>)' if not recognized.
 */
function cerber_get_err_level( $level ): string {

	static $list = [
		E_ERROR             => 'E_ERROR',
		E_WARNING           => 'E_WARNING',
		E_PARSE             => 'E_PARSE',
		E_NOTICE            => 'E_NOTICE',
		E_CORE_ERROR        => 'E_CORE_ERROR',
		E_CORE_WARNING      => 'E_CORE_WARNING',
		E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
		E_USER_ERROR        => 'E_USER_ERROR',
		E_USER_WARNING      => 'E_USER_WARNING',
		E_USER_NOTICE       => 'E_USER_NOTICE',
		E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		E_DEPRECATED        => 'E_DEPRECATED',
		E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		E_ALL               => 'E_ALL',
	];

	return $list[ $level ] ?? 'Unknown (' . crb_absint( $level ) . ')';
}

/**
 * Check if admin AJAX is permitted.
 *
 */
function cerber_check_ajax_permissions( $strict = true ) {
	if ( nexus_is_valid_request() ) {
	    /*
		$nonce = crb_array_get( nexus_request_data()->get_params, 'ajax_nonce' );
		if ( ! $nonce ) {
			$nonce = nexus_request_data()->get_post_data( 'ajax_nonce' );
		}
		if ( ! wp_verify_nonce( $nonce, 'crb-ajax-admin' ) ) {
			return false;
		}
	    */

		return true;
	}

	check_ajax_referer( 'crb-ajax-admin', 'ajax_nonce' );

	if ( ! cerber_user_can_manage() ) {
		if ( $strict ) {
			wp_die( 'Oops! Access denied.' );
		}

		return 0;
	}

	return true;
}

function crb_admin_allowed_ajax( $action ) {
	$list = array(
		'cerber_ajax',
		'cerber_scan_control',
		'cerber_ref_upload',
		'cerber_view_file',
		'cerber_scan_bulk_files'
	);

	return in_array( $action, $list );
}

function crb_is_task_permitted( $die = false ) {
	if ( is_super_admin()
	     || nexus_is_valid_request() ) {
		return true;
	}
	if ( $die ) {
		wp_die( 'Oops! Access denied.' );
	}

	return false;
}

/**
 * Identify and render Cerber's admin page
 *
 * @param string $page_id
 * @param string $active_tab
 */
function cerber_render_admin_page( $page_id = '', $active_tab = '' ) {

    if ( nexus_get_context() ) {
		nexus_show_remote_page();

		return;
	}

	$error = '';

	if ( ! $page_id ) {
		$page_id = crb_admin_get_page();
	}

	if ( $page = cerber_get_admin_page_config( $page_id ) ) {
		if ( ! empty( $page['pro_page'] ) && ! lab_lab() ) {
			cerber_admin_notice( array(
				__( 'These features are available in the professional version of WP Cerber.', 'wp-cerber' ),
				__( 'Know more about all advantages at', 'wp-cerber' ) . ' <a href="https://wpcerber.com/pro/" target="_blank">https://wpcerber.com/pro/</a>'
			) );
		}

		if ( ( $tab_filter = crb_array_get( $page, 'tab_filter' ) )
		     && is_callable( $tab_filter ) ) {
			$page['tabs'] = call_user_func( $tab_filter, $page['tabs'] );
		}

		cerber_show_admin_page( $page['title'], $page['tabs'], $active_tab, $page['callback'] );

	}
	else {
		$error = 'Unknown admin page: ' . crb_escape_html( $page_id );
	}

	if ( $error ) {
		echo '<div class="crb-generic-error">ERROR: ' . $error . '</div>';
	}
}

/**
 * Returns the configuration of the currently rendering WP Cerber admin page
 *
 * @param string $page
 *
 * @return array[]|false
 */
function cerber_get_admin_page_config( $page = '' ) {
	if ( ! $page ) {
		return false;
	}

	if ( $config = crb_get_admin_ui_config( $page ) ) {
		return $config;
	}

	if ( $page == CRB_ADDON_PAGE
         && $config = CRB_Addons::get_admin_ui() ) {
		return $config;
	}

	return false;
}

/**
 * Configuration data describing UI of all WP Cerber admin pages.
 * To get UI phrases translated, must be invoked on or after the 'init' hook.
 *
 * @param string $page
 *
 * @return array[]|false
 */
function crb_get_admin_ui_config( $page = '' ) {

    // Note: Tab titles (and other phrases) are not translated if it's called before the 'init' hook

	$admin_pages = array(
		'cerber-security'  => array(
			'title'      => 'WP Cerber Security',
			'tabs'       => array(
				'dashboard'     => array( 'bxs-dashboard', __( 'Dashboard', 'wp-cerber' ) ),
				'activity'      => array( 'bx-pulse', __( 'Activity', 'wp-cerber' ) ),
				'sessions'      => array( 'bx-group', __( 'Sessions', 'wp-cerber' ) ),
				'lockouts'      => array( 'bxs-shield', __( 'Lockouts', 'wp-cerber' ) ),
				'main'          => array( 'bx-slider', __( 'Main Settings', 'wp-cerber' ) ),
				'acl'           => array( 'bx-lock', __( 'Access Lists', 'wp-cerber' ) ),
				'hardening'     => array( 'bx-shield-alt', __( 'Hardening', 'wp-cerber' ) ),
				//'users'         => array( 'bx-group', __( 'Users', 'wp-cerber' ) ),
				'notifications' => array( 'bx-bell', __( 'Notifications', 'wp-cerber' ) ),
			),
			'tab_filter' => function ( $tabs ) {
				crb_del_expired_blocks();
				$blocked = cerber_blocked_num();
				$acl = cerber_db_get_var( 'SELECT COUNT(ip) FROM ' . CERBER_ACL_TABLE . ' WHERE acl_slice = 0' );
				crb_sessions_del_expired();
				$uss = crb_sessions_get_num();
				if ( ! $uss ) {
					cerber_bg_task_add( 'crb_sessions_sync_all' );
				}
				$tabs['sessions'][1] .= ' <sup>' . $uss . '</sup>';
				$tabs['lockouts'][1] .= ' <sup>' . $blocked . '</sup>';
				$tabs['acl'][1] .= ' <sup>' . $acl . '</sup>';

				return $tabs;
			},
			'callback'   => function ( $tab ) {
				switch ( $tab ) {
					case 'acl':
						cerber_acl_form();
						break;
					case 'activity':
						cerber_show_activity();
						break;
					case 'sessions':
						crb_admin_show_sessions();
						break;
					case 'lockouts':
						cerber_show_lockouts();
						break;
					case 'help':
						cerber_show_help();
						break;
					case 'dashboard':
						cerber_show_dashboard();
						break;
					default:
						cerber_show_settings_form( $tab );
				}
			}
		),
		'cerber-recaptcha' => array(
			'title'    => __( 'Anti-spam and bot detection settings', 'wp-cerber' ),
			'tabs'     => array(
				'antispam' => array( 'bx-chip', __( 'Anti-spam engine', 'wp-cerber' ) ),
				'captcha'  => array( 'bxl-google', 'reCAPTCHA' ),
			),
			'callback' => function ( $tab ) {

				switch ( $tab ) {
					case 'captcha':
						$group = 'recaptcha';
						break;
					default:
						$group = 'antispam';
				}

				cerber_show_settings_form( $group );

			}
		),
		'cerber-traffic'   => array(
			'title'    => __( 'Traffic Inspector', 'wp-cerber' ),
			'tabs'     => array(
				'traffic'     => array( 'bx-show', __( 'Live Traffic', 'wp-cerber' ) ),
				'ti_settings' => array( 'bx-slider', __( 'Settings', 'wp-cerber' ) ),
			),
			'callback' => function ( $tab ) {
				switch ( $tab ) {
					case 'ti_settings':
						cerber_show_settings_form( 'traffic' );
						break;
					default:
						CRB_Traffic_Log_Screen::render_screen();
				}
			}
		),
		'cerber-shield'    => array(
			'title'    => __( 'Data Shield Policies', 'wp-cerber' ),
			'tabs'     => array(
				'user_shield' => array( 'bx-group', __( 'Accounts & Roles', 'wp-cerber' ) ),
				'opt_shield'  => array( 'bx-slider', __( 'Site Settings', 'wp-cerber' ) ),
			),
			'callback' => function ( $tab ) {
				cerber_show_settings_form( $tab );
			}
		),
		'cerber-users'     => array(
			'title'    => __( 'User Policies', 'wp-cerber' ),
			'tabs'     => array(
				'role_policies'   => array( 'bx-group', __( 'Role Policies', 'wp-cerber' ) ),
				'global_policies' => array( 'bx-user-detail', __( 'Global Policies', 'wp-cerber' ) ),
			),
			'callback' => function ( $tab ) {
				switch ( $tab ) {
					case 'role_policies':
						crb_admin_show_role_policies();
						break;
					case 'global_policies':
						cerber_show_settings_form( 'users' );
						break;
					default:
						cerber_show_settings_form( $tab );
				}
			}
		),
		'cerber-rules'     => array(
			'pro_page' => 1,
			'title'    => __( 'Security Rules', 'wp-cerber' ),
			'tabs'     => array(
				'geo' => array( 'bxs-world', __( 'Countries', 'wp-cerber' ) ),
			),
			'callback' => function ( $tab ) {
				switch ( $tab ) {
					case 'geo':
						crb_admin_show_geo_rules();
						break;
					default:
						crb_admin_show_geo_rules();
				}
			}
		),
		'cerber-integrity' => array(
			'title'      => __( 'Site Integrity', 'wp-cerber' ),
			'tabs'       => array(
				'scan_main'       => array( 'bx-radar', __( 'Security Scanner', 'wp-cerber' ) ),
				'scan_settings'   => array( 'bxs-slider-alt', __( 'Settings', 'wp-cerber' ) ),
				'scan_schedule'   => array( 'bx-time', __( 'Scheduling', 'wp-cerber' ) ),
				'scan_policy'     => array( 'bx-bolt', __( 'Cleaning up', 'wp-cerber' ) ),
				'scan_ignore'     => array( 'bx-hide', __( 'Ignore List', 'wp-cerber' ) ),
				'scan_quarantine' => array( 'bx-trash', __( 'Quarantine', 'wp-cerber' ) ),
				'scan_insights'   => array( 'bx-flask', __( 'Analytics', 'wp-cerber' ) ),
			),
			'tab_filter' => function ( $tabs ) {

				$numi = 0;
				if ( $list = cerber_get_set( 'ignore-list' ) ) {
					$numi = count( $list );
				}

				$numq = cerber_get_set( 'quarantined_total', null, false );
				if ( ! is_numeric( $numq ) ) {
					cerber_bg_task_add( '_crb_qr_total_sync' );
					$numq = '';
				}

				$tabs['scan_quarantine'][1] .= ' <sup id="crb-quarantined_total">' . $numq . '</sup>';
				$tabs['scan_ignore'][1] .= ' <sup id="crb-ignore_total">' . $numi . '</sup>';

				return $tabs;
			},
			'callback'   => function ( $tab ) {
				switch ( $tab ) {
					case 'scan_settings':
						cerber_show_settings_form( 'scanner' );
						break;
					case 'scan_schedule':
						cerber_show_settings_form( 'schedule' );
						break;
					case 'scan_policy':
						cerber_show_settings_form( 'policies' );
						break;
					case 'scan_quarantine':
						cerber_show_quarantine();
						break;
					case 'scan_ignore':
						cerber_show_ignore();
						break;
					case 'scan_insights':
						cerber_scan_insights();
						break;
					case 'help':
						cerber_show_help();
						break;
					default:
						cerber_show_scanner();
				}
			}
		),
		'cerber-tools'     => array(
			'title'    => __( 'Tools', 'wp-cerber' ),
			'tabs'     => array(
				//'imex'       => array( 'bx-layer', __( 'Export & Import', 'wp-cerber' ) ),
				'imex'       => array( 'bx-layer', __( 'Manage Settings', 'wp-cerber' ) ),
				'diagnostic' => array( 'bx-wrench', __( 'Diagnostic', 'wp-cerber' ) ),
				'diag-log'   => array( 'bx-bug', __( 'Diagnostic Log', 'wp-cerber' ) ),
				'change-log' => array( 'bx-collection', __( 'Changelog', 'wp-cerber' ) ),
				'license'    => array( 'bx-key', __( 'License', 'wp-cerber' ) ),
			),
			'callback' => function ( $tab ) {
				switch ( $tab ) {
					case 'diagnostic':
						cerber_show_diag_page();
						break;
					case 'license':
						cerber_show_lic();
						break;
					case 'diag-log':
						cerber_show_diag_log();
						break;
					case 'change-log':
						cerber_show_change_log();
						break;
					case 'help':
						cerber_show_help();
						break;
					default:
						if ( ! nexus_is_valid_request() ) {
							cerber_show_imex();
						}
						else {
							echo 'This admin page is not available in this mode.';
						}
				}
			}
		),
	);


	if ( ! $page ) {
		return $admin_pages;
	}

	return crb_array_get( $admin_pages, $page );
}

/**
 * Identifies and retrieves Page ID by the given Tab ID
 *
 * @param string $tab Tab ID used in the query parameters. In some cases can be the Page ID - in case of the first tab on the page.
 *
 * @return string Page ID to be used in URL query parameters. Safe in any context.
 *
 * @since 9.6.2.1
 */
function crb_determine_page( string $tab ): string {
	static $map = array();

	if ( ! $map ) {
		$pages = crb_get_admin_ui_config();

        // Create tab => page structure

		foreach ( $pages as $page_id => $config ) {
			if ( ! empty ( $config['tabs'] ) ) {
				$map = array_merge( $map, array_fill_keys( array_keys( $config['tabs'] ), $page_id ) );
			}
		}
	}

	if ( $page = $map[ $tab ] ?? '' ) {
		return $page;
	}

    // An exception when the first tab on an admin page is specified by using Page ID since pages and tabs are unique

	if ( in_array( $tab, $map ) ) {
		return $tab;
	}

	if ( $page = CRB_Addons::get_addon_page( $tab ) ) {
		return $page;
    }

	$page = '';

	if ( list( $prefix ) = explode( '_', $tab, 2 ) ) {
		if ( $prefix == 'nexus' ) {
			$page = 'cerber-nexus';
		}
	}

	return $page;
}

/**
 * Extracts specified query parameters from the request URI and returns them as object properties.
 *
 * @param array $fields List of query parameters to extract from the request.
 * @param array $values Optional. Key-value pairs to overwrite extracted parameters.
 *
 * @return object Object with properties corresponding to the speficied query parameters.
 */
function crb_admin_parse_query( array $fields, array $values = array() ): object {
	$arr = crb_get_query_params();
	$ret = array();

	foreach ( $fields as $field ) {

		$val = $values[ $field ] ?? crb_array_get( $arr, $field, false );

		if ( is_array( $val ) ) {
			$val = array_map( 'trim', $val );
		}
        elseif ( $val !== false ) {
			$val = trim( $val );
		}

		$ret[ $field ] = $val;
	}

	return (object) $ret;
}

function crb_admin_get_page() {
	if ( nexus_is_valid_request() ) {
		return nexus_request_data()->page;
	}

	return cerber_get_get( 'page', '[A-Z0-9\_\-]+' );
}

/**
 * Retrieves the ID of the curretly selected tab of the currently displaying admin page.
 * The tab is identified by $_GET['tab'].
 * If no active tab is detected and the $tabs (group of tabs) provided, returns the first one as the default
 *
 * @param array $tabs
 *
 * @return string
 */
function crb_admin_get_tab( $tabs = array() ): string {
	if ( nexus_is_valid_request() ) {
		$tab = nexus_request_data()->tab;
	}
	else {
		$tab = cerber_get_get( 'tab', '[\w\-]+' );
	}

	if ( empty( $tabs ) ) {
		return (string) $tab;
	}

	$tabs['help'] = 1; // always must be in the array

	if ( ! $tab || ! isset( $tabs[ $tab ] ) ) {
		reset( $tabs );
		$tab = key( $tabs ); // Takes first tab from the group
	}

	return (string) $tab;
}

function cerber_show_tabs( $active, $tabs = array() ) {
	echo '<h2 class="nav-tab-wrapper cerber-tabs">';

	$args = array( 'page' => crb_admin_get_page() );

	foreach ( $tabs as $tab => $data ) {
		echo '<a href="' . cerber_admin_link( $tab, $args ) . '" class="nav-tab ' . ( $tab == $active ? 'nav-tab-active' : '' ) . '"><i class="crb-icon crb-icon-' . $data[0] . '"></i> ' . $data[1] . '</a>';
	}

	echo '<a href="' . cerber_admin_link( 'help', $args ) . '" class="nav-tab ' . ( $active == 'help' ? 'nav-tab-active' : '' ) . '"><i class="crb-icon crb-icon-bx-idea"></i> ' . __( 'Help', 'wp-cerber' ) . '</a>';

	$lab = lab_indicator();
	$ro  = '';
	if ( nexus_is_valid_request() && ! nexus_is_granted( 'submit' ) ) {
		$ro = '<span style="font-weight: 600; font-size: 0.8em; color: #f00;">Read-only mode</span>';
	}

	echo '<div style="float: right;">' . $ro . ' ' . $lab . '</div>';

	echo '</h2>';
}

// Access Lists (ACL) ---------------------------------------------------------

/**
 * Adds an IP address, range, or network to the Access List.
 *
 * This function allows whitelisting or blacklisting IPs, ranges, or networks.
 *
 *  Supported formats for IP addresses (IPv4 and IPv6) with examples:
 *
 *  **IPv4 Formats:**
 *  - Single address: `192.168.5.22`
 *  - Range (hyphen-separated): `192.168.1.45 - 192.168.22.165`
 *  - CIDR notation: `192.168.128.0/20`
 *  - Wildcard notation: `192.168.77.*`, `192.168.*.*`, `192.*.*.*`
 *  - Any address: `0.0.0.0/0` or `*.*.*.*`
 *
 *  **IPv6 Formats:**
 *  - Single address: `2001:0db8:85a3:0000:0000:8a2e:0370:7334`
 *  - Range (hyphen-separated): `2001:db8::ff00:41:0 - 2001:db8::ff00:41:12ff`
 *  - CIDR notation: `2001:db8::/46`
 *  - Wildcard notation: `2001:db8::ff00:41:*`
 *  - Any address: `::/0`
 *
 * @param string      $ip        An IP address, IP range, or IP network in CIDR format.
 * @param string      $tag       'W' for allowed list, 'B' for blocked list.
 * @param string|null $comment   (Optional) A note visible to website administrators. Default: ''.
 * @param int         $acl_slice (Optional) The ID of the Access List. Use 0 for the global Access List. Default: 0.
 *
 * @return true|WP_Error Returns true on success or WP_Error on failure.
 *
 * @throws WP_Error Possible error codes:
 *                  - 'acl_wrong_ip': Invalid IP address, range, or network format.
 *                  - 'acl_duplicate': The IP address or range is already in the list.
 *                  - 'acl_db_error': A database error occurred while inserting the entry.
 */
function cerber_acl_add( $ip, $tag, $comment = '', $acl_slice = 0 ) {
	global $wpdb;

	$ip = trim( $ip );

	$acl_slice = absint( $acl_slice );
	$v6range = '';
	$ver6 = 0;

	if ( cerber_is_ipv4( $ip ) ) {
		$begin = ip2long( $ip );
		$end   = ip2long( $ip );
	}
    elseif ( cerber_is_ipv6( $ip ) ) {
		$ip = cerber_ipv6_short( $ip );
		list( $begin, $end, $v6range ) = crb_ipv6_prepare( $ip, $ip );
	    $ver6 = 1;
	}
    elseif ( ( $range = cerber_any2range( $ip ) )
	         && is_array( $range ) ) {
		$ver6    = $range['IPV6'];
		$begin   = $range['begin'];
		$end     = $range['end'];
		$v6range = $range['IPV6range'];
	}
	else {
		return new WP_Error( 'acl_wrong_ip', __( 'Incorrect IP address or IP range', 'wp-cerber' ) . ' ' . $ip );
	}

	if ( cerber_db_get_var( 'SELECT ip FROM ' . CERBER_ACL_TABLE . ' WHERE acl_slice = ' . $acl_slice . ' AND ver6 = ' . $ver6 . ' AND ip_long_begin = ' . $begin . ' AND ip_long_end = ' . $end . ' AND v6range = "' . $v6range . '" LIMIT 1' ) ) {
		return new WP_Error( 'acl_wrong_ip', __( 'The IP address you are trying to add is already in the list', 'wp-cerber' ) );
	}

	$result = $wpdb->insert( CERBER_ACL_TABLE, array(
		'ip'            => $ip,
		'ip_long_begin' => $begin,
		'ip_long_end'   => $end,
		'tag'           => $tag,
		'comments'      => $comment,
		'acl_slice'     => $acl_slice,
		'v6range'       => $v6range,
		'ver6'          => $ver6,
	), array( '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d' ) );

	if ( ! $result ) {
		return new WP_Error( 'acl_db_error', $wpdb->last_error );
	}

	crb_event_handler( 'ip_event', array(
		'e_type'   => 'acl_add',
		'ip'       => $ip,
		'tag'      => $tag,
		'slice'    => $acl_slice,
		'comments' => $comment,
	) );

	return true;
}

function cerber_add_white( $ip, $comment = '' ) {
	return cerber_acl_add( $ip, 'W', $comment );
}

function cerber_add_black( $ip, $comment = '' ) {
	return cerber_acl_add( $ip, 'B', $comment );
}

function cerber_acl_remove( $ip, $acl_slice = 0 ) {

	if ( ! is_numeric( $acl_slice ) ) {
		return false;
	}

	$acl_slice = absint( $acl_slice );
	$ip  = preg_replace( CRB_IP_NET_RANGE, ' ', $ip );

	$ret = cerber_db_query( 'DELETE FROM ' . CERBER_ACL_TABLE . ' WHERE acl_slice = ' . $acl_slice . ' AND ip = "' . $ip . '"' );

	crb_event_handler( 'ip_event', array(
		'e_type' => 'acl_remove',
		'ip'     => $ip,
		'slice'  => $acl_slice,
		'result' => $ret
	) );

	return $ret;
}

/**
 * Can a given IP be added to the blacklist
 *
 * @param $ip string A candidate to be added to the list
 * @param $list string
 *
 * @return bool True if IP can be listed
 */
function cerber_can_be_listed( $ip, $list = 'B' ) {

    if ( $list == 'B' ) {

		$admin_ip = cerber_get_remote_ip();

		if ( cerber_is_ip( $ip ) ) {
			if ( $admin_ip == cerber_ipv6_short( $ip ) ) {
				return false;
			}

			return true;
		}

		// $ip = range

		if ( crb_acl_is_white( $admin_ip ) ) {
			return true;
		}

		if ( ! $range = cerber_any2range( $ip ) ) {
			return false;
		}

		if ( cerber_is_ip_in_range( $range, $admin_ip ) ) {
			return false;
		}

		return true;

	}

	return true;
}

/**
 * Bulk action for WP_List_Table
 *
 * @return bool|array|string
 */
function cerber_get_bulk_action() {

	// GET
	if ( cerber_is_http_get() ) {
		if ( ( $ac = crb_get_query_params( 'action', '[\w\-]+' ) ) && $ac != '-1' ) {
			return $ac;
		}
		if ( ( $ac = crb_get_query_params( 'action2', '[\w\-]+' ) ) && $ac != '-1' ) {
			return $ac;
		}

		return false;
	}

	// POST
	if ( ( $ac = crb_get_post_fields( 'action', false, '[\w\-]+' ) ) && $ac != '-1' ) {
		return $ac;
	}
	if ( ( $ac = crb_get_post_fields( 'action2', false, '[\w\-]+' ) ) && $ac != '-1' ) {
		return $ac;
	}

	return false;
}

function crb_admin_cool_features() {
	return
		'<div class="crb-pro-req">' .
		__( 'These features are available in the professional version of WP Cerber.', 'wp-cerber' ) .
		'<br/><br/>' . __( 'Know more about all advantages at', 'wp-cerber' ) .
		' <a href="https://wpcerber.com/pro/" target="_blank">https://wpcerber.com/pro/</a>
        </div>';
}

/**
 * Generates a flexible box HTML DOM element using string elements from $elements
 *
 * @param string[] $elements Text of child elements
 * @param string $justify How to arrange child elements
 *
 * @return string HTML code of flexible box layout
 *
 * @since 9.6.5.12
 */
function crb_generate_html_flex( array $elements, $class = '', $justify = 'space-between' ): string {

	$ret = '<div class="' . crb_boring_escape( $class ) . '" style="display: flex; justify-content: ' . crb_boring_escape( $justify ) . ';">';
	$ret .= '<div>' . implode( '</div><div>', $elements ) . '</div>';
	$ret .= '</div>';

	return $ret;
}

/**
 * Creates a Copy To Clipboard hypertext link.
 *
 * @param string $source_class The class of the elements to copy text content from.
 * @param bool $plain If true the plain inner text will be copied without tags and processing, otherwise <br/> tags will be converted into new lines and other tags will be removed.
 *
 * @return string HTML code of the link
 *
 * @since 9.6.5.12
 */
function crb_copy_to_clipboard( string $source_class, $plain = true ): string {
	return '<a href="#" data-plain_text="' . ( $plain ? 1 : 0 ) . '" data-copy_clipboard_class="' . crb_boring_escape( $source_class ) . '" class="crb-copy-to-clipboard">' . __( 'Copy To Clipboard', 'wp-cerber' ) . '</a>';
}

/**
 * Creates an HTML link with a confirmation dialog.
 *
 * When the user clicks the link, a confirmation dialog is displayed with the specified message.
 * If the user does not confirm, the action is cancelled, and the browser does not follow the link.
 *
 * @param string $url URL of the link to follow when the user confirms the action
 * @param string $text Link text
 * @param string $msg Optional confirmation message, if not specified the default message "Are you sure?" is used.
 * @param string $class Optional class for a element
 *
 * @return string HTML element with escaped attributes
 */
function crb_confirmation_link( string $url, string $text, string $msg = '', string $class = '' ) {

	$data = $msg ? 'data-user_message="' . crb_escape_html( $msg ) . '"' : '';
	$class = $class ? crb_escape_html( $class ) : '';

	return '<a href="' . crb_escape_url( $url ) . '" class="crb-confirm-action ' . $class . '" ' . $data . '>' . $text . '</a>';
}

/**
 * @param array $args
 * @param string $setting
 *
 * @return string
 *
 * @since 8.9.6.1
 */
function crb_test_notify_link( $args = array(), $setting = null ) {
	if ( $setting
	     && ! crb_get_settings( $setting ) ) {
		return '';
	}

	if ( isset( $args['title'] ) ) {
		$title = $args['title'];
		unset( $args['title'] );
	}
    else {
	    $title = __( 'Click to send test', 'wp-cerber' );
    }

	return '<span class="crb-insetting-link">[ <a href="' .
	       cerber_admin_link_add( array_merge(
		       array(
			       'cerber_admin_do' => 'testnotify',
			       'type'            => 'lockout',
		       ), $args ) ) . '">' . $title . '</a> ]</span>';
}

// Pop-up dialogs -------------------------------------------------------------

/**
 * Creates a pop-up dialog DOM element with a button that opens the dialog.
 *
 * @param array $fields Form fields - left side
 * @param array $aside Form fields - right side, optional
 * @param array $control
 * @param array $atts HTML attributes for popup element
 * @param string $method Form method
 * @param string|null $action Form action
 *
 * @return string The HTML code of the pop-up prepared to add to the web page
 *
 * @since 8.9.5.3
 */
function crb_create_popup_form( array $fields, array $aside = array(), array $control = array(), array $atts = array(), string $method = 'get', string $action = '' ) {
	static $counter = 0;
	$counter ++; // In case of multiple dialogs on a page

	$icon = $control['icon'] ?? '';
	$label = $control['label'] ?? '';
	$type = $control['type'] ?? 'button';

	$icon_html = ( $icon ) ? '<i class="crb-icon ' . crb_escape_html( $icon ) . '"></i>' : '';
	$space = ( $icon_html && $label ) ? ' ' : '';

	if ( $type == 'button' ) {
		$opener = '<a data-popup_element_id="crb-popup-dialog-' . $counter . '" class="button button-secondary cerber-button crb-popup-dialog-open" href="#" style="margin-right: 0.5em;">' . $icon_html . $space . $label . '</a>';
	}
	else {
		$opener = $icon_html . $space . '<a data-popup_element_id="crb-popup-dialog-' . $counter . '" class="crb-popup-dialog-open" href="#">' . $label . '</a>';
	}

	if ( $aside ) {
		$class = 'crb-table-2cols';
		$td_aside = '<td><div class="crb-popup-dialog-aside">' . crb_create_dialog_form( $aside ) . '</div></td>';
        $last_row = '<td></td>';
	}
	else {
		$class = 'crb-table-1col';
		$td_aside = '';
		$last_row = '';
	}

	$html_atts = '';

	if ( $atts ) {
		foreach ( $atts as $att => $val ) {
			$html_atts .= ' ' . crb_escape_html( $att ) . '="' . crb_escape_html( $val ) . '" ';
		}
	}

	if ( $action ) {
		$form_action = $action;
	}
    else {
	    $form_action = cerber_admin_link( crb_admin_get_tab() );
	}

	$dialog = '<div id="crb-popup-dialog-' . $counter . '" class="mfp-hide crb-popup-dialog" ' . $html_atts . '>';
	$dialog .= '<form action="' . crb_escape_url( $form_action ) . '" method="' . crb_escape_html( $method ) . '">';
	$dialog .= '<table class="' . crb_escape_html( $class ) . '">';
	$dialog .= '<tr><td>' . crb_create_dialog_form( $fields ) . '</td>' . $td_aside . '</tr>';

	$dialog .= '<tr>' . $last_row . '<td class="crb-dialog-controls">
                    <input type="submit" class="button button-primary" value="' . __( 'OK', 'wp-cerber' ) . '">
                    &nbsp; <input type="button" class="button button-secondary crb-popup-dialog-close" value="' . __( 'Cancel', 'wp-cerber' ) . '">
			    </td></tr>';

	$dialog .= '</table></form></div>';

	return $dialog . $opener;
}

/**
 * Creates a basic HTML form for pop-up dialogs
 *
 * @param array $form_data List of form fields
 *
 * @return string HTML code
 *
 * @since 8.9.5.3
 */
function crb_create_dialog_form( $form_data ) {

	$form = '';

	if ( $title = $form_data['title'] ?? '' ) {
		$form .= '<h3>' . $title . '</h3>';
	}

	$form .= $form_data['html'] ?? '';

	$hidden = array_merge(
		$form_data['hidden'] ?? array(), array(
		'page'         => crb_admin_get_page(),
		'tab'          => crb_admin_get_tab(),
		'cerber_nonce' => crb_create_nonce(),
	) );

	foreach ( $hidden as $field => $value ) {

		$field = crb_escape_html( $field );

		if ( is_array( $value ) ) {
			foreach ( $value as $val ) {
				$form .= '<input type="hidden" name="' . $field . '[]" value="' . crb_escape_html( $val ) . '">' . "\n";
			}
		}
		else {
			$form .= '<input type="hidden" name="' . $field . '" value="' . crb_escape_html( $value ) . '">' . "\n";
		}
	}

	$form .= '<table class="crb-form-fields">';

	$visible = $form_data['visible'] ?? array();

	foreach ( $visible as $field => $config ) {

		$atts = '';

		foreach ( $config['atts'] as $att => $val ) {
			$atts .= ' ' . crb_escape_html( $att ) . '="' . crb_escape_html( $val ) . '" ';
		}

		$field = crb_escape_html( $field );

		$input = '<input id="' . $field . '" name="' . $field . '" value="' . crb_escape_html( $config['value'] ) . '" ' . $atts . '>';
		$label = '<label for="' . $field . '">' . $config['label'] . '</label>';

		$type = $config['atts']['type'] ?? '';

		$form .= '<tr><td>';

		if ( $type == 'checkbox' ) {
			$form .= '<div class="crb-wrap-' . $type . '"><div>' . $input .'</div><div>'. $label . '</div></div>';
		}
		else {
            $form .= $label . '<div>' . $input . '</div>';
		}

		$form .= '</td></tr>';
	}

	$form .= '</table>';

	$mess = $form_data['error_messages'] ?? array();

	foreach ( $mess as $id => $msg ) {
		$form .= '<p id="crb-message-' . crb_sanitize_id( $id ) . '" class="crb-error-text" style="display:none;">' . $msg . '</p>';
	}

	return '<div class="crb-popup-dialog-form">' . $form . '</div>';

}

add_filter( 'self_admin_url', 'crb_set_release_note_url' );

/**
 * Sets the correct URL on the Dashboard -> Updates to view the page with the last version info.
 * Fix broken link pointing to a non-existing wordpress.org repository plugin profile.
 *
 * @param string $url
 *
 * @return string
 *
 * @since 9.5.8
 */
function crb_set_release_note_url( $url ): string {
	global $pagenow;

	$add = '';
	$replace = false;

	if ( 'update-core.php' === $pagenow

	     // We are looking for: plugin-install.php?tab=plugin-information&plugin=wp-cerber&section=changelog&TB_iframe=true&width=640&height=662
	     // The URL is hard coded in /wp-admin/update-core.php

	     && strpos( $url, '/wp-admin/plugin-install.php?tab=plugin-information&plugin=wp-cerber&section=changelog&TB_iframe=true&width=640&height=662' ) ) {
		$add = '?TB_iframe=true&width=640&height=662';
        $replace = true;
	}
    elseif ( 'plugins.php' === $pagenow

	         // This URL is generated in wp_plugin_update_row()

	         && strpos( $url, '/wp-admin/plugin-install.php?tab=plugin-information&plugin=wp-cerber&section=changelog' ) ) {
	    $replace = true;
	}

	if ( ! $replace ) {
		return $url;
	}

	$plugins = get_plugin_updates();
	$new_version = '';

	if ( $plugin_data = $plugins[ CERBER_PLUGIN_ID ] ?? false ) {
		$new_version = $plugin_data->update->new_version;
	}

	if ( $new_version ) {
		$url = 'https://wpcerber.com/wp-cerber-security-' . str_replace( '.', '-', $new_version ) . '/' . $add;
	}

	return $url;
}

/**
 * @param string $icon_id
 *
 * @return string HTML code to display a font-based icon
 *
 * @since 9.6.1.3
 */
function crb_get_icon( $icon_id ) {
	return '<i class="crb-icon crb-icon-' . ( CRB_ADMIN_ICONS[ $icon_id ] ?? 'unknown-icon' ) . '"></i>';
}

// Setting up WordPress navigation menu editor ----------------------------------

add_action( 'admin_head-nav-menus.php', function () {
	add_meta_box( 'wp_cerber_nav_menu',
		'WP Cerber',
		'cerber_nav_menu_box',
		'nav-menus',
		'side',
		'low' );
} );

function cerber_nav_menu_box() {

    // Warning: do not change array indexes
	$list = array(
		'login-url'  => __( 'Log In', 'wp-cerber' ),
		'logout-url' => __( 'Log Out', 'wp-cerber' ),
		'reg-url'    => __( 'Register', 'wp-cerber' ),
	);
	if ( class_exists( 'WooCommerce' ) ) {
		$list['wc-login-url']  = __( 'WooCommerce Log In', 'wp-cerber' );
		$list['wc-logout-url'] = __( 'WooCommerce Log Out', 'wp-cerber' );
	}

	?>
    <div id="posttype-wp-cerber-nav" class="posttypediv">
        <div id="tabs-panel-wp-cerber-nav" class="tabs-panel tabs-panel-active">
            <ul id="wp-cerber-nav-checklist" class="categorychecklist form-no-clear">
				<?php
				$i = - 1;
				foreach ( $list as $key => $value ) :
					$id = 'wp-cerber-' . $key;
					?>
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox" class="menu-item-checkbox"
                                   name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-object-id]"
                                   value="<?php echo esc_attr( $i ); ?>"/> <?php echo esc_html( $value ); ?>
                        </label>
                        <input type="hidden" class="menu-item-type"
                               name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-type]" value="custom"/>
                        <input type="hidden" class="menu-item-title"
                               name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-title]"
                               value="<?php echo esc_html( $value ); ?>"/>
                        <input type="hidden" class="menu-item-url"
                               name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-url]"
                               value="<?php echo '#will-be-generated-automatically'; ?>"/>
                        <input type="hidden" class="menu-item-attr-title"
                               name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-attr-title]"
                               value="<?php echo '*MENU*CERBER*|' . $id; ?>"/>
                    </li>
					<?php
					$i --;
				endforeach;
				?>
            </ul>
        </div>
        <p class="button-controls">
            <span class="add-to-menu">
					<button type="submit" class="button-secondary submit-add-to-menu right"
                            value="<?php esc_attr_e( 'Add to menu' ); ?>" name="add-post-type-menu-item"
                            id="submit-posttype-wp-cerber-nav"><?php esc_html_e( 'Add to menu' ); ?></button>
					<span class="spinner"></span>
            </span>
        </p>
    </div>
	<?php
}

/**
 * Renders a popup form to display screen configuration dialog
 *
 * @return string HTML
 *
 * @since 9.6.4.5
 */
function crb_admin_screen_dialog() {

	$atts = array( 'type' => 'checkbox', 'data-validation_group' => 'required_min_one' );

	$widgets = CRB_Widgets::get_titles( true, false );
	$field_list = array();

	foreach ( $widgets as $widget_id => $widget ) {

		if ( CRB_Widgets::is_active( $widget_id ) ) {
			$atts['checked'] = 1;
		}
		else {
			unset( $atts['checked'] );
		}

		$field_list[ $widget_id ] = array(
			'label' => $widget[0],
			'value' => '1',
			'atts'  => $atts,
		);
	}

	$form_fields = array(
		'title'          => __( 'Select widgets to display', 'wp-cerber' ),
		'visible'        => $field_list,
		'hidden'         => array(
			'cerber_admin_do' => 'save_widget_list',
		),
		'error_messages' => array( 'required_min_one' => __( 'Please select at least one widget', 'wp-cerber' ) )
	);

	return crb_create_popup_form( $form_fields, [], [ 'icon' => 'crb-icon-bx-cog' ], [ 'data-narrow' => 1 ], 'post' );
}


add_action( 'heartbeat_tick', 'cerber_update_widget_data' );
/**
 * Refresh expiring widgets stored in the WordPress persistent object cache
 *
 * @return void
 *
 * @since 9.6.4.3
 */
function cerber_update_widget_data(  ) {
	if ( ! CRB_Cache::checker()
	     || ! is_super_admin() ) {
		return;
	}

	cerber_widgets_init();
	CRB_Widgets::update_cache();
}

/**
 * WP Cerber Dashboard Widget Manager
 *
 * Provides functionality to register, render, and manage WP Cerber dashboard widgets,
 * including user-specific settings such as widget display order.
 *
 * @since 9.6.4.2
 */
final class CRB_Widgets {

    // All widgets are here
    private static $widgets = array();

    // If a widget loaded from the cache
    static $from_cache = false;

	/**
	 * Registers a new dashboard widget.
	 *
	 * @param string $id Unique identifier for the widget.
	 * @param string $title The title of the widget displayed in the heading of the widget
	 * @param string $sub_title Subtitle displayed next to the title
	 * @param string $controls HTML content for the widget's control area, if any.
	 * @param callable $callback Callback function for rendering the widget.
	 *                           Expected to return either a string (HTML content) or an array:
	 *                           - [0]: string - Widget body HTML content.
	 *                           - [1]: bool   - Whether to display controls.
	 *                           - [2]: bool   - Whether the widget body should be loaded via AJAX.
	 * @param array $cache_config If specified and the WordPress persistent object cache is available, the content of the widget will be cached according to this configuration.
	 *                                 - [0] string: Cache key for the data source.
	 *                                 - [1] int: Allowed lag behind the data source for the cached data, in seconds.
	 *                                 Default is 60 seconds.
	 * @param bool $no_header Hide heading of the widget
	 *
	 * @return void
	 */
	public static function register( string $id, string $title, string $sub_title, string $controls, callable $callback, array $cache_config = array(), $no_header = false ) {
		self::$widgets[ crb_sanitize_id( $id ) ] = array( $title, $controls, $callback, $cache_config, 'sub_title' => $sub_title, 'no_head' => $no_header );
	}

	/**
	 * Returns a list of all registered widget IDs and their titles.
	 *
	 * @param bool $sort Optional. Whether to sort the widgets based on the saved user order of widgets.
	 * @param bool $active_only Optional. Whether to filter out the widgets based on the saved user list of active widgets.
	 *
	 * @return array An associative array where the keys are widget IDs and the values are widget titles.
	 */
	public static function get_titles( bool $sort = false, bool $active_only = true ): array {
		if ( ! self::$widgets ) {
			return array();
		}

		$result = array();

		foreach ( self::$widgets as $id => $widget ) {
			$result[ $id ] = array( $widget[0], $widget['sub_title'] );
		}

		if ( $active_only
		     && $list = self::get_active_list() ) {
			$result = array_intersect_key( $result, array_filter( $list ) );
		}

		if ( $sort
		     && ( $order = self::get_screen_parameter( 'widget_order' ) )
		     && ! crb_is_wp_error( $order ) ) {

			$order = array_filter( $order );
			$order_indices = array_flip( $order );

			uksort( $result, function ( $a, $b ) use ( $order_indices ) {
				return ( $order_indices[ $a ] ?? 0 ) <=> ( $order_indices[ $b ] ?? 0 );
			} );
		}

		return $result;
	}

	/**
	 * Returns the HTML content for a widget's control area.
	 *
	 * @param string $widget_id The ID of the widget.
	 *
	 * @return string The HTML content of the widget controls, or an empty string if no controls are defined.
	 */
	public static function get_controls( string $widget_id ): string {
		return self::$widgets[ $widget_id ][1] ?? '';
	}

	/**
     * Determines whether the widget header will be shown or hidden
     * when displaying the given widget on admin pages
     *
	 * @param string $widget_id
	 *
	 * @return bool If true, the widget header must be hidden
	 */
	public static function hide_header( string $widget_id ): bool {
		return ! empty( self::$widgets[ $widget_id ]['no_head'] );
	}

	/**
	 * Renders a dashboard widget by its ID.
	 *
	 * @param string $widget_id The ID of the widget to render.
	 * @param bool   $is_ajax   Optional. Whether the rendering is triggered in an AJAX context. Default false.
	 *
	 * @return string|array|WP_Error The rendered widget content as a string or an array,
	 *                               or a WP_Error object if the widget cannot be rendered.
	 */
	public static function render_widget( string $widget_id, bool $is_ajax = false ) {
		if ( ! $widget = self::$widgets[ $widget_id ] ?? false ) {
			return new WP_Error( 'cerber_widget_not_found', 'Widget not found:' . $widget_id );
		}

		if ( $cached = self::get_cache( $widget_id ) ) {
            self::$from_cache = true;

			return $cached;
		}

		self::$from_cache = false;

		$callback = $widget[2];

		if ( ! is_callable( $callback ) ) {
			return new WP_Error( 'cerber_not_callable', 'Widget callback is not callable (Widget ID ' . $widget_id . ').' );
		}

		try {
			$result = call_user_func( $callback, $is_ajax );
		}
		catch ( Exception $e ) {
			return new WP_Error(
				'cerber_callback_error',
				'An exception occurred during widget callback execution (Widget ID ' . $widget_id . '). ERROR: ' . $e->getMessage(),
				array( 'exception' => $e->getMessage() )
			);
		}

		if ( crb_is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_array( $result )
		     && $no_data = $result['no_data'] ?? false ) {
            // Exception: there is nothing to display
			return array( '<div class="crb-dash-padding crb-dash-placeholder">' . $no_data . '</div>', false );
		}

		self::set_cache( $widget_id, $result );

		if ( $is_ajax
		     || ! is_array( $result ) ) {
			return $result;
		}

        // Check if AJAX loading is required - based on the returned value from the callback

		$ajax = $result[2] ?? false;

		if ( ! $ajax ) {
			return $result;
		}

		return self::get_ajax_area( $widget_id );
	}

	/**
	 * Retrieves a widget's content from the WordPress persistent object cache.
     * Note: If no persistent object cache is available, the content will be lost between HTTP requests.
	 *
	 * @param string $widget_id The ID of the widget.
	 *
	 * @return mixed|false The cached widget content if available and valid. Returns false if:
	 *                     - The cache is unavailable.
	 *                     - The cache has expired.
	 *                     - The widget does not have a valid cache configuration.
	 */
	private static function get_cache( string $widget_id ) {
		list ( $source_key, $lag ) = self::get_cache_params( $widget_id );

		if ( ! $source_key
		     || ! ( $source = cerber_cache_get( $source_key, false ) )
		     || ! ( $modified = $source['data_modified'] ?? false )
		     || ! ( $cached = cerber_cache_get( 'dash_widget_' . $widget_id, false ) )
		     || empty( $cached['widget'] ) ) {

			return false;
		}

		$saved = $cached['saved'];

		// Check if the cache has expired
		if ( ( $saved + $lag ) < $modified ) {
            return false;
        }

		// Check if the cache is stale
		if ( $saved < $modified
		     && $saved < ( time() - 600 ) ) {
			return false;
		}

		return $cached['widget'];
	}


	/**
	 * Saves a widget's rendered content to the WordPress persistent object cache.
	 * Note: If no persistent object cache is available, the content will be lost between HTTP requests.
	 *
	 * @param string       $widget_id The unique identifier of the widget.
	 * @param array|string $contents  The content of the widget to be cached.
	 *
	 * @return bool True if the cache entry was successfully saved, false otherwise.
	 *
	 */
	private static function set_cache( string $widget_id, $contents ) {
		list ( $source_key, $lag ) = self::get_cache_params( $widget_id );

		if ( ! $source_key ) {
			return false;
		}

		return cerber_cache_set( 'dash_widget_' . $widget_id, array( 'widget' => $contents, 'saved' => time(), 'lag' => $lag ) );
	}

	/**
	 * Purges all cached widgets
	 *
	 * @param string $widget_id
	 *
	 * @return void
	 */
	public static function purge_cache( string $widget_id = '' ) {
		if ( $widget_id ) {
			cerber_cache_set( 'dash_widget_' . $widget_id, array( 'purged' => time() ) );

			return;
		}

		foreach ( array_keys( self::$widgets ) as $widget_id ) {
			cerber_cache_set( 'dash_widget_' . $widget_id, array( 'purged' => time() ) );
		}
	}

	/**
     * Returns cache parameters if specified for a widget. Parameters are defined when registering widgets.
     *
	 * @param string $widget_id Widget ID.
	 *
	 * @return array Contains 1) key to get the last modification time of the data source and 2) Allowed lag behind the data source
	 */
	private static function get_cache_params( string $widget_id ): array {
		$key = self::$widgets[ $widget_id ][3][0] ?? '';
		$lag = self::$widgets[ $widget_id ][3][1] ?? 120; // Default value is 2 minutes

		return array( $key, $lag );
	}

	/**
     * Forcefully update widget cache elements that will expire soon
     *
	 * @return void
	 */
	public static function update_cache() {
		if ( ! self::$widgets
		     || ! CRB_Cache::checker()
		     || ! is_super_admin() ) {
			return;
		}

		foreach ( array_keys( self::$widgets ) as $widget_id ) {
			list ( $source_key, $lag ) = self::get_cache_params( $widget_id );

			if ( ! $source_key ) { // Meaning cache not in use for this widget
				continue;
			}

            // Do we have valid data source modification time?

			if ( ! ( $source = cerber_cache_get( $source_key, false ) )
			     || ! ( $modified = $source['data_modified'] ?? false ) ) {
				continue;
			}

            // Try to get widget from cache

			if ( ! ( $cached = cerber_cache_get( 'dash_widget_' . $widget_id, false ) )
			     || ! ( $saved = $cached['saved'] ?? false ) ) {

                self::render_widget( $widget_id );
				continue;
			}

			// If cache will expire soon (less than in 30 sec) we update it preliminary

			if ( ( $saved + $lag - 30 ) < $modified ) {
				self::purge_cache( $widget_id );
				self::render_widget( $widget_id );
			}
		}
	}

	/**
     * Updates the list of active widgets for the current user on a specific admin screen.
     *
	 * @param array $post_fields Array containing $_POST fields that represent enabled widgets as array keys
	 * @param string $screen
	 *
	 * @return true|WP_Error
	 */
	public static function save_list( $post_fields, string $screen = 'main' ) {
		if ( empty( self::$widgets ) ) {
			return new WP_Error( 'cerber_no_widgets', 'No widgets are registered yet. Did you forget to call ' . __CLASS__ . '::register();?' );
		}

        // Make sure we're saving existing widget IDs only

		$widgets = array_fill_keys( array_keys( self::$widgets ), 0 );
		$list = array_merge( $widgets, array_intersect_key( $post_fields, $widgets ) );

        // Sanitize values

		$list = array_map( function ( $val ) {
			return absint( $val );
		}, $list );

		return self::save_screen_parameter( 'widget_list', $list, $screen );
	}

	/**
	 * Returns the list of active widgets for the current user on a specific admin screen.
	 *
	 * @param string $screen
	 *
	 * @return array The list of active widgets, including those that were registered after the list was saved.
	 */
	public static function get_active_list( string $screen = 'main' ) {
		if ( empty( self::$widgets ) ) {
			return array();
		}

		$list = self::get_screen_parameter( 'widget_list', $screen );

		if ( crb_is_wp_error( $list ) ) {
			return self::$widgets;
		}

		$disabled = array_filter( $list, function ( $value ) {
			return empty( $value );
		} );

		return array_diff_key( self::$widgets, $disabled );
	}

	/**
	 * Check if the widget is active for the current user on a specific admin screen.
	 *
	 * @param string $widget_id The ID of the widget to check.
	 * @param string $screen The target screen ID.
	 *
	 * @return bool True if the widget is active.
	 */
	public static function is_active( string $widget_id, string $screen = 'main' ) {
		$list = self::get_active_list( $screen );

		return isset( $list[ $widget_id ] );
	}

	/**
	 * Updates the display order of widgets for the current user on a specific admin screen.
	 *
	 * @param array  $order  An array of widget IDs in the desired display order.
	 * @param string $screen Optional. The admin screen ID where the order applies. Default 'main'.
	 *
	 * @return true|WP_Error True on success, or a WP_Error object on failure.
	 */
	public static function save_order( array $order, string $screen = 'main' ) {

		$order = array_filter( $order );

		return self::save_screen_parameter( 'widget_order', $order, $screen );
	}

	/**
     * Save a specific configuration parameter for the current user and a given screen (admin page)
     *
	 * @param string $key The screen meta key to save/retrieve the parameter.
	 * @param array $value The parameter value.
	 * @param string $screen Optional. The admin screen ID where the $value saved for. Default 'main'.
	 *
	 * @return true|WP_Error True on success, or a WP_Error object on failure.
     */
	private static function save_screen_parameter( string $key, array $value, string $screen = 'main' ) {
		if ( ! $user_id = get_current_user_id() ) {
			return new WP_Error( 'cerber_non_user', 'User is not authenticated.' );
		}

		$meta = get_user_meta( $user_id, 'cerber_dashboard_config', true );

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$meta[ $screen ][$key] = $value;

		if ( ! update_user_meta( $user_id, 'cerber_dashboard_config', $meta ) ) {
			return new WP_Error( 'cerber_not_updated', 'User meta not updated. Possibly duplicate value.' );
		}

		return true;
	}

	/**
     * Retrieve a configuration parameter specified by $key for the current user and a given screen (admin page)
     *
	 * @param string $key The screen meta key to save/retrieve the parameter
	 * @param string $screen Optional. The admin screen ID where the $value saved for. Default 'main'.
	 *
	 * @return array|WP_Error The parameter value, or a WP_Error object on failure.
	 */
	public static function get_screen_parameter( string $key, string $screen = 'main' ) {
		if ( ! $user_id = get_current_user_id() ) {
			return new WP_Error( 'cerber_non_user', 'User is not authenticated.' );
		}

		if ( ( $meta = get_user_meta( $user_id, 'cerber_dashboard_config', true ) )
		     && ( $value = $meta[ $screen ][ $key ] ?? false )
		     && is_array( $value ) ) {

			return $value;
		}

		return array();
	}

	/**
	 * Generates an HTML skeleton loader for a table-like structure.
	 *
	 * @param int $rows Optional. The number of rows to generate.
	 * @param int $cols Optional. The number of columns to generate.
	 *
	 * @return string The generated HTML for the skeleton loader.
	 */
	static function get_skeleton( int $rows = 5, int $cols = 5 ): string {

		$html = '<div class="crb-skeleton-table">';

		for ( $i = 0; $i < $rows; $i ++ ) {
			$html .= '<div class="crb-skeleton-row">';

			$html .= str_repeat( '<div class="crb-skeleton-cell"></div>', $cols );

			$html .= '</div>' . PHP_EOL;
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generates an HTML container for asynchronously loading a widget via AJAX.
	 *
	 * @param string $widget_id The unique ID of the widget to load via AJAX.
	 *
	 * @return string The generated HTML container with AJAX-related attributes.
	 */
	static function get_ajax_area( string $widget_id ): string {

		return '<div class="crb_async_content" data-ajax_route="dashboard_analytics" data-ds_widget="' . crb_escape_html( $widget_id ) . '">' . self::get_skeleton() . '</div>';

	}
}
