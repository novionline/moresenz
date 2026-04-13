<?php

// Exit if accessed directly.

use function PHPSTORM_META\map;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Post Types - Global Sections customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Post_Types_WooCommerce {
  public static function get_kirki_partials() {

    // global $woocommerce;
    if ( class_exists( 'woocommerce' ) ) {

      return [
        [
          'panel_id' => 'woocommerce-panel',
          'settings' => [
            'title' => esc_html__( 'Woocommerce', 'nectar-blocks-theme' ),
            'priority' => 26
          ]
        ],
        self::get_woocommerce_general(),
        self::get_woocommerce_single_product(),
        self::get_woocommerce_archive_header()
      ];

    }

    return [];
  }

  public static function get_woocommerce_general() {
    $controls = [
      [
        'id' => 'enable-cart',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('WooCommerce Cart In Nav', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('This will add a cart item to your main navigation.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1',
        'priority' => 1,
      ],
      [
        'id' => 'ajax-cart-style',
        'type' => 'select',
        'title' => esc_html__('Cart In Nav Style', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the style you would like for your AJAX cart.', 'nectar-blocks-theme') . '<br/><br/><strong>' . esc_html__('Note:', 'nectar-blocks-theme') . '</strong> ' . esc_html__('Because WooCommerce caches the cart widget markup, when changing this option you will need to add a product to the cart to see the full change. Alternatively, you can also use the "Clear sessions" tool within WooCommerce > Status > Tools for this.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "dropdown" => esc_html__("Hover Dropdown", 'nectar-blocks-theme'),
          "slide_in_click" => esc_html__("Click Extended Functionality", 'nectar-blocks-theme')
        ],
        'default' => 'dropdown',
        'required' => [ [ 'enable-cart', '=', '1' ] ],
        'priority' => 1,
      ],
      [
        'id' => 'ajax-add-to-cart',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('AJAX Product Template Add to Cart', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Enabling this will allow products to be added to the cart without causing a page refresh on the single product template and in the quickview modal.', 'nectar-blocks-theme'),
        'default' => '0',
        'required' => [ [ 'enable-cart', '=', '1' ] ],
        'priority' => 1,
      ],

      [
        'id' => 'header-account-button',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Account Button In Nav', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'main_shop_layout',
        'type' => 'select',
        'title' => esc_html__('Main Shop Layout', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('Please select layout you would like to use on your main shop page.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "no-sidebar" => esc_html__("No Sidebar", 'nectar-blocks-theme'),
          "right-sidebar" => esc_html__("Right Sidebar", 'nectar-blocks-theme'),
          "left-sidebar" => esc_html__("Left Sidebar", 'nectar-blocks-theme')
        ],
        'default' => 'no-sidebar',
        'priority' => 1,
      ],

      [
        'id' => 'main_shop_layout_full_width',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Full Width Layout', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'product_filter_area',
        'type' => 'nectar_blocks_switch_legacy',
        'required' => [
          ['main_shop_layout', '!=', 'no-sidebar'],
          ['main_shop_layout', '!=', 'fullwidth' ]
        ],
        'title' => esc_html__('Add Filter Sidebar Toggle', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Enabling this will allow your sidebar widget area to be toggled from a button above your products on all product archives.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'product_show_filters',
        'type' => 'nectar_blocks_switch_legacy',
        'required' => [ ['product_filter_area', '=', '1'] ],
        'title' => esc_html__('Display Active Filters Next To Toggle', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Displays currently active filters next to the filter toggle button. Not compatible with third party plugins which update WooCommerce filters without reloading the page.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'product_filter_area_starting_state',
        'type' => 'select',
        'required' => [ ['product_filter_area', '=', '1'] ],
        'title' => esc_html__('Filter Sidebar Toggle Starting State', 'nectar-blocks-theme'),
        'options' => [
          'open' => esc_html__('Open', 'nectar-blocks-theme'),
          'closed' => esc_html__('Closed', 'nectar-blocks-theme'),
        ],
        'default' => 'open'
      ],

      [
        'id' => 'main_shop_layout_sticky_sidebar',
        'type' => 'nectar_blocks_switch_legacy',
        'required' => [
          ['main_shop_layout', '!=', 'no-sidebar'],
          ['main_shop_layout', '!=', 'fullwidth' ]
        ],
        'title' => esc_html__('Enable Sticky Sidebar', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Would you like to have your sidebar follow down as your scroll in a sticky manner?', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      // [
      //   'id' => 'product_archive_layout',
      //   'type' => 'select',
      //   'title' => esc_html__('Archive Product Layout Type', 'nectar-blocks-theme'),
      //   'desc' => '',
      //   'options' => [
      //     'default' => esc_html__('Default', 'nectar-blocks-theme'),
      //     'bordered' => esc_html__('Bordered', 'nectar-blocks-theme')
      //   ],
      //   'default' => 'default'
      // ],

      [
        'id' => 'product_style',
        'type' => 'radio',
        'title' => esc_html__('Product Style', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('Please select the style you would like your products to display in (single product page styling will also vary slightly with each)', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'classic' => esc_html__('Classic', 'nectar-blocks-theme'),
          'material' => esc_html__('Material Design', 'nectar-blocks-theme'),
          'minimal' => esc_html__('Minimal Design', 'nectar-blocks-theme')
        ],
        'default' => 'minimal'
      ],

      [
        'id' => 'product_minimal_hover_layout',
        'type' => 'select',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'title' => esc_html__('Minimal Product Hover Layout', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Price Hidden, Minimal Buttons', 'nectar-blocks-theme'),
          'price_visible_flex_buttons' => esc_html__('Price Visible, Flex Buttons', 'nectar-blocks-theme'),
        ],
        'default' => 'price_visible_flex_buttons'
      ],

      [
        'id' => 'product_minimal_hover_effect',
        'type' => 'select',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'title' => esc_html__('Minimal Product Hover Effect', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Background Grow W/ Shadow', 'nectar-blocks-theme'),
          'image_zoom' => esc_html__('Image Zoom', 'nectar-blocks-theme'),
        ],
        'default' => 'image_zoom'
      ],

      [
        'id' => 'product_minimal_text_alignment',
        'type' => 'select',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'title' => esc_html__('Minimal Product Text Alignment', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          'left' => esc_html__('Left', 'nectar-blocks-theme'),
          'center' => esc_html__('Center', 'nectar-blocks-theme'),
          'right' => esc_html__('Right', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],

      [
        'id' => 'product_minimal_button_color',
        'type' => 'color',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'transparent' => false,
        'title' => esc_html__('Button BG Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#ffffff'
      ],
      [
        'id' => 'product_minimal_button_color_hover',
        'type' => 'color',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'transparent' => false,
        'title' => esc_html__('Button BG Color Hover', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#ffffff'
      ],
      [
        'id' => 'product_minimal_button_text_color',
        'type' => 'color',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'transparent' => false,
        'title' => esc_html__('Button Text Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#000000'
      ],

      [
        'id' => 'product_minimal_border_color',
        'type' => 'color',
        'required' => [ ['product_style', '=', 'minimal'] ],
        'transparent' => false,
        'title' => esc_html__('Button Border Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => ''
      ],

      [
        'id' => 'product_gap',
        'type' => 'slider',
        'title' => esc_html__('Product Gap', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 20,
        "min" => 0,
        "step" => 1,
        "max" => 50,
        'display_value' => 'text'
      ],

      [
        'id' => 'product_border_radius',
        'type' => 'select',
        'title' => esc_html__('Product Border Radius', 'nectar-blocks-theme'),
        'options' => [
          'default' => esc_html__('Default', 'nectar-blocks-theme'),
          '0px' => esc_html__('0px', 'nectar-blocks-theme'),
          '1px' => esc_html__('1px', 'nectar-blocks-theme'),
          '2px' => esc_html__('2px', 'nectar-blocks-theme'),
          '3px' => esc_html__('3px', 'nectar-blocks-theme'),
          '4px' => esc_html__('4px', 'nectar-blocks-theme'),
          '5px' => esc_html__('5px', 'nectar-blocks-theme'),
          '6px' => esc_html__('6px', 'nectar-blocks-theme'),
          '7px' => esc_html__('7px', 'nectar-blocks-theme'),
          '8px' => esc_html__('8px', 'nectar-blocks-theme'),
          '9px' => esc_html__('9px', 'nectar-blocks-theme'),
          '10px' => esc_html__('10px', 'nectar-blocks-theme'),
          '15px' => esc_html__('15px', 'nectar-blocks-theme'),
          '20px' => esc_html__('20px', 'nectar-blocks-theme'),
        ],
        'default' => 'default'
      ],
      [
        'id' => 'product_quick_view',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Enable WooCommerce Product Quick View', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will add a "quick view" button to your products which will load key single product page info without having to navigate to the page itself.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],

      [
        'id' => 'product_hover_alt_image',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Show first gallery image on Product hover', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => esc_html__("Using this will cause your products to show the first gallery image (if supplied) on hover", 'nectar-blocks-theme'),
        'default' => '1'
      ],

      [
        'id' => 'product_mobile_deactivate_hover',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Disable Product Hover Effect On Mobile', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'woocommerce_button_typography',
        'type' => 'select',
        'title' => esc_html__('WooCommerce Button Typography', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default (Body)",
          "label" => esc_html__('Label', 'nectar-blocks-theme')
        ],
        'default' => 'default',
      ],

      [
        'id' => 'qty_button_style',
        'type' => 'select',
        'title' => esc_html__('Quantity Button Style', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select style for your quantity buttons.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Rounded Circles With Shadow', 'nectar-blocks-theme'),
          'grouped_together' => esc_html__('All Grouped Together', 'nectar-blocks-theme'),
        ],
        'default' => 'grouped_together'
      ],

      [
        'id' => 'product_desktop_cols',
        'type' => 'select',
        'title' => esc_html__('Archive Page Columns (Desktop)', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('The column number to be displayed on product archive pages when viewed on a desktop monitor ( > 1300px)', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "6" => "6",
          "5" => "5",
          "4" => "4",
          "3" => "3",
          "2" => "2"
        ],
        'default' => 'default',
      ],

      [
        'id' => 'product_desktop_small_cols',
        'type' => 'select',
        'title' => esc_html__('Archive Page Columns (Small Desktop)', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('The column number to be displayed on product archive pages when viewed on a small desktop monitor (1000px - 1300px)', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "6" => "6",
          "5" => "5",
          "4" => "4",
          "3" => "3",
          "2" => "2"
        ],
        'default' => 'default',
      ],

      [
        'id' => 'product_tablet_cols',
        'type' => 'select',
        'title' => esc_html__('Archive Page Columns (Tablet)', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('The column number to be displayed on product archive pages when viewed on a tablet (690px - 1024px)', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "4" => "4",
          "3" => "3",
          "2" => "2"
        ],
        'default' => 'default',
      ],

      [
        'id' => 'product_phone_cols',
        'type' => 'select',
        'title' => esc_html__('Archive Page Columns (Phone)', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('The column number to be displayed on product archive pages when viewed on a phone ( < 690px)', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "4" => "4",
          "3" => "3",
          "2" => "2",
          "1" => "1"
        ],
        'default' => 'default',
      ],

      [
        'id' => 'product_bg_color',
        'type' => 'color',
        'transparent' => false,
        'title' => esc_html__('Material Design Product Item BG Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Set this to match the BG color of your product images.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'product_style', '=', 'material' ] ],
        'default' => '#ffffff'
      ],

      [
        'id' => 'product_minimal_bg_color',
        'type' => 'color',
        'transparent' => false,
        'title' => esc_html__('Minimal Design Product Item BG Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Set this to match the BG color of your product images.', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'product_style', '=', 'minimal' ] ],
        'default' => ''
      ],

      [
        'id' => 'product_archive_bg_color',
        'type' => 'color',
        'transparent' => false,
        'title' => esc_html__('Product Archive Page BG Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Allows to you set the BG color for all product archive pages', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('product_archive_bg_color'),
      ],

      [
        'id' => 'woo-products-per-page',
        'type' => 'text',
        'title' => esc_html__('Products Per Page', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please enter your desired your products per page (default is 12)', 'nectar-blocks-theme'),
        'desc' => '',
        'validate' => 'numeric'
      ],

      // array(
      //   'id'    => 'woo_social',
      //   'type'  => 'info',
      //   'style' => 'success',
      //   'title' => esc_html__('WooCommerce Social Sharing Options', 'nectar-blocks-theme'),
      //   'icon'  => 'el-icon-info-sign',
      //   'desc'  => esc_html__( 'As of NectarBlocks v10.1 the WooCommerce social settings have been moved into WordPress customizer (Appearance > Customize). Ensure that you have the "NectarBlocks Social" plugin installed and activated to use them.', 'nectar-blocks-theme')
      // ),
    ];

    return [
      'section_id' => 'woocommerce-general-section',
      'settings' => [
        'panel' => 'woocommerce-panel',
        'title' => esc_html__( 'General', 'nectar-blocks-theme' ),
        'priority' => 1
      ],
      'controls' => $controls
    ];
  }

  public static function get_woocommerce_single_product() {
    $controls = [

      [
        'id' => 'single_product_layout',
        'type' => 'select',
        'title' => esc_html__('Single Product Layout', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('Please select layout you would like to use on your single product page.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "no-sidebar" => "No Sidebar",
          "right-sidebar" => "Right Sidebar",
          "left-sidebar" => "Left Sidebar",
        ],
        'default' => 'no-sidebar',
      ],

      [
        'id' => 'single_product_gallery_type',
        'type' => 'radio',
        'title' => esc_html__('Single Product Gallery Type', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('Please select what gallery type you would like on your single product page', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'default' => esc_html__('Default WooCommerce Gallery', 'nectar-blocks-theme'),
          'ios_slider' => esc_html__('Bottom Thumbnails Slider', 'nectar-blocks-theme'),
          'left_thumb_sticky' => esc_html__('Left Thumbnails Slider', 'nectar-blocks-theme'),
          'two_column_images' => esc_html__('2 Column Images + Sticky Product Info', 'nectar-blocks-theme'),
        ],
        'default' => 'left_thumb_sticky'
      ],
      [
        'id' => 'single_product_gallery_custom_width',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Custom Product Gallery Width', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'single_product_gallery_width',
        'type' => 'slider',
        'required' => [ [ 'single_product_gallery_custom_width', '=', '1' ] ],
        'title' => esc_html__('Product Gallery Width Percent', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Specify a custom width for your product gallery to be used on desktop views.', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 65,
        "min" => 30,
        "step" => 1,
        "max" => 75,
        'display_value' => 'text'
      ],
      [
        'id' => 'single_product_use_custom_image_aspect_ratio',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Product Gallery Locked To Aspect Ratio', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => '',
        'default' => '1'
      ],

      [
        'id' => 'single_product_custom_image_aspect_ratio',
        'type' => 'select',
        'required' => [ [ 'single_product_use_custom_image_aspect_ratio', '=', '1' ] ],
        'title' => esc_html__('Aspect Ratio', 'nectar-blocks-theme'),
        'subtitle' => '',
        'desc' => '',
        'options' => [
          "16-9" => "16:9",
          "4-3" => "4:3",
          "3-2" => "3:2",
          "1-1" => "1:1",
          "2-3" => "2:3",
          "3-4" => "3:4",
          "9-16" => "9:16"
        ],
        'default' => '1-1',
      ],

      [
        'id' => 'product_tab_position',
        'type' => 'radio',
        'title' => esc_html__('Product Tab Position', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('Please select what area you would like your tabs to display in on the single product page', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          'in_sidebar' => esc_html__('In Side Area', 'nectar-blocks-theme'),
          'fullwidth' => esc_html__('Fullwidth Under Images', 'nectar-blocks-theme'),
          'fullwidth_stacked' => esc_html__('Fullwidth Under Images Stacked (No Tabs)', 'nectar-blocks-theme')
        ],
        'default' => 'fullwidth_stacked'
      ],
      [
        'id' => 'product_reviews_style',
        'type' => 'select',
        'title' => esc_html__('Product Reviews Style', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Determines how product reviews will be styled.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "off_canvas" => "Submission Form Off Canvas",
        ],
        'default' => 'off_canvas',
      ],
      [
        'id' => 'single_product_related_upsell_carousel',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Related/Upsell Products Carousel', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Increases the max number of related/upsell products to 8 and will enable a carousel display.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '1'
      ],
      [
        'id' => 'single_product_related_upsell_carousel_number',
        'type' => 'slider',
        'title' => esc_html__('Related/Upsell Products Carousel Max Products', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 8,
        "min" => 4,
        "step" => 1,
        "max" => 20,
        'subtitle' => esc_html__('Choose the maximum number of related/upsell products to display per carousel.', 'nectar-blocks-theme'),
        'required' => [ [ 'single_product_related_upsell_carousel', '=', '1' ] ],
        'display_value' => 'label',
      ],
      [
        'id' => 'product_add_to_cart_style',
        'type' => 'select',
        'title' => esc_html__('Add to Cart Style', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "fullwidth" => "Full Width",
          "fullwidth_qty" => "Full Width with Quantity",

        ],
        'default' => 'fullwidth_qty',
      ],
      [
        'id' => 'product_title_typography',
        'type' => 'select',
        'title' => esc_html__('Product Title Typography', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "h2" => "Heading 2",
          "h3" => "Heading 3",
          "h4" => "Heading 4",
          "h5" => "Heading 5",
        ],
        'default' => 'h2',
      ],
      [
        'id' => 'product_price_typography',
        'type' => 'select',
        'title' => esc_html__('Product Price Typography', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "h2" => "Heading 2",
          "h3" => "Heading 3",
          "h4" => "Heading 4",
          "h5" => "Heading 5",
          "h6" => "Heading 6",
        ],
        'default' => 'h5',
      ],
      [
        'id' => 'product_tab_heading_typography',
        'type' => 'select',
        'title' => esc_html__('Product Tab Heading Typography', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "h2" => "Heading 2",
          "h3" => "Heading 3",
          "h4" => "Heading 4",
          "h5" => "Heading 5",
        ],
        'default' => 'Heading 5',
      ],
      [
        'id' => 'product_variable_select_style',
        'type' => 'select',
        'title' => esc_html__('Product Variable Product Dropdown Style', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Default",
          "underline" => "Animated Underline",
        ],
        'default' => 'underline',
      ],

      [
        'id' => 'product_gallery_bg_color',
        'type' => 'color',
        'title' => esc_html__('Product Gallery BG Color', 'nectar-blocks-theme'),
        'transparent' => false,
        'desc' => '',
        'default' => ''
      ],

      [
        'id' => 'woo_hide_product_sku',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove SKU From Product Page', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'woo_hide_product_additional_info_tab',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Remove Additional Information Tab From Product Page', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => '',
        'default' => '0'
      ],
    ];

    return [
      'section_id' => 'woocommerce-single-product-section',
      'settings' => [
        'panel' => 'woocommerce-panel',
        'title' => esc_html__( 'Single Product', 'nectar-blocks-theme' ),
        'priority' => 2
      ],
      'controls' => $controls
    ];
  }

  public static function get_woocommerce_archive_header() {
    $controls = [

      [
        'id' => 'product_archive_bg_image',
        'type' => 'media',
        'title' => esc_html__('Archive Header Background Image', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload an optional background that will be used as the default on all woocommerce archive pages.', 'nectar-blocks-theme'),
        'desc' => '',
      ],

      [
        'id' => 'product_archive_header_size',
        'type' => 'select',
        'title' => esc_html__('Product Category Header Sizing', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('When a product category header image is supplied, this option will control the sizing of the header area.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "default" => "Fullwidth",
          "contained" => "Contained"
        ],
        'default' => 'default',
      ],

      [
        'id' => 'product_archive_header_br',
        'type' => 'slider',
        'title' => esc_html__('Product Category Header Roundness', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0,
        "min" => 0,
        "step" => 1,
        "max" => 20,
        'display_value' => 'label'
      ],
      [
        'id' => 'product_archive_header_parallax',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Product Category Header Parallax Scrolling', 'nectar-blocks-theme'),
        'sub_desc' => '',
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'product_archive_header_auto_height',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Product Category Header Auto Height', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Will automatically adjust the height of your header depending on the content.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],
      [
        'id' => 'product_archive_header_text_width',
        'type' => 'slider',
        'title' => esc_html__('Product Category Header Max Content Width', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 1000,
        "min" => 300,
        "step" => 10,
        "max" => 2000,
        'display_value' => 'label'
      ],
    ];

    return [
      'section_id' => 'woocommerce-archive-header-section',
      'settings' => [
        'panel' => 'woocommerce-panel',
        'title' => esc_html__( 'Archive Header', 'nectar-blocks-theme' ),
        'priority' => 3
      ],
      'controls' => $controls
    ];
  }
}
