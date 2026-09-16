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
define( 'DB_NAME', 'lttheme' );

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
define( 'AUTH_KEY',         '0}4CEYo:sr:nnkN=;= fQ(Ey=wVgx2oj}BOm@;@idEHFl$4T3U.U)(@UH04YkI`p' );
define( 'SECURE_AUTH_KEY',  '.yYE1uI>(N#W$@m=z1cr!b~Lkji.7gzso.+/$w [ytHflhWU_XkZUy]|PxBx.zH2' );
define( 'LOGGED_IN_KEY',    'RXo(/mT;cVo1(rmAy<SqeauOa%lxQzB0`;=Xp#1zY6^MvZM}gGwaB]$9<e^=^zt|' );
define( 'NONCE_KEY',        'JL3~,aLtx,p!Twq6nTu=#~b$NB|qxaLzGpAsyAz_aZs%kGOc~e`]&_ZW:<)x^kH@' );
define( 'AUTH_SALT',        'z$2r<Fc$/1Qrq$&`&HaD*a-;iYe|5C5FYaBb!;^VD! {jDjCG9lU)7fYCCS/`X5|' );
define( 'SECURE_AUTH_SALT', '(t]^:PuG+$[7l7;=`7q=N!t$(*!)`~Kq![zHkM&o]3Rg,|o{Q0lAtO;w*!t8(;oh' );
define( 'LOGGED_IN_SALT',   'FQFOBKa thv)GpzOIH(1:c*p/_/L]L!q00Be <)Ku0W_Q|(K.5)E0GU&FA}KG-Sh' );
define( 'NONCE_SALT',       '|^e T!Dm{%o$4md!N^!lI7{JTe1KerZX~#e!Fq65PbQM=P&Q5NmH] Lwl=(cT<<:' );

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
