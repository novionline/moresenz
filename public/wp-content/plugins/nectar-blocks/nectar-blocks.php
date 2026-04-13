<?php

/**
 * Plugin Name:       Nectarblocks
 * Description:       Step into the future of WordPress with Nectarblocks, where innovation meets seamless design. Unleash the full potential of your website by transforming the core WordPress editor into a dynamic and robust full-site editor.
 * Version:           2.5.4
 * Requires at least: 6.2
 * Tested up to:      6.8.0
 * Requires PHP:      7.4
 * Author:            NectarBlocks
 * Author URI:        https://nectarblocks.com/
 * License:           Custom license
 * License URI:       https://nectarblocks.com/license
 * Text Domain:       nectar-blocks
 * Domain Path:       /languages
 */

namespace Nectar;

$autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once( $autoload );
require_once( 'nectar-vars.php' );

use Nectar\Plugin;

define( 'NECTAR_BLOCKS_VERSION', '2.5.4' );
// "/var/www/html/wp-content/plugins/plugin/"
define( 'NECTAR_BLOCKS_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
// http://localhost:1000/wp-content/plugins/plugin/build
define( 'NECTAR_BLOCKS_BUILD_PATH', plugins_url( NECTAR_BLOCKS_FOLDER_NAME . '/build' ) );
// http://localhost:1000/wp-content/plugins/plugin/
define( 'NECTAR_BLOCKS_PLUGIN_PATH', plugins_url( NECTAR_BLOCKS_FOLDER_NAME ) );
define( 'NECTAR_BLOCKS_FILE', __FILE__ );

$plugin = new Plugin();
$plugin->init();
