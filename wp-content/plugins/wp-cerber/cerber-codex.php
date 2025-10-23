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
 * Status/report/diagnostic domain-specific messages
 *
 * @since 9.6.2.6
 */

const CRB_SA221 = 'STS221';
const CRB_SA222 = 'STS222';
const CRB_SA223 = 'STS223';
const CRB_SA224 = 'STS224';

/*
	Severity of an event/status
 */
const CRB_SEV_CRITICAL = 'critical';
const CRB_SEV_WARNING = 'warning';
const CRB_SEV_NOTICE = 'notification';
const CRB_SEV_OK = 'good';

/*
	Processing and software errors
*/

// Generic
const CRB_PL721 = 'ECPL72';
// Network
const CRB_PL722 = 'ECN429';
const CRB_PL723 = 'ECN404';
const CRB_PL724 = 'ECN000';
// Content
const CRB_PL725 = 'ECPL41';
const CRB_PL726 = 'ECPL42';
const CRB_PL727 = 'ECPL43';

/**
 * Returns an error message corresponding to the given WP Cerber error ID.
 *
 * @param string $id The error ID. If not provided, returns an array of all error messages.
 *
 * @return string|string[]  The error message string if the ID is provided and exists in the error message array,
 *                          otherwise a string indicating an unknown error ID, or an array of all error messages.
 *
 * @since 9.6.2.4
 */
function crb_get_error_msg( $id = '' ) {
	static $messages;

	if ( ! $messages ) {
		$messages = array(
			CRB_PL721 => 'Unable to proceed due to invalid plugin slug.',
			CRB_PL722 => __( 'Plugin data cannot be loaded due to temporary rate limiting enforced by the plugin repository. Please try again in a few minutes.', 'wp-cerber' ),
			CRB_PL723 => __( 'At the moment, no plugin data is available. It will be collected and included in future reports.', 'wp-cerber' ),
			CRB_PL724 => __( 'A network error occurred while attempting to retrieve plugin data from the plugin repository. We will continue attempts.', 'wp-cerber' ),

			CRB_PL725 => 'Unable to extract plugin data. No valid plugin data found.',
			CRB_PL726 => 'Unable to parse HTML content. Invalid HTML markup.',
			CRB_PL727 => 'Unable to extract plugin data. No valid JSON data found.',
		);
	}

	if ( $id ) {
		if ( isset( $messages[ $id ] ) ) {
			return $messages[ $id ];
		}

		return 'Unknown error ID: ' . htmlspecialchars( $id, ENT_QUOTES );
	}

	return $messages;
}
