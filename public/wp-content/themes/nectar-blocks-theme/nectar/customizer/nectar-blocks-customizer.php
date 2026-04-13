<?php

/**
 * NectarBlocks Customizer.
 *
 * @package Nectar Blocks Theme
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// Custom components
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-title.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-divider.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/image-select/ImageSelect.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/icon-select/IconSelect.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/typography/Typography.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/divider/Divider.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/switch-legacy/SwitchLegacy.php';

// Panel imports
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/before-panels.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/after-panels.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/general-settings.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/typography.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/core.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/general-wordpress-settings.php';

// Layout
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/layout.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/layout/footer.php';

// Post Types
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/post-types.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/blog.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/woocommerce.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/portfolio.php';

if ( ! class_exists( 'NectarBlocks_Customizer' ) ) {

  class NectarBlocks_Customizer {
    private static $instance = null;

    public $nectar_post_message_fields = [];

    public function __construct() {
      // Enqueue scripts
      add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

      $this->register_custom_kirki();
      $this->kirki_config();
      add_action( 'customize_register', [ $this, 'build_customizer_panels' ] );

      // Set flag for dynamic css regeneration.
      add_action( 'customize_save_after', [$this, 'after_customizer_save'] );

      // Post message
      add_action( 'customize_preview_init', [$this, 'postmessage_init'] );

      // Remove default Kirki notice.
      $kirki_notices = get_option( 'kirki_notices', [] );
      if  ( empty($kirki_notices) ) {
        // this array may change with different versions:
        // https://github.com/themeum/kirki/blob/b1fe1faad2a7b300248af593a0f37796c16fdfc5/kirki-packages/settings/src/Notice.php#L83
        update_option( 'kirki_notices', ['discount_notice' => true] );
      }

      $defaults_set = get_option( 'nectar_customizer_defaults_set', false );
      if ( ! $defaults_set ) {
        $this->set_default_values();

        // regenerate dynamic css
        set_transient( 'nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
        update_option( 'nectar_customizer_defaults_set', true );
      }

    }

    /**
     * Gets an instance
     *
     * @since 14.0.2
     * @return NectarBlocks_Customizer
     */
    public static function get_instance() {
      if (self::$instance == null) {
        self::$instance = new NectarBlocks_Customizer();
      }

      return self::$instance;
    }

    public function set_default_values() {
      foreach( Kirki::$all_fields as $field ) {
        if( isset($field['default']) ) {
          $value = get_theme_mod( $field['settings'], null );
          if( null === $value ) {
            set_theme_mod( $field['settings'], $field['default'] );
          }
        }
      }
    }

    public function kirki_base_color_palette() {
      return [
        '#000000',
        '#ffffff',
        '#dd3333',
        '#dd9933',
        '#eeee22',
        '#81d742',
        '#1e73be',
        '#8224e3'
      ];
    }

    public function nectarblocks_color_palette() {
      $base_color_palette = self::kirki_base_color_palette();

      // Try and get a synced color palette from the Nectarblocks plugin
      if (class_exists('Nectar\Global_Settings\Global_Colors')) {
        $global_colors_class = new \Nectar\Global_Settings\Global_Colors();
        $global_colors = $global_colors_class->get_global_colors();
        if ( isset($global_colors['solids']) ) {
          $values = array_column($global_colors['solids'], 'value');
          // remove duplicates values
          $values = array_values(array_unique($values));
          // MUST contain minimum of 8 Colors or else kirki will explode.
          if ( count($values) < 8 ) {
            $itemsNeeded = 8 - count($values);
            for ($i = 0; $i < $itemsNeeded; $i++) {
                array_push($values, $base_color_palette[$i]);
            }
          }
          return $values;
        }
      }

      // default kirki color scheme.
      return $base_color_palette;

    }

    public function postmessage_init() {

      wp_enqueue_script(
          'nectar-postmessage-js',
          get_template_directory_uri() . '/build/postMessage.js',
          [ 'jquery', 'customize-preview', 'wp-hooks', 'underscore' ],
          nectar_get_theme_version(),
          true
      );

      // send postmessage vars to js
      wp_localize_script( 'nectar-postmessage-js', 'nectarPostMessageFields', $this->nectar_post_message_fields );
    }

    public function after_customizer_save() {

      // Reset the defaults flag.
      update_option( 'nectar_customizer_defaults_set', false );

      // Set flag for dynamic css regeneration.
      set_transient('nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
    }

    private function register_custom_kirki() {
      add_action( 'customize_register', function( $wp_customize ) {
        // Register our custom control with Kirki.
        add_filter(
            'kirki_control_types',
            function( $controls ) {
              $controls['nectar_image_select'] = 'ImageSelect';
              $controls['nectar_icon_select'] = 'IconSelect';
              $controls['nectar_typography'] = 'Typography';
              $controls['nectar_divider'] = 'Divider';
              $controls['nectar_blocks_switch_legacy'] = 'SwitchLegacy';
            return $controls;
          }
        );
      });
    }

    /**
     * Creates the Kirki configuration by pulling in settings.
     *
     * @since 14.0.2
     * @return void
     */
    private function kirki_config() {

      $kirki_config = [
        'capability' => 'edit_theme_options',
      ];

       if( ! is_customize_preview() ) {
        $kirki_config['disable_output'] = true;
        add_action( 'wp_enqueue_scripts', [$this, 'dequeue_assets'], 1000);

        // $kiri_config['styles_priority'] = 10;

        // Tell kirki to load css from file instead of inline
        // add_filter( 'kirki_output_inline_styles', '__return_false' );
       }

       Kirki::add_config( 'nectar_kirki_customizer', $kirki_config );

      // Disable telemetry
      add_filter( 'kirki_telemetry', '__return_false' );

      // Begin building Kirki sections, create a flat array of partials
      // Is a list of panel and section definitions.
      $customizer_partials = NectarBlocks_Panel_Section_Helper::get_instance()->nectar_customizer_panels_flat();

      // Process each section Kirki partials by mapping them into Kirki configuration classes
      foreach ($customizer_partials as &$partial) {

        if ( empty ( $partial ) ) {
          continue;
        }

        if ( isset( $partial['panel_id'] ) ) {

          Kirki::add_panel( $partial['panel_id'], $partial['settings']);

        } elseif ( isset( $partial['section_id'] ) ) {

          Kirki::add_section( $partial['section_id'], $partial['settings']);

          if ( array_key_exists( 'controls', $partial ) ) {
            foreach ($partial['controls'] as $index => $control) {

              // Add priority to keep custom elements in correct placement.
              $control['priority'] = $index;

              // Map to kirki control class
              $this->map_kirki( $control, $partial['section_id']);

              // Store for postmessage.
              if( isset($control['nectar_post_message_data']) ) {
                $this->nectar_post_message_fields[] = $control;
              }

            }
          } else {
            error_log('Unable to find controls for section: ' . $partial['section_id']);
          }

        }
      }

    }

    private function map_kirki( $control, $section ) {
      $settings = [
        'section' => $section,
        'settings' => $control['id'],
        'label' => isset($control['title']) ? $control['title'] : '',
      ];

      // Checks for css output.
      if ( array_key_exists( 'output', $control ) ) {
        $settings['output'] = $control['output'];

        $settings['transport'] = 'auto';
      }

      if ( array_key_exists( 'transport', $control ) ) {
        $settings['transport'] = $control['transport'];
      }

      // Checks for the existence of subtitle
      if ( array_key_exists( 'subtitle', $control ) ) {
        $settings['description'] = $control['subtitle'];
      }

      // Tooltip
      if ( array_key_exists( 'tooltip', $control ) ) {
        $settings['tooltip'] = $control['tooltip'];
      }

      // Partial refresh
      if ( array_key_exists( 'partial_refresh', $control ) ) {
        $settings['partial_refresh'] = $control['partial_refresh'];
      }

      // Checks for the existence of default
      if ( array_key_exists( 'default', $control ) ) {
        $settings['default'] = $control['default'];
      }

      // Checks for the existence of priority
      if ( array_key_exists( 'priority', $control ) ) {
        $settings['priority'] = $control['priority'];
      }

      // Checks for the existence of required settings
      if ( array_key_exists( 'required', $control ) ) {
        $callback = [];

        // TODO: These arrays need to be nested. Couldn't find a sexy way to do this,
        // so I ended up doing it in code.
        // Make it an array
        // if ( !is_array( $control['required'] )) {
        //   $control['required'] = [ $control['required'] ];
        // }

        foreach ( $control['required'] as $k => $r) {

          // or conditional passed in
          if( $k === 'or' ) {
            $callback[0] = [];
            foreach($r as $arr ) {
              $callback[0][] = $arr;
            }
          }

          // && conditional passed in
          else {

            array_push($callback, [
              'setting' => $r[0],
              'operator' => $r[1],
              'value' => $r[2]
            ] );
          }
        }
        $settings['active_callback'] = $callback;
      }

      if ( $control['type'] == 'select' ) {
        $settings = array_merge($settings, [
          'type' => 'select',
          'choices' => $control['options']
        ]);

      } else if ( $control['type'] == 'multi_select' ) {
        $settings = array_merge($settings, [
          'type' => 'select',
          'multiple' => 100,
          'choices' => $control['options']
        ]);

      } else if ( $control['type'] == 'dropdown_pages' ) {
        $settings = array_merge($settings, [
          'type' => 'dropdown_pages',
          'multiple' => isset($control['multiple']) ? $control['multiple'] : false,
        ]);

      } elseif ( $control['type'] == 'slider' ) {
        $settings = array_merge($settings, [
          'type' => 'slider',
          'choices' => [
            'min' => $control['min'],
            'max' => $control['max'],
            'step' => $control['step']
          ],
        ]);

      } elseif ( $control['type'] == 'typography' ) {
        $settings = array_merge($settings, [
          'type' => 'nectar_typography',
        ]);

      } elseif ( $control['type'] == 'code' ) {
        $settings = array_merge($settings, [
          'type' => 'code',
          'choices' => $control['choices'],
        ]);

      } elseif ( $control['type'] == 'media' ) {
        $settings = array_merge($settings, [
          'type' => 'image',
          'choices' => [
            'save_as' => 'array'
          ]
        ] );
      } elseif ( $control['type'] == 'radio' ) {
        $settings = array_merge($settings, [
          'type' => 'radio',
          'choices' => $control['options']
        ]);

      } elseif ( $control['type'] == 'color_gradient' ) {
        $settings = array_merge($settings, [
          'type' => 'multicolor',
          'choices' => [
            'to' => esc_html__( 'To', 'nectar-blocks-theme' ),
            'from' => esc_html__( 'From', 'nectar-blocks-theme' )
          ]
        ]);

      } elseif ( $control['type'] == 'multicolor' ) {
        $settings = array_merge($settings, [
          'type' => 'multicolor',
          'choices' => $control['choices']
        ]);

      } elseif ( $control['type'] == 'info' ) {

        $settings = array_merge($settings, [
          'type' => 'custom',
          'default' =>
          '<div class="nectar-customizer__info">' .
          '<div class="nectar-customizer__info--text">' . $control['desc'] . '</div>' .
          '</div>'
        ]);

      } elseif ( $control['type'] == 'spacing' ) {
        $settings = array_merge($settings, [
          'type' => 'dimensions',
          'choices' => $control['choices']
        ]);

      } elseif ( $control['type'] == 'dimension' ) {
        $settings = array_merge($settings, [
          'type' => 'dimension',
          'choices' => $control['choices']
        ]);

      }  elseif ( $control['type'] == 'image_select' ) {
        $settings = array_merge($settings, [
          'type' => 'nectar_image_select',
          'choices' => $control['options']
        ]);

      }  elseif ( $control['type'] == 'icon_select' ) {
        $settings = array_merge($settings, [
          'type' => 'nectar_icon_select',
          'choices' => $control['options']
        ]);

      } elseif ( $control['type'] == 'editor' ) {
        $settings = array_merge($settings, [
          'type' => 'editor'
        ]);

      } elseif ( $control['type'] == 'nectar_divider' ) {
        $settings = array_merge($settings, [
          'type' => 'nectar_divider'
        ]);

      } elseif ( $control['type'] == 'radio-image' ) {

          $settings = array_merge($settings, [
            'type' => 'radio-image',
            'choices' => $control['options']
          ]);

      } elseif ( $control['type'] == 'toggle' ) {
        $settings['type'] = 'toggle';

        // Turns the default into an integer instead of string.
        if ( isset( $control['default'] ) && is_int( $control['default'] ) ) {
          $settings['default'] = $control['default'];
        } else if ( isset( $control['default'] ) && $control['default'] == '1' ) {
          $settings['default'] = 1;
        } else {
          $settings['default'] = 0;
        }

      }
      else if( $control['type'] == 'nectar_blocks_switch_legacy' ) {
        $settings = array_merge($settings, [
          'type' => 'nectar_blocks_switch_legacy'
        ]);
      }
      elseif ( $control['type'] == 'text' ) {

        if ( array_key_exists( 'validate', $control ) && $control['validate'] == 'numeric' )  {
          $text_type = 'number';
        } else {
          $text_type = 'text';
        }

        $settings = array_merge($settings, [
          'type' => $text_type
        ]);

      } elseif ( $control['type'] == 'generic' ) {

        $settings = array_merge($settings, [
          'type' => $control['type'],
          'choices' => $control['choices'],
        ]);

      }  elseif ( $control['type'] == 'color' ) {

        $choices = [
          'swatches' => self::nectarblocks_color_palette()
        ];
        if ( array_key_exists( 'choices', $control ) ) {
          $choices = array_merge($choices, $control['choices']);
        }
        $settings = array_merge($settings, [
          'type' => 'color',
          'choices' => $choices
        ]);
      // Pass through controls
      // Will through an error if we process one that isn't on the approved list
      } elseif ( in_array( $control['type'], [ 'switch', 'textarea', 'checkbox' ] ) ) {

        $settings = array_merge($settings, [
          'type' => $control['type'],
        ]);

      } else {
        // var_dump('Error type not found');
        echo ('Error type not found:' . $control['type']);
        return;
      }

      Kirki::add_field( 'nectar_kirki_customizer', $settings );
    }

    public function dequeue_assets() {
      wp_dequeue_style( 'kirki-styles' );
    }

    public function enqueue_scripts($hook) {

      $css_uri = get_template_directory_uri() . '/build/customizer.css';
      $js_uri = get_template_directory_uri() . '/build/customizer.js';

      $screen = get_current_screen();
      if( $screen && $screen->id && 'customize' === $screen->id) {

          wp_enqueue_style(
              'nectar-customizer-css',
              $css_uri,
              [],
              nectar_get_theme_version()
          );

          $asset_file = require NECTAR_THEME_DIRECTORY . '/build/customizer.asset.php';

          wp_enqueue_script(
              'nectar-customizer-js',
              $js_uri,
              $asset_file['dependencies'],
              $asset_file['version'],
              true
          );
          // Localize the script with translations.
          wp_set_script_translations( 'nectar-customizer-js', 'nectar-blocks-theme', get_template_directory() . '/languages'  );

          // Load Google fonts from theme options.
          // This is so the saved fonts are correct in the selector.
          if ( ! defined('NECTAR_BLOCKS_VERSION') ) {
            $google_fonts = Nectar_Dynamic_Fonts::create_google_fonts_link();
            if ( $google_fonts ) {
              wp_enqueue_style( 'nectar-google-fonts', $google_fonts, [], null );
            }
          }
      }

    }

    public function build_customizer_panels( $wp_customize ) {

       // Need to alter the priority of the title_tagline section to be under our panels
       // inside of the WordPress / Plugin Options section
        if ( $wp_customize->get_section( 'title_tagline' ) ) {
          $wp_customize->get_section( 'title_tagline' )->priority = 40;
        }

      // Add the Nectar customizer panels
      $customizer_partials = [
        NectarBlocks_Customizer_Before_Panels::get_partials(),
        NectarBlocks_Customizer_General_Settings::get_partials(),
        NectarBlocks_Customizer_Typography::get_partials(),
        NectarBlocks_Customizer_Layout::get_partials(),
        NectarBlocks_Customizer_Post_Types::get_partials(),
        NectarBlocks_Customizer_General_WP_Settings::get_partials(),
        NectarBlocks_Customizer_Core::get_partials(),
        NectarBlocks_Customizer_After_Panels::get_partials(),
      ];

      $flat_partials = [];

      foreach ( $customizer_partials as $partial ) {
        foreach ( $partial as $field ) {
          $flat_partials[] = $field;
        }
      }

      foreach ($flat_partials as &$partial) {
        $control = $this->map_control_to_kirki( $partial );

        if ( array_key_exists( 'type', $control['settings'] ) ) {
          if ( $control['settings']['type'] == 'nectar-title' ) {
            $wp_customize->add_section(
                new NectarBlocks_Customizer_Title( $wp_customize, $control['id'], $control['settings'] )
            );
          } elseif ( $control['settings']['type'] == 'nectar-divider' ) {
            $wp_customize->add_section(
                new NectarBlocks_Customizer_Divider( $wp_customize, $control['id'], $control['settings'] )
            );
          }
        }

      }

      return;
    }

    private function map_control_to_kirki( $redux_control ) {
      // Newly added elements have type in a better spot
      if ( array_key_exists( 'type', $redux_control['settings'] ) &&
           in_array( $redux_control['settings']['type'], ['nectar-title', 'nectar-divider'] ) ) {
        return $redux_control;
      }

      $customizer_control = [
        'settings' => $redux_control['id'],
        'label' => $redux_control['title']
      ];
      $customizer_control['type'] = $redux_control['type'];
      return $customizer_control;
    }
  }

};

/**
 * Initialize the NectarBlocks_Customizer class.
 */
NectarBlocks_Customizer::get_instance();
