<?php

namespace Nectar\Menu_Options;

class Settings {
     private static $instance;

     public static $settings = [];

     public function __construct() {

      $global_section_options = [
        '-' => esc_html__('Select a Global Section', 'nectar-blocks'),
       ];
       $global_sections_query = get_posts(
           [
          'posts_per_page' => -1,
          'post_status' => 'publish',
          'ignore_sticky_posts' => true,
          'no_found_rows' => true,
          'post_type' => 'nectar_sections'
        ]
       );

      foreach( $global_sections_query as $section ) {
        if( property_exists( $section, 'post_title') && property_exists( $section, 'ID') ) {
          $global_section_options[$section->ID] = $section->post_title;
        }
      }

       self::$settings = [

        'enable_mega_menu' => [
          'type' => 'switch_toggle',
          'category' => 'menu-item',
          'label' => esc_html__('Enable Mega Menu', 'nectar-blocks'),
          'description' => esc_html__('Turns this menu item into a megamenu.', 'nectar-blocks'),
          'max_depth' => '0',
          'default_value' => '0',
          'custom_attrs' => [
            'data-toggles' => 'mega_menu_global_section',
          ],
        ],

        'mega_menu_global_section' => [
          'type' => 'dropdown',
          'category' => 'menu-item',
          'label' => esc_html__('Mega Menu Global Section', 'nectar-blocks'),
          'description' => esc_html__('Assign a Global Section to display as your mega menu.', 'nectar-blocks'),
          'default_value' => '',
          'max_depth' => '0',
          'options' => $global_section_options,
        ],

        'mega_menu_global_section_mobile' => [
          'type' => 'dropdown',
          'category' => 'menu-item',
          'label' => esc_html__('Mega Menu Global Section Mobile', 'nectar-blocks'),
          'description' => esc_html__('Assign a Global Section to display as your mega menu in the Off Canvas Menu. Not compatible with fullscreen menus.', 'nectar-blocks'),
          'default_value' => '',
          'max_depth' => '0',
          'options' => $global_section_options,
        ],

         'menu_item_persist_mobile_header' => [
          'type' => 'switch_toggle',
          'category' => 'menu-item',
          'label' => esc_html__('Persist In Mobile Navigation Header', 'nectar-blocks'),
          'description' => esc_html__('This will cause the link to remain visible in your mobile header navigation instead of the default location within the off canvas menu.', 'nectar-blocks'),
          'max_depth' => '0',
          'default_value' => '0'
        ],

         'menu_item_link_link_style' => [
          'type' => 'dropdown',
          'category' => 'menu-item',
          'label' => esc_html__('Menu Item Link Button Style', 'nectar-blocks'),
          'description' => esc_html__('Choose a style for your menu item.', 'nectar-blocks'),
          'max_depth' => '0',
          'options' => [
              'default' => esc_html__('Default', 'nectar-blocks'),
              'regular' => esc_html__('Solid Background', 'nectar-blocks'),
              'border' => esc_html__('Bordered', 'nectar-blocks')
          ],
          'custom_attrs' => [
             'data-toggles' => 'menu_item_link_button_color',
           ],
          ],

          'menu_item_link_link_text_style' => [
            'type' => 'dropdown',
            'category' => 'menu-item',
            'label' => esc_html__('Menu Item Link Text Hover', 'nectar-blocks'),
            'description' => esc_html__('Optionally set a link hover animation.', 'nectar-blocks'),
            'max_depth' => '0',
            'options' => [
                'default' => esc_html__('Default', 'nectar-blocks'),
                'text-reveal' => esc_html__('Reveal', 'nectar-blocks'),
                'text-reveal-wave' => esc_html__('Wave', 'nectar-blocks'),
              ]
            ],

          // regular button
          'menu_item_link_button_color' => [
           'type' => 'color',
           'category' => 'menu-item',
           'label' => esc_html__('Button Color', 'nectar-blocks'),
           'description' => 'The color used for the button.',
           'default_value' => '#000000',
           'max_depth' => '0',
           'custom_attrs' => [
            'data-toggled-by' => 'menu_item_link_link_style',
            'data-toggled-by-value' => 'regular'
          ],
         ],
         'menu_item_link_button_color_text' => [
          'type' => 'color',
          'category' => 'menu-item',
          'label' => esc_html__('Button Text Color', 'nectar-blocks'),
          'description' => 'The color used for the button text.',
          'default_value' => '#ffffff',
          'max_depth' => '0',
          'custom_attrs' => [
           'data-toggled-by' => 'menu_item_link_link_style',
           'data-toggled-by-value' => 'regular'
         ],
        ],
         'menu_item_link_button_color_hover' => [
          'type' => 'color',
          'category' => 'menu-item',
          'label' => esc_html__('Button Hover/Active Color', 'nectar-blocks'),
          'description' => 'The color used for the button hover/active state.',
          'default_value' => '#222222',
          'max_depth' => '0',
          'custom_attrs' => [
            'data-toggled-by' => 'menu_item_link_link_style',
            'data-toggled-by-value' => 'regular'
          ],
        ],
          'menu_item_link_button_color_text_hover' => [
            'type' => 'color',
            'category' => 'menu-item',
            'label' => esc_html__('Button Text Hover/Active Color', 'nectar-blocks'),
            'description' => 'The color used for the button text hover/active state.',
            'default_value' => '#ffffff',
            'max_depth' => '0',
            'custom_attrs' => [
              'data-toggled-by' => 'menu_item_link_link_style',
              'data-toggled-by-value' => 'regular'
            ],
          ],

          // bordered button
          'menu_item_link_button_color_border' => [
           'type' => 'color',
           'category' => 'menu-item',
           'label' => esc_html__('Button Border Color', 'nectar-blocks'),
           'description' => 'The color used for the button border.',
           'default_value' => '#666666',
           'max_depth' => '0',
           'custom_attrs' => [
            'data-toggled-by' => 'menu_item_link_link_style',
            'data-toggled-by-value' => 'border'
          ],
         ],
         'menu_item_link_button_color_border_text' => [
          'type' => 'color',
          'category' => 'menu-item',
          'label' => esc_html__('Button Text Color', 'nectar-blocks'),
          'description' => 'The color used for button text.',
          'default_value' => '#000000',
          'max_depth' => '0',
          'custom_attrs' => [
           'data-toggled-by' => 'menu_item_link_link_style',
           'data-toggled-by-value' => 'border'
         ],
        ],
        'menu_item_link_button_color_border_hover' => [
          'type' => 'color',
          'category' => 'menu-item',
          'label' => esc_html__('Button Border Color Hover', 'nectar-blocks'),
          'description' => 'The color used for the button border hover.',
          'default_value' => '#000000',
          'max_depth' => '0',
          'custom_attrs' => [
           'data-toggled-by' => 'menu_item_link_link_style',
           'data-toggled-by-value' => 'border'
         ],
        ],

        //  'menu_item_link_text_color_type' => [
        //    'type' => 'dropdown',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Text Coloring', 'nectar-blocks'),
        //    'description' => esc_html__('Select how your menu item text should be colored.', 'nectar-blocks'),
        //    'custom_attrs' => [
        //      'data-toggles' => 'menu_item_link_coloring',
        //    ],
        //    'default_value' => 'default',
        //    'max_depth' => '-1',
        //    'options' => [
        //      'default' => esc_html__('Default (Automatic)', 'nectar-blocks'),
        //      'custom' => esc_html__('Custom Coloring', 'nectar-blocks'),
        //    ]
        //  ],
        //  'menu_item_link_coloring_custom_text' => [
        //    'type' => 'color',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Title Color', 'nectar-blocks'),
        //    'description' => 'The color used for your menu item title.',
        //    'default_value' => '#ffffff',
        //    'max_depth' => '-1',
        //    'min_depth' => '1'
        //  ],
        //  'menu_item_link_coloring_custom_text_h' => [
        //    'type' => 'color',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Title Color Hover', 'nectar-blocks'),
        //    'description' => 'The color used for your menu item title on hover.',
        //    'default_value' => '#ffffff',
        //    'max_depth' => '-1',
        //    'min_depth' => '1'
        //  ],

        //  'menu_item_link_coloring_custom_button_bg' => [
        //   'type' => 'color',
        //   'category' => 'menu-item',
        //   'label' => esc_html__('Menu Item Button Effect BG', 'nectar-blocks'),
        //   'description' => 'The color used for the button effect background.',
        //   'default_value' => '#eeeeee',
        //   'theme_option_conditional' => [ 'header-hover-effect', 'button_bg' ],
        //   'max_depth' => '0',
        // ],
        // 'menu_item_link_coloring_custom_button_bg_active' => [
        //   'type' => 'color',
        //   'category' => 'menu-item',
        //   'label' => esc_html__('Menu Item Button Effect BG Active', 'nectar-blocks'),
        //   'description' => 'The color used for the button effect background in the active state.',
        //   'default_value' => '#000000',
        //   'theme_option_conditional' => [ 'header-hover-effect', 'button_bg' ],
        //   'max_depth' => '0',
        // ],
        // 'menu_item_link_coloring_custom_button_text_active' => [
        //   'type' => 'color',
        //   'category' => 'menu-item',
        //   'label' => esc_html__('Menu Item Button Effect Text Active', 'nectar-blocks'),
        //   'description' => 'The color used for the button effect text in the active state.',
        //   'default_value' => '#ffffff',
        //   'theme_option_conditional' => [ 'header-hover-effect', 'button_bg' ],
        //   'max_depth' => '0',
        // ],

        //  'menu_item_link_coloring_custom_text_p' => [
        //    'type' => 'color',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Title Color', 'nectar-blocks'),
        //    'description' => 'The color used for your menu item title.',
        //    'default_value' => '#000000',
        //    'max_depth' => '0',
        //  ],
        //  'menu_item_link_coloring_custom_text_h_p' => [
        //    'type' => 'color',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Title Color Hover', 'nectar-blocks'),
        //    'description' => 'The color used for your menu item title on hover.',
        //    'default_value' => '#777777',
        //    'max_depth' => '0',
        //  ],

        //  'menu_item_link_coloring_custom_desc' => [
        //    'type' => 'color',
        //    'category' => 'menu-item',
        //    'label' => esc_html__('Menu Item Description Color', 'nectar-blocks'),
        //    'description' => 'The color used for your menu item description.',
        //    'default_value' => '#ffffff',
        //    'max_depth' => '-1',
        //    'min_depth' => '1'
        //  ],

         /************* icons  *************/

         'menu_item_icon_type' => [
          'type' => 'dropdown',
          'category' => 'menu-icon',
          'label' => esc_html__('Icon Type', 'nectar-blocks'),
          'description' => '',
          'custom_attrs' => [
            'data-icon-container' => 'menu_item_icon',
            'data-iconsmind-container' => 'menu_item_icon_iconsmind',
            'data-icon-custom' => 'menu_item_icon_custom',
            'data-icon-custom-text' => "menu_item_icon_custom_text",
            'data-icon-custom-border-radius' => "menu_item_icon_custom_border_radius"
          ],
          'default_value' => 'font_awesome',
          'max_depth' => '-1',
          'options' => [
            'custom' => esc_html__('Image Icon', 'nectar-blocks'),
            'custom_text' => esc_html__('Text (Emoji)', 'nectar-blocks')
          ]
        ],
        'menu_item_icon_custom_text' => [
          'type' => 'text',
          'category' => 'menu-icon',
          'label' => esc_html__('Menu Icon Text (Emoji)', 'nectar-blocks'),
          'description' => esc_html__('Add in a symbol or emoji to display as the icon next to your menu title.', 'nectar-blocks') . '<br/><br/><strong>' . esc_html__('To add an Emoji:', 'nectar-blocks') . '</strong><br/><br/>' . esc_html__('Windows: On your keyboard, press and hold the Windows button and either the period (.) or semicolon (;)', 'nectar-blocks') . '<br/><br/>' . esc_html__('Mac: On your keyboard, press Command + Control + Space', 'nectar-blocks'),
          'default_value' => '',
          'max_depth' => '-1',
        ],
         'menu_item_icon_custom' => [
          'type' => 'image',
          'category' => 'menu-icon',
          'label' => esc_html__('Icon Selection', 'nectar-blocks'),
          'description' => esc_html__('Upload an icon to display next to the menu item title.', 'nectar-blocks'),
          'max_depth' => '-1',
          'default_value' => [
            'id' => '',
            'url' => ''
          ]
        ],
        'menu_item_icon_size' => [
          'type' => 'numerical',
          'category' => 'menu-icon',
          'label' => esc_html__('Menu Icon Size', 'nectar-blocks'),
          'description' => esc_html__('Define a custom size for your icon. Leave this blank for the default value.', 'nectar-blocks'),
          'default_value' => '',
          'custom_attrs' => [
            'data-ceil' => '100',
            'data-units' => 'px'
          ],
          'max_depth' => '-1',
        ],
        'menu_item_icon_position' => [
          'type' => 'dropdown',
          'category' => 'menu-icon',
          'label' => esc_html__('Menu Icon Position', 'nectar-blocks'),
          'description' => esc_html__('Determines where the menu icon will be aligned relative to the text. (Only applies to submenu items)', 'nectar-blocks'),
          'default_value' => 'default',
          'max_depth' => '-1',
          'options' => [
            'default' => esc_html__('Next To Text', 'nectar-blocks'),
            'above' => esc_html__('Above Text', 'nectar-blocks'),
          ]
        ],
        'menu_item_icon_spacing' => [
          'type' => 'numerical',
          'category' => 'menu-icon',
          'label' => esc_html__('Menu Icon Spacing', 'nectar-blocks'),
          'description' => esc_html__('Define a custom amount of spacing between your icon and menu item text. Leave this blank for the default value. (Only applies to submenu items)', 'nectar-blocks'),
          'default_value' => '',
          'custom_attrs' => [
            'data-ceil' => '50',
            'data-units' => 'px'
          ],
          'max_depth' => '-1',
        ],

      ];

     }

     /**
      * Initiator.
      */
     public static function get_instance() {
       if ( ! self::$instance ) {
         self::$instance = new self;
       }
       return self::$instance;
     }

     /**
     * Returns the settings.
     */
     public static function get_settings() {
       return self::$settings;
     }
   }

