<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\{Log, HTTP};
use Nectar\Global_Settings\Nectar_Custom_Fonts;

/**
 * Global_Typography
 * @version 1.3.0
 * @since 0.0.2
 */
class Global_Typography extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_global_typography';

  public static $devices = [
    'desktop' => '@media all',
    'tablet' => '@media (max-width: 1024px)',
    'mobile' => '@media (max-width: 767px)',
  ];

  function __construct() {
    add_action('after_setup_theme', [$this, 'initialize_defaults']);
  }

  public function initialize_defaults() {
    $nectar_options = get_option($this::$OPTION_NAME);

    if ($nectar_options !== false) {
      return;
    }

    update_option(
        $this::$OPTION_NAME,
        $this->defaults()
    );
    Log::debug('Default typography initialized.');
  }

  public static function css_output( $view = 'render', $exclude_core_typography = false ) {

    $global_typography = get_option(self::$OPTION_NAME);

    if ( ! is_array($global_typography) ) {
      return;
    }

    $typo_css = '';

    $typo_css .= self::create_uploaded_fonts_style('editor');

    $editor_class_name = $view === 'editor' ? '.block-editor-block-list__layout ' : '';

    foreach ( $global_typography as $category => $fonts ) {

      foreach ( $fonts as $font => $settings ) {

        if ( ! is_array($settings) ) {
          continue;
        }

        // Selectors
        $selectors = '.' . $font;

        if ( $category === 'coreTypography' ) {
          // Bypass core_typography if user is ignoring it
          if ($exclude_core_typography) {
            continue;
          }

          // body needs to be handled differently in editor.
          if ( $font === 'body' && $view === 'editor' ) {
            $selectors = '.nectar-font-' . $font . ', ' . $editor_class_name;
          } else if ( $font === 'h1' && $view === 'editor' ) {
            $selectors = '.nectar-font-' . $font . ', .edit-post-visual-editor__post-title-wrapper ' . $font . ',' . $editor_class_name . $font;
          } else {
            $selectors = $editor_class_name . '.nectar-font-' . $font . ', ' . $editor_class_name . $font;
          }

        } else if ( $category === 'userTypography' ) {

          $selectors = $editor_class_name . '.' . $font;

           // reassigned fonts.
          if ( isset($settings['reassigned']) ) {
            $settings = $global_typography['coreTypography'][$settings['reassigned']];
          }
        }

        $enable_responsive_sizing = true;
        // Rules which skip font sizing.
        if ( $font === 'em' && $category === 'coreTypography' ) {
          $enable_responsive_sizing = false;
        }

        //// Family, weight, style, letter spacing, transform, color.
        $typo_css .= $selectors . ' {';
          $typo_css .= self::get_core_font_properties($settings);
        $typo_css .= "}";

        //// Responsive values.
        if ( $enable_responsive_sizing ) {
          // Font size.
          $typo_css .= self::get_font_size_rules($settings, $selectors);

          // Line height.
          $typo_css .= self::get_line_height_rules($settings, $selectors);
        }

        //// CSS Variables
        $typo_css .= ':root { ' . self::get_core_font_properties($settings, $font) . '}';

      } // End fonts loop.

    } // End category loop.

    return (! empty($typo_css)) ? $typo_css : '';

  }

  /**
   * @param array $settings
   * @return string Font family properties.
   */
  public static function get_core_font_properties(array $settings, $css_variable_name = '') {
    $typo_css = '';

    $font_family = self::get_font_family_properties($settings);
    $font_weight = self::get_font_weight_properties($settings);
    $font_style = self::get_font_style_properties($settings);
    $font_letter_spacing = self::get_font_letter_spacing_properties($settings);
    $font_transform = self::get_font_transform_properties($settings);
    $font_color = self::get_font_color_properties($settings);

    // CSS Vars.
    if ( ! empty($css_variable_name) ) {
      if ( ! empty($font_family) )  {
        $typo_css .= '--' . $css_variable_name . '-' . $font_family;
      }
      if ( ! empty($font_style) )  {
        $typo_css .= '--' . $css_variable_name . '-' . $font_style;
      }
      if ( ! empty($font_weight) )  {
        $typo_css .= '--' . $css_variable_name . '-' . $font_weight;
      }
      if ( ! empty($font_letter_spacing) )  {
        $typo_css .= '--' . $css_variable_name . '-' . $font_letter_spacing;
      }
      if ( ! empty($font_transform) )  {
        $typo_css .= '--' . $css_variable_name . '-' . $font_transform;
      }
      if ( ! empty($font_color) ) {
        $typo_css .= '--' . $css_variable_name . '-' . $font_color;
      }
    } else {
      // Regular CSS rules..
      $typo_css .= $font_family;
      $typo_css .= $font_style;
      $typo_css .= $font_weight;
      $typo_css .= $font_letter_spacing;
      $typo_css .= $font_transform;
      $typo_css .= $font_color;
    }

    return $typo_css;

  }

  /**
   * @param array $settings
   * @return string Font family properties.
   */
  public static function get_font_family_properties(array $settings) {

    $css = '';

    if( isset($settings['fontFamily']) && ! empty($settings['fontFamily']) ) {
      if (
          isset($settings['fontSource']) && $settings['fontSource'] === 'Uploaded' ||
          self::font_needs_quotes($settings['fontFamily'])
        ) {
        $css .= "font-family: \"$settings[fontFamily]\";";
      } else {
        $css .= "font-family: $settings[fontFamily];";
      }

    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font transform properties.
   */
  public static function get_font_transform_properties(array $settings) {

    $css = '';

    if( isset($settings['transform']) && ! empty($settings['transform']) ) {
      $css .= "text-transform: $settings[transform];";
    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font align properties.
   */
  public static function get_font_letter_spacing_properties(array $settings) {

    $css = '';

    if( isset($settings['letterSpacing']) &&
        isset($settings['letterSpacing']['value']) ) {

        if ( ! empty($settings['letterSpacing']['value']) || $settings['letterSpacing']['value'] === 0 ) {
          $css .= 'letter-spacing: ' . self::sizing_field_to_string($settings['letterSpacing']) . ';';
        }
    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font color properties.
   */
  public static function get_font_color_properties(array $settings) {

    $css = '';

    if( isset($settings['fontColor']) && isset($settings['fontColor']['globalColorData']) ) {
      $global_color = $settings['fontColor']['globalColorData'];
      $css .= 'color: var(--' . $global_color['slug'] . ');';
    }
    else if( isset($settings['fontColor']) && ! empty($settings['fontColor']['value'])) {
      $css .= 'color:' . $settings['fontColor']['value'] . ';';
    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font weight properties.
   */
  public static function get_font_style_properties(array $settings) {

    $css = '';
    // if ( isset($settings['fontSource']) && $settings['fontSource'] === 'Uploaded') {
    //   return $css;
    // }

    if( isset($settings['fontWeight']) && strpos($settings['fontWeight'], 'italic' ) !== false ) {
      // Check if style is merged into weight. (Google does this)
      $css .= "font-style: italic;";
    } else if( isset($settings['fontStyle']) && ! empty($settings['fontStyle']) ) {
      $css .= "font-style: $settings[fontStyle];";
    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font weight properties.
   */
  public static function get_font_weight_properties(array $settings) {
    $css = '';
    // if ( isset($settings['fontSource']) && $settings['fontSource'] === 'Uploaded') {
    //   return $css;
    // }

    if( isset($settings['fontWeight']) ) {

      if (strpos($settings['fontWeight'], 'italic') !== false ) {
        $font_weight = str_replace('italic', '', $settings['fontWeight']);
        if ( strlen($font_weight) > 0 ) {
          $css .= "font-weight: $font_weight;";
        }
      }

      else if ( $settings['fontWeight'] === 'regular' ) {
        $css .= 'font-weight: normal;';
      }

      else {
        $css .= "font-weight: $settings[fontWeight];";
      }

    }

    return $css;
  }

  /**
   * @param array $settings
   * @param string $selectors
   * @return string Full rules inside media queries.
  */
  public static function get_line_height_rules(array $settings, string $selectors) {
    $css = '';
    $ordered_devices = array_keys(self::$devices);
    foreach ($ordered_devices as $device) {
      if (! isset($settings['lineHeight'][$device])) {
        continue;
      }

      $line_height = $settings['lineHeight'][$device];

      if( isset($line_height['disabled']) && $line_height['disabled'] === true ) {
        continue;
      }

      if( isset($line_height['value']) && ! empty($line_height['value']) ) {
        $css .= self::$devices[$device] . ' { ' . $selectors . ' { line-height: ' .
          self::sizing_field_to_string($line_height) . '; } }';
      }
    }

    return $css;
  }

  /**
   * @param array $settings
   * @param string $selectors
   * @return string Full rules inside media queries.
   */
  public static function get_font_size_rules(array $settings, string $selectors) {
    $css = '';
    $ordered_devices = array_keys(self::$devices);
    foreach ($ordered_devices as $device) {
      if (! isset($settings['fontSize'][$device])) {
        continue;
      }

      $size = $settings['fontSize'][$device];

      $clampValues = [
        'min' => isset($settings['fontSizeMin'][$device]) ? $settings['fontSizeMin'][$device] : false,
        'max' => isset($settings['fontSizeMax'][$device]) ? $settings['fontSizeMax'][$device] : false
      ];

      if( isset($size['disabled']) && $size['disabled'] === true ) {
        continue;
      }

      if( isset($size['value']) && ! empty($size['value']) ) {
        $css .= self::$devices[$device] . ' { ' . $selectors . ' { font-size: ' .
          self::clamp_values($clampValues, $size) . '; } }';
      }
    }

    return $css;
  }

  public static function font_needs_quotes($fontFamily) {
    return preg_match('/[^a-zA-Z\s-]/', $fontFamily) === 1;
  }

  public static function sizing_field_to_string($size) {
    if (isset($size['value']) && ! empty($size['value']) || $size['value'] === 0 ) {
      return $size['value'] . $size['unit'];
    }
    return '';
  }

  public static function clamp_values($clamp, $size) {

    if (isset($clamp['min']['value']) && isset($clamp['max']['value']) &&
      ! empty($clamp['min']['value']) && ! empty($clamp['max']['value'])) {
      return 'clamp(' . self::sizing_field_to_string($clamp['min']) . ', ' . self::sizing_field_to_string($size) . ', ' . self::sizing_field_to_string($clamp['max']) . ')';
    }
    else if (isset($clamp['min']['value']) && ! empty($clamp['min']['value'])) {
      return 'clamp(' . self::sizing_field_to_string($clamp['min']) . ', ' . self::sizing_field_to_string($size) . ', 100vw)';
    }
    else if (isset($clamp['max']['value']) && ! empty($clamp['max']['value'])) {
      return 'clamp(0px, ' . self::sizing_field_to_string($size) . ', ' . self::sizing_field_to_string($clamp['max']) . ')';
    }

    return self::sizing_field_to_string($size);

  }

  public static function create_uploaded_fonts_style(string $view) {
    // $typography = get_option(self::$OPTION_NAME);
    $custom_fonts = apply_filters('nectar_custom_font_list', Nectar_Custom_Fonts::get_options());
    $fonts_to_output = $custom_fonts;

    // if ($view === 'editor') {
    //   $fonts_to_output = $custom_fonts;
    // } else {
    //   $all_typography = array_merge($typography['coreTypography'], $typography['userTypography']);

    //   // Add uploaded fonts used in theme to the same google font link
    //   if ($view === 'frontend') {
    //     if (function_exists('Nectar_Dynamic_Fonts')) {
    //       $Nectar_Dynamic_Fonts = Nectar_Dynamic_Fonts();

    //       if (method_exists($Nectar_Dynamic_Fonts, 'get_used_theme_uploaded_fonts')) {
    //         $theme_uploaded_fonts = $Nectar_Dynamic_Fonts::get_used_theme_uploaded_fonts();
    //         $all_typography = array_merge($all_typography, $theme_uploaded_fonts);
    //       }
    //     }
    //   }

    //   foreach ($all_typography as $font) {
    //     if (array_key_exists('fontSource', $font) && $font['fontSource'] === 'Uploaded') {

    //       if (array_key_exists('fontFamily', $font) && $font['fontFamily'] === '') {
    //         continue;
    //       }

    //       $font_family = $font['fontFamily'];

    //       $custom_font = $custom_fonts[$found_index];
    //       $fonts_to_output[$font_family] = $custom_font;
    //     }
    //   }
    // }

    $output = '';

    foreach ($fonts_to_output as $slug => $custom_font) {
      foreach ($custom_font['variations'] as $variation) {
        $exploded = explode('.', $variation['url']);
        $format = array_pop($exploded);
        if ($format !== 'woff' && $format !== 'woff2') {
          $format = 'truetype';
        }

        $font_output = '@font-face { ' .
        'font-family: "' . esc_attr($custom_font['name']) . '"; ' .
        'src: url("' . HTTP::maybe_force_https(esc_attr($variation['url'])) . '") format("' . esc_attr($format) . '");';
        // 'src: url("' . esc_attr($variation['url']) . '");'; // No format

        if (isset($variation['fontData']['fontStyle']) && $variation['fontData']['fontStyle'] !== '') {
          $font_output .= 'font-style: ' . esc_attr($variation['fontData']['fontStyle']) . ';';
        }

        $font_output .= 'font-weight: ' . esc_attr($variation['fontData']['weight']) . ';';
        $font_output .= 'font-display: swap;';

        $font_output .= '}';

        $output .= $font_output;
      }
    }

    return $output;
  }

  /**
   * @return link to google fonts or false if no google fonts are used.
   */
  public static function create_google_fonts_link($view) {

    $typography = get_option(self::$OPTION_NAME);

    $all_typography = array_merge($typography['coreTypography'], $typography['userTypography']);

    $google_fonts = [];
    $google_fonts_subsets = [];

    // Add google fonts used in theme to the same google font link
    if ($view === 'frontend') {
      if (function_exists('Nectar_Dynamic_Fonts')) {
        $Nectar_Dynamic_Fonts = Nectar_Dynamic_Fonts();

        if (method_exists($Nectar_Dynamic_Fonts, 'get_used_theme_google_fonts')) {
          $theme_google_fonts = $Nectar_Dynamic_Fonts::get_used_theme_google_fonts();
          $all_typography = array_merge($all_typography, $theme_google_fonts);
        }
      }
    }

    // go through all saved typography settings and save google fonts/weights/subsets.
    foreach ($all_typography as $font) {
      if ($font['fontSource'] === 'Google') {

        // Make sure user font's aren't redirected
        if (isset($font['reassigned'])) {
          continue;
        }

        // font family.
         $family = urlencode($font['fontFamily']);
         if( ! isset($google_fonts[$family]) ) {
          $google_fonts[$family] = [];
        }

        // weights.
        if( isset($font['fontWeight']) ) {

          $font_weight = $font['fontWeight'];

          if ( 'regular' === $font_weight ) {
            $font_weight = '400';
          }

          if( ! isset($google_fonts[$family][$font_weight]) ) {
            $google_fonts[$family][$font_weight] = $font_weight;
          }

        } // end font weight.

        // subset.
        if( isset($font['fontSubset']) && ! empty($font['fontSubset'])) {

          if( ! isset($google_fonts_subsets[$font['fontSubset']]) ) {
            $google_fonts_subsets[$font['fontSubset']] = $font['fontSubset'];
          }

        } else {
          $google_fonts_subsets['latin'] = 'latin';
        }

      }
    }

    // create the link.
    if( ! empty($google_fonts) ) {

      // add each family and its weights to the link.
      $joined_fonts = '';
      foreach( $google_fonts as $font_name => $font_weight) {

        // When no family name is set, skip.
        if( empty($font_name) ) {
          continue;
        }

        if($view === 'editor') {
          $font_weight = ['100,200,400,500,600,700,800,900,italic'];
        }
        $joined_fonts .= $font_name . ':' . implode(',', $font_weight) . '|';
      }

      $subsets = ! empty($google_fonts_subsets) ? implode(',', $google_fonts_subsets) : 'latin';
      if ( ! empty($joined_fonts) ) {
        return 'https://fonts.googleapis.com/css?family=' . $joined_fonts . '&display=swap&subset=' . $subsets;
      }
    }

    return false;
  }

  /**
   * Default typography loaded into the database.
   * See:
   *  common.ts - TypographyData
   */
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

  // same as default typography, but with different font family.
  public static function default_secondary_typography() {
    $typography = self::default_typography();
    $typography['fontFamily'] = 'Playfair Display';
    $typography['fontData'] = [
      'variants' => [
        "regular",
        "500",
        "600",
        "700",
        "800",
        "900",
        'italic',
        '500italic',
        '600italic',
        '700italic',
        "800italic",
        "900italic"
      ],
      'subsets' => [
        'cyrillic',
        'latin',
        'latin-ext',
        'vietnamese'
      ],
    ];
    return $typography;
  }

  /**
   * Provides defaults for global colors.
   */
  private function defaults() {

    $typography_default = $this->default_typography();
    $typography_secondary_default = $this->default_secondary_typography();

    return [
      'coreTypography' => [
        'body' => array_merge($typography_default, [
          'label' => 'Body',
          'letterSpacing' => [
            'value' => 0,
            'unit' => 'em',
            'disabled' => false
          ],
          'fontColor' => [
            'value' => '#000000',
            'globalColorData' => [
              'slug' => 'dark',
              'value' => '#000000',
              'label' => 'Dark',
            ],
          ]
        ]),
        'h1' => array_merge($typography_default, [
          'label' => 'Heading 1',
          'fontSize' => [
            'desktop' => [
              'value' => 2.5,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'fontWeight' => '500'
        ]),
        'h2' => array_merge($typography_secondary_default, [
          'label' => 'Heading 2',
          'fontSize' => [
            'desktop' => [
              'value' => 2,
              'unit' => 'rem',
              'disabled' => false
            ]
          ]
        ]),
        'h3' => array_merge($typography_default, [
          'label' => 'Heading 3',
          'fontSize' => [
            'desktop' => [
              'value' => 1.75,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'fontWeight' => '500'
        ]),
        'h4' => array_merge($typography_default, [
          'label' => 'Heading 4',
          'fontSize' => [
            'desktop' => [
              'value' => 1.5,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'fontWeight' => '500'
        ]),
        'h5' => array_merge($typography_default, [
          'label' => 'Heading 5',
          'fontSize' => [
            'desktop' => [
              'value' => 1.25,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'fontWeight' => '500'
        ]),
        'h6' => array_merge($typography_default, [
          'label' => 'Heading 6',
          'fontWeight' => '500'
        ]),
        'label' => array_merge($typography_default, [
          'label' => 'Labels',
          'fontSize' => [
            'desktop' => [
              'value' => 0.75,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'letterSpacing' => [
            'value' => 0.05,
            'unit' => 'em',
            'disabled' => false
          ],
          'transform' => 'uppercase',
          'fontWeight' => '500'
        ]),
        'em' => array_merge($typography_secondary_default, [
          'label' => 'Italic',
          'fontSize' => [
            'desktop' => [
              'value' => 1,
              'unit' => 'rem',
              'disabled' => false
            ]
          ],
          'fontStyle' => 'italic',
        ]),
      ],
      'userTypography' => [],
    ];
  }
}