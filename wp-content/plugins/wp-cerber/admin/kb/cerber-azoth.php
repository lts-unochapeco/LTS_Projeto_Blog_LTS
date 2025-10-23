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

final class CRB_Explainer {
	private static $counter = 0;
	private static $user_id = 0;
	private static $activity;
	private static $closing_html = '';
	private static $ip = '';

	/* Prevents duplicates when including settings in the explainer */

	private static $done_sts = array();

	/* Prevents duplicates when including links to the logs in the explainer */

	private static $done_links = array();

	/**
	 * Generates UI HTML elements for displaying extended information on event as a popup
	 *
	 * @param int $activity Activity ID
	 * @param int $status Status ID
	 * @param int $user_id User ID
	 * @param string $settings Comma-separated list of settings from the log entry
	 * @param string $ip IP address
	 * @param string $control Link text to open the popup
	 * @param string $closing_html To be displayed bellow the explainer text, no block-level HTML tags are allowed
	 * @param string $footer To be displayed at the bottom of the explainer element
	 *
	 * @return string  Sanitized HTML
	 *
	 * @since 9.6.1.3
	 */
	static function create_popup( int $activity, int $status, int $user_id, string $settings, string $ip = '', string $control = '', string $closing_html = '', string $footer = '' ): string {

		$set_list = $settings ? explode( ',', $settings ) : array();

		self::$activity = $activity;
		self::$closing_html = $closing_html;
		self::$ip = $ip;

		if ( ! $explainer = self::create( $activity, $status, $user_id, $set_list ) ) {
			return '';
		}

		self::$counter++;
		$dom_id = 'crb-expl-' . self::$counter;

		CRB_Globals::to_admin_footer( '<div class="crb-popup-dialog mfp-hide" id="' . $dom_id . '">' . $explainer . $footer . '</div>' );

		if ( ! $control ) {
			$control = '<svg height="2em" viewBox="-5 -5 28 28" preserveAspectRatio="xMidYMid meet" focusable="false"><path d="M9 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 5a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 5a2 2 0 1 1 0-4 2 2 0 0 1 0 4z" fill-rule="evenodd"></path></svg>';
		}

		return '<div class="crb-act-context-menu"><a href="#" data-popup_element_id="' . $dom_id . '" class="crb-popup-dialog-open">' . $control . '</a></div>';
	}

	/**
	 * Generates a KB explainer for an event
	 *
	 * @param int $activity Activity ID
	 * @param int $status Status ID
	 * @param int $user_id User ID
	 * @param array $set_list List of settings from the log entry
	 *
	 * @return string Sanitized HTML
	 *
	 * @since 9.6.1.3
	 */
	static function create( $activity, $status = 0, $user_id = 0, $set_list = array() ) {
		self::$done_links = array();
		self::$done_sts = array();
		self::$user_id = $user_id;

		$explainer = array();

		// IP Blocked

		if ( $activity == 10 || $activity == 11 ) {
			if ( ( $title = cerber_get_reason( $status, '' ) )
			     && $texts = self::make_explainer( 'reason', $status, $set_list ) ) {
				$explainer[] = array( $title, $texts );
			}
		}
		else {

			if ( ( $title = cerber_get_labels( 'activity', $activity ) )
			     && $texts = self::make_explainer( 'activity', $activity, $set_list ) ) {
				$explainer[] = array( $title, $texts );
			}

			if ( $status > 0
			     && $status != 520
			     && ( $title = cerber_get_labels( 'status', $status ) )
			     && $texts = self::make_explainer( 'status', $status, $set_list ) ) {
				$explainer[] = array( $title, $texts );
			}
		}

		if ( $explainer ) {

			$html = '';

			foreach ( $explainer as $section ) {

				$html .= '<h4>' . $section[0] . '</h4>';

				foreach ( $section[1] as $class => $sec_items ) {

					$html .= '<div class="crb-kb-' . $class . '">';

					foreach ( $sec_items as $item ) {
						$html .= '<p class="' . ( $item[1] ?? '' ) . '">' . $item[0] . '</p>';
					}

					$html .= '</div>';
				}
			}

			return '<div class="crb-explainer">' . $html . '</div>';
		}

		return '';
	}

	/**
	 * Builds and returns an array holding the explainer
	 *
	 * @param string $type Type of the event
	 * @param string|int $id ID of the event
	 * @param array $set_list Settings from the log entry
	 *
	 * @return array Elements of the explainer. HTML is filtered and escaped.
	 *
	 * @since 9.6.1.3
	 */
	static function make_explainer( $type, $id, $set_list ) {

		$setting_expl = array();

		// Check for a "master" setting that guided WP Cerber

		$final = ( $set_list ) ? array_pop( $set_list ) : '';

		if ( $final
		     && ! isset( self::$done_sts[ $final ] )
		     && $st_desc = self::get_setting_desc( $final ) ) {

			$setting_expl[] = array( crb_get_icon( 'settings' ) . __( 'WP Cerber processed this request according to this setting', 'wp-cerber' ), 'crb-kb-setting-intro' );
			$setting_expl[] = array( $st_desc );
			self::$done_sts[ $final ] = 1;
		}
		else {
			$st_desc = '';
		}

		if ( self::$user_id ) {
			$act_link = self::get_user_log_link();
		}
		else {
			$act_link = self::get_ip_log_link();
		}

		// Now build the explainer

		$expl = array();

		if ( $kb_entry = self::get_kb_data( $type, $id ) ) {

			// Text

			if ( $kb_entry['explainer'] ) {
				$expl['kb_explain'] = array( array( crb_strip_tags( $kb_entry['explainer'] ) ) );
			}

			if ( $kb_entry['action'] ) {
				$expl['kb_action'] = array( array( crb_strip_tags( $kb_entry['action'] ) ) );
			}

			if ( self::$closing_html ) {
				$expl['kb_closing'] = array( array( self::$closing_html ) );
			}

			if ( $act_link ) {
				$expl['kb_show_log'] = array( array( $act_link ) );
			}

			// Settings if any

			if ( $list = $kb_entry['sts_list'] ?? false ) {
				if ( $st_desc ) {
					unset( $list[ $final ] );
				}

				$list = array_diff_key( $list, self::$done_sts );

				if ( ! empty( $list ) ) {
					$setting_expl[] = array( crb_get_icon( 'settings' ) . ( ( $st_desc ) ? __( 'Other related WP Cerber settings', 'wp-cerber' ) : __( 'Settings that control behavior of WP Cerber', 'wp-cerber' ) ), 'crb-kb-setting-intro' );

					foreach ( $list as $st_id => $st_desc ) {
						$setting_expl[] = array( $st_desc );
						self::$done_sts[ $st_id ] = 1;
					}
				}
			}

			if ( $setting_expl ) {
				$expl['kb_settings'] = $setting_expl;
			}

			// Documentation links

			if ( $link = $kb_entry['doc_link'] ?? '' ) {
				$link = crb_escape_url( $link );
				$expl['kb_link'] = array( array( crb_get_icon( 'know_more' ) . '<a href="' . $link . '" target="_blank">' . $link . '</a>' ) );
			}

		}
		else {
			if ( $setting_expl ) {

				$expl['kb_settings'] = $setting_expl;

				if ( $act_link ) {
					$expl['kb_show_log'] = array( array( $act_link ) );
				}
			}
		}

		return $expl;
	}

	/**
	 * Prepares KB entry for use to build an explainer
	 *
	 * @param string $type Type of the event
	 * @param int|string $id ID of the event
	 *
	 * @return array|false Returns false if no KB entry found
	 *
	 * @since 9.6.1.3
	 */
	static function get_kb_data( $type, $id ) {

		if ( ! $kb = CRB_Wisdom::get( $type, $id ) ) {
			return false;
		}

		$desc = $kb['kb_desc'] ?? '';

		$action = $kb['kb_action'] ?? '';

		$settings = array();

		if ( $kb['kb_sts'] ?? false ) {

			foreach ( $kb['kb_sts'] as $sts ) {
				if ( ( $setting = cerber_settings_config( array( 'setting' => $sts ) ) )
				     && $title = $setting['title'] ?? '' ) {

					$settings[ $sts ] = array( $title, $setting['page_tab_id'] );
				}

				$settings[ $sts ] = self::get_setting_desc( $sts );
			}
		}

		if ( $kb['kb_url'] ) {
			$doc_link = crb_escape_url( $kb['kb_url'] );
		}
		else {
			$doc_link = '';
		}

		if ( $desc || $settings || $doc_link ) {
			return array( 'explainer' => $desc,  'action' => $action, 'sts_list' => $settings, 'doc_link' => $doc_link );
		}

		return false;
	}

	/**
	 * Returns settings title and link to the admin page where it's located
	 *
	 * @param string $setting_id WP Cerber setting ID
	 *
	 * @return string Safe HTML to be used on a web page.
	 *
	 * @since 9.6.1.3
	 */
	static function get_setting_desc( $setting_id ) {

		$title = '';
		$tab = '';
		$bm = '';

		if ( $setting = cerber_settings_config( array( 'setting' => $setting_id ) ) ) {
			if ( $title = $setting['title'] ?? '' ) {
				$tab = $setting['page_tab_id'];
				$bm = '#' . CRB_SETTING_PREFIX . 'global-' . $setting_id;
			}
		}
		elseif ( $setting = crb_admin_role_config( $setting_id ) ) {
			if ( $title = $setting['title'] ?? '' ) {
				$tab = 'role_policies';

				if ( ( $user = crb_get_userdata( self::$user_id ) )
				     && $role = crb_sanitize_id( array_shift( $user->roles ) ) ) { // Currently, we do not support multiple roles
					$bm = '#' . $role;
				}
			}
		}

		if ( $title ) {
			return $title . ' [&nbsp;<a href="' . crb_admin_link_for_html( $tab ) . $bm . '" target="_blank">' . __( 'Manage', 'wp-cerber' ) . '</a>&nbsp;]';
		}

		return '';
	}

	/**
	 * Returns a link to the IP activity log if applicable, empty string otherwise
	 *
	 * @return string
	 */
	static function get_ip_log_link(): string {
		if ( ! self::$ip
		     || ( self::$done_links[ self::$ip ] ?? false ) ) {
			return '';
		}

		self::$done_links[ self::$ip ] = 1;

		$normal = true;

		if ( self::$activity ) {
			$in = crb_get_activity_set( 'suspicious' );
			if ( in_array( self::$activity, $in ) ) {
				$normal = false;
			}
		}

		$args = array( 'filter_ip' => self::$ip );

		if ( ! $normal ) {
			$args['filter_set'] = 1;
			$txt = __( 'View the log of suspicious and malicious activity from this IP address', 'wp-cerber' );
		}
		else {
			$txt = __( 'View all activity from this IP address', 'wp-cerber' );
		}

		return crb_get_icon( 'activity' ) . '<a href="' . crb_admin_link_for_html( 'activity', $args ) . '">' . $txt . '</a>';
	}

	/**
	 * Returns a link to the user activity log if applicable, empty string otherwise
	 *
	 * @return string
	 */
	static function get_user_log_link(): string {
		if ( ! self::$user_id
		     || ( self::$done_links[ self::$user_id ] ?? false ) ) {
			return '';
		}

		self::$done_links[ self::$user_id ] = 1;

		return crb_get_icon( 'activity' ) . '<a href="' . crb_admin_link_for_html( 'activity', [
				'filter_user'  => self::$user_id
			] ) . '">' . __( 'View recent activity and security-related events for this user', 'wp-cerber' ) . '</a>';
	}
}

final class CRB_Wisdom {
	static $kb = array();
	static $ready = false;

	/**
	 * Returns RAW, unescaped data from the KB
	 *
	 * @param string $type
	 * @param string|int $id
	 *
	 * @return array
	 *
	 * @since 9.6.1.3
	 */
	static function get( $type, $id, $default = false ) {
		if ( ! self::$ready ) {
			self::load();
		}

		return crb_array_get( self::$kb, array( $type, $id ), $default );
	}

	/**
	 * Loading KB to a variable and to the object cache
	 *
	 * @return void
	 *
	 * @since 9.6.1.3
	 */
	static function load() {
		self::$ready = true;
		self::$kb = array();
		$locale = self::determine_locale();

		if ( self::$kb = cerber_cache_get( 'azoth_data' . $locale ) ) {
			return;
		}

		$data = cerber_get_set( 'azoth_loaded' . $locale );

		if ( ! $data
		     && ! $data = self::load_local_file() ) {
			return;
		}

		self::$kb = $data['azoth'];

		cerber_cache_set( 'azoth_data' . $locale, self::$kb, 8 * 3600 );
	}

	/**
	 * Loading KB data from the bundled KB file
	 *
	 * @return false|array
	 *
	 * @since 9.6.1.3
	 */
	static function load_local_file() {

		$kb_file = __DIR__ . '/data/azoth_data.json';

		if ( ! file_exists( $kb_file )
		     || ! $json_text = file_get_contents( $kb_file ) ) {
			return false;
		}

		$data = json_decode( $json_text, true );

		if ( ! $data
		     || JSON_ERROR_NONE != json_last_error()
		     || empty( $data['azoth'] )
		     || empty( $data['kb_updated'] ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Loading updates to KB from the official WP Cerber website
	 *
	 * @param int $user_id User to determine the locale (translation) of KB files
	 *
	 * @return array|void|WP_Error
	 *
	 * @since 9.6.1.3
	 */
	static function load_remote_file( $user_id = 0 ) {

		$user_locale = self::determine_locale( $user_id );

		$loaded = crb_get_remote_json( 'https://downloads.wpcerber.com/azoth/azoth_data' . $user_locale . '.json' );

		if ( crb_is_wp_error( $loaded ) ) {

			return $loaded;
		}

		if ( empty( $loaded['azoth'] )
		     || empty( $loaded['kb_updated'] ) ) {

			return;
		}

		if ( ( $local_file = self::load_local_file() )
		     && $local_file['kb_updated'] > $loaded['kb_updated'] ) {

			// If we have a previously saved KB data, it's outdated now

			cerber_update_set( 'azoth_loaded' . $user_locale, array() );

			return;
		}

		if ( ( $stored = cerber_get_set( 'azoth_loaded' . $user_locale ) )
		     && ( $stored['kb_updated'] >= $loaded['kb_updated'] ) ) {

			// No update needed

			return;
		}

		// There is an update to KB, let's store it

		cerber_update_set( 'azoth_loaded' . $user_locale, $loaded );

		return $loaded;
	}

	/**
	 * Schedule checking for possible updates to KB.
	 *
	 * @return void
	 *
	 * @since 9.6.1.3
	 */
	static function schedule_updating() {

		$user_locale = self::determine_locale();

		if ( get_site_transient( 'cerber_update_kb' . $user_locale ) ) {
			return;
		}

		cerber_bg_task_add( array( 'CRB_Wisdom', 'load_remote_file' ), array( 'load_admin' => 1, 'args' => array( get_current_user_id() ) ) );

		set_site_transient( 'cerber_update_kb' . $user_locale, time(), 24 * 3600 );
	}

	/**
	 * Returns the user locale if it's non-English.
	 * Returns an empty string for any English-based locale.
	 *
	 * @param int $user_id  User to determine the locale (translation). Defaults to the current user.
	 *
	 * @return string
	 *
	 * @since 9.6.1.3
	 */
	static function determine_locale( $user_id = 0 ): string {
		static $user_locale;

		if ( ! $user_locale ) {
			$user_locale = crb_sanitize_id( crb_get_admin_locale( (int) $user_id ) );
			$user_locale = ( 0 === strpos( $user_locale, 'en_' ) ) ? '' : '_' . $user_locale;
		}

		return $user_locale;
	}

	/**
	 * Clears all cached data.
	 * Typically, we use it when we need fresh data loaded from the disk or, if it's missing, from WP Cerber's cloud.
	 *
	 * @param bool $remote If true, forces to reload data from the cloud instantly. Should be used on installing a new version of WP Cerber.
	 *
	 * @return void
	 *
	 * @since 9.6.5.14
	 */
	static function clear_cache( $remote = false ) {
		$user_locale = self::determine_locale();

		cerber_cache_delete( 'azoth_data' . $user_locale );
		cerber_delete_set( 'azoth_loaded' . $user_locale );

		if ( $remote ) {
			delete_site_transient( 'cerber_update_kb'.$user_locale );
		}
		else {
			set_site_transient( 'cerber_update_kb'.$user_locale, time(), 120 );
		}
	}
}