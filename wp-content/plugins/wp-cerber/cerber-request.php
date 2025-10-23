<?php

final class CRB_Request {
	private static $remote_ip = null;
	private static $clean_uri = null; // No trailing slash, GET parameters and other junk symbols
	private static $uri_script = null; // With path and the starting slash (if script)
	private static $site_root = null; // Without trailing slash and path (site domain or IP address plus schema only)

	private static string $home_path = ''; // The URL path to the website home folder, no trailing slash
	private static string $wp_path = ''; // The URL path to the WordPress files, no trailing slash

	private static $the_path = null;
	private static array $files = array();
	private static int $recursion_counter = 0; // buffer overflow attack protection
	private static int $el_counter = 0; // buffer overflow attack protection
	private static $bad_request = false; // buffer overflow attack protection
	private static $commenting = null; // A comment is submitted

	const WP_FILES = [
		'/index.php',
		'/wp-activate.php',
		'/wp-blog-header.php',
		'/wp-comments-post.php',
		'/wp-config-sample.php',
		'/wp-config.php',
		'/wp-cron.php',
		'/wp-links-opml.php',
		'/wp-load.php',
		'/wp-login.php',
		'/wp-mail.php',
		'/wp-settings.php',
		'/wp-signup.php',
		'/wp-trackback.php',
		'/xmlrpc.php',
	];

	const WP_FOLDERS = [
		'/wp-admin/',
		'/wp-includes/',
	];

	/**
	 * Returns clean "Request URI" without trailing slash and GET parameters
	 *
	 * @return string
	 */
	static function URI() {
		if ( isset( self::$clean_uri ) ) {
			return self::$clean_uri;
		}

		return self::purify();
	}

	/**
	 * Cleans up and normalizes the requested URI.
	 * Removes GET parameters and extra slashes, normalizes malformed URI.
	 *
	 * @return string
	 * @since 7.9.2
	 */
	private static function purify() {
		$uri = $_SERVER['REQUEST_URI'];

		if ( $pos = strpos( $uri, '?' ) ) {
			$uri = substr( $uri, 0, $pos );
		}

		if ( $pos = strpos( $uri, '#' ) ) {
			$uri = substr( $uri, 0, $pos ); // malformed
		}

		$uri = rtrim( urldecode( $uri ), '/' );

		self::$clean_uri = preg_replace( '/\/+/', '/', $uri );

		return self::$clean_uri;
	}

	/**
	 * Parses website URLs and extracts hostname (domain or IP address), website installation (home) folder, WordPress installation folder.
	 *
	 * @return void
	 */
	static function parse_site_urls() {
		if ( isset( self::$site_root ) ) {
			return;
		}

		list( self::$site_root, self::$home_path ) = crb_parse_home_url();

		if ( cerber_get_home_url() == cerber_get_site_url() ) {
			self::$wp_path = self::$home_path;
		}
		else {
			self::$wp_path = crb_parse_site_url()[1];
		}
	}

	/**
	 * If the website is installed in a subfolder, returns the subfolder without trailing slash. The empty string otherwise.
	 *
	 * @return string Empty string if not in a subfolder
	 *
	 * @since 9.3.1
	 */
	static function get_site_path(): string {
		if ( ! isset( self::$site_root ) ) {
			self::parse_site_urls();
		}

		return self::$home_path;
	}

	/**
	 * The folder where the WordPress files are installed. No trailing slash.
	 *
	 * @return string Returns empty string if WordPress files or the website are not installed in a folder
	 *
	 * @since 9.6.5.8
	 */
	static function get_wp_path(): string {
		if ( ! isset( self::$site_root ) ) {
			self::parse_site_urls();
		}

		return self::$wp_path;
	}

	/**
	 * Requested URL as is
	 *
	 * @return string
	 */
	static function full_url() {

		self::parse_site_urls();

		return self::$site_root . $_SERVER['REQUEST_URI'];

	}

	static function full_url_clean() {

		self::parse_site_urls();

		return self::$site_root . self::URI();

	}

	/**
	 * Does request URI starts with a given string?
	 * Safe for checking malformed URLs
	 *
	 * @param $str string
	 *
	 * @return bool
	 */
	static function is_full_url_start_with( $str ) {

		$url = self::full_url_clean();

		if ( substr( $str, - 1, 1 ) == '/' ) {
			$url = rtrim( $url, '/' ) . '/';
		}

		if ( 0 === strpos( $url, $str ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Does requested URL start with a given string?
	 * Safe for checking malformed URLs
	 *
	 * @param $str string
	 *
	 * @return bool
	 */
	static function is_full_url_equal( $str ) {

		$url = self::full_url_clean();

		if ( substr( $str, - 1, 1 ) == '/' ) {
			$url = rtrim( $url, '/' ) . '/';
		}

		if ( $url == $str ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the requested URI is equal to the given one. Process only non-malformed URL.
	 * May not be used to check for a malicious URI since they can be malformed.
	 *
	 * @param string $slug No domain, no subfolder installation path
	 *
	 * @return bool True if requested URI match the given string and it's not malformed
	 */
	static function is_equal( $slug ) {
		self::parse_site_urls();
		$slug = ( $slug[0] != '/' ) ? '/' . $slug : $slug;
		$slug = self::$home_path . rtrim( $slug, '/' );
		$uri = rtrim( $_SERVER['REQUEST_URI'], '/' );

		if ( strlen( $slug ) === strlen( $uri )
		     && $slug == $uri ) {
			return true;
		}

		return false;
	}

	static function script() {
		if ( ! isset( self::$uri_script ) ) {
			if ( cerber_detect_exec_extension( self::URI() ) ) {
				self::$uri_script = strtolower( self::URI() );
			}
			else {
				self::$uri_script = false;
			}
		}

		return self::$uri_script;
	}

	/**
	 * Checks if the request URI strictly contains the given executable script.
	 * The script file name in the request URI must matches the given script strictly.
	 * Takes into consideration all installation paths automatically.
	 *
	 * @param string|array $val Script name without any query parameters, e.g. "/wp-admin/profile.php"
	 * @param bool $multiview
	 *
	 * @return bool
	 *
	 * @since 7.9.2
	 */
	static function is_script( $val, $multiview = false ) {
		if ( ! $uri_script = self::script() ) {
			return false;
		}

		self::parse_site_urls();

		if ( self::$wp_path
		     && 0 === strpos( $uri_script, self::$wp_path . '/' ) ) {
			$uri_script = substr( $uri_script, strlen( self::$wp_path ) );
		}
		elseif ( self::$home_path
		         && 0 === strpos( $uri_script, self::$home_path . '/' ) ) {
			$uri_script = substr( $uri_script, strlen( self::$home_path ) );
		}

		if ( is_array( $val ) ) {
			if ( in_array( $uri_script, $val ) ) {
				return true;
			}
		}
		elseif ( $uri_script == $val ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines if the given file path matches a standard WordPress script.
	 *
	 * The script path must start with a leading slash, e.g., "/wp-activate.php" or "/wp-admin/admin.php".
	 *
	 * @param string $script Relative file path starting with a slash.
	 *
	 * @return bool True if the file path matches a WordPress script, false otherwise.
	 *
	 * @since 9.6.5.8
	 */
	public static function is_wordpress_script( string $script ): bool {

		if ( in_array( $script, self::WP_FILES, true ) ) {
			return true;
		}

		foreach ( self::WP_FOLDERS as $folder_prefix ) {
			if ( strpos( $script, $folder_prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * WordPress search results page
	 *
	 * @return bool
	 */
	static function is_search() {
		if ( isset( $_GET['s'] ) ) {
			return true;
		}

		if ( self::is_path_start_with( '/search/', true ) ) {
			return true;
		}

		return false;
	}


	/**
	 * Returns true if the request URI starts with a given string.
	 *
	 *
	 * @param string $str
	 * @param bool $relative
	 *
	 * @return bool
	 */
	static function is_path_start_with( $str, $relative = false ) {
		static $cache;

		if ( ! $str ) {
			return false;
		}

		if ( ! isset( $cache[ $str ] ) ) {

			$path = $relative ? self::get_relative_path() : self::URI();
			$sub = substr( $path, 0, strlen( $str ) );

			$cache[ $str ] = ( $sub == $str );
		}

		return $cache[ $str ];
	}

	/**
	 * The request path with leading and trailing slashes.
	 *
	 * No subfolder is included if the website or/and WordPress are installed in a folder.
	 * The path is relative to the home folder of website or WordPress installation folder.
	 *
	 * @return string
	 *
	 * @since 9.3.1
	 */
	static function get_relative_path() {

		if ( self::$the_path !== null ) {
			return self::$the_path;
		}

		if ( ( $path = $_SERVER['PATH_INFO'] ?? '' )
		     && $path !== '/' ) { // Skip specific cases when the script name in the request is not the same as specified in the permalink setting
			$path = str_replace( '%', '%25', $path );

			$path = rawurldecode( $path );
		}
		elseif ( $path = $_SERVER['REQUEST_URI'] ) {

			if ( $pos = strpos( $path, '?' ) ) {
				$path = substr( $path, 0, $pos );
			}

			if ( $pos = strpos( $path, '#' ) ) {
				$path = substr( $path, 0, $pos );
			}

			$path = rawurldecode( $path );

			if ( ( $begin_with = self::get_wp_path() )
			     && 0 === mb_strpos( $path, $begin_with . '/' ) ) {
				$path = mb_substr( $path, mb_strlen( $begin_with ) );
			}
			elseif ( ( $begin_with = self::get_site_path() )
			         && 0 === mb_strpos( $path, $begin_with . '/' ) ) {
				$path = mb_substr( $path, mb_strlen( $begin_with ) );
			}
		}
		else {
			self::$the_path = '/';

			return self::$the_path;
		}

		$end = ( mb_substr( $path, - 1, 1 ) == '/' ) ? '/' : '';
		$path = trim( $path, '/' );

		self::$the_path = '/' . $path . ( $path ? $end : '' );

		return self::$the_path;
	}

	/**
	 * Returns requested REST route without leading and trailing slashes for any valid REST API request
	 *
	 * @return string Empty string if no valid REST API request/route detected
	 *
	 * @since 9.6.5.8 Replaced crb_get_rest_path()
	 */
	static function get_rest_api_path(): string {
		static $ret;

		if ( isset( $ret ) ) {
			return $ret;
		}

		$ret = '';

		if ( isset( $_REQUEST['rest_route'] ) ) {
			$ret = trim( $_REQUEST['rest_route'], '/' );
		}
		elseif ( cerber_is_permalink_enabled()
		         && $path = CRB_Request::get_relative_path() ) {

			$path = trim( $path, '/' );
			$prefix = rest_get_url_prefix() . '/';

			if ( 0 === strpos( $path, $prefix ) ) {
				$ret = substr( $path, strlen( $prefix ) );
			}
		}

		return $ret;
	}

	/**
	 * True if the request is sent to the root of the WP installation
	 *
	 * @return bool
	 */
	static function is_root_request() {
		return ! (bool) ( strlen( self::get_relative_path() ) > 1 );
	}

	static function get_files() {
		if ( self::$files ) {
			return self::$files;
		}

		if ( $_FILES ) {
			self::parse_files( $_FILES );
		}

		return self::$files;
	}

	/**
	 * Parser for messy $_FILES
	 *
	 * @param $fields
	 *
	 * @since 8.6.9
	 *
	 */
	static function parse_files( $fields ) {
		foreach ( $fields as $element ) {
			self::$el_counter ++;
			if ( self::$el_counter > 100 ) { // Normal forms never reach this limit
				self::$bad_request = true;

				return;
			}
			if ( ( $name = crb_array_get( $element, 'name' ) )
			     && is_string( $name )
			     && ( $tmp_file = crb_array_get( $element, 'tmp_name' ) )
			     && is_string( $tmp_file )
			     && is_file( $tmp_file ) ) {
				self::$files[] = array( 'source_name' => $name, 'tmp_file' => $tmp_file );
			}
			elseif ( is_array( $element ) ) {
				self::$recursion_counter ++;
				if ( self::$recursion_counter > 100 ) { // Normal forms never reach this limit
					self::$bad_request = true;

					return;
				}
				self::parse_files( $element );
			}
		}
	}

	static function is_comment_sent() {
		if ( ! isset( self::$commenting ) ) {
			self::$commenting = self::_check_comment_sent();
		}

		return self::$commenting;
	}

	private static function _check_comment_sent() {

		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
		     || $_SERVER['REQUEST_METHOD'] != 'POST'
		     || empty( $_POST )
		     || ! empty( $_GET ) ) {
			return false;
		}

		if ( cerber_is_custom_comment() ) {
			if ( ! empty( $_POST[ crb_get_compiled( 'custom_comm_mark' ) ] )
			     && self::is_equal( crb_get_compiled( 'custom_comm_slug' ) ) ) {
				return true;
			}
		}
		else {
			if ( self::is_script( '/' . WP_COMMENT_SCRIPT ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the request's Content-Type header matches a specified type.
	 * This function handles cases where the Content-Type header might include parameters
	 * (e.g., 'application/json; charset=UTF-8') and ensures an exact match for the base type.
	 *
	 * @param string $expected The base MIME type to check against (e.g., 'application/json', 'text/html').
	 *
	 * @return bool True if the Content-Type matches the expected type, false otherwise.
	 *
	 * @since 9.6.9.7
	 */
	static function check_content_type( string $expected ): bool {

		$content_type = trim( $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '' );

		if ( empty( $content_type ) ) {
			return false;
		}

		// The first part is the base MIME type, parameters are in the second part (if any).
		$parts = explode( ';', $content_type, 2 );
		$content_type = strtolower( trim( $parts[0] ) );

		return ( strtolower( rtrim( $expected, ';' ) ) === $content_type );
	}

	/**
	 * Check if the request declares a JSON payload via the Content-Type header.
	 * This is a standard encoding for WP REST-API requests.
	 *
	 * Note: this check does not guarantee that the request body is valid JSON.
	 *
	 * @return bool True if the request's declared Content-Type is 'application/json', false otherwise.
	 *
	 * @since 9.6.9.7
	 */
	static function is_json_header(): bool {
		return self::check_content_type( 'application/json' );
	}
}