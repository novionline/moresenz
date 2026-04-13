<?php

namespace Nectar\Global_Sections;

use Nectar\Global_Sections\Global_Sections_Register;

if ( ! defined('ABSPATH') ) {
  exit;
}
class Global_Sections {
  public const POST_TYPE = 'nectar_sections';

  public const META_KEY = '_nectar_g_section_options';

  function __construct() {
    $register = new Global_Sections_Register();
  }

  /**
   * Get the default values.
   * @since 2.0.0
   * @version 2.0.0
   * @return array
   */
  public static function defaults(): array {
    return [
      // EX: {key: '8GZIKJN25MYCeyHj2wePN', priority: 10, location: 'nectar_hook_before_content_global_section'}
      'locations' => [],
      'operator' => 'and',
      // EX: {key: 'rzksY53n0HOAERBfud0bC', include: true, condition: 'is_search'}
      'conditions' => [],
    ];
  }

  /**
   * Get the conditions.
   * @since 2.0.0
   * @version 2.0.0
   * @return array
   */
  public static function get_conditions() {
    $post_types = get_post_types(
        [ 'public' => true ]
    );
    $exclude_post_types = ['nectar_sections', 'home_slider', 'nectar_slider', 'nectar_templates'];

    // Post types.
    $formatted_post_types = [];
    foreach ($post_types as $post_type) {
      if (in_array($post_type, $exclude_post_types)) {
        continue;
      }

      $formatted_post_types[] = [
        'value' => 'post_type__' . $post_type,
        'label' => $post_type,
      ];
    }
    // Single post types
    foreach ($post_types as $post_type) {
      if (in_array($post_type, $exclude_post_types) || in_array($post_type, ['attachment'])) {
        continue;
      }

      $formatted_post_types[] = [
        'value' => 'single__pt__' . $post_type,
        'label' => 'Single: ' . $post_type,
      ];
    }

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
      [
        'label' => esc_html__('General', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'everywhere',
            'label' => esc_html__('Everywhere', 'nectar-blocks'),
          ],

          [
            'value' => 'is_archive',
            'label' => esc_html__('Archive', 'nectar-blocks'),
          ],
          [
            'value' => 'is_front_page',
            'label' => esc_html__('Front Page', 'nectar-blocks'),
          ],
          [
            'value' => 'is_search',
            'label' => esc_html__('Search Results', 'nectar-blocks'),
          ],

          [
            'value' => 'is_single',
            'label' => esc_html__('Single', 'nectar-blocks'),
          ],
          [
            'value' => 'specific_post',
            'label' => esc_html__('Specific Post', 'nectar-blocks'),
          ],
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
        'label' => esc_html__('Post Types', 'nectar-blocks'),
        'options' => $formatted_post_types
      ],
      [
        'label' => esc_html__('User Roles/Permissions', 'nectar-blocks'),
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

  /**
   * Get the locations.
   * @since 2.0.0
   * @version 2.0.0
   * @return array
   */
  public static function get_locations() {
    return [
      [
        'label' => esc_html__('Top', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'nectar_hook_before_secondary_header',
            'label' => esc_html__('Inside Header Navigation Top', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_global_section_after_header_navigation',
            'label' => esc_html__('After Header Navigation', 'nectar-blocks'),
          ]
        ]
      ],
      [
        'label' => esc_html__('Main Content', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'nectar_hook_before_content_global_section',
            'label' => esc_html__('Before Page/Post Content', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_global_section_after_content',
            'label' => esc_html__('After Page/Post Content', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_sidebar_top',
            'label' => esc_html__('Sidebar Top', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_sidebar_bottom',
            'label' => esc_html__('Sidebar Bottom', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_before_blog_loop_start',
            'label' => esc_html__('Before Blog Loop', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_before_blog_loop_end',
            'label' => esc_html__('After Blog Loop', 'nectar-blocks'),
          ]
        ]
      ],
      [
        'label' => esc_html__('Footer', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'nectar_hook_ocm_before_menu',
            'label' => esc_html__('Before Off Canvas Menu Items', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_ocm_after_menu',
            'label' => esc_html__('After Off Canvas Menu Items', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_hook_ocm_bottom_meta',
            'label' => esc_html__('Off Canvas Menu Meta Area', 'nectar-blocks'),
          ],
        ]
      ],
      [
        'label' => esc_html__('Bottom', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'nectar_hook_global_section_footer',
            'label' => esc_html__('Footer', 'nectar-blocks'),
        ],
        [
          'value' => 'nectar_hook_global_section_parallax_footer',
          'label' => esc_html__('Footer Parallax', 'nectar-blocks'),
        ],

        [
          'value' => 'nectar_hook_global_section_after_footer',
          'label' => esc_html__('After Footer', 'nectar-blocks'),
          ],
        ]
      ],
      [
        'label' => esc_html__('WooCommerce', 'nectar-blocks'),
        'options' => [
          [
            'value' => 'nectar_woocommerce_before_shop_loop',
            'label' => esc_html__('Before Shop Loop', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_shop_loop',
            'label' => esc_html__('After Shop Loop', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_single_product_summary',
            'label' => esc_html__('Single Product Before Summary', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_add_to_cart_form',
            'label' => esc_html__('Single Product Before Add to Cart', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_add_to_cart_form',
            'label' => esc_html__('Single Product After Add to Cart', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_single_product_summary',
            'label' => esc_html__('Single Product After Summary', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_checkout_billing_form',
            'label' => esc_html__('Checkout Before Billing Form', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_checkout_billing_form',
            'label' => esc_html__('Checkout After Billing Form', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_checkout_shipping_form',
            'label' => esc_html__('Checkout Before Shipping Form', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_order_notes',
            'label' => esc_html__('Checkout Before Order Notes', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_order_notes',
            'label' => esc_html__('Checkout After Order Notes', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_checkout_before_order_review',
            'label' => esc_html__('Checkout Before Order Review', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_review_order_before_payment',
            'label' => esc_html__('Checkout Before Review Order Payment', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_review_order_after_payment',
            'label' => esc_html__('Checkout After Review Order Payment', 'nectar-blocks'),
          ],

          [
            'value' => 'nectar_woocommerce_cart_coupon',
            'label' => esc_html__('Cart Coupon', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_cart_totals',
            'label' => esc_html__('Cart Before Totals', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_cart_totals_before_shipping',
            'label' => esc_html__('Cart Before Shipping', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_before_shipping_calculator',
            'label' => esc_html__('Cart Before Shipping Calculator', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_after_shipping_calculator',
            'label' => esc_html__('Cart After Shipping Calculator', 'nectar-blocks'),
          ],
          [
            'value' => 'nectar_woocommerce_proceed_to_checkout',
            'label' => esc_html__('Cart Proceed to Checkout', 'nectar-blocks'),
          ],
        ]
      ]
    ];
  }
}
