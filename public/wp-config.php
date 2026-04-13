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
define( 'DB_NAME', 'moresenz_loc' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1:3308' );

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
define( 'AUTH_KEY',         'lF~,!n3K#QNt52M(=0DUy*9goV%t_iyR!/`s?Vox41;Oyx!]uY_V]?4DH]vzQO#I' );
define( 'SECURE_AUTH_KEY',  'KPS|CC7Y!Z_j_-[xF1_KRU.yU,n!! ;gO%})Ns-%jZ8#S&&9*;_,[j9.[-MSw*88' );
define( 'LOGGED_IN_KEY',    ',(6_:QXl[_jV$S/oeI9XP Jp&%}I}M%^^c4sJDNIgg9>qTvk,,Q!ihj9iCCa6&%T' );
define( 'NONCE_KEY',        'z:.`U7>9A~~nahU+s[hp.U2Wx5N 2KcL2=^lh5FbjF ;4vU#3ATEYT8lGFciTGQf' );
define( 'AUTH_SALT',        'rJg IhmWF^>+ebwI[K`tuzv[SN* VJdaBOZ_w>8QIvyePKrotzPX-uItMB|L=kdh' );
define( 'SECURE_AUTH_SALT', '<~7^_` - Ax2hj?qt4,6)Gfyku8U.E]^OT]1`@$65w_z.Au6@[UWwd~Yel?BU0bi' );
define( 'LOGGED_IN_SALT',   '%idw$V} dDQVrSlh}qVz}NJ*2]E+,>LFVRH.jCUp.3GxE0F~7]WfMZWnp6Kjofy*' );
define( 'NONCE_SALT',       'VRKkG|+`Gv*.%qAADTK5[kVfGLN$~5H6U@I@RbJ,6i7s6J;*lOam%pW-/y +h?.P' );

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
$table_prefix = 'ms_';

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

$isDev = true;
@ini_set('log_errors', $isDev ? 'On' : 'Off');
@ini_set('display_errors', 'Off');
define('WP_DEBUG', $isDev ? true : false);
define('WP_DEBUG_LOG', $isDev ? true : false);
define('WP_DEBUG_DISPLAY', false);

/** Define WP environment **/
define('WP_ENVIRONMENT_TYPE', $isDev ? 'development' : 'production');

/** Prevent editing by Admin -> Appearance -> Editor **/
define('DISALLOW_FILE_EDIT', true);

/** Prevent WP Schedule System **/
define('DISABLE_WP_CRON', false);

/** Prevent concatenate scripts in the admin **/
define('CONCATENATE_SCRIPTS', false);

/** Disable auto-update **/
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

/** Set default theme **/
define('WP_DEFAULT_THEME', 'nectar-blocks-theme-child');

/** Set memory limits */
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

/** Set maximal number of post revisions to keep */
define('WP_POST_REVISIONS', 50);

/** Redis configuration */
define('WP_REDIS_DATABASE', '1');
define('WP_REDIS_PREFIX', 'ms_prod');
define('WP_REDIS_MAXTTL', 2419200);
define('WP_REDIS_READ_TIMEOUT', 5);
define('WP_REDIS_DISABLE_METRICS', false);
define('WP_REDIS_DISABLED', true);

/* Add any custom values between this line and the "stop editing" line. */

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
