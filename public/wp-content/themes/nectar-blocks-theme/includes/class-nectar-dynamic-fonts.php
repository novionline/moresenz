<?php

/**
 * Nectar Dynamic Colors Class
 *
 * @version 1.0
 */
class Nectar_Dynamic_Fonts {
    private static $instance = null;

    public static $css_rules = [];

    public static $options;

    public static $devices = [
        'desktop' => '@media all',
        'tablet' => '@media (max-width: 1024px)',
        'mobile' => '@media (max-width: 767px)',
    ];

    public static $font_option_keys = [
        'logo_font_family',
        'navigation_font_family',
        'navigation_dropdown_font_family',
        'off_canvas_nav_font_family',
        'off_canvas_nav_subtext_font_family',
        'page_heading_font_family',
        'page_heading_subtitle_font_family',
        'testimonial_font_family',
        'blog_single_post_content_font_family',
        'nectar_woo_shop_product_title_font_family',
        'nectar_woo_shop_product_secondary_font_family',
        'navigation_custom_text'
    ];

    public static $product_title_typography = false;

    public function __construct() {

        self::$options = get_nectar_theme_options();
        $this->setup_vars();
        $this->gather_rules();

    }

    /**
    * Singleton instance
    *
    */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Nectar_Dynamic_Fonts();
        }

        return self::$instance;
    }

    /**
    * Only needed until we have a system to handle defaults correctly.
    *
    * @since 1.0
    *
    */
    public function setup_vars() {

        global $nectar_options;

        if( class_exists( 'woocommerce' ) &&
            isset($nectar_options['product_title_typography']) &&
            'default' !== $nectar_options['product_title_typography'] ) {
                self::$product_title_typography = esc_html($nectar_options['product_title_typography']);
        }

    }

    public function output_CSS() {

        $this->setup_vars();
        $this->gather_rules();

        foreach(self::$css_rules as $css_arr) {

            if( isset($css_arr['skip_on_frontend']) ) {
                continue;
            }

            if( isset($css_arr['conditionals']) && false === $css_arr['conditionals'] ) {
                continue;
            }

            $settings = self::$options[$css_arr['key']];
            if($settings) {
                // Opening selector.
                echo $css_arr['selectors'] . '{';

                     // Font family.
                    echo self::get_font_family_properties($settings);

                    // Font weight.
                    echo self::get_font_weight_properties($settings);

                    // Font style.
                    echo self::get_font_style_properties($settings);

                    // Letter Spacing.
                    echo self::get_font_letter_spacing_properties($settings);

                    // Font transform.
                    echo self::get_font_transform_properties($settings);

                    // Font color.
                    echo self::get_font_color_properties($settings);

                echo '}';

                // Responsive settings.

                if( isset($settings['fontSize']) ) {
                    echo self::get_font_size_rules($settings, $css_arr['selectors']);
                }
                // Line height.
                if( isset($settings['lineHeight']) ) {
                    echo self::get_line_height_rules($settings, $css_arr['selectors']);
                }

            }

        }
    }

    /**
    * Converts data to format for kirki
    *
    * @return array
    * ( 'element' => string,
    *   'property' => string,
    *   'suffix' => string,
    *   'value_pattern' => string  )
    *
    * @since 1.0
    *
    */
    public function kirki_arrays($key) {

        $arrays = [];

        $relevant_rules = array_merge(
            self::$css_rules,
            $this->customizer_specific_rules()
        );

        foreach( $relevant_rules as $css_arr ) {

            // limit returned arrays to passed key.
            if( ! isset($css_arr['key']) || $css_arr['key'] !== $key ) {
                continue;
            }

            // constant props.
            $kirki_array = [
                'element' => $css_arr['selectors']
            ];

            // addons.
            if( isset($css_arr['addon_rules']) ) {
                $kirki_array['addon_rules'] = $css_arr['addon_rules'];
            }

            // exclude
            if( isset($css_arr['exclude_props']) ) {
                $kirki_array['exclude_props'] = $css_arr['exclude_props'];
            }

            // key.
            if( isset($css_arr['key']) ) {
                $kirki_array['key'] = $css_arr['key'];
            }

            $arrays[] = $kirki_array;

        }

        return $arrays;
    }

  /**
   * @param array $settings
   * @return string Font family properties.
   */
  public static function get_font_family_properties(array $settings) {

    $css = '';

    if( isset($settings['fontFamily']) && ! empty($settings['fontFamily']) ) {
      if ( self::font_needs_quotes($settings['fontFamily']) ) {
        $css .= "font-family: '$settings[fontFamily]';";
      } else {
        $css .= "font-family: $settings[fontFamily];";
      }
    }

    return $css;
  }

  public static function font_needs_quotes($fontFamily) {
    return preg_match('/[^a-zA-Z\s-]/', $fontFamily) === 1;
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

    if( isset($settings['fontWeight']) && strpos($settings['fontWeight'], 'italic' ) !== false ) {
      // Check if style is merged into weight. (Google does this)
      $css .= "font-style: italic;";
    } else if( isset($settings['fontStyle']) && ! empty($settings['fontStyle']) ) {
      $css .= "font-style: $settings[fontStyle];";
    } else {
      $css .= "font-style: normal;";
    }

    return $css;
  }

  /**
   * @param array $settings
   * @return string Font weight properties.
   */
  public static function get_font_weight_properties(array $settings) {
    $css = '';
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
    foreach( $settings['lineHeight'] as $device => $size ) {
      if( isset($size['value']) && ! empty($size['value']) ) {
        $css .= self::$devices[$device] . ' {
          ' . $selectors . ' {
            line-height: ' . self::sizing_field_to_string($size) . ';
          }
        }';
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
    // Sometimes the order for devices is not always desktop, tablet, mobile so we need to sort it.
    $sorted_settings = [];
    foreach(['desktop', 'tablet', 'mobile'] as $device) {
      if(isset($settings['fontSize'][$device])) {
        $sorted_settings[$device] = $settings['fontSize'][$device];
      }
    }
    $settings['fontSize'] = $sorted_settings;
    foreach( $settings['fontSize'] as $device => $size ) {

      if( isset($size['disabled']) && $size['disabled'] === true ) {
        continue;
      }

      $clampValues = [
        'min' => isset($settings['fontSizeMin'][$device]) ? $settings['fontSizeMin'][$device] : false,
        'max' => isset($settings['fontSizeMax'][$device]) ? $settings['fontSizeMax'][$device] : false
      ];

      if( isset($size['value']) && ! empty($size['value']) ) {
        $css .= self::$devices[$device] . ' {
          ' . $selectors . ' {
            font-size: ' . self::clamp_values($clampValues, $size) . ';
          }
        }';
      }
    }

    return $css;
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

     /**
    * Gathers/flattens all rules from all
    * colors to prepare for output
    *
    * @since 1.0
    *
    */
    public function gather_rules() {

        global $nectar_options;

        // Reset for css output to have fresh data set.
        // Needed for cusrtomizer refresh.
        self::$css_rules = [];

        $rules = [];

        /******* Logo Font *******************************************/

        $rules[] = [
            'selectors' => '#nectar-nav #logo.no-image,
                body #nectar-nav-spacer .logo,
                #nectar-nav[data-format="centered-menu"] .logo-spacing[data-using-image="false"],
                #nectar-nav[data-format="centered-logo-between-menu"] .logo-spacing[data-using-image="false"]',

            'key' => 'logo_font_family',
            'suffix' => '',
        ];

        $rules[] = [
            'selectors' => '#nectar-nav #logo.no-image',
            'key' => '',
            'suffix' => ''
        ];

        /******* Navigation Font *******************************************/
        $rules[] = [
            'selectors' => '#top nav > ul > li > a,
            .span_3 .pull-left-wrap > ul > li > a,
            body.material #search-outer #search input[type="text"],
            #top ul .slide-out-widget-area-toggle a i.label,
            #top .span_9 > .slide-out-widget-area-toggle a.using-label .label,
            #header-secondary-outer .nectar-center-text,
            #slide-out-widget-area .secondary-header-text,
            #nectar-nav #mobile-menu ul li a,
            #nectar-nav #mobile-menu .secondary-header-text,
            .nectar-mobile-only.mobile-header a',

            'key' => 'navigation_font_family',
            'suffix' => '',
        ];

        //TODO do this in style.css limit dropdown arrow
        // $rules[] = array(
        //     'selectors'    => '.material .sf-menu > li > a > .sf-sub-indicator [class^="icon-"]',

        //     'addon_rules'  => '
        //         font-size: 18px;',

        //     'key'          => '',
        //     'conditionals' => $nav_font['size'] >= 16
        // );

        // min line height
        $nav_line_height = isset($nectar_options['navigation_font_family']['line-height']) ? intval($nectar_options['navigation_font_family']['line-height']) : false;
        $rules[] = [
            'selectors' => '#top nav > ul > li > a',
            'addon_rules' => 'line-height: 10px;',

            'key' => '',
            'conditionals' => $nav_line_height && $nav_line_height < 10
        ];

        /******* Navigation Dropdown Font *******************************************/
        $rules[] = [
            'selectors' => '#top .sf-menu li ul li a,
            #header-secondary-outer nav > ul > li > a,
            #header-secondary-outer .sf-menu li ul li a,
            #header-secondary-outer ul ul li a,
            #nectar-nav .widget_shopping_cart .cart_list a,
            .nectar-slide-in-cart.style_slide_in_click .close-cart,
            body #slide-out-widget-area[class*="slide-out-from-right"] .inner .off-canvas-menu-container li li a',

            'key' => 'navigation_dropdown_font_family',
            'suffix' => '',
        ];

        /******* Navigation Custom Text *******************************************/
        $rules[] = [
          'selectors' => '#nectar-nav .nectar-header-text-content',

          'key' => 'navigation_custom_text',
          'suffix' => '',
      ];

          /******* Off canvas menu *******************************************/

          $rules[] = [
            'selectors' => 'body #slide-out-widget-area .inner .off-canvas-menu-container li > a,
			body #slide-out-widget-area.fullscreen .inner .off-canvas-menu-container li > a,
			body #slide-out-widget-area.fullscreen-alt .inner .off-canvas-menu-container li > a,
			body #slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li > a,
			body #nectar-ocm-ht-line-check',

            'key' => 'off_canvas_nav_font_family',
            'suffix' => '',
        ];

        /******* Off canvas menu description text *******************************************/

        $rules[] = [
            'selectors' => 'body #slide-out-widget-area .menuwrapper li small,
                #nectar-nav .sf-menu li ul li a .item_desc,
                #slide-out-widget-area.fullscreen-split .off-canvas-menu-container li small,
                 #slide-out-widget-area .off-canvas-menu-container .nectar-ext-menu-item .item_desc,
                .material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container .menu li small,
                #nectar-nav #mobile-menu ul ul > li > a .item_desc,
                .nectar-ext-menu-item .menu-item-desc,
                #slide-out-widget-area .inner .off-canvas-menu-container li > a .item_desc',

            'key' => 'off_canvas_nav_subtext_font_family',
            'suffix' => '',
        ];

        /******* Page Heading Font *******************************************/
        $rules[] = [
            'selectors' => 'body #page-header-bg h1,
                html body .row .col.section-title h1,
                div[data-style="parallax_next_only"].blog_next_prev_buttons h3,
                .full-width-content.blog_next_prev_buttons[data-style="fullwidth_next_only"] h3,
                .featured-media-under-header h1,
                .nectar-shop-header h1',

            'key' => 'page_heading_font_family',
            'suffix' => '',
        ];

        /******* Page Sub Heading Font *******************************************/

        $rules[] = [
            'selectors' => 'body #page-header-bg .span_6 span.subheader,
			#page-header-bg span.result-num,
			body .row .col.section-title > span,
			.page-header-no-bg .col.section-title h1 > span',

            'key' => 'page_heading_subtitle_font_family',
            'suffix' => '',
        ];

        /******* Testimonial Font *******************************************/
        $rules[] = [
            'selectors' => 'blockquote',

            'key' => 'testimonial_font_family',
            'suffix' => '',
        ];

        /******* Blog Single Post Content Font *******************************************/
        $rules[] = [
            'selectors' => '.single-post .nectar_template_single__post, .single-post .post-content, .featured-media-under-header__excerpt',

            'key' => 'blog_single_post_content_font_family',
            'suffix' => '',
        ];

        /******* WooCommerce Product Title Font *******************************************/
        $rules[] = [
            'selectors' => '.woocommerce ul.products li.product .woocommerce-loop-product__title,
			.woocommerce ul.products li.product h3, .woocommerce ul.products li.product h2,
			.woocommerce ul.products li.product h2, .woocommerce-page ul.products li.product h2',
            'key' => 'nectar_woo_shop_product_title_font_family',
            'suffix' => '',
        ];

        /******* WooCommerce Product Secondary Font *******************************************/
        $rules[] = [
            'selectors' => '.woocommerce .material.product .product-wrap .product-add-to-cart .price .amount,
			.woocommerce .material.product .product-wrap .product-add-to-cart a,
			.woocommerce .material.product .product-wrap .product-add-to-cart a > span,
			.woocommerce .material.product .product-wrap .product-add-to-cart a.added_to_cart,
			html .woocommerce ul.products li.product.material .price,
			.woocommerce ul.products li.product.material .price ins,
			.woocommerce ul.products li.product.material .price ins .amount,
			.woocommerce-page ul.products li.product.material .price ins span,
			.material.product .product-wrap .product-add-to-cart a span,
			html .woocommerce ul.products .text_on_hover.product .add_to_cart_button,
			.woocommerce ul.products li.product .price,
			.woocommerce ul.products li.product .price ins,
			.woocommerce ul.products li.product .price ins .amount,
			html .woocommerce .material.product .product-wrap .product-add-to-cart a.added_to_cart,
			body .material.product .product-wrap .product-add-to-cart[data-nectar-quickview="true"] a span,
			.woocommerce .material.product .product-wrap .product-add-to-cart a.added_to_cart,
			.products li.product.minimal .product-meta .price,
			.products li.product.minimal .product-meta .amount',
            'key' => 'nectar_woo_shop_product_secondary_font_family',
            'suffix' => '',
        ];

        /******* Set rules to class  *******************************************/
        self::$css_rules = $rules;

    }

    public static function get_used_theme_google_fonts() {
      self::$options = get_nectar_theme_options();

      $google_fonts = [];

      foreach (self::$font_option_keys as $font) {
        if (isset(self::$options[$font]) && self::$options[$font]['fontSource'] === 'Google') {
          array_push($google_fonts, self::$options[$font]);
        }
      }

      return $google_fonts;
    }

    public static function create_google_fonts_link() {

      self::$options = get_nectar_theme_options();

      $google_fonts = [];
      $google_fonts_subsets = [];

      // go through all saved typography settings and output google fonts/weights/subsets.
      foreach (self::$font_option_keys as $font) {
        if (isset(self::$options[$font]) && self::$options[$font]['fontSource'] === 'Google') {

          // font family.
            $family = urlencode(self::$options[$font]['fontFamily']);
            if( ! isset($google_fonts[$family]) ) {
            $google_fonts[$family] = [];
          }

          // weights.
          if( isset(self::$options[$font]['fontWeight']) ) {

            $font_weight = self::$options[$font]['fontWeight'];

            if ( 'regular' === $font_weight ) {
              $font_weight = '400';
            }

            if( ! isset($google_fonts[$family][$font_weight]) ) {
              $google_fonts[$family][$font_weight] = $font_weight;
            }

          } // end font weight.

          // subset.
          if( isset(self::$options[$font]['fontSubset']) && ! empty(self::$options[$font]['fontSubset'])) {
          $subset = self::$options[$font]['fontSubset'];
            if( ! isset($google_fonts_subsets[$subset]) ) {
              $google_fonts_subsets[$subset] = $subset;
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

          $joined_fonts .= $font_name . ':' . implode(',', $font_weight) . '|';
        }

        $subsets = ! empty($google_fonts_subsets) ? implode(',', $google_fonts_subsets) : 'latin';
        if ( ! empty($joined_fonts) ) {
          return 'https://fonts.googleapis.com/css?family=' . $joined_fonts . '&display=swap&subset=' . $subsets;
        }
      }

      return false;
    }

    public function customizer_specific_rules() {
        $rules = [];
        return $rules;
    }
}

function Nectar_Dynamic_Fonts() {
    return Nectar_Dynamic_Fonts::get_instance();
}