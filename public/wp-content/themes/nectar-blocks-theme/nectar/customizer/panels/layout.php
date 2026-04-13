<?php

/**
 * Customizer Layout section.
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Layout customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Layout {
  private static function get_title() {
    return [
      'id' => 'layout-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'Layout', 'nectar-blocks-theme' ),
        'priority' => 14,
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
      self::get_header_navigation(),
      self::get_logo_and_gen_styling(),
      self::get_layout_and_content_related(),
      self::get_secondary_header_bar(),
      self::get_header_nav_transparency(),
      self::get_header_nav_animation_effects(),
      self::get_header_dropdown_megamenu(),
      self::get_header_nav_search(),
      self::get_header_ocm(),
      self::get_header_mobile_menu(),
      self::get_header_color_scheme()
    ];
  }

  public static function get_header_navigation() {
    return [
      'panel_id' => 'header-navigation-panel',
      'settings' => [
        'title' => esc_html__( 'Header Navigation', 'nectar-blocks-theme' ),
        'priority' => 15
      ]
    ];
  }

  public static function get_logo_and_gen_styling() {
    $controls = [
      [
        'id' => 'use-logo',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Image Logo', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('If left unchecked, your site name will be used instead.', 'nectar-blocks-theme'),
        'desc' => ''
      ],

      [
        'id' => 'logo',
        'type' => 'media',
        'title' => esc_html__('Logo Upload', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload your logo here and enter the height of it below.', 'nectar-blocks-theme') . '<br/><br/>' . esc_html__('Note: there are additional logo upload fields in the transparent header effect tab.', 'nectar-blocks-theme'),
        'required' => [ [ 'use-logo', '=', '1' ] ],
        'desc' => ''
      ],

      // array(
      //   'id' => 'retina-logo',
      //   'type' => 'media',
      //   'title' => esc_html__('Retina Logo Upload', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('Upload at 2x the size of your standard logo. Supplying this will keep your logo crisp on screens with a higher pixel density.', 'nectar-blocks-theme'),
      //   'desc' => '' ,
      //   'required' => [ array( 'use-logo', '=', '1' ) ]
      // ),

      [
        'id' => 'logo-height',
        'type' => 'text',
        'title' => esc_html__('Logo Height', 'nectar-blocks-theme'),
        'desc' => '',
        'validate' => 'numeric',
        'required' => [ [ 'use-logo', '=', '1' ] ],
      ],

      [
        'id' => 'mobile-logo-height',
        'type' => 'text',
        'title' => esc_html__('Mobile Logo Height', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'use-logo', '=', '1' ] ],
        'validate' => 'numeric'
      ],

      [
        'id' => 'mobile-logo',
        'type' => 'media',
        'title' => esc_html__('Mobile Only Logo Upload', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('An optional field that allows you to display a separate logo that will be shown on mobile devices only.', 'nectar-blocks-theme'),
        'required' => [ [ 'use-logo', '=', '1' ] ],
        'desc' => ''
      ],

      [
        'id' => 'header-padding',
        'type' => 'slider',
        'title' => esc_html__('Header Padding', 'nectar-blocks-theme'),
        "default" => 25,
        "min" => 5,
        "step" => 1,
        "max" => 50,
        'desc' => '',
        'validate' => 'numeric'
      ],

      [
        'id' => 'header-menu-item-spacing',
        'type' => 'slider',
        'title' => esc_html__('Menu Item Gap', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 10,
        "min" => 8,
        "step" => 1,
        "max" => 50,
        // 'display_value' => 'label'
      ],
      [
        'id' => 'header-bg-opacity',
        'type' => 'slider',
        'title' => esc_html__('Background Opacity', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 100,
        "min" => 0,
        "step" => 1,
        "max" => 100,
        'tooltip' => esc_html__('If you are trying to have your header navigation completely see through before scrolling, setting this very low is not how to achieve it. The fully transparent style as shown on many of the demos is the option titled', 'nectar-blocks-theme') . '<b> ' . esc_html__('Use Transparent Header When Applicable', 'nectar-blocks-theme') . '</b> ' . esc_html__('which is available in the Header Navigation ~ Transparent Header Effect tab.', 'nectar-blocks-theme'),
        'display_value' => 'label'
      ],

      [
        'id' => 'header-box-shadow',
        'type' => 'select',
        'title' => esc_html__('Box Shadow', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'small' => esc_html__('Small', 'nectar-blocks-theme'),
          'large' => esc_html__('Large', 'nectar-blocks-theme'),
          'large-line' => esc_html__('Large With Bottom Line', 'nectar-blocks-theme'),
          'none' => esc_html__('None', 'nectar-blocks-theme')
        ],
        'default' => 'large'
      ],

      [
        'id' => 'header-blur-bg',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Blur Background', 'nectar-blocks-theme'),
        'desc' => '',
        'type' => 'nectar_blocks_switch_legacy',
        'default' => '0'
      ],

      [
        'id' => 'header-blur-bg-func',
        'type' => 'select',
        'title' => esc_html__('Blur Background Functionality', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'required' => [ [ 'header-blur-bg', '=', '1' ] ],
        'options' => [
          'active_non_transparent' => esc_html__('Active when "Transparent header effect" is not', 'nectar-blocks-theme'),
          'active_all' => esc_html__('Active in all header states', 'nectar-blocks-theme')
        ],
        'default' => 'active_non_transparent'
      ],

      // array(
      //   'id' => 'header-button-styling',
      //   'type' => 'select',
      //   'title' => esc_html__('Header Button Link Style', 'nectar-blocks-theme'),
      //   // TODO: Replace docs that are commented out below
      //   'subtitle' => esc_html__('This effects any header links which are set to use','nectar-blocks-theme'),
      //   // . ' <a target="_blank" href="http://themenectar.com/docs/salient/header-button-links/">' . esc_html('button styling.', 'nectar-blocks-theme') .'</a>'
      //   'desc' => '',
      //   'options' => array(
      //     'default' => esc_html__('Default', 'nectar-blocks-theme'),
      //     'hover_scale' => esc_html__('Scale on Hover', 'nectar-blocks-theme'),
      //     'shadow_hover_scale' => esc_html__('Button Shadow and Scale on Hover', 'nectar-blocks-theme')
      //   ),
      //   'default' => 'default'
      // ),

      [
        'id' => 'header-enable-border',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Border', 'nectar-blocks-theme'),
        'desc' => '',
        'type' => 'nectar_blocks_switch_legacy',
        'default' => '0'
      ],
      [
        'id' => 'header-border-color',
        'type' => 'color',
        'title' => esc_html__('Header Border Color', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'required' => [ [ 'header-enable-border', '=', '1' ] ],
        'desc' => '',
        'default' => '#000000'
      ],
      [
        'id' => 'header-remove-fixed',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Desktop Stickiness', 'nectar-blocks-theme'),
        'default' => '0'
      ]

    ];

    return [
      'section_id' => 'logo-and-general-styling-section',
      'settings' => [
        'title' => esc_html__( 'Logo & General Styling', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_layout_and_content_related() {
    $controls = [
      [
        'id' => 'header_format',
        'type' => 'image_select',
        'title' => esc_html__('Header Layout', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => ['title' => esc_html__('Default Layout', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/default-header.png'],
          'centered-menu' => ['title' => esc_html__('Centered Menu', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/centered-menu.png'],
          'centered-menu-under-logo' => ['title' => esc_html__('Centered Menu Alt', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/centered-menu-under-logo.png'],
          'centered-menu-bottom-bar' => ['title' => esc_html__('Menu Bottom Bar', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/centered-menu-bottom-bar.png'],
          'centered-logo-between-menu-alt' => ['title' => esc_html__('Centered Logo Menu Alt', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/centered-logo-menu-alt.png'],
          'menu-left-aligned' => ['title' => esc_html__('Menu Left Aligned', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/menu-left-aligned.png'],
          'left-header' => ['title' => esc_html__('Left Header', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/fixed-left.png', 'tooltip' => 'Does not allow 	&quot;Transparency&quot; options, and some options in	&quot;Animation Effects&quot;']
        ],
        'priority' => 1,
        'default' => 'default'
      ],

      [
        'id' => 'left-header-dropdown-func',
        'type' => 'select',
        'title' => esc_html__('Left Header Dropdown Functionality', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the functionality for how dropdowns will behave in the left header navigation.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'header_format', '=', 'left-header' ] ],
        'options' => [
          'default' => esc_html__('Dropdown Parent Link Toggles Submenu', 'nectar-blocks-theme'),
          'separate-dropdown-parent-link' => esc_html__('Separate Dropdown Parent Link From Dropdown Toggle', 'nectar-blocks-theme')
        ],
        'default' => 'default'
      ],

      [
        'id' => 'centered-menu-bottom-bar-separator',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Menu Bottom Bar Separator', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Add a line to separate the top/bottom of your header.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'header_format', '=', 'centered-menu-bottom-bar' ] ],
        'default' => '0'
      ],

      [
        'id' => 'centered-menu-bottom-bar-alignment',
        'type' => 'select',
        'required' => [ [ 'header_format', '=', 'centered-menu-bottom-bar' ] ],
        'title' => esc_html__('Menu Bottom Bar Alignment', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select how you would like your header content to align.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'center' => esc_html__('Center', 'nectar-blocks-theme'),
          'left' => esc_html__('Left', 'nectar-blocks-theme'),
          'left_t_center_b' => esc_html__('Left Top Center Bottom', 'nectar-blocks-theme'),
        ],
        'default' => 'center'
      ],

      [
        'id' => 'header-fullwidth',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Full Width Header', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'header-size', '=', 'default' ] ],
        'default' => '0'
      ],

      [
        'id' => 'header-fullwidth-padding',
        'type' => 'dimension',
        'title' => esc_html__('Full Width Left/Right Padding (px)', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'header-size', '=', 'default' ] ],
        'choices' => [
          'accept_unitless' => true
        ]
      ],

      [
        'id' => 'header-size',
        'type' => 'select',
        'required' => [
          [ 'header_format', '!=', 'left-header' ],
          [ 'header_format', '!=', 'centered-menu-bottom-bar' ],
          [ 'header_layout', '!=', 'header_with_secondary' ],
        ],
        'title' => esc_html__('Header Size', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Default (Expanded to screen edges)', 'nectar-blocks-theme'),
          'contained' => esc_html__('Contained', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],
      [
        'id' => 'header-border-radius',
        'type' => 'slider',
        'required' => [
            [ 'header_format', '!=', 'left-header' ],
            [ 'header_format', '!=', 'centered-menu-bottom-bar' ],
            [ 'header_layout', '!=', 'header_with_secondary' ],
            [ 'header-size', '=', 'contained' ],
        ],
        'title' => esc_html__('Header Roundness', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0,
        "min" => 0,
        "step" => 1,
        "max" => 50,
        'display_value' => 'label'
      ],
      [
        'id' => 'header-text-widget',
        'type' => 'editor',
        'title' => esc_html__('Text To Display In Header', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('Enter a small amount of text to display in your header navigation. e.g. a phone number, store address etc. The positioning of this content will be determined by the header layout that you are using.', 'nectar-blocks-theme'),
        'default' => '',
      ],

    ];

    return [
      'section_id' => 'header-nav-layout-section',
      'settings' => [
        'title' => esc_html__( 'Layout & Content Related', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_secondary_header_bar() {
    $controls = [
      [
        'id' => 'header_layout',
        'type' => 'select',
        'title' => esc_html__('Secondary Header Bar', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select if you would like an additional header bar above the main navigation.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'standard' => esc_html__('Standard Header', 'nectar-blocks-theme'),
          'header_with_secondary' => esc_html__('Header With Secondary Navigation Bar', 'nectar-blocks-theme'),
        ],
        'default' => 'standard'
      ],
      [
        'id' => 'secondary-header-text',
        'type' => 'text',
        'title' => esc_html__('Secondary Header Text', 'nectar-blocks-theme'),
        'required' => [ [ 'header_layout', '=', 'header_with_secondary' ] ],
        'subtitle' => esc_html__('Add the text that you would like to appear in the secondary header.', 'nectar-blocks-theme'),
        'desc' => ''
      ],
      [
        'id' => 'secondary-header-link',
        'type' => 'text',
        'title' => esc_html__('Secondary Header Link URL', 'nectar-blocks-theme'),
        'required' => [ [ 'header_layout', '=', 'header_with_secondary' ] ],
        'subtitle' => esc_html__('Please enter an optional URL for the secondary header text here.', 'nectar-blocks-theme'),
        'desc' => ''
      ],
      [
        'id' => 'secondary-header-mobile-display',
        'type' => 'select',
        'required' => [ [ 'header_layout', '=', 'header_with_secondary' ] ],
        'title' => esc_html__('Secondary Header Mobile Functionality', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select how you would like the secondary header bar to display on mobile devices.', 'nectar-blocks-theme') . '<br/><br/><i>' . esc_html__('The option to "Display Items Above Mobile Header" will be skipped when using the "Header Permanent Transparent" option in the Header Navigation > Transparent Effect tab.', 'nectar-blocks-theme') . '</i>',
        'desc' => '',
        'options' => [
          'default' => esc_html__('Add Items Into Mobile Navigation Menu', 'nectar-blocks-theme'),
          'display_full' => esc_html__('Display Items Above Mobile Header', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ]
    ];

    return [
      'section_id' => 'header-secondary-bar-section',
      'settings' => [
        'title' => esc_html__( 'Secondary Header Bar', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_nav_transparency() {

    // create a list of post types
    $transparent_header_auto_activation_locations = [];
    $post_types = get_transient('nectar_available_post_types');

    if ( $post_types && ! empty($post_types)) {
      foreach ( $post_types as $post_type ) {
        $transparent_header_auto_activation_locations['archive-' . $post_type->name] = esc_html__( 'Archive: ', 'nectar-blocks-theme' ) . $post_type->label;
      }
      foreach ( $post_types as $post_type ) {
        $transparent_header_auto_activation_locations['single-' . $post_type->name] = esc_html__( 'Single: ', 'nectar-blocks-theme' ) . $post_type->label;
      }
    }

    $controls = [
      [
        'id' => 'transparent-header',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Transparent Header', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Causes your header to be completely transparent before the user scrolls. This will be triggered automatically when using a post header, or you can manually trigger it per page.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'transparent-header-auto-activation-locations',
        'type' => 'multi_select',
        'title' => esc_html__( 'Automatically Enable On', 'nectar-blocks-theme' ),
        'options' => $transparent_header_auto_activation_locations,
        'required' => [ [ 'transparent-header', '=', '1' ] ]
      ],

      [
        'id' => 'header-starting-logo',
        'type' => 'media',
        'title' => esc_html__('Transparent Light Logo', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will be used when the header is transparent state is active.', 'nectar-blocks-theme'),
        'desc' => '' ,
        'required' => [ [ 'transparent-header', '=', '1' ] ],
      ],

      // array(
      //   'id' => 'header-starting-retina-logo',
      //   'type' => 'media',
      //   'title' => esc_html__('Transparent Light Retina Logo', 'nectar-blocks-theme'),
      //   'required' => [ array( 'transparent-header', '=', '1' ) ],
      //   'desc' => ''
      // ),

      [
        'id' => 'header-starting-mobile-only-logo',
        'type' => 'media',
        'title' => esc_html__('Transparent Light Mobile Logo', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('A separate header starting logo that will be shown on mobile devices only.', 'nectar-blocks-theme'),
        'required' => [ [ 'transparent-header', '=', '1' ] ],
        'desc' => ''
      ],

      [
        'id' => 'header-starting-logo-dark',
        'type' => 'media',
        'title' => esc_html__('Transparent Dark Logo', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will be used when the header transparent effect is active and the dark color is selected.', 'nectar-blocks-theme'),
        'desc' => '' ,
        'required' => [ [ 'transparent-header', '=', '1' ], [ 'header-permanent-transparent', '!=', '1' ]  ],
      ],
      // array(
      //   'id' => 'header-starting-retina-logo-dark',
      //   'type' => 'media',
      //   'title' => esc_html__('Transparent Dark Retina Logo', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'required' => [ array( 'transparent-header', '=', '1' ) ],
      // ),
      [
        'id' => 'header-starting-mobile-only-logo-dark',
        'type' => 'media',
        'title' => esc_html__('Transparent Dark Mobile Logo', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('A separate header starting dark logo that will be shown on mobile devices only.', 'nectar-blocks-theme'),
        'required' => [ [ 'transparent-header', '=', '1' ], [ 'header-permanent-transparent', '!=', '1' ]  ],
        'desc' => ''
      ],
      [
        'id' => 'header-starting-color',
        'type' => 'color',
        'title' => esc_html__('Transparent Light Text Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the color you desire for your header text before the user scrolls', 'nectar-blocks-theme'),
        'desc' => '',
        'transparent' => false,
        'required' => [ [ 'transparent-header', '=', '1' ], [ 'header-permanent-transparent', '!=', '1' ] ],
        'default' => '#ffffff'
      ],
      [
        'id' => 'header-transparent-dark-color',
        'type' => 'color',
        'title' => esc_html__('Transparent Dark Text Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the color you desire for your header text when the dark header is active.', 'nectar-blocks-theme'),
        'desc' => '',
        'transparent' => false,
        'required' => [ [ 'transparent-header', '=', '1' ], [ 'header-permanent-transparent', '!=', '1' ] ],
        'default' => '#000000'
      ],

      [
        'id' => 'header-starting-opacity',
        'type' => 'select',
        'required' => [ [ 'transparent-header', '=', '1' ] ],
        'title' => esc_html__('Header Starting Text Opacity', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          '0.75' => esc_html__('Default (Dimmed)', 'nectar-blocks-theme'),
          '1.0' => esc_html__('Full Opacity', 'nectar-blocks-theme'),
        ],
        'default' => '0.75'
      ],
      [
        'id' => 'header-permanent-transparent',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Header Permanent Transparent', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Turning this on will allow your header to remain transparent even after scrolling down. This option will override all colors. When using an image logo, it must have a transparent background (PNG or SVG).', 'nectar-blocks-theme'),
        'required' => [
          [ 'transparent-header', '=', '1' ],
          [ 'header_format', '!=', 'centered-menu-bottom-bar' ]
        ],
        'desc' => '',
        // 'tooltip' => esc_html__('Your navigation will alternate between dark and light color schemes based on the intersecting row. When editing your pages, every row in the page builder has a field for', 'nectar-blocks-theme') . ' <b>' . esc_html__('Text Color','nectar-blocks-theme') .'</b> ' . esc_html__('to set this.','nectar-blocks-theme'),
        'default' => '0'
      ],

      [
        'id' => 'transparent-header-shadow-helper',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Add Shadow Behind Transparent Header', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('Add a subtle shadow behind your transparent header to help with the visibility of your navigation items.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'transparent-header', '=', '1' ] ],
        'default' => '0'
      ],
    ];

    return [
      'section_id' => 'header-nav-transparency-section',
      'settings' => [
        'title' => esc_html__( 'Transparent Header Effect', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_nav_animation_effects() {
    $controls = [
      [
        'id' => 'header-hover-effect',
        'type' => 'select',
        'title' => esc_html__('Header Link Hover/Active Effect', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Color Change', 'nectar-blocks-theme'),
          'animated_underline' => esc_html__('Animated Underline', 'nectar-blocks-theme'),
          'button_bg' => esc_html__('Button Background', 'nectar-blocks-theme')
        ],
        'default' => 'animated_underline'
      ],

      [
        'id' => 'header-hover-effect-button-bg-size',
        'type' => 'select',
        'title' => esc_html__('Button Background Size', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Alters the button background size releative to link tett.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'small' => esc_html__('Small', 'nectar-blocks-theme'),
          'medium' => esc_html__('Medium', 'nectar-blocks-theme'),
          'large' => esc_html__('Large', 'nectar-blocks-theme'),
        ],
        'required' => [ [ 'header-hover-effect', '=', 'button_bg' ] ],
        'default' => 'medium'
      ],
      [
        'id' => 'header-hover-effect-button-bg-style',
        'type' => 'select',
        'title' => esc_html__('Button Background Animation', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Alters the button background entrance animation.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'fade-in' => esc_html__('Fade In', 'nectar-blocks-theme'),
          'grow-in' => esc_html__('Grow In', 'nectar-blocks-theme'),
        ],
        'required' => [ [ 'header-hover-effect', '=', 'button_bg' ] ],
        'default' => 'fade-in'
      ],

      [
        'id' => 'header-hide-until-needed',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Header Hide Until Needed', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Do you want the header to be hidden after scrolling until needed? i.e. the user scrolls back up towards the top', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [[ 'header_format', '!=', 'centered-menu-bottom-bar' ]],
        'default' => ''
      ],

      [
        'id' => 'header-resize-on-scroll',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Header Resize On Scroll', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Do you want the header to shrink a little when you scroll?', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [[ 'header_format', '!=', 'centered-menu-bottom-bar' ]],
        'default' => '1' ,
        'tooltip' => esc_html__('This will only be active when the', 'nectar-blocks-theme') . '<b> ' . esc_html__('Header Hide Until Needed', 'nectar-blocks-theme') . '</b> ' . esc_html__('effect is turned off', 'nectar-blocks-theme'),
      ],
      [
        'id' => 'header-resize-on-scroll-shrink-num',
        'type' => 'text',
        'title' => esc_html__('Header Logo Shrink Number (in px)', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [[ 'header-resize-on-scroll', '=', '1' ]],
        'validate' => 'numeric'
      ],

      [
        'id' => 'condense-header-on-scroll',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Condense Header On Scroll', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This option is specific to "Menu Bottom Bar" Header Format.', 'nectar-blocks-theme') . '<br /><br /> <strong>' . esc_html__('When Menu Is Center Aligned', 'nectar-blocks-theme') . '</strong><br />' . esc_html__('Adds the logo/header buttons into the bottom nav bar when scrolling. Uses the "Mobile Only Logo" if supplied.', 'nectar-blocks-theme') . '<br /><br /> <strong>' . esc_html__('When Menu Is left Aligned', 'nectar-blocks-theme') . '</strong><br />' . esc_html__('Keeps bottom bar sticky when scrolling.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [[ 'header_format', '=', 'centered-menu-bottom-bar' ]],
        'default' => ''
      ],
    ];

    return [
      'section_id' => 'header-nav-animation-effects',
      'settings' => [
        'title' => esc_html__( 'Animation Effects', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_dropdown_megamenu() {

    $controls = [
      [
        'id' => 'header-dropdown-opacity',
        'type' => 'slider',
        'title' => esc_html__('Dropdown Opacity', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 100,
        "min" => 1,
        "step" => 1,
        "max" => 100,
        'display_value' => 'label'
      ],

      [
        'id' => 'header-dropdown-hover-effect',
        'type' => 'select',
        'title' => esc_html__('Dropdown Link Hover/Active Effect', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Background Color Change', 'nectar-blocks-theme'),
          'animated_underline' => esc_html__('Animated Underline', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],

      [
        'id' => 'header-dropdown-arrows',
        'type' => 'select',
        'title' => esc_html__('Dropdown Arrows', 'nectar-blocks-theme'),
        'desc' => '',
       'required' => [ [ 'header_format', '!=', 'left-header' ] ],
        'options' => [
          'inherit' => esc_html__('Inherit', 'nectar-blocks-theme'),
          'show' => esc_html__('Show Arrow', 'nectar-blocks-theme'),
          'dont_show' => esc_html__('Don\'t Show Arrow', 'nectar-blocks-theme')
        ],
        'default' => 'inherit'
      ],

      [
        'id' => 'header-dropdown-display-desc',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Display Descriptions', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('This will display the "Description" field specified for dropdown menu items in Appearance > Menus.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'header-dropdown-overlay',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Add Background Overlay', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('Adds an overlay that appears when a dropdown is opened to improve focus.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'header-dropdown-position',
        'type' => 'select',
        'title' => esc_html__('Dropdown Positioning', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Bottom of Header Navigation Bar', 'nectar-blocks-theme'),
          'bottom-of-menu-item' => esc_html__('Bottom of Menu Item Label', 'nectar-blocks-theme')
        ],
        'default' => 'bottom-of-menu-item'
      ],
      [
        'id' => 'header-dropdown-animation',
        'type' => 'select',
        'title' => esc_html__('Dropdown Animation', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'fade-in-up' => esc_html__('Fade In Up', 'nectar-blocks-theme'),
          'fade-in' => esc_html__('Fade In', 'nectar-blocks-theme')
        ],
        'default' => 'fade-in-up'
      ],
      [
        'id' => 'header-dropdown-border-radius',
        'type' => 'slider',
        'title' => esc_html__('Dropdown Roundness', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 5,
        "min" => 0,
        "step" => 1,
        "max" => 20,
        'display_value' => 'label'
      ],
      [
        'id' => 'header-dropdown-box-shadow',
        'type' => 'select',
        'title' => esc_html__('Dropdown Box Shadow', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'small' => esc_html__('Small', 'nectar-blocks-theme'),
          'large' => esc_html__('Large', 'nectar-blocks-theme'),
          'none' => esc_html__('None', 'nectar-blocks-theme')
        ],
        'default' => 'large'
      ],
      // array(
      //   'id' => 'header-megamenu-width',
      //   'type' => 'select',
      //   'title' => esc_html__('Header Mega Menu Width', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('Please choose whether you would like your megamenu to be constrained to the same width of the header container or if you would prefer to be the full width of the page.', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'options' => array(
      //     'contained' => esc_html__('Contained To Header Item Width', 'nectar-blocks-theme'),
      //     'full-width' => esc_html__('Full Screen Width', 'nectar-blocks-theme')
      //   ),
      //   'default' => 'contained'
      // ),

      // array(
      //   'id' => 'header-megamenu-remove-transparent',
      //   'type' => 'nectar_blocks_switch_legacy',
      //   'title' => esc_html__('Megamenu Removes Transparent Header', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('This will cause your header navigation to temporarily disable the transparent effect when your megamenu is open', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'default' => '0'
      // ),
    ];

    return [
      'section_id' => 'header-nav-dropdown-megamenu',
      'settings' => [
        'title' => esc_html__( 'Dropdowns', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_nav_search() {

    $controls = [
      [
        'id' => 'header-disable-search',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Header search', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0',
      ],
      [
        'id' => 'header-disable-ajax-search',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Disable AJAX from search', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will turn off the autocomplete suggestions from appearing when typing in the search box.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'header-ajax-search-style',
        'type' => 'select',
        'title' => esc_html__('AJAX search styling', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Select how to display the search results as the user types.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [  ['header-disable-ajax-search', '!=', '1'] ],
        'options' => [
          'default' => esc_html__('Simple List', 'nectar-blocks-theme'),
          'extended' => esc_html__('Extended List', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],
      [
        'id' => 'header-search-limit',
        'type' => 'select',
        'title' => esc_html__('Limit Search To Post Type', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'any' => esc_html__('All', 'nectar-blocks-theme'),
          'product' => esc_html__('Products', 'nectar-blocks-theme'),
          'post' => esc_html__('Posts', 'nectar-blocks-theme'),
        ],
        'default' => 'all'
      ],
      [
        'id' => 'header-search-type',
        'type' => 'select',
        'title' => esc_html__('Header Search Typography', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'header_nav' => esc_html__('Inherit Navigation Typography', 'nectar-blocks-theme')
        ],
        'default' => 'default'
      ],
      [
        'id' => 'header-search-type-size',
        'type' => 'slider',
        'title' => esc_html__('Search Font Size', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 30,
        "min" => 20,
        "step" => 1,
        "max" => 60,
        'display_value' => 'text'
      ],
      [
        'id' => 'header-search-ph-text',
        'type' => 'text',
        'title' => esc_html__('Search Placeholder Text', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Define custom text to show to the user when opening the search, before they start typing.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => ''
      ],
    ];

    return [
      'section_id' => 'header-nav-search',
      'settings' => [
        'title' => esc_html__( 'Header Search', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_ocm() {

    $controls = [
      [
        'id' => 'header-slide-out-widget-area',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Off Canvas Menu', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will add an off canvas menu button on all viewports to your header navigation. When this is disabled, the off canvas menu will only be visible on mobile devices.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [  ['header-slide-out-widget-area-style', '!=', 'simple'] ],
        'default' => '1'
      ],

      [
        'id' => 'header-slide-out-widget-area-style',
        'type' => 'select',
        'title' => esc_html__('Off Canvas Menu Style', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('The "Slide Out From Right Hover Triggered" style will force the "Full Width Header" option regardless of your selection.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'slide-out-from-right' => esc_html__('Slide Out From Side', 'nectar-blocks-theme'),
          'slide-out-from-right-hover' => esc_html__('Slide Out From Side Hover Triggered', 'nectar-blocks-theme'),
          'fullscreen' => esc_html__('Fullscreen Cover Slide + Blur BG', 'nectar-blocks-theme'),
          'fullscreen-alt' => esc_html__('Fullscreen Cover Fade', 'nectar-blocks-theme'),
          //'fullscreen-inline-images' => esc_html__('Fullscreen Inline with Dynamic BG', 'nectar-blocks-theme'),
          'fullscreen-split' => esc_html__('Fullscreen Cover Split', 'nectar-blocks-theme'),
          'simple' => esc_html__('Simple Dropdown', 'nectar-blocks-theme')
        ],
        'default' => 'slide-out-from-right',
      ],

      [
        'id' => 'header-slide-out-widget-area-separate-mobile',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Off Canvas Menu Separate Mobile Menu', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will cause your off canvas to only display navigation menu items assigned to the "Off Canvas Navigation Menu" location when viewing on a mobile device.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [  ['header-slide-out-widget-area', '!=', '1'] ],
        'default' => '0'
      ],

      [
        'id' => 'header-slide-out-widget-area-slide-from-side-width',
        'type' => 'slider',
        'title' => esc_html__('Off Canvas Menu Desktop Width (%)', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 33,
        "min" => 25,
        "step" => 1,
        "max" => 100,
        'required' => [  ['header-slide-out-widget-area-style', '=', 'slide-out-from-right'] ],
        'display_value' => 'text'
      ],
      [
        'id' => 'header-slide-out-widget-area-offset',
        'type' => 'slider',
        'title' => esc_html__('Off Canvas Menu Offset', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0,
        "min" => 0,
        "step" => 1,
        "max" => 30,
        'display_value' => 'text'
      ],
      [
        'id' => 'header-slide-out-widget-area-roundness',
        'type' => 'slider',
        'title' => esc_html__('Off Canvas Menu Roundness', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0,
        "min" => 0,
        "step" => 1,
        "max" => 30,
        'display_value' => 'text'
      ],

      [
        'id' => 'header-slide-out-widget-area-icon-width',
        'type' => 'slider',
        'title' => esc_html__('Off Canvas Menu Icon Width', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 22,
        "min" => 16,
        "step" => 1,
        "max" => 40,
        'display_value' => 'text'
      ],

      [
       'id' => 'fullscreen-inline-images-default',
       'type' => 'media',
       'required' => [  ['header-slide-out-widget-area-style', '=', 'fullscreen-inline-images'] ],
       'title' => esc_html__('Default Off Canvas Background Image', 'nectar-blocks-theme'),
       'subtitle' => esc_html__('Choose the default image to be shown in your Off Canvas Menu. You can also supply a unique item for each menu item in', 'nectar-blocks-theme') . ' <a href="' . esc_url( admin_url('nav-menus.php') ) . '">' . esc_html__('Appearance > Menus.', 'nectar-blocks-theme') . '</a>',
       'desc' => ''
     ],

      [
        'id' => 'header-slide-out-widget-area-dropdown-behavior',
        'type' => 'select',
        'title' => esc_html__('Off Canvas Menu Dropdown Behavior', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the functionality for how dropdowns will behave in your off canvas menu.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Dropdown Parent Link Toggles Submenu', 'nectar-blocks-theme'),
          'separate-dropdown-parent-link' => esc_html__('Separate Dropdown Parent Link From Dropdown Toggle', 'nectar-blocks-theme')
        ],
        'default' => 'separate-dropdown-parent-link',
        'required' => [  ['header-slide-out-widget-area-style', '!=', 'fullscreen'], ['header-slide-out-widget-area-style', '!=', 'fullscreen-inline-images' ], ['header-slide-out-widget-area-style', '!=', 'fullscreen-alt' ], ['header-slide-out-widget-area-style', '!=', 'simple' ] ],
      ],

      // array(
      //   'id' => 'header-slide-out-from-right-simplify-mobile',
      //   'type' => 'nectar_blocks_switch_legacy',
      //   'title' => esc_html__('Simplify Animation/Style of OCM on Mobile', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('The "Slide Out From Side" animation can be difficult for some mobile devices to render correctly. You can use this to offer a simplified version when accessed from a mobile device.', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'required' => array(  array('header-slide-out-widget-area-style', '=', 'slide-out-from-right') ),
      //   'default' => '0'
      // ),

      [
        'id' => 'header-menu-label',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Off Canvas Menu Add Menu Label', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
       'id' => 'ocm_btn_position',
       'type' => 'select',
       'title' => esc_html__('Off Canvas Menu Button Position', 'nectar-blocks-theme'),
       'desc' => '',
       'required' => [  ['header_format', '!=', 'centered-logo-between-menu'], ['header_format', '!=', 'centered-menu-under-logo'], ['header-slide-out-widget-area-style', '!=', 'simple'] ],
       'options' => [
         'default' => esc_html__('Default', 'nectar-blocks-theme'),
         'left' => esc_html__('Left', 'nectar-blocks-theme'),
       ],
       'default' => 'default',
     ],

      // array(
      //   'id' => 'header-slide-out-widget-area-social',
      //   'type' => 'nectar_blocks_switch_legacy',
      //   'title' => esc_html__('Off Canvas Menu Add Social', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('This will add the social links you have links set for in the "Social Media" tab to your off canvas menu.', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'default' => '0'
      // ),
      [
        'id' => 'header-slide-out-widget-area-bottom-text',
        'type' => 'text',
        'title' => esc_html__('Off Canvas Menu Bottom Text', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will add some text fixed at the bottom of your off canvas menu - useful for copyright or quick contact info etc.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => ''
      ],
      [
        'id' => 'header-slide-out-widget-area-overlay-opacity',
        'type' => 'select',
        'title' => esc_html__('Off Canvas Menu Overlay Strength', 'nectar-blocks-theme'),
        'tooltip' => esc_html__('Not all off canvas menu styles utilize this option.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'solid' => esc_html__('Solid', 'nectar-blocks-theme'),
          'dark' => esc_html__('Dark', 'nectar-blocks-theme'),
          'medium' => esc_html__('Medium', 'nectar-blocks-theme'),
          'light' => esc_html__('Light', 'nectar-blocks-theme'),
          'none' => esc_html__('None', 'nectar-blocks-theme')
        ],
        'default' => 'dark',
        'required' => [  ['header-slide-out-widget-area-style', '!=', 'simple'] ]
      ],
      [
        'id' => 'header-slide-out-widget-area-top-nav-in-mobile',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Off Canvas Menu Mobile Nav Menu items', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will cause your off canvas menu to inherit any navigation items assigned in your "Top Navigation" menu location when viewing on a mobile device.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [  ['header-slide-out-widget-area-style', '!=', 'simple'], ['header-slide-out-widget-area', '!=', '0'] ],
        'default' => '0'
      ],
      [
        'id' => 'header-slide-out-widget-area-icons-display',
        'type' => 'select',
        'title' => esc_html__('Off Canvas Menu Item Icons', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will control what type of icons (if any) to display in your off canvas menu. Icons are defined by you on an individual menu item basis in Appearance > Menus.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'none' => esc_html__('Display No Icons', 'nectar-blocks-theme'),
          'font_icons_only' => esc_html__('Display Font/Emoji Icons Only', 'nectar-blocks-theme'),
          'image_icons_only' => esc_html__('Display Image Icons Only', 'nectar-blocks-theme'),
          'all' => esc_html__('Display All Icons', 'nectar-blocks-theme'),
        ],
        'default' => 'none'
      ],
      // array(
      //   'id' => 'header-slide-out-widget-area-image-display',
      //   'type' => 'select',
      //   'title' => esc_html__('Off Canvas Menu Item Images', 'nectar-blocks-theme'),
      //   'subtitle' => esc_html__('This will control how to display menu items which have an image set. Removing images will simply your menu items to their default mobile state. Menu Item images are defined by you on an individual menu item basis in Appearance > Menus.', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'options' => array(
      //     'remove_images' => esc_html__('Remove Images', 'nectar-blocks-theme'),
      //     'default' => esc_html__('Display Images', 'nectar-blocks-theme'),
      //   ),
      //   'default' => 'remove_images'
      // ),
      [
        'id' => 'header-slide-out-widget-area-icon-style',
        'type' => 'select',
        'title' => esc_html__('Off Canvas Icon Style', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'circular' => esc_html__('Circular', 'nectar-blocks-theme')
        ],
        'default' => 'default',
        'required' => [  ['header-menu-label', '!=', '1'] ]
      ],
      [
        'id' => 'header-slide-out-widget-area-menu-btn-bg-color',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Menu Button BG Color', 'nectar-blocks-theme'),
        'desc' => '',
        'transparent' => false,
        'subtitle' => esc_html__('Optionally define a background color for your off canvas navigation button within the header.', 'nectar-blocks-theme'),
        'default' => ''
      ],
      [
        'id' => 'header-slide-out-widget-area-menu-btn-color',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Menu Button Color', 'nectar-blocks-theme'),
        'desc' => '',
        'transparent' => false,
        'default' => ''
      ],

      [
       'id' => 'header-slide-out-widget-area-custom-font-size',
       'type' => 'text',
       'title' => esc_html__('Off Canvas Menu Custom Font Size (Desktop)', 'nectar-blocks-theme'),
       'subtitle' => esc_html__('Optionally specify a custom font size to use for your off canvas navigation menu items when viewed on desktop displays. All unit types are accepted.', 'nectar-blocks-theme'),
       'desc' => '',
       'default' => ''
     ],
     [
       'id' => 'header-slide-out-widget-area-custom-font-size-mobile',
       'type' => 'text',
       'title' => esc_html__('Off Canvas Menu Custom Font Size (Mobile)', 'nectar-blocks-theme'),
       'subtitle' => esc_html__('Optionally specify a custom font size to use for your off canvas navigation menu items when viewed on mobile displays. All unit types are accepted.', 'nectar-blocks-theme'),
       'desc' => '',
       'default' => ''
     ],

    ];

    return [
      'section_id' => 'header-ocm',
      'settings' => [
        'title' => esc_html__( 'Header Off Canvas Menu', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_mobile_menu() {

    $controls = [
      [
        'id' => 'mobile-menu-layout',
        'type' => 'image_select',
        'title' => esc_html__('Mobile Header Layout', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => ['title' => esc_html__('Default Layout', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/mobile-default.jpg'],
          'centered-menu' => ['title' => esc_html__('Centered Menu', 'nectar-blocks-theme'), 'img' => NECTAR_FRAMEWORK_DIRECTORY . 'assets/img/mobile-centered.jpg'],
        ],
        'priority' => 1,
        'default' => 'default'
      ],
      [
        'id' => 'header-mobile-fixed',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Header Sticky On Mobile', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],

      [
        'id' => 'header-menu-mobile-breakpoint',
        'type' => 'slider',
        'title' => esc_html__('Header Mobile Breakpoint', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Define at what window size (in px) the header navigation menu will collapse into the mobile menu style - larger values are useful when you have a navigation with many items which wouldn\'t fit on one line when viewed on small desktops/laptops.', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 1025,
        "min" => 1025,
        "step" => 1,
        "max" => 1600,
        'display_value' => 'text'
      ],

      [
        'id' => 'header-fullwidth-mobile-padding',
        'required' => [ [ 'header-fullwidth', '=', '1' ] ],
        'type' => 'slider',
        'title' => esc_html__('Full Width Left/Right Padding', 'nectar-blocks-theme'),
        "default" => 18,
        "min" => 5,
        "step" => 1,
        "max" => 40,
        'desc' => '',
        'validate' => 'numeric'
      ],

      [
        'id' => 'header-mobile-padding',
        'type' => 'slider',
        'title' => esc_html__('Header Top/Bottom Padding', 'nectar-blocks-theme'),
        "default" => 12,
        "min" => 5,
        "step" => 1,
        "max" => 30,
        'desc' => '',
        'validate' => 'numeric'
      ],

    ];

    return [
      'section_id' => 'header-mobile-menu',
      'settings' => [
        'title' => esc_html__( 'Mobile Menu', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_header_color_scheme() {
    $controls = [
      [
        'id' => 'header-color',
        'type' => 'select',
        'title' => esc_html__('Header Color Scheme', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select your header color scheme here. Color pickers below will only be used when using "Custom" for the color scheme.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'light' => esc_html__('Light', 'nectar-blocks-theme'),
          'dark' => esc_html__('Dark', 'nectar-blocks-theme'),
          'custom' => esc_html__('Custom', 'nectar-blocks-theme')
        ],
        'default' => 'light'
      ],

      [
        'id' => 'header-background-color',
        'type' => 'color',
        'title' => esc_html__('Header Background', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'transparent' => false,
        'default' => '#ffffff',
        'required' => [ [ 'header-color', '=', 'custom' ] ],
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-background-color'),
      ],
      [
        'id' => 'header-font-color',
        'type' => 'color',
        'title' => esc_html__('Header Font', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'desc' => '',
        'default' => '#888888',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-font-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-font-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'transparent' => false,
        'desc' => '',
        'class' => 'hover-state',
        'default' => '#3452ff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-font-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-font-button-bg',
        'type' => 'color',
        'title' => esc_html__('Header Button Effect', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'class' => 'no-border',
        'desc' => esc_html__('BG Default State', 'nectar-blocks-theme'),
        'default' => '#eeeeee',
        'required' => [ [ 'header-hover-effect', '=', 'button_bg' ] ],
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-font-button-bg')
      ],
      [
        'id' => 'header-font-button-bg-active',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('BG Active State', 'nectar-blocks-theme'),
        'transparent' => false,
        'class' => 'hover-state no-border-middle',
        'default' => '#000000',
        'required' => [[ 'header-hover-effect', '=', 'button_bg' ]],
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-font-button-bg-active')
      ],
      [
        'id' => 'header-font-button-text-active',
        'type' => 'color',
        'title' => '',
        'transparent' => false,
        'class' => 'hover-state',
        'subtitle' => esc_html__('Text Active State', 'nectar-blocks-theme'),
        'default' => '#ffffff',
        'required' => [[ 'header-hover-effect', '=', 'button_bg' ]],
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-font-button-text-active')
      ],

      [
        'id' => 'header-icon-color',
        'type' => 'color',
        'title' => esc_html__('Header Menu Item Icons', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'desc' => '',
        'default' => '#888888',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-icon-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],

      // array(
      //   'id' => 'header-color-scheme-divider-1',
      //   'type' => 'nectar_divider',
      //   'title' => esc_html__('Divider', 'nectar-blocks-theme'),
      //   'subtitle' => '',
      //   'desc' => '',
      //   'required' => [ array( 'header-color', '=', 'custom' ) ],
      // ),

      [
        'id' => 'secondary-header-background-color',
        'type' => 'color',
        'title' => esc_html__('Secondary Header Background', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'transparent' => false,
        'default' => '#F8F8F8',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('secondary-header-background-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'secondary-header-font-color',
        'type' => 'color',
        'title' => esc_html__('Secondary Header Font', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'desc' => '',
        'default' => '#666666',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('secondary-header-font-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'secondary-header-font-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'transparent' => false,
        'desc' => '',
        'class' => 'hover-state',
        'default' => '#222222',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('secondary-header-font-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],

      [
        'id' => 'header-dropdown-background-color',
        'type' => 'color',
        'title' => esc_html__('Dropdown Background', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#1F1F1F',
        'transport' => 'refresh',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-background-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-background-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'transparent' => false,
        'desc' => '',
        'class' => 'hover-state',
        'default' => '#313233',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-background-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-font-color',
        'type' => 'color',
        'title' => esc_html__('Dropdown Font', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'desc' => '',
        'default' => '#CCCCCC',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-font-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-font-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'desc' => '',
        'class' => 'hover-state',
        'transparent' => false,
        'default' => '#3452ff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-font-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-icon-color',
        'type' => 'color',
        'title' => esc_html__('Dropdown Menu Item Icons', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'desc' => '',
        'default' => '#3452ff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-icon-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-desc-font-color',
        'type' => 'color',
        'title' => esc_html__('Dropdown Description Font', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'desc' => '',
        'class' => 'no-border',
        'transparent' => false,
        'default' => '#CCCCCC',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-desc-font-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-desc-font-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'class' => 'hover-state',
        'desc' => '',
        'transparent' => false,
        'default' => '#ffffff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-desc-font-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-heading-font-color',
        'type' => 'color',
        'title' => esc_html__('Mega Menu Heading Font', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'desc' => '',
        'default' => '#ffffff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-heading-font-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-dropdown-heading-font-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'transparent' => false,
        'class' => 'hover-state',
        'desc' => '',
        'default' => '#ffffff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-dropdown-heading-font-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-separator-color',
        'type' => 'color',
        'title' => esc_html__('Header Separators', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'desc' => '',
        'default' => '#eeeeee',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-separator-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],

      [
        'id' => 'header-slide-out-widget-area-background-color',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation BG', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'class' => 'no-border',
        'transparent' => false,
        'default' => '#3452ff',
        'transport' => 'refresh',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-background-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-slide-out-widget-area-background-color-2',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation BG 2', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Used for gradient', 'nectar-blocks-theme'),
        'desc' => '',
        'transparent' => false,
        'default' => '',
        'transport' => 'refresh',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-background-color-2'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-slide-out-widget-area-header-color',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Headers', 'nectar-blocks-theme'),
        'subtitle' => '',
        'transparent' => false,
        'desc' => '',
        'default' => '#ffffff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-header-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-slide-out-widget-area-color',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Text', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default State', 'nectar-blocks-theme'),
        'class' => 'no-border',
        'transparent' => false,
        'desc' => '',
        'default' => '#eefbfa',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-slide-out-widget-area-hover-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Hover State', 'nectar-blocks-theme'),
        'class' => 'hover-state',
        'transparent' => false,
        'desc' => '',
        'default' => '#ffffff',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-hover-color'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],

      [
        'id' => 'header-slide-out-widget-area-close-button-bg',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Close BG', 'nectar-blocks-theme'),
        'subtitle' => '',
        'choices' => [
          'alpha' => true,
        ],
        'default' => 'rgba(0,0,0,0.05)',
        'transport' => 'refresh',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-close-button-bg'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],
      [
        'id' => 'header-slide-out-widget-area-close-button',
        'type' => 'color',
        'title' => esc_html__('Off Canvas Navigation Close', 'nectar-blocks-theme'),
        'subtitle' => '',
        'choices' => [
          'alpha' => true,
        ],
        'default' => '#ffffff',
        'transport' => 'refresh',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('header-slide-out-widget-area-close-button'),
        'required' => [ [ 'header-color', '=', 'custom' ] ],
      ],

    ];

    return [
      'section_id' => 'header-nav-color-scheme-section',
      'settings' => [
        'title' => esc_html__( 'Header Color Scheme', 'nectar-blocks-theme' ),
        'panel' => 'header-navigation-panel',
        'priority' => 1
      ],
      'controls' => $controls
    ];

  }
}

