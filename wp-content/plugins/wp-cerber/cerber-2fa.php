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
const CERBER_PIN_LENGTH = 4;
const CERBER_PIN_EXPIRES = 15;

final class CRB_2FA {
	static $token = '';
	static $user_set = null;

	/**
	 * Enforce 2FA for a user based on the plugin settings
	 *
	 * @param string $login User login
	 * @param WP_User $user
	 *
	 * @return bool|WP_Error
	 */
	static function main_controller( $login, $user ) {
		static $done = false;

		if ( $done ) {
			return 0;
		}

		$done = true;

		if ( crb_acl_is_white() ) {
			return 6;
		}

		if ( ( ! $user instanceof WP_User ) || empty( $user->ID ) ) {
			return new WP_Error( 'no-user', 'Invalid user data' );
		}

		self::remove_expired_cookies( $user->ID );

		$user_mode = self::get_user_meta_data( 'tfm', $user->ID );

        if ( $user_mode === 2 ) {
	        return 7;
		}

		if ( ( $coo = self::get_user_meta_data( 'tf_remember', $user->ID ) )
		     && self::get_user_days( $user->ID ) ) {

            // Check for remembered devices

			foreach ( $coo as $coo_name => $coo_data ) {
				if ( $coo_data[1] > time()
				     && cerber_get_cookie( $coo_name ) == $coo_data[0] ) {

					return 8;
				}
			}

		}

		if ( $user_mode == 1 ) {
			$go = true;
		}
		else {

			$u_roles = null;

			if ( ! empty( $user->roles ) ) {
				$u_roles = $user->roles;
			}
			else { // a backup way
				$data = get_userdata( $user->ID );
				if ( ! empty( $data->roles ) ) {
					$u_roles = $data->roles;
				}
			}

			if ( empty( $u_roles ) ) {
				return new WP_Error( 'no-roles', 'No roles found for the user #' . $user->ID );
			}

			if ( $go = self::check_role_policies( $user->ID, $u_roles ) ) {
				CRB_Globals::set_ctrl_setting( $go );
			}

		}

		if ( ! $go ) {
			return 9;
		}

		// This user must complete 2FA

		$login = (string) $login;

		$ret = self::initiate_2fa( $user, $login );

		if ( crb_is_wp_error( $ret ) ) {
			return $ret;
		}

		crb_sessions_update( $user->ID, self::get_session_token_hash(), array( 'mfa_status' => 1 ) );

		cerber_log( 400, $login, $user->ID );

		$home = cerber_get_home_url();

		if ( 2 < substr_count( $home, '/' ) ) {
            // Home page URL includes a subfolder. Make sure it contains trailing slash as well. Improves web servers compatibility.
			$home .= '/';
		}

		crb_safe_redirect( $home );
		exit;

	}

	/**
     * Check if 2FA must be enforced.
     *
     * @param int $user_id
	 * @param string[] $roles
	 *
	 * @return string|bool Non-empty string if the given user must complete 2FA, false otherwise
	 */
	private static function check_role_policies( $user_id, $roles ) {

		foreach ( $roles as $role ) {

			$policies = cerber_get_role_policies( $role );

			if ( empty( $policies['2famode'] )
			     || ( $policies['2famode'] == 2 && ! lab_lab() ) ) {
				continue;
			}
            elseif ( $policies['2famode'] == 1 ) {
				return '2famode';
			}

			if ( $history = self::get_user_meta_data( '2fa_history', $user_id ) ) {
				if ( ( $logins = crb_array_get( $policies, '2falogins' ) )
				     && ( $history[0] >= $logins ) ) {
					return '2falogins';
				}
				if ( ( $days = crb_array_get( $policies, '2fadays' ) )
				     && ( ( time() - $history[1] ) > $days * 24 * 3600 ) ) {
					return '2fadays';
				}
			}

			if ( $last_login = self::get_user_meta_data( 'last_login', $user_id ) ) {
				if ( crb_array_get( $policies, '2fanewip' ) ) {
					if ( $last_login['ip'] != cerber_get_remote_ip() ) {
						return '2fanewip';
					}
				}
				if ( crb_array_get( $policies, '2fanewnet4' ) ) {
					if ( cerber_get_subnet_ipv4( $last_login['ip'] ) != cerber_get_subnet_ipv4( cerber_get_remote_ip() ) ) {
						return '2fanewnet4';
					}
				}
				if ( crb_array_get( $policies, '2fanewua' ) ) {
					if ( $last_login['ua'] != sha1( crb_array_get( $_SERVER, 'HTTP_USER_AGENT', '' ) ) ) {
						return '2fanewua';
					}
				}
			}

			if ( $limit = crb_array_get( $policies, '2fasessions' ) ) {
				if ( $limit < crb_sessions_get_num( $user_id ) ) {
					return '2fasessions';
				}
			}

			if ( $last_login ) {
				if ( crb_array_get( $policies, '2fanewcountry' ) ) {
					if ( lab_get_country( $last_login['ip'], false ) != lab_get_country( cerber_get_remote_ip(), false ) ) {
						return '2fanewcountry';
					}
				}
			}
		}

		return false;
	}

	/**
     * Return user's meta data handled by WP Cerber
	 *
	 * @param string $field
	 * @param integer $user_id
	 *
	 * @return false|mixed
     *
     * @since 9.6.1
	 */
	private static function get_user_meta_data( $field, $user_id ) {
		static $pro = array( 'tfm', 'tf_remember', 'tfremember', '2fa_history', 'last_login' );

		if ( in_array( $field, $pro )
             && ! lab_lab() ) {
			return false;
		}

		if ( ! self::$user_set ) {
			self::$user_set = cerber_get_set( CRB_USER_SET, $user_id );
		}

		return crb_array_get( self::$user_set, $field );
    }

	/**
	 * Initiate 2FA process
	 *
	 * @param $user
	 * @param string $login
	 *
	 * @return bool|string|WP_Error
	 */
	private static function initiate_2fa( $user, $login = '' ) {

		if ( ! $pin = self::generate_pin( $user->ID ) ) {
			return new WP_Error( '2fa-error', 'Unable to create PIN for the user #' . $user->ID );
		}

		$data = array(
			'login'   => $login,
			'to'      => cerber_2fa_get_redirect_to( $user ),
			'ajax'    => cerber_is_wp_ajax(),
			'interim' => isset( $_REQUEST['interim-login'] ) ? 1 : 0,
		);

		self::update_2fa_data( $data, $user->ID );

		return $pin;

	}

	/**
	 * Generates PIN and its expiration
	 *
	 * @param $user_id
	 *
	 * @return bool|string
	 */
	private static function generate_pin( $user_id ) {

		$pin = substr( str_shuffle( '1234567890' ), 0, CERBER_PIN_LENGTH );

		$data = array(
			'pin'      => $pin,
			'expires'  => time() + CERBER_PIN_EXPIRES * 60,
			'attempts' => 0,
			'ip'       => cerber_get_remote_ip(),
			'ua'       => sha1( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'ua_raw'   => substr( trim( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 200 ),
			'2email'   => self::get_user_email( $user_id )
		);

		if ( self::update_2fa_data( $data, $user_id ) ) {

			self::send_user_pin( $user_id, $pin );

			return $pin;
		}

		return false;

	}

	/**
     * Pre-checks: make sure WordPress has properly identified the user.
     *
	 * @param $user_id
	 *
	 * @return void
	 */
	static function check_for_errors( $user_id ) {
		if ( ! cerber_get_post( 'the_2fa_nonce' )
		     || ! cerber_get_post( 'cerber_verify_pin' ) ) {
			return;
		}

		if ( ! $user_id ) {
			self::send_ajax_response( 'Authentication error: WordPress user is not identified.' );
		}
	}

	/**
	 * @param int $current_user_id Current user ID
     *
	 */
	static function restrict_and_verify( $current_user_id = null ) {
		static $done = false;

		if ( $done ) {
			return;
		}

    	$done = true;

		if ( ! $current_user_id
             && ! $current_user_id = get_current_user_id() ) {
			return;
		}

		$twofactor = self::get_2fa_data( $current_user_id );

		if ( empty( $twofactor['pin'] ) ) {
			return;
        }

		if ( crb_acl_is_white() ) {
			self::delete_2fa( $current_user_id );

			return;
		}

		// Check user settings again: they can be changed by admin after the user was enforced to complete 2FA

        $user_mode = self::get_user_meta_data( 'tfm', $current_user_id);

		if ( $user_mode === 2 ) {
			self::delete_2fa( $current_user_id, true );

			return;
		}
        elseif ( ! $user_mode ) {
			$user = wp_get_current_user();
	        if ( ! self::check_role_policies( $current_user_id, $user->roles ) ) {
				self::delete_2fa( $current_user_id );

				return;
			}
		}

		// Check the browser and user context

		if ( ( $sts = ( $twofactor['ip'] != cerber_get_remote_ip() ? 540 : 0 ) )
		     || ( $sts = ( $twofactor['ua'] != sha1( crb_array_get( $_SERVER, 'HTTP_USER_AGENT', '' ) ) ? 541 : 0 ) )
		     || ( $sts = ( cerber_is_ip_allowed() ? 0 : CRB_Globals::$act_status ) ) ) {

			self::delete_2fa( $current_user_id );
			cerber_user_logout( $sts );
			crb_redirect( get_home_url() );

			exit;
		}

		// User wants to abort 2FA?

		if ( $now = cerber_get_get( 'cerber_2fa_now' ) ) {
			$go = null;
			if ( $now == 'different' ) {
				$go = wp_login_url( ( ! empty( $twofactor['to'] ) ) ? urldecode( $twofactor['to'] ) : '' );
			}
			if ( $now == 'cancel' ) {
				$go = get_home_url();
			}
			if ( $go ) {
				cerber_user_logout( 28 );

				crb_redirect( $go );
				exit;
			}
		}

		if ( $twofactor['attempts'] > 5 ) {
			cerber_soft_block_add( cerber_get_remote_ip(), 721 );
			cerber_user_logout( 542 );

			crb_redirect( get_home_url() );
			exit;
		}

		$pin_expired = false;

        if ( $twofactor['expires'] < time() ) {
			self::generate_pin( $current_user_id );
			$pin_expired = true;
		}

		// The first step of verification, ajax
		if ( cerber_is_http_post() ) {
			self::process_ajax( $pin_expired );
		}

		// The second, final step of verification
		if ( cerber_is_http_post()
		     && ! empty( $twofactor['nonce'] )
		     && $_POST['cerber_tag'] === $twofactor['nonce']
		     && ( $pin = cerber_get_post( 'cerber_pin' ) )
		     && self::verify_pin( trim( $pin ) ) ) {

			self::delete_2fa( $current_user_id );

			cerber_log( CRB_EV_LIN, $twofactor['login'], $current_user_id, 27 );
			crb_update_login_history( $current_user_id, true );

			cerber_2fa_checker( true );

			self::save_remember_device( $current_user_id );

			crb_sessions_update( $current_user_id, self::get_session_token_hash(), array( 'mfa_status' => 2 ) );

			$url = ( ! empty( $twofactor['to'] ) ) ? $twofactor['to'] : get_home_url();

			crb_safe_redirect( $url );
			exit;
		}

		self::show_2fa_page();
		exit;
	}

	/**
	 * Returns the number of days for which we will remember the user's device.
	 * Picks the smallest number if the user has multiple roles.
	 *
	 * @param int $user_id
	 *
	 * @return int
	 *
	 * @since 9.5.7
	 */
	private static function get_user_days( $user_id = null ) {
		if ( ! $user_id ) {
			$user = wp_get_current_user();
		}
		else {
			$user = get_userdata( $user_id );
		}

		// User policies

		if ( lab_lab() ) {

	        $cus = cerber_get_set( CRB_USER_SET, $user->ID );

			if ( is_numeric( $cus['tfremember'] ?? false ) ) {
				return $cus['tfremember'];
			}
        }

        // Roles policies

		if ( ! $user->roles ) {
			return 0;
		}

		$list = array();

		foreach ( $user->roles as $role ) {
			$policies = cerber_get_role_policies( $role );
			$list[] = (int) $policies['2faremember'] ?? 0;
		}

		return min( $list );
	}

	/**
	 * @param int $user_id
	 *
	 * @return void
     *
     * @since 9.5.7
	 */
	private static function save_remember_device( $user_id ) {
		if ( cerber_get_post( 'cerber_trust_device' ) != 'yes'
		     || ! $days = self::get_user_days( $user_id ) ) {
			return;
		}

		$salt = crb_random_string( 8, 12 );

        // Unique name for all users and websites on the domain

		$name = 'crb_tf_' . sha1( $salt . cerber_get_site_url() . '|' . $user_id );

		$val = '(' . rand( 0, 99 ) . ')'; // Can be any value
		$until = time() + DAY_IN_SECONDS * $days; // expires
        $expires = $until + DAY_IN_SECONDS * rand( 0, 3 ); // add some randomness to make it harder to find expiration time

		if ( cerber_set_cookie( $name, $val, $expires, '', '', true ) ) {

			$cus = cerber_get_set( CRB_USER_SET, $user_id );
			$cook_list = ( isset( $cus['tf_remember'] ) && is_array( $cus['tf_remember'] ) ) ? $cus['tf_remember'] : array();
			$cook_list[ $name ] = array( $val, $until );
			$cus['tf_remember'] = $cook_list;

			cerber_update_set( CRB_USER_SET, $cus, $user_id );
		}

		self::remove_expired_cookies( $user_id );
	}

	/**
     * Removes stored but expired device cookies
     *
	 * @param int $user_id
	 *
	 * @return void
     *
     * @since 9.5.7
	 */
	static function remove_expired_cookies( $user_id ) {

		$cus = cerber_get_set( CRB_USER_SET, $user_id );
		$cook_list = ( isset( $cus['tf_remember'] ) && is_array( $cus['tf_remember'] ) ) ? $cus['tf_remember'] : array();

        if ( ! $cook_list ) {
			return;
		}

        $update = false;

        foreach ( $cook_list as $coo_name => $coo_data ) {
			if ( $coo_data[1] < time() ) {
				unset( $cook_list[ $coo_name ] );
				$update = true;
			}
		}

        if ( $update ) {
			$cus['tf_remember'] = $cook_list;
			cerber_update_set( CRB_USER_SET, $cus, $user_id );
		}
	}

	static function process_ajax( $pin_expired = false ) {
		if ( ( ! $nonce = cerber_get_post( 'the_2fa_nonce', '\w+' ) )
		     || ( ! $pin = cerber_get_post( 'cerber_verify_pin' ) ) ) {
			return;
		}

		$err = '';

		if ( ! wp_verify_nonce( $nonce, 'crb-ajax-2fa' ) ) {
			$err = 'Nonce error.';
		}
        elseif ( $pin_expired ) {
			$err = __( 'This verification PIN code is expired. We have just sent a new one to your email.', 'wp-cerber' );
		}
        elseif ( ! self::verify_pin( trim( $pin ), $nonce ) ) {
			$err = __( 'You have entered an incorrect verification PIN code', 'wp-cerber' );
		}

		self::send_ajax_response( $err );
	}

	/**
	 * @param string $err
	 *
	 * @return void
	 */
	private static function send_ajax_response( $err ) {
		echo json_encode( array( 'error' => $err ) );
		exit;
	}

	private static function verify_pin( $pin, $nonce = null ) {

		$data = self::get_2fa_data();

		if ( empty( $data['pin'] )
		     || $data['expires'] < time() ) {
			return false;
		}

		if ( (string) $pin === (string) $data['pin'] ) {
			$ret = true;
			if ( ! $nonce ) {
				return $ret;
			}
			$data['nonce'] = $nonce;
		}
		else {
			$data['attempts'] ++;
			$ret = false;
		}

		self::update_2fa_data( $data );

		return $ret;
	}

	static function show_2fa_page( $echo = true ) {
		@ini_set( 'display_errors', 0 );

		if ( ! defined( 'CONCATENATE_SCRIPTS' ) ) {
			define( 'CONCATENATE_SCRIPTS', false );
			define( 'CONCATENATE_SCRIPTS_BY_CRB', true );
		}

        // Workaround to avoid warning messages generated _wp_scripts_maybe_doing_it_wrong()
        global $wp_actions;
		$wp_actions['wp_enqueue_scripts'] = 1;
        // --------------------------------------------------------------------

		wp_enqueue_script( 'jquery' );

		ob_start();
		?>
        <!DOCTYPE html>
        <html style="height: 100%;">
        <head>
            <meta charset="UTF-8">
            <title><?php _e( 'Please verify that it’s you', 'wp-cerber' ); ?></title>
            <style>
                body {
                    height: 90%;
                    text-align: center;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #555;
                }
                #cerber_2fa_page {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    height: 90%;
                }
                #cerber_2fa_wrap {
                    text-align: center;
                    background-color: #eee;
                    border-top: solid 4px #ddd;
                    padding: 1.5em 3em 1.5em 3em;
                }
                #cerber_2fa_inner  {
                    width: 350px;
                }
                @media (-webkit-min-device-pixel-ratio: 2) and (max-width: 1000px),
                (min-resolution: 192dpi) and (max-width: 1000px), {
                    #cerber_2fa_inner {
                        width: 100%;
                    }
                }
                @media screen and (max-width: 900px) {
                    #cerber_2fa_inner {
                        /*width: 100%;*/
                    }
                }
                #cerber_2fa_msg {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    /*height: 80px;*/
                    padding: 40px 0 40px 0;
                    background-color: #FF4633;
                    color: #fff;
                    opacity: 0.9;
                }
                #cerber_2fa_title {
                    color:#000;
                }
                #cerber_2fa_info{
                    color: #333;
                }
                #cerber_2fa_form {
                    margin-bottom: 3em;
                }
                #cerber_2fa_form input[type="text"] {
                    color: #000;
                    text-align: center;
                    font-size: 1.4em;
                    letter-spacing: 0.1em;
                    padding: 5px;
                    min-width: 140px;
                    border-radius: 4px;
                }
                #cerber_2fa_form input[type="submit"] {
                    color: white;
                    background: #0085ba;
                    /*background: #0073aa;*/
                    border: 0;
                    font-size: 1em;
                    font-weight: 600;
                    letter-spacing: 0.05em;
                    text-align: center;
                    cursor: pointer;
                    padding: 1em;
                    min-width: 150px;
                    border-radius: 4px;
                }
                #cerber_2fa_trust_device {
                    margin: 1.3em 0;
                }
                #cerber_2fa_trust_device > * {
                    vertical-align: middle;
                    cursor: pointer;
                }
                #cerber_2fa_trust_device > input {
                    width: 1.1em;
                    height: 1.1em;
                    margin-right: 0.5em;
                }
            </style>
			<?php

            // -------------------------------------------
            // Because print_head_scripts() -> 'wp_print_scripts' hook -> wp_just_in_time_script_localization() -> AUTOSAVE_INTERVAL

            if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
				define( 'AUTOSAVE_INTERVAL', MINUTE_IN_SECONDS );
			}

            print_head_scripts();
			// -------------------------------------------

            ?>
            <script>

                var ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
                var nonce2fa = "<?php echo wp_create_nonce( 'crb-ajax-2fa' ); ?>";

            </script>
        </head>

        <body>
        <div id="cerber_2fa_page">
			<?php
			self::cerber_2fa_form();
			?>
        </div>
        </body>
        </html>

		<?php

		$html = ob_get_clean();
		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	static function send_user_pin( $user_id, $pin ) {
		if ( ! $pin ) {
			return false;
		}

		$subj = __( 'Please verify that it’s you', 'wp-cerber' );
		$body = array();

		//$body[] = 'We need to verify that it’s you because you are trying to sign-in from a different device or a different location or you have not signed in for a long time. If this wasn’t you, please reset your password immediately.';
		$body[] = __( "You or someone else trying to log into the website. We have to verify that it's you. If this wasn't you, please immediately reset your password to safeguard your account.", 'wp-cerber' );
		$body[] = __( 'Please use the following verification PIN code to verify your identity.', 'wp-cerber' ) . ' ' . sprintf( __( 'The code is valid for %s minutes.', 'wp-cerber' ), CERBER_PIN_EXPIRES );
		$body[] = '';
		$body[] = $pin;

		$user_data = get_userdata( $user_id );

		$info_level = cerber_get_user_policy( '2faemailinfo', $user_data );

		if ( $info_level ) {
			$ds = array();
			$ds[] = __( 'Login:', 'wp-cerber' ) . ' ' . $user_data->user_login;

			if ( $info_level == 2 ) {
				$ds[] = __( 'IP address:', 'wp-cerber' ) . ' ' . cerber_get_remote_ip();
				$ds[] = __( 'Hostname:', 'wp-cerber' ) . ' ' . @gethostbyaddr( cerber_get_remote_ip() );

				if ( $c = lab_get_country( cerber_get_remote_ip(), false ) ) {
					$ds[] = __( 'Location:', 'wp-cerber' ) . '' . crb_get_country_name( $c ) . ' (' . $c . ')';
				}
			}

			$ds[] = __( 'Browser:', 'wp-cerber' ) . ' ' . substr( strip_tags( crb_array_get( $_SERVER, 'HTTP_USER_AGENT', 'Not set' ) ), 0, 1000 );
			$ds[] = __( 'Date:', 'wp-cerber' ) . ' ' . cerber_date( time(), false );

			$body[] = '';
			$body[] = __( 'Here are the details of the sign-in attempt', 'wp-cerber' );
			$body[] = implode( "\n", $ds );
		}

		$body = implode( "\n\n", $body );

		$email = self::get_user_email( $user_id );

		$user_name = trim( $user_data->user_firstname . ' ' . $user_data->user_lastname );
		$to = $user_name ? '"' . $user_name . '" <' . $email . '>' : $email;

		$result = cerber_send_message( '2fa', array(
			'subj' => $subj,
			'text' => $body
		), array( 'email' => 1, 'pushbullet' => 0 ), true, array( 'email_recipients' => array( $to ) ) );

		if ( $result && ( $user_data->user_email != $email ) ) {
		    // TODO Should we send a notification to the main user email?
		}

		return true;
	}

	static function get_user_email( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( lab_lab() ) {
			$cus = cerber_get_set( CRB_USER_SET, $user_id );
			if ( $cus
			     && ( $email = crb_array_get( $cus, 'tfemail' ) )
			     && is_email( $email ) ) {
				return $email;
			}
		}

		$data = get_userdata( $user_id );

		return $data->user_email;
	}

	/**
     * Return all valid user PINs
     *
	 * @param $user_id
	 *
	 * @return string
	 */
	static function get_user_pin_info( $user_id ) {

		if ( ! $cus = cerber_get_set( CRB_USER_SET, $user_id ) ) {
			return '';
		}

		if ( ! $fa = crb_array_get( $cus, '2fa' ) ) {
			return '';
		}

		$pins = '';

		foreach ( $fa as $entry ) {
			if ( empty( $entry['pin'] )
			     || $entry['expires'] < time() ) {
				continue;
			}

			if ( $ua = $entry['ua_raw'] ?? '' ) {
				$ua_info = cerber_detect_browser( $ua ) . ' (' . crb_escape_html( $ua ) . ')';
			}
            else {
	            $ua_info = '';
            }

			$pins .= '<tr><td><code style="font-size: 110%;">' . $entry['pin'] . '</code></td><td>' . cerber_ago_time( $entry['expires'] ) . '</td><td>' . ( $entry['2email'] ?? '' ) . '</td><td>' . $ua_info . '</td></tr>';
		}

		if ( ! $pins ) {
			return '';
		}

		return '<div id="crb-admin-2fa-pins"><table><tr><td>PIN</td><td>Expires</td><td>Sent To</td><td>User Browser</td></tr>' . $pins . '</table></div>';

	}

	static function update_2fa_data( $data, $user_id = null ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$cus = cerber_get_set( CRB_USER_SET, $user_id );

		if ( ! is_array( $cus ) ) {
			$cus = array();
		}

		if ( ! isset( $cus['2fa'] ) ) {
			$cus['2fa'] = array();
		}

		$old = self::cerber_2fa_session_id();
		$token = self::get_session_token_hash();

        // Move existing data from the deprecated format

		if ( $existing = $cus['2fa'][ $old ] ?? false ) {
			unset( $cus['2fa'][ $old ] );
			$cus['2fa'][ $token ] = $existing;
		}

		$cus['2fa'][ $token ] = array_merge( $cus['2fa'][ $token ] ?? [], $data );

		return cerber_update_set( CRB_USER_SET, $cus, $user_id );
	}

	/**
     * Returns 2FA data for the current user session
     *
	 * @param int $current_user_id
	 *
	 * @return array
	 */
	static function get_2fa_data( $current_user_id = null ) {

        if ( ! $current_user_id ) {
			$current_user_id = get_current_user_id();
		}

		if ( ! $cus = cerber_get_set( CRB_USER_SET, $current_user_id ) ) {
			return array();
		}

		if ( empty( $cus['2fa'] ) ) {
			return array();
		}

		if ( $ret = $cus['2fa'][ self::get_session_token_hash() ] ?? false ) {
            return $ret;
		}

        // Deprecated format

		if ( $ret = $cus['2fa'][ self::cerber_2fa_session_id() ] ?? false ) {
			return $ret;
		}

		return array();
	}

	/**
     * Deletes all 2FA data for any given user, or the current session 2FA data of the current user
     *
	 * @param int $uid If $all is false, may be only the current user ID
	 * @param bool $all If false, deletes current session 2FA data for the current user
	 *
	 * @return void
	 */
	static function delete_2fa( $uid, $all = false ) {

		if ( ! $uid = absint( $uid ) ) {
			return;
		}

        $cus = cerber_get_set( CRB_USER_SET, $uid );

        if ( $cus && isset( $cus['2fa'] ) ) {
			if ( $all ) {
				unset( $cus['2fa'] );
			}
			else {
				unset( $cus['2fa'][ self::get_session_token_hash() ] );
				unset( $cus['2fa'][ self::cerber_2fa_session_id() ] );
			}

			cerber_update_set( CRB_USER_SET, $cus, $uid );
		}
	}

	/**
     * Return WordPress session token for the current user session
     *
	 * @return string
     *
     * @deprecated since 9.6.3.2
	 */
	static function cerber_2fa_session_id() {
		if ( self::$token ) {
			return self::$token;
		}

		return crb_get_session_token();
	}

	/**
     * Returns hashed WordPress session token for the current user session
     * The hash can be stored in the DB safely
     *
	 * @return string
     *
     * @since 9.6.3.2
	 */
	static function get_session_token_hash() {
		$token = ( self::$token ) ?: crb_get_session_token();

		return cerber_hash_token( $token );
	}

	static function cerber_2fa_form() {

		$max    = CERBER_PIN_LENGTH;
		$atts   = 'pattern="\d{' . $max . '}" maxlength="' . $max . '" size="' . $max . '" title="' . __( 'only digits are allowed', 'wp-cerber' ) . '"';
		$email = self::get_user_email();
		$text = __( "We've sent a verification PIN code to your email", 'wp-cerber' ) . ' ' . cerber_mask_email( $email ) .
		        '<p>'. __( 'Enter the code from the email in the field below.', 'wp-cerber' ).'</p>';
		//$change = '<a href="' . cerber_get_home_url() . '/?cerber_2fa_now=different">' . __( 'Sign in with a different account', 'wp-cerber' ) . '</a>';
		$change = '<a href="' . cerber_get_home_url() . '/?cerber_2fa_now=different">' . __( 'Try again', 'wp-cerber' ) . '</a>';
		$cancel = '<a href="' . cerber_get_home_url() . '/?cerber_2fa_now=cancel">' . __( 'Cancel', 'wp-cerber' ) . '</a>';
		$links = '<p>'.__( 'Did not receive the email?', 'wp-cerber' ) .'</p>'. $change . ' ' . __( 'or', 'wp-cerber' ) . ' ' . $cancel;

		$trust = '';

		if ( $days = self::get_user_days() ) {
			$trust = '<p id="cerber_2fa_trust_device"><input name="cerber_trust_device" id="trust_this_device" type="checkbox" value="yes"><label for="trust_this_device">' . sprintf( __( 'Remember this device for %d days', 'wp-cerber' ), $days ) . '</label></p>';
		}

		?>

        <div id="cerber_2fa_msg"></div>
        <div id="cerber_2fa_box">
            <div id="cerber_2fa_wrap">
                <div id="cerber_2fa_inner">
                    <h1 id="cerber_2fa_title"><?php _e( "Verify it's you", 'wp-cerber' ); ?></h1>
                    <div id="cerber_2fa_info"><?php echo $text; ?></div>
                    <form id="cerber_2fa_form" method="post" data-verified="no">
                        <p><input required type="text" name="cerber_pin" <?php echo $atts; ?> ></p>
                        <p><input type="hidden" name="cerber_tag" value="2FA"></p>

                        <?php echo $trust; ?>

                        <p><input type="submit" value="<?php _e( 'Verify', 'wp-cerber' ); ?>"></p>
                    </form>
                </div>
            </div>
			<?php echo $links; ?>
        </div>
        <script>
            jQuery( function( $ ) {
                let cform = $('#cerber_2fa_form');
                let umsg = 'cerber_2fa_msg';
                cform.submit(function (event) {
                    crb_hide_user_msg();
                    if (cform.data('verified') === 'yes') {
                        return;
                    }
                    event.preventDefault();
                    $.post(ajaxurl, {
                            the_2fa_nonce: nonce2fa,
                            cerber_verify_pin: $(this).find('input[type="text"]').val()
                        },
                        function (server_response, textStatus, jqXHR) {
                            let server_data = JSON.parse(server_response);
                            if (server_data.error.length === 0) {
                                cform.find('[name="cerber_tag"]').val(nonce2fa);
                                cform.data('verified', 'yes');
                                cform.submit();
                            }
                            else {
                                crb_display_user_msg(server_data['error']);
                            }
                        }
                    ).fail(function (jqXHR, textStatus, errorThrown) {
                        let err = errorThrown + ' ' + jqXHR.status;
                        alert(err);
                        console.error('Server Error: ' + err);
                    });
                });

                function crb_display_user_msg(msg) {
                    $('#' + umsg).fadeIn(500).html(msg);
                    setTimeout(function (args) {
                        crb_hide_user_msg();
                    }, 5000);
                }

                function crb_hide_user_msg() {
                    document.getElementById(umsg).style.display = "none";
                }
            });
        </script>
		<?php
	}
}

/**
 * @param $user WP_User
 *
 * @return string
 */
function cerber_2fa_get_redirect_to( $user ) {
	if ( isset( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = $_REQUEST['redirect_to'];
		$requested_redirect_to = $redirect_to;
	}
	else {
		$redirect_to = admin_url();
		$requested_redirect_to = '';
	}

	$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

	return $redirect_to;
}

/**
 * Verify that 2FA on the website works
 * If it works, 2FA can be enabled for admins
 *
 */
function cerber_2fa_checker( $save = false ) {
	if ( $save ) {
		cerber_update_set( 'cerber_2fa_is_ok', 1, null, false );
	}
	else {
		if ( cerber_get_set( 'cerber_2fa_is_ok', null, false ) ) {
			return true;
		}

		return false;
	}
}
