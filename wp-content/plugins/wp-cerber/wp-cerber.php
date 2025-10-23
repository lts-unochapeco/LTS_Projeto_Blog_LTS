<?php
/*
	Plugin Name: WP Cerber Security, Anti-spam, and Malware Scan
	Plugin URI: https://wpcerber.com
	Description: Continuously evolving security solution that defends WordPress against hacker attacks, spam, trojans, and malware. Hardens WordPress with comprehensive security algorithms, limits login attempts, enforces two-factor authentication, and restricts access based on country using GeoIP policies. Features a powerful malware scanner and integrity checker to detect and eliminate threats. Blocks spam using a sophisticated bot detection engine and reCAPTCHA. Tracks user and intruder activity with real-time alerts via email, mobile, and desktop notifications.
	Author: Cerber Tech Inc.
	Author URI: https://talk.wpcerber.com
	Update URI: https://downloads.wpcerber.com/versions/wp-cerber.json
	Version: 9.6.10
	Requires PHP: 7.4
	Requires at least: 5.8
	Text Domain: wp-cerber
	Domain Path: /languages
	Network: true

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

const CERBER_VER = '9.6.10';

// Must be also updated in the header above
const CERBER_REQ_PHP = '7.4';
const CERBER_REQ_WP = '5.8';

const CERBER_PLUGIN_ID = 'wp-cerber/wp-cerber.php';

function cerber_plugin_file() {
	return __FILE__;
}

function cerber_plugin_data() {
	return get_plugin_data( __FILE__ );
}

/**
 * Returns the folder where WP Cerber is installed. No trailing slash.
 *
 * @return string
 */
function cerber_plugin_dir() {

	return __DIR__;
}

/**
 * Returns the full URL with a path to the WP Cerber folder containing trailing slash
 *
 * @return string
 */
function cerber_plugin_dir_url(): string {
	static $ret = null;

	if ( $ret === null ) {
		$ret = (string) plugin_dir_url( __FILE__ );
	}

	return $ret;
}

/**
 * Returns an absolute path to the plugins folder without trailing slash: /full-path-to-wordpress-folder/wp-content/plugins
 *
 * @return string
 */
function cerber_get_plugins_dir(): string {
	static $dir = null;

	if ( $dir === null ) {
		$dir = dirname( __FILE__, 2 );
	}

	return $dir;
}

/**
 * Returns an absolute path to the themes folder without trailing slash: /full-path-to-wordpress-folder/wp-content/themes
 *
 * @return string
 */
function cerber_get_themes_dir() {
	static $dir = null;

	if ( $dir === null ) {
		$dir = cerber_get_content_dir() . DIRECTORY_SEPARATOR . 'themes';
	}

	return $dir;
}

function cerber_get_content_dir() {
	static $dir = null;

	if ( $dir === null ) {
		$dir = dirname( cerber_get_plugins_dir() );
	}

	return $dir;
}

/**
 * @return null|string Full path. For MU it returns the uploads folder of the main site.
 */
function cerber_get_upload_dir() {
	static $dir = null;
	if ( $dir === null ) {
		if ( is_multisite() ) {
			switch_to_blog( get_main_site_id() );
		}
		$wp_upload_dir = wp_get_upload_dir();
		if ( is_multisite() ) {
			restore_current_blog();
		}
		$dir = cerber_normal_path( $wp_upload_dir['basedir'] );
	}

	return $dir;
}

/**
 * Returns path to the root uploads folder for all sites in the MU network
 *
 * @return false|string
 */
function cerber_get_upload_dir_mu() {
	global $blog_id, $wpdb;
	static $dir = null;

	if ( $dir === null ) {
		if ( is_multisite()
		     && ( $id = cerber_db_get_var( 'SELECT MAX(blog_id) FROM ' . $wpdb->blogs ) ) ) {

			if ( $id == get_main_site_id() ) {
				// no child sites in the network
				$dir = cerber_get_upload_dir();
			}
			else {
				$tmp = $blog_id;
				$blog_id = $id;
				$wp_upload_dir = wp_upload_dir();
				$blog_id = $tmp;
				$site_dir = rtrim( $wp_upload_dir['basedir'], '/' ) . '/';
				// A new network created post-3.5
				$end = '/sites/' . $id . '/';
				if ( $p = mb_strpos( $site_dir, $end ) ) {
					$dir = mb_substr( $site_dir, 0, $p );
				}
				else {
					$id = 1; // workaround for old MU installations
					$end = '/' . $id . '/files/';
					if ( $p = mb_strpos( $site_dir, $end ) ) {
						$dir = mb_substr( $site_dir, 0, $p );
					}
					else {
						// Check if a custom path has been defined by the site admin
						// see also UPLOADS,  BLOGUPLOADDIR, BLOGUPLOADDIR
						if ( defined( 'UPLOADBLOGSDIR' ) ) {
							$dir = ABSPATH . UPLOADBLOGSDIR;
							if ( ! file_exists( $dir ) ) {
								$dir = false;
							}
						}
					}
				}

				if ( $dir ) {
					$dir = cerber_normal_path( $dir );
				}
			}
		}
		else {
			$dir = cerber_get_upload_dir();
		}
	}

	if ( ! $dir ) {
		$dir = false;
	}

	return $dir;
}

function cerber_get_abspath() {
	static $abspath = null;

	if ( $abspath === null ) {
		$abspath = cerber_normal_path( ABSPATH );
	}

	return $abspath;
}

/**
 * @return float
 */
function cerber_request_time() {
	static $req_time = null;

	if ( ! isset( $req_time ) ) {

		if ( ! empty( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
			$req_time = filter_var( $_SERVER['REQUEST_TIME_FLOAT'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
		}

		$mt = microtime( true );

		if ( ! $req_time || 60 < abs( $req_time - $mt ) ) { // Workaround: some servers may have a wrong value in 'REQUEST_TIME_FLOAT'
			$req_time = $mt;
		}
	}

	return $req_time;
}

cerber_request_time();

require_once( __DIR__ . '/cerber-load.php' );

cerber_init();