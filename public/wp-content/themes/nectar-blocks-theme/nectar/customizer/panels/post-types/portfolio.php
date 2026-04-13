<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Post Types - Portfolio customizer options.
 *
 * @since 2.0.0
 * @version 2.0.0
 */
class NectarBlocks_Customizer_Post_Types_Portfolio {
  public static $using_post_grid_archive = false;

  public static $post_grid_special_id = '';

  public static function get_kirki_partials() {

    if ( class_exists('Nectar\Global_Settings\Nectar_Modules') &&
        Nectar\Global_Settings\Nectar_Modules::get_options()['portfolioPostType'] === true
    ) {
      return [
        [
          'panel_id' => 'portfolio-panel',
          'settings' => [
            'title' => esc_html__( 'Portfolio', 'nectar-blocks-theme' ),
            'priority' => 25
          ]
        ],
        self::get_styling(),
        self::get_single_post()
      ];
    }

    return [];
  }

  public static function get_styling() {

    $controls = [

      // TODO: future feature
      // [
      //   'id' => 'portfolio_archive_meta_info',
      //   'type' => 'info',
      //   'style' => 'success',
      //   'title' => esc_html__('Portfolio Archive (Post Grid/List) Template', 'nectar-blocks-theme'),
      //   'icon' => 'el-icon-info-sign',
      //   'desc' => esc_html__( 'Use the following options to control what meta information will be shown on your posts in the main post query.', 'nectar-blocks-theme')
      // ],

      [
        'id' => 'portfolio_auto_masonry_spacing',
        'type' => 'select',
        'class' => self::$using_post_grid_archive ? 'hidden-theme-option' : '',
        'title' => esc_html__('Portfolio Spacing', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          '4px' => '4px',
          '8px' => '8px',
          '12px' => '12px',
          '16px' => '16px',
          '20px' => '20px',
        ],
        'default' => '8px'
      ],

      [
        'id' => 'portfolio_archive_description',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Description', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'portfolio_archive_description_length',
        'type' => 'slider',
        'required' => [ [ 'portfolio_archive_description', '=', '1' ] ],
        'title' => esc_html__('Description Length', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 20,
        "min" => 5,
        "step" => 1,
        "max" => 40,
        'display_value' => 'label'
      ],
      [
        'id' => 'portfolio_archive_categories',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Categories', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],

    ];

    return [
      'section_id' => 'portfolio-archive-section',
      'settings' => [
        'panel' => 'portfolio-panel',
        'title' => esc_html__( 'Archives', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_single_post() {
    $controls = [
      [
        'id' => 'portfolio_header_aspect_ratio',
        'type' => 'select',
        'title' => esc_html__('Header Image Sizing', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'required' => [[ 'portfolio_header_type', '=', 'image_under'  ]],
        'options' => [
          '40' => esc_html__('Slim (2.5:1)', 'nectar-blocks-theme'),
          '50' => esc_html__('Narrow (2:1)', 'nectar-blocks-theme'),
          '56.25' => esc_html__('Regular (16:9)', 'nectar-blocks-theme'),
          '66.66' => esc_html__('Tall (3:2)', 'nectar-blocks-theme'),
          '100' => esc_html__('Square (1:1)', 'nectar-blocks-theme'),
        ],
        'default' => '40'
      ],

      [
       'id' => 'portfolio_header_image_under_border_radius',
       'type' => 'slider',
       'required' => [[ 'portfolio_header_type', '=', 'image_under'  ]],
       'title' => esc_html__('Header Roundness', 'nectar-blocks-theme'),
       'desc' => '',
       "default" => 0,
       "min" => 0,
       "step" => 1,
       "max" => 50,
       'display_value' => 'label'
     ],
      [
        'id' => 'portfolio_header_scroll_effect',
        'type' => 'select',
        'title' => esc_html__('Header Scroll Effect', 'nectar-blocks-theme'),
        'desc' => esc_html__('Globally define a scroll effect for your blog header.', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('None', 'nectar-blocks-theme'),
          'parallax' => esc_html__('Parallax', 'nectar-blocks-theme')
        ],
        'default' => 'parallax'
      ],
      [
        'id' => 'portfolio_header_load_in_animation',
        'type' => 'select',
        'title' => esc_html__('Portfolio Header Load In Animation', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "none" => esc_html__("None", 'nectar-blocks-theme'),
          "fade_in" => esc_html__("Fade In Staggered", 'nectar-blocks-theme'),
        ],
        'default' => 'none'
      ],
      [
        'id' => 'portfolio_single_client',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Client', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'portfolio_single_date',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Date', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'portfolio_single_project_link',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Project Link', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'portfolio_single_project_description',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Description', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'portfolio_single_project_categories',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Categories', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '1'
      ],
    ];

    return [
      'section_id' => 'portfolio-single-post-section',
      'settings' => [
        'panel' => 'portfolio-panel',
        'title' => esc_html__( 'Single Post', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }
}
