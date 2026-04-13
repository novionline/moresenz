<?php

namespace Nectar;

use Nectar\Global_Settings\Global_Settings_Register;
use Nectar\Editor\Blocks;
use Nectar\Editor\Post_Meta;
use Nectar\API\Router;
use Nectar\Admin_Panel\Panel;
use Nectar\Render\Render;
use Nectar\Update\NectarBlocksUpdater;
use Nectar\Global_Sections\Global_Sections;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Menu_Options\Menu_Options_Register;
use Nectar\Notifications\Notifications_Register;
use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Portfolio\Portfolio_Register;
use Nectar\Migration\Migration_Runner;
use Nectar\Global_Settings\Nectar_Blocks_Options;
use Nectar\Utilities\Log;

class Plugin {
  function __construct() {}

  public function init() {
    $welcome = new Welcome();
    $global_settings = new Global_Settings_Register();
    $migration_runner = new Migration_Runner();
    $this->check_for_upgrade($migration_runner);
    $this->on_new_install();

    $render = new Render();
    $blocks = new Blocks();
    $router = new Router();
    $post_meta = new Post_Meta();
    $global_sections = new Global_Sections();
    $nectar_templates = new Nectar_Templates();
    $portfolio = new Portfolio_Register();
    $menu_options = new Menu_Options_Register();
    $notifications = new Notifications_Register();
    $dynamic_data = new Frontend_Render();

    $adminPanel = new Panel();
    $updater = new NectarBlocksUpdater();
  }

  private function on_new_install() {
    $nectar_options = Nectar_Blocks_Options::get_options();
    if ( ! $nectar_options ) {
      Portfolio_Register::flush_rewrite_rules();
    }
  }

  private function check_for_upgrade($migration_runner) {
    $nectar_options = Nectar_Blocks_Options::get_options();
    $is_upgrade = false;
    $current_version = null;

    // Handle versions before 2.0.0 that did not have this key
    if ( ! isset($nectar_options['currentNBVersion'])) {
      Log::debug('Initializing currentNBVersion');
      $nectar_options['currentNBVersion'] = NECTAR_BLOCKS_VERSION;
      $current_version = NECTAR_BLOCKS_VERSION;
      $is_upgrade = true;
    } else {
      $current_version = $nectar_options['currentNBVersion'];
      if (version_compare($current_version, NECTAR_BLOCKS_VERSION, '<')) {
        $is_upgrade = true;
      }
      $nectar_options['currentNBVersion'] = NECTAR_BLOCKS_VERSION;
    }

    if ($is_upgrade) {
      Log::info('Upgrade detected. Running migrations and flushing rewrite rules.');
      Nectar_Blocks_Options::update_options($nectar_options);
      // $migration_runner->run_migrations($current_version);
      $migration_runner->check_migrations();
      Portfolio_Register::flush_rewrite_rules_on_upgrade();
    }
  }
}
