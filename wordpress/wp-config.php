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
define( 'DB_NAME', 'wordpress' );

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
define( 'AUTH_KEY',         '~QY*0}&hSoE{Gryg#V%~*<Q,fYei&,jA6F Y6be.t:tnQflIADi4sy5<po@59bVr' );
define( 'SECURE_AUTH_KEY',  'TLP ^Q^yB~YgPa7oog*_VGnr4O%@9eQm5?8Kj]v__WtD$@e#h{<%&MTaT>mX2<~N' );
define( 'LOGGED_IN_KEY',    '|uK4GDc{HTY.RbyzJM?(UJJ1^}9LT.G]TdXR/N{edkh:Vdf@sQ:Xd.Tsc$YyOxfZ' );
define( 'NONCE_KEY',        '<*vr>P_=R3n8wU_JTho:/VD,SDa35~WK)PES] xZ.*% =C6A6N5A>GpR6fs!oYI7' );
define( 'AUTH_SALT',        'JK:^4(-~/OV@~7wc9l]J=-]cvtQ i#A`*+m:M_z+Q_i/VjIEi8O.5)NcZ3=pofBs' );
define( 'SECURE_AUTH_SALT', 'Vu@!RU:-1Y 4U#bj,y@DiEHsdY-&YW<EC$:F_PL7N6f}9^ yS}$v%by);L4JJgVS' );
define( 'LOGGED_IN_SALT',   '^Z!}%NdMX0H4*k,P<*(0`:st8CrKcbMp%8>F18n1;tIYjR7k!fD33mH;snxl%s3K' );
define( 'NONCE_SALT',       '-wr}U@F,,Z@8*%?nu3kZ.)7>4l@ntC6$k#+}ORZC9j~|Fk+YltMf5S8x8xwwzL!j' );

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
