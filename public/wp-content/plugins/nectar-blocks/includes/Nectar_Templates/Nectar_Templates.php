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

    // Header Navigation.
    $formatted_post_types[] = [
      'value' => 'nectar_template__header_navigation',
      'label' => __('Header Navigation', 'nectar-blocks')
    ];

    // OCM.
    $formatted_post_types[] = [
      'value' => 'nectar_template__ocm',
      'label' => __('Off Canvas Menu', 'nectar-blocks')
    ];

    // Footer.
    $formatted_post_types[] = [
      'value' => 'nectar_hook_global_section_footer',
      'label' => __('Footer', 'nectar-blocks')
    ];

    // Footer Parallax.
    $formatted_post_types[] = [
      'value' => 'nectar_hook_global_section_parallax_footer',
      'label' => __('Footer Parallax', 'nectar-blocks')
    ];

    // WooCommerce Templates (only when WooCommerce is active).
    if ( class_exists( 'WooCommerce' ) ) {
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__single_product',
        'label' => __('WooCommerce: Single Product', 'nectar-blocks')
      ];
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__archive_product',
        'label' => __('WooCommerce: Product Archive', 'nectar-blocks')
      ];
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__cart',
        'label' => __('WooCommerce: Cart', 'nectar-blocks')
      ];
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__checkout',
        'label' => __('WooCommerce: Checkout', 'nectar-blocks')
      ];
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__my_account',
        'label' => __('WooCommerce: My Account', 'nectar-blocks')
      ];
      $formatted_post_types[] = [
        'value' => 'nectar_template_wc__order_confirmation',
        'label' => __('WooCommerce: Order Confirmation', 'nectar-blocks')
      ];
    }

    // Append hasDefaultContent flag to each entry.
    return array_map( function( $entry ) {
      $entry['hasDefaultContent'] = Template_Default_Content::has_default_content( $entry['value'] );
      return $entry;
    }, $formatted_post_types );
  }

  /**
   * Check if the location is active.
   * @since 2.1.0
   * @version 3.1.1
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
      $post_type = str_replace('nectar_template_archive__', '', $location);
      // The blog posts page is is_home(), not is_archive(), but the theme
      // renders the post archive template there (index.php).
      if (is_archive() || ($post_type === 'post' && is_home())) {
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
    // Check if it's a footer template part
    else if ( in_array($location, ['nectar_hook_global_section_footer', 'nectar_hook_global_section_parallax_footer'], true) ) {
      $is_active = true;
    }
    // Check if it's a WooCommerce template part
    else if ( strpos($location, 'nectar_template_wc__') === 0 && class_exists('WooCommerce') ) {
      $wc_key = str_replace('nectar_template_wc__', '', $location);
      switch ($wc_key) {
        case 'single_product':
          $is_active = is_product();
          break;
        case 'archive_product':
          $is_active = is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy();
          break;
        case 'cart':
          $is_active = is_cart();
          break;
        case 'order_confirmation':
          $is_active = is_order_received_page();
          break;
        case 'checkout':
          $is_active = is_checkout() && ! is_order_received_page();
          break;
        case 'my_account':
          $is_active = is_account_page();
          break;
      }
    }

    return $is_active;
  }

  /**
   * Get the published off-canvas-menu template parts. Returns a list of
   * `{ id, title }` pairs (empty when none assigned).
   *
   * Used by the core/navigation enhanced inspector to link out to the OCM
   * template-part editor(s) when the Nectar Blocks theme is active. Multiple
   * OCM templates are valid because authors can scope each one with different
   * display conditions (logged-in, locale, post type, etc.).
   *
   * @since 3.0.0
   * @return array<int, array{id:int,title:string}>
   */
  public static function get_ocm_template_parts(): array {
    $posts = get_posts( [
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'no_found_rows' => true,
      'orderby' => 'title',
      'order' => 'ASC',
      'meta_query' => [
        [
          'key' => self::META_KEY,
          'value' => 'nectar_template__ocm',
          'compare' => 'LIKE',
        ],
      ],
    ] );

    $out = [];
    foreach ( $posts as $post ) {
      $out[] = [
        'id' => (int) $post->ID,
        // Use the raw post_title to avoid the `the_title` filter chain, which
        // can return entity-encoded output (e.g. `&#038;`) that React would
        // then render literally in the editor sidebar.
        'title' => (string) $post->post_title,
      ];
    }
    return $out;
  }

  /**
   * Check if a header navigation template is assigned.
   * @since 2.6.0
   * @return boolean
   */
  public static function has_header_navigation_template(): bool {
    $args = [
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_query' => [
        [
          'key' => self::META_KEY,
          'value' => 'nectar_template__header_navigation',
          'compare' => 'LIKE'
        ]
      ]
    ];

    $query = new \WP_Query($args);
    return $query->have_posts();
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
