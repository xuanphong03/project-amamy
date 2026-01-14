<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'VN_=pbUIFn?xsO&=g<DK<-*CZsF$@D#Nb)u:p2%kJ.GidB3BYC@$%^=-sk8}p8=9' );
define( 'SECURE_AUTH_KEY',   'c9RC|fPi&$n#hb+;2^OfK<u]1nd{qw35x%niUH2Eu_o%8,0dvA|O:y*[#{F=Jl:+' );
define( 'LOGGED_IN_KEY',     'J[p=J`LUuV7qnxw<!dWq/d^d+vK:/!#4!:6=4~To$c}Cw5p>d;ym9)<n!T)9Bm2Y' );
define( 'NONCE_KEY',         '2UM$*pO)T|0O:4_oSU~Ohd@S=iaOUTzZ)8TvK]rr9^=T_S+#F!<>I 8vd9!irCjk' );
define( 'AUTH_SALT',         'lh#ZWknQMHcgtmgn!v[{fII[p*Y 1BYS Cn#lR]Dy_c$UT}%:?$9w/jCvAL^vP0d' );
define( 'SECURE_AUTH_SALT',  'y,hN`v.~oP0E]}%?]bVKcuY-l{Z[|L L>}hpoe~R<JGLV/31o_BJM3Dvb,^B<thA' );
define( 'LOGGED_IN_SALT',    '7a4oIF|#WF##(3D:6=zpL>?Ce.avv-82:M^RURbFGOgFR[g#5,)q,m2 fTA^b){*' );
define( 'NONCE_SALT',        'I|2y)l3le8cz1d7Dd=Bk }`?/KWt():SzB=He 3$ML&H;Dayqig|RxbxiGIJ_S>Y' );
define( 'WP_CACHE_KEY_SALT', '~D+tf/W@Wvx://S!5m<haVv](Ig^2){ysW-u,fCsT4;bdF-cs3:!^B=.q(MT]`F~' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
// if ( ! defined( 'WP_DEBUG' ) ) {
// 	define( 'WP_DEBUG', false );
// }

// define( 'WP_DEBUG_LOG', false );
// define( 'WP_DEBUG_DISPLAY', false );

// define( 'WP_ENVIRONMENT_TYPE', 'local' );
// /* That's all, stop editing! Happy publishing. */


define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
error_reporting(0);

define( 'WP_ENVIRONMENT_TYPE', 'production' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
