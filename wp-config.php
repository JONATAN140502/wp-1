<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
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
define( 'DB_NAME', 'wp' );

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
define( 'AUTH_KEY',         'cLiQ*@awkhfhGY87I9p M1.Nv*wo1Z%<O|yf,L^Q4O 7/(ccP@m^OP%dvAuIOZ>Y' );
define( 'SECURE_AUTH_KEY',  'n@Nn?T/nHY:!-bi!dP`FChIdSJ,C[0$X5G$T&z:R?4Equ!)h!?<dwp&SI+<u2@FM' );
define( 'LOGGED_IN_KEY',    ',cBx A#I]G`0eE1pS@-&K*38JLR2R*j=*6<y;<MP3cjbdO-)0H.;J$@wK,HGcw!g' );
define( 'NONCE_KEY',        '2#B:J7q8]<;TVyf;Grzkg{^L|>xefngN|<=t[RB!2LgR qqf]bfYAF0&s2/;OAPI' );
define( 'AUTH_SALT',        ';om*YG5>#R]2OOHpSF_r7Mn;,W#E~k+869fU3h*Yr:qS=f4/tW9.*k-`&.5t4J#}' );
define( 'SECURE_AUTH_SALT', ']OBhEJ -}!W[r=rDdyoD_-KY/r:`18=!o&>.m-O_-a%mqbKc;mf5:D^xXMkDlOEf' );
define( 'LOGGED_IN_SALT',   '9SK,<$`X3HoakWe9Vy`Qhyzwu6iM,Sb[ou ^l/b]`>K =CIuf6O8F l.kyOddE(h' );
define( 'NONCE_SALT',       're7SK(rB?eIf(|XP`)oHa7F&<390zn06T%NCRIu 4lA#Ot/<iGtd1#j_Hn;]Q)8r' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
