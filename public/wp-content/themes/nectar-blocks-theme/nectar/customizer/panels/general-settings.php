<?php

require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-section.php';

/**
 * Customizer Section + Panel: General Settings Styling
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

  /**
   * General Settings Panel.
   *
   * @since 14.0.2
   */
  class NectarBlocks_Customizer_General_Settings {
    public static $id = 'general-settings';

    public static function get_options() {
      return [
        self::get_title()
      ];
    }

    public static $panel_id = 'general-settings-panel';

    private static function get_title() {
      return [
        'id' => 'general-settings-title',
        'settings' => [
          'type' => 'nectar-title',
          'title' => esc_html__( 'General Settings', 'nectar-blocks-theme' ),
          'priority' => 1,
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
        self::get_section_style(),
        self::get_section_functionality(),
        self::get_section_performance(),
        self::get_form_styling(),
        self::get_page_transitions()
      ];
    }

    public static function get_section_style() {

      $controls = [
        [
          'id' => 'overall-bg-color',
          'type' => 'color',
          'title' => esc_html__('Theme Background Color', 'nectar-blocks-theme'),
          'subtitle' => '',
          'output' => Nectar_Dynamic_Colors()->kirki_arrays('overall-bg-color'),
          'transparent' => false,
          'desc' => '',
          'default' => '#ffffff'
        ],

        [
          'id' => 'accent-color',
          'type' => 'color',
          'title' => esc_html__('Theme Accent Color', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('learn more about color schemes in the', 'nectar-blocks-theme') . ' <a href="https://docs.nectarblocks.com/global-settings/colors" target="_blank" rel="noopener">' . __('documentation', 'nectar-blocks-theme') . '</a>.',
          'default' => '#3452ff',
          'output' => Nectar_Dynamic_Colors()->kirki_arrays('accent-color')
        ],

        [
          'id' => 'accent-text-color',
          'type' => 'color',
          'title' => esc_html__('Theme Accent Text Color', 'nectar-blocks-theme'),
          'tooltip' => esc_html__('Used for text overlaying the accent color on buttons and notifications.', 'nectar-blocks-theme'),
          'subtitle' => '',
          'default' => '#ffffff',
          'output' => Nectar_Dynamic_Colors()->kirki_arrays('accent-text-color')
        ],

        [
         'id' => 'button-styling',
         'type' => 'select',
         'title' => esc_html__('Button Styling', 'nectar-blocks-theme'),
         'subtitle' => esc_html__('This will effect the overall styling of buttons', 'nectar-blocks-theme'),
         'options' => [
           "default" => esc_html__("Default", 'nectar-blocks-theme'),
           "slightly_rounded" => esc_html__("Slightly Rounded", 'nectar-blocks-theme'),
           "slightly_rounded_shadow" => esc_html__("Slightly Rounded Shadow", 'nectar-blocks-theme'),
           "rounded" => esc_html__("Rounded", 'nectar-blocks-theme'),
           "rounded_shadow" => esc_html__("Rounded Shadow", 'nectar-blocks-theme'),
           "rounded_reveal" => esc_html__("Rounded Reveal", 'nectar-blocks-theme')
         ],
         'default' => 'slightly_rounded_shadow'
       ],
       [
         'id' => 'button-styling-roundness',
         'type' => 'slider',
         'title' => esc_html__('Button Roundness', 'nectar-blocks-theme'),
         'desc' => '',
         "default" => 4,
         "min" => 1,
         "step" => 1,
         "max" => 50,
         'subtitle' => esc_html__('Fine-tune the rounded edges of your buttons.', 'nectar-blocks-theme'),
         'required' => [ [ 'button-styling', '!=', 'default' ], [ 'button-styling', '!=', 'rounded'] , [ 'button-styling', '!=', 'rounded_shadow'], [ 'button-styling', '!=', 'rounded_reveal']  ],
         'display_value' => 'label',
       ],
        [
          'id' => 'animated-underline-type',
          'type' => 'select',
          'desc' => '',
          'title' => esc_html__('Animated Underline Type', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Various elements in Nectar Blocks display an animated underline when hovering over. This option allows you to globally fine-tune the styling of that line.', 'nectar-blocks-theme'),
          'options' => [
            'default' => esc_html__('Default', 'nectar-blocks-theme'),
            'ltr' => esc_html__('Left to Right Simple', 'nectar-blocks-theme'),
            'ltr-fancy' => esc_html__('Left to Right Fancy', 'nectar-blocks-theme'),
          ],
          'default' => 'ltr-fancy'
        ],
        [
          'id' => 'animated-underline-thickness',
          'type' => 'slider',
          'title' => esc_html__('Animated Underline Thickness', 'nectar-blocks-theme'),
          'desc' => '',
          "default" => 1,
          "min" => 1,
          "step" => 1,
          "max" => 4,
          'display_value' => 'label',
        ],
      //   array(
      //    'id' => 'general-link-style',
      //    'type' => 'select',
      //    'desc' => '',
      //    'title' => esc_html__('General Link Style', 'nectar-blocks-theme'),
      //    'subtitle' => esc_html__('This controls the styling of standard anchor links.', 'nectar-blocks-theme'),
      //    'options' => array(
      //      'default' => esc_html__('Inherit Accent Color', 'nectar-blocks-theme'),
      //      'basic-underline' => esc_html__('Basic Underline', 'nectar-blocks-theme')
      //    ),
      //    'default' => 'default'
      //  )

      ];

      // Overall font color if NB plugin is not active.
      if ( ! defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
        array_unshift(
            $controls,
            [
            'id' => 'overall-font-color',
            'type' => 'color',
            'title' => esc_html__('Theme Font Color', 'nectar-blocks-theme'),
            'subtitle' => '',
            'output' => Nectar_Dynamic_Colors()->kirki_arrays('overall-font-color'),
            'transparent' => false,
            'desc' => '',
            'default' => '#000000'
          ]
        );
      }

      return [
        'section_id' => 'general-settings-style-section',
        'settings' => [
          'title' => esc_html__( 'Styling', 'nectar-blocks-theme' ),
          'priority' => 2,
        ],
        'controls' => $controls
      ];
    }

    public static function get_section_functionality() {

      $controls = [
        [
          'id' => 'smooth-scroll',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Smooth Scrolling', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Toggle whether or not to enable smooth scrolling for browsers which support it.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '0'
        ],
        [
          'id' => 'smooth-scroll-strength',
          'type' => 'slider',
          'title' => esc_html__('Smooth Scroll Strength', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Controls the intensity of the smooth scrolling effect.', 'nectar-blocks-theme'),
          'desc' => '',
          'required' => [ ['smooth-scroll', '=', '1' ]],
          "default" => 0.75,
          "min" => 0.1,
          "step" => 0.05,
          "max" => 1,
          'display_value' => 'text'
        ],
         [
          'id' => 'meta_viewport',
          'type' => 'select',
          'title' => esc_html__('Meta Viewport Functionality', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Determines whether browser zooming/scaling is enabled or disabled. Enabling this will improve accessibility for users with low vision.', 'nectar-blocks-theme'),
          'options' => [
            "not_scalable" => "Not Scalable",
            "scalable" => "Scalable",
          ],
          'default' => 'not_scalable'
        ],
         [
           'id' => 'max_container_width',
           'type' => 'slider',
           'title' => esc_html__('Max Website Container Width', 'nectar-blocks-theme'),
           'subtitle' => esc_html__('Your container will scale to a maximum width of 1425px, use this option if you\'d like to increase that value.', 'nectar-blocks-theme'),
           'desc' => '',
           "default" => 1400,
           "min" => 1000,
           "step" => 10,
           "max" => 2400,
           'display_value' => 'text'
         ],
         [
           'id' => 'ext_responsive_padding',
           'type' => 'slider',
           'title' => esc_html__('Container Left/Right Padding', 'nectar-blocks-theme'),
           'subtitle' => esc_html__('The main content container will have 90px of padding set on left and right, use this option if you\'d like to modify that.', 'nectar-blocks-theme'),
           'desc' => '',
           "default" => 50,
           "min" => 20,
           "step" => 5,
           "max" => 120,
           'display_value' => 'text'
         ]

      ];

      return [
        'section_id' => 'general-settings-functionality-section',
        'settings' => [
          'title' => esc_html__( 'Functionality', 'nectar-blocks-theme' ),
          'priority' => 3,
        ],
        'controls' => $controls
      ];
    }

    public static function get_section_performance() {

      $controls = [
       [
          'id' => 'delay-js-execution',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Delay Javascript Execution', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Prevents theme javascript from running until the user makes an interaction such as scrolling, tapping etc.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '1'
        ],
        [
          'id' => 'delay-js-execution-devices',
          'type' => 'select',
          'title' => esc_html__('Devices to Activate "Delay Javascript" Logic', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('If you are using a performance plugin that caches your pages, this will need to be set to "All Devices" in order to take effect.', 'nectar-blocks-theme'),
          'required' => [ ['delay-js-execution', '=', '1' ]],
          'options' => [
            "all" => esc_html__("All Devices", 'nectar-blocks-theme'),
            "mobile" => esc_html__("Mobile", 'nectar-blocks-theme'),
          ],
          'default' => 'all'
        ],
        [
          'id' => 'defer-javascript',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Move jQuery to Footer', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Attempts to move jQuery to the footer to make it non render-blocking. Note that this can break third party plugins/scripts which require JavaScript to be loaded in the head.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '1'
        ],

         [
           'id' => 'typography_font_swap',
           'type' => 'nectar_blocks_switch_legacy',
           'title' => esc_html__('Font Display Swap', 'nectar-blocks-theme'),
           'subtitle' => esc_html__('This is a font performance option which will your allow text to display in a default font before Google fonts have loaded.', 'nectar-blocks-theme'),
           'desc' => '',
           'default' => '0'
         ],

         [
          'id' => 'rm-wp-emojis',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Remove WordPress Emoji Script/CSS', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Removes the WordPress Emoji assets which automatically convert emoticons to WP specific emojis.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '0'
        ]
      ];

      return [
        'section_id' => 'general-settings-performance-section',
        'settings' => [
          'title' => esc_html__( 'Performance', 'nectar-blocks-theme' ),
          'priority' => 5
        ],
        'controls' => $controls
      ];
    }

    public static function get_color_scheme() {
      $controls = [
        [
          'id' => 'accent-color',
          'type' => 'color',
          'title' => esc_html__('Accent Color', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Change this color to alter the accent color globally for your site.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '#3452ff',
          'output' => Nectar_Dynamic_Colors()->kirki_arrays('accent-color')
        ],
        [
          'id' => 'extra-color-1',
          'type' => 'color',
          'title' => esc_html__('Accent Color #2', 'nectar-blocks-theme'),
          'subtitle' => '',
          'default' => '#ff1053',
          'output' => Nectar_Dynamic_Colors()->kirki_arrays('extra-color-1')
        ],
    ];

      return [
        'section_id' => 'general-color-scheme-section',
        'settings' => [
          'title' => esc_html__( 'Color Scheme', 'nectar-blocks-theme' ),
          'priority' => 6
        ],
        'controls' => $controls
      ];
    }

    public static function get_form_styling() {
      $controls = [
        [
          'id' => 'form-style',
          'type' => 'select',
          'title' => esc_html__('Overall Form Style', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Sets the style of all form elements used.', 'nectar-blocks-theme'),
          'tooltip' => esc_html__('If you\'re trying to get third party forms to display without any styling from NectarBlocks, simply select the', 'nectar-blocks-theme') . ' <b>' . esc_html__('Inherit', 'nectar-blocks-theme') . '</b> ' . esc_html__('option.', 'nectar-blocks-theme'),
          'options' => [
            "default" => esc_html__("Inherit", 'nectar-blocks-theme'),
            "minimal" => esc_html__("Minimal", 'nectar-blocks-theme')
          ],
          'default' => 'default'
        ],

        [
          'id' => 'form-fancy-select',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Enable Fancy Select Styling', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('This will add additional styling and functionality to your select (dropdown) elements.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => '0'
        ],

        [
          'id' => 'form-submit-btn-style',
          'type' => 'select',
          'title' => esc_html__('Form Submit Button Style', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Select your desired style which will be used for submit buttons throughout your site', 'nectar-blocks-theme'),
          'desc' => '',
          'options' => [
            'default' => esc_html__('Default', 'nectar-blocks-theme'),
            'regular' => esc_html__('Nectar Btn', 'nectar-blocks-theme'),
            'see-through' => esc_html__('Nectar Btn See Through', 'nectar-blocks-theme')
          ],
          'default' => 'regular'
        ],
        [
           'id' => 'form-submit-spacing',
           'type' => 'spacing',
           'title' => esc_html__('Form Submit Button Padding', 'nectar-blocks-theme'),
           'subtitle' => esc_html__('Fine-tune form submit button padding.', 'nectar-blocks-theme'),
           'default' => [
               'padding-top' => '15px',
               'padding-right' => '20px',
           ],
           'choices' => [
            'labels' => [
              'padding-top' => esc_html__( 'Padding Top/Bottom', 'nectar-blocks-theme' ),
              'padding-right' => esc_html__( 'Padding Left/Right', 'nectar-blocks-theme' )
            ],
          ],
       ],
       [
         'id' => 'form-input-font-size',
         'type' => 'slider',
         'title' => esc_html__('Form Input Field Text Size', 'nectar-blocks-theme'),
         'subtitle' => esc_html__('Alters the font size for form input field/textarea elements.', 'nectar-blocks-theme'),
         'desc' => '',
         "default" => 14,
         "min" => 14,
         "step" => 1,
         "max" => 30,
         'display_value' => 'text'
       ],
       [
          'id' => 'form-input-spacing',
          'type' => 'spacing',
          'title' => esc_html__('Form Input Field Padding', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Fine-tune form input fields/textarea element padding.', 'nectar-blocks-theme'),
          'desc' => '',
          'default' => [
              'padding-top' => '10px',
              'padding-right' => '10px',
          ],
          'choices' => [
            'labels' => [
              'padding-top' => esc_html__( 'Padding Top/Bottom', 'nectar-blocks-theme' ),
              'padding-right' => esc_html__( 'Padding Left/Right', 'nectar-blocks-theme' )
            ],
          ],
      ],
      [
        'id' => 'form-input-border-width',
        'type' => 'select',
        'title' => esc_html__('Form Input Border Width', 'nectar-blocks-theme'),
        'options' => [
          "default" => esc_html__("Default", 'nectar-blocks-theme'),
          "1px" => esc_html__("1px", 'nectar-blocks-theme'),
          "2px" => esc_html__("2px", 'nectar-blocks-theme'),
          "3px" => esc_html__("3px", 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],

      [
        'id' => 'form-input-bg-color',
        'type' => 'color',
        'title' => esc_html__('Form Input BG Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'transparent' => false
      ],
      [
        'id' => 'form-input-text-color',
        'type' => 'color',
        'title' => esc_html__('Form Input Text Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'transparent' => false
      ],
      [
        'id' => 'form-input-border-color',
        'type' => 'color',
        'title' => esc_html__('Form Input Border Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'transparent' => false
      ],
      [
        'id' => 'form-input-border-color-hover',
        'type' => 'color',
        'title' => esc_html__('Form Input Border Hover Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'transparent' => false
      ],
      [
        'id' => 'form-input-border-color-focus',
        'type' => 'color',
        'title' => esc_html__('Form Input Border Focus Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'transparent' => false
      ],
      ];

      return [
        'section_id' => 'general-form-styling-section',
        'settings' => [
          'title' => esc_html__( 'Form Styling', 'nectar-blocks-theme' ),
          'priority' => 7
        ],
        'controls' => $controls
      ];
    }

    public static function get_page_transitions() {
      $controls = [
        [
          'id' => 'page-transitions',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Page Transitions', 'nectar-blocks-theme'),
          'subtitle' => __('Enhance your site with the modern "View Transitions API" as a progressive enhancement. This cutting-edge web technology enables smoother page transitions. See', 'nectar-blocks-theme') . ' ' . '<a target="_blank" rel="noopener noreferrer" href="https://caniuse.com/view-transitions">' . esc_html__('which browsers support it.', 'nectar-blocks-theme') . '</a>',
          'default' => '0'
        ],
        [
          'id' => 'page-transitions-effect',
          'type' => 'select',
          'title' => esc_html__('Transition Effect', 'nectar-blocks-theme'),
          'subtitle' => '',
          'required' => [ ['page-transitions', '=', '1' ]],
          'options' => [
            "cross-fade" => __('Cross Fade', 'nectar-blocks-theme'),
            "fade" => __('Fade', 'nectar-blocks-theme'),
            "gradient-fade" => __('Gradient Fade', 'nectar-blocks-theme'),
            "push-reveal" => __('Push Reveal', 'nectar-blocks-theme')
          ],
          'default' => 'cross-fade'
        ],
        [
          'id' => 'page-transitions-bg-color',
          'type' => 'color',
          'title' => esc_html__('Transition BG Color', 'nectar-blocks-theme'),
          'subtitle' => '',
          'required' => [ ['page-transitions', '=', '1' ]],
          'transparent' => false,
          'tooltip' => __('Some effects will show this color during the transition.', 'nectar-blocks-theme'),
          'default' => ''
        ],
        [
          'id' => 'page-transitions-mobile',
          'type' => 'nectar_blocks_switch_legacy',
          'title' => esc_html__('Enable on Mobile', 'nectar-blocks-theme'),
          'subtitle' => esc_html__('Some mobile devices may struggle to handle page transitions, especially when heavy block animations are involved.', 'nectar-blocks-theme'),
          'required' => [ ['page-transitions', '=', '1' ]],
          'default' => '0'
        ],
    ];

      return [
        'section_id' => 'general-page-transitions-section',
        'settings' => [
          'title' => esc_html__( 'Page Transitions', 'nectar-blocks-theme' ),
          'priority' => 7
        ],
        'controls' => $controls
      ];
    }
  }
