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


// If this file is called directly, abort executing.
if ( ! defined( 'WPINC' ) ) { exit; }

const WHOIS_ERR_EXPIRE = 300;
const WHOIS_OK_EXPIRE = 4 * 3600;
const WHOIS_IO_TIMEOUT = 3;
const CRB_NO_COUNTRY = '??';

require_once( __DIR__ . '/cerber-ripe.php' );

/**
 * Get WHOIS info about a given IP
 *
 * @param string $ip
 *
 * @return array
 *
 * @since 2.7
 *
 */
function cerber_ip_whois_info( $ip ) {
	$ret = array();

	$whois_server = cerber_get_whois_server( $ip );
	if ( is_array( $whois_server ) ) {
		return $whois_server;
	}

	if ( $whois_server == 'whois.ripe.net' ) {
		return ripe_readable_info( $ip );
	}

	$whois_info = cerber_get_whois( $ip );
	if ( is_array( $whois_info ) ) {
		return $whois_info;
	}

	$data = cerber_parse_whois_data( $whois_info );

	// Special case - network was transferred to RIPE
	if ( isset( $data['ReferralServer'] )
	     && $data['ReferralServer'] == 'whois://whois.ripe.net' ) {
		return ripe_readable_info( $ip );
	}

	$data = crb_attr_escape( $data );

	$table1 = '';

	if ( ! empty( $data ) ) {
		$table1 = '<table class="whois-object"><tr><td colspan="2"><b>FILTERED WHOIS INFO</b></td></tr>';
		foreach ( $data as $key => $value ) {
			if ( is_email( $value ) ) {
				$value = '<a href="mailto:' . $value . '">' . $value . '</a>';
			}
			elseif ( strtolower( $key ) == 'country' ) {
				$value = crb_get_flag_html( $value, '<b>' . crb_get_country_name( $value ) . ' (' . $value . ')</b>' );
				$ret['country'] = $value;
			}

			$table1 .= '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
		}
		$table1 .= '</table>';
	}

	$table2 = '<table class="whois-object crb-raw-data"><tr><td><b>RAW WHOIS INFO</b></td></tr>';
	$table2 .= '<tr><td><pre>' . crb_escape( $whois_info ) . "\n WHOIS server: " . $whois_server . '</pre></td></tr>';
	$table2 .= '</table>';

	$info = $table1 . $table2;

	// Other possible fields with abuse email address
	if ( empty( $data['abuse-mailbox'] ) ) {
		$data['abuse-mailbox'] = $data['OrgAbuseEmail'] ?? '';
	}

	if ( empty( $data['abuse-mailbox'] ) ) {
		foreach ( $data as $field ) {
			$maybe_email = trim( $field );
			if ( false !== strpos( $maybe_email, 'abuse' )
			     && is_email( $maybe_email ) ) {
				$data['abuse-mailbox'] = $maybe_email;
				break;
			}
		}
	}

	// Network
	$data['network'] = $data['inetnum'] ?? $data['NetRange'] ?? '';

	$ret['data'] = $data;
	$ret['whois'] = $info;
	return $ret;
}
/**
 * Get WHOIS info for a given IP
 *
 * @param string $ip IP address
 *
 * @return array|string Array on error
 *
 * @since 2.7
 */
function cerber_get_whois( $ip ) {
	$key = 'WHS-' . cerber_get_id_ip( $ip );

	if ( ! $info = cerber_get_set( $key, null, false ) ) {
		$whois_server = cerber_get_whois_server( $ip );

		if ( is_array( $whois_server ) ) {
			return $whois_server;
		}

		$info = make_whois_request( $whois_server, $ip );

		if ( is_array( $info ) ) {
			return $info;
		}

		cerber_update_set( $key, $info, null, false, time() + WHOIS_OK_EXPIRE );
	}

	return $info;
}
/**
 * Find out what server stores WHOIS info for a given IP
 *
 * @param string $ip IP Address
 *
 * @return mixed|string[] Array on error, hostname on success
 */
function cerber_get_whois_server( $ip ) {
	$key = 'SRV-' . cerber_get_id_ip( $ip );

	if ( ! $server = cerber_get_set( $key, null, false ) ) {
		$w = make_whois_request( 'whois.iana.org', $ip );

		if ( is_array( $w ) ) {
			return $w;
		}

		preg_match( '/^whois\:\s+([\w\.\-]{3,})/m', $w, $data );

		if ( empty( $data[1] )
		     || ! crb_is_valid_hostname( $data[1] ) ) {
			return array( 'error' => 'No valid WHOIS server was found for IP ' . $ip );
		}

		$server = $data[1];

		cerber_update_set( $key, $server, null, false, time() + WHOIS_OK_EXPIRE );
	}

	return $server;
}
/**
 * Attempts to parse textual WHOIS response to associative array
 *
 * @param string $text
 *
 * @return array
 *
 * @since 2.7
 */
function cerber_parse_whois_data( $text ) {
	$lines = explode( "\n", $text );
	$lines = array_filter( $lines );
	$ret = array();

	foreach ( $lines as $line ) {
		if ( preg_match( '/^([\w\-]+)\:\s+(.+)/', trim( $line ), $data ) ) {
			$ret[ $data[1] ] = $data[2];
		}
	}

	return $ret;
}
/**
 * Retrieve RAW text information about an IP address by using WHOIS protocol
 *
 * @param string $hostname WHOIS server
 * @param string $ip IP address
 *
 * @return string|string[] Array on error
 *
 * @since 2.7
 */
function make_whois_request( $hostname, $ip ) {
	if ( ! $socket = @fsockopen( $hostname, 43, $errno, $errstr, WHOIS_IO_TIMEOUT ) ) {
		return array( 'error' => 'Network error: ' . $errstr . ' (WHOIS server: ' . $hostname . ').' );
	}

	#Set the timeout for answering
	if ( ! stream_set_timeout( $socket, WHOIS_IO_TIMEOUT ) ) {
		return array( 'error' => 'WHOIS: Unable to set IO timeout.' );
	}

	#Send the IP address to the whois server
	if ( false === fwrite( $socket, "$ip\r\n" ) ) {
		return array( 'error' => 'WHOIS: Unable to send request to remote WHOIS server (' . $hostname . ').' );
	}

	//Set the timeout limit for reading again
	if ( ! stream_set_timeout( $socket, WHOIS_IO_TIMEOUT ) ) {
		return array( 'error' => 'WHOIS: Unable to set IO timeout.' );
	}

	//Set socket in non-blocking mode
	if ( ! stream_set_blocking( $socket, 0 ) ) {
		return array( 'error' => 'WHOIS: Unable to set IO non-blocking mode.' );
	}

	//If connection is still valid
	if ( $socket ) {
		$data = '';
		while ( ! feof( $socket ) ) {
			$data .= fread( $socket, 256 );
		}
	}
	else {
		return array( 'error' => 'Unable to get WHOIS response.' );
	}

	if ( ! $data ) {
		return array( 'error' => 'Remote WHOIS server return empty response (' . $hostname . ').' );
	}

	return $data;
}