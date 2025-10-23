<?php
/**
 * The base configuration for WordPress
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', true);
define( 'WPCACHEHOME', '/opt/lampp/htdocs/wordpress/wp-content/plugins/wp-super-cache/' );
define( 'DB_NAME', 'cursoemvideowp' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '8`0!3sCx)bO8?7ddj~e,[Rbl=c~Eoy9 .Nd5,1+Azb$#Q1iG~9j9Z3uz_3+&N_F+' );
define( 'SECURE_AUTH_KEY',  'mc<+6*h[RE+~H_/z~S}svZX[#B],Bt@_F6{%,<2Tx8[P]I*Loz:z@5MFUh5&n:k@' );
define( 'LOGGED_IN_KEY',    'jy;iUp3Yr.c8B)<?YU|`GOP?q/Sav?t~:83At`#qGg[p18O0B[bR5x*>,}|Fu7Ly' );
define( 'NONCE_KEY',        'gdS#vdJ(/Uk?`l-&z$wl6=`mY)%~IU]90O*Nwd,ylaru_{<C6+,?o#j-U[ev;i9O' );
define( 'AUTH_SALT',        'g(</2km_)?U)/`c>#L5#(rjR&=<e%]$34lh_GiU .T1o,#^Kjkm9mAp7>).4<&vi' );
define( 'SECURE_AUTH_SALT', '_s<c}hhRZK%8=A?aP4)iuplD6o1!x9q%i.eZMoiYoGFe_{3S(9`gM!bs$vx{s<Qd' );
define( 'LOGGED_IN_SALT',   '6758YK<i_#z8N5NOya|$Lh+;MWhEPcMISNLJ)E^=TgXyww;dptGNLt:2)vP8q>GE' );
define( 'NONCE_SALT',       '0w+mB<4o|fhEdK&j8GzN cOygZR$~qOk<xhD$7&/-b#jw};x7^hU1^oaAsuPP[EL' );

/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 */
define( 'FS_METHOD', 'direct' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
