<?php
define('WP_CACHE', true); // Added by WP Rocket
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'vapet' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '#TmVKjWSZ+fTZwcu*h%>EDf8uJCJs[n6%BuaNym:h#hp@iGq.:y^dqojU*jTxHJZ' );
define( 'SECURE_AUTH_KEY',  'rBOr&!>][9]TD}7yZ._3wU %lUrZE^5[WVXtj1R}d&9rNIcLMeWIh^?6OxAvm.R>' );
define( 'LOGGED_IN_KEY',    '=,g,A4cA_8^+#;{RezaAhlRHY2))D(`mbSQF+{:hRP6%;sN}41YQ0PVY+9(7n=(_' );
define( 'NONCE_KEY',        'fQtim[#Dq3w7|r8n$pm|MM2p2&Lu$1]bZ747f|>Hx|MBQ_$3ts=op):~>[KB#7z;' );
define( 'AUTH_SALT',        'g|B *p(/cu~C|gdjS_I8NyTchf0=X)IbLgZR+w?q|&~4on_NYl3,J<FQ`&jZ$hK4' );
define( 'SECURE_AUTH_SALT', 'PPn=-lfgh4bH!VpIdT}9?Pq^X2W%}Se$ip3UOqtTu&Wg6-$#ZorvTa0cP2Cy8n88' );
define( 'LOGGED_IN_SALT',   'vqxm-hJ[K`Hq#3*VD-HO{rJd?!V%/TOeY*=e)2e)jEts*<XK]LKi5-sLEVK%zs{z' );
define( 'NONCE_SALT',       'XHyM]Ot|>&7FgWl#J:B:t)}A]B|*vz.9]wuI*LAbhacO`_]G4_Y[jG)Cj}RKQlg#' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
