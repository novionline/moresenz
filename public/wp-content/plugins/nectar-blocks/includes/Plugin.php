<?php

namespace Nectar;

use Nectar\Global_Settings\Global_Settings_Register;
use Nectar\Editor\Blocks;
use Nectar\Editor\Post_Meta;
use Nectar\API\Router;
use Nectar\API\Local_Templates_API;
use Nectar\Admin_Panel\Panel;
use Nectar\Render\Render;
use Nectar\Update\NectarBlocksUpdater;
use Nectar\Global_Sections\Global_Sections;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Menu_Options\Menu_Options_Register;
use Nectar\Notifications\Notifications_Register;
use Nectar\Notifications\Expired_License_Notice;
use Nectar\Notifications\Reauth_Failed_Notice;
use Nectar\Notifications\Domain_Changed_Notice;
use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Portfolio\Portfolio_Register;
use Nectar\Migration\Migration_Runner;
use Nectar\Global_Settings\Nectar_Blocks_Options;
use Nectar\Licensing\Token_Refresh_Cron;
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
    $expired_license_notice = new Expired_License_Notice();
    $reauth_failed_notice = new Reauth_Failed_Notice();
    $domain_changed_notice = new Domain_Changed_Notice();
    $dynamic_data = new Frontend_Render();

    $adminPanel = new Panel();
    $updater = new NectarBlocksUpdater();
    $token_refresh_cron = new Token_Refresh_Cron();

    $this->register_dev_opt_ins();
  }

  /**
   * Dev opt-ins. All dormant unless a site adds the `nectar_blocks_dev`
   * filter. Hard-gated off in production via `wp_get_environment_type()` (which
   * defaults to `production`) so the dev routes can never register on a live
   * site, even if the filter snippet is mistakenly copied there. Deferred to
   * `after_setup_theme` so the theme's `functions.php` has had a chance to
   * register the filter before we read it. See each class's `from_filter()`
   * for the supported config keys.
   */
  private function register_dev_opt_ins() {
    if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'production' ) {
      return;
    }

    add_action( 'after_setup_theme', function() {
      if ( $local_templates = Local_Templates_API::from_filter() ) {
        $local_templates->register();
      }
    } );
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
    $is_major_upgrade = false;
    $current_version = null;

    // Handle versions before 2.0.0 that did not have this key
    if ( ! isset($nectar_options['currentNBVersion'])) {
      Log::debug('Initializing currentNBVersion');
      // If options exist but currentNBVersion does not, the install pre-dates the
      // 2.0.0 release that introduced this key — treat as a major upgrade.
      // An empty/false $nectar_options means a fresh install, not an upgrade.
      if ( ! empty($nectar_options) ) {
        $is_major_upgrade = true;
      }
      $nectar_options['currentNBVersion'] = NECTAR_BLOCKS_VERSION;
      $current_version = NECTAR_BLOCKS_VERSION;
      $is_upgrade = true;
    } else {
      $current_version = $nectar_options['currentNBVersion'];
      if (version_compare($current_version, NECTAR_BLOCKS_VERSION, '<')) {
        $is_upgrade = true;
        $old_major = (int) explode('.', $current_version)[0];
        $new_major = (int) explode('.', NECTAR_BLOCKS_VERSION)[0];
        if ($new_major > $old_major) {
          $is_major_upgrade = true;
        }
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

    if ($is_major_upgrade) {
      Log::info('Major version upgrade detected. Queueing What\'s New redirect.');
      update_option('nectar_blocks_show_whats_new', true);
    }
  }
}
