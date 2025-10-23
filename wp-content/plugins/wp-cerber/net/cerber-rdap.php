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

/**
 * Class CRB_RDAP_Client
 *
 * Provides a complete RDAP client implementation for IP address lookup, including data parsing,
 * normalization, caching, and HTML rendering.
 *
 * Key features:
 * - Retrieves RDAP data from compliant servers (e.g. rdap.org) with caching
 * - Parses and normalizes IP ownership, contact, ASN, block type, and country info
 * - Maps diverse RDAP block types to standard categories (ALLOCATED, ASSIGNED, LEGACY, etc.)
 * - Extracts and deduplicates nested entities and abuse contacts
 * - Detects RIR source (ARIN, RIPE, APNIC, LACNIC, AFRINIC)
 * - Converts structured RDAP data into a responsive HTML view
 *
 * All methods are static and can be called without instantiating the class.
 *
 * @see cerber_get_set()
 * @see cerber_update_set()
 * @see crb_get_country_code()
 * @see cerber_auto_date()
 *
 * @since 9.6.7.10
 */
class CRB_RDAP_Client {

	const RDAP_BLOCK_TYPES = [
		'allocated'             => [ 'normalized' => 'ALLOCATED', 'description' => 'Block allocated to a regional internet registry (RIR)' ],
		'assigned'              => [ 'normalized' => 'ASSIGNED', 'description' => 'Block assigned to an end-user organization or network operator' ],
		'assignment'            => [ 'normalized' => 'ASSIGNED', 'description' => 'Block assigned to an end-user organization or network operator' ],
		'legacy'                => [ 'normalized' => 'LEGACY', 'description' => 'Legacy block issued before RIRs were established' ],
		'reserved'              => [ 'normalized' => 'RESERVED', 'description' => 'Block is reserved and not publicly routable' ],
		'available'             => [ 'normalized' => 'AVAILABLE', 'description' => 'Block is available for assignment by a registry' ],
		'reassigned'            => [ 'normalized' => 'REASSIGNED', 'description' => 'Block delegated to a sub-organization' ],

		// Extended known RDAP variations
		'direct allocation'     => [ 'normalized' => 'ALLOCATED', 'description' => 'Block directly allocated by a registry' ],
		'indirect allocation'   => [ 'normalized' => 'ALLOCATED', 'description' => 'Block allocated via intermediary organization' ],
		'direct assignment'     => [ 'normalized' => 'ASSIGNED', 'description' => 'Block directly assigned to an organization' ],
		'indirect assignment'   => [ 'normalized' => 'ASSIGNED', 'description' => 'Block assigned through a downstream provider' ],
		'legacy allocation'     => [ 'normalized' => 'LEGACY', 'description' => 'Legacy allocation still recognized' ],

		// RIR-specific types
		'assigned pa'           => [ 'normalized' => 'ASSIGNED', 'description' => 'Assigned Provider-Aggregatable address block' ],
		'assigned pi'           => [ 'normalized' => 'ASSIGNED', 'description' => 'Assigned Provider-Independent address block' ],
		'allocated pa'          => [ 'normalized' => 'ALLOCATED', 'description' => 'Allocated Provider-Aggregatable block for LIRs' ],
		'allocated pi'          => [ 'normalized' => 'ALLOCATED', 'description' => 'Allocated Provider-Independent block' ],
		'allocated portable'    => [ 'normalized' => 'ALLOCATED', 'description' => 'Allocated Provider-Independent block' ],
		'allocated unspecified' => [ 'normalized' => 'ALLOCATED', 'description' => 'Allocated block with unspecified status (e.g. from LACNIC)' ],

		// APNIC and LACNIC extras
		'assigned pi ipv6'      => [ 'normalized' => 'ASSIGNED', 'description' => 'Provider-independent IPv6 block assigned to an end-user' ],
		'assigned pa ipv6'      => [ 'normalized' => 'ASSIGNED', 'description' => 'Provider-aggregatable IPv6 block assigned to an end-user' ],
		'allocated pi ipv6'     => [ 'normalized' => 'ALLOCATED', 'description' => 'Provider-independent IPv6 block allocated to LIR' ],
		'allocated pa ipv6'     => [ 'normalized' => 'ALLOCATED', 'description' => 'Provider-aggregatable IPv6 block allocated to LIR' ],
		'assigned-anycast ipv6' => [ 'normalized' => 'ASSIGNED', 'description' => 'Anycast IPv6 assignment (RIPE-specific)' ],

		// Rare or regional variants
		'allocated-by-lir'      => [ 'normalized' => 'ALLOCATED', 'description' => 'Block allocated by a Local Internet Registry (LIR) under RIPE policy' ],
		'assigned-anycast'      => [ 'normalized' => 'ASSIGNED', 'description' => 'Block assigned for anycast routing' ],
		'sub-allocated pa'      => [ 'normalized' => 'ALLOCATED', 'description' => 'Block sub-allocated by a provider (e.g. LACNIC or RIPE)' ],
		'allocated-unspecified' => [ 'normalized' => 'ALLOCATED', 'description' => 'Block allocated without specific policy classification' ],
		'assigned non-portable' => [ 'normalized' => 'ASSIGNED', 'description' => 'Assigned address block that is provider-aggregatable and non-portable' ],
		'not-available'         => [ 'normalized' => 'UNKNOWN', 'description' => 'Block type not disclosed or not available' ],
	];

	/**
	 * Performs RDAP lookup and returns structured data extracted from the response.
	 *
	 * @param string $ip A valid IPv4 or IPv6 address.
	 * @param bool $force_refresh If true, skips the cache and performs a fresh RDAP request.
	 *
	 * @return object|WP_Error Returns an object on success, containing:
	 * @property string $rir_source One of: ARIN, RIPE, APNIC, LACNIC, AFRINIC, or UNKNOWN.
	 * @property string $owner_name    Name of the IP block or organization.
	 * @property string $abuse_email   First abuse-role email found (if any).
	 * @property string $abuse_address Physical address from abuse-role entity if available.
	 * @property string $country       Country code or name if available.
	 * @property string $cidr          IP range in "start - end" format.
	 * @property string $asn           ASN (e.g. "AS12345") if available.
	 * @property array $block_type    {
	 * @type string $raw Original RDAP type value (e.g. "direct allocation").
	 * @type string $normalized Normalized uppercase type (e.g. "ALLOCATED").
	 * @type string $description Human-readable explanation of the type.
	 *   }
	 * @property array[] $entities      List of structured contact records:
	 *                                         [
	 *                                           'handle'  => string,
	 *                                           'roles'   => string[],
	 *                                           'name'    => string,
	 *                                           'email'   => string|null,
	 *                                           'phone'   => string|null,
	 *                                           'address' => string|null,
	 *                                         ]
	 * @property array[] $events        List of RDAP event records:
	 *                                         [
	 *                                           'action' => string,
	 *                                           'label'  => string,
	 *                                           'date'   => string (ISO 8601),
	 *                                         ]
	 * @property array $raw_data      Full decoded RDAP response as returned by the server.
	 */
	public static function get_parsed_ip_info( string $ip, bool $force_refresh = false ) {

		$raw = self::fetch_rdap_data( $ip, $force_refresh );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return self::parse_rdap_data( $raw );
	}

	/**
	 * Fetches raw RDAP response as an associative array, optionally using cache.
	 *
	 * @param string $ip IP address.
	 * @param bool $force_refresh If true, skips cache and fetches live data.
	 *
	 * @return array|WP_Error       RDAP response as array, or WP_Error on failure.
	 */
	public static function fetch_rdap_data( string $ip, bool $force_refresh = false ) {

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'invalid_ip', 'Invalid IP address format.' );
		}

		$cache_key = 'rdap_ip_' . $ip;

		if ( ! $force_refresh && function_exists( 'cerber_get_set' ) ) {
			$cached = cerber_get_set( $cache_key );
			if ( $cached !== false && is_array( $cached ) ) {
				return $cached;
			}
		}

		$endpoint = 'https://rdap.org/ip/' . $ip;
		$response = wp_remote_get( $endpoint, [
			'timeout' => 5,
			'headers' => [
				'Accept' => 'application/rdap+json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'http_request_failed', 'Failed to fetch RDAP data: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'rdap_bad_response', 'Unexpected RDAP response code: ' . $code );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'rdap_empty_response', 'RDAP server returned an empty response.' );
		}

		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'rdap_json_error', 'Failed to decode RDAP response: ' . json_last_error_msg() );
		}

		if ( function_exists( 'cerber_update_set' ) ) {
			$expires = time() + 6 * HOUR_IN_SECONDS;
			cerber_update_set( $cache_key, $data, 0, true, $expires );
		}

		return $data;
	}

	/**
	 * Parses RDAP JSON array and extracts normalized fields.
	 *
	 * @param array $data RDAP response already decoded as an array.
	 *
	 * @return object See get_parsed_ip_info() for structure.
	 */
	public static function parse_rdap_data( array $data ) {
		$result = [
			'owner_name'    => '',
			'abuse_email'   => '',
			'abuse_address' => '',
			'country'       => '',
			'cidr'          => '',
			'block_type'    => '',
			'contacts'      => [],
			'events'        => [],
			'remarks'       => [],
			'rir_source'    => '',
			'asn'           => '',
			'raw_data'      => $data,
		];

		if ( isset( $data['startAddress'], $data['endAddress'] ) ) {
			$result['cidr'] = $data['startAddress'] . ' - ' . $data['endAddress'];
		}

		if ( isset( $data['name'] ) ) {
			$result['owner_name'] = $data['name'];
		}

		if ( isset( $data['country'] ) ) {
			$result['country'] = strtoupper( (string) $data['country'] );
		}
		else {
			$result['country'] = self::extract_country_from_registrant( $data['entities'] );
		}

		$result['rir_source'] = self::detect_rir_source( $data );
		$result['block_type'] = self::parse_rdap_block_type( $data['type'] ?? null );
		$result['contacts'] = self::extract_rdap_contacts( $data['entities'] ?? null );
		$result['events'] = self::extract_rdap_events( $data['events'] ?? null );

		foreach ( $result['contacts'] as $entity ) {
			if ( in_array( 'abuse', $entity['roles'], true ) ) {
				if ( $entity['email'] ) {
					$result['abuse_email'] = $entity['email'];
				}
				if ( $entity['address'] ) {
					$result['abuse_address'] = $entity['address'];
				}
				break;
			}
		}

		if ( isset( $data['arin_originas0_originautnums'][0] ) ) {
			$result['asn'] = 'AS' . $data['arin_originas0_originautnums'][0];
		}

		$result['remarks'] = $data['remarks'] ?? array();

		return (object) $result;
	}

	/**
	 * Parses and normalizes the 'type' field of RDAP response.
	 *
	 * @param string $type Raw type string (e.g. "allocated", "legacy").
	 *
	 * @return array {
	 * @type string $raw Raw type string.
	 * @type string $normalized Normalized uppercase type.
	 * @type string $description Human-readable description.
	 * }
	 */
	protected static function parse_rdap_block_type( $type ): array {

		$key = strtolower( trim( (string) $type ) );

		if ( isset( self::RDAP_BLOCK_TYPES[ $key ] ) ) {
			$type_info = self::RDAP_BLOCK_TYPES[ $key ];
			$type_info['raw'] = $type;
		}
		else {
			$type_info = [
				'raw'         => $type,
				'normalized'  => strtoupper( $key ?: 'UNKNOWN' ),
				'description' => 'Unrecognized block type: ' . $type,
			];
		}

		return $type_info;
	}

	/**
	 * Parses the 'entities' array from RDAP and returns structured contact records.
	 * Recursively processes nested entities and merges data by handle.
	 *
	 * @param array $entities Raw RDAP entities array.
	 *
	 * @return array[] Each item contains:
	 *   - handle  (string)       Entity handle.
	 *   - roles   (string[])     List of lowercase roles (e.g. abuse, registrant).
	 *   - name    (string)       Display name (vCard FN).
	 *   - email   (string|null)  Email if provided.
	 *   - phone   (string|null)  Phone number if provided.
	 *   - address (string|null)  Address label if provided.
	 */
	protected static function extract_rdap_contacts( $entities ): array {
		if ( ! is_array( $entities ) ) {
			return [];
		}

		$merged = [];
		$seen = [];

		self::walk_rdap_vcards( $entities, $merged, $seen );

		return array_values( $merged );
	}

	/**
	 * Recursively walks through RDAP entity records and extracts vCard contact data.
	 *
	 * Merges all entities by handle and collects the first found vCard fields per entity.
	 * Processes nested entities via depth-first traversal.
	 *
	 * @param array $items  A list of RDAP entity objects to process. Each item must be an associative array with optional 'handle', 'roles', 'vcardArray', and nested 'entities'.
	 * @param array &$merged Reference to the accumulator array, keyed by entity handle. Each value is a structured contact record:
	 *                       [
	 *                         'handle'  => string,
	 *                         'roles'   => string[],
	 *                         'name'    => string,
	 *                         'email'   => string|null,
	 *                         'phone'   => string|null,
	 *                         'address' => string|null,
	 *                       ]
	 * @param array &$seen   Reserved for future use (e.g. to track already-processed entity handles and avoid redundant processing in cyclic structures).
	 *
	 * @return void
	 */
	protected static function walk_rdap_vcards( array $items, array &$merged, array &$seen ) {

		foreach ( $items as $entity ) {

			if ( ! is_array( $entity ) ) {
				continue;
			}

			$handle = isset( $entity['handle'] ) ? (string) $entity['handle'] : md5( serialize( $entity ) );

			if ( ! isset( $merged[ $handle ] ) ) {
				$merged[ $handle ] = [
					'handle'  => $handle,
					'roles'   => [],
					'name'    => '',
					'email'   => null,
					'phone'   => null,
					'address' => null,
				];
			}

			$record = &$merged[ $handle ];

			if ( isset( $entity['roles'] ) && is_array( $entity['roles'] ) ) {
				$record['roles'] = array_unique( array_merge( $record['roles'], array_map( 'strtolower', $entity['roles'] ) ) );
			}

			if ( isset( $entity['vcardArray'][1] ) && is_array( $entity['vcardArray'][1] ) ) {
				foreach ( $entity['vcardArray'][1] as $vcard ) {
					if ( count( $vcard ) !== 4 ) {
						continue;
					}
					list( $field, $params, , $value ) = $vcard;

					switch ( strtolower( $field ) ) {
						case 'fn':
							if ( ! $record['name'] ) {
								$record['name'] = (string) $value;
							}
							break;
						case 'email':
							if ( ! $record['email'] ) {
								$record['email'] = (string) $value;
							}
							break;
						case 'tel':
							if ( ! $record['phone'] ) {
								$record['phone'] = (string) $value;
							}
							break;
						case 'adr':
							if ( ! $record['address'] && isset( $params['label'] ) ) {
								$record['address'] = (string) $params['label'];
							}
							break;
					}
				}
			}

			if ( isset( $entity['entities'] ) && is_array( $entity['entities'] ) ) {
				self::walk_rdap_vcards( $entity['entities'], $merged, $seen );
			}
		}
	}

	/**
	 * Parses the 'events' array from RDAP and returns structured entries.
	 *
	 * @param array|null $events Raw RDAP events array.
	 *
	 * @return array[] Each item contains:
	 *   - action (string) Machine-readable action (e.g. "registration").
	 *   - label  (string) Human-readable label (e.g. "Registered").
	 *   - date   (string) ISO 8601 date string.
	 */
	protected static function extract_rdap_events( $events ): array {
		if ( ! is_array( $events ) ) {
			return [];
		}

		static $event_labels = [
			'registration' => 'Registered',
			'last changed' => 'Last updated',
			'expiration'   => 'Expires on',
			'deletion'     => 'Deleted',
			'reallocation' => 'Reallocated',
			'reassignment' => 'Reassigned',
			'last checked' => 'Last checked',
		];

		$result = [];

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$action = isset( $event['eventAction'] ) ? strtolower( trim( (string) $event['eventAction'] ) ) : '';
			$date = isset( $event['eventDate'] ) ? (string) $event['eventDate'] : '';

			if ( $action === '' || $date === '' ) {
				continue;
			}

			$label = $event_labels[ $action ] ?? ucwords( str_replace( '_', ' ', $action ) );

			$result[] = [
				'action' => $action,
				'label'  => $label,
				'date'   => $date,
			];
		}

		return $result;
	}

	/**
	 * Determines the RIR source from RDAP response data.
	 *
	 * @param array $rdap_data Full decoded RDAP response.
	 *
	 * @return string One of: 'ARIN', 'RIPE', 'APNIC', 'LACNIC', 'AFRINIC', or 'UNKNOWN'
	 */
	protected static function detect_rir_source( array $rdap_data ): string {
		static $rir_domains = [
			'arin.net'    => 'ARIN',
			'ripe.net'    => 'RIPE',
			'apnic.net'   => 'APNIC',
			'lacnic.net'  => 'LACNIC',
			'afrinic.net' => 'AFRINIC',
		];

		if ( ! empty( $rdap_data['links'] ) && is_array( $rdap_data['links'] ) ) {
			foreach ( $rdap_data['links'] as $link ) {
				if ( ! is_array( $link ) || empty( $link['href'] ) ) {
					continue;
				}
				foreach ( $rir_domains as $domain => $rir ) {
					if ( stripos( $link['href'], $domain ) !== false ) {
						return $rir;
					}
				}
			}
		}

		if ( ! empty( $rdap_data['port43'] ) ) {
			foreach ( $rir_domains as $domain => $rir ) {
				if ( stripos( $rdap_data['port43'], $domain ) !== false ) {
					return $rir;
				}
			}
		}

		return 'UNKNOWN';
	}

	/**
	 * Extracts the country name from the registrant entity.
	 *
	 *  This method attempts to extract a human-readable country name from the 'label' field of the
	 *  'adr' component in a registrant's vCard. It also includes a fallback to the standard 7-element
	 *  address array format defined in RFC 6350.
	 *
	 * @param array $entities Indexed array of RDAP entity associative arrays.
	 *
	 * @return string|null Country code, or null if not found.
	 */
	protected static function extract_country_from_registrant( array $entities ) {

		foreach ( $entities as $entity ) {

			if ( empty( $entity['roles'] ) || ! in_array( 'registrant', $entity['roles'], true ) ) {
				continue;
			}

			if ( empty( $entity['vcardArray'] ) || ! is_array( $entity['vcardArray'] ) ) {
				continue;
			}

			$vcard_data = isset( $entity['vcardArray'][1] ) && is_array( $entity['vcardArray'][1] )
				? $entity['vcardArray'][1]
				: [];

			foreach ( $vcard_data as $vcard_item ) {
				if ( ! is_array( $vcard_item ) || $vcard_item[0] !== 'adr' ) {
					continue;
				}

				$meta = isset( $vcard_item[1] ) && is_array( $vcard_item[1] ) ? $vcard_item[1] : [];
				$value = isset( $vcard_item[3] ) && is_array( $vcard_item[3] ) ? $vcard_item[3] : [];

				// Try extracting from 'label'
				if ( ! empty( $meta['label'] ) ) {
					$raw = $meta['label'];
					$parts = preg_split( '/[\n\r,]+/', $raw );
					$parts = array_filter( array_map( 'trim', $parts ), 'strlen' );
					if ( ! empty( $parts ) ) {
						$country = array_pop( $parts );
						return crb_get_country_code( $country );
					}
				}

				// Fallback to 7-element address array
				if ( count( $value ) === 7 ) {
					$country = trim( $value[6] );
					if ( $country !== '' ) {
						return crb_get_country_code( $country );
					}
				}
			}
		}

		return null;
	}

	/**
	 * Renders RDAP data as a multi-column HTML layout using a configuration array.
	 *
	 * The layout configuration ($layout_config) maps any number of columns
	 * to RDAP field keys with labels. Each column becomes a separate HTML table.
	 *
	 * Format:
	 * [
	 *     'column_id' => [ 'field_key' => 'Label', ... ],
	 *     ...
	 * ]
	 *
	 * Special formatting:
	 * - Emails render as mailto: links
	 * - Addresses render as Google Maps links
	 * - Arrays like contacts, events, and remarks are supported
	 *
	 * @param object $data RDAP data object (decoded from JSON).
	 * @param array $layout_config Optional layout definition. Defaults to a two-column layout.
	 *
	 * @return string HTML content ready to render.
	 */
	static function render_html_view( object $data, array $layout_config = array() ): string {
		// Default layout config if not provided
		if ( ! $layout_config ) {
			$layout_config = [
				'rdap_column_1' => [
					'owner_name'    => 'Owner',
					'asn'           => 'ASN',
					'rir_source'    => 'RIR Source',
					'cidr'          => 'CIDR Range',
					'block_type'    => 'Block Type',
					'abuse_email'   => 'Abuse Email',
					'abuse_address' => 'Abuse Address',
					'country'       => 'Country',
					'contacts'      => 'Contact'
				],
				'rdap_column_2' => [
					'events'  => 'Event',
					'remarks' => 'Remark'
				]
			];
		}

		ob_start();
		echo '<div class="crb-rdap-container">';

		foreach ( $layout_config as $column_id => $fields ) {
			echo '<div class="crb-rdap-column">';
			echo '<table class="crb-rdap-table">';

			foreach ( $fields as $key => $label ) {
				switch ( $key ) {
					case 'block_type':
						if ( is_array( $data->block_type ?? null ) ) {
							$desc = $data->block_type['description'] ?? '';
							echo '<tr><td>' . htmlspecialchars( $label ) . '</td><td>' . htmlspecialchars( $desc ) . '</td></tr>';
						}
						break;

					case 'contacts':
						if ( ! empty( $data->contacts ) && is_array( $data->contacts ) ) {
							foreach ( $data->contacts as $contact ) {
								if ( ! is_array( $contact ) ) {
									continue;
								}

								$roles = isset( $contact['roles'] ) ? implode( ', ', array_map( 'ucfirst', $contact['roles'] ) ) : 'Contact';
								$parts = [];

								if ( ! empty( $contact['name'] ) ) {
									$parts[] = htmlspecialchars( $contact['name'] );
								}

								if ( ! empty( $contact['email'] ) ) {
									$parts[] = 'Email: <a href="mailto:' . crb_escape_email( $contact['email'] ) . '">' . htmlspecialchars( $contact['email'] ) . '</a>';
								}

								if ( ! empty( $contact['phone'] ) ) {
									$parts[] = 'Phone: ' . htmlspecialchars( $contact['phone'] );
								}

								if ( ! empty( $contact['address'] ) ) {
									$clean_address = preg_replace( '/\s+/', ' ', $contact['address'] ); // Replace all whitespace (including \n) with a space
									$map_link = 'https://www.google.com/maps?q=' . rawurlencode( trim( $clean_address ) );
									$parts[] = 'Address: <a href="' . crb_escape_url( $map_link ) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars( $clean_address ) . '</a>';
								}

								echo '<tr><td>' . htmlspecialchars( $roles ) . '</td><td>' . implode( '<br>', $parts ) . '</td></tr>';
							}
						}
						break;

					case 'events':
						if ( ! empty( $data->events ) && is_array( $data->events ) ) {
							foreach ( $data->events as $event ) {
								if ( ! is_array( $event ) ) {
									continue;
								}

								$label_text = $event['label'] ?? ucfirst( $event['action'] ?? 'Event' );
								$date = $event['date'] ?? '';

								if ( $date ) {
									$ts = strtotime( $date );
									if ( $ts ) {
										$date = cerber_auto_date( $ts, false ) ?: $date;
									}
								}

								echo '<tr><td>' . htmlspecialchars( $label_text ) . '</td><td>' . htmlspecialchars( $date ) . '</td></tr>';
							}
						}
						break;

					case 'remarks':
						if ( ! empty( $data->remarks ) && is_array( $data->remarks ) ) {
							foreach ( $data->remarks as $remark ) {
								if ( ! is_array( $remark ) ) {
									continue;
								}

								$title = $remark['title'] ?? $label;
								$desc = '';

								if ( ! empty( $remark['description'] ) && is_array( $remark['description'] ) ) {
									$desc = implode( '<br>', array_map( 'htmlspecialchars', $remark['description'] ) );
								}

								echo '<tr><td>' . htmlspecialchars( $title ) . '</td><td>' . $desc . '</td></tr>';
							}
						}
						break;

					default:
						if ( property_exists( $data, $key ) && ! empty( $data->$key ) ) {
							$value = (string) $data->$key;

							if ( strpos( $key, 'email' ) !== false ) {
								$value = '<a href="mailto:' . crb_escape_email( $value ) . '">' . htmlspecialchars( $value ) . '</a>';
							}
							elseif ( strpos( $key, 'address' ) !== false ) {
								$clean_address = preg_replace( '/\s+/', ' ', $value ); // Replace all whitespace (including \n) with a space
								$map_link = 'https://www.google.com/maps?q=' . rawurlencode( trim( $clean_address ) );
								$value = '<a href="' . crb_escape_url( $map_link ) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars( $clean_address ) . '</a>';
							}
							elseif ( strpos( $key, 'owner_name' ) !== false ) {
								$clean_value = preg_replace( '/\s+/', ' ', $value ); // Replace all whitespace (including \n) with a space
								$the_link = 'https://www.google.com/search?q=' . rawurlencode( trim( $clean_value ) );
								$value = '<a href="' . crb_escape_url( $the_link ) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars( $clean_value ) . '</a>';
							}
							else {
								$value = nl2br( htmlspecialchars( $value ) );
							}

							echo '<tr><td>' . htmlspecialchars( $label ) . '</td><td>' . $value . '</td></tr>';
						}
						break;
				}
			}

			echo '</table>';
			echo '</div>';
		}

		echo '</div>';

		return ob_get_clean();
	}
}