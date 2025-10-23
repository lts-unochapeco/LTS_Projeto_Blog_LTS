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

// Note: these constant names may not be changed ever

const CRB_ADDON_PAGE = 'cerber-addons';
const CRB_ADDON_SIGN = '_addon';
const CRB_BOOT_ADDONS = 'boot_cerber_addons';



// WP Cerber add-ons API -----------------------------------------------------------------

/**
 * @param string $file Add-on PHP file to be loaded after WP Cerber has loaded itself
 * @param string $addon_id Add-on slug (unique add-on ID)
 * @param string $name Name of the add-on show in the admin UI
 * @param string $requires Optional version of WP Cerber required by the add-on
 * @param callable $settings Optional configuration of the add-on setting fields: a callback function returning the settings fields since 9.6.2.3
 * @param callable $cb Optional callback function invoked when a website admin saves add-on settings.
 *
 * @return bool
 */
function cerber_register_addon( $file, $addon_id, $name, $requires = '', $settings = null, $cb = null ) {

	return CRB_Addons::register_addon( $file, $addon_id, $name, $requires, $settings, $cb );
}

/**
 * @param string $event
 * @param callable $callback
 * @param string $addon_id
 *
 * @return bool
 */
function cerber_add_handler( $event, $callback, $addon_id = null ) {

	return CRB_Events::add_handler( $event, $callback, $addon_id );
}

/**
 * Returns add-on settings
 *
 * @param string $addon_id
 * @param string $setting
 * @param bool $purge_cache
 *
 * @return array|bool|mixed
 *
 * @since 9.3.4
 */
function cerber_get_addon_settings( $addon_id = '', $setting = '', $purge_cache = false ) {
	$all = crb_get_settings( CRB_ADDON_STS, $purge_cache );

	if ( ! $addon_id ) {
		return $all;
	}

	$ret = crb_array_get( $all, $addon_id, false );

	if ( ! $ret || ! $setting ) {
		return $ret;
	}

	return crb_array_get( $ret, $setting, false );
}

// END of WP Cerber's add-ons API ----------------------------------------------------------




cerber_add_handler( 'update_settings', function ( $data ) {
	crb_x_update_add_on_list();
} );

/**
 * Creates and updates a list of files to be booted
 *
 */
add_action( 'activated_plugin', 'crb_update_add_on_list' );
function crb_update_add_on_list() {
	if ( ! $addons = CRB_Addons::get_all() ) {
		cerber_update_set( CRB_BOOT_ADDONS, array() );

		return;
	}

	$boot = array();

	foreach ( CRB_Events::get_addons() as $event => $listeners ) {
		$to_boot        = array_intersect_key( $addons, array_flip( $listeners ) );
		$boot[ $event ] = array_column( $to_boot, 'file' );
	}

	cerber_update_set( CRB_BOOT_ADDONS, $boot );
}

add_action( 'deactivated_plugin', 'crb_x_update_add_on_list' );

/**
 * Postponed refreshing. This combination is used when it's not possible to correctly refresh
 * the list during the current request.
 *
 */
function crb_x_update_add_on_list() {
	if ( ! defined( 'CRB_POSTPONE_REFRESH' ) ) {
		define( 'CRB_POSTPONE_REFRESH', 1 );
	}

	cerber_update_set( 'refresh_add_on_list', 1, null, false );
}
register_shutdown_function( function () {
	if ( ! defined( 'CRB_POSTPONE_REFRESH' )
	     && cerber_get_set( 'refresh_add_on_list', null, false ) ) {

		crb_update_add_on_list();
		cerber_update_set( 'refresh_add_on_list', 0, null, false );
	}
} );

final class CRB_Events {
	private static $handlers = array();
	private static $addons = array();
	private static $addon_files = null;
	private static $loaded = array();
	/**
	 * Register a handler for an event
	 *
	 * @param string $event
	 * @param callable $callback
	 * @param string $addon_id
	 *
	 * @return bool
	 */
	static function add_handler( $event, $callback, $addon_id = null ) {

		if ( $addon_id && ! CRB_Addons::is_registered( $addon_id ) ) {
			return false;
		}

		self::$handlers[ $event ][] = $callback;

		if ( $addon_id ) {
			self::$addons[ $event ][] = $addon_id;
		}

		return true;
	}

	static function event_handler( $event, $data ) {

		if ( ! isset( self::$addon_files ) ) {
			if ( ! self::$addon_files = cerber_get_set( CRB_BOOT_ADDONS ) ) {
				self::$addon_files = false;
			}
		}

		if ( ! empty( self::$addon_files[ $event ] )
		     && ! isset( self::$loaded[ $event ] ) ) {
			ob_start();

			self::$loaded[ $event ] = 1; //Avoid processing files for repetitive events

			foreach ( self::$addon_files[ $event ] as $addon_file ) {
				if ( @file_exists( $addon_file ) ) {
					include_once $addon_file;
				}
			}

			ob_end_clean();
		}

		if ( ! isset( self::$handlers[ $event ] ) ) {
			return;
		}

		foreach ( self::$handlers[ $event ] as $handler ) {
			if ( is_callable( $handler ) ) {
				call_user_func( $handler, $data );
			}
		}
	}

	static function get_addons( $event = null ) {
		if ( ! $event ) {
			return self::$addons;
		}

		return crb_array_get( self::$addons, $event, array() );
	}

}

final class CRB_Addons {
	private static $addons = array();
	private static $tabs = array(); // Add-on tab IDs, they are equal add-on setting screen IDs
	private static $first = '';

	/**
	 * @param string $file Add-on main PHP file to be invoked in if event occurs
	 * @param string $addon_id Add-on slug
	 * @param string $name Name of the add-on
	 * @param string $requires Version of WP Cerber required
	 * @param callable $settings Configuration of the add-on setting fields
	 * @param callable $cb Optional callback function invoked when a website admin saves add-on settings.
	 *
	 * @return bool
	 */
	static function register_addon( $file, $addon_id, $name, $requires = '', $settings = null, $cb = null ) {
		$addon_id = crb_sanitize_id( $addon_id );

		if ( isset( self::$addons[ $addon_id ] ) ) {
			return false;
		}

		if ( $requires && version_compare( $requires, CERBER_VER, '>' ) ) {
			crb_admin_notice_interactive( 'This add-on is not active: ' . esc_html( $name ) . '. It requires WP Cerber version ' . esc_html( $requires ) . ' or newer. To solve the issue, please install the latest version of WP Cerber or deactivate the add-on.' );

			return false;
		}

		if ( ! self::$first ) {
			self::$first = $addon_id;
		}

		self::$addons[ $addon_id ] = array(
			'file'        => $file,
			'name'        => $name,
			'settings'    => $settings,
			'settings_cb' => $settings,
			'callback'    => $cb
		);

		self::$tabs[ $addon_id . CRB_ADDON_SIGN ] = $addon_id . CRB_ADDON_SIGN;

		return true;
	}

	/**
	 * @return array
	 */
	static function get_all() {
		return self::$addons;
	}

	/**
	 * Returns add-on settings config, if it generates by the specified add-on.
	 *
	 * @param $addon_id
	 *
	 * @return array
	 *
	 * @since 9.6.2.3
	 */
	static function get_addon_settings( $addon_id ): array {
		static $cache = array();

		if ( ! isset( $cache[ $addon_id ] ) ) {

			$config = array();

			if ( $cb = self::$addons[ $addon_id ]['settings_cb'] ?? false ) {

				if ( is_callable( $cb ) ) {
					$config = call_user_func( $cb ); // New way since 9.6.2.3
					if ( ! is_array( $config ) ) {
						$config = false;
					}
				}
				elseif ( is_array( $cb ) ) { // Old way
					$config = $cb;
				}
			}

			$cache[ $addon_id ] = $config;
		}

		return $cache[ $addon_id ];
	}

	/**
	 * Add setting screens (tabs) and related setting sections for all add-ons
	 *
	 * @return array[]|false
	 */
	static function settings_config() {

		if ( ! self::$addons ) {
			return false;
		}

		$settings = array( 'screens' => array(), 'sections' => array() );

		foreach ( self::$addons as $id => $addon ) {
			if ( ! $config = self::get_addon_settings( $id ) ) {
				continue;
			}

			$settings['screens'][ $id . CRB_ADDON_SIGN ] = array_keys( $config ); // Register a setting tab with setting sections
			$settings['sections'] = array_merge( $settings['sections'], $config ); // Register setting sections
		}

		return $settings;
	}

	static function update_settings( $form_fields, $addon_id ) {

		if ( ( ! $addon = self::$addons[ $addon_id ] ?? false )
		     || ! $config = self::get_addon_settings( $addon_id ) ) {
			return false;
		}

		$fields = array();

		foreach ( $config as $section ) {
			$fields = array_merge( $fields, array_keys( $section['fields'] ) );
		}

		$new_settings = array_merge( array_fill_keys( $fields, '' ), $form_fields );
		$all_settings = cerber_get_addon_settings();
		$save = array_merge( $all_settings, array( $addon_id => $new_settings ) );

		$ret = cerber_settings_update( array( CRB_ADDON_STS => $save ) );

		if ( ! empty( $addon['callback'] )
		     && is_callable( $addon['callback'] ) ) {
			crb_sanitize_deep( $new_settings );
			call_user_func( $addon['callback'], $new_settings );
		}

		return $ret;

	}

	/**
	 * Returns add-on tab ID for the specified WP setting screen ID
	 *
	 * @param string $screen_id
	 *
	 * @return string
	 *
	 * @since 9.6.2.1
	 */
	static function get_addon_tab( $screen_id ): string {
		return self::$tabs[ $screen_id ] ?? '';
	}

	/**
	 * Returns WP setting screen ID for the specified add-on tab (or the add-on page)
	 *
	 * @param string $id
	 *
	 * @return string
	 *
	 * @since 9.6.2.1
	 */
	static function get_setting_screen( $id ): string {
		if ( ! empty( self::$tabs[ $id ] ) ) {
			return self::$tabs[ $id ];
		}

		// If the page ID specified, it means the first tab

		if ( $id == CRB_ADDON_PAGE ) {
			return reset( self::$tabs );
		}

		return '';
	}

	/**
	 * Returns WP Cerber admin page ID for the specified add-on tab
	 *
	 * @param string $tab
	 *
	 * @return string
	 *
	 * @since 9.6.2.1
	 */
	static function get_addon_page( $tab ): string {
		if ( ! empty( self::$tabs[ $tab ] ) ) {
			return CRB_ADDON_PAGE;
		}

		return '';
	}

	/**
	 * @param string $addon
	 *
	 * @return bool
	 */
	static function is_registered( $addon ) {
		return isset( self::$addons[ $addon ] );
	}

	/**
	 * Is any add-on registered?
	 *
	 * @return bool
	 */
	static function none() {
		return empty( self::$first );
	}

	/**
	 * @return string
	 */
	static function get_first() {
		return self::$first;
	}

	/**
	 * Load code of all active add-ons
	 *
	 */
	static function load_active() {
		if ( ! $list = cerber_get_set( CRB_BOOT_ADDONS ) ) {
			return;
		}

		foreach ( $list as $files ) {
			foreach ( $files as $file ) {
				if ( @file_exists( $file ) ) {
					include_once $file;
				}
			}
		}
	}

	/**
	 * Retrieves the configuration for the admin UI of the addons.
	 *
	 * @return array|false The configuration array for the admin UI, or false if no addons are available.
	 */
	static function get_admin_ui() {

		if ( ! self::$addons ) {
			return false;
		}

		$config = array(
			'title'    => __( 'Add-ons', 'wp-cerber' ),
			'callback' => function ( $tab, $tab_data ) {
				cerber_show_settings_form( $tab, $tab_data );
			},
		);

		foreach ( self::$addons as $id => $addon ) {
			$config['tabs'][ $id . CRB_ADDON_SIGN ] = array(
				'bx-cog',
				crb_escape_html( $addon['name'] ),
				'tab_data' => array(
					'page_type' => 'addon-settings',
					'addon_id'  => $id
				)
			);
		}

		return $config;
	}
}

function crb_event_handler( $event, $data ) {

	CRB_Events::event_handler( $event, $data );

	return;
}

/**
 * Upgrade add-on settings (if any) to a new format
 *
 * @return void
 *
 * @since 9.3.4
 */
function _cerber_upgrade_addon_settings(){
	$addon_settings = array();
	$old_settings = get_site_option( 'cerber_tmp_old_settings' );

	foreach ( CRB_Addons::get_all() as $addon_id => $addon_conf ) {

		if ( ! $config = CRB_Addons::get_addon_settings( $addon_id ) ) {
			continue;
		}

		$fields = array();

		foreach ( $config as $section ) {
			$fields = array_merge( $fields, array_keys( $section['fields'] ) );
		}

		$addon_settings[ $addon_id ] = array_intersect_key( $old_settings, array_flip( $fields ) );
	}

	if ( $addon_settings
	     && ! cerber_settings_update( array( CRB_ADDON_STS => $addon_settings ) ) ) {
		cerber_admin_notice( 'Unable to upgrade add-on settings' );
	}

	delete_site_option( 'cerber_tmp_old_settings' );
}