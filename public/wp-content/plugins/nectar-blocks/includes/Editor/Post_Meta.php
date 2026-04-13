<?php

namespace Nectar\Editor;

use Nectar\API\Access_Utils;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Global_Sections\Global_Sections;

class Post_Meta {
  private static $instance;

  public static function get_instance() {
    if ( ! isset( self::$instance ) ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct() {
    add_action( 'init', [$this, 'init'] );
  }

  public function init() {
    $this->register_post_options();
    $this->register_portfolio_options();
  }

  public function register_portfolio_options() {
    // Portfolio Options.
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_client', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Client Name', 'nectar-blocks'),
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_date', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Date', 'nectar-blocks'),
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_project_link', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Project Link', 'nectar-blocks'),
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_video', [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'source' => [
              'type' => 'object',
              'properties' => [
                'id' => [
                  'type' => ['integer', 'null'], // Allows undefined (null in PHP)
                ],
                'type' => [
                  'type' => 'string',
                ],
                'url' => [
                  'type' => 'string',
                ],
              ],
            ],
          ],
        ],
      ],
      'label' => __('Video', 'nectar-blocks'),
      'single' => true,
      'default' => [
        'source' => [
          'id' => null,
          'url' => '',
          'type' => 'empty'
        ],
      ],
      'type' => 'object',
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);

    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_description', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Description', 'nectar-blocks'),
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_project_url', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Project URL', 'nectar-blocks'),
      'auth_callback' => function () { return Access_Utils::can_edit_others_posts(); }
    ]);
  }

  // Note: any CPT that wants to use these will need to have custom fields enabled in the post type.
  public function register_post_options() {
    // Hide post title.
    register_post_meta( '', '_nectar_blocks_hide_post_title', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Transparent Header Effect.
    register_post_meta( '', '_nectar_blocks_transparent_header_effect', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Transparent Header Effect Color.
    register_post_meta( '', '_nectar_blocks_transparent_header_effect_color', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 'light',
      'type' => 'string',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Header Animation.
    register_post_meta( '', '_nectar_blocks_header_animation', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    register_post_meta( '', '_nectar_blocks_header_animation_delay', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 0,
      'type' => 'number',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Header Animation Effect.
    register_post_meta( '', '_nectar_blocks_header_animation_effect', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 'fade',
      'type' => 'string',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Page CSS.
    register_post_meta( '', '_nectar_blocks_page_css', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Page JS.
    register_post_meta( '', '_nectar_blocks_page_js', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Global Section Options.
    register_post_meta( Global_Sections::POST_TYPE, Global_Sections::META_KEY, [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'locations' => [
              'type' => 'array',
            ],
            'operator' => [
              'type' => 'string',
            ],
            'conditions' => [
              'type' => 'array',
            ],
          ],
        ],
      ],
      'single' => true,
      'default' => Global_Sections::defaults(),
      'type' => 'object',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);

    // Template Part Options.
    register_post_meta( Nectar_Templates::POST_TYPE, Nectar_Templates::META_KEY, [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'templatePart' => [
              'type' => 'string',
            ],
            'operator' => [
              'type' => 'string',
            ],
            'conditions' => [
              'type' => 'array',
            ],
          ],
        ],
      ],
      'single' => true,
      'default' => Nectar_Templates::defaults(),
      'type' => 'object',
      'auth_callback' => function () { return Access_Utils::can_manage_options(); }
    ]);
  }
}