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
 * Handling information about PHP errors thrown in the WP Cerber code
 *
 * @since 9.6.9.1
 */
final class CRB_Bug_Hunter {

	public const CRB_ERROR_LOG = 'cerber-errors.log';
	public const BODY_SIZE_LIMIT = 64 * 1024; // 64KB

	/**
	 * Returns the full path to the error log file.
	 *
	 * @return string Empty string when a diagnostic directory is not available.
	 *
	 * @since 9.6.9.1
	 */
	private static function get_log_file_path(): string {

		$dir = crb_get_diag_dir();

		if ( ! $dir ) {
			return '';
		}

		return $dir . self::CRB_ERROR_LOG;
	}

	/**
	 * Reads all lines from the error log file.
	 *
	 * @return array Array of log lines, empty array when the file is not available.
	 *
	 * @since 9.6.9.1
	 */
	private static function read_log_lines(): array {

		$file_path = self::get_log_file_path();

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array();
		}

		$lines = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( ! is_array( $lines ) ) {
			return array();
		}

		return $lines;
	}

	/**
	 * Saves PHP errors thrown in the WP Cerber code to an error log file with minimal overhead.
	 * We must complete all operations as quick as we can.
	 *
	 * @return void
	 *
	 * @since 9.6.9.1
	 */
	public static function save_errors(): void {
		if ( ! $errors = CRB_Globals::get_errors() ) {
			return;
		}

		$crb_errors = array();
		$php = phpversion();
		$wp = cerber_get_wp_version();

		foreach ( $errors as $error_info ) {
			$msg = $error_info[1];
			$err_level = $error_info[0];

			if ( E_WARNING == $err_level && strripos( $msg, 'Permission denied' ) ) {
				continue;
			}

			$file = $error_info[2];
			$uri = $error_info[4];

			// We collect WP Cerber errors only

			if ( strpos( $file, '/wp-cerber/' ) ) {
				$msg = mb_substr( $msg, 0, 2048 );
				$file = substr( $file, 0, 1024 );
				$uri = substr( $uri, 0, 1024 );

				$crb_errors[] = array( $err_level, $msg, $file, $error_info[3], CERBER_VER, $php, $wp, $uri );
			}
		}

		if ( ! $crb_errors ) {
			return;
		}

		// One log entry can contain multiple errors

		$log_entry = array(
			'time'   => time(),
			'errors' => $crb_errors,
		);

		if ( ! $json = json_encode( $log_entry, JSON_UNESCAPED_UNICODE ) ) {
			return;
		}

		$file_path = self::get_log_file_path();

		if ( ! $file_path || ( ! $log = @fopen( $file_path, 'a' ) ) ) {
			return;
		}

		if ( @flock( $log, LOCK_EX | LOCK_NB ) ) {
			@fwrite( $log, $json . PHP_EOL );
			@flock( $log, LOCK_UN );
		}

		@fclose( $log );
	}

	/**
	 * Truncate a log file, keeping only the last N lines.
	 * Note: Do not use for huge files as the entire file is loaded into memory.
	 *
	 * This function removes lines from the beginning of the log file, keeping
	 * only the specified number of lines at the end.
	 *
	 * @param int $lines_to_keep Number of lines to keep.
	 *
	 * @return void
	 *
	 * @since 9.6.9.1
	 */
	public static function truncate_log_file( int $lines_to_keep = 50 ): void {

		$file_path = self::get_log_file_path();

		if ( ! $file_path ) {
			return;
		}

		if ( ! $lines_to_keep ) {
			file_put_contents( $file_path, '' );
			return;
		}

		$lines = self::read_log_lines();

		if ( ! $lines || count( $lines ) <= $lines_to_keep ) {
			return;
		}

		$lines_to_keep = max( 1, $lines_to_keep );
		$lines = array_slice( $lines, - $lines_to_keep );

		$log = @fopen( $file_path, 'w' );

		if ( ! $log ) {
			return;
		}

		if ( @flock( $log, LOCK_EX ) ) {

			foreach ( $lines as $line ) {
				@fwrite( $log, $line . PHP_EOL );
			}

			@flock( $log, LOCK_UN );
		}

		@fclose( $log );
	}

	/**
	 * Launch periodical maintenance tasks
	 *
	 * @since 9.6.9.1
	 */
	public static function run_maintenance_tasks() {

		self::flush_errors();
		self::truncate_log_file();

	}

	/**
	 * Send accumulated WP Cerber's errors to the BugHunter error collector web service.
	 *
	 * @since 9.6.9.1
	 */
	public static function flush_errors() {
		$lines = self::read_log_lines();

		if ( ! $lines ) {
			return;
		}

		$payload = array(
			'crb_version' => CERBER_VER,
			'wp_version'  => cerber_get_wp_version(),
			'revision'    => 1,
			'bug_pile'    => array(),
		);

		$root_dir = rtrim( ABSPATH, '/' );

		// Which entry we processed last time, if any

		$last_entry = (int) cerber_get_set( 'bug_hunter_last_processed', 0, false );
		$save_last = 0;

		foreach ( $lines as $line ) {
			$entry = json_decode( $line, true );

			if ( json_last_error() !== JSON_ERROR_NONE
			     || empty( $entry['time'] )
			     || $entry['time'] <= $last_entry ) {
				continue;
			}

			// Remove sensitive data (anonymisation)

			foreach ( $entry['errors'] as &$error ) {

				$error[2] = str_replace( $root_dir, '', $error[2] );

				$error[7] = crb_strip_query_params( $error[7], array(
					'page',
					'tab',
					'cerber_admin_do',
					'filter_activity',
					'filter_set',
				) );
			}

			// Build payload

			$payload['bug_pile'][] = $entry;
			$test_body = json_encode( $payload, JSON_UNESCAPED_UNICODE );

			if ( ! $test_body || strlen( $test_body ) > self::BODY_SIZE_LIMIT ) {
				array_pop( $payload['bug_pile'] );

				break;
			}

			$save_last = $entry['time'];
		}

		if ( ! $payload['bug_pile'] ) {
			return;
		}

		$body = json_encode( $payload, JSON_UNESCAPED_UNICODE );

		if ( ! $body ) {
			return;
		}

		$args = array(
			'body'    => $body,
			'headers' => array(
				'Content-Type'    => 'application/json',
				'X-Body-Checksum' => md5( $body ),
			),
			'timeout' => 3,
		);

		$diag_enabled = defined( 'CERBER_NETWORK_DEBUG' ) && CERBER_NETWORK_DEBUG;

		if ( $diag_enabled ) {
			cerber_diag_log( 'Sending BugHunter payload (' . strlen( $body ) . ' bytes)', 'BugHunter' );
		}

		$response = wp_remote_post( 'https://downloads.wpcerber.com/bughunter/', $args );

		if ( crb_is_wp_error( $response ) ) {
			if ( $diag_enabled ) {
				cerber_error_log( 'BugHunter request failed: ' . $response->get_error_message(), 'BugHunter' );
			}

			return;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			if ( $diag_enabled ) {
				cerber_error_log( 'BugHunter bad response code: ' . $code, 'BugHunter' );
			}
		}
		else {
			// HTTP 200 OK = SUCCESS
			// Set marker for the next submission
			cerber_update_set( 'bug_hunter_last_processed', $save_last, 0, false );
		}
	}

	/**
	 * Reads a log file created by cerber_save_errors() and creates HTML view for displaying on an admin webpage.
	 *
	 * @param string $item_class Optional CSS class for log entries wrappers.
	 *
	 * @return string If any error logged, return formatted contents of the file, empty line otherwise
	 *
	 * @since 9.6.9.1
	 */
	public static function create_log_view( string $item_class = '' ): string {

		$lines = self::read_log_lines();

		if ( ! $lines ) {
			return '';
		}

		$entries = array();
		$root_dir = rtrim( ABSPATH, '/' );

		foreach ( array_reverse( $lines ) as $line ) {
			$entry = json_decode( $line, true );

			if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $entry['time'], $entry['errors'] ) ) {
				continue;
			}

			$errors = '';

			foreach ( $entry['errors'] as $error ) {
				$errors .= "Level:\t" . cerber_get_err_level( $error[0] ) . '<br/>';

				$file = str_replace( $root_dir, '', $error[2] );
				$errors .= "File:\t" . esc_textarea( $file ) . '<br/>';

				$errors .= "Line:\t" . crb_absint( $error[3] ) . '<br/>';

				$msg = str_replace( $root_dir, '', $error[1] );
				$errors .= "Error:\t" . esc_textarea( $msg ) . '<br/>';

				$errors .= "Version:\t" . esc_textarea( $error[4] ) . '<br/>';
				$errors .= "PHP:\t" . esc_textarea( $error[5] ) . '<br/>';
				$errors .= "WP:\t" . esc_textarea( $error[6] ) . '<br/>';
				$errors .= "URI:\t" . esc_textarea( $error[7] ?? '' ) . '<br/>';
			}

			$entries[] = array(
				cerber_date( $entry['time'] ),
				'<div class="' . crb_boring_escape( $item_class ) . '" style="white-space: pre-wrap; tab-size: 10;">' . $errors . '</div>' . "\n",
			);
		}

		$ret = cerber_make_plain_table( $entries );

		return $ret;
	}
}
