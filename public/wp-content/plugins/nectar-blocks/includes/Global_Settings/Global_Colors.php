<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Global Color Settings.
 * @version 0.0.6
 * @since 0.0.2
 */
class Global_Colors extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_global_colors';

  function __construct() {
    add_action( 'after_setup_theme', [ $this, 'initialize_defaults' ] );
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
    Log::debug('Default colors initialized');
  }

  /**
   * Creates CSS Vars from global colors.
   */
  public static function get_global_colors() {
    // return an array of solid colors
    $global_colors = get_option(Global_Colors::$OPTION_NAME);

    if ( ! is_array($global_colors) ) {
      return [];
    }

    $merged_global_colors = [
      'solids' => [
        ...$global_colors['userSolids'],
        ...$global_colors['coreSolids']
      ],
      'gradients' => [
        ...$global_colors['userGradients'],
        ...$global_colors['coreGradients']
      ]
    ];

    return $merged_global_colors;
  }

  public static function css_output() {
    $color_css = '';

    $merged_global_colors = self::get_global_colors();

    foreach ( $merged_global_colors as $category => $colors ) {
      foreach ( $colors as $color ) {

        if ( ! is_array($color) ) {
          continue;
        }

        // Reassigned colors.
        if ( isset($color['reassigned']) ) {

          $core_key = array_search($color['reassigned'], array_column($colors, 'slug'));
          $color_css .= ':root { --' . $color["slug"] . ': ' . $colors[$core_key]["value"] . '; }';

        } else {
          // Normal colors.
          $color_css .= ":root { --$color[slug]: $color[value]; }";
        }

      }
    }

    return (! empty($color_css)) ? $color_css : '';

  }

  public static function create_gradients($solid_colors) {
    $gradients = [
      [
        'slug' => 'gradient-1',
        'label' => __('Core Gradient #1', 'nectar-blocks'),
        'value' => 'linear-gradient(135deg,' . $solid_colors['accentLight']['value'] . ' 0%,' . $solid_colors['accentPrimary']['value'] . ' 100%)'
      ],
      [
        'slug' => 'gradient-2',
        'label' => __('Core Gradient #2', 'nectar-blocks'),
        'value' => 'linear-gradient(135deg,' . $solid_colors['accentLight']['value'] . ' 0%,' . $solid_colors['accentDark']['value'] . ' 100%)'
      ],
      [
        'slug' => 'gradient-3',
        'label' => __('Core Gradient #3', 'nectar-blocks'),
        'value' => 'linear-gradient(180deg,' . $solid_colors['light']['value'] . ' 0%,' . $solid_colors['accentPrimary']['value'] . ' 100%)'
      ],
      [
        'slug' => 'gradient-4',
        'label' => __('Core Gradient #4', 'nectar-blocks'),
        'value' => 'linear-gradient(135deg,' . $solid_colors['accentLight']['value'] . ' 0%,' . $solid_colors['dark']['value'] . ' 100%)'
      ],
      [
        'slug' => 'gradient-5',
        'label' => __('Core Gradient #5', 'nectar-blocks'),
        'value' => 'linear-gradient(225deg,' . $solid_colors['light']['value'] . ' 25%,' . self::hex_to_rgba($solid_colors['light']['value'], 0.2) . ' 100%)'
      ]
    ];

    return $gradients;
  }

  /**
   * Provides defaults for global colors.
   */
  private function defaults() {

    // Defaults to "Tranquility" palette
    $core_solids = [
      'light' => [
        'label' => __('Light', 'nectar-blocks'),
        'value' => '#fbfaf9',
        'slug' => 'light'
      ],
      'accentLight' => [
        'label' => __('Accent Light', 'nectar-blocks'),
        'value' => '#e8e6e0',
        'slug' => 'accentLight'
      ],
      'accentPrimary' => [
        'label' => __('Accent Primary', 'nectar-blocks'),
        'value' => '#f6c062',
        'slug' => 'accentPrimary'
      ],
      'accentDark' => [
        'label' => __('Accent Dark', 'nectar-blocks'),
        'value' => '#64793a',
        'slug' => 'accentDark'
      ],
      'dark' => [
        'label' => __('Dark', 'nectar-blocks'),
        'value' => '#000000',
        'slug' => 'dark'
      ]
    ];

    $coreGradients = $this->create_gradients( $core_solids );

    return [
      'coreSolids' => array_values($core_solids),
      'coreGradients' => $coreGradients,
      'userSolids' => [],
      'userGradients' => []
    ];
  }

  private static function hex_to_rgba($hex, $alpha = 1) {
    // Remove the hash at the start if it's there
    $hex = ltrim($hex, '#');

    // If the color code is in shorthand form (3 characters), convert to 6 characters
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) .
               str_repeat(substr($hex, 1, 1), 2) .
               str_repeat(substr($hex, 2, 1), 2);
    }

    // Convert hexadecimally
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Return the RGBA string
    return "rgba($r, $g, $b, $alpha)";
}

  // public static function get_theme_support_colors() {
  //   return get_theme_support( 'editor-color-palette' );
  // }

  // public static function get_theme_support_gradients() {
  //   return get_theme_support( 'editor-gradient-presets' );
  // }
}
