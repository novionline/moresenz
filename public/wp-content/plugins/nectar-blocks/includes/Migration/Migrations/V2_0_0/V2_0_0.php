<?php

namespace Nectar\Migration\Migrations\V2_0_0;

use Nectar\Global_Sections\Global_Sections;
use Nectar\Migration\Migration_Base;
use Nectar\Utilities\Log;
class V2_0_0 implements Migration_Base {
  public const ORIGINAL_NECTAR_SECTION_CONDITIONS_META_KEY = 'nectar_g_section_conditions';

  public const ORIGINAL_NECTAR_SECTION_OPERATOR_META_KEY = 'nectar_g_section_conditions_operator';

  public const ORIGINAL_NECTAR_SECTION_LOCATIONS_META_KEY = 'nectar_g_section_locations';

  public const NEW_NECTAR_SECTION_POST_META_KEY = Global_Sections::META_KEY;

  public function __construct() {}

  public function get_version(): string {
    return '2.0.0';
  }

  public function migrate(): bool {
    try {
      $this->migrate_nectar_section_post_meta();
    } catch (\Exception $e) {
      Log::error('Error migrating nectar_section post: ' . $e->getMessage());
      return false;
    }
    return true;
  }

  public function migrate_nectar_section_post_meta() {
    $posts = get_posts([
      'post_type' => Global_Sections::POST_TYPE,
    ]);

    foreach ($posts as $post) {
      Log::debug('Migrating nectar_section post: ' . $post->ID);

      $conditions = get_post_meta($post->ID, self::ORIGINAL_NECTAR_SECTION_CONDITIONS_META_KEY, true);
      $conditions = json_decode((string) json_encode($conditions), true);

      $operator = get_post_meta($post->ID, self::ORIGINAL_NECTAR_SECTION_OPERATOR_META_KEY, true);

      $locations = get_post_meta($post->ID, self::ORIGINAL_NECTAR_SECTION_LOCATIONS_META_KEY, true);
      $locations = json_decode((string) json_encode($locations), true);

      Log::debug('Data to migrate nectar_section post: ' . $post->ID, [
        'conditions' => $conditions,
        'operator' => $operator,
        'locations' => $locations,
      ]);

      if (empty($conditions) || ! is_array($conditions)) {
        $updated_conditions = [];
      } else {
        $updated_conditions = $this->map_conditions($conditions);
      }

      if ( ! empty($condition_operator) && is_array($condition_operator) ) {
        $updated_operator = $this->map_operator($operator);
      } else {
        $updated_operator = 'and';
      }

      if (empty($locations) || ! is_array($locations)) {
        $updated_locations = [];
      } else {
        $updated_locations = $this->map_locations($locations);
      }

      $meta = [
        'conditions' => $updated_conditions,
        'operator' => $updated_operator,
        'locations' => $updated_locations
      ];

      Log::debug('Meta nectar_section post: ' . $post->ID, $meta);

      $success = update_post_meta($post->ID, self::NEW_NECTAR_SECTION_POST_META_KEY, $meta);
      Log::debug('Migrating nectar_section post: ' . $post->ID . ' success: ' . $success);
    }
  }

  /**
   * Map the conditions to the new format
   * @param array $conditions
   * @return array
   */
  public function map_conditions($conditions) {
    try {
      $conditions = array_map(function ($condition) {
        foreach ($condition['options'] as $option) {
          if ($option['type'] === 'include') {
            if ($option['value'] === 'include') {
              $include_value = true;
            } else {
              $include_value = false;
            }
          }

          if ($option['type'] === 'condition') {
            $condition_value = $option['value'];
          }
        }

        return [
          'key' => $condition['key'],
          'include' => $include_value,
          'condition' => $condition_value,
        ];
      }, $conditions);

      return $conditions;
    } catch (\Throwable $e) {
      Log::error('Error mapping conditions: ' . $e->getMessage());
    }
    return [];
  }

  /**
   * Map the operator to the new format
   * @param string $operator
   * @return string
   */
  public function map_operator($operator) {
    if ($operator === 'and' || $operator === 'or') {
      Log::error('Invalid operator: ' . $operator);
      return 'and';
    }
    return $operator;
  }

  /**
   * Map the locations to the new format
   * @param array|stdClass $locations
   * @return array
   */
  public function map_locations($locations) {
    try {
      $locations = array_map(function ($location) {
        $location_value = '';
        $priority = 10;

        foreach ($location['options'] as $option) {
          if ($option['type'] === 'priority') {
            $priority = $option['value'];
          }

          if ($option['type'] === 'location') {
            $location_value = $option['value'];
          }
        }

        return [
          'key' => $location['key'],
          'priority' => $priority,
          'location' => $location_value,
        ];
      }, $locations);
      return $locations;

    } catch (\Throwable $e) {
      Log::error('Error mapping locations: ' . $e->getMessage());
    }
    return [];
  }
}
