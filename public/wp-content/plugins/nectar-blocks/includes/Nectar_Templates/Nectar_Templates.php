<?php

namespace Nectar\Nectar_Templates;

if ( ! defined('ABSPATH') ) {
  exit;
}

class Nectar_Templates {
  public const POST_TYPE = 'nectar_templates';

  public const META_KEY = '_nectar_template_part_options';

  function __construct() {
    $register = new Nectar_Templates_Register();
  }

  /**
   * Get the default values.
   * @since 2.0.0
   * @version 2.0.0
   * @return array
   */
  public static function defaults(): array {
    return [
      // EX: "single__pt__post"
      'templatePart' => '',
      'operator' => 'and',
      // EX: [ {key: '5O_tvpgY7oR4fD7_JDX0h', include: true, condition: 'is_user_not_logged_in'} ]
      'conditions' => [],
    ];
  }

  public static function get_template_parts() {
    $post_types = get_post_types( [ 'public' => true ], 'objects' );
    $exclude_post_types = [
      'nectar_sections',
      'home_slider',
      'nectar_slider',
      'nectar_templates',
      'product',
      'page'
    ];

    $formatted_post_types = [
      [
        'value' => '',
        'label' => __('Select a Template', 'nectar-blocks')
      ]
    ];

    // Single post types
    foreach ($post_types as $post_type) {
      if (in_array($post_type->name, $exclude_post_types) || in_array($post_type->name, ['attachment'])) {
        continue;
      }

      $formatted_post_types[] = [
        'value' => 'nectar_template_single__' . $post_type->name,
        'label' => __('Single:', 'nectar-blocks') . ' ' . $post_type->label,
      ];
    }
    // Archive post types
    foreach ($post_types as $post_type) {
      if (in_array($post_type->name, $exclude_post_types) || in_array($post_type->name, ['attachment'])) {
        continue;
      }

      $formatted_post_types[] = [
        'value' => 'nectar_template_archive__' . $post_type->name,
        'label' => __('Archive:', 'nectar-blocks') . ' ' . $post_type->label,
      ];
    }

    // 404
    $formatted_post_types[] = [
      'value' => 'nectar_template__404',
      'label' => __('404 Template', 'nectar-blocks')
    ];

    // OCM.
    $formatted_post_types[] = [
      'value' => 'nectar_template__ocm',
      'label' => __('Off Canvas Menu', 'nectar-blocks')
    ];

    return $formatted_post_types;
  }

  /**
   * Check if the location is active.
   * @since 2.1.0
   * @version 2.1.1
   * @return boolean
   */
  public static function is_active_location($location) {
    $is_active = false;

    // Check if it's a global template part
    if (strpos($location, 'nectar_template__ocm') === 0) {
      $is_active = true;
    }
    // Check if it's a single post template part
    else if (strpos($location, 'nectar_template_single__') === 0) {
      if (is_single()) {
        $post_type = str_replace('nectar_template_single__', '', $location);
        if ($post_type === 'post' || get_post_type() === $post_type) {
          $is_active = true;
        }
      }
    }
    // Check if it's an archive template part
    else if (strpos($location, 'nectar_template_archive__') === 0) {
      if (is_archive()) {
        $post_type = str_replace('nectar_template_archive__', '', $location);
        if ($post_type === 'post' || get_post_type() === $post_type) {
          $is_active = true;
        }
      }
    }
    // Check if it's a 404 template part
    else if ($location === 'nectar_template__404') {
      if (is_404()) {
        $is_active = true;
      }
    }

    return $is_active;
  }

  /**
   * Get the conditions.
   * @since 2.0.0
   * @version 2.0.0
   * @return array
   */
  public static function get_conditions() {
    // User Roles.
    $user_roles = [];
    if ( ! function_exists( 'get_editable_roles' ) ) {
      if ( defined('ABSPATH') ) {
        require_once constant('ABSPATH') . 'wp-admin/includes/user.php';
      } else {
        return [];
      }
    }
    $roles = get_editable_roles();
    foreach ($roles as $role => $details) {
      $user_roles[] = [
        'value' => 'role__' . $role,
        'label' => $details['name'],
      ];
    }

    $options = [
      // [
      //   'label' => __('Everywhere', 'nectar-blocks'),
      //   'options' => [
      //     [
      //       'value' => 'everywhere',
      //       'label' => esc_html__('Everywhere', 'nectar-blocks'),
      //     ]
      //   ]
      // ],
      [
        'label' => __('Taxonomy Terms', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'is_taxonomy_term',
            'label' => esc_html__('Is Taxonomy Term', 'nectar-blocks'),
          ],
          [
            'value' => 'has_taxonomy_term',
            'label' => esc_html__('Has Taxonomy Term', 'nectar-blocks'),
          ],
        ]
      ],
      [
        'label' => __('User Roles/Permissions', 'nectar-blocks'),
        'options' => array_merge(
            [
            [
              'value' => 'is_user_logged_in',
              'label' => esc_html__('User Logged In', 'nectar-blocks'),
            ]
          ],
            [
            [
              'value' => 'is_user_not_logged_in',
              'label' => esc_html__('User Not Logged In', 'nectar-blocks'),
            ]
          ],
            $user_roles
        )
      ]
    ];
    return $options;
  }
}
