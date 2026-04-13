<?php

/**
 * Customizer Typography section.
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Typography customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Typography {
  private static function get_title() {
    return [
      'id' => 'typography-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'Typography', 'nectar-blocks-theme' ),
        'priority' => 9,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_title()
    ];
  }

  public static function get_kirki_partials() {
    return [
      self::get_nav_elements(),
      self::get_page_header_elements(),
      self::get_content_elements(),
    ];
  }

  public static function default_typography() {
    return [
      'fontSource' => 'Google',
      'fontFamily' => 'Roboto',
      'fontData' => [
        'variants' => [
          '100',
          '100italic',
          '300',
          '300italic',
          'regular',
          'italic',
          '500',
          '500italic',
          '700',
          '700italic',
          '900',
          '900italic'
        ],
        'subsets' => [
          'latin',
          'latin-ext',
          'cyrillic',
          'cyrillic-ext',
          'greek',
          'greek-ext',
          'vietnamese'
        ],
      ],
      'fontSize' => [
        'desktop' => [
          'value' => 1,
          'unit' => 'rem',
          'disabled' => false
        ],
      ],
      'fontSizeMin' => [
        'desktop' => [
          'value' => null,
          'unit' => 'px',
        ],
      ],
      'fontSizeMax' => [
        'desktop' => [
          'value' => null,
          'unit' => 'px',
        ],
      ],
      'fontWeight' => 'regular',
      // 'fontStretchPercentage' => '?',
      'letterSpacing' => [
        'value' => -0.01,
        'unit' => 'em',
        'disabled' => false
      ],
      'fontStyle' => 'normal',
      'transform' => 'none',
      'lineHeight' => [
        'desktop' => [
          'value' => 1.4,
          'unit' => 'em',
          'disabled' => false
        ]
      ],
      'fontColor' => [
        'value' => '',
        'globalColorData' => null,
      ]
    ];
  }

  public static function default_typography_empty() {
    $typography = self::default_typography();

    // Remove font family
    $typography['fontFamily'] = '';

    // Set letter spacing to 1
    $typography['letterSpacing']['value'] = 0;

    // Disable font size
    $typography['fontSize']['desktop']['disabled'] = true;

    // Disable line height
    $typography['lineHeight']['desktop']['disabled'] = true;

    return $typography;
  }

  public static function get_nav_elements() {
    $controls = [

      [
        'id' => 'logo_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Logo Font', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('logo_font_family'),
        'default' => array_merge(
            NectarBlocks_Customizer_Typography::default_typography(),
            [
            'fontSize' => [
              'desktop' => [
                'value' => 24,
                'unit' => 'px',
                'disabled' => false
              ]
              ],
              'fontWeight' => '500'
          ]
        )
      ],

      [
        'id' => 'navigation_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Navigation Font', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('navigation_font_family'),
        'default' => array_merge(
            NectarBlocks_Customizer_Typography::default_typography(),
            [
                'fontWeight' => '500'
            ]
        )
      ],

      [
        'id' => 'navigation_dropdown_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Navigation Dropdown Font', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('navigation_dropdown_font_family'),
        'default' => NectarBlocks_Customizer_Typography::default_typography()
      ],

      [
        'id' => 'off_canvas_nav_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Off Canvas Navigation', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('off_canvas_nav_font_family'),
        'default' => array_merge(
            NectarBlocks_Customizer_Typography::default_typography(),
            [
            'fontSize' => [
              'desktop' => [
                'value' => 2.5,
                'unit' => 'rem',
                'disabled' => false
              ]
              ],
              'lineHeight' => [
                'desktop' => [
                  'value' => 1.1,
                  'unit' => 'em',
                  'disabled' => false
                ]
              ],
              'fontWeight' => '500'
          ]
        )
      ],

      [
        'id' => 'off_canvas_nav_subtext_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Off Canvas Navigation/Dropdown Description Text', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('off_canvas_nav_subtext_font_family'),
        'default' => NectarBlocks_Customizer_Typography::default_typography(),
      ],

      [
        'id' => 'navigation_custom_text',
        'type' => 'typography',
        'title' => esc_html__( 'Navigation Custom Text', 'nectar-blocks-theme' ),
        'tooltip' => __('Targets the content added via the "Text To Display In Header" field.', 'nectar-blocks-theme'),
        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('off_canvas_nav_subtext_font_family'),
        'default' => NectarBlocks_Customizer_Typography::default_typography_empty(),
      ],
    ];

    return [
      'section_id' => 'typography-nav-section',
      'settings' => [
        'title' => esc_html__( 'Navigation', 'nectar-blocks-theme' ),
        'priority' => 10
      ],
      'controls' => $controls
    ];
  }

  public static function get_page_header_elements() {
    $controls = [

      [
        'id' => 'page_heading_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Page Heading Font', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('page_heading_font_family'),
        'default' => array_merge(
            NectarBlocks_Customizer_Typography::default_typography(),
            [
            'fontSize' => [
              'desktop' => [
                'value' => 2.5,
                'unit' => 'rem',
                'disabled' => false
              ]
              ],
              'fontWeight' => '500'
          ]
        ),
      ],

      [
        'id' => 'page_heading_subtitle_font_family',
        'type' => 'typography',
        'title' => esc_html__( 'Page Heading Subtitle Font', 'nectar-blocks-theme' ),
        'subtitle' => '',

        // 'transport' => 'postMessage',
        'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('page_heading_subtitle_font_family'),
        'default' => array_merge(
            NectarBlocks_Customizer_Typography::default_typography(),
            [
            'fontSize' => [
              'desktop' => [
                'value' => 1.3,
                'unit' => 'rem',
                'disabled' => false
              ]
              ]
          ]
        ),
      ],

    ];

    return [
      'section_id' => 'typography-page-header-section',
      'settings' => [
        'title' => esc_html__( 'Page Header', 'nectar-blocks-theme' ),
        'priority' => 10
      ],
      'controls' => $controls
    ];
  }

  public static function get_content_elements() {
    $controls = [

          [
            'id' => 'testimonial_font_family',
            'type' => 'typography',
            'title' => esc_html__( 'Blockquote Font', 'nectar-blocks-theme' ),
            'subtitle' => '',

            // 'transport' => 'postMessage',
            'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('testimonial_font_family'),
            'default' => array_merge(
                NectarBlocks_Customizer_Typography::default_typography(),
                [
                'fontSize' => [
                  'desktop' => [
                    'value' => 1.3,
                    'unit' => 'rem',
                    'disabled' => false
                  ]
                ],
              ]
            ),
          ],

          [
            'id' => 'blog_single_post_content_font_family',
            'type' => 'typography',
            'title' => esc_html__( 'Blog Single Post Content', 'nectar-blocks-theme' ),
            'subtitle' => '',

            // 'transport' => 'postMessage',
            'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('blog_single_post_content_font_family'),
            'default' => array_merge(
                NectarBlocks_Customizer_Typography::default_typography(),
                [
                'fontFamily' => 'Source Serif Pro',
                'fontSize' => [
                  'desktop' => [
                    'value' => 20,
                    'unit' => 'px',
                    'disabled' => false
                  ]
                  ],
                  'fontWeight' => '400'
              ]
            ),
         ],

          [
            'id' => 'nectar_woo_shop_product_title_font_family',
            'type' => 'typography',
            'title' => esc_html__( 'WooCommerce Product Title Font', 'nectar-blocks-theme' ),
            'subtitle' => '',

            // 'transport' => 'postMessage',
            'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('nectar_woo_shop_product_title_font_family'),
            'default' => array_merge(
                NectarBlocks_Customizer_Typography::default_typography(),
                [
                'fontSize' => [
                  'desktop' => [
                    'value' => 1.2,
                    'unit' => 'rem',
                    'disabled' => false
                  ]
                ],
                'lineHeight' => [
                  'desktop' => [
                    'value' => 1.2,
                    'unit' => 'em',
                    'disabled' => false
                  ]
                ],
                'fontWeight' => '500'
              ]
            ),
          ],

          [
            'id' => 'nectar_woo_shop_product_secondary_font_family',
            'type' => 'typography',
            'title' => esc_html__( 'WooCommerce Product Secondary Font', 'nectar-blocks-theme' ),
            'subtitle' => '',

            // 'transport' => 'postMessage',
            'nectar_post_message_data' => Nectar_Dynamic_Fonts()->kirki_arrays('nectar_woo_shop_product_secondary_font_family'),
            'default' => array_merge(
                NectarBlocks_Customizer_Typography::default_typography(),
                [
                'fontWeight' => '500'
              ]
            )
          ]
    ];

    return [
      'section_id' => 'typography-nectar-section',
      'settings' => [
        'title' => esc_html__( 'Content Elements', 'nectar-blocks-theme' ),
        'priority' => 12
      ],
      'controls' => $controls
    ];
  }
}