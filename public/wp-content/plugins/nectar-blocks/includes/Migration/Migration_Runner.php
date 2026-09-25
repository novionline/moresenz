<?php

namespace Nectar\Migration;

use Nectar\Utilities\Log;
use Nectar\Migration\Migrations\V2_0_0\V2_0_0;
use Nectar\Global_Settings\Nectar_Blocks_Options;

class Migration_Runner {
  public $migration_list = [
    '2.0.0' => V2_0_0::class
  ];

  public function __construct() {}

  public function check_migrations() {
    $nectar_options = Nectar_Blocks_Options::get_options();
    if (isset($nectar_options['migrationVersion'])) {
      $previous_version = $nectar_options['migrationVersion'];
    } else {
      // Will only be empty before 2.0.0
      $previous_version = '0.0.0';
    }

    Log::info('Initializing migrations. Previous version: ' . $previous_version);
    $migrations = $this->run_migrations($previous_version);

    if ($migrations['last_success'] !== null) {
      Log::debug('Migrations completed. Last success: ' . $migrations['last_success'], $migrations);
      $nectar_options['migrationVersion'] = $migrations['last_success'];
      Nectar_Blocks_Options::update_options($nectar_options);
    }
  }

  /**
   * Run migrations that are newer than the current version.
   * @param string $version The current version of the plugin.
   * @param bool $dry_run Whether to run the migrations in dry run mode. Always returns success.
   */
  public function run_migrations($prev_version, $dry_run = false) {
    $migrations = [
      'last_success' => null,
      'all_status' => []
    ];

    foreach ($this->migration_list as $migration_version => $migration) {
      if (version_compare($prev_version, $migration_version, '<')) {
        Log::info('Running migration: ' . $migration_version);
        if ($dry_run) {
          Log::info('Dry run: ' . $migration_version);
          $migrations['all_status'][$migration_version] = 'success';
          $migrations['last_success'] = $migration_version;
          continue;
        }

        $migration = new $migration();
        $success = $migration->migrate();
        if ($success) {
          Log::info('Migration ' . $migration_version . ' completed successfully.');
          $migrations['all_status'][$migration_version] = 'success';
          $migrations['last_success'] = $migration_version;
        } else {
          Log::error('Migration ' . $migration_version . ' failed.');
          $migrations['all_status'][$migration_version] = 'failed';
        }
      } else {
        $migrations['all_status'][$migration_version] = 'skipped';
      }
    }

    return $migrations;
  }
}