<?php

/**
 * Nectar Dynamic Colors Class
 *
 * @version 1.0
 */

/**
 * Generates a array of rules which will be passed to the kirki output param.
 * The actual data passed to kirki is formatted in the kirki_arrays() method.
 *
 */

class Nectar_Dynamic_Colors {
    private static $instance = null;

    public static $css_rules = [];

    public static $header_hover_animation;

    public static $off_canvas_style;

    public static $theme_skin;

    public static $using_underline_dropdown_effect;

    public static $form_style;

    public static $woocommerce_active;

    public static $options;

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
            self::$instance = new Nectar_Dynamic_Colors();
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

        $nectar_options = self::$options;

        self::$header_hover_animation = ( isset($nectar_options['header-hover-effect']) && ! empty($nectar_options['header-hover-effect']) ) ? $nectar_options['header-hover-effect'] : 'animated_underline';
        self::$off_canvas_style = ( isset($nectar_options['header-slide-out-widget-area-style']) ) ? $nectar_options['header-slide-out-widget-area-style'] : 'slide-out-from-right';
        self::$theme_skin = 'material';
        self::$using_underline_dropdown_effect = ( isset($nectar_options['header-dropdown-hover-effect']) && $nectar_options['header-dropdown-hover-effect'] === 'animated_underline' ) ? true : false;
        self::$form_style = ( isset($nectar_options['form-style']) ) ? $nectar_options['form-style'] : 'default';

        self::$woocommerce_active = ( class_exists( 'woocommerce' )) ? true : false;
    }

    /**
    * Gathers/flattens all rules from all
    * colors to prepare for output
    *
    * @since 1.0
    *
    */
    public function gather_rules() {

        self::$css_rules = array_merge(
            $this->accent_color(),
            $this->accent_text_color(),
            $this->secondary_colors(),
            $this->gradient_colors(),
            $this->general_colors(),
            $this->header_colors()
        );

    }

    /**
    * Converts the placeholder {$} values to actual color values
    *
    * @since 1.0
    *
    */
    public function convert_declarations($css_arr) {

        $nectar_options = self::$options;

        $declarations = $css_arr['declarations'];
        $color = isset($css_arr['color']) ? $css_arr['color'] : false;
        $color_2 = isset($css_arr['color_2']) ? $css_arr['color_2'] : false;

        if( strpos($declarations, '$') === false || $color === false ) {
            $value = ( isset($css_arr['numerical']) ) ? intval($declarations) : $declarations;
            return $value;
        }

        // Gradient colors.
        if( in_array( $color, ['extra-color-gradient', 'extra-color-gradient-2']) ) {
            $gradient_color = $nectar_options[$color];
            $from = $gradient_color['from'];
            $to = $gradient_color['to'];

            $property = $this->str_replace_once("$", $to, $declarations);
            $property = $this->str_replace_once("$", $from, $property);

            return $property;
        }
        // Custom Gradient colors.
        else if ( strpos($declarations, '$') !== false &&
                strpos($declarations, '@') !== false && $color_2 ) {

            $gradient_color = $nectar_options[$color];
            $gradient_color_2 = $nectar_options[$color_2];

            return str_replace(["$", "@"], [$gradient_color, $gradient_color_2], $declarations);
        }
        // Regular colors.
        else {
            $color = $nectar_options[$color];
            return str_replace("$", $color, $declarations);
        }

    }

    /**
     * Replaces the first occurance of a string.
     *
     * @since 1.0
     *
     */
    public function str_replace_once( $needle, $replace, $haystack ) {
        if ( ( $pos = strpos( $haystack, $needle ) ) === false )
            return $haystack;

        return substr_replace( $haystack, $replace, $pos, strlen( $needle ) );
    }

    /**
     * Echo's out all of the color CSS.
     *
     * This gets called when saving changes in the customizer.
     *
     * @since 1.0
     *
     */
    public function output_CSS_rules($css_arr) {

        $declarations = '';
        $pre_declarations = ( isset($css_arr['fallback_declarations']) ) ? $css_arr['fallback_declarations'] : '';
        $suffix = ( isset($css_arr['suffix']) ) ? $css_arr['suffix'] : '';

        // Custom mapped rules.
        if( isset($css_arr['custom_selector_mapping']) ) {

            $rules = '';

            foreach( $css_arr['custom_selector_mapping'] as $prop => $selector ) {

                $property = isset($css_arr['property']) ? $css_arr['property'] : 'not-set';
                $value = (isset($css_arr['declarations']) && isset($css_arr['declarations'][$prop])) ? $css_arr['declarations'][$prop] : 'not-set';

                $rules .= $selector . '{' . $property . ':' . $value . $suffix . '; }';
            }

            return $rules;
        }

        // Regular rules.
        if( is_array( $css_arr['declarations'] ) ) {
            foreach( $css_arr['declarations'] as $prop => $value ) {
                $value = ( isset($css_arr['numerical']) ) ? intval($value) : $value;
                $declarations .= $prop . ':' . $value . $suffix . ';';
            }
        }
        else {
            $declarations = $this->convert_declarations($css_arr);
            $declarations = $css_arr['property'] . ':' . $declarations . $suffix . ';';
        }

        return $css_arr['selectors'] . '{' . $pre_declarations . $declarations . '}';
    }

    public function output_CSS() {

        foreach(self::$css_rules as $css_arr) {

            if( isset($css_arr['skip_on_frontend']) ) {
                continue;
            }

            if( isset($css_arr['conditionals']) && false === $css_arr['conditionals'] ) {
                continue;
            }

            // Opening media query.
            if( isset($css_arr['media_query']) ) {
                echo '@media only screen and (' . esc_attr($css_arr['media_query']) . ') {';
            }

            echo $this->output_CSS_rules($css_arr);

            // Closing media query.
            if( isset($css_arr['media_query']) ) {
                echo '}';
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
    public function kirki_arrays($color) {

        $arrays = [];

        $relevant_rules = array_merge(
            self::$css_rules,
            $this->customizer_specific_rules()
        );

        foreach( $relevant_rules as $css_arr ) {

            // limit returned arrays to passed color.
            if( $css_arr['color'] !== $color ) {
                continue;
            }

            // constant props.
            $kirki_array = [
                'element' => $css_arr['selectors'],
                'property' => $css_arr['property'],
            ];

            // suffix.
            if( isset($css_arr['suffix']) ) {
                $kirki_array['suffix'] = $css_arr['suffix'];
            }

            if( isset($css_arr['custom_selector_mapping']) ) {
                $kirki_array['custom_selector_mapping'] = $css_arr['custom_selector_mapping'];
            }

            // Complex declarations (value_pattern).
            if( isset($css_arr['declarations']) &&
                ! is_array($css_arr['declarations']) &&
                strpos($css_arr['declarations'], '$') !== false ||
                in_array($css_arr['color'], ['extra-color-gradient','extra-color-gradient-2'] ) ) {
                $kirki_array['value_pattern'] = $css_arr['declarations'];

                // Pattern replacement.
                if( isset($css_arr['pattern_replace']) ) {
                    $kirki_array['pattern_replace'] = $css_arr['pattern_replace'];
                }
            }

            if( isset($css_arr['fallback_declarations']) ) {
                $kirki_array['fallback_declarations'] = $css_arr['fallback_declarations'];
            }

            $arrays[] = $kirki_array;

        }

        return $arrays;
    }

    /**
    * Accent color specific CSS
    * Declarations can also use $ to represent the color for complex rules.
    *
    *
    * @return array
    * ( 'selectors' => string,
    *   'declarations' => string,
    *   'property' => string,
    *   'color' => string,
    *   'suffix' => string,
    *   'conditionals' => bool  )
    *
    * @since 1.0
    *
    */
    public function accent_text_color() {

        $nectar_options = self::$options;

        $accent_text_color = false;
        if ( isset($nectar_options['accent-text-color']) && ! empty($nectar_options['accent-text-color']) ) {
            $accent_text_color = esc_attr($nectar_options['accent-text-color']);
        }

        $rules = [];

        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-accent-text-color',
            'declarations' => $accent_text_color,
            'suffix' => '',
            'conditionals' => $accent_text_color
        ];

        // Add current color key to all rules.
        foreach( $rules as $index => $rule ) {
            $rules[$index]['color'] = 'accent-text-color';
        }

        return $rules;
    }

    public function accent_color() {

        $nectar_options = self::$options;

        $accent_color = isset($nectar_options['accent-color']) && ! empty($nectar_options['accent-color']) ? esc_attr($nectar_options['accent-color']) : false;

        $rules = [];

        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-accent-color',
            'declarations' => $accent_color,
            'suffix' => '',
            'conditionals' => $accent_color
        ];

        // Color.
        $rules[] = [
            'selectors' =>
                '.nectar-color-accent-color,
				label span,
				body [class^="icon-"].icon-default-style,
				.comment-author a:hover,
				.comment-author a:focus,
				.post .post-header h2 a,
				.post .post-header a:hover,
				.post .post-header a:focus,
				#single-below-header a:hover,
				#single-below-header a:focus,
				.comment-list .pingback .comment-body > a:hover,
				#footer-outer #copyright li a i:hover,
				.widget:not(.nectar_popular_posts_widget):not(.recent_posts_extra_widget) li a:hover,
				#sidebar .widget:not(.nectar_popular_posts_widget):not(.recent_posts_extra_widget) li a:hover,
				#footer-outer .widget:not(.nectar_popular_posts_widget):not(.recent_posts_extra_widget) li a:hover,
				#top nav .sf-menu .current_page_item > a .sf-sub-indicator i,
				#top nav .sf-menu .current_page_ancestor > a .sf-sub-indicator i,
				.sf-menu > .current_page_ancestor > a > .sf-sub-indicator i,
				.widget .tagcloud a,
				#single-below-header a:hover [class^="icon-"],
				.wpcf7-form .wpcf7-not-valid-tip,
				#nectar-nav .nectar-menu-label',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '',
            'conditionals' => $accent_color
        ];

        // color !important

        // Header link hover: color property.
        $rules[] = [
            'selectors' =>
                '#nectar-nav[data-lhe="default"] #top nav > ul > li > a:hover,
				#nectar-nav[data-lhe="default"] #top nav .sf-menu > .sfHover:not(#social-in-menu) > a,
				#nectar-nav[data-lhe="default"] #top nav .sf-menu > [class*="current"] > a,
				#nectar-nav[data-lhe="default"] #top nav > ul > .button_bordered > a:hover,
				#nectar-nav[data-lhe="default"] #top nav > .sf-menu > .button_bordered.sfHover > a,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header a:hover,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header li[class*="current"] a',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '!important',
            'conditionals' => 'default' === self::$header_hover_animation && $accent_color,
        ];

        // border-color !important
        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header .menu-title-text:after',

            'declarations' => $accent_color,
            'property' => 'border-color',
            'suffix' => '!important',
            'conditionals' => 'animated_underline' === self::$header_hover_animation && $accent_color,
        ];

        // color property !important
        $rules[] = [
            'selectors' =>
                '#nectar-nav #top nav > ul > .button_bordered > a:hover,
				#nectar-nav:not(.transparent) #social-in-menu a i:after,
				.sf-menu > li > a:hover > .sf-sub-indicator i,
				.sf-menu > li > a:active > .sf-sub-indicator i,
				.sf-menu > .sfHover > a > .sf-sub-indicator i,
				.sf-menu .megamenu > ul > li:hover > a,
				#nectar-nav nav > ul > .megamenu > ul > li > a:hover,
				#nectar-nav nav > ul > .megamenu > ul > .sfHover > a,
				#nectar-nav nav > ul > .megamenu > ul > li > a:focus,
				#top nav ul #nectar-user-account a:hover span,
				#top nav ul #search-btn a:hover span,
				#top nav ul .slide-out-widget-area-toggle a:hover span,
				body:not([data-header-color="custom"]) #nectar-nav:not([data-format="left-header"]) #top ul.cart_list a:hover,
				body #nectar-nav:not(.transparent) .cart-outer:hover .cart-menu-wrap .icon-nectar-blocks-cart,
				#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-item.has-ul > a,
				#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-ancestor.has-ul > a,
				body #header-secondary-outer #social a:hover i,
				body #header-secondary-outer #social a:focus i,
				#footer-outer a:focus,
				#footer-outer a:hover,
				.result a:hover,
				.single .post .post-meta a:hover,
				.single .post .post-meta a:focus,
				.single #single-meta div a:hover i,
				.single #single-meta div:hover > a,
				.single #single-meta div:focus > a,
				.result .title a,
				span.accent-color,
				body .hovered .nectar-love i,
				body:not(.material) #search-outer #search #close a span:hover,
				#search-outer .ui-widget-content li:hover *,
				#search-outer .ui-widget-content .ui-state-focus *,
				body #pagination .page-numbers.prev:hover,
				body #pagination .page-numbers.next:hover,
				body #pagination a.page-numbers:hover,
				body #pagination a.page-numbers:focus,
				body[data-form-b-style="see-through"] input[type=submit],
				body[data-form-b-style="see-through"] button[type=submit],
				body:not([data-header-format="left-header"]) nav > ul > .megamenu > ul > li > ul > .has-ul > a:hover,
				body:not([data-header-format="left-header"]) nav > ul > .megamenu > ul > li > ul > .has-ul > a:focus,
				.masonry.material .masonry-blog-item .meta-category a,
				.comment-list .reply a:hover,
				.comment-list .reply a:focus,
				.widget li:not(.has-img) a:hover .post-title,
				#sidebar .widget li:not(.has-img) a:hover .post-title,
				#sidebar .widget ul[data-style="featured-image-left"] li a:hover .post-title,
				#sidebar .widget .tagcloud a,
				.single .post-area .content-inner > .post-tags a,
				.post-area.featured_img_left .meta-category a,
				.post-meta .icon-salient-heart-2.loved,
				.widget_search .search-form button[type=submit] .icon-nectar-blocks-search,
				body.search-no-results .search-form button[type=submit] .icon-nectar-blocks-search',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '!important',
            'conditionals' => $accent_color
        ];

        // simple OCM.
        $rules[] = [
            'selectors' =>
                '#nectar-nav #mobile-menu ul li[class*="current"] > a,
				#nectar-nav #mobile-menu ul li a:hover,
				#nectar-nav #mobile-menu ul li a:focus,
				#nectar-nav #mobile-menu ul li a:hover .sf-sub-indicator i,
				#nectar-nav #mobile-menu ul li a:focus .sf-sub-indicator i',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '',
            'conditionals' => 'simple' === self::$off_canvas_style && $accent_color,
        ];

        // BG color main
        $rules[] = [
            'selectors' =>
                '.nectar-bg-accent-color,
				.nectar-bg-hover-accent-color:hover,
				#nectar-content-wrap .nectar-bg-pseudo-accent-color:before,
				.nectar-cta[data-color="accent-color"]:not([data-style="material"]) .link_wrap,
				.main-content .widget_calendar caption,
				#footer-outer .widget_calendar caption,
				.post .more-link span:hover,
				.post.format-quote .post-content .quote-inner,
				.post.format-link .post-content .link-inner,
				.format-status .post-content .status-inner,
				input[type=submit]:hover,
				input[type="button"]:hover,
				body[data-form-b-style="regular"] input[type=submit],
				body[data-form-b-style="regular"] button[type=submit],
				#slide-out-widget-area,
				#slide-out-widget-area-bg.fullscreen,
				#slide-out-widget-area-bg.fullscreen-split,
				#slide-out-widget-area-bg.fullscreen-alt .bg-inner,
				.widget .material .widget .tagcloud a:before,
				#nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after,
				.nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .nectar-notice,
				.woocommerce #review_form #respond .form-submit #submit,
				#nectar-nav .nectar-menu-label:before',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '',
            'conditionals' => $accent_color
        ];

        // BG color main !important
        $rules[] = [
            'selectors' =>
                '#footer-outer #footer-widgets .col .tagcloud a:hover,
				#sidebar .widget .tagcloud a:hover,
				#pagination .next a:hover,
				#pagination .prev a:hover,
				.comment-list .reply a:hover,
				.comment-list .reply a:focus,
				#footer-outer #footer-widgets .col input[type="submit"],
				.post-tags a:hover,
				#to-top:hover,
				#to-top.dark:hover,
				body[data-button-style*="rounded"] #to-top:after,
				#pagination a.page-numbers:hover,
				#pagination span.page-numbers.current,
				.bottom_controls #portfolio-nav .controls li a i:after,
				.bottom_controls #portfolio-nav ul:first-child li#all-items a:hover i,
				.mejs-controls .mejs-time-rail .mejs-time-current,
				.mejs-controls .mejs-volume-button .mejs-volume-slider .mejs-volume-current,
				.mejs-controls .mejs-horizontal-volume-slider .mejs-horizontal-volume-current,
				.post.quote .content-inner .quote-inner .whole-link,
				#nectar-nav .widget_shopping_cart a.button,
				#nectar-nav a.cart-contents .cart-wrap span,
				#nectar-nav #mobile-cart-link .cart-wrap span,
				#top nav ul .slide-out-widget-area-toggle a:hover .lines,
				#top nav ul .slide-out-widget-area-toggle a:hover .lines:after,
				#top nav ul .slide-out-widget-area-toggle a:hover .lines:before,
				#top nav ul .slide-out-widget-area-toggle a:hover .lines-button:after,
				#nectar-nav .widget_shopping_cart a.button,
				body[data-header-format="left-header"] #nectar-nav[data-lhe="animated_underline"] #top nav ul li:not([class*="button_"]) > a span:after,
				#buddypress a.button:focus,
				.select2-container .select2-choice:hover,
				.select2-dropdown-open .select2-choice,
				body[data-form-select-js="1"] .select2-container--default .select2-selection--single:hover,
				body[data-form-select-js="1"] .select2-container--default.select2-container--open .select2-selection--single,
				#top nav > ul > .button_solid_color > a:before,
				#nectar-nav.transparent #top nav > ul > .button_solid_color > a:before,
				.masonry.material .masonry-blog-item .meta-category a:before,
				.material.masonry .masonry-blog-item .video-play-button,
				.masonry.material .quote-inner:before,
				.masonry.material .link-inner:before,
				#page-header-bg[data-post-hs="default_minimal"] .inner-wrap > a:hover,
				#page-header-bg[data-post-hs="default_minimal"] .inner-wrap > a:focus,
				.single .heading-title[data-header-style="default_minimal"] .meta-category a:hover,
				.single .heading-title[data-header-style="default_minimal"] .meta-category a:focus,
				.nectar-slide-in-cart .widget_shopping_cart a.button,
				.post-area.featured_img_left .meta-category a:before,
				body.material #page-header-bg.fullscreen-header .inner-wrap >a,
				#sidebar .widget .tagcloud a:before,
				.single .post-area .content-inner > .post-tags a:before,
				.auto_meta_overlaid_spaced .post.quote .n-post-bg:after,
				.auto_meta_overlaid_spaced .post.link .n-post-bg:after,
				.post-area.featured_img_left .posts-container .article-content-wrap .video-play-button,
				.post-area.featured_img_left .post .quote-inner:before,
				.post-area.featured_img_left .link-inner:before,
				.fancybox-navigation button:hover:before,
				button[type=submit]:hover,
				button[type=submit]:focus,
				body[data-form-b-style="see-through"] input[type=submit]:hover,
				body[data-form-b-style="see-through"].woocommerce #respond input#submit:hover,
				html body[data-form-b-style="see-through"] button[type=submit]:hover,
				body[data-form-b-style="see-through"] .container-wrap .span_12.light input[type=submit]:hover,
				body[data-form-b-style="see-through"] .container-wrap .span_12.light button[type=submit]:hover,
				body.original .bypostauthor .comment-body:before,
				.widget_layered_nav ul.yith-wcan-label li a:hover,
				.widget_layered_nav ul.yith-wcan-label .chosen a ',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '!important',
            'conditionals' => $accent_color
        ];

        // Dropdown Hover Coloring.
        $rules[] = [
            'selectors' =>
                '#nectar-nav #top nav > ul > li:not(.megamenu) ul a:hover,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul a:hover,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-ancestor > a,
				#nectar-nav nav > ul > .megamenu > ul ul li a:hover,
				#nectar-nav nav > ul > .megamenu > ul ul li a:focus,
				#nectar-nav nav > ul > .megamenu > ul ul .sfHover > a,
				#header-secondary-outer ul > li:not(.megamenu) .sfHover > a,
				#header-secondary-outer ul > li:not(.megamenu) ul a:hover,
				#header-secondary-outer ul > li:not(.megamenu) ul a:focus,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul ul .current-menu-item > a',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '!important',
            'conditionals' => false === self::$using_underline_dropdown_effect && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav[data-format="left-header"] #top nav > ul > li:not(.megamenu) ul a:hover',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '',
            'conditionals' => false === self::$using_underline_dropdown_effect && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav[data-format="left-header"] .sf-menu .sub-menu .current-menu-item > a,
				.sf-menu ul .open-submenu > a',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '!important',
            'conditionals' => false === self::$using_underline_dropdown_effect && $accent_color,
        ];

        // minimal form styling.
        $rules[] = [
            'selectors' =>
                'body[data-form-style="minimal"] input[type=text]:focus,
				body[data-form-style="minimal"].woocommerce-cart table.cart .actions .coupon .input-text:focus,
				body[data-form-style="minimal"] textarea:focus,
				body[data-form-style="minimal"] input[type=email]:focus,
				body[data-form-style="minimal"] input[type=search]:focus,
				body[data-form-style="minimal"] input[type=password]:focus,
				body[data-form-style="minimal"] input[type=tel]:focus,
				body[data-form-style="minimal"] input[type=url]:focus,
				body[data-form-style="minimal"] input[type=date]:focus,
				body[data-form-style="minimal"] input[type=number]:focus,
				body[data-form-style="minimal"] select:focus',

            'declarations' => $accent_color,
            'property' => 'border-color',
            'suffix' => '',
            'conditionals' => 'minimal' === self::$form_style && $accent_color,
        ];

        // border color main.
        $rules[] = [
            'selectors' =>
                'input[type=text]:focus,
				 textarea:focus,
				input[type=email]:focus,
				input[type=search]:focus,
				input[type=password]:focus,
				input[type=tel]:focus,
				input[type=url]:focus,
				input[type=date]:focus,
				input[type=number]:focus,
				select:focus,
				.material.woocommerce-page input#coupon_code:focus,
				.material #search-outer #search input[type="text"],
				#nectar-nav[data-lhe="animated_underline"] #top nav > ul > li > a .menu-title-text:after,
				.single #single-meta div a:hover,
				.single #single-meta div a:focus,
				.single .fullscreen-blog-header #single-below-header > span a:hover,
				.blog-title #single-meta .nectar-social.hover > div a:hover,
				.material.woocommerce-page[data-form-style="default"] div input#coupon_code:focus',

            'declarations' => $accent_color,
            'property' => 'border-color',
            'suffix' => '',
            'conditionals' => $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                'body[data-form-style="minimal"] label:after,
				#footer-outer #flickr a:hover img,
				div.wpcf7-validation-errors,
				.select2-container .select2-choice:hover,
				.select2-dropdown-open .select2-choice,
				.bypostauthor img.avatar,
				blockquote::before,
				blockquote.wp-block-quote:before,
				#nectar-nav:not(.transparent) #top nav > ul > .button_bordered > a:hover:before,
				body[data-button-style="rounded"] #pagination > a:hover,
				body[data-form-b-style="see-through"] input[type=submit],
				body[data-form-b-style="see-through"] button[type=submit],
				#header-secondary-outer[data-lhe="animated_underline"] nav > .sf-menu >li >a .menu-title-text:after,
				.woocommerce-page.material .widget_price_filter .ui-slider .ui-slider-handle,
				body[data-form-b-style="see-through"] button[type=submit]:not(.search-widget-btn),
				.woocommerce-account[data-form-b-style="see-through"] .woocommerce-form-login button.button,
				.woocommerce-account[data-form-b-style="see-through"] .woocommerce-form-register button.button,
				body[data-form-b-style="see-through"] .woocommerce #order_review #payment #place_order,
				body[data-form-select-js="1"] .select2-container--default .select2-selection--single:hover,
				body[data-form-select-js="1"] .select2-container--default.select2-container--open .select2-selection--single',

            'declarations' => $accent_color,
            'property' => 'border-color',
            'suffix' => '!important',
            'conditionals' => $accent_color,
        ];

        // WooCommerce
        $rules[] = [
            'selectors' =>
                '.woocommerce div.product .woocommerce-variation-price span.price,
				.woocommerce div.product .entry-summary .stock,
				.woocommerce p.stars:hover a::before',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav .widget_shopping_cart .cart_list a,
				#nectar-nav .woocommerce.widget_shopping_cart .cart_list li a.remove,
				.woocommerce .star-rating,
				.woocommerce form .form-row .required,
				.woocommerce-page form .form-row .required,
				.woocommerce-pagination a.page-numbers:hover,
				.woocommerce p.stars a:hover,
				.woocommerce .material.product .product-wrap .product-add-to-cart a:hover,
				.woocommerce .material.product .product-wrap .product-add-to-cart a:hover > span,
				.woocommerce-MyAccount-navigation ul li.is-active a:before,
				.woocommerce-MyAccount-navigation ul li:hover a:before,
				.woocommerce.ascend .price_slider_amount button.button[type="submit"],
				.woocommerce .widget_layered_nav ul li.chosen a:after,
				.woocommerce-page .widget_layered_nav ul li.chosen a:after,
				.woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control.active,
				.woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control:hover,
				.woocommerce #review_form #respond p.comment-notes span.required,
				.nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart .cart_list a,
				#sidebar .widget_shopping_cart .cart_list li a.remove:hover,
				.text_on_hover.product .add_to_cart_button,
				.text_on_hover.product > .button,
				.minimal.product .product-wrap .normal.icon-nectar-blocks-cart[class*=" icon-"],
				.minimal.product .product-wrap i,
				.minimal.product .product-wrap .normal.icon-nectar-blocks-m-eye,
				.products li.product.minimal .product-add-to-cart .loading:after',

            'declarations' => $accent_color,
            'property' => 'color',
            'suffix' => '!important',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce div.product .woocommerce-tabs ul.tabs li.active,
				.woocommerce #content div.product .woocommerce-tabs ul.tabs li.active,
				.woocommerce-page div.product .woocommerce-tabs ul.tabs li.active,
				.woocommerce-page #content div.product .woocommerce-tabs ul.tabs li.active',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce ul.products li.product .onsale,
				.woocommerce-page ul.products li.product .onsale, .woocommerce span.onsale,
				.woocommerce-page span.onsale, .woocommerce .product-wrap .add_to_cart_button.added,
				.single-product .facebook-share a:hover, .single-product .twitter-share a:hover,
				.single-product .pinterest-share a:hover, .woocommerce-message, .woocommerce-error,
				.woocommerce-info, .woocommerce .chzn-container .chzn-results .highlighted,
				.woocommerce .chosen-container .chosen-results .highlighted, .woocommerce a.button:hover,
				.woocommerce-page a.button:hover, .woocommerce button.button:hover, .woocommerce-page button.button:hover,
				.woocommerce input.button:hover, .woocommerce-page input.button:hover,
				.woocommerce #respond input#submit:hover,
				.woocommerce-page #respond input#submit:hover,
				.woocommerce #content input.button:hover,
				.woocommerce-page #content input.button:hover,
				.woocommerce .widget_price_filter .ui-slider .ui-slider-range,
				.woocommerce-page .widget_price_filter .ui-slider .ui-slider-range,
				.woocommerce #sidebar div ul li a:hover ~ .count,
				.woocommerce #sidebar div ul li.chosen > a ~ .count,
				.woocommerce #sidebar div ul .current-cat > .count,
				.woocommerce .widget_price_filter .ui-slider .ui-slider-range,
				.woocommerce-page .widget_price_filter .ui-slider .ui-slider-range,
				.woocommerce-account .woocommerce-form-login button.button,
				.woocommerce-account .woocommerce-form-register button.button,
				.woocommerce.widget_price_filter .price_slider:not(.ui-slider):before,
				.woocommerce.widget_price_filter .price_slider:not(.ui-slider):after,
				.woocommerce.widget_price_filter .price_slider:not(.ui-slider),
				body .woocommerce.add_to_cart_inline a.button.add_to_cart_button,
				.woocommerce table.cart a.remove:hover,
				.woocommerce #content table.cart a.remove:hover,
				.woocommerce-page table.cart a.remove:hover,
				.woocommerce-page #content table.cart a.remove:hover,
				.woocommerce-page .woocommerce p.return-to-shop a.wc-backward,
				.woocommerce .yith-wcan-reset-navigation.button,
				ul.products li.minimal.product span.onsale,
				.woocommerce-page button.single_add_to_cart_button,
				.woocommerce div.product .woocommerce-tabs .full-width-content ul.tabs li a:after,
				.woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
				.woocommerce #order_review #payment #place_order,
				.woocommerce .span_4 input[type="submit"].checkout-button,
				.woocommerce .material.product .add_to_cart_button,
				body nav.woocommerce-pagination span.page-numbers.current,
				.woocommerce span.onsale .nectar-quick-view-box .onsale,
				.nectar-quick-view-box .onsale,
				.woocommerce-page .nectar-quick-view-box .onsale,
				.cart .quantity input.plus:hover,
				.cart .quantity input.minus:hover,
				.woocommerce-mini-cart .quantity input.plus:hover,
				.woocommerce-mini-cart .quantity input.minus:hover,
				body .nectar-quick-view-box .single_add_to_cart_button,
				.woocommerce .classic .add_to_cart_button,
				.woocommerce .classic .product-add-to-cart a.button,
				body[data-form-b-style="see-through"] .woocommerce #order_review #payment #place_order:hover,
				body .products-carousel .carousel-next:hover,
				body .products-carousel .carousel-prev:hover,
				.text_on_hover.product .nectar_quick_view,
				.text_on_hover.product a.added_to_cart',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '!important',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.single-product:not(.mobile) .product[data-gallery-style="left_thumb_sticky"] .product-thumbs .thumb a.active img',

            'declarations' => $accent_color,
            'property' => 'border-color',
            'suffix' => '!important',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce.material .widget_price_filter .ui-slider .ui-slider-handle:before,
				.material.woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle:before',

            'declarations' => '0 0 0 10px $ inset',
            'property' => 'box-shadow',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce.material .widget_price_filter .ui-slider .ui-slider-handle.ui-state-active:before,
				.material.woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle.ui-state-active:before',

            'declarations' => '0 0 0 2px $ inset',
            'property' => 'box-shadow',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce #sidebar .widget_layered_nav ul.yith-wcan-color li.chosen a',

            'declarations' => '0 0 0 2px $, inset 0 0 0 3px #fff',
            'property' => 'box-shadow',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce #sidebar .widget_layered_nav ul.yith-wcan-color li a:hover',

            'declarations' => '0 0 0 2px $, 0px 8px 20px rgba(0,0,0,0.2), inset 0 0 0 3px #fff',
            'property' => 'box-shadow',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control',

            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'property' => 'background-image',
            'suffix' => '',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '.woocommerce-page table.cart a.remove',

            'declarations' => $accent_color,
            'property' => 'background-color',
            'suffix' => '!important',
            'media_query' => 'max-width: 767px',
            'conditionals' => self::$woocommerce_active && $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '#footer-outer[data-link-hover="underline"][data-custom-color="false"] #footer-widgets ul:not([class*="nectar_blog_posts"]):not(.cart_list) a:not(.tag-cloud-link):not(.nectar-button),
				#footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button),
				#search-results .result .title a',

            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'property' => 'background-image',
            'suffix' => '',
            'conditionals' => $accent_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav a.cart-contents span:before',

            'declarations' => 'transparent $',
            'property' => 'border-color',
            'suffix' => '!important',
            'conditionals' => $accent_color,
        ];

        // Add current color key to all rules.
        foreach( $rules as $index => $rule ) {
            $rules[$index]['color'] = 'accent-color';
        }

        return $rules;

    }

    /**
    * Secondary (extra color) CSS
    * Declarations can also use $ to represent the color for complex rules.
    *
    * @return array
    * ( 'selectors' => string,
    *   'declarations' => string,
    *   'property' => string,
    *   'suffix' => string,
    *   'conditionals' => bool  )
    *
    * @since 1.0
    *
    */
    public function secondary_colors() {
        // not used right now.
        return [];

        $nectar_options = self::$options;

        $secondary_colors = ['extra-color-1', 'extra-color-2', 'extra-color-3'];
        $rules = [];

        // Extra color 1 specific.
        $extra_color_1 = isset($nectar_options['extra-color-1']) && ! empty($nectar_options['extra-color-1']) ? esc_attr($nectar_options['extra-color-1']) : false;

        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-extra-color-1',
            'declarations' => $extra_color_1,
            'suffix' => '',
            'color' => 'extra-color-1',
            'conditionals' => $extra_color_1
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav .widget_shopping_cart .cart_list li a.remove,
				.stock.out-of-stock,
				#nectar-nav #top nav > ul > .button_bordered_2 > a:hover,
				#nectar-nav[data-lhe="default"] #top nav > ul > .button_bordered_2 > a:hover,
				#nectar-nav[data-lhe="default"] #top nav .sf-menu .button_bordered_2.current-menu-item > a',

            'declarations' => $extra_color_1,
            'property' => 'color',
            'suffix' => '!important',
            'color' => 'extra-color-1',
            'conditionals' => $extra_color_1
        ];

        $rules[] = [
            'selectors' =>
                '#top nav > ul > .button_solid_color_2 > a:before,
				#nectar-nav.transparent #top nav > ul > .button_solid_color_2 > a:before,
				#nectar-nav .widget_shopping_cart a.button,
				.woocommerce ul.products li.product .onsale,
				.woocommerce-page ul.products li.product .onsale,
				.woocommerce span.onsale,
				.woocommerce-page span.onsale',

            'declarations' => $extra_color_1,
            'property' => 'background-color',
            'suffix' => '',
            'color' => 'extra-color-1',
            'conditionals' => $extra_color_1
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav .woocommerce.widget_shopping_cart .cart_list li a.remove,
				#nectar-nav .woocommerce.widget_shopping_cart .cart_list li a.remove,
				#nectar-nav:not(.transparent) #top nav > ul > .button_bordered_2 > a:hover:before',

            'declarations' => $extra_color_1,
            'property' => 'border-color',
            'suffix' => '',
            'color' => 'extra-color-1',
            'conditionals' => $extra_color_1
        ];

        // Main loop.
        foreach( $secondary_colors as $color_key ) {

            $hex_color = isset($nectar_options[$color_key]) && ! empty($nectar_options[$color_key]) ? esc_attr($nectar_options[$color_key]) : false;

            // Color.
            $rules[] = [
                'selectors' =>
                    ':root',
                'property' => '--nectar-' . $color_key,
                'declarations' => $hex_color,
                'suffix' => '',
                'color' => $color_key,
                'conditionals' => $hex_color
            ];

            $rules[] = [
                'selectors' => '.nectar-color-' . $color_key,
                'declarations' => $hex_color,
                'property' => 'color',
                'suffix' => '',
                'color' => $color_key,
                'conditionals' => $hex_color
            ];

            // Color important.
            $rules[] = [
                'selectors' =>
                    'span.' . $color_key,

                'declarations' => $hex_color,
                'property' => 'color',
                'suffix' => '!important',
                'color' => $color_key,
                'conditionals' => $hex_color
            ];

            // Background color.
            $rules[] = [
                'selectors' =>
                    '.nectar-bg-' . $color_key . ',
					#nectar-content-wrap .nectar-bg-pseudo-' . $color_key . ':before',

                'declarations' => $hex_color,
                'property' => 'background-color',
                'suffix' => '',
                'color' => $color_key,
                'conditionals' => $hex_color
            ];

        }

        return $rules;

    }

    /**
    * Gradient color CSS
    * Declarations can also use $ to represent the color for complex rules.
    *
    * @return array
    * ( 'selectors' => string,
    *   'declarations' => string,
    *   'property' => string,
    *   'suffix' => string,
    *   'conditionals' => bool  )
    *
    * @since 1.0
    *
    */
    public static function gradient_colors() {

        // Not used right now.
        return [];

        $nectar_options = self::$options;

        $gradient_colors = ['extra-color-gradient', 'extra-color-gradient-2'];
        $rules = [];

        foreach( $gradient_colors as $color_key ) {

            $gradient_color = (isset($nectar_options[$color_key]) && ! empty($nectar_options[$color_key])) ? $nectar_options[$color_key] : [];

            $gradient_from = isset($gradient_color['from']) && ! empty($gradient_color['from']) ? $gradient_color['from'] : false;
            $gradient_to = isset($gradient_color['to']) && ! empty($gradient_color['to']) ? $gradient_color['to'] : false;

            if( ! $gradient_from || ! $gradient_to ) {
                continue;
            }

            // Transpose selector name for extra-color-gradient.
            $selector = $color_key;

            if( $color_key === 'extra-color-gradient' ) {
                $selector = 'extra-color-gradient-1';
            }

            // TODO: these are not working, moved to custom.php with a refresh.

            // // Gradient rtl.
            // $rules[] = array(
            //  'selectors' =>
            //      '.nectar-bg-'.$selector.',
            //      #nectar-content-wrap .nectar-bg-pseudo-'.$selector.':before',

            //  'fallback_declarations' => 'background: '.$gradient_from.';',
            //  'declarations' => 'linear-gradient(to right, $, $)',
            //  'property' => 'background',
            //  'suffix' => '',
            //  'color' => $color_key,
            // );

            // #TODO: test this
            // $rules[] = array(
            //  'selectors' =>
            //      '.nectar-color-'.$selector,

            //  'fallback_declarations' => '
            //      color: '.$gradient_from.';
            //      -webkit-background-clip: text;
            //      -webkit-text-fill-color: transparent;
            //      background-clip: text;
            //      text-fill-color: transparent;',
            //  'declarations' => 'linear-gradient(to right, $, $)',
            //  'property' => 'background',
            //  'suffix' => '',
            //  'color' => $color_key,
            // );

            // Menu button gradient styles - needed for customizer
            // $rules[] = array(
            //  'selectors' =>
            //      '#nectar-nav .menu-item-btn-style-button_'.$color_key.' > a:before,
            //      #nectar-nav .menu-item-btn-style-button-border_'.$color_key.' > a:after',

            //  'property' => 'background',
            //  'fallback_declarations' => '',
            //  'declarations' => 'linear-gradient(90deg, $, $)',
            //  'color' => $color_key,
            //  'skip_on_frontend' => true,
            //  'suffix' => '',
            // );

            // $rules[] = array(
            //  'selectors' =>
            //      '#nectar-nav .menu-item-btn-style-button-animated_'.$color_key.' > a:before,
            //       #nectar-nav .menu-item-btn-style-button-border-animated_'.$color_key.' > a:after',

            //  'property' => 'background-image',
            //  'fallback_declarations' => '',
            //  'declarations' => 'linear-gradient(90deg, $, $, $)',
            //  'color' => $color_key,
            //  'skip_on_frontend' => true,
            //  'suffix' => '',
            // );

        }

        return $rules;

    }

    /**
    * General colors
    *
    * handles misc colors
    *
    * @since 1.0
    *
    */
    public function general_colors() {

        $nectar_options = self::$options;

        $rules = [];

        $overall_bg_color = (isset($nectar_options['overall-bg-color']) && ! empty($nectar_options['overall-bg-color'])) ? esc_attr($nectar_options['overall-bg-color']) : false;

        // overall BG Color.
        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-overall-bg-color',
            'declarations' => $overall_bg_color,
            'color' => 'overall-bg-color',
            'suffix' => '',
            'conditionals' => $overall_bg_color,
        ];
        $rules[] = [
            'selectors' =>
                'body,
				.container-wrap,
				.material .ocm-effect-wrap,
				.single-post #single-below-header.fullscreen-header,
				#page-header-wrap,
				.page-header-no-bg,
				body .nectar-quick-view-box div.product .product div.summary,
			  	.wpml-ls-statics-footer',

            'property' => 'background-color',
            'declarations' => $overall_bg_color,
            'color' => 'overall-bg-color',
            'suffix' => '',
            'conditionals' => $overall_bg_color,
        ];

        // Overall font color.
        // Skip when NB plugin is active since we already offer a body font color option.
        $overall_font_color = (isset($nectar_options['overall-font-color']) && ! empty($nectar_options['overall-font-color'])) ? $nectar_options['overall-font-color'] : false;
        if ( defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
            $overall_font_color = false;
        }

        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-overall-font-color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => $overall_font_color,
        ];
        $rules[] = [
            'selectors' =>
                'body,
				.woocommerce div.product .woocommerce-tabs .full-width-content ul.tabs li a,
				.woocommerce .woocommerce-breadcrumb a,
				.woocommerce .woocommerce-breadcrumb i',

            'property' => 'color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => ($overall_font_color),
        ];

        //// WooCommerce
        $rules[] = [
            'selectors' =>
                '.woocommerce-tabs .full-width-content[data-tab-style="fullwidth"] ul.tabs li a,
				.woocommerce .woocommerce-breadcrumb a,
				.nectar-shop-header > .woocommerce-ordering .select2-container--default .select2-selection__rendered,
				.woocommerce div.product .woocommerce-review-link,
				.woocommerce.single-product div.product_meta a',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => ($overall_font_color && self::$woocommerce_active),
        ];

        $rules[] = [
            'selectors' =>
                '#sidebar .price_slider_amount .price_label,
				#sidebar .price_slider_amount button.button[type="submit"]:not(:hover),
				#sidebar .price_slider_amount button.button:not(:hover)',

            'property' => 'color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => ($overall_font_color && self::$woocommerce_active),
        ];

        //// Color
        $rules[] = [
            'selectors' =>
                '#sidebar h4,
			 	body .row .col.section-title span,
				.single .heading-title[data-header-style="default_minimal"] .meta-category a',

            'property' => 'color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => ($overall_font_color),
        ];

        //// Color important
        $rules[] = [
            'selectors' =>
                '#nectar-content-wrap ul.products li.product.minimal .price ',

            'property' => 'color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '!important',
            'conditionals' => ($overall_font_color),
        ];

        //// Border color.
        $rules[] = [
            'selectors' =>
                '.single .heading-title[data-header-style="default_minimal"] .meta-category a',

            'property' => 'border-color',
            'declarations' => $overall_font_color,
            'color' => 'overall-font-color',
            'suffix' => '',
            'conditionals' => ($overall_font_color),
        ];

        //// Search results
        if ( isset($nectar_options['search-results-layout']) ) {
            $rules[] = [
                'selectors' =>
                    '#search-results[data-layout="list-no-sidebar"] .result,
					#search-results[data-layout="list-no-sidebar"] .result .title span',

                'property' => 'color',
                'declarations' => $overall_font_color,
                'color' => 'overall-font-color',
                'suffix' => '',
                'conditionals' => ( in_array($nectar_options['search-results-layout'], ['list-no-sidebar']) ),
            ];
            $rules[] = [
                'selectors' =>
                    '#search-results[data-layout="list-with-sidebar"] .result,
					#search-results[data-layout="list-with-sidebar"] .result .title span',

                'property' => 'color',
                'declarations' => $overall_font_color,
                'color' => 'overall-font-color',
                'suffix' => '',
                'conditionals' => ( in_array($nectar_options['search-results-layout'], ['list-with-sidebar']) ),
            ];
        }

        $rules[] = [
            'selectors' =>
                '.nectar-link-underline-effect--inherit-body a',

            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'overall-font-color',
            'property' => 'background-image',
            'suffix' => '',
            'conditionals' => ($overall_font_color),
        ];

        /* Blog header overlay  **************************/
        $blog_header_type = (isset($nectar_options['blog_header_type']) && ! empty($nectar_options['blog_header_type'])) ? esc_attr($nectar_options['blog_header_type']) : 'default_minimal';
        $blog_header_color = (isset($nectar_options['default_minimal_overlay_color']) && ! empty($nectar_options['default_minimal_overlay_color'])) ? esc_attr($nectar_options['default_minimal_overlay_color']) : '#2d2d2d';

        $rules[] = [
            'selectors' => '.single-post #page-header-bg[data-post-hs="default_minimal"] .page-header-bg-image:after,
				.single-post #page-header-bg[data-post-hs="default_minimal"]',
            'property' => 'background-color',
            'declarations' => $blog_header_color,
            'color' => 'default_minimal_overlay_color',
            'suffix' => '',
            'conditionals' => $blog_header_type === 'default_minimal' && ! empty($blog_header_color),
        ];

        $blog_header_overlay = (isset($nectar_options['default_minimal_overlay_opacity'])) ? $nectar_options['default_minimal_overlay_opacity'] : '0.4';

        $rules[] = [
            'selectors' =>
                '.single-post #page-header-bg[data-post-hs="default_minimal"] .page-header-bg-image:after',

            'property' => 'opacity',
            'declarations' => $blog_header_overlay,
            'color' => 'default_minimal_overlay_opacity',
            'suffix' => '',
            'conditionals' => $blog_header_type === 'default_minimal' && ! empty($blog_header_overlay),
        ];

        return $rules;
    }

    /**
    * Header navigation color scheme
    *
    * @since 1.0
    *
    */
    public function header_colors() {

        $nectar_options = self::$options;
        $rules = [];

        $using_custom_color_scheme = false;

        if( ! empty($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom'  ) {
            $using_custom_color_scheme = true;
        }

        $header_bg_color = (isset($nectar_options['header-background-color'])) ? esc_attr($nectar_options['header-background-color']) : '#ffffff';
        /* Header background color **************************/
        $rules[] = [
            'selectors' =>
                'body #nectar-nav,
				body #search-outer,
				#nectar-nav-spacer,
				#nectar-nav #search-outer:before,
				#search-outer .nectar-ajax-search-results,
				body[data-header-format="left-header"] #search-outer,
				body[data-header-format="centered-menu-bottom-bar"] #page-header-wrap.fullscreen-header,
				body #nectar-nav #mobile-menu:before,
				.nectar-slide-in-cart.style_slide_in_click',

            'property' => 'background-color',
            'declarations' => $header_bg_color,
            'color' => 'header-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($nectar_options['header-background-color']),
        ];

        $rules[] = [
            'selectors' =>
                'body .nectar-slide-in-cart:not(.style_slide_in_click) .blockUI.blockOverlay',

            'property' => 'background-color',
            'declarations' => $header_bg_color,
            'color' => 'header-background-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($nectar_options['header-background-color']),
        ];

        // header bg opacity.
        // $navBGColor = isset($nectar_options['header-background-color']) && !empty($nectar_options['header-background-color']) ? esc_attr($nectar_options['header-background-color']) : '#ffffff';
        // $navBGColor = substr($navBGColor,1);
        // $colorR = hexdec( substr( $navBGColor, 0, 2 ) );
        // $colorG = hexdec( substr( $navBGColor, 2, 2 ) );
        // $colorB = hexdec( substr( $navBGColor, 4, 2 ) );
        // $colorA = '1';

        // if ( isset($nectar_options['header-bg-opacity'] ) ) {
        //  $alpha = $nectar_options['header-bg-opacity'];
        //  $leading_zero = $alpha < 10 ? '0.0' : '0.';
        //  $colorA = $nectar_options['header-bg-opacity'] != '100' ? $leading_zero . esc_attr($nectar_options['header-bg-opacity']) : esc_attr($nectar_options['header-bg-opacity']);
        // }
        // $rules[] = array(
        //  'selectors' =>
        //      'html body #nectar-nav, html body[data-header-color="dark"] #nectar-nav',

        //  'property' => 'background-color',
        //  'declarations' => 'rgba('.$colorR.','.$colorG.','.$colorB.','.$colorA.')',
        //  'color' => 'header-bg-opacity',
        //  'suffix' => '',
        //  'conditionals' => $using_custom_color_scheme && !empty($nectar_options['header-bg-opacity']),
        // );

        //// matierial search.
        $rules[] = [
            'selectors' =>
                '.material #nectar-nav:not(.transparent) .bg-color-stripe',

            'property' => 'display',
            'declarations' => 'none',
            'color' => 'header-bg-opacity',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($nectar_options['header-bg-opacity']),
        ];

        /* header font color **************************/
        $header_font_color = (isset($nectar_options['header-font-color']) && ! empty($nectar_options['header-font-color'])) ? esc_attr($nectar_options['header-font-color']) : false;

        // font color !important
        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-header-font-color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_font_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav #top nav > ul > li > a,
				#nectar-nav .slide-out-widget-area-toggle a i.label,
				#nectar-nav:not(.transparent) #top #logo,
				#nectar-nav #top .span_9 > .slide-out-widget-area-toggle i,
				#nectar-nav #top .sf-sub-indicator i,
				#nectar-nav #top nav ul #nectar-user-account a span,
				#nectar-nav #top #toggle-nav i,
				#nectar-nav:not([data-permanent-transparent="1"]) .mobile-user-account .icon-nectar-blocks-m-user,
				#nectar-nav:not([data-permanent-transparent="1"]) .mobile-search .icon-nectar-blocks-search,
				#nectar-nav #top #mobile-cart-link i,
				#nectar-nav .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
				body[data-header-format="left-header"] #nectar-nav #social-in-menu a,
				#nectar-nav #top nav ul #search-btn a span,
				#search-outer #search input[type="text"],
				#search-outer #search #close a span,
				.material #search-outer #search .span_12 span,
				.style_slide_in_click .total,
				.style_slide_in_click .total strong,
				.nectar-slide-in-cart.style_slide_in_click h4,
				.nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart,
				.nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list.woocommerce-mini-cart .mini_cart_item a,
				.style_slide_in_click .woocommerce-mini-cart__empty-message h3,
				#nectar-nav #search-outer input::-webkit-input-placeholder,
    			body[data-header-format="left-header"] #search-outer input::-webkit-input-placeholder',

            'property' => 'color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_font_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav #mobile-menu ul li a,
				#nectar-nav #mobile-menu ul li a .item_desc,
				#nectar-nav #mobile-menu .below-menu-items-wrap p',

            'property' => 'color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_font_color && 'simple' === self::$off_canvas_style,
        ];

        // font color
        $rules[] = [
            'selectors' =>
                'body #nectar-nav .nectar-header-text-content,
				.nectar-ajax-search-results .search-post-item,
				.nectar-ajax-search-results ul.products li.product,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header',

            'property' => 'color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_font_color,
        ];

        // background color
        $rules[] = [
            'selectors' =>
                '#nectar-nav #top .slide-out-widget-area-toggle a .lines:after,
				#nectar-nav #top .slide-out-widget-area-toggle a .lines:before,
				#nectar-nav #top .slide-out-widget-area-toggle a .lines-button:after,
				body.material.mobile #nectar-nav.transparent:not([data-permanent-transparent="1"]) header .slide-out-widget-area-toggle a .close-line,
				body.material.mobile #nectar-nav:not([data-permanent-transparent="1"]) header .slide-out-widget-area-toggle a .close-line,
				#search-outer .close-wrap .close-line,
				#nectar-nav:not(.transparent) #top .slide-out-widget-area-toggle .close-line,
				.nectar-slide-in-cart.style_slide_in_click .close-cart .close-line,
				.nectar-ajax-search-results h4 a:before',

            'property' => 'background-color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_font_color,
        ];

        // border color
        $rules[] = [
            'selectors' =>
                '#top nav > ul > .button_bordered > a:before,
				#nectar-nav:not(.transparent) #top .slide-out-widget-area-toggle .close-line',

            'property' => 'border-color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_font_color,
        ];

        // contained header
        #TODO: test this rule.
        $rules[] = [
            'selectors' =>
                '#nectar-nav.transparent #top .slide-out-widget-area-toggle .close-line',

            'property' => 'background-color',
            'declarations' => $header_font_color,
            'color' => 'header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_font_color // && nectar_is_contained_header(), TODO: this isn't available at the time of running and i'm not sure the logic is even right
        ];

        /* Font hover color **************************/
        $font_hover_color = isset($nectar_options['header-font-hover-color']) ? esc_attr($nectar_options['header-font-hover-color']) : false;

        //// Defsult header hover animation
        $rules[] = [
            'selectors' =>
                '#nectar-nav[data-lhe="default"] #top nav > ul > li > a:hover,
				#nectar-nav[data-lhe="default"] #top nav .sf-menu > .sfHover:not(#social-in-menu) > a,
				body #nectar-nav[data-lhe="default"] #top nav > ul > li > a:hover,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .sfHover:not(#social-in-menu) > a,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current-menu-item > a,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current_page_item > a .sf-sub-indicator i,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current_page_ancestor > a,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a i,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current_page_item > a,
				body #nectar-nav[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header a:hover,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header li[class*="current"] a',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color) && 'default' === self::$header_hover_animation,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header li[class*="current-"] a,
				#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header li a:active',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color) && 'default' === self::$header_hover_animation,
        ];

        //// Animated underline header hover animation
        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) .nectar-mobile-only.mobile-header .menu-title-text:after',

            'property' => 'border-color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color) && 'animated_underline' === self::$header_hover_animation,
        ];

        //// Font color !important
        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) .slide-out-widget-area-toggle a:hover i.label,
				body #nectar-nav:not(.transparent) #social-in-menu a i:after,
				body.material #nectar-nav:not(.transparent) .cart-outer:hover .cart-menu-wrap .icon-nectar-blocks-cart,
				body #top nav .sf-menu > .current_page_ancestor > a .sf-sub-indicator i,
				body #top nav .sf-menu > .current_page_item > a .sf-sub-indicator i,
				#nectar-nav #top .sf-menu > .sfHover > a .sf-sub-indicator i,
				#nectar-nav #top .sf-menu > li > a:hover .sf-sub-indicator i,
				#nectar-nav #top nav ul #search-btn a:hover span,
				#nectar-nav #top nav ul #nectar-user-account a:hover span,
				#nectar-nav #top nav ul .slide-out-widget-area-toggle a:hover span',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color),
        ];

        //// Font color
        $rules[] = [
            'selectors' =>
                '#top .sf-menu > li.nectar-regular-menu-item > a:hover > .nectar-menu-icon,
				#top .sf-menu > li.nectar-regular-menu-item.sfHover > a > .nectar-menu-icon,
				#top .sf-menu > li.nectar-regular-menu-item[class*="current-"] > a > .nectar-menu-icon,
				#nectar-nav[data-lhe="default"]:not(.transparent) .nectar-header-text-content a:hover',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color),
        ];

        //// background image
        $rules[] = [
            'selectors' =>
                '.nectar-ajax-search-results .search-post-item h5',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'header-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color),
        ];

        //// Simple OCM
        $rules[] = [
            'selectors' =>
                '#nectar-nav #mobile-menu ul li a:hover,
				#nectar-nav #mobile-menu ul li a:hover .sf-sub-indicator i,
				#nectar-nav #mobile-menu ul li a:focus,
				#nectar-nav #mobile-menu ul li a:focus .sf-sub-indicator i,
				#nectar-nav #mobile-menu ul li[class*="current"] > a,
				#nectar-nav #mobile-menu ul li[class*="current"] > a i',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color) && 'simple' === self::$off_canvas_style,
        ];

        //// background color
        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) #top nav ul .slide-out-widget-area-toggle a:hover .lines:after,
				#nectar-nav:not(.transparent) #top nav ul .slide-out-widget-area-toggle a:hover .lines:before,
				#nectar-nav:not(.transparent) #top nav ul .slide-out-widget-area-toggle a:hover .lines-button:after,
				body[data-header-format="left-header"] #nectar-nav[data-lhe="animated_underline"] #top nav > ul > li:not([class*="button_"]) > a > span:after,
				#nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after',

            'property' => 'background-color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color),
        ];

        //// border color.
        $rules[] = [
            'selectors' =>
                '#nectar-nav[data-lhe="animated_underline"] #top nav > ul > li > a .menu-title-text:after,
				body.material #nectar-nav #search-outer #search input[type="text"],
				body[data-header-format="left-header"].material #search-outer #search input[type="text"]',

            'property' => 'border-color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($font_hover_color),
        ];

        // Button BG hover effect  **************************/
        $using_button_bg = 'button_bg' === self::$header_hover_animation;

        $header_btn_bg = (isset($nectar_options['header-font-button-bg']) && ! empty($nectar_options['header-font-button-bg'])) ? $nectar_options['header-font-button-bg'] : false;
        $header_btn_bg_active = (isset($nectar_options['header-font-button-bg-active']) && ! empty($nectar_options['header-font-button-bg-active'])) ? $nectar_options['header-font-button-bg-active'] : false;
        $header_btn_text_active = (isset($nectar_options['header-font-button-text-active']) && ! empty($nectar_options['header-font-button-text-active'])) ? $nectar_options['header-font-button-text-active'] : false;

        $rules[] = [
            'selectors' => '#top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text:before',

            'property' => 'background-color',
            'declarations' => $header_btn_bg,
            'color' => 'header-font-button-bg',
            'suffix' => '',
            'conditionals' => $using_button_bg && $header_btn_bg
        ];

        $rules[] = [
            'selectors' => '#top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text:before',

            'property' => 'background-color',
            'declarations' => $header_btn_bg_active,
            'color' => 'header-font-button-bg-active',
            'suffix' => '',
            'conditionals' => $using_button_bg && $header_btn_bg_active
        ];

        $rules[] = [
            'selectors' => '#top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text,
			#nectar-nav #top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a > .sf-sub-indicator i',

            'property' => 'color',
            'declarations' => $header_btn_text_active,
            'color' => 'header-font-button-text-active',
            'suffix' => '!important',
            'conditionals' => $using_button_bg && $header_btn_text_active
        ];

        $rules[] = [
            'selectors' => '#nectar-nav[data-lhe="button_bg"] #top nav .sf-menu > .sfHover:not([class*="current"]):not(#social-in-menu) > a .menu-title-text,
			#nectar-nav[data-lhe="button_bg"] #top nav > ul > li:not([class*="current"]):not([class*="menu-item-btn-style"]) > a:hover .menu-title-text',

            'property' => 'color',
            'declarations' => $font_hover_color,
            'color' => 'header-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_button_bg && $font_hover_color
        ];

        /* Header icon color **************************/
        $header_icon_color = isset($nectar_options['header-icon-color']) ? esc_attr($nectar_options['header-icon-color']) : false;
        $rules[] = [
            'selectors' =>
                '#top .sf-menu > li.nectar-regular-menu-item > a > .nectar-menu-icon',

            'property' => 'color',
            'declarations' => $header_icon_color,
            'color' => 'header-icon-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($header_icon_color),
        ];

        /* Header dropdown bg color  **************************/
        $header_dropdown_bg = isset($nectar_options['header-dropdown-background-color']) ? esc_attr($nectar_options['header-dropdown-background-color']) : false;
        $rules[] = [
            'selectors' =>
                '#search-outer .ui-widget-content,
				body:not([data-header-format="left-header"]) #top .sf-menu li ul,
				#nectar-nav nav > ul > .megamenu > .sub-menu,
				body #nectar-nav nav > ul > .megamenu > .sub-menu > li > a,
				#nectar-nav .widget_shopping_cart .cart_list a,
				#nectar-nav .widget_shopping_cart .cart_list li,
				#nectar-nav .widget_shopping_cart_content,
				.woocommerce .cart-notification,
				#header-secondary-outer ul ul li a,
				#header-secondary-outer .sf-menu li ul',

            'property' => 'background-color',
            'declarations' => $header_dropdown_bg,
            'color' => 'header-dropdown-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_bg,
        ];

        $rules[] = [
            'selectors' =>
                ':root',
            'property' => '--nectar-nav-dropdown-bg',
            'declarations' => $header_dropdown_bg,
            'color' => 'header-dropdown-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_bg
        ];

        $rules[] = [
            'selectors' =>
                'body[data-header-format="left-header"] #nectar-nav .cart-outer .cart-notification:after',

            'property' => 'border-color',
            'declarations' => 'transparent transparent $ transparent',
            'color' => 'header-dropdown-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_bg,
        ];

        /* Header dropdown hover bg color  **************************/
        $header_dropdown_bg_hover = isset($nectar_options['header-dropdown-background-hover-color']) ? esc_attr($nectar_options['header-dropdown-background-hover-color']) : false;
        $rules[] = [
            'selectors' =>
                '#top .sf-menu li ul li a:hover,
				body #top nav .sf-menu ul .sfHover > a,
				#top .sf-menu li ul .current-menu-item > a,
				#top .sf-menu li ul .current-menu-ancestor > a,
				#nectar-nav nav > ul > .megamenu > ul ul li a:hover,
				#nectar-nav nav > ul > .megamenu > ul ul li a:focus,
				#nectar-nav nav > ul > .megamenu > ul ul .current-menu-item > a,
				#header-secondary-outer ul ul li a:hover,
				#header-secondary-outer ul ul li a:focus,
				#header-secondary-outer ul > li:not(.megamenu) ul a:hover,
				body #header-secondary-outer .sf-menu ul .sfHover > a,
				#search-outer .ui-widget-content li:hover,
				#search-outer .ui-state-hover,
				#search-outer .ui-widget-content .ui-state-hover,
				#search-outer .ui-widget-header .ui-state-hover,
				#search-outer .ui-state-focus,
				#search-outer .ui-widget-content .ui-state-focus,
				#search-outer .ui-widget-header .ui-state-focus,
				#nectar-nav #top nav > ul > li:not(.megamenu) ul a:hover,
				#nectar-nav #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav nav > ul > .megamenu > ul ul .sfHover > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul a:hover,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul ul .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-ancestor > a',

            'property' => 'background-color',
            'declarations' => $header_dropdown_bg_hover,
            'color' => 'header-dropdown-background-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_bg_hover && true !== self::$using_underline_dropdown_effect,
        ];

        $rules[] = [
            'selectors' =>
                '#search-outer .ui-widget-content li:hover,
				#search-outer .ui-widget-content .ui-state-hover,
				#search-outer .ui-widget-header .ui-state-hover,
				#search-outer .ui-widget-content .ui-state-focus,
				#search-outer .ui-widget-header .ui-state-focus',

            'property' => 'background-color',
            'declarations' => $header_dropdown_bg_hover,
            'color' => 'header-dropdown-background-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_bg_hover && true === self::$using_underline_dropdown_effect,
        ];

        /* Header dropdown hover font color  **************************/
        $header_dropdown_font_color = isset($nectar_options['header-dropdown-font-color']) ? esc_attr($nectar_options['header-dropdown-font-color']) : false;
        $rules[] = [
            'selectors' =>
                '#search-outer .ui-widget-content li a,
				#search-outer .ui-widget-content i,
				#top .sf-menu li ul li a,
				body #nectar-nav .widget_shopping_cart .cart_list a,
				#header-secondary-outer ul ul li a,
				.woocommerce .cart-notification .item-name,
				.cart-outer .cart-notification,
				#nectar-nav #top .sf-menu li ul .sf-sub-indicator i,
				#nectar-nav .widget_shopping_cart .quantity,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul a,
				#nectar-nav .cart-notification .item-name,
				#nectar-nav #top nav > ul > .nectar-woo-cart .cart-outer .widget ul a:hover,
				#nectar-nav .cart-outer .total strong,
				#nectar-nav .cart-outer .total,
				#nectar-nav ul.product_list_widget li dl dd,
				#nectar-nav ul.product_list_widget li dl dt',

            'property' => 'color',
            'declarations' => $header_dropdown_font_color,
            'color' => 'header-dropdown-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_font_color,
        ];
        $rules[] = [
            'selectors' =>
                '.sf-menu .widget-area-active .widget *,
				.sf-menu .widget-area-active:hover .widget *',

            'property' => 'color',
            'declarations' => $header_dropdown_font_color,
            'color' => 'header-dropdown-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_font_color,
        ];

        /* Header dropdown icon color  **************************/
        $header_dropdown_icon = isset($nectar_options['header-dropdown-icon-color']) ? esc_attr($nectar_options['header-dropdown-icon-color']) : false;
        $rules[] = [
            'selectors' =>
                '#top .sf-menu > li li > a > .nectar-menu-icon',

            'property' => 'color',
            'declarations' => $header_dropdown_icon,
            'color' => 'header-dropdown-icon-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_icon,
        ];

        /* Header dropdown font hover color  **************************/

        //// animated underline effect
        $header_dropdown_font_hover = isset($nectar_options['header-dropdown-font-hover-color']) ? esc_attr($nectar_options['header-dropdown-font-hover-color']) : false;

        $rules[] = [
            'selectors' =>
                '#search-outer .ui-widget-content li:hover *,
				#search-outer .ui-widget-content .ui-state-focus *,
				body nav .sf-menu ul .sfHover > a .sf-sub-indicator i,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li:hover > a',

            'property' => 'color',
            'declarations' => $header_dropdown_font_hover,
            'color' => 'header-dropdown-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_font_hover && true === self::$using_underline_dropdown_effect,
        ];

        //// default header effect
        $rules[] = [
            'selectors' =>
                '#search-outer .ui-widget-content li:hover *,
				#search-outer .ui-widget-content .ui-state-focus *,
				body #top nav .sf-menu ul .sfHover > a,
				#header-secondary-outer ul ul li:hover > a,
				#header-secondary-outer ul ul li:hover > a i,
				#header-secondary-outer ul .sfHover > a,
				body[data-dropdown-style="minimal"] #header-secondary-outer ul > li:not(.megamenu) .sfHover > a,
				body #top nav .sf-menu ul .sfHover > a .sf-sub-indicator i,
				body #top nav .sf-menu ul li:hover > a .sf-sub-indicator i,
				body #top nav .sf-menu ul li:hover > a,
				body #top nav .sf-menu ul .current-menu-item > a,
				body #top nav .sf-menu ul .current_page_item > a .sf-sub-indicator i,
				body #top nav .sf-menu ul .current_page_ancestor > a .sf-sub-indicator i,
				body #top nav .sf-menu ul .sfHover > a,
				body #top nav .sf-menu ul .current_page_ancestor > a,
				body #top nav .sf-menu ul .current-menu-ancestor > a,
				body #top nav .sf-menu ul .current_page_item > a,
				body .sf-menu ul li ul .sfHover > a .sf-sub-indicator i,
				body .sf-menu > li > a:active > .sf-sub-indicator i,
				body .sf-menu > .sfHover > a > .sf-sub-indicator i,
				body .sf-menu li ul .sfHover > a,
				#nectar-nav nav > ul > .megamenu > ul ul .current-menu-item > a,
				#nectar-nav nav > ul > .megamenu > ul > li > a:hover,
				#nectar-nav nav > ul > .megamenu > ul > .sfHover > a,
				body #nectar-nav nav > ul > .megamenu ul li:hover > a,
				#nectar-nav #top nav ul li .sfHover > a .sf-sub-indicator i,
				#nectar-nav #top nav > ul > .megamenu > ul ul li a:hover,
				#nectar-nav #top nav > ul > .megamenu > ul ul li a:focus,
				#nectar-nav #top nav > ul > .megamenu > ul ul .sfHover > a,
				#nectar-nav #header-secondary-outer nav > ul > .megamenu > ul ul li a:hover,
				#nectar-nav #header-secondary-outer nav > ul > .megamenu > ul ul li a:focus,
				#nectar-nav #header-secondary-outer nav > ul > .megamenu > ul ul .sfHover > a,
				#nectar-nav #top nav ul li li:hover > a .sf-sub-indicator i,
				#nectar-nav[data-format="left-header"] .sf-menu .sub-menu .current-menu-item > a,
				body:not([data-header-format="left-header"]) #nectar-nav #top nav > ul > .megamenu > ul ul .current-menu-item > a,
				body:not([data-header-format="left-header"]) #nectar-nav #header-secondary-outer nav > ul > .megamenu > ul ul .current-menu-item > a,
				#nectar-nav #top nav > ul > li:not(.megamenu) ul a:hover,
				body[data-dropdown-style="minimal"] #header-secondary-outer ul >li:not(.megamenu) ul a:hover,
				#nectar-nav #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) .sfHover > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul a:hover,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-ancestor > a,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > li:not(.megamenu) ul .current-menu-ancestor > a .sf-sub-indicator i,
				#nectar-nav:not([data-format="left-header"]) #top nav > ul > .megamenu ul ul .current-menu-item > a,
				#nectar-nav:not([data-format="left-header"]) #header-secondary-outer nav > ul > .megamenu ul ul .current-menu-item > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul > a:hover,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul > a:focus,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li:hover > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul:hover > a,
				#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-item.has-ul > a,
			  #nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-ancestor.has-ul > a',

            'property' => 'color',
            'declarations' => $header_dropdown_font_hover,
            'color' => 'header-dropdown-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_font_hover && true !== self::$using_underline_dropdown_effect,
        ];

        $rules[] = [
            'selectors' =>
                '#top .sf-menu > li li > a:hover > .nectar-menu-icon,
				#top .sf-menu > li li.sfHover > a > .nectar-menu-icon,
				#top .sf-menu > li li.nectar-regular-menu-item[class*="current-"] > a > .nectar-menu-icon',

            'property' => 'color',
            'declarations' => $header_dropdown_font_hover,
            'color' => 'header-dropdown-font-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_font_hover && true !== self::$using_underline_dropdown_effect,
        ];

        /* Header dropdown desc color  **************************/
        $header_dropdown_desc_font = isset($nectar_options['header-dropdown-desc-font-color']) ? $nectar_options['header-dropdown-desc-font-color'] : false;
        $rules[] = [
            'selectors' =>
                'body #nectar-nav #top nav .sf-menu ul li > a .item_desc',

            'property' => 'color',
            'declarations' => $header_dropdown_desc_font,
            'color' => 'header-dropdown-desc-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_desc_font,
        ];

        /* Header dropdown desc hover color  **************************/
        $header_dropdown_desc_font_h = isset($nectar_options['header-dropdown-desc-font-hover-color']) ? $nectar_options['header-dropdown-desc-font-hover-color'] : false;
        $rules[] = [
            'selectors' =>
                'body #nectar-nav #top nav .sf-menu ul .sfHover > a .item_desc,
				body #nectar-nav #top nav .sf-menu ul li:hover > a .item_desc,
				body #nectar-nav #top nav .sf-menu ul .current-menu-item > a .item_desc,
				body #nectar-nav #top nav .sf-menu ul .current_page_item > a .item_desc,
				body #nectar-nav #top nav .sf-menu ul .current_page_ancestor > a .item_desc,
				body #nectar-nav nav > ul > .megamenu > ul ul li a:focus .item_desc',

            'property' => 'color',
            'declarations' => $header_dropdown_desc_font_h,
            'color' => 'header-dropdown-desc-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_desc_font_h,
        ];

        /* Header dropdown heading color  **************************/
        $header_dropdown_heading_font = isset($nectar_options['header-dropdown-heading-font-color']) ? esc_attr($nectar_options['header-dropdown-heading-font-color']) : false;
        $rules[] = [
            'selectors' =>
                'body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > a,
				#nectar-nav[data-lhe="default"] nav .sf-menu .megamenu ul .current_page_ancestor > a,
				#nectar-nav[data-lhe="default"] nav .sf-menu .megamenu ul .current-menu-ancestor > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul > a',

            'property' => 'color',
            'declarations' => $header_dropdown_heading_font,
            'color' => 'header-dropdown-heading-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_heading_font,
        ];

        /* Header dropdown heading hover color  **************************/
        $header_dropdown_header_font_h = isset($nectar_options['header-dropdown-heading-font-hover-color']) ? esc_attr($nectar_options['header-dropdown-heading-font-hover-color']) : false;
        $rules[] = [
            'selectors' =>
                'body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li:hover > a,
				body:not([data-header-format="left-header"]) #nectar-nav #top nav > ul > .megamenu > ul > li:hover > a,
				body:not([data-header-format="left-header"]) #nectar-nav #header-secondary-outer nav > ul > .megamenu > ul > li:hover > a,
	 			#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu > ul > .current-menu-ancestor.menu-item-has-children > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > .current-menu-item > a,
				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul:hover > a,
   				body:not([data-header-format="left-header"]) #nectar-nav nav > ul > .megamenu > ul > li > ul > .has-ul > a:focus,
	 			#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-item.has-ul > a,
				#nectar-nav:not([data-format="left-header"]) nav > ul > .megamenu ul ul .current-menu-ancestor.has-ul > a',

            'property' => 'color',
            'declarations' => $header_dropdown_header_font_h,
            'color' => 'header-dropdown-heading-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $header_dropdown_header_font_h,
        ];

        /* Header separator color  **************************/
        $header_separator_color = isset($nectar_options['header-separator-color']) ? esc_attr($nectar_options['header-separator-color']) : false;

        $rules[] = [
            'selectors' =>
                'body #nectar-nav[data-transparent-header="true"] #top nav ul #nectar-user-account > div,
				body[data-header-color="custom"] #top nav ul #nectar-user-account > div,
				#nectar-nav:not(.transparent) .sf-menu > li ul',

            'property' => 'border-color',
            'declarations' => $header_separator_color,
            'color' => 'header-separator-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_separator_color,
        ];

        $rules[] = [
            'selectors' =>
                '#nectar-nav:not(.transparent) .sf-menu > li ul',

            'property' => 'border-top-width',
            'declarations' => '1px',
            'fallback_declarations' => 'border-top-style: solid;',
            'color' => 'header-separator-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $header_separator_color,
        ];

        /* Header with secondary **************************/
        $header_format = ( isset( $nectar_options['header_format'] ) ) ? esc_attr($nectar_options['header_format']) : 'default';
        $using_secondary = ( isset( $nectar_options['header_layout'] ) && $header_format !== 'left-header' && $nectar_options['header_layout'] == 'header_with_secondary' ) ? true : false;

        $secondary_header_bg = isset($nectar_options['secondary-header-background-color']) && ! empty($nectar_options['secondary-header-background-color']) ? esc_attr($nectar_options['secondary-header-background-color']) : false;
        //// bg color
        $rules[] = [
            'selectors' =>
                '#header-secondary-outer,
				#nectar-nav #header-secondary-outer,
				body #nectar-nav #mobile-menu .secondary-header-text',

            'property' => 'background-color',
            'declarations' => $secondary_header_bg,
            'color' => 'secondary-header-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $using_secondary && $secondary_header_bg,
        ];

        //// font color
        $secondary_header_font = isset($nectar_options['secondary-header-font-color']) && ! empty($nectar_options['secondary-header-font-color']) ? esc_attr($nectar_options['secondary-header-font-color']) : false;
        $rules[] = [
            'selectors' =>
                '#header-secondary-outer nav > ul > li > a,
				#header-secondary-outer .nectar-center-text,
				#header-secondary-outer .nectar-center-text a,
				body #header-secondary-outer nav > ul > li > a .sf-sub-indicator i,
				#header-secondary-outer #social li a i,
				#header-secondary-outer[data-lhe="animated_underline"] nav > .sf-menu >li:hover >a,
				#nectar-nav #mobile-menu .secondary-header-text p',

            'property' => 'color',
            'declarations' => $secondary_header_font,
            'color' => 'secondary-header-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $using_secondary && $secondary_header_font,
        ];

        //// font hover color
        $secondary_font_color_h = isset($nectar_options['secondary-header-font-hover-color']) ? esc_attr($nectar_options['secondary-header-font-hover-color']) : false;
        $rules[] = [
            'selectors' =>
                '#header-secondary-outer #social li a:hover i,
				#header-secondary-outer .nectar-center-text a:hover,
				#header-secondary-outer nav > ul > li:hover > a,
				#header-secondary-outer nav > ul > .current-menu-item > a,
				#header-secondary-outer nav > ul > .sfHover > a,
				#header-secondary-outer nav > ul > .sfHover > a .sf-sub-indicator i,
				#header-secondary-outer nav > ul > .current-menu-item > a .sf-sub-indicator i,
				#header-secondary-outer nav > ul > .current-menu-ancestor > a,
				#header-secondary-outer nav > ul > .current-menu-ancestor > a .sf-sub-indicator i,
				#header-secondary-outer nav > ul > li:hover > a .sf-sub-indicator i',

            'property' => 'color',
            'declarations' => $secondary_font_color_h,
            'color' => 'secondary-header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $using_secondary && $secondary_font_color_h,
        ];

        $rules[] = [
            'selectors' =>
                '#header-secondary-outer[data-lhe="animated_underline"] nav > .sf-menu >li >a .menu-title-text:after',

            'property' => 'border-color',
            'declarations' => $secondary_font_color_h,
            'color' => 'secondary-header-font-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && $using_secondary && $secondary_font_color_h,
        ];

        /* OCM BG Color  **************************/
        $ocm_bg = isset($nectar_options['header-slide-out-widget-area-background-color']) && ! empty($nectar_options['header-slide-out-widget-area-background-color']) ? esc_attr($nectar_options['header-slide-out-widget-area-background-color']) : false;
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area:not(.fullscreen-alt):not(.fullscreen),
				#slide-out-widget-area-bg.fullscreen,
                #slide-out-widget-area-bg.fullscreen-alt,
				#slide-out-widget-area-bg.fullscreen-split,
				body.material #slide-out-widget-area',

            'property' => 'background-color',
            'declarations' => $ocm_bg,
            'color' => 'header-slide-out-widget-area-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_bg,
        ];

        //// OCM BG Gradient
        $ocm_bg_2 = isset($nectar_options['header-slide-out-widget-area-background-color-2']) && ! empty($nectar_options['header-slide-out-widget-area-background-color-2']) ? esc_attr($nectar_options['header-slide-out-widget-area-background-color-2']) : false;
        $rules[] = [
            'selectors' =>
                'body.material #slide-out-widget-area.slide-out-from-right,
				#slide-out-widget-area.slide-out-from-right-hover,
				#slide-out-widget-area-bg.fullscreen,
		  		#slide-out-widget-area-bg.fullscreen-split,
				#slide-out-widget-area-bg.fullscreen-alt .bg-inner,
				body.material #slide-out-widget-area-bg.fullscreen-inline-images .nectar-ocm-image-wrap-outer',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(145deg, $, @)',
            'pattern_replace' => [
                '$' => 'header-slide-out-widget-area-background-color-2',
                '@' => 'header-slide-out-widget-area-background-color',
            ],
            'color' => 'header-slide-out-widget-area-background-color-2',
            'color_2' => 'header-slide-out-widget-area-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_bg && $ocm_bg_2,
        ];

        /* OCM Font Color  **************************/
        $ocm_font_color = isset($nectar_options['header-slide-out-widget-area-color']) && ! empty($nectar_options['header-slide-out-widget-area-color']) ? esc_attr($nectar_options['header-slide-out-widget-area-color']) : false;
        $rules[] = [
            'selectors' =>
                'body #slide-out-widget-area,
				body.material #slide-out-widget-area.slide-out-from-right .off-canvas-social-links a:hover i:before,
				body #slide-out-widget-area:not(.has-nectar-template) a,
                body #slide-out-widget-area:not(.has-nectar-template),
				body #slide-out-widget-area.fullscreen-alt .inner .widget.widget_nav_menu li a,
				body #slide-out-widget-area.fullscreen-alt .inner .off-canvas-menu-container li > a,
				#slide-out-widget-area.fullscreen-split .inner .widget.widget_nav_menu li a,
				#slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a,
				#slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a,
				body #slide-out-widget-area.fullscreen .menuwrapper li a,
				body #slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li > a,
				body #slide-out-widget-area .slide_out_area_close .icon-default-style[class^="icon-"],
				body #slide-out-widget-area .nectar-menu-label,
				body #slide-out-widget-area.fullscreen-inline-images .bottom-text,
      			.material #slide-out-widget-area.fullscreen-inline-images .wp-block-search input[type=search]',

            'property' => 'color',
            'declarations' => $ocm_font_color ,
            'color' => 'header-slide-out-widget-area-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_font_color,
        ];
        $rules[] = [
            'selectors' =>
                'body #slide-out-widget-area .nectar-menu-label:before, .slide_out_area_close .close-wrap .close-line',

            'property' => 'background-color',
            'declarations' => $ocm_font_color,
            'color' => 'header-slide-out-widget-area-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_font_color ,
        ];

        //// OCM border color.
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area .tagcloud a,
				body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container li > a:after,
				#slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a:after',

            'property' => 'border-color',
            'declarations' => $ocm_font_color,
            'color' => 'header-slide-out-widget-area-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_font_color,
        ];

        $rules[] = [
            'selectors' =>
                'body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .lines:before,
				body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .lines:after,
				body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .lines-button:after,
				body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .unhidden-line .lines:before,
				body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .unhidden-line .lines:after,
				body .slide-out-hover-icon-effect.slide-out-widget-area-toggle .unhidden-line.lines-button:after',

            'property' => 'background-color',
            'declarations' => $ocm_font_color ,
            'color' => 'header-slide-out-widget-area-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_font_color && 'slide-out-from-right-hover' === self::$off_canvas_style,
        ];

        /* OCM Button   **************************/
        $ocm_button_bg_color = isset($nectar_options['header-slide-out-widget-area-close-button-bg']) && ! empty($nectar_options['header-slide-out-widget-area-close-button-bg']) ? esc_attr($nectar_options['header-slide-out-widget-area-close-button-bg']) : false;
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area .slide_out_area_close:before',

            'property' => 'background-color',
            'declarations' => $ocm_button_bg_color,
            'color' => 'header-slide-out-widget-area-close-button-bg',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_button_bg_color,
        ];

        $ocm_button_color = isset($nectar_options['header-slide-out-widget-area-close-button']) && ! empty($nectar_options['header-slide-out-widget-area-close-button']) ? esc_attr($nectar_options['header-slide-out-widget-area-close-button']) : false;
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area .slide_out_area_close .close-wrap .close-line',

            'property' => 'background-color',
            'declarations' => $ocm_button_color,
            'color' => 'header-slide-out-widget-area-close-button',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_button_color,
        ];

        /* OCM Header Color  **************************/
        $ocm_header_color = isset($nectar_options['header-slide-out-widget-area-header-color']) && ! empty($nectar_options['header-slide-out-widget-area-header-color']) ? esc_attr($nectar_options['header-slide-out-widget-area-header-color']) : false;
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area:not(.has-nectar-template) h1,
				#slide-out-widget-area:not(.has-nectar-template) h2,
				#slide-out-widget-area:not(.has-nectar-template) h3,
				#slide-out-widget-area:not(.has-nectar-template) h4,
				#slide-out-widget-area:not(.has-nectar-template) h5,
				#slide-out-widget-area.has-nectar-template h6',

            'property' => 'color',
            'declarations' => $ocm_header_color,
            'color' => 'header-slide-out-widget-area-header-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && $ocm_header_color,
        ];

        /* OCM Header hover Color  **************************/
        $ocm_hover_font_color = isset($nectar_options['header-slide-out-widget-area-hover-color']) ? esc_attr($nectar_options['header-slide-out-widget-area-hover-color']) : false;

        $rules[] = [
            'selectors' =>
                'body #slide-out-widget-area.fullscreen-inline-images a:hover',

            'property' => 'color',
            'declarations' => $ocm_hover_font_color,
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color),
        ];

        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a span:after',

            'property' => 'border-color',
            'declarations' => $ocm_hover_font_color,
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color),
        ];

        //// font color !important
        $rules[] = [
            'selectors' =>
                'body #slide-out-widget-area[class*="fullscreen"] .current-menu-item > a,
				body #slide-out-widget-area.fullscreen li.menu-item > a:hover,
				body #slide-out-widget-area.fullscreen-split li.menu-item > a:hover,
				body #slide-out-widget-area.fullscreen-split .off-canvas-menu-container .current-menu-item > a,
				#slide-out-widget-area.slide-out-from-right-hover li.menu-item > a:hover,
				body.material #slide-out-widget-area.slide-out-from-right .off-canvas-social-links a i:after,
				body #slide-out-widget-area.slide-out-from-right li.menu-item > a:hover,
				body #slide-out-widget-area.fullscreen-alt .inner .off-canvas-menu-container li.menu-item > a:hover,
				#slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li.menu-item > a:hover,
				#slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li.current-menu-item a,
				#slide-out-widget-area.slide-out-from-right-hover.no-text-effect .inner .off-canvas-menu-container li.menu-item > a:hover,
				body #slide-out-widget-area .slide_out_area_close:hover .icon-default-style[class^="icon-"],
				body.material #slide-out-widget-area.slide-out-from-right .off-canvas-menu-container .current-menu-item > a,
				#slide-out-widget-area .widget .nectar_widget[class*="nectar_blog_posts_"] li:not(.has-img) a:hover .post-title',

            'property' => 'color',
            'declarations' => $ocm_hover_font_color,
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color),
        ];

        $rules[] = [
            'selectors' =>
                'body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container li > a:after,
				#slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a:after,
				#slide-out-widget-area .tagcloud a:hover',

            'property' => 'border-color',
            'declarations' => $ocm_hover_font_color,
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color),
        ];

        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area.fullscreen-split .widget ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
				#slide-out-widget-area.fullscreen-split .textwidget a:not(.nectar-button)',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color),
        ];

        $remove_menu_images_ocm = true;
        if( isset($nectar_options['header-slide-out-widget-area-image-display']) &&
            'default' === $nectar_options['header-slide-out-widget-area-image-display'] ) {
            $remove_menu_images_ocm = false;
        }

        //// Remove OCM menu images.
        #todo test
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area ul .menu-item .nectar-ext-menu-item .menu-title-text',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color) && $remove_menu_images_ocm,
        ];

        $rules[] = [
            'selectors' =>
                '#mobile-menu ul .menu-item .nectar-ext-menu-item .menu-title-text',

            'property' => 'background-image',
            'declarations' => 'none',
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color) && $remove_menu_images_ocm,
        ];

        //// Display OCM menu images.
        #TODO:  test
        $dropdown_hover_effect = (isset($nectar_options['header-dropdown-hover-effect'])) ? esc_attr($nectar_options['header-dropdown-hover-effect']) : 'default';
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area ul .menu-item .nectar-ext-menu-item .menu-title-text',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'header-slide-out-widget-area-hover-color',
            'suffix' => '',
            'conditionals' => $using_custom_color_scheme && ! empty($ocm_hover_font_color) && ! $remove_menu_images_ocm && $dropdown_hover_effect === 'animated_underline',
        ];

        //* Footer custom colors  **************************/
        $using_custom_footer_color_scheme = false;
        if( ! empty($nectar_options['footer-custom-color']) && $nectar_options['footer-custom-color'] === '1'  ) {
            $using_custom_footer_color_scheme = true;
        }

        $footer_bg = isset($nectar_options['footer-background-color']) ? esc_attr($nectar_options['footer-background-color']) : false;
        $rules[] = [
            'selectors' =>
                'body #footer-outer',

            'property' => 'background-color',
            'declarations' => $footer_bg,
            'color' => 'footer-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_bg,
        ];
        $footer_font_color = isset($nectar_options['footer-font-color']) ? esc_attr($nectar_options['footer-font-color']) : false;
        $rules[] = [
            'selectors' =>
                'body #footer-outer, #footer-outer a:not(.nectar-button)',

            'property' => 'color',
            'declarations' => $footer_font_color,
            'color' => 'footer-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_font_color,
        ];

        $rules[] = [
            'selectors' =>
                '#footer-outer[data-link-hover="underline"][data-custom-color="true"] #footer-widgets ul:not([class*="nectar_blog_posts"]) a:not(.tag-cloud-link):not(.nectar-button),
				#footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button) ',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, $ 0%, $ 100%)',
            'color' => 'footer-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_font_color,
        ];

        $footer_secondary_font_color = isset($nectar_options['footer-secondary-font-color']) ? esc_attr($nectar_options['footer-secondary-font-color']) : false;
        $rules[] = [
            'selectors' =>
                '#footer-outer #footer-widgets .widget h4',

            'property' => 'color',
            'declarations' => $footer_secondary_font_color,
            'color' => 'footer-secondary-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_secondary_font_color,
        ];
        $footer_copyright_bg = isset($nectar_options['footer-copyright-background-color']) ? esc_attr($nectar_options['footer-copyright-background-color']) : false;
        $rules[] = [
            'selectors' => 'body #footer-outer #copyright',
            'property' => 'background-color',
            'declarations' => $footer_copyright_bg,
            'color' => 'footer-copyright-background-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_copyright_bg,
        ];
        $footer_copyright_font_color =
            isset($nectar_options['footer-copyright-font-color'])
            ? esc_attr($nectar_options['footer-copyright-font-color'])
            : false;
        $rules[] = [
            'selectors' => 'body #footer-outer #copyright .widget h4,
				body #footer-outer #copyright li a i,
				body #footer-outer #copyright p',

            'property' => 'color',
            'declarations' => $footer_copyright_font_color,
            'color' => 'footer-copyright-font-color',
            'suffix' => '',
            'conditionals' => $using_custom_footer_color_scheme && $footer_copyright_font_color,
        ];
        $rules[] = [
            'selectors' => '#footer-outer #copyright a:not(.nectar-button)',

            'property' => 'color',
            'declarations' => $footer_secondary_font_color,
            'color' => 'footer-copyright-font-color',
            'suffix' => '!important',
            'conditionals' => $using_custom_footer_color_scheme && $footer_secondary_font_color,
        ];

        // no custom OCM colors are set, ext menu items which have been converted to basic items still need a color for the underline
        $rules[] = [
            'selectors' =>
                '#slide-out-widget-area ul .menu-item .nectar-ext-menu-item .menu-title-text',

            'property' => 'background-image',
            'declarations' => 'linear-gradient(to right, #fff 0%, #fff 100%)',
            'color' => 'header-slide-out-widget-area-close-icon-color',
            'suffix' => '',
            'conditionals' => ! $using_custom_color_scheme && $remove_menu_images_ocm,
        ];

        return $rules;

    }

    /**
    * Customizer specific rules
    * handles rules that are not output in the dynamic CSS
    * stylesheet by default - inline items, plguin styles, etc.
    *
    * @since 1.0
    *
    */
    public function customizer_specific_rules() {

        // TODO: Test all these are loading from custom / dynamic-styles.php
        $nectar_options = self::$options;

        $theme_colors = ['accent-color', 'extra-color-1', 'extra-color-2', 'extra-color-3'];

        $rules = [];

        // foreach( $theme_colors as $color ) {

        //  // BG Color.
        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar_image_with_hotspots[data-color="'.$color.'"] .nectar_hotspot,
        //          .nectar_image_with_hotspots[data-color="'.$color.'"] .nttip .tipclose span:before,
        //          .nectar_image_with_hotspots[data-color="'.$color.'"] .nttip .tipclose span:after,
        //          .nectar_icon_wrap[data-style="shadow-bg"][data-color="'.$color.'"] .nectar_icon:after,
        //          .nectar_icon_wrap[data-style="soft-bg"][data-color="'.$color.'"] .nectar_icon:before,
        //          .nectar_icon_wrap[data-style="border-animation"][data-color="'.$color.'"]:not([data-draw="true"]) .nectar_icon:hover,
        //          .nectar-post-grid-filters[data-active-color="'.$color.'"] a:before,
        //          .tabbed[data-color-scheme="'.$color.'"][data-style="default"] li:not(.cta-button) .active-tab,
        //          .tabbed[data-color-scheme="'.$color.'"][data-style="minimal_alt"] .magic-line,
        //          .tabbed[data-style="vertical_modern"][data-color-scheme="'.$color.'"] .wpb_tabs_nav li .active-tab,
        //          .nectar-scrolling-tabs[data-color-scheme="'.$color.'"] .scrolling-tab-nav .line,
        //          #nectar-content-wrap [data-stored-style="vs"] .tabbed[data-color-scheme="'.$color.'"] .wpb_tabs_nav li a:before,
        //          .tabbed[data-style*="material"][data-color-scheme="'.$color.'"] ul:after,
        //          .tabbed[data-style*="material"][data-color-scheme="'.$color.'"] ul li .active-tab,
        //          .tabbed[data-style*="minimal"][data-color-scheme="'.$color.'"] > ul li a:after,
        //          .nectar-google-map[data-nectar-marker-color="'.$color.'"] .animated-dot .middle-dot,
        //          .nectar-google-map[data-nectar-marker-color="'.$color.'"] .animated-dot div[class*="signal"],
        //          .nectar-leaflet-map[data-nectar-marker-color="'.$color.'"] .animated-dot .middle-dot,
        //          .nectar-leaflet-map[data-nectar-marker-color="'.$color.'"] .animated-dot div[class*="signal"],
        //          .pricing-table[data-style="default"] .pricing-column.highlight.'.$color.' h3,
        //          .pricing-table[data-style="flat-alternative"] .pricing-column.'.$color.'.highlight h3 .highlight-reason,
        //          .pricing-table[data-style="flat-alternative"] .pricing-column.'.$color.':before,
        //          #nectar-nav .menu-item-btn-style-button-border_'.$color.' > a:after,
        //          #nectar-nav .menu-item-btn-style-button_'.$color.' > a:before',

        //      'property' => 'background-color',
        //      'declarations' => $color,
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  //// Color.
        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar-post-grid-filters[data-active-color="'.$color.'"] a.active,
        //          .nectar-icon-list[data-icon-color="'.$color.'"][data-icon-style="border"] .content h4,
        //          .nectar-icon-list[data-icon-color="'.$color.'"][data-icon-style="border"] .list-icon-holder[data-icon_type="numerical"] span,
        //          .nectar-icon-list[data-icon-color="'.$color.'"] .nectar-icon-list-item .list-icon-holder[data-icon_type="numerical"],
        //          body.material .tabbed[data-color-scheme="'.$color.'"][data-style="minimal"]:not(.using-icons) >ul li:not(.cta-button) a:hover,
        //          body.material .tabbed[data-color-scheme="'.$color.'"][data-style="minimal"]:not(.using-icons) >ul li:not(.cta-button) .active-tab,
        //          body .tabbed[data-style*="material"][data-color-scheme="'.$color.'"] .wpb_tabs_nav li a:not(.active-tab):hover,
        //          .pricing-table[data-style="flat-alternative"] .pricing-column.highlight.'.$color.' h3,
        //          .pricing-table[data-style="flat-alternative"] .pricing-column.'.$color.' h4,
        //          .pricing-table[data-style="flat-alternative"] .pricing-column.'.$color.' .interval',

        //      'property' => 'color',
        //      'declarations' => $color,
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  //// Border color.
        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar_icon_wrap[data-style="border-animation"][data-color="'.$color.'"]:not([data-draw="true"]) .nectar_icon,
        //          .nectar_icon_wrap[data-style="border-animation"][data-color="'.$color.'"][data-draw="true"]:hover .nectar_icon,
        //          .nectar_icon_wrap[data-style="border-basic"][data-color="'.$color.'"] .nectar_icon,
        //          .nectar-post-grid-filters[data-active-color="'.$color.'"] a.active:after,
        //          .tabbed[data-color-scheme="'.$color.'"][data-style="default"] li:not(.cta-button) .active-tab',

        //      'property' => 'border-color',
        //      'declarations' => $color,
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  //// MISC.
        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar_icon_wrap[data-style="shadow-bg"][data-color="'.$color.'"] .nectar_icon:before',

        //      'property' => 'box-shadow',
        //      'declarations' => '0px 15px 28px $',
        //      'color' => $color,
        //      'suffix' => '',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style="minimal_flexible"][data-color-scheme="'.$color.'"] .wpb_tabs_nav > li a:before',

        //      'property' => 'box-shadow',
        //      'declarations' => '0px 8px 22px $',
        //      'color' => $color,
        //      'suffix' => '',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style*="material"][data-color-scheme="'.$color.'"] ul li .active-tab:after',

        //      'property' => 'box-shadow',
        //      'declarations' => '0px 18px 50px $',
        //      'color' => $color,
        //      'suffix' => '',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar-leaflet-map[data-nectar-marker-color="'.$color.'"] .nectar-leaflet-pin',

        //      'property' => 'border',
        //      'declarations' => '10px solid $',
        //      'color' => $color,
        //      'suffix' => '',
        //  );

        // }

        // // Gradient colors
        // $gradient_colors = array('extra-color-gradient', 'extra-color-gradient-2');

        // foreach( $gradient_colors as $color ) {

        //  $selector = $color;

        //  if( $color === 'extra-color-gradient' ) {
        //      $selector = 'extra-color-gradient-1';
        //  }

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style*="default"][data-color-scheme="'.$selector.'"] ul li a:before,
        //          .tabbed[data-style*="material"][data-color-scheme="'.$selector.'"] ul li a:before,
        //          .tabbed[data-color-scheme="'.$selector.'"][data-style="minimal_alt"] .magic-line,
        //          .nectar-scrolling-tabs[data-color-scheme="'.$selector.'"] .scrolling-tab-nav .line,
        //          .tabbed[data-style*="vertical"][data-color-scheme="'.$selector.'"] ul li a:before,
        //          .tabbed[data-style*="minimal"][data-color-scheme="'.$selector.'"] > ul li a:after,
        //          .nectar-icon-list[data-icon-color="'.$selector.'"] .list-icon-holder[data-icon_type="numerical"] span,
        //          .nectar-gradient-text[data-color="'.$selector.'"][data-direction="diagonal"] *,
        //          .nectar_icon_wrap[data-style="shadow-bg"][data-color="'.$selector.'"] .nectar_icon:after,
        //          .nectar_icon_wrap[data-style="soft-bg"][data-color="'.$selector.'"] .nectar_icon:before,
        //          .nectar_icon_wrap[data-style="border-animation"][data-color="'.$selector.'"]:before',

        //      'property' => 'background-image',
        //      'fallback_declarations' => '',
        //      'declarations' => 'linear-gradient(to bottom right, $, $)',
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style*="material"][data-color-scheme="'.$selector.'"] ul:after',

        //      'property' => 'background-color',
        //      'fallback_declarations' => '',
        //      'declarations' => '$',
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style*="material"][data-color-scheme="'.$selector.'"] ul li .active-tab:after',

        //      'property' => 'box-shadow',
        //      'fallback_declarations' => '',
        //      'declarations' => '0px 18px 50px $',
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.tabbed[data-style="minimal_flexible"][data-color-scheme="'.$selector.'"] .wpb_tabs_nav > li a:before',

        //      'property' => 'box-shadow',
        //      'fallback_declarations' => '',
        //      'declarations' => '0px 8px 22px $',
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '.nectar_icon_wrap[data-style="shadow-bg"][data-color="'.$selector.'"] .nectar_icon:before',

        //      'property' => 'box-shadow',
        //      'fallback_declarations' => '',
        //      'declarations' => '0px 15px 28px $',
        //      'color' => $color,
        //      'suffix' => '!important',
        //  );

        // }

        // Blog header overlay.
        // $blog_header_overlay = isset(  $nectar_options['std_blog_header_overlay_color'] ) ? esc_attr( $nectar_options['std_blog_header_overlay_color']) : false;
        // $rules[] = array(
        //  'selectors' =>
        //      '.single-post #page-header-bg[data-post-hs="default"] .page-header-bg-image-wrap .page-header-bg-image:after,
        //      .single-post #page-header-bg[data-post-hs="fullscreen"] .page-header-bg-image-wrap .page-header-bg-image:after',

        //  'property' => 'background-color',
        //  'declarations' => $blog_header_overlay,
        //  'color' => 'std_blog_header_overlay_color',
        //  'suffix' => '',
        //  'conditionals' => $blog_header_overlay
        // );
        // $rules[] = array(
        //  'selectors' =>
        //      '.single-post #page-header-bg[data-post-hs="default"] .page-header-bg-image-wrap .page-header-bg-image:after,
        //      .single-post #page-header-bg[data-post-hs="fullscreen"] .page-header-bg-image-wrap .page-header-bg-image:after',

        //  'property' => 'opacity',
        //  'declarations' => isset($nectar_options['std_blog_header_overlay_opacity']) ? $nectar_options['std_blog_header_overlay_opacity'] : 0,
        //  'color' => 'std_blog_header_overlay_opacity',
        //  'suffix' => '',
        //  'conditionals' => isset($nectar_options['std_blog_header_overlay_opacity']) && !empty($nectar_options['std_blog_header_overlay_opacity'])
        // );

        // $rules[] = array(
        //  'selectors' =>
        //      '.single-post #page-header-bg[data-post-hs="default_minimal"] .page-header-bg-image:after',

        //  'property' => 'background-color',
        //  'declarations' => isset($nectar_options['default_minimal_overlay_color']) ? esc_attr($nectar_options['default_minimal_overlay_color']) : '#000000',
        //  'color' => 'default_minimal_overlay_color',
        //  'suffix' => '',
        // );
        // $rules[] = array(
        //  'selectors' =>
        //      '.single-post #page-header-bg[data-post-hs="default_minimal"] .page-header-bg-image:after',

        //  'property' => 'opacity',
        //  'declarations' => isset($nectar_options['default_minimal_overlay_opacity']) ? esc_attr($nectar_options['default_minimal_overlay_opacity']) : '0',
        //  'color' => 'default_minimal_overlay_opacity',
        //  'suffix' => '',
        //  'conditionals' => isset($nectar_options['default_minimal_overlay_opacity']) && !empty($nectar_options['default_minimal_overlay_opacity'])
        // );

        // if (isset($nectar_options['default_minimal_text_color']) && !empty($nectar_options['default_minimal_text_color'])) {
        //  $rules[] = array(
        //      'selectors' =>
        //          '#nectar-content-wrap [data-post-hs="default_minimal"] h1,
        //          #nectar-content-wrap [data-post-hs="default_minimal"] .subheader,
        //          #nectar-content-wrap [data-post-hs="default_minimal"] span,
        //          #nectar-content-wrap [data-post-hs="default_minimal"] #single-below-header a:hover,
        //          #nectar-content-wrap [data-post-hs="default_minimal"] #single-below-header a:focus,
        //          #nectar-content-wrap [data-post-hs="default_minimal"] .inner-wrap > a:not(:hover)',

        //      'property' => 'color',
        //      'declarations' => $nectar_options['default_minimal_text_color'],
        //      'color' => 'default_minimal_text_color',
        //      'suffix' => '!important',
        //  );

        //  $rules[] = array(
        //      'selectors' =>
        //          '[data-post-hs="default_minimal"] .inner-wrap > a:not(:hover), [data-post-hs="default_minimal"] #single-below-header > span',

        //      'property' => 'border-color',
        //      'declarations' => $nectar_options['default_minimal_text_color'],
        //      'color' => 'default_minimal_text_color',
        //      'suffix' => '!important',
        //  );
        // }

        $product_archive_bg_color = isset($nectar_options['product_archive_bg_color']) ? $nectar_options['product_archive_bg_color'] : '#ffffff';
        // WooCommerce
        //if ( isset($nectar_options['product_archive_bg_color']) ) {
            $rules[] = [
                'selectors' =>
                    '.post-type-archive-product.woocommerce .container-wrap,
                    .tax-product_cat.woocommerce .container-wrap',

                'property' => 'background-color',
                'declarations' => $product_archive_bg_color,
                'color' => 'product_archive_bg_color',
                'suffix' => '',
            ];
        //}

        // wp pages
        //if (isset($nectar_options['search-results-header-bg-color'])) {
            $search_results_header_bg_color = isset($nectar_options['search-results-header-bg-color']) ? $nectar_options['search-results-header-bg-color'] : '#f8f8f8';
            $rules[] = [
                'selectors' =>
                    '.search-results #page-header-bg',

                'property' => 'background-color',
                'declarations' => $search_results_header_bg_color,
                'color' => 'search-results-header-bg-color',
                'suffix' => '!important',
            ];
        //}
        //if (isset($nectar_options['search-results-header-font-color'])) {
            $search_results_header_font_color = isset($nectar_options['search-results-header-font-color']) ? $nectar_options['search-results-header-font-color'] : '#000000';
            $rules[] = [
                'selectors' =>
                    '.search-results #page-header-bg h1, .search-results #page-header-bg .result-num',

                'property' => 'color',
                'declarations' => $search_results_header_font_color,
                'color' => 'search-results-header-font-color',
                'suffix' => '!important',
            ];
        //}

        return $rules;
    }
}

function Nectar_Dynamic_Colors() {
    return Nectar_Dynamic_Colors::get_instance();
}