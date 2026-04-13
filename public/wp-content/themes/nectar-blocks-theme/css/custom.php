<?php

/**
 * Creates various style/sizing calculations dynamically from theme options.
 *
 * The styles generated from here will either be contained in nectar-blocks/css/nectar-blocks-dynamic-styles.css
 * or output directly in the head, depending on if the server writing permission is set for the css directory.
 *
 * @version 1.0
 */

 /*---------------------------------------------------------------------------

 [Table Of Contents]

 0.5 Temp Options moved from dynamic-colors.php
 1. Header Navigation
    1.1. Header Navigation Sizing
    1.2. Header Navigation Dropdown
    1.3. Mobile Logo Height
    1.4. Custom Mobile Breakpoint
    1.5. Megamenu Removes Transparent
    1.6. Dark Color Scheme
    1.7. Social Icons
    1.8. AJAX Search
    1.9. Button Styling
    1.10. Text Content
    1.11. Mobile Layout
    1.12. Search Core
    1.13. Ext Search
    1.14. Search Typography
    1.15. Shadows
    1.16. Animations
    1.17. OCM Alignment
    1.18. Off Canvas Menu Icon Width
    1.19. Background Blur
    1.20. Header Size
    1.21. Header Border
2. Link Hover Effects
    2.1 Skip to content focus
    2.2. Header Navigation Hover Effects
    2.3. Global Hover Effects
    2.4. General links
3. Nectar Slider Font Sizing
4. Header Navigation Transparent Coloring
5. Extended Responsive Theme Option
6. Form styling
   6.1. Fancy Selects
   6.2. Input Coloring
   6.3. Input Padding
   6.4. Submit Button Sizing
   6.5  Minimal Style
   6.6. Input Sizing
   6.7. Input Styling Output
7. Blog
    7.1. Blog Single Width
    7.2. Blog Archives
    7.3. Blog Comments
    7.4. Category Coloring
8. Page Transitions
9. Page Header
10. Button Roundness
11. Call To Action
12. Body Border
13. Mobile Animations
14. Footer
  14.1 Core Footer
  14.2. Reveal Effect
  14.3. Footer Layouts
  14.4. To Top Button
  14.5  Global Section
15. Column Spacing
16. Off Canvas Menu
  16.1 Font sizing
  16.2 Overlay
  16.3 Styling
17. Animations
18. Third Party
  18.1. WooCommerce Theme Skin
  18.2. WooCommerce AJAX Cart
  18.3. WooCommerce Quantity Style
  18.4. WooCommerce Sidebar Toggles
  18.5. WooCommerce Single Gallery Variants
  18.6. WooCommerce Single Gallery Width
  18.7. WooCommerce Add to Cart Style
  18.8. WooCommerce Filters
  18.9. WooCommerce Archive header
  18.10. WooCommerce Product Styles
  18.11. WooCommerce Product Style Mods
  18.12. WooCommerce Related/Upsell Carousel
  18.13. WooCommerce lightbox Gallery Background
  18.14. WooCommerce Review Style
  18.15. WooCommerce Variation Dropdown Style
  18.16. MISC Third Party
  18.17. Product Typography
  18.19. Full Width Layout
  18.20. Button Font Styling.
---------------------------------------------------------------------------*/

NectarThemeManager::setup();

$nectar_options = get_nectar_theme_options();
$headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';
$theme_skin = NectarThemeManager::$skin;

/*-------------------------------------------------------------------------*/
/* 05. Temp Options from Kirki
/*-------------------------------------------------------------------------*/
$GRAD_DEFAULT = [
  'from' => 'transparent',
  'to' => 'transparent',
];
$gradients = ['extra-color-gradient', 'extra-color-gradient-2'];
foreach ($gradients as $gradient) {
  if (isset($nectar_options[$gradient])) {

    $saved_grad = $nectar_options[$gradient];

    if (! isset($saved_grad['from']) ) {
      $saved_grad['from'] = $GRAD_DEFAULT['from'];
    }
    if (! isset($saved_grad['to']) ) {
      $saved_grad['to'] = $GRAD_DEFAULT['to'];
    }

    echo '.nectar-bg-' . $gradient . ',
          #nectar-content-wrap .nectar-bg-pseudo-' . $gradient . ':before {
      background: linear-gradient(90deg, ' . $saved_grad['from'] . ' 0%, ' . $saved_grad['to'] . ' 100%);
    }';
  }
}

/*-------------------------------------------------------------------------*/
/* 1. Header Navigation
/*-------------------------------------------------------------------------*/

  $header_starting_color = (empty($nectar_options['header-starting-color'])) ? '#ffffff' : $nectar_options['header-starting-color'];

  $header_starting_dark_color = (isset($nectar_options['header-transparent-dark-color'])) ? $nectar_options['header-transparent-dark-color'] : '#000000';

  if ( ! defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
    $global_font_color = (isset($nectar_options['overall-font-color']) && ! empty($nectar_options['overall-font-color']) ) ? $nectar_options['overall-font-color'] : '#000000';
  } else {
    // use Nectarblocks global color.
    $global_font_color = 'var(--body-color, var(--dark))';
  }
  $global_bg_color = (isset($nectar_options['overall-bg-color']) && ! empty($nectar_options['overall-bg-color']) ) ? $nectar_options['overall-bg-color'] : '#ffffff';
  $boxed_layout = (isset($nectar_options['boxed_layout'])) ? $nectar_options['boxed_layout'] : false;
  $logo_height = 0;

    // Using image based logo.
    if( ! empty( $nectar_options['use-logo'] ) ) {
            $logo_height = ( ! empty($nectar_options['logo-height']) ) ? intval($nectar_options['logo-height']) : 30;
    }
  // Text logo handled in nectar-nav-spacer.php

    $mobile_logo_height = (! empty($nectar_options['use-logo']) && ! empty($nectar_options['mobile-logo-height'])) ? intval($nectar_options['mobile-logo-height']) : 24;
  $mobile_header_layout = (isset($nectar_options['mobile-menu-layout']) && ! empty($nectar_options['mobile-menu-layout'])) ? $nectar_options['mobile-menu-layout'] : 'default';
    $header_padding = (! empty($nectar_options['header-padding'])) ? intval($nectar_options['header-padding']) : 28;
    $nav_font_size = (! empty($nectar_options['navigation_font_family']['font-size']) && $nectar_options['navigation_font_family']['font-size'] != '-') ? intval(substr($nectar_options['navigation_font_family']['font-size'], 0, -2) * 1.4 ) : 20;
  $nav_font_line_height = (! empty($nectar_options['navigation_font_family']['line-height']) && $nectar_options['navigation_font_family']['line-height'] != '-') ? intval( substr($nectar_options['navigation_font_family']['line-height'], 0, -2) ) : $nav_font_size;
    $dd_indicator_height = (! empty($nectar_options['use-custom-fonts']) && $nectar_options['use-custom-fonts'] == 1 && ! empty($nectar_options['navigation_font_size']) && $nectar_options['navigation_font_size'] != '-') ? intval(substr($nectar_options['navigation_font_size'], 0, -2)) - 1 : 20;
    $headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';
    $shrinkNum = (! empty($nectar_options['header-resize-on-scroll-shrink-num'])) ? intval($nectar_options['header-resize-on-scroll-shrink-num']) : 6;
    $perm_trans = (! empty($nectar_options['header-permanent-transparent'])) ? $nectar_options['header-permanent-transparent'] : 'false';
  $headerResize = (! empty($nectar_options['header-resize-on-scroll']) && $headerFormat != 'centered-menu-bottom-bar') ? $nectar_options['header-resize-on-scroll'] : '0';
    $hideHeaderUntilNeeded = (! empty($nectar_options['header-hide-until-needed']) && $headerFormat != 'centered-menu-bottom-bar') ? $nectar_options['header-hide-until-needed'] : '0';
    $body_border = (! empty($nectar_options['body-border'])) ? $nectar_options['body-border'] : 'off';
    $headerRemoveStickiness = (! empty($nectar_options['header-remove-fixed'])) ? $nectar_options['header-remove-fixed'] : '0';
    $using_secondary = (! empty($nectar_options['header_layout'])) ? $nectar_options['header_layout'] : ' ';
    $menu_item_spacing = (! empty($nectar_options['header-menu-item-spacing'])) ? esc_attr($nectar_options['header-menu-item-spacing']) : '10';
  $side_widget_class = (! empty($nectar_options['header-slide-out-widget-area-style'] ) ) ? $nectar_options['header-slide-out-widget-area-style'] : 'slide-out-from-right';
  $side_widget_area = (! empty($nectar_options['header-slide-out-widget-area'] ) && $headerFormat != 'left-header') ? $nectar_options['header-slide-out-widget-area'] : 'off';
  $centered_menu_bb_sep = (isset($nectar_options['centered-menu-bottom-bar-separator']) && ! empty($nectar_options['centered-menu-bottom-bar-separator'])) ? $nectar_options['centered-menu-bottom-bar-separator'] : '0';
  $centered_menu_align = (isset($nectar_options['centered-menu-bottom-bar-alignment']) && ! empty($nectar_options['centered-menu-bottom-bar-alignment'])) ? $nectar_options['centered-menu-bottom-bar-alignment'] : 'center';
  $header_fullwidth = (! empty($nectar_options['header-fullwidth'])) ? $nectar_options['header-fullwidth'] : '0';
    $header_fullwidth_pad = (! empty($nectar_options['header-fullwidth-padding'])) ? $nectar_options['header-fullwidth-padding'] : 28;
  $header_hover_effect = (isset($nectar_options['header-hover-effect']) && ! empty($nectar_options['header-hover-effect'])) ? $nectar_options['header-hover-effect'] : 'default';

  $button_width = 1; // in em
  $button_sizing = isset($nectar_options['header-hover-effect-button-bg-size']) ? $nectar_options['header-hover-effect-button-bg-size'] : 'medium';
  if( 'small' === $button_sizing ) {
    $button_width = 0.8;
  } else if( 'large' === $button_sizing ) {
    $button_width = 1.4;
  }
  // Button bg modifies the link line height calculation.
  $nav_item_extra_height = 0;
  if( 'button_bg' === $header_hover_effect && 'left-header' !== $headerFormat ) {
    $nav_item_extra_height = ( ($button_width / 1.5) * $nav_font_size );
  }

  if( class_exists('NectarElDynamicStyles') ) {
    $header_fullwidth_pad = NectarElDynamicStyles::percent_unit_type($header_fullwidth_pad, false);
  } else {
    $header_fullwidth_pad = intval($header_fullwidth_pad) . 'px';
  }

  $user_set_side_widget_area = $side_widget_area;

  if( isset($nectar_options['header-resize-on-scroll-shrink-num']) && '0' === $nectar_options['header-resize-on-scroll-shrink-num'] ) {
        $shrinkNum = 0;
    }

    // Options that disable the header resize effect.
    if( $hideHeaderUntilNeeded === '1' || $body_border === '1' || $headerFormat === 'left-header' || $headerRemoveStickiness === '1') {
        $headerResize = '0';
    }

    // Larger secondary header with material theme skin.
    if( $theme_skin === 'material' ) {
        $extra_secondary_height = ($using_secondary === 'header_with_secondary') ? 42 : 0;
    } else {
        $extra_secondary_height = ($using_secondary === 'header_with_secondary') ? 34 : 0;
    }

    if( $headerFormat === 'centered-menu-bottom-bar') {
    $sep_height = ($headerFormat === 'centered-menu-bottom-bar' && '1' === $centered_menu_bb_sep ) ? $header_padding : 0;
        $header_space = $logo_height + ($header_padding * 3) + $nav_font_line_height + $extra_secondary_height + $sep_height + $nav_item_extra_height;
    }
    else if( $headerFormat === 'centered-menu-under-logo') {
        $header_space = $logo_height + ($header_padding * 2) + 20 + $nav_font_line_height + $extra_secondary_height + $nav_item_extra_height;
    }
    else {
        $header_space = $logo_height + ($header_padding * 2) + $extra_secondary_height;
    }

    $page_transition_bg = (! empty($nectar_options['transition-bg-color'])) ? $nectar_options['transition-bg-color'] : '#ffffff';
    $page_transition_bg_2 = (! empty($nectar_options['transition-bg-color-2'])) ? $nectar_options['transition-bg-color-2'] : $page_transition_bg;

    $headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';
    $small_matieral_header_space = (($header_padding / 1.8) * 2) + $logo_height - $shrinkNum;

  $menu_label = false;
  if( ! empty( $nectar_options['header-menu-label'] ) && $nectar_options['header-menu-label'] === '1' ) {
    $menu_label = true;
  }

  // Ext search.
  $ajax_search = ( ! empty( $nectar_options['header-disable-ajax-search'] ) && $nectar_options['header-disable-ajax-search'] === '1' ) ? 'no' : 'yes';
  $header_search = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? 'false' : 'true';
  $ext_search_active = false;

  if( 'material' === $theme_skin && 'yes' === $ajax_search && 'true' === $header_search ) {
    $ext_search_active = true;
  }

  echo ':root {
    --nectar-nav-item-gap: ' . esc_attr($menu_item_spacing) . 'px;
  }';

  /*-------------------------------------------------------------------------*/
  /* 1.1. Header Navigation Sizing
  /*-------------------------------------------------------------------------*/

    $material_header_space = $logo_height + ($header_padding * 2);

      if( $headerFormat !== 'left-header' ) {

            // Desktop header navigation sizing.
          echo '
		  @media only screen and (min-width: 1025px) {

				#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 #logo {
					margin-top: -' . $header_padding / 2 . 'px;
				}

				#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 nav >ul >li:not(#social-in-menu):not(#nectar-user-account):not(#search-btn):not(.slide-out-widget-area-toggle) > a {
					margin-bottom: ' . $header_padding . 'px;
				}

				#nectar-nav #logo,
				#nectar-nav .logo-spacing {
					margin-top: ' . $header_padding . 'px;
					margin-bottom: ' . $header_padding . 'px;
					position: relative;
				}

				 #nectar-nav.small-nav #logo,
				 #nectar-nav.small-nav .logo-spacing {
						margin-top: ' . ($header_padding / 1.8) . 'px;
						margin-bottom: ' . ($header_padding / 1.8) . 'px;

				}

				#nectar-nav.small-nav #logo img,
				#nectar-nav.small-nav .logo-spacing img {
						height: ' . ($logo_height - $shrinkNum) . 'px;
				}

		  }';

          echo '#nectar-nav #logo img,
			#nectar-nav .logo-spacing img {
				height: ' . $logo_height . 'px;
			}';

    // Centered menu bottom bar
    if( $headerFormat === 'centered-menu-bottom-bar' ) {

      // Bottom bar separator
      if( '1' === $centered_menu_bb_sep ) {
        echo '@media only screen and (min-width: 1025px) {
         #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3 {
           margin-bottom: ' . ($header_padding) . 'px;
         }
         #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
           margin-left: -50vw;
           left: 50%;
           bottom: 0;
           width: 100vw;
           height: 1px;
           content: "";
           z-index: 100;
           background-color: rgba(0,0,0,0.08);
           position: absolute;
         }
         #nectar-nav[data-format="centered-menu-bottom-bar"].transparent.dark-slide #top .span_3:before {
           background-color: rgba(0,0,0,0.1);
         }
         body.material #nectar-nav[data-box-shadow="large-line"].transparent:not(.scrolled-down):not(.fixed-menu) {
           box-shadow: 0 1px 0 rgba(255,255,255,0.25), 0 18px 40px rgba(0,0,0,0.0);
         }
         body.material #nectar-nav[data-box-shadow="large-line"].transparent.dark-slide:not(.scrolled-down):not(.fixed-menu) {
           box-shadow: 0 1px 0 rgba(0,0,0,0.1), 0 18px 40px rgba(0,0,0,0.0);
         }

         #nectar-nav[data-format="centered-menu-bottom-bar"].transparent:not(.dark-slide) #top .span_3:before,
         body[data-header-color="dark"] #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
           background-color: rgba(255,255,255,0.25);
         }
        }';
        if( ! empty($nectar_options['header-color']) &&
            $nectar_options['header-color'] === 'custom' &&
            ! empty($nectar_options['header-separator-color']) ) {

          echo '#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
            background-color: ' . esc_attr($nectar_options['header-separator-color']) . ';
          }';

        }
      }

      // Left alignment specific.
      if( 'left' === $centered_menu_align ) {
        echo '#nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"]  #top .span_9 ul.buttons > li {
          margin-top: -' . ($header_padding) . 'px;
          height: calc(100% + ' . $header_padding . 'px);
        }';
      }
    } // centered menu bottom bar end

    // Full width header left/right custom padding
    if( 'left-header' !== $headerFormat && '1' === $header_fullwidth && ! empty($header_fullwidth_pad) && '28px' !== $header_fullwidth_pad ) {
     echo '
     @media only screen and (min-width: 1025px) {
      :root {
        --header-fullwidth-padding: ' . esc_attr($header_fullwidth_pad) . ';
      }
       #nectar-nav[data-full-width="true"] header > .container, body #nectar-nav[data-full-width="true"] #search-outer .container {
         padding: 0 ' . esc_attr($header_fullwidth_pad) . ';
       }
     }
     #nectar-nav[data-full-width="true"] #search-outer #search #close {
      right: ' . esc_attr($header_fullwidth_pad) . '!important;
     }
     #nectar-nav[data-full-width="true"] #search-outer #search #close a {
      right: 8px!important;
     }';
    }

    if( isset($nectar_options['header-text-widget']) && ! empty($nectar_options['header-text-widget']) ) {
        echo 'body[data-header-search="false"][data-full-width-header="false"] #nectar-nav[data-lhe="animated_underline"][data-format="default"][data-cart="false"] #top nav >ul >li:last-child > a,
        #nectar-nav[data-menu-bottom-bar-align="left"][data-format="centered-menu-bottom-bar"][data-lhe="animated_underline"] #top .span_3 nav.right-side ul > li:last-child > a,
        #nectar-nav[data-menu-bottom-bar-align="left_t_center_b"][data-format="centered-menu-bottom-bar"][data-lhe="animated_underline"] #top .span_3 nav.right-side ul > li:last-child > a {
          margin-right: ' . $menu_item_spacing . 'px;
        }
        #nectar-nav[data-menu-bottom-bar-align="left_t_center_b"][data-format="centered-menu-bottom-bar"][data-lhe="default"] #top .span_3 nav.right-side ul > li:not([class*="button_"]):last-child > a,
        #nectar-nav[data-menu-bottom-bar-align="left"][data-format="centered-menu-bottom-bar"][data-lhe="default"] #top .span_3 nav.right-side ul > li:not([class*="button_"]):last-child > a {
          padding-right: ' . $menu_item_spacing . 'px;
        }';
    }

         echo '
		 #nectar-nav[data-lhe="animated_underline"] #top nav > ul > li > a,
		 #top nav > ul > li[class*="button_solid_color"] > a,
     #nectar-nav[data-lhe="default"] #top nav > ul > li[class*="menu-item-btn-style"] > a,
		 body #nectar-nav[data-lhe="default"] #top nav .sf-menu > li[class*="button_solid_color"] > a:hover,
		 #nectar-nav[data-lhe="animated_underline"] #top nav > .sf-menu > li[class*="button_bordered"] > a,
		 #top nav > ul > li[class*="button_bordered"] > a,
		 body #nectar-nav.transparent #top nav > ul > li[class*="button_bordered"] > a,
		 body #nectar-nav[data-lhe="default"] #top nav .sf-menu > li[class*="button_bordered"] > a:hover,
		 body #nectar-nav.transparent #top nav > ul > li[class*="button_solid_color"] > a,
		 #nectar-nav[data-lhe="animated_underline"] #top nav > ul > li[class*="button_solid_color"] > a {
			 margin-left: ' . $menu_item_spacing . 'px;
			 margin-right: ' . $menu_item_spacing . 'px;
		 }

		 #nectar-nav[data-lhe="default"] #top nav > ul > li > a,
     #nectar-nav .nectar-header-text-content,
     body[data-header-search="false"][data-full-width-header="false"] #nectar-nav[data-lhe="animated_underline"][data-format="default"][data-cart="false"] .nectar-header-text-content {
			 padding-left: ' . $menu_item_spacing . 'px;
			 padding-right: ' . $menu_item_spacing . 'px;
		 }
      #nectar-nav[data-lhe="default"] #top nav > ul > li[class*="menu-item-btn-style"] > a {
        padding-left: 0;
        padding-right: 0;
      }';

     if( 'left' !== $centered_menu_align ) {
         echo '#nectar-nav[data-lhe="animated_underline"][data-condense="true"][data-format="centered-menu-bottom-bar"].fixed-menu #top nav > ul > li > a {
  			 margin-left: ' . floor($menu_item_spacing / 1.3) . 'px;
  			 margin-right: ' . floor($menu_item_spacing / 1.3) . 'px;
  		 }

  		 #nectar-nav[data-lhe="default"][data-condense="true"][data-format="centered-menu-bottom-bar"].fixed-menu #top nav > ul > li > a {
  			 padding-left: ' . floor($menu_item_spacing / 1.3) . 'px;
  			 padding-right: ' . floor($menu_item_spacing / 1.3) . 'px;
  		 }';
     }

     // No margin on last li for full width header layout
     if( 'default' === $headerFormat && '1' === $header_fullwidth ) {
      echo '@media only screen and (min-width: 1025px) {
        body.material #nectar-nav #top .span_9 nav > ul.sf-menu > li:last-child > a:not(:has(.sf-sub-indicator)) {
          margin-right: 0;
        }
      }';
     }

     /*-------------------------------------------------------------------------*/
     /* 1.2. Header Navigation Dropdown
     /*-------------------------------------------------------------------------*/

     // Arrows.
         $dropdown_arrows = (! empty($nectar_options['header-dropdown-arrows']) && $headerFormat !== 'left-header' ) ? $nectar_options['header-dropdown-arrows'] : 'inherit';

         if( $dropdown_arrows === 'show' && intval($menu_item_spacing) > 7 ) {

             echo '#nectar-nav #top .sf-menu > .sf-with-ul > a {
					 padding-right: ' . intval(intval($menu_item_spacing) + 10) . 'px!important;
				 }
	 			 #nectar-nav[data-lhe="animated_underline"] #top .sf-menu > .sf-with-ul:not([class*="button"]) > a {
					 padding-right: 10px!important;
				 }
         #nectar-nav[data-lhe="animated_underline"] #top .sf-menu > .sf-with-ul[class*="button"] > a {
					 padding-right: 26px!important;
				 }
				 #nectar-nav[data-lhe="default"][data-condense="true"][data-format="centered-menu-bottom-bar"]:not([data-menu-bottom-bar-align="left"]).fixed-menu #top nav .sf-menu > .sf-with-ul > a {
					 padding-right: ' . intval( floor($menu_item_spacing / 1.3) + 10) . 'px!important;
				 }';

         }

     // Dropdown Positioning.
     if( isset( $nectar_options['header-dropdown-position'] ) &&
     ! empty( $nectar_options['header-dropdown-position'] ) &&
     'bottom-of-menu-item' === $nectar_options['header-dropdown-position'] ) {

       echo '#nectar-nav #top .sf-menu > li > ul,
       body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart,
       body #nectar-nav #top .cart-notification {
         top: 85%;
         transition: margin 0.2s ease;
       }';

       if( 'button_bg' === $header_hover_effect ) {

        $button_extra_spacing = 28;
        $button_bg_sizing = isset($nectar_options['header-hover-effect-button-bg-size']) ? $nectar_options['header-hover-effect-button-bg-size'] : 'medium';
        if( $button_bg_sizing == 'large' || $button_bg_sizing == 'medium' ) {
          $button_extra_spacing = 35;
        }
          echo '#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 .sf-menu > li > ul,
          #nectar-nav[data-format="centered-menu-under-logo"] #top .sf-menu > li > ul {
            top: 0%;
            margin-top: ' . ( $nav_font_line_height + $button_extra_spacing) . 'px;
          }';

       } else {
          echo '#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 .sf-menu > li > ul,
          #nectar-nav[data-format="centered-menu-under-logo"] #top .sf-menu > li > ul {
            top: 0%;
            margin-top: ' . ( $nav_font_line_height + 16) . 'px;
          }';
       }

       echo '#nectar-nav #top .widget_shopping_cart .widget_shopping_cart_content ul,
       #nectar-nav .cart-notification {
         border-top: none;
         border-bottom: none;
       }
       #nectar-nav #top .sf-menu > li ul {
         border-top: none;
       }';
     }

     // Dropdown Animations.
     if( isset($nectar_options['header-dropdown-animation']) ) {

       if( 'default' === $nectar_options['header-dropdown-animation'] ) {
         echo '#top nav >ul >li >ul >li,
         #nectar-nav[data-format="centered-menu"] #top nav >ul >li >ul >li,
         #header-secondary-outer .sf-menu > li > ul > li,
         #nectar-nav .widget_shopping_cart .cart_list,
         #nectar-nav .widget_shopping_cart .total,
         #nectar-nav .widget_shopping_cart .buttons {
           -webkit-transform:translate3d(0,13px,0);
           transform:translate3d(0,13px,0);
         }';
       }
       else if( 'fade-in-up' === $nectar_options['header-dropdown-animation'] ) {
         echo '
         body .sf-menu >li:not(.nectar-woo-cart):not(.slide-out-widget-area-toggle):not([class*="menu-item-btn-style"]) {
           overflow: hidden;
         }
         body .sf-menu >li:not(.nectar-woo-cart).sfHover {
           overflow: visible!important;
         }

         body .sf-menu >li > ul {
           -webkit-transform:translate3d(0,15px,0);
           transform:translate3d(0,15px,0);
           opacity: 0;
         }
        #nectar-nav nav .nectar-global-section-megamenu {
           opacity: 0;
         }

         body .sf-menu >li.sfHover > ul{
           -webkit-transform:translate3d(0,0,0);
           transform:translate3d(0,0,0);
           opacity: 1;
         }

         body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart {
           -webkit-transform:translate3d(0,15px,0);
           transform:translate3d(0,15px,0);
           transition: transform 0.55s cubic-bezier(0.2,.8,.25,1);
         }
         body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart.open {
           -webkit-transform:translate3d(0,0,0);
           transform:translate3d(0,0,0);
         }

         body .sf-menu >li.sfHover > ul,
         #nectar-nav #top .sf-menu > li.sfHover > ul {
           transition: transform 0.55s cubic-bezier(0.2,.8,.25,1),opacity 0.55s cubic-bezier(0.2,.8,.25,1);
         }

         body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart,
         body #nectar-nav #top .cart-notification {
           transition: transform 0.55s cubic-bezier(0.2,.8,.25,1);
         }

         #nectar-nav #top .sf-menu.menu-open > li > ul {
          transition: none;
          -webkit-transition: none;
       }

         @media all and (-ms-high-contrast: none), (-ms-high-contrast: active) {
           body .sf-menu >li:not(.sfHover) > ul,
           body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart:not(.open) {
             transform:translate3d(0,-9999px,0);
           }
           body .sf-menu >li.sfHover > ul,
           #nectar-nav #top .sf-menu > li.sfHover > ul,
            body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart {
             transition: opacity 0.55s cubic-bezier(0.2,.8,.25,1);
           }


         }';
       }
       else if( 'fade-in' === $nectar_options['header-dropdown-animation']  ) {
        echo '#top nav >ul >li >ul >li,
         #nectar-nav[data-format="centered-menu"] #top nav >ul >li >ul >li,
         #header-secondary-outer .sf-menu > li > ul > li,
         #nectar-nav .widget_shopping_cart .cart_list,
         #nectar-nav .widget_shopping_cart .total,
         #nectar-nav .widget_shopping_cart .buttons {
          transition: opacity 0.4s ease;
          -webkit-transition: opacity 0.4s ease;
         }';
       }

     }

     // Dropdown Box Shadow.
     $header_dropdown_overlay = ( isset($nectar_options['header-dropdown-overlay']) ) ? $nectar_options['header-dropdown-overlay'] : '0';
     if( isset( $nectar_options['header-dropdown-box-shadow'] ) &&
     ! empty( $nectar_options['header-dropdown-box-shadow'] ) &&
     $header_dropdown_overlay !== '1' ) {

       if( 'small' === $nectar_options['header-dropdown-box-shadow'] ) {
         echo '#nectar-nav #top .sf-menu > li > ul,
         #nectar-nav #header-secondary-outer .sf-menu > li > ul,
         #nectar-nav #top .sf-menu > li:not(.megamenu) ul:not(.woocommerce-mini-cart),
         #nectar-nav .widget_shopping_cart,
         #nectar-nav .cart-notification,
         body[data-form-select-js="1"] .nectar-shop-header .woocommerce-ordering .select2-dropdown,
         body[data-form-select-js="1"] .variations_form .select2-dropdown,
         #nectar-nav nav .nectar-global-section-megamenu {
           box-shadow: rgba(0, 0, 0, 0.04) 0px 1px 0px, rgba(0, 0, 0, 0.05) 0px 2px 7px, rgba(0, 0, 0, 0.06) 0px 12px 22px;
         }';
       } else if( 'large-alt' === $nectar_options['header-dropdown-box-shadow']  ) {
         echo '#nectar-nav #top .sf-menu > li > ul,
         #nectar-nav #header-secondary-outer .sf-menu > li > ul,
         #nectar-nav #top .sf-menu > li:not(.megamenu) ul:not(.woocommerce-mini-cart),
         #nectar-nav .widget_shopping_cart,
         #nectar-nav .cart-notification,
         body[data-form-select-js="1"] .nectar-shop-header .woocommerce-ordering .select2-dropdown,
         body[data-form-select-js="1"] .variations_form .select2-dropdown,
         #nectar-nav nav .nectar-global-section-megamenu {
           box-shadow:
                rgba(0, 0, 0, 0.02) 0px 1px 0px,
                rgba(0, 0, 0, 0.06) 0px 38px 38px;
         }';
       }
       else if( 'none' === $nectar_options['header-dropdown-box-shadow'] ) {
         echo '#nectar-nav #top .sf-menu > li ul,
         #nectar-nav nav .nectar-global-section-megamenu {
           box-shadow: none;
         }';
       }

     }

     // Dropdown Border Radius.
     if( isset($nectar_options['header-dropdown-border-radius']) &&
     ! empty($nectar_options['header-dropdown-border-radius']) &&
     '0' !== $nectar_options['header-dropdown-border-radius'] ) {
       echo '#nectar-nav #top .sf-menu > li ul,
       #nectar-nav #header-secondary-outer .sf-menu > li ul,
       #nectar-nav #top .sf-menu > li ul li a,
       #nectar-nav #header-secondary-outer .sf-menu > li ul li a,
       #nectar-nav .widget_shopping_cart .widget_shopping_cart_content,
       #nectar-nav .cart-notification,
       body #nectar-nav #top .nectar-woo-cart .widget_shopping_cart,
       body[data-form-select-js="1"] .nectar-shop-header .woocommerce-ordering .select2-dropdown {
         border-radius: ' . esc_attr($nectar_options['header-dropdown-border-radius']) . 'px;
       }
      #nectar-nav nav .nectar-global-section-megamenu {
        border-radius: 0 0 ' . esc_attr($nectar_options['header-dropdown-border-radius']) . 'px ' . esc_attr($nectar_options['header-dropdown-border-radius']) . 'px;
      }';
     }

         if( intval($menu_item_spacing) > 15 ) {
             echo 'body.material[data-header-format="default"] #nectar-nav[data-has-buttons="yes"]:not([data-format="left-header"]) #top nav >.buttons {
				 margin-left: ' . intval($menu_item_spacing) * 2 . 'px;
			 }';
         }

         if( intval($menu_item_spacing) > 30 ) {
              echo 'body.material[data-header-format="default"] #nectar-nav[data-format="default"] #social-in-menu {
					margin-left: ' . intval($menu_item_spacing) . 'px;
				} ';
       }

    } // End not using left header.
    else {
        echo '#nectar-nav #logo img {
			height: ' . $logo_height . 'px;
		}
		body[data-header-format="left-header"] #nectar-nav .row .col.span_9 {
			top: ' . intval($logo_height + 40) . 'px;
		}';
    }

  // Dropdown Hover Effect.
  $dropdown_hover_effect = (isset($nectar_options['header-dropdown-hover-effect'])) ? esc_attr($nectar_options['header-dropdown-hover-effect']) : 'default';
  $dropdown_underline_color = $nectar_options["accent-color"];

  if( ! empty($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom' ) {
    if( ! empty($nectar_options['header-dropdown-font-hover-color']) ) {
      $dropdown_underline_color = $nectar_options['header-dropdown-font-hover-color'];
    }
  }

  if( 'animated_underline' === $dropdown_hover_effect ) {

    echo '
    .sf-menu li ul li a .menu-title-text {
      position: relative;
    }
    .sf-menu li ul li a .menu-title-text:after {
      position: absolute;
      left: 0;
      bottom: 0;
      width: 100%;
      height: 2px;
      display: block;
      content: "";
      transition: transform 0.35s cubic-bezier(0.52, 0.01, 0.16, 1);
      transform: scaleX(0);
      transform-origin: 0 0;
      background-color: ' . esc_attr($dropdown_underline_color) . ';
    }
    nav > ul > .megamenu > ul > li > a > .menu-title-text:after,
    nav > ul > .megamenu > ul > li > ul > .has-ul > a > .menu-title-text:after {
      display: none;
    }
    .sf-menu li ul li a:focus .menu-title-text:after,
    .sf-menu li ul li a:hover .menu-title-text:after,
    .sf-menu li ul li.sfHover > a .menu-title-text:after,
    .sf-menu li ul li[class*="current-"] > a .menu-title-text:after,
    .sf-menu ul .open-submenu > a .menu-title-text:after {
      transform: scaleX(1);
    }

    .nectar-ext-menu-item .menu-title-text {
      background-repeat: no-repeat;
      background-size: 0% 2px;
      background-image: linear-gradient(to right, ' . esc_attr($dropdown_underline_color) . ' 0%, ' . esc_attr($dropdown_underline_color) . ' 100%);
      -webkit-transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
      transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
      background-position: left bottom;
    }
    a:hover > .nectar-ext-menu-item .menu-title-text,
    a:focus > .nectar-ext-menu-item .menu-title-text,
    li[class*="current"] > a > .nectar-ext-menu-item .menu-title-text {
      background-size: 100% 2px;
    }
    ';
  } else if($headerFormat !== 'left-header') {
    // Default dropdown coloring.
    echo '
    #nectar-nav nav ul li li:hover >a .sf-sub-indicator i,
    #nectar-nav nav ul li .sfHover >a .sf-sub-indicator i,
    #nectar-nav:not([data-format="left-header"]) #top nav >ul >li:not(.megamenu) ul .current-menu-ancestor >a .sf-sub-indicator i,
    #nectar-nav:not([data-format="left-header"]) nav >ul >.megamenu ul ul .current-menu-item >a,
    #nectar-nav:not([data-format="left-header"]) nav >ul >.megamenu ul ul .current-menu-ancestor >a,
    #nectar-nav nav > ul >.megamenu > ul ul .sfHover >a,
    #nectar-nav nav > ul >.megamenu > ul ul li a:hover,
    #nectar-nav nav > ul >.megamenu > ul ul li a:focus,
    body:not([data-header-format="left-header"]) #nectar-nav nav >ul >.megamenu >ul ul .current-menu-item > a,
    #nectar-nav:not([data-format="left-header"]) #top nav >ul >li:not(.megamenu) ul a:hover,
    #nectar-nav:not([data-format="left-header"]) #top nav >ul >li:not(.megamenu) .sfHover >a,
    #nectar-nav:not([data-format="left-header"]) #top nav >ul >li:not(.megamenu) ul .current-menu-item >a,
    #nectar-nav:not([data-format="left-header"]) #top nav >ul >li:not(.megamenu) ul .current-menu-ancestor >a,
    body[data-dropdown-style="minimal"] #header-secondary-outer ul >li:not(.megamenu) .sfHover >a,
    body[data-dropdown-style="minimal"] #header-secondary-outer ul >li:not(.megamenu) ul a:hover {
      color:#fff
    }
    body:not([data-header-format="left-header"]) #nectar-nav nav >ul >.megamenu >ul ul li a:hover,
    body:not([data-header-format="left-header"]) #nectar-nav nav >ul >.megamenu >ul ul .current-menu-item > a{
      color:#fff;
      background-color:#000
    }';
  }

  // When ext menu items have images removed, the underline effect is still needed.
  if( isset($nectar_options['header-slide-out-widget-area-image-display']) &&
      'remove_images' === $nectar_options['header-slide-out-widget-area-image-display'] ) {

        $animated_underline_thickness = (isset($nectar_options['animated-underline-thickness']) && ! empty($nectar_options['animated-underline-thickness'])) ? $nectar_options['animated-underline-thickness'] : '2';

        if( (in_array($theme_skin, ['original','ascend']) && 'fullscreen-split' !== $side_widget_class) ||
            'material' === $theme_skin && in_array($side_widget_class, ['fullscreen', 'fullscreen-alt']) ) {

          echo '#slide-out-widget-area .nectar-ext-menu-item .menu-title-text {
            background-image: none!important;
          }';

        }
        else if( 'animated_underline' !== $dropdown_hover_effect ) {

          echo '
          #slide-out-widget-area .nectar-ext-menu-item .menu-title-text {
            background-repeat: no-repeat;
            background-size: 0% ' . esc_attr($animated_underline_thickness) . 'px;
            background-image: linear-gradient(to right, ' . esc_attr($dropdown_underline_color) . ' 0%, ' . esc_attr($dropdown_underline_color) . ' 100%);
            -webkit-transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
            transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
            background-position: left bottom;
          }
          #slide-out-widget-area a:hover > .nectar-ext-menu-item .menu-title-text,
          #slide-out-widget-area li[class*="current"] > a > .nectar-ext-menu-item .menu-title-text {
            background-size: 100% ' . esc_attr($animated_underline_thickness) . 'px;
          }';

        }

    }

    global $post;
    if( ! empty($nectar_options['transparent-header']) && $nectar_options['transparent-header'] === '1' && isset($post->ID) ) {
        $activate_transparency = nectar_using_page_header($post->ID);
    } else {
        $activate_transparency = false;
    }

  $mobile_header_padding = isset($nectar_options['header-mobile-padding']) && ! empty($nectar_options['header-mobile-padding']) ? intval($nectar_options['header-mobile-padding']) * 2 : 24;
  $mobile_header_space = $mobile_header_padding;
  if ( ! empty( $nectar_options['use-logo'] ) ) {
    $mobile_header_space = intval($mobile_logo_height) + $mobile_header_padding;
  }
     echo '
    :root {
      --header-nav-height: ' . $header_space . 'px;
    }
    #nectar-nav-spacer {
		 padding-top: ' . $header_space . 'px;
	 }
	 @media only screen and (max-width: 1024px) {
		 #nectar-nav-spacer {
			 padding-top: ' . $mobile_header_space . 'px;
		 }
	 }';

     $header_extra_space_to_remove = $extra_secondary_height;

     if($headerFormat === 'centered-menu-under-logo' || $headerFormat === 'centered-menu-bottom-bar') {
         $header_extra_space_to_remove += 20;
     } else {
         $header_extra_space_to_remove += intval($header_padding);
     }

  if( isset($nectar_options['header-dropdown-display-desc']) &&
      ! empty($nectar_options['header-dropdown-display-desc']) &&
      '1' === $nectar_options['header-dropdown-display-desc']) {
      echo '#slide-out-widget-area .inner .off-canvas-menu-container li > a .item_desc {
        display: block;
      }';
  }

     // Permanent transparent theme option.
     $perm_trans = (! empty($nectar_options['header-permanent-transparent'])) ? $nectar_options['header-permanent-transparent'] : 'false';
    if ( $perm_trans === '1' && ! nectar_is_contained_header() ) {
      echo '#nectar-nav.transparent:not(.material-search-open) {
        mix-blend-mode: exclusion;
      }
      #nectar-nav.transparent #search-outer:not(.material-open),
      #nectar-nav.transparent #search-outer:not(.material-open):before,
      #nectar-nav.transparent #search-outer:not(.material-open) #search {
        transition: none!important;
      }';

      // Disable blur on all Webkit browsers, as it causes a color inversion when combined with the mix-blend-mode: exclusion;
      if( isset($nectar_options['header-blur-bg']) && $nectar_options['header-blur-bg'] === '1' ) {
        echo '@supports (-webkit-hyphens: none) and (not (-ms-ime-align: auto)) {
            #nectar-nav {
              -webkit-backdrop-filter: none !important;
              backdrop-filter: none !important;
            }
        }';
      }

    $header_starting_color = '#ffffff';
    $header_starting_dark_color = '#ffffff';
    }

   /*-------------------------------------------------------------------------*/
   /* 1.3. Mobile Logo Height
   /*-------------------------------------------------------------------------*/
     echo '
	 #nectar-nav #logo .mobile-only-logo,
	 #nectar-nav[data-format="centered-menu-bottom-bar"][data-condense="true"] .span_9 #logo img {
		 height: ' . esc_attr($mobile_logo_height) . 'px;
	 }

	 @media only screen and (max-width: 1024px) {
		 	body #top #logo img,
			#nectar-nav[data-permanent-transparent="false"] #logo .dark-version {
		 		height: ' . esc_attr($mobile_logo_height) . 'px!important;
		 	}

	 }';

   /*-------------------------------------------------------------------------*/
   /* 1.4. Custom Mobile Breakpoint
   /*-------------------------------------------------------------------------*/
     $mobile_breakpoint = (! empty($nectar_options['header-menu-mobile-breakpoint'])) ? $nectar_options['header-menu-mobile-breakpoint'] : 1025;
     $has_main_menu = (has_nav_menu('top_nav')) ? 'true' : 'false';

   if( $headerFormat === 'centered-logo-between-menu-alt' ) {
     $has_main_menu = 'true';
   }

     if( ! empty($mobile_breakpoint) && $mobile_breakpoint != 1025 &&
      $has_main_menu === 'true' &&
      'centered-menu' !== $mobile_header_layout ) {

        $mobileMenuTopPadding = ceil(($logo_height / 2)) - 10;
        $mobileMenuTopPaddingSmall = ceil( ($logo_height - $shrinkNum) / 2  ) - 10;

    $starting_opacity = (isset($nectar_options['header-starting-opacity']) && ! empty($nectar_options['header-starting-opacity'])) ? $nectar_options['header-starting-opacity'] : '0.75';
        $starting_color = $header_starting_color;
        $mobile_menu_hover = $nectar_options["accent-color"];

        if( ! empty($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom' && ! empty($nectar_options['header-font-hover-color'])) {
            $mobile_menu_hover = $nectar_options['header-font-hover-color'];
        }

    // Left Header format
    if ( $headerFormat === 'left-header' ) {
      echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {

        html body[data-header-format="left-header"] #nectar-nav-spacer {
          display: block;
        }
        html body[data-header-format="left-header"] #nectar-content-wrap {
          margin-left: 0;
        }
        #nectar-nav[data-format="left-header"] #top .sf-menu .nectar-woo-cart {
            display: none;
        }
        html body #nectar-nav[data-format="left-header"] {
          width: 100%;
          height: auto;
          border-right: none;
        }
        html body[data-header-format="left-header"] #top {
          height: auto;
        }
        html body[data-header-format="left-header"] #top #logo {
          margin: ' . $header_padding . 'px 0;
          display: block;
        }
        #nectar-nav[data-format="left-header"] #top .span_9,
        #nectar-nav[data-format="left-header"] #top .span_3 {
          width: auto;
        }
        #nectar-nav[data-format="left-header"] #top .span_9 {
          margin-left: auto;
          position: relative;
          bottom: auto;
          overflow: visible;
          display: flex;
          width: auto;
          top: 0;
        }
        body[data-header-format="left-header"] #nectar-nav .button_social_group {
          display: none;
        }
        #nectar-nav[data-format="left-header"] #top .buttons.sf-menu,
        body[data-header-format="left-header"] #nectar-nav .span_3 {
          margin-top: 0;
        }
        body[data-header-format="left-header"] #nectar-nav nav {
            padding: 0;
            display: block;
        }
        #nectar-nav[data-format="left-header"] #top > .container > .row nav >ul {
          width: auto;
          gap: 20px;
          margin:0 20px;
        }
        body[data-header-format="left-header"][data-header-search="true"] #nectar-nav nav #nectar-user-account {
          margin-left: 0;
        }

        #nectar-nav[data-format="left-header"] #top > .container > .row,
        #nectar-nav[data-format="left-header"] #top > .container > .row nav,
        #nectar-nav[data-format="left-header"] #top > .container > .row nav >ul,
        body[data-header-format="left-header"] #nectar-nav .nav-outer {
          display: flex;
      }
      #nectar-nav[data-format="left-header"] .buttons.sf-menu li {
        display: flex;
        align-content: center;
        align-items: center;
      }

      html body[data-header-format="left-header"].material #search-outer {
        left: 0;
        width: 100%;
        position: absolute;
        z-index: 99999!important;
      }

      body[data-header-format="left-header"] .portfolio-filters-inline.full-width-section:not(.non-fw),
      body[data-header-format="left-header"] .full-width-content.blog-fullwidth-wrap,
      body[data-header-format="left-header"] .wpb_row.full-width-content,
      body[data-header-format="left-header"] .wpb_row.full-width-content.has-global-section .wpb_row.full-width-content,
      body[data-header-format="left-header"] .full-width-content.nectar-shop-outer,
      body[data-header-format="left-header"] .page-submenu > .full-width-section,
      body[data-header-format="left-header"] .page-submenu .full-width-content,
      body[data-header-format="left-header"] .full-width-section .row-bg-wrap,
      body[data-header-format="left-header"] .full-width-section .nectar-parallax-scene,
      body[data-header-format="left-header"] .full-width-section > .nectar-shape-divider-wrap,
      body[data-header-format="left-header"] .full-width-section > .video-color-overlay,
      body[data-header-format="left-header"] .full-width-section.parallax_section .row-bg-wrap,
      body[data-header-format="left-header"] .nectar-slider-wrap[data-full-width="true"],
      body[data-header-format="left-header"] .wpb_row.full-width-section .templatera_shortcode > .wpb_row.full-width-section > .row-bg-wrap,
      .single-product[data-header-format="left-header"] .product[data-gallery-style="left_thumb_sticky"][data-gallery-variant="fullwidth"] .single-product-wrap {
        margin-left: -50vw;
        margin-left: calc(-50vw + var(--scroll-bar-w)/2);
        left: 50%;
        width: 100vw;
        width: calc(100vw - var(--scroll-bar-w));
      }
      body[data-header-format="left-header"] .full-width-section > .nectar-video-wrap {
        margin-left: calc(-50vw + var(--scroll-bar-w)/2);
        width: calc(100vw - var(--scroll-bar-w))!important;
      }

      }';
    }

    // Regular formats
        echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {

      #top .span_9 > .nectar-mobile-only {
        display: flex;
        align-items: center;
        order: 1;
      }

      #nectar-nav[data-has-buttons="no"] #top .span_9 > .nectar-mobile-only {
        margin-right: 20px;
      }

      #nectar-nav:not([data-format="centered-menu-bottom-bar"]) #top .span_9 {
        flex-direction: row-reverse;
      }

      body #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
        display: none;
      }

      body #nectar-nav[data-format="centered-menu-bottom-bar"][data-condense="true"].fixed-menu .span_3 .sf-menu  {
        visibility: visible;
      }

      #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3 {
        margin-bottom: 0;
      }


      body.material #nectar-nav[data-format="centered-menu-bottom-bar"][data-condense="true"].fixed-menu #search-outer {
        top: 0;
      }

      body[data-slide-out-widget-area-style="simple"] #nectar-nav #mobile-menu {
          top: 100%;
      }

      body[data-slide-out-widget-area-style="simple"][data-ext-responsive="true"] #nectar-nav[data-full-width="false"] #mobile-menu {
        padding: 0 90px;
      }

	 		#nectar-nav #top .span_9 nav .sf-menu:not(.buttons) > li,
			#top .span_9 nav .buttons .menu-item,
      #top .right-aligned-menu-items .buttons .menu-item {
				visibility: hidden;
				pointer-events: none;
				margin: 0;
			}

	 		#nectar-nav #top .span_9 nav .sf-menu:not(.buttons) > li,
			#top .span_9 nav .buttons .menu-item,
      #top .right-aligned-menu-items .buttons .menu-item {
				position: absolute;
			}

      #nectar-nav[data-format="centered-menu"] #top nav >.buttons {
        position: relative;
      }

	 		#nectar-nav #top nav .sf-menu > #social-in-menu {
				position: relative;
				visibility: visible;
				pointer-events: auto;
			}

			body.material[data-header-search="true"][data-user-set-ocm="off"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons,
			body.material[data-cart="true"][data-user-set-ocm="off"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons,
			body.material[data-user-account-button="true"][data-user-set-ocm="off"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons {
				margin-right: 28px;
			}



			body[data-header-search="true"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons,
			body[data-cart="true"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons,
			body[data-user-account-button="true"] #nectar-nav:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons {
				margin-right: 19px;
			}

      body #nectar-nav[data-full-width="false"][data-has-buttons="no"]:not([data-format="left-header"]) #top nav #social-in-menu,
      body.material #nectar-nav[data-has-buttons="no"]:not([data-format="left-header"]) #top nav #social-in-menu {
        margin-right: 20px;
      }

      #nectar-nav[data-format="menu-left-aligned"] #top > .container .span_9 > .slide-out-widget-area-toggle.mobile-icon {
        top: 50%;
        right: 0;
        position: absolute;
        transform: translateY(-50%);
        -webkit-transform: translateY(-50%);
      }
      body #nectar-nav[data-format="menu-left-aligned"]:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons {
        margin-right: 55px!important;
      }
      ';
            if( true === $menu_label ) {
        echo 'body #nectar-nav[data-format="menu-left-aligned"]:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons {
          margin-right: 110px!important;
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"] .right-side > .slide-out-widget-area-toggle a.using-label > span {
          display: inline-block;
          vertical-align: middle;
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"] .right-side > .slide-out-widget-area-toggle a.using-label .label {
          margin-right: 15px;
        }';
      }

      //// Centered Menu.
      if( $headerFormat === 'centered-menu' ) {
        echo '#nectar-nav #top .span_9 nav .sf-menu:not(.buttons) {
          display: none!important;
        }
        #nectar-nav[data-format=centered-menu] #top .span_9, #nectar-nav[data-format=centered-menu] #top .span_9 nav {
          flex-grow: 0;
        }
        #nectar-nav #top .logo-spacing {
          width: 1px;
        }';
      }

      // Centered Logo Between Menu Alt.
      if( $headerFormat === 'centered-logo-between-menu-alt' ) {
        echo '
          #nectar-nav #top > .container > .row {
            justify-content: flex-start;
          }
          #nectar-nav #top > .container > .row > .span_9,
          #nectar-nav #top > .container > .row > .right-aligned-menu-items {
            position: relative;
            width: auto;
            pointer-events: all;
          }
          #nectar-nav #top > .container > .row > .right-aligned-menu-items {
            order: 1;
            margin-left: auto;
          }
          #nectar-nav #top > .container > .row > .span_9 {
            order: 2;
          }
          #nectar-nav #top > .container > .row > .span_9 nav {
            display: none;
          }
          body.material #nectar-nav:not([data-format="left-header"]) #top nav >.buttons {
            margin-left: 0;
          }
          #nectar-nav #top>.container>.row>.right-aligned-menu-items nav >.buttons  {
            display: none!important;
          }

          #nectar-nav #top .span_9 > .mobile-search,
          #nectar-nav #top .span_9 > .slide-out-widget-area-toggle,
          #nectar-nav #top .span_9 > #mobile-cart-link,
          #nectar-nav #top .mobile-user-account {
            display: flex;
            position: relative;
            align-items: center;
            padding: 0 var(--header-mobile-vertical-padding, 12px);
          }

          #nectar-nav #top .span_9 > .nectar-mobile-only.mobile-header {
            order: 11;
          }

          #nectar-nav #top .span_9 > .mobile-search {
            order: 10;
          }
          #nectar-nav #top .span_9 > .mobile-user-account {
            order: 9;
          }
          #nectar-nav #top .span_9 > #mobile-cart-link{
            order: 8;
          }
          #nectar-nav #top .span_9 > .slide-out-widget-area-toggle {
            order: 7;
          }

        ';
      }

      // Centered Menu Bottom Bar.
            else if( $headerFormat === 'centered-menu-bottom-bar' ) {

        // Centered menu bottom bar is no longer two rows at this point.
        $centerd_header_space = $logo_height + ($header_padding * 2) + $extra_secondary_height;
          echo 'body #nectar-nav-spacer {
    		  padding-top: ' . esc_attr($centerd_header_space) . 'px;
    	  }';

                echo '
        #nectar-nav[data-menu-bottom-bar-align="left"] #top .span_9 > .nectar-mobile-only.mobile-header {
          display: none;
        }

        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] .row {
          flex-direction: row;
        }
        body.material #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top .span_9 {
          width: auto;
          margin-left: 20px;
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top .span_9 nav {
          display: none;
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top .span_9 > .mobile-search,
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top .span_9 > .slide-out-widget-area-toggle,
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top .span_9 > #mobile-cart-link {
          display: flex;
          position: relative;
          align-items: center;
          padding: 0 var(--header-mobile-vertical-padding, 12px);
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #top #mobile-cart-link i {
          font-size: 22px;
          line-height: 22px;
        }
        #nectar-nav[data-format="centered-menu-bottom-bar"][data-menu-bottom-bar-align="left"] #mobile-cart-link .cart-wrap {
          right: 3px;
        }

				#nectar-nav[data-format="centered-menu-bottom-bar"]:not([data-menu-bottom-bar-align="left"]) #top .span_3 .slide-out-widget-area-toggle.mobile-icon {
					display: flex;
					display: -webkit-flex;
					margin-left: 13px;
					align-items: center;
					-webkit-align-items: center;
				}

				body[data-user-set-ocm="off"] #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3 .slide-out-widget-area-toggle.mobile-icon {
					margin-left: 23px;
				}

				#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3 .slide-out-widget-area-toggle.mobile-icon > div {
				 display: flex;
         display: -webkit-flex;
				 -webkit-align-items: center;
				 align-items: center;
				 height: 100%;
			 }

				body.material #nectar-nav[data-using-secondary="1"][data-format="centered-menu-bottom-bar"][data-condense="true"]:not([data-format="left-header"]) {
					margin-top: 0;
				}

				#nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3 .right-side ul .slide-out-widget-area-toggle.mobile-icon {
					display: block!important;
				}
				body #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"] #top .span_3 {
					text-align: center;
				}

				body #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
					position: fixed!important;
				}

        body:not(.admin-bar) #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
          top: 0px!important;
        }
				body.admin-bar #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
					top: 32px!important;
				}

				#nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"].fixed-menu #top nav >.buttons,
				#nectar-nav[data-has-menu="true"]:not([data-format="centered-menu-bottom-bar"]) #top .span_3 #logo img {
					transform: none!important;
				}

				body #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"]:not([data-menu-bottom-bar-align="left"]) #top .span_9 {
					display: none;
				}';

            } // end conditional for centered menu bottom bar

            echo '

			#top nav ul .slide-out-widget-area-toggle {
				display: none!important;
			}


	 		#nectar-nav[data-format="centered-menu"] #top .span_9 nav .sf-menu,
	 		#nectar-nav[data-format="centered-logo-between-menu"] #top .span_9 nav .sf-menu,
      #nectar-nav[data-format="centered-logo-between-menu"] #top .span_9 nav .sf-menu:not(.buttons),
	 		#nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 nav {
	 			-webkit-justify-content: flex-end;
			    justify-content: flex-end;
	 		}

			#nectar-nav[data-format="centered-logo-between-menu"] #top nav > .buttons {
				position: relative;
			}

			body #nectar-nav[data-format="centered-logo-between-menu"] #top #logo,
      body[data-slide-out-widget-area-style="slide-out-from-right"]:not(.material) #nectar-nav[data-format="centered-logo-between-menu"] #top #logo {
				transform: none!important;
			}

 	    #nectar-nav:not([data-format="centered-menu-bottom-bar"]) #top .span_9 > .slide-out-widget-area-toggle,
			#slide-out-widget-area .mobile-only:not(.nectar-header-text-content) {
        display: -webkit-flex;
				display: flex!important;
        -webkit-align-items: center;
        align-items: center;
				transition: padding 0.2s ease;
			}

      #slide-out-widget-area.fullscreen .mobile-only,
      #slide-out-widget-area.fullscreen-alt .mobile-only {
        justify-content: center;
      }
      #slide-out-widget-area.fullscreen .mobile-only:not(.nectar-header-text-content),
      #slide-out-widget-area.fullscreen-alt .mobile-only:not(.nectar-header-text-content) {
        display: block!important;
      }

	 		#nectar-nav[data-has-menu="true"] #top .span_3,
			body #nectar-nav[data-format="centered-menu-under-logo"] .span_3 {
			    text-align: left;
			    left: 0;
			    width: auto;
			    float: left;
			}

			#nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 ul #social-in-menu a {
				margin-bottom: 0;
			}

			#nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 nav >.buttons {
				padding-bottom: 0;
			}

			body.material #nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 {
			    margin-left: auto;
			}

			body.material #nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 ul #social-in-menu a,
		  body.material #nectar-nav[data-format="centered-menu-under-logo"] #top .span_9 nav >.buttons {
				margin-bottom: 0;
				padding-bottom: 0;
			}

			body.material #nectar-nav[data-format="centered-menu-under-logo"] #top .row .span_9,
			body.material #nectar-nav[data-format="centered-menu-under-logo"] #top .row .span_3 {
				    display: -webkit-flex;
				    display: -ms-flexbox;
				    display: flex;
			}

			body #nectar-nav[data-format="centered-menu-under-logo"] .row {
				-webkit-flex-direction: row;
			    flex-direction: row;
			}

			#nectar-nav[data-format="centered-menu-under-logo"] #top #logo{
			  display: -webkit-flex;
			  display: -ms-flexbox;
			  display: flex;
			  -webkit-align-items: center;
			  align-items: center;
			}

			body #nectar-nav[data-format="centered-menu-under-logo"] #top #logo .starting-logo {
				left: 0;
				-webkit-transform: none;
				transform: none;
			}

			body #nectar-nav[data-format="centered-menu-under-logo"] #top #logo img {
				margin: 0
			}

			#nectar-nav[data-format="centered-menu-under-logo"] #top #logo {
				text-align: left;
			}

			.cart-outer, body #nectar-nav[data-full-width="false"] .cart-outer {
			    display: block;
			}

			#nectar-nav[data-format="centered-logo-between-menu"] #top nav .sf-menu > li {
				float: left!important;
			}


			body[data-full-width-header="false"] #slide-out-widget-area.slide-out-from-right-hover .slide_out_area_close {
				display: none;
			}

			body[data-slide-out-widget-area-style="slide-out-from-right-hover"][data-slide-out-widget-area="true"][data-user-set-ocm="off"] #nectar-nav[data-full-width="false"][data-cart="false"] header > .container {
			    max-width: 100%!important;
			    padding: 0 28px !important;
			}

			body[data-full-width-header="false"][data-cart="true"] .slide-out-hover-icon-effect.small {
				right: 28px!important;
			}

			body[data-full-width-header="false"][data-cart="true"] .slide-out-widget-area-toggle .lines-button.x2.no-delay .lines:before,
			body[data-full-width-header="false"][data-cart="true"] .slide-out-widget-area-toggle .lines-button.x2.no-delay .lines:after,
			body[data-full-width-header="false"][data-cart="true"] .slide-out-hover-icon-effect.slide-out-widget-area-toggle .no-delay.lines-button.no-delay:after {
				-webkit-transition: none!important;
				transition: none!important;
			}';

      if ( ! nectar_is_contained_header() ) {
        echo 'body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
        body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
        body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
          background-color: ' . esc_attr($starting_color) . '!important;
          opacity: ' . esc_attr($starting_opacity) . ';
        }';
      }

            echo 'body:not(.mobile) #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
			body:not(.mobile) #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
			body:not(.mobile) #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
				background-color: ' . esc_attr($header_starting_dark_color) . '!important;
				opacity: ' . esc_attr($starting_opacity) . ';
			}
			body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines-button:after,
			body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines:before,
			body:not(.mobile) #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines:after {
				opacity: 1;
			}

			body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines:after,
			body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines-button:after,
			body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines:before {
				background-color: ' . esc_attr($mobile_menu_hover) . '!important;
			}

			body:not(.mobile) #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
			body:not(.mobile) #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
			body:not(.mobile) #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
				background-color: #fff!important;
			}

      body[data-user-set-ocm="off"] #slide-out-widget-area.fullscreen-split,
      body[data-user-set-ocm="off"] #slide-out-widget-area-bg.fullscreen-split {
        display: block;
      }

	 	}';
     }

     // Custom header bg opacity
     if( isset($nectar_options['header-bg-opacity']) && ! empty($nectar_options['header-color']) ) {

      $alpha = intval( $nectar_options['header-bg-opacity'] );

      if( $headerFormat !== 'left-header' && $alpha !== 100 ) {

        $header_bg_color = '#ffffff';
        if ( $nectar_options['header-color'] === 'dark' ) {
          $header_bg_color = '#000000';
        } else if ( $nectar_options['header-color'] === 'custom' ) {
          $header_bg_color = isset($nectar_options['header-background-color']) && ! empty($nectar_options['header-background-color']) ? esc_attr($nectar_options['header-background-color']) : '#ffffff';
        }

        $header_bg_color = substr($header_bg_color, 1);

        $leading_zero = $alpha < 10 ? '0.0' : '0.';

        $colorR = hexdec( substr( $header_bg_color, 0, 2 ) );
        $colorG = hexdec( substr( $header_bg_color, 2, 2 ) );
        $colorB = hexdec( substr( $header_bg_color, 4, 2 ) );
        $colorA = $leading_zero . esc_attr($alpha);

        echo 'html body #nectar-nav,
        html body[data-header-color="dark"] #nectar-nav {
          background-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',' . $colorA . ');
        }';

    }

    }

    if(! empty($nectar_options['header-dropdown-opacity']) &&
    $nectar_options['header-dropdown-opacity'] !== '100' &&
    ! empty($nectar_options['header-color'])) {

          if($nectar_options['header-color'] === 'light' ||
        $nectar_options['header-color'] === 'dark') {
        $dropdownBGColor = '#1c1c1c';

        if( $nectar_options['header-color'] === 'light' ) {
          $dropdownBGColor = '#ffffff';
        }
      } else {
        // custom color scheme
        $custom_dropdown_color = ( isset($nectar_options['header-dropdown-background-color']) && ! empty($nectar_options['header-dropdown-background-color']) ) ? $nectar_options['header-dropdown-background-color'] : '#1c1c1c';
        $dropdownBGColor = $custom_dropdown_color;
      }

      $dropdownBGColor = substr($dropdownBGColor, 1);
             $colorR = hexdec( substr( $dropdownBGColor, 0, 2 ) );
             $colorG = hexdec( substr( $dropdownBGColor, 2, 2 ) );
             $colorB = hexdec( substr( $dropdownBGColor, 4, 2 ) );
             $colorA = ($nectar_options['header-dropdown-opacity'] != '100') ? '0.' . esc_attr($nectar_options['header-dropdown-opacity']) : esc_attr($nectar_options['header-dropdown-opacity']);

             echo '
			 #search-outer .ui-widget-content,
			 body:not([data-header-format="left-header"]) #top .sf-menu li ul,
			 #nectar-nav .widget_shopping_cart .cart_list a,
			 #header-secondary-outer ul ul li a,
			 #nectar-nav .widget_shopping_cart .cart_list li,
			 .woocommerce .cart-notification,
			 #nectar-nav .widget_shopping_cart_content {
					background-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',' . $colorA . ')!important;
				}
        :root {
          --nectar-nav-dropdown-bg: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',' . $colorA . ');
        }';

    }

  /*-------------------------------------------------------------------------*/
  /* 1.5. Megamenu Removes Transparent
  /*-------------------------------------------------------------------------*/
  if(isset($nectar_options['header-megamenu-remove-transparent']) &&
    ! empty($nectar_options['header-megamenu-remove-transparent']) &&
    '1' === $nectar_options['header-megamenu-remove-transparent'] &&
    isset($nectar_options['transparent-header']) && '1' === $nectar_options['transparent-header'] ) {

    echo '
    #nectar-nav.no-transition,
    #nectar-nav.no-transition a:not(#logo),
    #nectar-nav.no-transition img,
    #nectar-nav.no-transition a.no-image,
    #nectar-nav.no-transition .icon-nectar-blocks-search,
    #nectar-nav.no-transition .icon-nectar-blocks-m-user,
    #nectar-nav.no-transition .icon-nectar-blocks-cart,
    #nectar-nav.no-transition #top nav ul .slide-out-widget-area-toggle a .lines,
    #nectar-nav.no-transition #top nav ul .slide-out-widget-area-toggle a .lines:before,
    #nectar-nav.no-transition #top nav ul .slide-out-widget-area-toggle a .lines:after,
    .material #nectar-nav.no-transition #top .slide-out-widget-area-toggle a .lines-button:after,
    #nectar-nav.no-transition .lines-button:after,
    #nectar-nav.no-transition #top nav ul li a > .sf-sub-indicator i,
    #nectar-nav.no-transition #top nav ul #search-btn a:after,
    #nectar-nav.no-transition #top nav ul #nectar-user-account a:after,
    #nectar-nav.no-transition #top nav ul .slide-out-widget-area-toggle a:after,
    #nectar-nav.no-transition .cart-menu:after,
    #nectar-nav.no-transition #top nav >ul >li[class*="button_bordered"] >a:not(:hover):before,
    #nectar-nav.no-transition.transparent #top nav >ul >li[class*="button_bordered"] >a:not(:hover):before,
    #nectar-nav.no-transition .nectar-header-text-content {
      -webkit-transition:none!important;
      transition:none!important
    }
    .material #nectar-nav:not([data-transparent-header="true"]):not([data-format="left-header"]).no-transition #logo img,
    .material #nectar-nav:not([data-transparent-header="true"]):not([data-format="left-header"]).no-transition .logo-spacing img {
      -webkit-transition: height 0.15s ease!important;
      transition: height 0.15s ease!important;
    }
    .material #nectar-nav:not([data-format="left-header"]).no-transition #logo img,
    .material #nectar-nav:not([data-format="left-header"]).no-transition .logo-spacing img {
      -webkit-transition: height 0.32s ease!important;
      transition: height 0.32s ease!important;
    }
    #nectar-nav.no-transition li[class*="button_"] a:hover{
      -webkit-transition:opacity 0.2s ease,color 0.2s ease!important;
      transition:opacity 0.2s ease,color 0.2s ease!important
    }
    #nectar-nav[data-lhe="animated_underline"].no-transition #top nav >ul >li:not([class*="button_"]) >a .menu-title-text:after{
      -webkit-transition:-webkit-transform .3s ease-out;
      transition:transform .3s ease-out
    }
    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 1.6. Dark Color Scheme
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-color']) &&
      ! empty($nectar_options['header-color']) &&
      'dark' === $nectar_options['header-color'] ) {
    echo '
     #nectar-nav-spacer {
      background-color: #000;
    }
     #nectar-nav,
     #search-outer{
      background-color:#000;
      -webkit-box-shadow:none;
    	box-shadow:none;
    }

     #top nav ul li > a,
     #search-outer input,
     #top #logo,
     body #top #logo:focus,
     body #top #logo:hover,
     #top nav ul #nectar-user-account a span,
     #top nav ul #search-btn a span,
     #nectar-nav .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
     .nectar-mobile-only.mobile-header {
      color:#A0A0A0
    }

     #top nav ul .slide-out-widget-area-toggle a .lines,
     #top nav ul .slide-out-widget-area-toggle a .lines:after,
     #top nav ul .slide-out-widget-area-toggle a .lines:before,
     #top nav ul .slide-out-widget-area-toggle .lines-button:after{
      background-color:#A0A0A0
    }
    body:not([data-header-format="left-header"]) #top nav >ul >.megamenu >ul >li >a,
    body:not([data-header-format="left-header"]) #top nav >ul >.megamenu >ul >li >ul >.has-ul >a {
    	color: #fff;
    }
     #header-secondary-outer .sf-menu li ul,
    body:not([data-header-format="left-header"]) #nectar-nav .sf-menu li ul,
     #nectar-nav nav >ul >.megamenu >.sub-menu,
     #nectar-nav .widget_shopping_cart .cart_list li,
     #nectar-nav .cart-notification,
     #nectar-nav .widget_shopping_cart_content {
      background-color:#000
    }
    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 1.7. Social Icons
  /*-------------------------------------------------------------------------*/
  $social_media_list = nectar_get_social_media_list();
  foreach ($social_media_list as $network_name => $icon_arr) {

    if ( isset( $nectar_options['use-' . $network_name . '-icon-header'] ) &&
         $nectar_options['use-' . $network_name . '-icon-header'] === '1' ||
         isset( $nectar_options[$network_name . '-url'] ) &&
         ! empty($nectar_options[$network_name . '-url']) ) {

           echo '#nectar-nav #social-in-menu .' . esc_attr($icon_arr['icon_class']) . ':after,
           .material #slide-out-widget-area.slide-out-from-right .off-canvas-social-links .' . esc_attr($icon_arr['icon_class']) . ':after{
             content:"' . esc_attr($icon_arr['icon_code']) . '"
           }';
    }
  }

  /*-------------------------------------------------------------------------*/
  /* 1.8. AJAX Search
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-disable-search']) &&
      '1' !== $nectar_options['header-disable-search'] ||
      isset($nectar_options['header-disable-search']) &&
      empty($nectar_options['header-disable-search']) ) {

      if( isset($nectar_options['header-disable-ajax-search']) &&
         '1' !== $nectar_options['header-disable-ajax-search'] ||
         isset($nectar_options['header-disable-ajax-search']) &&
         empty($nectar_options['header-disable-ajax-search'])) {

           echo '#search-outer .ui-widget-content{
             background-color:#1F1F1F;
             border:none;
             border-radius:0;
             -webkit-border-radius:0;
             background-image:none
           }
           #search-outer .ui-widget-content li:hover,
           .ui-state-hover,
           .ui-widget-content .ui-state-hover,
           .ui-widget-header .ui-state-hover,
           .ui-state-focus,
           .ui-widget-content .ui-state-focus,
           .ui-widget-header .ui-state-focus {
             background-color:#272727;
             cursor:pointer
           }
           #search-outer .ui-widget-content li:hover a{
             background-image:none
           }
           #search-outer .ui-widget-content li:last-child a{
             border:none
           }
           #search-outer .ui-widget-content li a{
             border-bottom:1px solid rgba(255,255,255,0.1)
           }
           #search-outer .ui-widget-content img,
           #search-outer .ui-widget-content i{
             width:40px;
             height:auto;
             float:left;
             margin-right:15px
           }
           #search-outer .ui-widget-content i{
             line-height:40px;
             font-size:22px;
             background-color:rgba(255,255,255,0.1);
             border-radius:0
           }
           .ui-widget{
             font-family:Verdana,Arial,sans-serif;
             font-size:1.1em
           }
           .ui-menu{
             display:block;
             float:left;
             list-style:none outside none;
             margin:0;
             padding:2px
           }
           .ui-autocomplete{
             cursor:default;
             position:absolute
           }
           .ui-menu .ui-menu-item a{
             display:block;
             line-height:1.5;
             padding:0.2em 0.4em;
             text-decoration:none
           }
           #search-outer .ui-widget-content{
             width:100%!important;
             left:0!important
           }

           #search-outer .ui-widget-content li{
             line-height:14px;
             clear:left;
             width:100%;
             display:block;
             float:left;
             margin:0
           }
           #search-outer .ui-widget-content li .desc{
             position:relative;
             line-height:14px;
             font-size:11px
           }
           #search-outer .ui-widget-content li a{
             color:#CCC;
             line-height:1.4em;
             transition:none;
             -webkit-transition:none;
             padding:15px 15px
           }';

      }

  }

  /*-------------------------------------------------------------------------*/
  /* 1.9. Button Styling
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-button-styling']) ) {

      if( 'shadow_hover_scale' === $nectar_options['header-button-styling'] ||
          'hover_scale' === $nectar_options['header-button-styling'] ) {

        echo '
        #nectar-nav li[class*="menu-item-btn-style"] > a:hover:before {
          transform: scale(1.065) translateY(-50%)!important;
        }

        #nectar-nav li[class*="menu-item-btn-style"] > a:hover:after {
          transform: scale(1.07) translateY(-50%)!important;
        }';
      }

  }

  // Header styles based on icons active
  $woo_cart = ( function_exists( 'is_woocommerce' ) && isset( $nectar_options['enable-cart'] ) && $nectar_options['enable-cart'] === '1') ? 'true' : 'false';
  if( in_array( NectarThemeManager::$header_format, ['centered-logo-between-menu-alt'] ) ) {
        $has_main_menu = 'true';
    }

  $ocm_menu_btn_bg_color = 'false';
  $full_width_header = ( isset( $nectar_options['header-fullwidth'] ) && $nectar_options['header-fullwidth'] === '1' ) ? 'true' : 'false';

  if( isset($nectar_options['header-slide-out-widget-area-menu-btn-bg-color']) &&
    ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'] ) ) {

      //// Ascend full width does not support custom OCM coloring.
      $ocm_menu_btn_color_non_compatible = ( 'ascend' === $theme_skin && 'true' === $full_width_header ) ? true : false;

      if( false === $ocm_menu_btn_color_non_compatible ) {
        $ocm_menu_btn_bg_color = 'true';
      }

  }

  /*-------------------------------------------------------------------------*/
  /* 1.10. Text Content
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-text-widget']) && ! empty($nectar_options['header-text-widget']) ) {
    echo '
    #nectar-nav[data-format="left-header"] .buttons.sf-menu .nectar-header-text-content-wrap {
        float: none;
        display: block;
    }
    #nectar-nav .nectar-header-text-content {
    	line-height: 1.3em;
    	padding-top: 15px;
    	padding-bottom: 15px;
    	align-self: center;
    }

    #nectar-nav[data-format="default"] .nectar-header-text-content,
    #nectar-nav[data-format="centered-logo-between-menu"] .nectar-header-text-content {
     padding-left: 0;
     text-align: center;
    }
    #nectar-nav[data-format="menu-left-aligned"] .nectar-header-text-content {
    	text-align: right;
    }
    #nectar-nav .buttons > .nectar-header-text-content:last-child {
    	 padding-right: 0;
     }

    #nectar-nav .nectar-header-text-content h2,
    #nectar-nav .nectar-header-text-content h3,
    #nectar-nav .nectar-header-text-content h4,
    #nectar-nav .nectar-header-text-content h5,
    #nectar-nav .nectar-header-text-content h6 {
    	margin-bottom: 0;
    }
    #nectar-nav .nectar-header-text-content p {
      margin: 3px 0;
    }

    #nectar-nav .nectar-header-text-content {
    	transition: color 0.2s ease, opacity 0.2s ease;
    }
    #nectar-nav .nectar-header-text-content a {
    	transition: none;
    	position: relative;
    	display: inline-block;
    }
    #nectar-nav[data-lhe="default"]:not(.transparent) .nectar-header-text-content a:hover {
    	transition: color 0.2s ease;
    }
    #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after {
    	transition: transform .3s ease-out, border-color .3s ease-out;
      position: absolute;
      display: block;
      bottom: -3px;
    	height: 2px;
      left: 0;
      width: 100%;
      transform: scaleX(0);
      background-color: #000;
      content: "";
    }
    body[data-header-color="dark"] #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after {
    	background-color: #fff;
    }
    #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:hover:after {
    	transform: scaleX(1);
    }
    #nectar-nav .nectar-header-text-content * {
    	color: inherit;
    }
    #nectar-nav #mobile-menu .nectar-header-text-content div {
    	color: #000;
    }';
  }

  // Hide header menu links when original OCM is open
  if( in_array($theme_skin, ['original','ascend']) &&
      'slide-out-from-right' === $side_widget_class &&
      $user_set_side_widget_area === '1' ) {
    echo '
    #nectar-nav.side-widget-open.hidden-menu #top nav >.sf-menu li,
    #nectar-nav.hidden-menu-items #top nav >.sf-menu li:not(#social-in-menu){
      transition:opacity 0.75s ease
    }
    body:not(.material) #nectar-nav[data-format="centered-menu"].side-widget-open #top nav >.sf-menu:not(.buttons) li,
    body:not(.material) #nectar-nav.side-widget-open.hidden-menu #top nav >.sf-menu:not(.buttons) li,
    body:not(.material) #nectar-nav.side-widget-open.hidden-menu-items #top nav >.sf-menu:not(.buttons) li:not(#social-in-menu){
      opacity:0
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 1.11. Mobile Layout
  /*-------------------------------------------------------------------------*/

  if( 'centered-menu' === $mobile_header_layout ) {

    $header_breakpoint = ( 'left-header' !== $headerFormat && $mobile_breakpoint != 1025 ) ? $mobile_breakpoint : '1024';
    if( $has_main_menu !== 'true' ) {
      $header_breakpoint = '1024';
    }

    echo '@media only screen and (max-width: ' . esc_attr($header_breakpoint) . 'px) {

      body #nectar-nav.transparent #top .span_3 #logo[data-supplied-ml-starting="true"] img.mobile-only-logo.starting-logo {
        transform: translateX(-50%);
        left: 50%;
      }
      #nectar-nav[data-has-menu][data-format] #top .row {
        display: flex;
        justify-content: center;
      }
      #nectar-nav[data-format="centered-menu-under-logo"] .row,
      #nectar-nav[data-format="centered-menu-bottom-bar"] .row {
        flex-direction: row;
      }
      #nectar-nav[data-has-menu][data-format] #top .row .span_3,
      body[data-header-format] #nectar-nav[data-format="centered-menu-under-logo"] #top .row .span_3 {
        margin: 0;
        float: none;
        z-index: 30000;
        width: auto!important;
        position: relative;
        left: 0;
      }
      #nectar-nav[data-has-menu][data-format].material-search-open #top .row .span_3 #logo {
        pointer-events: none;
      }
      #nectar-nav #top .row .col.span_9,
      body[data-header-format] #nectar-nav[data-format="centered-menu-bottom-bar"] #top .row .span_9,
      body[data-header-format] #nectar-nav[data-format="centered-menu-under-logo"] #top .row .span_9 {
        width: 100%!important;
        display: flex!important;
        flex-direction: row;
        align-items: center;
      }
      #nectar-nav #top .row .col.span_9 .slide-out-widget-area-toggle {
        order: 1;
        padding: 0 10px 0 0;
      }
      #nectar-nav #top .col.span_9 .mobile-search {
        order: 2;
      }
      #nectar-nav #top .col.span_9 .nectar-mobile-only.mobile-header {
        order: 5;
        margin-left: 8px;
      }
      #nectar-nav #top .col.span_9 .mobile-user-account {
        order: 3;
        margin-left: auto;
      }
      #nectar-nav #top .col.span_9 #mobile-cart-link {
        order: 4;
        padding-right: 0;
      }
      #nectar-nav #mobile-cart-link .cart-wrap {
        right: -9px;
      }
      body[data-cart="false"] #nectar-nav #top .col.span_9 .mobile-user-account {
        padding-right: 0;
      }

      body:not([data-user-account-button="true"])[data-cart="false"] #nectar-nav #top .col.span_9 .mobile-search,
      body[data-cart="false"][data-user-account-button="false"][data-header-search="false"] #nectar-nav #top .col.span_9 .nectar-mobile-only.mobile-header {
        padding-right: 0;
        margin-left: auto;
      }

      #nectar-nav #top .col.span_9 .nectar-mobile-only.mobile-header ul > li:last-child {
        margin-right: 0;
      }

      body:not([data-user-account-button="true"]) #nectar-nav #top .col.span_9 #mobile-cart-link {
        margin-left: auto;
      }
      #nectar-nav .logo-spacing {
        display: none;
      }';

  echo '}'; // End media query.

  /* Custom mobile breakpoint specific */
  if( ! empty($mobile_breakpoint) && $mobile_breakpoint != 1025 && $headerFormat !== 'left-header' && $has_main_menu === 'true' ) {

    $starting_opacity = (isset($nectar_options['header-starting-opacity']) && ! empty($nectar_options['header-starting-opacity'])) ? $nectar_options['header-starting-opacity'] : '0.75';
    $starting_color = $header_starting_color;
        $mobile_menu_hover = $nectar_options["accent-color"];

        if( ! empty($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom' && ! empty($nectar_options['header-font-hover-color'])) {
            $mobile_menu_hover = $nectar_options['header-font-hover-color'];
        }

    echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {';

      echo '#top .span_9 > .nectar-mobile-only {
        display: flex;
        align-items: center;
      }';

      // Centered Logo Between Menu Alt.
      if( $headerFormat === 'centered-logo-between-menu-alt' ) {
          echo '#nectar-nav #top > .container > .row > .right-aligned-menu-items {
    		  display: none;
    	  }
        #nectar-nav #top > .container > .row > .span_9 {
          pointer-events: all;
        }';
      }

      // Centered menu bottom bar is no longer two rows at this point.
      if( 'centered-menu-bottom-bar' === $headerFormat ) {
        $centerd_header_space = $logo_height + ($header_padding * 2) + $extra_secondary_height;
          echo 'body #nectar-nav-spacer {
    		  padding-top: ' . esc_attr($centerd_header_space) . 'px;
    	  }';
      }

      // Coloring.
      if ( ! nectar_is_contained_header() ) {
        echo '
        #nectar-nav > #top .span_9 > a > span,
        #nectar-nav > #top .span_9 > a > i {
          transition: color 0.25s ease;
        }
        #nectar-nav > #top .span_9 > a {
          transition: opacity 0.25s ease;
        }
        #nectar-nav.transparent > #top .span_9 > a:not(:hover) {
          opacity: ' . esc_attr($starting_opacity) . ';
        }

        #nectar-nav:not(.transparent) > #top .span_9 > a:hover > span,
        #nectar-nav:not(.transparent) > #top .span_9 > a:hover > i {
          color: ' . esc_attr($mobile_menu_hover) . ';
        }

        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
          background-color: ' . esc_attr($starting_color) . '!important;
          opacity: ' . esc_attr($starting_opacity) . ';
        }
        #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
        #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
        #nectar-nav.transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
          background-color: ' . esc_attr($header_starting_dark_color) . '!important;
          opacity: ' . esc_attr($starting_opacity) . ';
        }
        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines-button:after,
        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines:before,
        #nectar-nav.transparent > #top .span_9 > .slide-out-widget-area-toggle:hover .lines:after {
          opacity: 1;
        }

        body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines:after,
        body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines-button:after,
        body #top .span_9 > .slide-out-widget-area-toggle.mobile-icon a:hover .lines:before {
          background-color: ' . esc_attr($mobile_menu_hover) . '!important;
        }

        #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
        #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
        #nectar-nav.light-text > #top .span_9 > .slide-out-widget-area-toggle .lines:after {
          background-color: #fff!important;
        }';
      }

      // Alignment.
      echo '
      body #nectar-nav[data-format="centered-logo-between-menu"] #top #logo,
      body[data-slide-out-widget-area-style="slide-out-from-right"]:not(.material) #nectar-nav[data-format="centered-logo-between-menu"] #top #logo {
				transform: none!important;
			}

      body #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
        position: fixed!important;
      }
      #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
        display: none;
      }
      body:not(.admin-bar) #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
        top: 0px!important;
      }
      body.admin-bar #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"][data-condense="true"] {
        top: 32px!important;
      }
      body.material #nectar-nav[data-format="centered-menu-bottom-bar"][data-condense="true"].fixed-menu #search-outer {
        top: 0;
      }

      #top .col.span_9,
      #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 {
        min-height: 0;
        width: auto!important;
        position: absolute!important;
        right: 0;
        top: 0;
        z-index: 2000;
        height: 100%;
        padding-left: 0!important;
        padding-right: 0!important;
    }

    #nectar-nav #top nav,
    #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_9 > #logo,
    #nectar-nav[data-format="menu-left-aligned"] .row .right-aligned-menu-items {
        display: none!important;
    }
    body #nectar-nav[data-has-menu="true"][data-format="centered-menu-bottom-bar"]:not([data-menu-bottom-bar-align="left"]) #top .span_9 {
      display: flex;
    }
      #top #mobile-cart-link,
      #top .span_9 > .slide-out-widget-area-toggle,
      #top .mobile-search,
      #nectar-nav #top .mobile-user-account {
        display: flex;
        align-items: center;
        position: relative;
        width: auto;
        padding: 0 14px;
        top: auto;
        right: auto;
        margin-bottom: 0;
        margin-top: 0;
        line-height: 0;
        height: 100%;
        -webkit-transform: none;
        transform: none;
      }
      #nectar-nav #top .row .col.span_9 .slide-out-widget-area-toggle {
        padding-right: 14px;
      }

      .material #top .mobile-search .icon-nectar-blocks-search {
        font-size: 20px;
        line-height: 20px;
        height: 20px;
      }
      body.material #nectar-nav #top .mobile-user-account .icon-nectar-blocks-m-user{
        font-size: 20px;
        line-height: 22px;
        height: 20px;
      }
      body.material #nectar-nav #top #mobile-cart-link .icon-nectar-blocks-cart {
        font-size: 20px;
        line-height: 22px;
        height: 21px;
      }
      body.material #nectar-nav #mobile-cart-link .cart-wrap {
          margin-top: -15px;
          right: -10px;
      }';

      // Display mobile only menu items.
      echo '
      #slide-out-widget-area .mobile-only:not(.nectar-header-text-content),
      #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container.mobile-only {
				display: block;
			}';

      // Simple dropdown.
      if( 'simple' === $side_widget_class ) {
        echo 'body[data-slide-out-widget-area-style="simple"] #nectar-nav #mobile-menu {
              top: 100%;
          }

          body[data-slide-out-widget-area-style="simple"][data-ext-responsive="true"] #nectar-nav[data-full-width="false"] #mobile-menu {
            padding: 0 90px;
          }';
      }

      // OCM.
      if( 'slide-out-from-right-hover' === $side_widget_class ) {
        echo '#slide-out-widget-area.slide-out-from-right-hover[data-dropdown-func="separate-dropdown-parent-link"] .off-canvas-menu-container.mobile-only {
          display: block!important;
        }
        body[data-full-width-header="false"] #slide-out-widget-area .slide_out_area_close {
          display: none!important;
        }';
      }

      if( 'fullscreen-split' === $side_widget_class ) {
        echo 'body[data-user-set-ocm="off"] #slide-out-widget-area.fullscreen-split,
              body[data-user-set-ocm="off"] #slide-out-widget-area-bg.fullscreen-split {
          display: block;
        }';
      }

    echo '}'; // Ending media query.

  } // End custom mobile breakpoint specific.

  } // End centered mobile layout.

  // Mobile Icons
  $ocm_menu_icon_display = (isset($nectar_options['header-slide-out-widget-area-icons-display'])) ? esc_html($nectar_options['header-slide-out-widget-area-icons-display']) : 'none';

  if( 'none' === $ocm_menu_icon_display ) {
    echo '.off-canvas-menu-container .nectar-menu-icon,
    .off-canvas-menu-container .nectar-menu-icon-img,
    #nectar-nav #mobile-menu .nectar-menu-icon,
    #nectar-nav #mobile-menu .nectar-menu-icon-img  {
      display: none;
    }';
  }
  else if( 'font_icons_only' === $ocm_menu_icon_display ) {
    echo '.off-canvas-menu-container .nectar-menu-icon-img,
    #nectar-nav #mobile-menu .nectar-menu-icon-img  {
      display: none;
    }';
  }
  else if( 'image_icons_only' === $ocm_menu_icon_display ) {
    echo '.off-canvas-menu-container .nectar-menu-icon,
    #nectar-nav #mobile-menu .nectar-menu-icon  {
      display: none;
    }';
  }

  // Mobile Padding.
  if( isset($nectar_options['header-mobile-padding']) && ! empty($nectar_options['header-mobile-padding']) ) {
    echo '@media only screen and (max-width: 1024px) {
      :root {
       --header-mobile-vertical-padding: ' . esc_attr($nectar_options['header-mobile-padding']) . 'px;
      }
    }';
  }

  if( ( $full_width_header === 'true' || 'slide-out-from-right-hover' === $side_widget_class ) &&
    isset($nectar_options['header-fullwidth-mobile-padding']) && ! empty($nectar_options['header-fullwidth-mobile-padding'])
  ) {
    echo '@media only screen and (max-width: 1024px) {
      :root {
        --header-mobile-fullwidth-horizontal-padding: ' . esc_attr($nectar_options['header-fullwidth-mobile-padding']) . 'px;
        --mobile-container-width: calc( 100% - ( var(--header-mobile-fullwidth-horizontal-padding, 6%) * 2));
      }
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 1.12. Search Core
  /*-------------------------------------------------------------------------*/
  if( 'true' == $header_search ) {

    echo '


    @media only screen and (max-width: 1024px) {

      .material #search-outer #search #close a span {
        font-size: 13px;
        height: 13px;
        line-height: 13px;
        top: 6px;
      }
      #search-outer #search #close a span {
        font-size: 16px;
        height: 16px;
        line-height: 16px;
      }

      #search-outer #search #close a {
        height: 14px;
      }
      #search-outer #search #close,
      body.material #nectar-nav #search-outer #search #close {
        top: 0;
        right: -5px;
      }
      #search-outer #search #close a,
      body.material #nectar-nav #search-outer #search #close a {
        right: 8px;
        top: 9px;
      }

    }

    body[data-bg-header="true"][data-header-search="true"] #nectar-nav[data-has-menu="false"] #top nav ul #search-btn a:after{
      border:none;
      display:none
    }

    #nectar-content-wrap.material-open:after {
      pointer-events: all;
    }
    #nectar-content-wrap:after {
      position: fixed;
      pointer-events: none;
      display: block;
      content: "";
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      background-color: var(--nectar-overlay-color, rgba(0,0,0,0.45));
      z-index: 10000;
      opacity: 0;
      transition: opacity 0.8s cubic-bezier(0.2,1,0.3,1);
    }
    .material-search-open ~ #nectar-content-wrap:after {
      opacity: 1;
    }

    #search-outer{
      top:0;
      left:0;
      width:100%;
      height: 100%;
      position:absolute;
      z-index:10000;
      overflow:visible;
      display:none;
      background-color:#fff
    }

    #search-box{
      position:relative
    }
    #search-outer .container{
      overflow:visible;
      width: 100%;
    }
    #search-outer #search input[type=text]{
      width:100%;
      color:#888;
      font-size:43px;
      line-height:43px;
      position:relative;
      padding:0;
      background-color:transparent;
      border:0;
      -webkit-transition:none;
      transition:none;
      box-shadow:none;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:1px
    }

    #search-outer >#search form{
      width:92%;
      float:left
    }
    #search-outer #search #close{
      list-style:none
    }
    #search-outer #search #close a{
      position:absolute;
      right:0;
      top:24px;
      display:block;
      width:24px;
      height:17px;
      line-height:22px;
      z-index:10
    }
    #search-outer #search #close a span:not(.close-line){
      color:#a0a0a0;
      font-size:18px;
      height:20px;
      line-height:19px;
      background-color:transparent;
      transition:color 0.2s ease
    }
    #search-outer >#search #close a:hover span{
      color:#000
    }
    #nectar-nav #search{
      position:relative
    }

    body #search-outer #search #close a span{
      font-size:20px
    }

    ';

    // responsive
    echo '
    @media only screen and (min-width : 1px) and (max-width: 1024px) {

      body[data-header-format="left-header"]:not(.material) #search-outer #search input[type="text"] {
        font-size: 24px;
        border-bottom-width: 2px;
      }

      body.material #search-outer #search form input[type="text"][name] {
        font-size: 16px;
        line-height: 40px;
        border-bottom-width: 2px;
        padding-right: 50px;
      }


      body.material #search-outer .col {
        margin-bottom: 0;
      }
    }';

    if( 'material' === $theme_skin ) {

      echo '
      @keyframes nectar-search-outer-anim-in {
        0% {
          transform: translateY(-100%);
        }
        100% {
          transform: translateY(0);
        }
      }
      @keyframes nectar-search-inner-anim-in {
        0% {
          transform: translateY(100%);
        }
        100% {
          transform: translateY(0);
        }
      }
      @keyframes nectar-search-outer-anim-out {
        0% {
          opacity: 1;
        }
        100% {
          opacity: 0;
        }
      }

      @media screen and (max-width: 1024px) {

        body.material #nectar-nav #search-outer {
          height: 100%;
        }
        body.material #nectar-nav[data-using-secondary="1"] #search-outer {
          top: auto;
          margin-top: -13px;
        }

        #search-outer #search-box,
        #search-outer #search #close,
        #nectar-nav #search-outer #search-box,
        #nectar-nav #search-outer #search #close {
          -webkit-transform: translate3d(0,100%,0);
          transform: translate3d(0,100%,0);
        }
      }

      body.material #search-outer {
        background-color: transparent;
        position: absolute;
        opacity: 0;
        pointer-events: none;
        z-index: 9999!important;
        padding:0;
        top: -1px;
      }

      body.material[data-header-search="false"] #search-outer {
        visibility: hidden;
      }

      body.material #search-outer.perma-trans {
        position: fixed;
      }
      body.material.admin-bar #search-outer.perma-trans {
        top: 32px;
      }

      body.material #search-outer #search .container {
        height:auto!important;
        float:none;
        width:100%;
      }


      body[data-header-color="dark"].material #nectar-nav #search-outer:before {
        background-color: #000;
      }
      #nectar-nav #search-outer:before {
        transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1);
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 100%;
        display: block;
        background-color: #fff;
        -webkit-transform: scaleY(0);
        transform: scaleY(0);
        -webkit-transform-origin: top;
        transform-origin: top;
      }

      .material #nectar-nav #search-outer:before {
        content: "";
        -webkit-transform: none;
        transform: none;
        backface-visibility: hidden;
      }



      .material #search-outer {
        display: block;
      }
      #search-outer #search {
        max-width: 1200px;
        position: relative;
        z-index: 10;
        margin: 0 auto;
        height: 100%;
        display: -webkit-flex;
        display: -ms-flexbox;
        display: flex;
        -webkit-align-items: center;
        align-items: center;
      }
      .material #search-outer #search,
      .material #nectar-nav #search-outer #search {
        padding: 0;
      }
      #search-outer #search-box,
      #search-outer #search #close {
        transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1), opacity 0.8s cubic-bezier(0.2, 1, 0.3, 1);
        opacity: 0;
      }

      body.material #search-outer.material-open #search > .container{
        -webkit-animation: nectar-search-inner-anim-in 0.8s cubic-bezier(0.2, 1, 0.3, 1) forwards;
        animation: nectar-search-inner-anim-in 0.8s cubic-bezier(0.2, 1, 0.3, 1) forwards;
      }

      #search-outer.material-open #search {
        -webkit-animation: nectar-search-outer-anim-in 0.8s cubic-bezier(0.2, 1, 0.3, 1) forwards;
        animation: nectar-search-outer-anim-in 0.8s cubic-bezier(0.2, 1, 0.3, 1) forwards;
      }

      body.material #search-outer.material-open,
      body.material #nectar-nav #search-outer.material-open {
        opacity: 1;
        pointer-events: all;
      }
      #search-outer.material-open #search-box,
      #search-outer.material-open #search #close,
      #nectar-nav #search-outer.material-open #search-box,
      #nectar-nav #search-outer.material-open #search #close {
        opacity: 1;
      }
      body #search-outer #search input[type="text"] {
        height:auto!important;
        text-transform: none;
        color: #000;
        border-radius: 0;
      }

      body[data-header-color="custom"] #search-outer #search .span_12 span {
        opacity: 0.7;
      }

      body[data-header-color="light"] #nectar-nav #search-outer input::-webkit-input-placeholder {
        color: #000;
      }
      body[data-header-color="dark"] #nectar-nav #search-outer input::-webkit-input-placeholder {
        color: #fff;
      }

      #nectar-nav #search-outer input::-webkit-input-placeholder,
      #nectar-nav #search-outer input::-moz-placeholder,
      #nectar-nav #search-outer input:-ms-input-placeholder {
        color: #888;
      }
      body.material #search-outer > #search form {
        width:100%;
        float:none;
        position: relative;
      }
      body.material #search-outer > #search #search-box .span_12 {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      body.material #search-outer > #search #search-box .span_12:before {
        font-size: 20px;
        content: "\e900";
        font-family: icomoon;
        speak: none;
        font-style: normal;
        font-variant: normal;
        font-weight: 400;
        line-height: 1;
        text-transform: none;
        -webkit-font-smoothing: antialiased;
      }
      #nectar-nav.light-text #search-outer input[type="text"],
      body[data-header-color="dark"] #nectar-nav #search-outer input[type="text"] {
        border-color: #fff;
      }
      #nectar-nav.light-text #search-outer #search .span_12 span,
      body[data-header-color="dark"] #nectar-nav #search-outer #search .span_12 span {
        color: rgba(255,255,255,0.6);
      }
      #nectar-nav.light-text #search-outer #search #close a span,
      body[data-header-color="dark"] #nectar-nav #search-outer #search #close a span,
      body[data-header-color="dark"] .nectar-ajax-search-results .search-post-item,
      body[data-header-color="dark"] .nectar-ajax-search-results ul.products li.product {
        color: #fff;
      }
      #nectar-nav.light-text #search-outer input::-webkit-input-placeholder,
      body[data-header-color="dark"] #nectar-nav #search-outer input::-webkit-input-placeholder {
        color: rgba(255,255,255,0.5);
      }
      #nectar-nav.light-text #search-outer input::-moz-placeholder,
      body[data-header-color="dark"] #nectar-nav #search-outer input::-moz-placeholder {
        color: rgba(255,255,255,0.5);
      }
      #nectar-nav.light-text #search-outer input:-ms-input-placeholder,
      body[data-header-color="dark"] #nectar-nav #search-outer input:-ms-input-placeholder {
        color: rgba(255,255,255,0.5);
      }
      #search-outer #search #close {
        position:absolute;
        right:40px
      }

      body[data-ext-responsive="false"].material #search-outer #search #close {
        right: 0;
      }
      body[data-ext-responsive="false"].material #search-outer #search #close a {
        right: 15px;
      }

      body.material #search-outer #search input[type="text"]{
        padding-right: 70px;
      }
      body.material #search-outer #search #close a {
        right:64px;
        top:16px;
      }

      #search-outer #search .span_12 span {
        display:block;
        color:rgba(0,0,0,0.4);
        margin-top:15px
      }
      @media only screen and (max-width: 1024px) {
        #search-outer #search .span_12 span {
          display: none;
        }
      }

      body.material #search-outer #search #close a span {
        color:#000;
      }
      body #search-outer .ui-widget-content {
        top:90px
      }';
    }

    } else {
      echo '#search-outer, #nectar-nav .bg-color-stripe {
        display: none;
      }';
    }

  /*-------------------------------------------------------------------------*/
  /* 1.13. Ext Search
  /*-------------------------------------------------------------------------*/

  if( true === $ext_search_active || nectar_is_contained_header() && 'material' === $theme_skin && 'true' === $header_search ) {

    if( $headerFormat !== 'left-header' ) {

      if( $headerFormat === 'centered-menu-bottom-bar' ) {
        echo '
        @media only screen and (min-width: ' . esc_attr($mobile_breakpoint) . 'px) {


          body.material [data-format="centered-menu-bottom-bar"][data-condense="true"].fixed-menu #search-outer {
            top: ' . $material_header_space . 'px;
          }
        }';

        }

    }

    echo '

    .material #nectar-nav #search-outer:not(.material-open):before {
      transform: scaleY(0);
    }
    .material #nectar-nav #search-outer:before {
      transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1);
    }
    #search-outer:not(.material-open) {
      pointer-events: none;
    }

    body.material #nectar-nav:not([data-format="left-header"]) #search-outer {
      transform: none;
      -webkit-transform: none;
    }


    @media only screen and (min-width: 1025px) {
      body[data-header-format="left-header"] #search-outer:not(.material-open) #search {
        transform: translate3d(0,100%,0);
        -webkit-transform: translate3d(0,100%,0);
      }
    }

    #search-outer #search {
      overflow: hidden;
    }

    #search-outer #search {
      height: 100%;
    }

    #search-outer .nectar-ajax-search-results {
      position: absolute;
      top: 100%;
      left: 0;
      width: 100%;
      background-color: #fff;
      max-height: 0;
      overflow: hidden;
    }
    #search-outer.material-open .nectar-ajax-search-results {
      transition: transform 0.8s cubic-bezier(0.2, 0.6, 0.4, 1), max-height 0.8s cubic-bezier(0.2, 0.6, 0.4, 1);
    }


    .results-shown #search-outer #search,
    .results-shown #search-outer #search-box,
    .results-shown #search-outer #search #close {
      transition: transform 0.4s cubic-bezier(0.2, 0.6, 0.4, 1) 0.2s, opacity 0.4s cubic-bezier(0.2, 0.6, 0.4, 1) 0.2s;
    }

    #nectar-nav:not([data-format="left-header"]).results-shown header {
      transition: all 0.2s ease 0.3s;
    }

    .results-shown #search-outer .nectar-ajax-search-results {
      transition: transform 0.3s cubic-bezier(0.68, 0.01, 1, 1), max-height 0.3s cubic-bezier(0.68, 0.01, 1, 1);
    }


    #search-outer .nectar-ajax-search-results .inner {
      max-height: calc(100vh - 225px);
      padding-bottom: 30px;
      overflow-y: auto;
      overflow-x: hidden;
      position: relative;
    }

    .admin-bar #search-outer .nectar-ajax-search-results .inner {
      max-height: calc(100vh - 282px);
    }



    @media screen and (max-width: 1024px) {

      #nectar-nav[data-format="left-header"].material-search-open header {
        opacity: 0;
      }
      body[data-header-format="left-header"].admin-bar.material #search-outer {
        top: 32px;
      }
      body[data-header-format="left-header"].material #search-outer {
        background-color: transparent;
      }

      body[data-header-format="left-header"] #search-outer,
      body.material #nectar-nav #search-outer {
        transform: none;
        -webkit-transform: none;
      }


    }


    #search-outer .nectar-ajax-search-results .inner::-webkit-scrollbar {
        width: 10px;
    }
    #search-outer .nectar-ajax-search-results .inner::-webkit-scrollbar {
    	background-color: rgba(0, 0, 0, 0.07);
    }
    #search-outer .nectar-ajax-search-results .inner::-webkit-scrollbar:hover {
      background-color: rgba(0, 0, 0, 0.09);
    }
    #search-outer .nectar-ajax-search-results .inner::-webkit-scrollbar:hover {
    	background-color: rgba(0, 0, 0, 0.11);
    }
    #search-outer .nectar-ajax-search-results .inner::-webkit-scrollbar-thumb {
    	background: rgba(0,0,0,0.25);
      background-clip: padding-box;
      min-height: 10px;
    }

    ';

    $limit_search = ( isset($nectar_options['header-search-limit']) ) ? $nectar_options['header-search-limit'] : 'all';
    $ajax_search_style = ( isset($nectar_options['header-ajax-search-style']) ) ? $nectar_options['header-ajax-search-style'] : 'default';

    // Limit search styling.
    if( 'product' === $limit_search && 'extended' === $ajax_search_style ) {

      $product_style = ( ! empty( $nectar_options['product_style'] ) ) ? $nectar_options['product_style'] : 'classic';

      if( 'material' === $product_style ) {
        echo '
        #search-outer .woocommerce .material.product:hover .product-wrap .product-meta > .price {
          opacity: 1;
        }
        #search-outer .woocommerce ul.products li.product a {
          position: relative;
        }
        #search-outer .woocommerce .material.product .product-wrap .product-add-to-cart,
        #search-outer .woocommerce .material.product:hover:before {
          display: none;
        }
        #search-outer .products li.product.material {
            border-radius: var(--nectar-product-border-radius, 6px);
            transition: box-shadow 0.25s ease, opacity 0.6s cubic-bezier(0.2, 0.6, 0.4, 1), transform 0.6s cubic-bezier(0.2, 0.6, 0.4, 1);
            box-shadow: rgba(0, 0, 0, 0.06) 0 0 0 1px,
                        rgba(0, 0, 0, 0.03) 0 2px 7px;
        }
        #search-outer .products li.product.material:hover {
            box-shadow: rgba(0, 0, 0, 0.1) 0 0 0 1px,
                        rgba(0, 0, 0, 0.07) 0 4px 8px;
        }
        #search-outer .woocommerce .material.product:hover h3,
        #search-outer .woocommerce .material.product:hover .product-meta > .price,
        #search-outer .woocommerce .material.product:hover h2 {
          transform: none;
        }';
      }

      else if( 'classic' === $product_style ) {
        echo '
        #search-outer .woocommerce ul.products li.product a {
          position: relative;
        }
        #search-outer .woocommerce .classic .product-wrap a.button,
        #search-outer .woocommerce .classic .product-wrap .product-add-to-cart[data-nectar-quickview="true"] {
          display: none;
        }';
      }
      else if( 'minimal' === $product_style ) {

        $product_minimal_hover_effect = ( isset( $nectar_options['product_minimal_hover_effect'] ) ) ? esc_html($nectar_options['product_minimal_hover_effect']) : 'default';

        if( 'default' === $product_minimal_hover_effect ) {
          echo '#search-outer .products li.product.minimal:hover {
              box-shadow: rgba(0, 0, 0, 0.08) 0 0 0 1px,
                          rgba(0, 0, 0, 0.05) 0 3px 8px;
          }';
        }

        echo '
        #search-outer .background-color-expand {
          display: none;
        }
        #search-outer .products li.product.minimal {
            border-radius: 6px;
            transition: box-shadow 0.25s ease, opacity 0.6s cubic-bezier(0.2, 0.6, 0.4, 1), transform 0.6s cubic-bezier(0.2, 0.6, 0.4, 1);
        }

        #search-outer ul.products li.minimal.product .product-wrap {
          overflow: hidden;
        }
        #search-outer .products li.product.minimal:hover .product-meta .price {
            opacity: 1;
         }
        #search-outer .products li.product.minimal.hover-bound:hover .product-meta {
           transform: none;
         }
         #search-outer .products li.product.minimal .product-add-to-cart {
            display: none;
          }
        ';
      }
      else if( 'text_on_hover' === $product_style ) {
        echo '#search-outer ul.products .text_on_hover.product > .button {
           display: none;
         }';
      }

      echo '
      #search-outer .woocommerce ul.products .text_on_hover.product .add_to_cart_button,
      #search-outer .products li.product .nectar_quick_view {
        display: none;
      }

      .nectar-ajax-search-results li.product .woocommerce-loop-product__title {
        color: inherit;
      }

      @media only screen and (min-width: 1025px) {
        #search-outer ul.products {
          width: 101.5%;
          padding: 3px;
        }
        #search-outer ul.products > li:nth-child(6) {
          display: none;
        }
        #search-outer ul.products li.product {
          width: 18.4%;
          margin: 0 1.5% 1.5% 0%;
        }

      }
      ';
    } // end product limit.

    // Extended list ajax styling - non product.
    else if( 'extended' === $ajax_search_style ) {

      echo '
      .nectar-ajax-search-results .header {
        flex: 1;
        line-height: 1.3;
        padding: 20px;
      }
      .nectar-ajax-search-results .nectar-search-results {
        display: -webkit-flex;
        display: flex;
        -webkit-flex-wrap: wrap;
        flex-wrap: wrap;
      }
      .nectar-ajax-search-results .post-featured-img {
        width: 100%;
        padding-bottom: 65%;
        background-size: cover;
        background-position: center;
        display: block;
      }
      .nectar-ajax-search-results a {
        color: inherit;
      }
      .nectar-ajax-search-results .meta {
        display: block;
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 3px;
      }
      .nectar-ajax-search-results .meta-type {
        margin: 3px 0 0 0;
      }
      .nectar-ajax-search-results .search-post-item h5 {
        margin-bottom: 0;
        line-height: 1.3;
      }
      .nectar-ajax-search-results .search-post-item {
        border-radius: 6px;
        overflow: hidden;
        transition: box-shadow 0.25s ease, opacity 0.6s cubic-bezier(0.2, 0.6, 0.4, 1), transform 0.6s cubic-bezier(0.2, 0.6, 0.4, 1);
        box-shadow: rgba(0, 0, 0, 0.06) 0 0 0 1px,
                    rgba(0, 0, 0, 0.03) 0 2px 7px;
      }
      .nectar-ajax-search-results .search-post-item:hover {
          box-shadow: rgba(0, 0, 0, 0.1) 0 0 0 1px,
                      rgba(0, 0, 0, 0.07) 0 4px 8px;
      }

      @media only screen and (max-width: 1024px) {
        .nectar-ajax-search-results .nectar-search-results > div:nth-child(2n+2) {
          margin-right: 0;
        }
        .nectar-ajax-search-results .search-post-item {
          width: 49.25%;
          margin: 0 1.5% 1.5% 0%;
        }
      }

      @media only screen and (min-width: 1025px) {
        .nectar-ajax-search-results .nectar-search-results {
          width: 101.5%;
          padding: 3px;
        }
        .nectar-ajax-search-results .search-post-item {
          width: 18.4%;
          margin: 0 1.5% 1.5% 0%;
        }
        .nectar-ajax-search-results .nectar-search-results > div:nth-child(6) {
          display: none;
        }

      }
      @media only screen and (max-width: 767px) {
        .nectar-ajax-search-results .search-post-item h5 {
          font-size: 14px;
        }
        .nectar-ajax-search-results .meta {
          font-size: 13px;
        }
      }';
    }
    // Simple ajax style
    else {
      echo '
      .nectar-ajax-search-results .header {
        flex: 1;
      }
      .nectar-ajax-search-results .post-featured-img {
        border-radius: 8px;
        height: 60px;
        width: 60px;
        background-position: center;
        background-size: cover;
        margin-right: 15px;
      }
      .nectar-ajax-search-results a {
        color: inherit;
      }
      .nectar-ajax-search-results .meta {
        display: block;
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 3px;
      }
      .nectar-ajax-search-results .search-post-item h5 {
        margin-bottom: 0;
        line-height: 1.3;
      }
      .nectar-ajax-search-results .search-post-item a {
        display: flex;
        align-items: center;
        min-height: 60px;
        margin: 0 0 25px 0;
      }
      @media only screen and (max-width: 767px) {
        .nectar-ajax-search-results .search-post-item h5 {
          font-size: 14px;
        }
        .nectar-ajax-search-results .meta {
          font-size: 13px;
        }
      }';
    }

    echo '.nectar-ajax-search-results h5 {
      color: inherit;
      display: inline;
    }
    .nectar-ajax-search-results h5 a {
      position: relative;
    }';

  } // ext search enabled

  /*-------------------------------------------------------------------------*/
  /*  1.14. Search Typography
  /*-------------------------------------------------------------------------*/
  $header_search_font_size = ( isset($nectar_options['header-search-type-size']) ) ? $nectar_options['header-search-type-size'] : false;

  if( false !== $header_search_font_size ) {
    echo '
    @media only screen and (min-width: 1025px) {
        #search-outer #search #search-box input[type="text"] {
        font-size: ' . intval($header_search_font_size) . 'px;
        line-height: 1;';
      echo '}';

      if( 'material' === $theme_skin ) {
        echo '
        #search-outer #search #close {
          top: 50%;
          margin-top: -28px;
        }';
      }

      echo '}'; // end media query.
  }

  /*-------------------------------------------------------------------------*/
  /*  1.15. Shadows / overlays
  /*-------------------------------------------------------------------------*/
  $header_box_shadow = ( isset($nectar_options['header-box-shadow']) ) ? $nectar_options['header-box-shadow'] : false;

  if( 'small' === $header_box_shadow ) {
    echo '#nectar-nav[data-box-shadow="small"],
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="small"][data-rm-fixed="0"]:not(.transparent):not(.invisible),
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="small"][data-rm-fixed="1"]:not(.transparent){
      box-shadow:0 0 3px 0 rgba(0,0,0,0.22);
    }';
  }
  if( 'large' === $header_box_shadow ) {
    echo '#nectar-nav[data-box-shadow="large"],
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="large"][data-rm-fixed="0"]:not(.transparent):not(.invisible),
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="large"][data-rm-fixed="1"]:not(.transparent) {
      box-shadow:0 3px 45px rgba(0,0,0,0.15);
    }';
  }
  if( 'large-line' === $header_box_shadow ) {
    echo '#nectar-nav[data-box-shadow="large-line"],
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="large-line"][data-rm-fixed="0"]:not(.transparent):not(.invisible),
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="large-line"][data-rm-fixed="0"]:not(.transparent):not(.fixed-menu).scrolling,
    body.material[data-hhun="1"] #nectar-nav[data-header-resize="0"][data-box-shadow="large-line"][data-rm-fixed="1"]:not(.transparent) {
      box-shadow: 0 0 2px rgba(0,0,0,0.14), 0 18px 40px rgba(0,0,0,0.045);
    }';
  }

  $header_dropdown_overlay = ( isset($nectar_options['header-dropdown-overlay']) ) ? $nectar_options['header-dropdown-overlay'] : '0';

  if ( '1' === $header_dropdown_overlay ) {
    echo 'body:before {
      background-color: var(--nectar-overlay-color, rgba(0,0,0,0.45));
      position: fixed;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.4s ease;
      pointer-events: none;
      content: "";
    }
    body:has(#nectar-nav .menu-item-has-children.sfHover):before,
    body:has(#nectar-nav .sfHover .nectar-global-section-megamenu):before {
      opacity: 1;
    }';
  }

  /*-------------------------------------------------------------------------*/
  /*  1.16. Animations
  /*-------------------------------------------------------------------------*/

  /* Header Transparent logo is same as regular logo */
  if( isset($nectar_options['header-starting-logo']) && isset($nectar_options['header-starting-logo']['url']) &&
      isset($nectar_options['logo']) && isset($nectar_options['logo']['url'])) {

        if( $nectar_options['header-starting-logo']['url'] == $nectar_options['logo']['url'] ) {
          echo '#nectar-nav[data-transparent-header="true"] #logo img {
            transition: height 0.32s ease;
          }';
        }

  }
  if( isset($nectar_options['header-starting-logo-dark']) && isset($nectar_options['header-starting-logo-dark']['url']) &&
      isset($nectar_options['logo']) && isset($nectar_options['logo']['url'])) {

        if( $nectar_options['header-starting-logo-dark']['url'] == $nectar_options['logo']['url'] ) {
          echo '#nectar-nav[data-permanent-transparent="false"][data-transparent-header="true"].dark-slide #logo img {
            transition: height 0.32s ease;
          }';
        }

  }

  /* Header resize */
  if( '1' === $headerResize && 'left-header' !== $headerFormat ) {

    echo '#nectar-nav[data-header-resize="1"] #logo,
    #nectar-nav[data-header-resize="1"] .logo-spacing {
      transition: margin 0.32s ease, color 0.32s ease;
    }
    #nectar-nav[data-header-resize="1"] #logo img,
    #nectar-nav[data-header-resize="1"] .logo-spacing img {
      transition: height 0.32s ease, opacity 0.2s ease;
    }
    #nectar-nav[data-header-resize="1"]:not([data-transparent-header="true"]) #logo,
    #nectar-nav[data-header-resize="1"]:not([data-transparent-header="true"]) .logo-spacing {
      transition: margin 0.15s ease, color 0.32s ease;
    }
    #nectar-nav[data-header-resize="1"]:not([data-transparent-header="true"]) #logo img,
    #nectar-nav[data-header-resize="1"]:not([data-transparent-header="true"]) .logo-spacing img {
      transition: height 0.15s ease, opacity 0.2s ease;
    }

    #nectar-nav[data-header-resize="1"].small-nav:not([data-transparent-header="true"]) #logo,
    #nectar-nav[data-header-resize="1"].small-nav:not([data-transparent-header="true"]) .logo-spacing {
      transition: margin 0.3s ease;
    }
    #nectar-nav[data-header-resize="1"].small-nav:not([data-transparent-header="true"]) #logo img,
    #nectar-nav[data-header-resize="1"].small-nav:not([data-transparent-header="true"]) .logo-spacing img {
      transition: height 0.3s ease, opacity 0.2s ease;
    }

    #nectar-nav.small-nav {
      transition: box-shadow 0.42s ease, opacity 0.3s ease;
    }
    #nectar-nav.small-nav #logo,
    #nectar-nav.small-nav .logo-spacing {
      transition: margin 0.32s ease;
    }
    #nectar-nav.small-nav #logo img,
    #nectar-nav.small-nav .logo-spacing img {
      transition: height 0.32s ease;
    }
    #nectar-nav[data-using-secondary="1"].small-nav {
      transition: box-shadow 0.42s ease, opacity 0.3s ease, transform 0.32s ease;
    }';
  }

  /*-------------------------------------------------------------------------*/
  /*  1.17. OCM Alignment
  /*-------------------------------------------------------------------------*/
  $side_widget_area_pos = ( isset( $nectar_options['ocm_btn_position'] ) ) ? esc_html($nectar_options['ocm_btn_position']) : 'default';

  if( 'left' === $side_widget_area_pos && $side_widget_class !== 'simple' ) {

    $bottom_bar_align = isset($nectar_options['centered-menu-bottom-bar-alignment']) ? $nectar_options['centered-menu-bottom-bar-alignment'] : 'center';

    //Custom Mobile Breakpoint
    if(! empty($mobile_breakpoint) && $mobile_breakpoint != 1025 &&
    $headerFormat !== 'left-header' &&
    $has_main_menu === 'true' ) {

      echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {';

        if( 'centered-menu' !== $mobile_header_layout ) {

          //// Bottom Bar.
          if( $headerFormat === 'centered-menu-bottom-bar' ) {

            echo '#top .span_3 .left-aligned-ocm .slide-out-widget-area-toggle {
              display: flex!important;
            }

            #nectar-nav[data-format="centered-menu-bottom-bar"] #top .slide-out-widget-area-toggle.mobile-icon,
            #nectar-nav[data-format="centered-menu-bottom-bar"]:not([data-menu-bottom-bar-align="left"]) #top .span_3 .slide-out-widget-area-toggle.mobile-icon {
              display: none!important;
            }';

          }

          echo 'body #nectar-nav[data-format="' . esc_attr($headerFormat) . '"] header#top  .left-aligned-ocm[data-user-set="off"] {
            display: flex!important;
          }';

          // centered-logo-between-menu-alt
          if( $headerFormat === 'centered-logo-between-menu-alt' ) {
            echo ' #nectar-nav[data-format="centered-logo-between-menu-alt"] #top .slide-out-widget-area-toggle.mobile-icon {
              display: none!important;
            }
            #nectar-nav[data-format="centered-logo-between-menu-alt"] #top .span_3 .left-aligned-ocm,
            body #nectar-nav[data-format="centered-logo-between-menu-alt"] header#top .span_3 .left-aligned-ocm[data-user-set="off"] {
              display: flex!important;
            }';

          }

          //// General.
          if( in_array(NectarThemeManager::$header_format, ['default','centered-menu','menu-left-aligned']) ) {
            echo '
            body #nectar-nav #top .left-aligned-ocm {
              display: flex;
            }
            #nectar-nav:not([data-format="centered-menu-bottom-bar"]) #top .span_9 > .slide-out-widget-area-toggle {
              display: none!important;
            }';
          }

        }

        //// centered-menu
        else {
          echo ' #nectar-nav #top .left-aligned-ocm[data-user-set="1"] {
             display: none!important;
          }
          #top .span_9 > .slide-out-widget-area-toggle a.using-label {
            display: flex!important;
            align-items: center;
            flex-direction: row-reverse;
          }
          #top .span_9 > .slide-out-widget-area-toggle a.using-label .label {
            margin: 0 0 0 15px;
          }';
        }

        echo '}'; // end media query.

    }

    echo '
    @media only screen and (min-width: 1025px) {
      #nectar-nav #top .left-aligned-ocm:not([data-user-set="1"]) {
        display: none!important;
      }
      #nectar-nav[data-format="centered-logo-between-menu-alt"] #top .left-aligned-ocm:not([data-user-set="1"]) {
        display: none!important;
      }
    }
   ';

    if( 'centered-menu' !== $mobile_header_layout ) {
      echo '
      @media only screen and (max-width: 1024px) {';

        //// Bottom Bar.
        if( $headerFormat === 'centered-menu-bottom-bar' ) {

          if( $bottom_bar_align === 'center' ) {
            echo ' body #nectar-nav[data-format=centered-menu-bottom-bar] .span_3 {
              display: flex!important;
              justify-content: flex-end;
              flex-direction: row-reverse;
              align-items: center;
            }';
          } else {
            echo ' body #nectar-nav[data-format=centered-menu-bottom-bar] .span_3 {
              display: flex!important;
              align-items: center;
            }';
          }

          echo '
          #nectar-nav[data-format=centered-menu-bottom-bar] #top .span_3 nav.left-side {
            display: inline-block!important;
            position: relative;
          }
          #nectar-nav #top .span_9 > .slide-out-widget-area-toggle,
          #nectar-nav[data-format=centered-menu-bottom-bar] #top .span_3 nav.left-side > *:not(.left-aligned-ocm)  {
            display: none!important;
          }
          #nectar-nav header#top .left-side ul {
            padding: 0;
          }
         ';
        }

        if( $headerFormat === 'centered-logo-between-menu-alt' ) {
          echo '#nectar-nav #top .span_3 .left-aligned-ocm {
          display: flex!important;
          }';
        }

        echo '#nectar-nav:not([data-format="centered-menu-bottom-bar"]) #top .span_9 > .slide-out-widget-area-toggle {
          display: none!important;
        }
        #nectar-nav #top .left-aligned-ocm {
          display: flex;
        }

        #nectar-nav #top .col.span_3 {
          display: flex;
        }
        #nectar-nav[data-transparent-header="true"].transparent #logo {
          position: relative;
        }
        body #nectar-nav #top .left-aligned-ocm {
          margin-right: 18px;
        }';

      echo '}';
    }
    // Centered logo mobile layout
    else {
      echo '
      @media only screen and (max-width: 1024px) {
        #nectar-nav .span_3 .left-aligned-ocm,
        #nectar-nav #top .left-aligned-ocm[data-user-set="1"] {
           display: none!important;
        }
        #top .span_9 > .slide-out-widget-area-toggle a.using-label {
          display: flex!important;
          align-items: center;
          flex-direction: row-reverse;
        }
        #top .span_9 > .slide-out-widget-area-toggle a.using-label .label {
          margin: 0 0 0 15px;
        }
      }';

    }

    if( $headerFormat === 'centered-logo-between-menu-alt' ) {
      echo '#nectar-nav #top .span_3 .left-aligned-ocm[data-user-set="1"] {
        display: none;
      }';
    }

    echo '#nectar-nav #top .left-aligned-ocm[data-user-set="1"] {
      display: flex;
    }

    #nectar-nav #top .left-aligned-ocm a {
      display: flex!important;
      align-items: center;
      flex-direction: row-reverse;
    }

    #nectar-nav .left-aligned-ocm {
      align-items: center;
      margin: 0 28px 0 0;
    }

    #nectar-nav #top .left-aligned-ocm .label {
      margin: 0 0 0 15px;
    }';
  }

  // Left Aligned OCM
  if( 'centered-menu' === $mobile_header_layout ||
      'left' === $side_widget_area_pos ) {

    $header_nav_breakpoint = ( 'left-header' !== $headerFormat && $mobile_breakpoint != 1025 ) ? $mobile_breakpoint : '1024';

    if( $has_main_menu !== 'true' ) {
      $header_nav_breakpoint = '1024';
    }

      if( 'left' === $side_widget_area_pos ) {

        if( 'slide-out-from-right' === $side_widget_class && 'material' === $theme_skin ) {
          echo 'body #slide-out-widget-area.slide-out-from-right:not(.material-open) .bottom-meta-wrap,
          body #slide-out-widget-area.slide-out-from-right:not(.material-open) .inner > div {
            transform: translateX(-100px);
          }';
        }

      }

      //// Move OCM to left on all viewports
      if( 'left' === $side_widget_area_pos ) {
        $header_nav_breakpoint = '9999';
      }

    echo '@media only screen and (max-width: ' . esc_attr($header_nav_breakpoint) . 'px) {';

    if( 'slide-out-from-right' === $side_widget_class ) {
      echo '
        body #slide-out-widget-area.slide-out-from-right {
          right: auto;
          left: 0;
        }';
    }
    if( 'slide-out-from-right-hover' === $side_widget_class ) {
      echo 'body #slide-out-widget-area.slide-out-from-right-hover {
          transform: translate3d(-101%,0,0);
            -webkit-transform: translate3d(-101%,0,0);
            -ms-transform: translate3d(-101%,0,0);
            right: auto;
            left: 0;
            opacity: 0;
            transition: transform .7s cubic-bezier(0.25,1,.2,1), opacity .2s ease 0.3s;
      }
      body #slide-out-widget-area.slide-out-from-right-hover.open {
            transition: transform .7s cubic-bezier(0.25,1,.2,1), opacity .1s ease 0s;
      }
      body .slide-out-hover-icon-effect.small {
        left: 27px;
        right: auto;
      }';

      if( 'left' === $side_widget_area_pos ) {
        echo '
        body #slide-out-widget-area.slide-out-from-right-hover .inner,
        body #slide-out-widget-area.slide-out-from-right-hover .bottom-meta-wrap {
          transform: translateX(-50px);
        }
        .admin-bar .slide-out-hover-icon-effect {
          top: 62px!important;
        }
        .slide-out-hover-icon-effect {
          top: 30px!important;
        }';
      }

    }
    else if( 'slide-out-from-right' === $side_widget_class ) {
      echo 'html body[data-slide-out-widget-area-style="slide-out-from-right"] #slide-out-widget-area {
        -webkit-animation: nectar_ocm_reveal_left_close 0.7s cubic-bezier(.15,0.2,.1,1) forwards;
        animation: nectar_ocm_reveal_left_close 0.7s cubic-bezier(.15,0.2,.1,1) forwards;
      }

      html body[data-slide-out-widget-area-style="slide-out-from-right"] #slide-out-widget-area.material-open {
        -webkit-animation: nectar_ocm_reveal_left_open 0.7s cubic-bezier(.15,0.2,.1,1) forwards;
        animation: nectar_ocm_reveal_left_open 0.7s cubic-bezier(.15,0.2,.1,1) forwards;
      }';
    }

    echo '}'; // end media query

    //// Within custom breakpoint.
    if( ! empty($mobile_breakpoint) && $mobile_breakpoint != 1025 && $headerFormat !== 'left-header' && $has_main_menu === 'true' ) {

      echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {';
        if( 'slide-out-from-right' === $side_widget_class && 'material' === $theme_skin ) {

          echo '
          body[data-slide-out-widget-area-style="slide-out-from-right"] .follow-body.slide_out_area_close {
            right: auto!important;
            left: 0!important;
          }
          body[data-slide-out-widget-area-style="slide-out-from-right"] .follow-body.slide_out_area_close {
            -webkit-transform: translateY(-50%) translateX(35.55vw)!important;
            transform: translateY(-50%) translateX(35.55vw)!important;
          }
          body[data-slide-out-widget-area-style="slide-out-from-right"].material .ocm-effect-wrap.material-ocm-open {
            -webkit-transform: scale(0.8) translateX(32vw) translateZ(0)!important;
            transform: scale(0.8) translateX(32vw) translateZ(0)!important;
            -webkit-transform-origin: center;
            transform-origin: center;
          }';

        }
      echo '}';

    }

    if( 'slide-out-from-right' === $side_widget_class && 'material' === $theme_skin ) {
      echo '@media only screen and (max-width: 450px) {
        body[data-slide-out-widget-area-style="slide-out-from-right"].material .slide_out_area_close {
          display: none;
        }
        body[data-slide-out-widget-area-style="slide-out-from-right"].material .ocm-effect-wrap.material-ocm-open {
          -webkit-transform: scale(0.835) translateX(93vw) translateZ(0)!important;
          transform: scale(0.835) translateX(93vw) translateZ(0)!important;
        }
      }';
    }

    echo 'body:not(.material) #slide-out-widget-area.slide-out-from-right:not(.open) {
      -webkit-transform: translate(301px,0px)!important;
      transform: translate(301px,0px)!important;
    }


    body #slide-out-widget-area.slide-out-from-right-hover {
          opacity: 0;
          transition: transform .7s cubic-bezier(0.25,1,.2,1), opacity .2s ease 0.3s;
    }
    body #slide-out-widget-area.slide-out-from-right-hover.open {
          opacity: 1;
          transition: transform .7s cubic-bezier(0.25,1,.2,1), opacity .1s ease 0s;
    }';

  }

  /*-------------------------------------------------------------------------*/
  /*  1.18. Off Canvas Menu Icon Width
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-slide-out-widget-area-icon-width']) ) {
    echo '
    :root {
      --ocm-icon-width: ' . esc_attr($nectar_options['header-slide-out-widget-area-icon-width']) . 'px;
    }';
  }

  /*-------------------------------------------------------------------------*/
  /*  1.19. Background Blur
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['header-blur-bg']) && $nectar_options['header-blur-bg'] === '1' ) {

    $header_blur_func = (isset($nectar_options['header-blur-bg-func'])) ? $nectar_options['header-blur-bg-func'] : 'active_non_transparent';

    if( $header_blur_func === 'active_non_transparent' ) {
      echo '#nectar-nav:not(.transparent) {
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
     }';
    }
    else {
      echo '#nectar-nav {
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
     }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /*  1.20. Header Size
  /*-------------------------------------------------------------------------*/

  if ( nectar_is_contained_header() ) {
    echo '
    body #nectar-nav-spacer {
      background-color: transparent!important;
    }
    body #nectar-nav {
      transition: box-shadow 0.35s ease;
    }

    #nectar-nav[data-box-shadow="small"] {
      box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    }

    body #nectar-nav, body #nectar-nav-spacer {
      width: calc(100% - var(--container-padding)*2);
      max-width: calc(var(--container-width) - var(--container-padding)*2);
      left: 0;
      right: 0;
      margin-left: auto;
      margin-right: auto;
      margin-top: max(calc(var(--container-padding)/3), 25px);
    }

    body #nectar-nav .container {
      padding-left: max(calc(var(--container-padding)/3), 25px);
      padding-right: max(calc(var(--container-padding)/3), 25px);
    }
    .nectar_hook_before_secondary_header {
      padding-top: max(calc(var(--container-padding)/3), 25px);
    }

    body[data-bg-header="true"].single-product .nectar_hook_global_section_after_header_navigation {
      margin-bottom: 40px;
    }

    @media only screen and (max-width: 1024px) {
      body #nectar-nav, body #nectar-nav-spacer {
          max-width: var(--mobile-container-width);
          width: 100%;
      }
      body #nectar-nav .container {
        max-width: 100%;
      }

      body #nectar-nav[data-full-width="true"] #mobile-menu:before {
        width: 100%;
        left: 0;
      }

    }

    @media only screen and (max-width: 767px) {

      body #nectar-nav .container {
        padding-left: min(max(calc(var(--container-padding)/3),25px), 22px);
        padding-right: min(max(calc(var(--container-padding)/3),25px), 22px);
      }
    }

    @media only screen and (min-width : 1px) and (max-width: 1024px) {
      body #nectar-nav #top .span_3 #logo[data-supplied-ml="true"] img:not(.mobile-only-logo) {
        display: none!important;
      }
      body #nectar-nav #top .span_3 #logo[data-supplied-ml="true"] .mobile-only-logo {
        display: block!important;
      }
    }

    ';

    // megamenu.
    echo 'body #nectar-nav nav .nectar-global-section-megamenu {
      left: 0;
      margin-left: calc(max(calc(var(--container-padding)/3),25px) * -1)!important;
      max-width: calc(100% + max(calc(var(--container-padding)/3),25px) * 2);
      width: calc(100% + max(calc(var(--container-padding)/3),25px) * 2);
    }
    body #nectar-nav nav .nectar-global-section-megamenu > .inner > [class*="wp-block"].alignfull {
      left: 0;
      margin-left: calc(var(--wp--style--root--padding-left,50px) * -1)!important;
      max-width: calc(100% + var(--wp--style--root--padding-left,50px) * 2);
      width: calc(100% + var(--wp--style--root--padding-left,50px) * 2);
    }
    #nectar-nav:has(.nectar-megamenu-menu-item.sfHover) {
      border-bottom-left-radius: 0;
      border-bottom-right-radius: 0;
    }';

    // hhun
    echo '
    body[data-hhun="1"] #nectar-nav.invisible:not(.side-widget-open),
    body[data-hhun="1"] #nectar-nav.no-trans-hidden:not(.side-widget-open) {
      transform: translateY(calc((100% + max(calc(var(--container-padding)/3), 25px)) * -1))!important;
    }
    body[data-hhun="1"] #nectar-nav.invisible:not(.side-widget-open) {
      transition: transform .3s ease, box-shadow 0.3s ease;
    }
    ';

    // simple ocm
    echo '
    body #nectar-nav #mobile-menu:before {
      width: 100%;
      left: 0;
      margin-left: 0;
    }
    #mobile-menu .menu-items-wrap,
    #nectar-nav .nectar-global-section.nectar_hook_ocm_bottom_meta > .container {
      padding: 0 min(max(calc(var(--container-padding)/3),25px),22px);
    }
    ';

    if ( 'material' === $theme_skin ) {

      $header_search_font_size = ( isset($nectar_options['header-search-type-size']) ) ? $nectar_options['header-search-type-size'] : false;

      echo '
      body[data-header-color="light"] #top .slide-out-widget-area-toggle .close-line {
        background-color: var(--nectar-default-header-font-color);
      }
      .nectar-ajax-search-results {
        overflow: hidden;
      }
      body.material #search-outer #search,
      html body.material #search-outer {
        height: 100%;
        min-height: 0;
      }

      @media only screen and (min-width: 1025px) {
        html body.material #search-outer #search #close {
            right: max(calc(var(--container-padding)/3), 25px);
        }
      }
      @media only screen and (max-width: 1024px) {
        html body.material #nectar-nav #search-outer #search #close {
          right: 20px;
        }
      }';
    } else {
      echo '
      @media only screen and (max-width: 1024px) {
        .original #nectar-nav #search-outer {
           height: 100%;
        }
        .original #search-outer #search #close a {
          right: 20px;
        }
      }';
    }

    // Contained header needs to remove alpha for search.

    if ( isset($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom'  ) {
      $navBGColor = isset($nectar_options['header-background-color']) && ! empty($nectar_options['header-background-color']) ? $nectar_options['header-background-color'] : '#ffffff';

      echo 'body #nectar-nav.material-search-open,
      body[data-header-color="dark"] #nectar-nav.material-search-open {
        background-color: ' . esc_attr($navBGColor) . ';
      }';
    }

    if ( isset($nectar_options['header-border-radius']) && $nectar_options['header-border-radius'] !== '0' ) {
      echo '
      body #nectar-nav, #nectar-nav #top {
        border-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
      }
      .material #nectar-nav #search-outer {
        border-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
      }
      body.material #nectar-nav #search-outer:before {
        border-radius: 0 0 ' . esc_attr($nectar_options['header-border-radius']) . 'px ' . esc_attr($nectar_options['header-border-radius']) . 'px;
      }

      #nectar-nav.simple-ocm-open {
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
      }
      #nectar-nav #mobile-menu:before {
        border-bottom-right-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
        border-bottom-left-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
      }
      body #nectar-nav nav .nectar-global-section-megamenu {
        transition: none;
        border-bottom-left-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
        border-bottom-right-radius: ' . esc_attr($nectar_options['header-border-radius']) . 'px;
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /*  1.21. Header Border
  /*-------------------------------------------------------------------------*/
  if ( isset( $nectar_options['header-enable-border'] ) && '1' === $nectar_options['header-enable-border'] ) {

    $header_bottom_border_color = ( isset( $nectar_options['header-border-color'] ) ) ? $nectar_options['header-border-color'] : '#000000';
    $remove_border = ( isset( $nectar_options['header-remove-border'] ) && $nectar_options['header-remove-border'] === '1' || $theme_skin === 'material' ) ? true : false;

    // contained header.
    if ( nectar_is_contained_header() ) {
      echo '
      @media only screen and (min-width: 1025px) {
        #nectar-nav #top {
          border: 1px solid ' . esc_attr($header_bottom_border_color) . ';
        }
      }';
    }

    // mobile
    echo '
    @media only screen and (max-width: 1024px) {';

      if ( ! $remove_border ) {
        echo '
        html body #nectar-nav[data-has-menu][data-lhe] {
          border-bottom: 1px solid ' . esc_attr($header_bottom_border_color) . '!important;
        }
        html body #nectar-nav[data-has-menu][data-lhe].transparent {
          border-color: var(--nectar-starting-header-color)!important;
        }
        html body #nectar-nav[data-has-menu][data-lhe].transparent.dark-slide,
        html body #nectar-nav[data-has-menu][data-lhe].dark-text {
          border-color: var(--nectar-starting-dark-header-color)!important;
        }';
      } else {
        echo 'html body #nectar-nav[data-has-menu][data-lhe]:not(.transparent) {
          border-bottom: 1px solid ' . esc_attr($header_bottom_border_color) . '!important;
        }';
      }

    echo '}';

    // regular desktop
    echo '
    @media only screen and (min-width: 1025px) {
      #nectar-nav:not(.transparent) #top {
        transition: border-color 0.2s ease;
        border-bottom: 1px solid ' . esc_attr($header_bottom_border_color) . ';
      }';

      if ( ! $remove_border ) {
        echo '
        #nectar-nav #top {
          transition: border-color 0.2s ease;
          border-bottom: 1px solid ' . esc_attr($header_bottom_border_color) . ';
        }
        #nectar-nav.transparent #top {
          border-color: var(--nectar-starting-header-color);
        }
        #nectar-nav.transparent.dark-slide #top,
        #nectar-nav.dark-text #top {
          border-color: var(--nectar-starting-dark-header-color);
        }';
      } else {
        echo '#nectar-nav:not(.transparent) #top {
          transition: border-color 0.2s ease;
          border-bottom: 1px solid ' . esc_attr($header_bottom_border_color) . ';
        }';
      }

    echo '}';

    // left header
    if ( $headerFormat === 'left-header'  ) {
      echo '
      @media only screen and (min-width: 1025px) {
        body #nectar-nav[data-format="left-header"] {
          border-right: 0;
        }
        body #nectar-nav[data-format="left-header"] #top {
          border-right: 1px solid ' . esc_attr($header_bottom_border_color) . ';
        }
      }
      ';
    }

    // bottom bar
    if( $headerFormat === 'centered-menu-bottom-bar' ) {
      if( '1' === $centered_menu_bb_sep ) {
        echo '
        @media only screen and (min-width: 1025px) {
          #nectar-nav[data-format="centered-menu-bottom-bar"] #top .span_3:before {
            background-color: ' . esc_attr($header_bottom_border_color) . ';
          }
          body #nectar-nav[data-format="centered-menu-bottom-bar"].transparent:not(.dark-slide) #top .span_3:before {
            background-color: var(--nectar-starting-header-color);
          }
          body #nectar-nav[data-format="centered-menu-bottom-bar"].transparent.dark-slide #top .span_3:before,
          body #nectar-nav[data-format="centered-menu-bottom-bar"].transparent.dark-text #top .span_3:before {
            background-color: var(--nectar-starting-dark-header-color);
          }
        }';
      }
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 2. Link Hover Effects
  /*-------------------------------------------------------------------------*/

  /*-------------------------------------------------------------------------*/
  /* 2.1. Header Navigation Hover Effects
  /*-------------------------------------------------------------------------*/
    echo '
    .nectar-skip-to-content:focus {
      position: fixed;
      left: 6px;
      top: 7px;
      height: auto;
      width: auto;
      display: block;
      font-size: 14px;
      font-weight: 600;
      padding: 15px 23px 14px;
      background: #f1f1f1;
      color: #000;
      z-index: 100000;
      line-height: normal;
      text-decoration: none;
      box-shadow: 0 0 2px 2px rgba(0,0,0,.6);
  }';

  /*-------------------------------------------------------------------------*/
  /* 2.2. Header Navigation Hover Effects
  /*-------------------------------------------------------------------------*/

 if( 'animated_underline' === $header_hover_effect && 'left-header' !== $headerFormat ) {
   echo '
   #nectar-nav[data-lhe="animated_underline"] li > a .menu-title-text {
     position: relative;
   }
   #nectar-nav .mobile-header li:not([class*="button_"]) > a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] nav > ul >li:not([class*="button_"]) > a .menu-title-text:after,
   #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a .menu-title-text:after {
     -webkit-transition:-webkit-transform .3s ease-out, border-color .3s ease-out;
     transition:transform .3s ease-out,border-color .3s ease-out;
     position:absolute;
     display:block;
     bottom:-6px;
     left:0;
     width:100%;
     -ms-transform:scaleX(0);
     -webkit-transform:scaleX(0);
     transform:scaleX(0);
     border-top:2px solid #000;
     content: "";
     padding-bottom:inherit
   }
   #nectar-nav .mobile-header li:not([class*="button_"]) > a:hover .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] #top nav >ul >li >a:hover .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] #top nav >ul >.sfHover >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .sf-menu .current_page_ancestor >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .sf-menu .current-menu-ancestor >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .sf-menu .current-menu-item >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .sf-menu .current_page_item >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .sf-menu .current_page_parent >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .buttons .current-menu-item >a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"] .buttons .current-menu-ancestor >a .menu-title-text:after,
   #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a .menu-title-text:hover:after,
   #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a:focus .menu-title-text:after {
     backface-visibility: hidden;
     -webkit-transform:scaleX(1);
     transform:scaleX(1)
   }

   #nectar-nav[data-lhe="animated_underline"].transparent.dark-slide #top nav >ul >li >a .menu-title-text:after {
     border-color:#000!important;
   }';
 }

 // Button BG style
 else if( 'button_bg' === $header_hover_effect ) {

  $header_color_scheme = (isset($nectar_options['header-color'])) ? $nectar_options['header-color'] : 'light';

  if ( 'left-header' === $headerFormat ) {
    echo '@media only screen and (min-width: 1025px) {
      #nectar-nav[data-format=left-header] nav > .sf-menu {
        display: flex;
        flex-direction: column;
        gap: 5px;
      }
    }';
  }
  // core styling.
  echo '
  #top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text {
    transition: color .45s cubic-bezier(0.25,1,0.33,1);
  }
  #top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text:before {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    content: "";
    display: block;
    z-index: -1;
    transition: opacity .45s cubic-bezier(0.25,1,0.33,1),
                transform .45s cubic-bezier(0.25,1,0.33,1),
                background-color .45s cubic-bezier(0.25,1,0.33,1);
  }';

  // dropdown arrows.
  echo '#top .sf-menu > li > a > .sf-sub-indicator {
    left: 0;
    margin-left: 5px;
  }';

  // default coloring.
  if( in_array($header_color_scheme, ['light','dark']) ) {
    $default_header_button_color = (isset($nectar_options["accent-color"]) && ! empty($nectar_options["accent-color"])) ? $nectar_options["accent-color"] : '#000';
    echo '#top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      background-color: ' . esc_attr($default_header_button_color) . ';
      filter: opacity(0.15);
    }
    #top .sf-menu > li:not([class*="current"]):not([class*="menu-item-btn"]) > a .menu-title-text:before {
      background-color: currentColor;
    }
    #top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text {
      color: #fff;
    }
    #top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      filter: opacity(1);
    }';
  }

  // sizing.
  echo '
  #top .sf-menu > li,
  body.material #nectar-nav:not([data-format=left-header]) #top nav>.buttons.sf-menu>li.menu-item  {
    margin-left: ' . (intval($menu_item_spacing) / 2) . 'px;
    margin-right: ' . (intval($menu_item_spacing) / 2) . 'px;
  }
  #top .sf-menu > li[class*="menu-item-btn"] > a {
    padding: 0px;
  }
  #top .sf-menu > li:not([class*="menu-item-btn"]) > a {
    padding: ' . ($button_width / 1.8) . 'em ' . $button_width . 'em;
  }
  #top .sf-menu > #social-in-menu > a {
    padding: 0;
  }
  #nectar-nav[data-format="centered-menu-under-logo"] #top .sf-menu > li:not([class*="menu-item-btn"]) > a {
    padding: ' . ($button_width / 1.8) . 'em ' . $button_width . 'em!important;
  }';

  // styling.
  $menu_button_style = isset($nectar_options['header-hover-effect-button-bg-style']) ? $nectar_options['header-hover-effect-button-bg-style'] : 'fade-in';
  if( 'fade-in' === $menu_button_style ) {
    echo '#top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      opacity: 0;
    }
    #top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text:before,
    #top .sf-menu > li:not([class*="menu-item-btn"]) > a:hover .menu-title-text:before,
    #top .sf-menu > li.sfHover:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      opacity: 1;
    }
   ';
  }
  else if ( 'grow-in' === $menu_button_style ) {
    echo '#top .sf-menu > li:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      transform: scale(0.5);
      opacity: 0;
    }

    #top .sf-menu > li[class*="current"]:not([class*="menu-item-btn"]) > a .menu-title-text:before,
    #top .sf-menu > li:not([class*="menu-item-btn"]) > a:hover .menu-title-text:before,
    #top .sf-menu > li.sfHover:not([class*="menu-item-btn"]) > a .menu-title-text:before {
      transform: scale(1) translateZ(0);
      opacity: 1;
    }';
  }

  // roundness.
    $button_roundness = ( isset($nectar_options['button-styling-roundness']) && ! empty($nectar_options['button-styling-roundness']) ) ? intval( $nectar_options['button-styling-roundness'] ) : 4;
    echo '#top .sf-menu .menu-title-text:before {
      border-radius: var(--nectar-border-radius);
    }';

      echo '#top .sf-menu .menu-title-text:before {
        border-radius: var(--nectar-border-radius);
      }';

 }

 /*-------------------------------------------------------------------------*/
 /* 2.3. Global Hover Effects
 /*-------------------------------------------------------------------------*/
 $animated_underline_thickness = (isset($nectar_options['animated-underline-thickness']) && ! empty($nectar_options['animated-underline-thickness'])) ? $nectar_options['animated-underline-thickness'] : '2';

  // Animated Underline Animation Variants.
 if( isset( $nectar_options['animated-underline-type'] ) && ! empty( $nectar_options['animated-underline-type'] ) ) {

   // Left To Right Fancy Style
   if( 'ltr-fancy' === $nectar_options['animated-underline-type'] ) {

     $starting_origin = 'right';
     $ending_origin = 'left';

     if( is_rtl() ) {
       $starting_origin = 'left';
       $ending_origin = 'right';
     }

     // Transform
     echo '
     body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container li > a:after,
     #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a .menu-title-text:after,
     #slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a:after,
     #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a span:after,
     body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container .nectar-menu-item-with-icon .menu-title-text:after,
     body #slide-out-widget-area.fullscreen-split .off-canvas-menu-container .nectar-menu-item-with-icon .menu-title-text:after,
     .post-area.featured_img_left .meta-category a:before,
     .related-posts[data-style="material"] .meta-category a:before,
     .nectar-recent-posts-single_featured .grav-wrap .text a:before,
     .auto_meta_overlaid_spaced .masonry-blog-item .meta-category a:before,
     [data-style="list_featured_first_row"] .meta-category a:before,
     body #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after,
     .sf-menu li ul li a .menu-title-text:after {
       transform-origin: ' . esc_attr($starting_origin) . ';
       transition: transform 0.3s cubic-bezier(0.25, 0, 0.4, 1), border-color 0.35s cubic-bezier(0.52, 0.01, 0.16, 1);
     }

    #nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) #top nav > ul > li:not([class*="button_"]) > a .menu-title-text:after,
     body #nectar-nav .mobile-header li:not([class*="button_"]) > a .menu-title-text:after {
        transform-origin: ' . esc_attr($starting_origin) . ';
       transition: transform 0.3s cubic-bezier(0.25, 0, 0.4, 1), border-color 0.3s ease;
    }

     body #nectar-nav .mobile-header li:not([class*="button_"]) > a:hover .menu-title-text:after,
     #nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) #top nav > ul > li:not([class*="button_"]) > a:hover .menu-title-text:after,
     #nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) #top nav > ul > li.sfHover:not([class*="button_"]) > a .menu-title-text:after,
     body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container li > a:hover:after,
     #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a:hover .menu-title-text:after,
     #slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a:hover:after,
     #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a:hover span:after,
     body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container .nectar-menu-item-with-icon:hover .menu-title-text:after,
     body #slide-out-widget-area.fullscreen-split .off-canvas-menu-container .nectar-menu-item-with-icon:hover .menu-title-text:after,
     .related-posts[data-style="material"] .meta-category a:hover:before,
     .post-area.featured_img_left .meta-category a:hover:before,
     .nectar-recent-posts-single_featured .grav-wrap .text a:hover:before,
     .auto_meta_overlaid_spaced .masonry-blog-item .meta-category a:hover:before,
     [data-style="list_featured_first_row"] .meta-category a:hover:before,
     .related-posts[data-style="material"] .meta-category a:focus:before,
     .post-area.featured_img_left .meta-category a:focus:before,
     .nectar-recent-posts-single_featured .grav-wrap .text a:focus:before,
     .auto_meta_overlaid_spaced .masonry-blog-item .meta-category a:focus:before,
     [data-style="list_featured_first_row"] .meta-category a:focus:before,
     body #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:hover:after,
     .sf-menu li ul li a:hover .menu-title-text:after,
     .sf-menu li ul li.sfHover > a .menu-title-text:after,
     #nectar-content-wrap .portfolio-filters-inline[data-color-scheme*="-underline"] ul li a:hover:after  {
       transform-origin: ' . esc_attr($ending_origin) . ';
     }';

     // BG size
     echo '#footer-outer[data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
     #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button),
     #slide-out-widget-area.fullscreen-split .widget ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
     #slide-out-widget-area.fullscreen-split .textwidget a:not(.nectar-button),
     .products li.product.minimal .product-add-to-cart .added_to_cart,
     .woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control,
     .woocommerce-tabs .full-width-content[data-tab-style="fullwidth"] ul.tabs li a,
     .woocommerce .woocommerce-info a,
     .woocommerce .woocommerce-error a,
     .woocommerce-message a,
     .woocommerce-MyAccount-content .woocommerce-message a.button,
     #search-results .result .title a,
     a > .nectar-ext-menu-item .menu-title-text,
     .nectar-slide-in-cart.style_slide_in_click .cart_list .product-meta a:not(.remove),
     body .woocommerce-checkout-review-order-table .product-info h4 a,
     body.woocommerce-cart .product-name a,
     .woocommerce .woocommerce-breadcrumb a,
     .nectar-ajax-search-results .search-post-item h5,
     .nectar-category-grid[data-style="mouse_follow_image"] .nectar-category-grid-item .cat-heading,
     .nectar-underline h1,
     .nectar-underline h2,
     .nectar-underline h3,
     .nectar-underline h4,
     .nectar-underline h5,
     .nectar-link-underline a span,
     .nectar-shop-header > .woocommerce-ordering .select2-container--default:hover .select2-selection__rendered,
     .nectar-shop-header > .woocommerce-ordering .select2-container--default.select2-container--open .select2-selection__rendered,
     .variations_form .variations .select2-container--default:hover .select2-selection__rendered,
     .variations_form .variations .select2-container--default.select2-container--open .select2-selection__rendered,
     body .variations_form .variations select:hover,
     .woocommerce div.product .woocommerce-review-link,
     .woocommerce.single-product div.product_meta a {
          background-position: ' . esc_attr($starting_origin) . ' bottom;
     }';

     echo '#footer-outer[data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button):hover,
     #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button):hover,
     #slide-out-widget-area.fullscreen-split .widget ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button):hover,
     #slide-out-widget-area.fullscreen-split .textwidget a:not(.nectar-button):hover,
     .nectar-quick-view-box .single_add_to_cart_button_wrap a:hover span,
     .products li.product.minimal .product-add-to-cart a:hover span,
     .products li.product.minimal .product-add-to-cart .added_to_cart:hover,
     .woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control.active,
     .woocommerce-tabs .full-width-content[data-tab-style="fullwidth"] ul.tabs li.active a,
     .woocommerce .woocommerce-info a:hover,
     .woocommerce .woocommerce-error a:hover,
     .woocommerce-message a:hover,
     .woocommerce-MyAccount-content .woocommerce-message a.button:hover,
     #search-results .result .title a:hover,
     a:hover > .nectar-ext-menu-item .menu-title-text,
     a:focus > .nectar-ext-menu-item .menu-title-text,
     li[class*="current"] > a > .nectar-ext-menu-item .menu-title-text,
     .nectar-slide-in-cart.style_slide_in_click .cart_list .product-meta a:hover:not(.remove),
     body .woocommerce-checkout-review-order-table .product-info h4 a:hover,
     body.woocommerce-cart .product-name a:hover,
     .woocommerce .woocommerce-breadcrumb a:hover,
     .nectar-ajax-search-results .search-post-item:hover h5,
     .nectar-category-grid[data-style="mouse_follow_image"] .nectar-category-grid-item:hover .cat-heading,
     .nectar-underline:hover h1,
     .nectar-underline:hover h2,
     .nectar-underline:hover h3,
     .nectar-underline:hover h4,
     .nectar-underline:hover h5,
     .nectar-link-underline a:hover span,
     .active-tab .nectar-link-underline a span,
     .nectar-link-underline a[class*="active"] span,
     .nectar-post-grid-item:hover .nectar-link-underline span,
     .nectar-shop-header > .woocommerce-ordering .select2-container--default .select2-selection__rendered,
     .woocommerce-ordering .select2-container--default .select2-selection__rendered,
     .variations_form .variations .select2-container--default .select2-selection__rendered,
     body .variations_form .variations select,
     .woocommerce div.product .woocommerce-review-link:hover,
     .woocommerce.single-product div.product_meta a:hover
      {
          background-position: ' . esc_attr($ending_origin) . ' bottom;
     }';
   }

   // Left To Right Style
   else if( 'ltr' === $nectar_options['animated-underline-type'] ) {
     echo '
     body #nectar-nav .mobile-header li:not([class*="button_"]) > a .menu-title-text:after,
     #nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) #top nav > ul > li:not([class*="button_"]) > a .menu-title-text:after,
     .sf-menu li ul li a .menu-title-text:after {
       transform-origin: left;
       transition: transform 0.3s cubic-bezier(0.25, 0, 0.4, 1), border-color 0.3s ease;
     }';
   }

 } // end underline type

 if( '2' !== $animated_underline_thickness ) {

   // Border
   echo '
   body #nectar-nav .mobile-header li:not([class*="button_"]) > a .menu-title-text:after,
   #nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) nav > ul > li:not([class*="button_"]) > a .menu-title-text:after,
   body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container li > a:after,
   body.material #slide-out-widget-area[class*="slide-out-from-right"] .off-canvas-menu-container .nectar-menu-item-with-icon .menu-title-text:after,
   body #slide-out-widget-area.fullscreen-split .off-canvas-menu-container .nectar-menu-item-with-icon .menu-title-text:after,
   #header-secondary-outer[data-lhe="animated_underline"] nav >.sf-menu >li >a .menu-title-text:after,
   #slide-out-widget-area.fullscreen-split .inner .off-canvas-menu-container li > a:after,
   #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a span:after {
     border-top-width: ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }
   .nectar-cta[data-style="underline"] .link_wrap .link_text:after {
      border-bottom-width: ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }
   :root {
      --nectar-border-thickness: ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }';

   // BG
   echo '
   .post-area.featured_img_left .meta-category a:before,
  .related-posts[data-style="material"] .meta-category a:before,
  .nectar-recent-posts-single_featured .grav-wrap .text a:before,
  .auto_meta_overlaid_spaced .masonry-blog-item .meta-category a:before,
  #nectar-nav[data-lhe="animated_underline"] .nectar-header-text-content a:after,
  .sf-menu li ul li a .menu-title-text:after {
      height: ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
  }';

   // BG Size
   echo '#footer-outer[data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
   #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button),
   #slide-out-widget-area.fullscreen-split .widget ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
   #slide-out-widget-area.fullscreen-split .textwidget a:not(.nectar-button),
   .products li.product.minimal .product-add-to-cart .added_to_cart,
   .woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control,
   .woocommerce-tabs .full-width-content[data-tab-style="fullwidth"] ul.tabs li a,
   .woocommerce .woocommerce-info a,
   .woocommerce .woocommerce-error a,
   .woocommerce-message a,
   .woocommerce-MyAccount-content .woocommerce-message a.button,
   #search-results .result .title a,
   a > .nectar-ext-menu-item .menu-title-text,
   .nectar-slide-in-cart.style_slide_in_click .cart_list .product-meta a:not(.remove),
   body .woocommerce-checkout-review-order-table .product-info h4 a,
   body.woocommerce-cart .product-name a,
   .woocommerce .woocommerce-breadcrumb a,
   .nectar-ajax-search-results .search-post-item h5,
   .nectar-underline h1,
   .nectar-underline h2,
   .nectar-underline h3,
   .nectar-underline h4,
   .nectar-underline h5,
   .nectar-shop-header > .woocommerce-ordering .select2-container--default:hover .select2-selection__rendered,
   .nectar-shop-header > .woocommerce-ordering .select2-container--default.select2-container--open .select2-selection__rendered,
   .variations_form .variations .select2-container--default:hover .select2-selection__rendered,
   .variations_form .variations .select2-container--default.select2-container--open .select2-selection__rendered,
   body .variations_form .variations select:hover,
   .woocommerce div.product .woocommerce-review-link,
   .woocommerce.single-product div.product_meta a,
   .nectar-link-underline a span
   {
        background-size: 0% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }
   #footer-outer[data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button):hover,
   #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button):hover,
   #slide-out-widget-area.fullscreen-split .widget ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button):hover,
   #slide-out-widget-area.fullscreen-split .textwidget a:not(.nectar-button):hover,
   .nectar-quick-view-box .single_add_to_cart_button_wrap a:hover span,
   .products li.product.minimal .product-add-to-cart a:hover span,
   .products li.product.minimal .product-add-to-cart .added_to_cart:hover,
   .woocommerce-account .woocommerce > #customer_login .nectar-form-controls .control.active,
   .woocommerce-tabs .full-width-content[data-tab-style="fullwidth"] ul.tabs li.active a,
   .woocommerce .woocommerce-info a:hover,
   .woocommerce .woocommerce-error a:hover,
   .woocommerce-message a:hover,
   .woocommerce-MyAccount-content .woocommerce-message a.button:hover,
   #search-results .result .title a:hover,
   a:hover > .nectar-ext-menu-item .menu-title-text,
   a:focus > .nectar-ext-menu-item .menu-title-text,
   li[class*="current"] > a > .nectar-ext-menu-item .menu-title-text,
   .nectar-slide-in-cart.style_slide_in_click .cart_list .product-meta a:hover:not(.remove),
   body .woocommerce-checkout-review-order-table .product-info h4 a:hover,
   body.woocommerce-cart .product-name a:hover,
   .woocommerce .woocommerce-breadcrumb a:hover,
   .nectar-ajax-search-results .search-post-item:hover h5,
   .nectar-underline:hover h1,
   .nectar-underline:hover h2,
   .nectar-underline:hover h3,
   .nectar-underline:hover h4,
   .nectar-underline:hover h5,
   .active-tab .nectar-link-underline a span,
   .nectar-shop-header > .woocommerce-ordering .select2-container--default .select2-selection__rendered,
   .woocommerce-ordering .select2-container--default .select2-selection__rendered,
   .variations_form .variations .select2-container--default .select2-selection__rendered,
   body .variations_form .variations select,
   .woocommerce div.product .woocommerce-review-link:hover,
   .woocommerce.single-product div.product_meta a:hover,
   .nectar-post-grid-item:hover .nectar-link-underline span,
   .nectar-link-underline a:hover span,
   .nectar-link-underline a[class*="active"] span {
        background-size: 100% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }

   .nectar-link-underline-effect a {
    background-size: 100% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
   }
   @keyframes nectar_ltr_line_animation_start {
    0% {
      background-size: 0% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
    }
    100% {
      background-size: 100% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
    }
  }
   @keyframes nectar_ltr_line_animation {
    0% {
      background-size: 100% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
    }
    100% {
      background-size: 0% ' . esc_attr($nectar_options['animated-underline-thickness']) . 'px;
    }
  }';

   if( '1' === $nectar_options['animated-underline-thickness'] ) {
     echo '#nectar-nav[data-lhe="animated_underline"]:not([data-format="left-header"]) nav > ul > li:not([class*="button_"]) > a .menu-title-text:after {
       bottom: -4px;
     }';
   }

 } // end underline thickness
 else {
    echo ':root {
      --nectar-border-thickness: 2px;
    }';
 }

 /*-------------------------------------------------------------------------*/
 /* 2.4. General Links
 /*-------------------------------------------------------------------------*/
//  if( isset($nectar_options['general-link-style']) &&
//      'basic-underline' === $nectar_options['general-link-style'] ) {
//        echo '.wpb_text_column a,
//        p a,
//        .nectar-fancy-box .inner a,
//        .nectar-fancy-ul a,
//        .nectar_team_member_overlay .team-desc a,
//        .main-content > .row > h1 a,
//        .main-content > .row > h2 a,
//        .main-content > .row > h3 a,
//        .main-content > .row > h4 a,
//        .main-content > .row > h5 a,
//        .main-content > .row > h6 a,
//        .wp-block-quote a {
//          color: inherit;
//          text-decoration: underline;
//        }
//        .nectar-cta p a,
//        .nectar_team_member_overlay .team-desc .bottom_meta a {
//         text-decoration: none;
//        }
//        ';

//  }
//  else {

//   echo '.span_12.light .wpb_text_column a {
//     transition: 0.3s ease opacity, 0.3s ease color;
//   }
//   .span_12.light .wpb_text_column a:not(:hover) {
//     opacity: 0.7;
//   }';

//  }

 /*-------------------------------------------------------------------------*/
 /* 3. Nectar Slider Font Sizing
 /*-------------------------------------------------------------------------*/
    //  $heading_size = (!empty($nectar_options['use-custom-fonts']) && $nectar_options['use-custom-fonts'] == 1 && $nectar_options['nectar_slider_heading_font_size'] != '-') ? intval($nectar_options['nectar_slider_heading_font_size']) : 60;
    //  $caption_size = (!empty($nectar_options['use-custom-fonts']) && $nectar_options['use-custom-fonts'] == 1 && $nectar_options['home_slider_caption_font_size'] != '-') ? intval($nectar_options['home_slider_caption_font_size']) : 24;

    //  echo '@media only screen and (min-width: 1025px) and (max-width: 1300px) {
    //     .nectar-slider-wrap[data-full-width="true"] .swiper-slide .content h2,
    //     .nectar-slider-wrap[data-full-width="boxed-full-width"] .swiper-slide .content h2,
    //     .full-width-content .vc_span12 .swiper-slide .content h2 {
    //      font-size: ' .$heading_size*0.75 . 'px!important;
    //      line-height: '.$heading_size*0.85 .'px!important;
    //  }

    //  .nectar-slider-wrap[data-full-width="true"] .swiper-slide .content p,
    //  .nectar-slider-wrap[data-full-width="boxed-full-width"] .swiper-slide .content p,
    //     .full-width-content .vc_span12 .swiper-slide .content p {
    //      font-size: ' .$caption_size *0.75 . 'px!important;
    //      line-height: '.$caption_size *1.3 .'px!important;
    //  }
    // }

    // @media only screen and (min-width : 691px) and (max-width: 1024px) {
    //  .nectar-slider-wrap[data-full-width="true"] .swiper-slide .content h2,
    //  .nectar-slider-wrap[data-full-width="boxed-full-width"] .swiper-slide .content h2,
    //     .full-width-content .vc_span12 .swiper-slide .content h2 {
    //      font-size: ' . (($heading_size*0.55 > 20) ? $heading_size*0.55 : 20) . 'px!important;
    //      line-height: '. (($heading_size*0.55 > 20) ? $heading_size*0.65 : 27) .'px!important;
    //  }

    //  .nectar-slider-wrap[data-full-width="true"] .swiper-slide .content p,
    //  .nectar-slider-wrap[data-full-width="boxed-full-width"] .swiper-slide .content p,
    //     .full-width-content .vc_span12 .swiper-slide .content p {
    //      font-size: ' . (($caption_size *0.55 > 12) ? $caption_size *0.55 : 12). 'px!important;
    //      line-height: '.$caption_size *1 .'px!important;
    //  }
    // }

    // @media only screen and (max-width : 768px) {
    //  .nectar-slider-wrap[data-full-width="true"][data-fullscreen="false"] .swiper-slide .content h2,
  //   .full-width-content .vc_col-sm-12 .nectar-slider-wrap[data-fullscreen="false"] .swiper-slide .content h2,
    //  .nectar-slider-wrap[data-full-width="boxed-full-width"][data-fullscreen="false"] .swiper-slide .content h2,
    //     .full-width-content .vc_span12 .nectar-slider-wrap[data-fullscreen="false"] .swiper-slide .content h2 {
    //      font-size: ' .(($heading_size*0.25 > 14) ? $heading_size*0.25 : 14) . 'px!important;
    //      line-height: '.(($heading_size*0.25 > 14) ? $heading_size*0.35 : 20) .'px!important;
    //  }

    //  .nectar-slider-wrap[data-full-width="true"][data-fullscreen="false"] .swiper-slide .content p,
    //  .nectar-slider-wrap[data-full-width="boxed-full-width"][data-fullscreen="false"]  .swiper-slide .content p,
    //     .full-width-content .vc_span12 .nectar-slider-wrap[data-fullscreen="false"] .swiper-slide .content p {
    //      font-size: ' .(($caption_size *0.32 > 10) ? $caption_size *0.32 : 10) . 'px!important;
    //      line-height: '.(($caption_size *0.73 > 10) ? $caption_size *0.73 : 18) .'px!important;
    //  }
    // }
    // ';

  /*-------------------------------------------------------------------------*/
  /* 4. Header Navigation Transparent Coloring
  /*-------------------------------------------------------------------------*/
    if( (! empty($nectar_options['transparent-header']) &&
      $nectar_options['transparent-header'] === '1' &&
      ! nectar_is_contained_header() ) ||
      'fullscreen-inline-images' === $side_widget_class && ! nectar_is_contained_header() ) {

    // Dynamic coloring.
    $starting_color = $header_starting_color;
    $starting_opacity = (isset($nectar_options['header-starting-opacity']) && ! empty($nectar_options['header-starting-opacity'])) ? $nectar_options['header-starting-opacity'] : '0.75';

    echo ':root {
      --nectar-starting-header-color: ' . esc_attr($starting_color) . ';
    }';

    // Core.
    echo 'body #nectar-nav[data-transparent-header="true"],
    body #nectar-nav[data-transparent-header="true"] .cart-menu{
      transition:background-color 0.30s ease,box-shadow 0.30s ease,margin 0.25s ease, backdrop-filter 0.25s ease;
      -webkit-transition:background-color 0.30s ease,box-shadow 0.30s ease,margin 0.25s ease, backdrop-filter 0.25s ease;
    }
    body #nectar-nav[data-transparent-header="true"].transparent,
    body #nectar-nav[data-transparent-header="true"].transparent .cart-menu {
      transition:border-color 0.30s ease;
      -webkit-transition:border-color 0.30s ease
    }
    body.material #nectar-nav[data-transparent-header="true"].transparent {
      box-shadow:none;
    }
    body #nectar-nav[data-transparent-header="true"].transparent {
      background-color:transparent!important;
      -webkit-box-shadow:none;
    	box-shadow:none;
      border-bottom:1px solid rgba(255,255,255,0.25)
    }


    #nectar-nav[data-transparent-header="true"][data-transparent-shadow-helper="true"].transparent:not(.dark-slide):before {
      background: linear-gradient(to top,rgba(255,255,255,0) 0%,rgba(0,0,0,0) 1%,rgba(0,0,0,0.04) 16%,rgba(0,0,0,0.23) 75%,rgba(0,0,0,0.33) 100%);
      position: absolute;
      pointer-events: none;
      height: 120%;
      top: 0;
      left: 0;
      width: 100%;
      content: "";
      display: block;
    }

    body.material #nectar-nav[data-transparent-header="true"].transparent {
      border-bottom: 0;
    }

    body #nectar-nav[data-transparent-header="true"].transparent nav >ul >li >a{
      margin-bottom:-1px
    }
    body #nectar-nav[data-transparent-header="true"][data-format="centered-menu"].transparent.side-widget-open.small-nav nav >ul >li >a{
      margin-bottom:0
    }

    #nectar-nav[data-transparent-header="true"].transparent #logo img,
    #nectar-nav[data-transparent-header="true"] #logo .starting-logo,
    #nectar-nav[data-transparent-header="true"].light-text #logo img{
      opacity:0;
      -ms-filter:"alpha(opacity=0)"
    }
    #nectar-nav[data-transparent-header="true"].transparent #logo .starting-logo,
    #nectar-nav[data-transparent-header="true"].light-text #logo .starting-logo {
      opacity:1;
      -ms-filter:"alpha(opacity=100)"
    }

    #nectar-nav[data-transparent-header="true"].transparent:not(.dark-text):not(.dark-slide) #logo picture.starting-logo:not(.dark-version) img {
      opacity: 1!important;
    }
    #nectar-nav[data-transparent-header="true"].light-text:not(.dark-text) #logo picture.starting-logo:not(.dark-version) img {
      opacity: 1!important;
    }
    #nectar-nav[data-transparent-header="true"].transparent.dark-slide #logo picture.starting-logo.dark-version img,
    #nectar-nav[data-transparent-header="true"].dark-text #logo picture.starting-logo.dark-version img,
    #nectar-nav[data-transparent-header="true"].dark-text #logo picture.starting-logo.dark-version {
      opacity: 1!important;
    }

    body #nectar-nav[data-transparent-header="true"][data-remove-border="true"],
    #nectar-nav[data-transparent-header="true"][data-full-width="true"][data-remove-border="true"] .cart-menu,
    #nectar-nav[data-transparent-header="true"][data-full-width="false"][data-remove-border="true"].transparent .cart-menu {
      border:none!important
    }
    body #nectar-nav.transparent[data-transparent-header="true"][data-remove-border="true"]{
      transition:background-color 0.3s ease 0s,box-shadow 0.3s ease 0s,margin 0.25s ease, backdrop-filter 0.25s ease;
      -webkit-transition:background-color 0.3s ease 0s,box-shadow 0.3s ease 0s,margin 0.25s ease, backdrop-filter 0.25s ease;
    }
    body:not(.ascend) #nectar-nav[data-transparent-header="true"][data-remove-border="true"]:not(.transparent) .cart-menu:after{
      border-left:1px solid rgba(0,0,0,0)
    }
    body #nectar-nav[data-transparent-header="true"][data-remove-border="true"].transparent.pseudo-data-transparent {
      border-color:transparent!important
    }


    #nectar-nav.light-text #top nav >ul >li >a,
    #nectar-nav.light-text #top nav ul #search-btn a span,
    #nectar-nav.light-text #top nav ul #nectar-user-account a span,
    #nectar-nav.light-text #top .container nav >ul >li >a >.sf-sub-indicator i,
    #nectar-nav.light-text .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
    #nectar-nav.light-text .nectar-header-text-content,
    .light-text .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a,
    #nectar-nav[data-lhe="default"].light-text #top nav .sf-menu .current-menu-item >a,
    #nectar-nav[data-lhe="default"].light-text #top nav >ul >li >a:hover,
    #nectar-nav[data-lhe="default"].light-text #top nav .sf-menu >.sfHover >a{
      color:#fff!important;
      opacity:' . esc_attr($starting_opacity) . ';
    }
    #nectar-nav.light-text #logo,
    #nectar-nav.light-text .sf-menu > li.nectar-regular-menu-item > a > .nectar-menu-icon {
    	color: #fff;
    }

    body #nectar-nav.light-text #top .container nav ul .slide-out-widget-area-toggle a .lines,
    body #nectar-nav.light-text #top .container nav ul .slide-out-widget-area-toggle a .lines:before,
    body #nectar-nav.light-text #top .container nav ul .slide-out-widget-area-toggle a .lines:after,
    body #nectar-nav.light-text #top .container nav ul .slide-out-widget-area-toggle .lines-button:after {
      background-color:#fff !important
    }
    #nectar-nav.dark-text #top nav >ul >li >a,
    #nectar-nav.dark-text #top nav ul #search-btn a span,
    #nectar-nav.dark-text #top nav ul #nectar-user-account a span,
    #nectar-nav.dark-text nav >ul >li >a >.sf-sub-indicator i,
    .dark-text .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a,
    #nectar-nav.dark-text .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
    #nectar-nav[data-lhe="default"].dark-text #top nav .sf-menu .current-menu-item >a{
      color:#444!important;
      opacity:' . esc_attr($starting_opacity) . ';
    }
    #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a .lines,
    #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a .lines:before,
    #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a .lines:after{
      background-color:#444 !important
    }
    #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a .lines,
    #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a .lines,
    #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a .lines-button:after{
      opacity: ' . esc_attr($starting_opacity) . ';
    }';

    if( '1.0' !== $starting_opacity ) {
      echo '#nectar-nav.light-text #top nav >ul >li >a:hover,
      #nectar-nav.light-text #top nav .sf-menu >.sfHover >a,
      #nectar-nav.light-text #top nav .sf-menu >.current_page_ancestor >a,
      #nectar-nav.light-text #top nav .sf-menu >.current-menu-item >a,
      #nectar-nav.light-text #top nav .sf-menu >.current-menu-ancestor >a,
      #nectar-nav.light-text #top nav .sf-menu >.current_page_item >a,
      #nectar-nav.light-text #top nav >ul >li >a:hover >.sf-sub-indicator >i,
      #nectar-nav.light-text #top nav >ul >.sfHover >a >span >i,
      #nectar-nav.light-text #top nav ul #search-btn a:hover span,
      #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a:hover span,
      #nectar-nav.light-text #top nav .sf-menu >.current-menu-item >a i,
      #nectar-nav.light-text #top nav .sf-menu >.current-menu-ancestor >a i,
      #nectar-nav.light-text .cart-outer:hover .icon-nectar-blocks-cart,
      .light-text .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a:hover,
      .light-text .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"])[class*="current"] a,
      .dark-text .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a:hover,
      .dark-text .nectar-mobile-only.mobile-header li[class*="current"] a,
      #nectar-nav.dark-text #top nav >ul >li >a:hover,
      #nectar-nav.dark-text #top nav .sf-menu >.sfHover >a,
      #nectar-nav.dark-text #top nav .sf-menu >.current_page_ancestor >a,
      #nectar-nav.dark-text #top nav .sf-menu >.current-menu-item >a,
      #nectar-nav.dark-text #top nav .sf-menu >.current-menu-ancestor >a,
      #nectar-nav.dark-text #top nav .sf-menu >.current_page_item >a,
      #nectar-nav.dark-text #top nav >ul >li >a:hover >.sf-sub-indicator >i,
      #nectar-nav.dark-text #top nav >ul >.sfHover >a >span >i,
      #nectar-nav.dark-text #top nav ul #search-btn a:hover span,
      #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a:hover span,
      #nectar-nav.dark-text #top nav .sf-menu >.current-menu-item >a i,
      #nectar-nav.dark-text #top nav .sf-menu >.current-menu-ancestor >a i,
      #nectar-nav.dark-text .cart-outer:hover .icon-nectar-blocks-cart,
      #nectar-nav.light-text.side-widget-open #top nav ul .slide-out-widget-area-toggle a .lines,
      #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a:hover .lines,
      #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a:hover .lines-button:after,
      #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a:hover .lines:before,
      #nectar-nav.light-text #top nav ul .slide-out-widget-area-toggle a:hover .lines:after,
      #nectar-nav.dark-text.side-widget-open #top nav ul .slide-out-widget-area-toggle a .lines,
      #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a:hover .lines,
      #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a:hover .lines:before,
      #nectar-nav.dark-text #top nav ul .slide-out-widget-area-toggle a:hover .lines:after {
        opacity:1!important
      }';
    }

    echo '#nectar-nav.light-text #top nav >ul >li >a,
    #nectar-nav.light-text #top nav >ul >li *,
    #nectar-nav.dark-text #top nav >ul >li >a,
    #nectar-nav.dark-text #top nav >ul >li * {
      border-color:rgba(0,0,0,0.1)
    }
    body #nectar-nav.transparent[data-transparent-header="true"].dark-slide,
    body #nectar-nav.transparent[data-transparent-header="true"].dark-slide .cart-menu,
    body #nectar-nav.transparent[data-transparent-header="true"].dark-slide >#top nav ul #nectar-user-account >div,
    body #nectar-nav.transparent[data-transparent-header="true"].dark-slide >#top nav ul .slide-out-widget-area-toggle >div,
    #boxed #nectar-nav.transparent[data-transparent-header="true"].dark-slide,
    body #nectar-nav.transparent[data-transparent-header="true"][data-remove-border="true"].dark-slide .cart-menu:after{
      border-color:rgba(0,0,0,0.08) !important
    }

    #nectar-nav.transparent.dark-slide #top nav >ul >li[class*="button_bordered"] >a:before,
    .dark-slide.transparent #top nav >ul >li[class*="button_bordered"] >a:before {
      border-color:#000!important
    }
    #nectar-nav[data-transparent-header="true"].no-pointer-events {
      pointer-events:none
    }
    #nectar-nav[data-transparent-header="true"].no-pointer-events.side-widget-open.style-slide-out-from-right,
    #nectar-nav[data-transparent-header="true"].no-pointer-events.side-widget-open.style-slide-out-from-right-alt{
      pointer-events:auto
    }
    #nectar-nav[data-transparent-header="true"].transparent >header #logo img.dark-version,
    #nectar-nav[data-transparent-header="true"].light-text >header #logo img.dark-version,
    #nectar-nav[data-transparent-header="true"].transparent.dark-slide >header #logo img,
    #nectar-nav[data-transparent-header="true"].dark-text >header #logo img,
    #nectar-nav[data-transparent-header="true"].dark-text.side-widget-open >header #logo img.dark-version{
      opacity:0!important
    }
    #nectar-nav[data-transparent-header="true"].transparent.dark-slide >header #logo img.dark-version,
    #nectar-nav[data-transparent-header="true"].dark-text >header #logo img.dark-version,
    #nectar-nav[data-transparent-header="true"].dark-text.side-widget-open >header #logo img.starting-logo{
      opacity:1!important
    }
    ';

        echo '
				#nectar-nav.transparent #top #logo,
				#nectar-nav.transparent #top #logo:hover {
				 	color: ' . esc_attr($starting_color) . ';
				 }
         #nectar-nav:not(.transparent).light-text #top #logo {
           color: ' . esc_attr($starting_color) . '!important;
         }

				 #nectar-nav.transparent[data-permanent-transparent="false"] #top .slide-out-widget-area-toggle.mobile-icon i:before,
				 #nectar-nav.transparent[data-permanent-transparent="false"] #top .slide-out-widget-area-toggle.mobile-icon i:after,
				 body.material.mobile #nectar-nav.transparent:not([data-permanent-transparent="1"]) header .slide-out-widget-area-toggle a .close-line,
				 body #nectar-nav[data-permanent-transparent="1"].transparent:not(.dark-slide) > #top .span_9 > .slide-out-widget-area-toggle .lines-button:after,
				 body #nectar-nav[data-permanent-transparent="1"].transparent:not(.dark-slide) > #top .span_9 > .slide-out-widget-area-toggle .lines:before,
				 body #nectar-nav[data-permanent-transparent="1"].transparent:not(.dark-slide) > #top .span_9 > .slide-out-widget-area-toggle .lines:after,
         #nectar-nav[data-lhe="animated_underline"].transparent .nectar-header-text-content a:after {
					 background-color: ' . esc_attr($starting_color) . '!important;
				 }

				 #nectar-nav.transparent #top nav > ul > li > a,
				 #nectar-nav.transparent #top nav > .sf-menu > li > a,
         #nectar-nav.transparent .slide-out-widget-area-toggle a i.label,
         #nectar-nav.transparent #top .span_9 > .slide-out-widget-area-toggle a.using-label .label,
				 #nectar-nav.transparent #top nav ul #search-btn a .icon-nectar-blocks-search,
				 #nectar-nav.transparent #top nav ul #nectar-user-account a span,
				 #nectar-nav.transparent #top nav > ul > li > a > .sf-sub-indicator i,
				 #nectar-nav.transparent .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
         #nectar-nav.transparent #top .sf-menu > li.nectar-regular-menu-item > a > .nectar-menu-icon,
         #nectar-nav.transparent .nectar-header-text-content,
         #nectar-nav.transparent .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a,
         #nectar-nav.transparent .nectar-mobile-only.mobile-header li.menu-item-btn-style-border a
				  {
				 	color: ' . esc_attr($starting_color) . '!important;
				 	opacity: ' . esc_attr($starting_opacity) . ';
          will-change: opacity, color;
					transition: opacity 0.2s ease, color 0.2s ease;
				 }
				#nectar-nav.transparent[data-lhe="default"] #top nav > ul > li > a:hover,
				#nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .sfHover:not(#social-in-menu) > a,
				#nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current_page_ancestor > a,
				#nectar-nav.transparent #top nav .sf-menu > .current-menu-item > a,
				#nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a,
				#nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current-menu-item > a,
				#nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current_page_item > a,
				#nectar-nav.transparent #top nav > ul > li > a:hover > .sf-sub-indicator > i,
        #nectar-nav.transparent #top .sf-menu > .sfHover > a .sf-sub-indicator i,
				#nectar-nav.transparent #top nav > ul > .sfHover > a > span > i,
				#nectar-nav.transparent #top nav ul #search-btn a:hover span,
				#nectar-nav.transparent #top nav ul #nectar-user-account a:hover span,
				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:hover span,
				#nectar-nav.transparent #top nav .sf-menu > .current-menu-item > a i,
				body #nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current_page_item > a .sf-sub-indicator i,
				#nectar-nav.transparent #top nav .sf-menu > .current-menu-ancestor > a i,
				body #nectar-nav.transparent[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a i,
				#nectar-nav.transparent .cart-outer:hover .icon-nectar-blocks-cart,
				#nectar-nav.transparent[data-permanent-transparent="false"]:not(.dark-slide) #top .span_9 > a[class*="mobile-"] > *,
				#nectar-nav.transparent[data-permanent-transparent="false"]:not(.dark-slide) #top #mobile-cart-link i,
				#nectar-nav[data-permanent-transparent="1"].transparent:not(.dark-slide) #top .span_9 > a[class*="mobile-"] > *,
				#nectar-nav[data-permanent-transparent="1"].transparent:not(.dark-slide) #top #mobile-cart-link i,
        #nectar-nav.transparent #top .sf-menu > li.nectar-regular-menu-item > a:hover > .nectar-menu-icon,
        #nectar-nav.transparent #top .sf-menu > li.nectar-regular-menu-item.sfHover > a:hover > .nectar-menu-icon,
        #nectar-nav.transparent #top .sf-menu > li.nectar-regular-menu-item[class*="current-"] > a:hover > .nectar-menu-icon,
        #nectar-nav.transparent .nectar-header-text-content:hover,
        #nectar-nav.transparent:not(.dark-slide) .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a:hover,
        .transparent:not(.dark-slide) .nectar-mobile-only.mobile-header li[class*="menu-item-btn-style-button-border"]:not(:hover) > a
				{
					opacity: 1;
					color: ' . esc_attr($starting_color) . '!important;
				}';

        if( '1.0' !== $starting_opacity ) {
                echo '#nectar-nav.transparent[data-lhe="animated_underline"] #top nav > ul > li > a:hover,
          #nectar-nav.transparent[data-lhe="animated_underline"] #top nav > ul > li > a:focus,
  				#nectar-nav.transparent[data-lhe="animated_underline"] #top nav .sf-menu > .sfHover > a,
  				#nectar-nav.transparent[data-lhe="animated_underline"] #top nav .sf-menu > .current-menu-ancestor > a,
  				#nectar-nav.transparent[data-lhe="animated_underline"] #top nav .sf-menu > .current_page_item > a,
          #nectar-nav.transparent[data-lhe="default"] #top nav > ul > li > a:focus,
          #nectar-nav.transparent .slide-out-widget-area-toggle a:hover i.label,
          #nectar-nav.transparent #top nav ul #search-btn a:focus span,
  				#nectar-nav.transparent #top nav ul #nectar-user-account a:focus span,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:focus span,
          #nectar-nav.transparent .nectar-woo-cart .cart-contents:focus .icon-nectar-blocks-cart {
  					opacity: 1;
  				}';
        }

                echo '#nectar-nav[data-lhe="animated_underline"].transparent #top nav > ul > li > a .menu-title-text:after,
        #nectar-nav.transparent #top nav>ul>li[class*="button_bordered"]>a:before,
        #nectar-nav.transparent .nectar-mobile-only.mobile-header li a .menu-title-text:after {
					border-color: ' . esc_attr($starting_color) . '!important;
				}

        .transparent .left-aligned-ocm .lines-button i:before,
        .transparent .left-aligned-ocm .lines-button i:after,
        .transparent .left-aligned-ocm .lines-button:after,
				#nectar-nav.transparent > #top nav ul .slide-out-widget-area-toggle a .lines,
				#nectar-nav.transparent > #top nav ul .slide-out-widget-area-toggle a .lines:before,
				#nectar-nav.transparent > #top nav ul .slide-out-widget-area-toggle a .lines:after,
				body.material #nectar-nav.transparent .slide-out-widget-area-toggle a .close-line,
				#nectar-nav.transparent > #top nav ul .slide-out-widget-area-toggle .lines-button:after {
					background-color: ' . esc_attr($starting_color) . '!important;
				}

				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a .lines,
				body.material:not(.mobile) #nectar-nav.transparent .slide-out-widget-area-toggle a .close-line,
				#nectar-nav.transparent:not(.side-widget-open) #top nav ul .slide-out-widget-area-toggle a .lines-button:after {
					opacity: ' . esc_attr($starting_opacity) . ';
				}';

        if( '1.0' !== $starting_opacity ) {
                echo '#nectar-nav.transparent.side-widget-open #top nav ul .slide-out-widget-area-toggle a .lines,
  				body.material #nectar-nav.transparent .slide-out-widget-area-toggle a:hover .close-line,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:hover .lines,
          #nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:hover .lines-button:after,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:hover .lines:before,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:hover .lines:after,
          #nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:focus .lines-button:after,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:focus .lines:before,
  				#nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:focus .lines:after,
          #nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle a:focus .lines {
  					opacity: 1;
  				}';
        }

        $dark_header_color = $header_starting_dark_color;

    echo ':root {
      --nectar-starting-dark-header-color: ' . esc_attr($dark_header_color) . ';
    }';

        echo '
		#nectar-nav.transparent[data-permanent-transparent="false"].dark-slide #top .slide-out-widget-area-toggle.mobile-icon i:before,
		#nectar-nav.transparent[data-permanent-transparent="false"].dark-slide #top .slide-out-widget-area-toggle.mobile-icon i:after {
			background-color: ' . esc_attr($dark_header_color) . '!important;
		}';

        echo '#nectar-nav.transparent.dark-slide > #top nav > ul > li > a,
		#nectar-nav.transparent.dark-row > #top nav > ul > li > a,
    #nectar-nav.transparent.dark-row .slide-out-widget-area-toggle a i.label,
    #nectar-nav.transparent.dark-slide .slide-out-widget-area-toggle a i.label,
    #nectar-nav.transparent.dark-slide #top .span_9 > .slide-out-widget-area-toggle a.using-label .label,
    #nectar-nav.transparent.dark-row #top .span_9 > .slide-out-widget-area-toggle a.using-label .label,
		#nectar-nav.transparent.dark-slide > #top nav ul #search-btn a span,
		#nectar-nav.transparent.dark-row > #top nav ul #search-btn a span,
		#nectar-nav.transparent.dark-slide > #top nav ul #nectar-user-account a span,
		#nectar-nav.transparent.dark-row > #top nav ul #nectar-user-account a span,
		#nectar-nav.transparent.dark-slide > #top nav > ul > li > a > .sf-sub-indicator [class^="icon-"],
		#nectar-nav.transparent.dark-slide > #top nav > ul > li > a > .sf-sub-indicator [class*=" icon-"],
		#nectar-nav.transparent.dark-row > #top nav > ul > li > a > .sf-sub-indicator [class*=" icon-"],
		#nectar-nav.transparent.dark-slide .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
		#nectar-nav.transparent.dark-row .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart,
		#nectar-nav[data-permanent-transparent="1"].transparent.dark-slide .mobile-search .icon-nectar-blocks-search,
		#nectar-nav[data-permanent-transparent="1"].transparent.dark-slide .mobile-user-account .icon-nectar-blocks-m-user,
	  #nectar-nav[data-permanent-transparent="1"].transparent.dark-slide #top #mobile-cart-link i,
		#nectar-nav.transparent[data-permanent-transparent="false"].dark-slide #top .span_9 > a[class*="mobile-"] > *,
 		#nectar-nav.transparent[data-permanent-transparent="false"].dark-slide #top #mobile-cart-link i,
    #nectar-nav.transparent.dark-slide #top .sf-menu > li.nectar-regular-menu-item > a > .nectar-menu-icon,
    #nectar-nav.transparent.dark-slide .nectar-header-text-content,
    #nectar-nav.dark-slide .nectar-mobile-only.mobile-header li:not([class*="menu-item-btn-style"]) a  {
		 	color: ' . esc_attr($dark_header_color) . '!important;
		 }

		#nectar-nav.transparent.dark-slide > #top nav ul .slide-out-widget-area-toggle a .lines-button i:after,
		#nectar-nav.transparent.dark-slide > #top nav ul .slide-out-widget-area-toggle a .lines-button i:before,
		#nectar-nav.transparent.dark-slide > #top nav ul .slide-out-widget-area-toggle .lines-button:after,
    .transparent.dark-slide .left-aligned-ocm .lines-button i:before,
    .transparent.dark-slide .left-aligned-ocm .lines-button i:after,
    .transparent.dark-slide .left-aligned-ocm .lines-button:after,
		body.marterial #nectar-nav.transparent.dark-slide > #top nav .slide-out-widget-area-toggle a .close-line,
		body #nectar-nav[data-permanent-transparent="1"].transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle.mobile-icon .lines-button:after,
		body #nectar-nav[data-permanent-transparent="1"].transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle.mobile-icon .lines:before,
		body #nectar-nav[data-permanent-transparent="1"].transparent.dark-slide > #top .span_9 > .slide-out-widget-area-toggle.mobile-icon .lines:after,
     #nectar-nav[data-lhe="animated_underline"].transparent.dark-slide .nectar-header-text-content a:after {
			background-color: ' . esc_attr($dark_header_color) . '!important;
		}

		#nectar-nav.transparent.dark-slide > #top nav > ul > li > a:hover,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .sfHover > a,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current_page_ancestor > a,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current-menu-item > a,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current-menu-ancestor > a,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current_page_item > a,
		#nectar-nav.transparent.dark-slide > #top nav > ul > li > a:hover > .sf-sub-indicator > i,
		#nectar-nav.transparent.dark-slide > #top nav > ul > .sfHover > a > span > i,
		#nectar-nav.transparent.dark-slide > #top nav ul #search-btn a:hover span,
		#nectar-nav.transparent.dark-slide > #top nav ul #nectar-user-account a:hover span,
		body #nectar-nav.dark-slide.transparent[data-lhe="default"] #top nav .sf-menu > .current_page_item > a .sf-sub-indicator i,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current-menu-item > a i,
		#nectar-nav.transparent.dark-slide > #top nav .sf-menu > .current-menu-ancestor > a i,
		body #nectar-nav.dark-slide.transparent[data-lhe="default"] #top nav .sf-menu > .current-menu-ancestor > a i,
		#nectar-nav.transparent.dark-slide  > #top .cart-outer:hover .icon-nectar-blocks-cart,
		#nectar-nav.transparent.dark-slide > #top #logo,
		#nectar-nav.transparent[data-lhe="default"].dark-slide #top nav .sf-menu > .current_page_item > a,
		#nectar-nav.transparent[data-lhe="default"].dark-slide #top nav .sf-menu > .current-menu-ancestor > a,
		#nectar-nav.transparent[data-lhe="default"].dark-slide #top nav > ul > li > a:hover,
		#nectar-nav.transparent[data-lhe="default"].dark-slide #top nav .sf-menu > .sfHover:not(#social-in-menu) > a,
		#nectar-nav.transparent.dark-slide #top nav > ul > .sfHover > a > span > i,
		.swiper-wrapper .swiper-slide[data-color-scheme="dark"] .slider-down-arrow i.icon-default-style[class^="icon-"],
		.slider-prev.dark-cs i,
		.slider-next.dark-cs i,
		.swiper-container .dark-cs.slider-prev .slide-count span,
		.swiper-container .dark-cs.slider-next .slide-count span,
    #nectar-nav.transparent.dark-slide #top .sf-menu > li.nectar-regular-menu-item > a:hover > .nectar-menu-icon,
    #nectar-nav.transparent.dark-slide #top .sf-menu > li.nectar-regular-menu-item.sfHover > a:hover > .nectar-menu-icon,
    #nectar-nav.transparent.dark-slide #top .sf-menu > li.nectar-regular-menu-item[class*="current-"] > a:hover > .nectar-menu-icon,
    #nectar-nav.transparent.dark-slide .nectar-header-text-content:hover,
    .transparent.dark-slide .nectar-mobile-only.mobile-header li[class*="menu-item-btn-style-button-border"]:not(:hover) > a {
			color: ' . esc_attr($dark_header_color) . '!important;
		}

		#nectar-nav[data-lhe="animated_underline"].transparent.dark-slide #top nav > ul > li > a .menu-title-text:after,
    #nectar-nav.dark-slide.transparent:not(.side-widget-open) #top nav>ul>li[class*="button_bordered"]>a:before,
    #nectar-nav.dark-slide .nectar-mobile-only.mobile-header li a .menu-title-text:after {
			border-color: ' . esc_attr($dark_header_color) . '!important;
		}

		.swiper-container[data-bullet_style="scale"] .slider-pagination.dark-cs .swiper-pagination-switch.swiper-active-switch i,
		.swiper-container[data-bullet_style="scale"] .slider-pagination.dark-cs .swiper-pagination-switch:hover i {
			background-color: ' . esc_attr($dark_header_color) . ';
		}

		.slider-pagination.dark-cs .swiper-pagination-switch {
			 border: 1px solid ' . esc_attr($dark_header_color) . ';
			 background-color: transparent;
		}
		.slider-pagination.dark-cs .swiper-pagination-switch:hover {
			background: none repeat scroll 0 0 ' . esc_attr($dark_header_color) . ';
		}

		.slider-pagination.dark-cs .swiper-active-switch {
			 background: none repeat scroll 0 0 ' . esc_attr($dark_header_color) . ';
		}
		';

       $dark_header_color = str_replace("#", "", $dark_header_color);
         $darkcolorR = hexdec( substr( $dark_header_color, 0, 2 ) );
         $darkcolorG = hexdec( substr( $dark_header_color, 2, 2 ) );
         $darkcolorB = hexdec( substr( $dark_header_color, 4, 2 ) );
         echo '
		 #fp-nav:not(.light-controls) ul li a span:after {
			 background-color: #' . esc_attr($dark_header_color) . ';
		 }
		 #fp-nav:not(.light-controls) ul li a span {
			 box-shadow: inset 0 0 0 8px rgba(' . $darkcolorR . ',' . $darkcolorG . ',' . $darkcolorB . ',0.3);
			 -webkit-box-shadow: inset 0 0 0 8px rgba(' . $darkcolorR . ',' . $darkcolorG . ',' . $darkcolorB . ',0.3);
		 }
		 body #fp-nav ul li a.active span  {
			 box-shadow: inset 0 0 0 2px rgba(' . $darkcolorR . ',' . $darkcolorG . ',' . $darkcolorB . ',0.8);
			 -webkit-box-shadow: inset 0 0 0 2px rgba(' . $darkcolorR . ',' . $darkcolorG . ',' . $darkcolorB . ',0.8);
		 }';

     } // Using transparent theme option

    // Custom off canvas navigation menu button coloring.
  $ocm_menu_btn_bg_color = false;
  $ocm_menu_btn_color = false;
  $full_width_header = (! empty($nectar_options['header-fullwidth']) && $nectar_options['header-fullwidth'] === '1') ? true : false;

  if ( $headerFormat === 'centered-menu-under-logo' ) {
    if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
      $side_widget_class = 'slide-out-from-right';
    }
    $full_width_header = false;
  }
  if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
    $full_width_header = true;
  }

  $ocm_menu_btn_color_non_compatible = ( 'ascend' === $theme_skin && true === $full_width_header ) ? true : false;

  if( true !== $ocm_menu_btn_color_non_compatible &&
  isset($nectar_options['header-slide-out-widget-area-menu-btn-color']) &&
  ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-color'] ) ) {

    $ocm_menu_btn_color = $nectar_options['header-slide-out-widget-area-menu-btn-color'];

    echo 'body #nectar-nav[data-has-menu][data-format][data-padding] #top .slide-out-widget-area-toggle[data-custom-color="true"] a i.label,
    body #nectar-nav.transparent #top .slide-out-widget-area-toggle[data-custom-color="true"] a i.label {
      color: ' . esc_attr($ocm_menu_btn_color) . '!important;
    }
    body #nectar-nav[data-has-menu][data-format][data-padding][data-using-logo] > #top .slide-out-widget-area-toggle[data-custom-color="true"] .lines-button:after,
    body #nectar-nav[data-has-menu][data-format][data-padding][data-using-logo] > #top .slide-out-widget-area-toggle[data-custom-color="true"] a .lines-button i:before,
    body #nectar-nav[data-has-menu][data-format][data-padding][data-using-logo] > #top .slide-out-widget-area-toggle[data-custom-color="true"] a .lines-button i.lines:after,
    body.material #nectar-nav .slide-out-widget-area-toggle[data-custom-color="true"] a .close-line,
    body.material #nectar-nav[data-using-logo].transparent .slide-out-widget-area-toggle[data-custom-color="true"] a .close-line,
    body.material:not(.mobile) #nectar-nav.transparent .slide-out-widget-area-toggle[data-custom-color="true"] a .close-line {
      background-color: ' . esc_attr($ocm_menu_btn_color) . '!important;
      opacity: 1;
    }
    #nectar-nav.transparent #top nav ul .slide-out-widget-area-toggle[data-custom-color="true"] a .lines {
      opacity: 1;
    }';

  }

  if( true !== $ocm_menu_btn_color_non_compatible &&
  isset($nectar_options['header-slide-out-widget-area-menu-btn-bg-color']) &&
  ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'] ) ) {

    $ocm_menu_btn_bg_color = $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'];
    $mobile_padding_mod = ( $mobile_logo_height < 38 ) ? 10 : 0;

    echo 'body #nectar-nav #top .slide-out-widget-area-toggle[data-custom-color="true"] a:before {
      background-color: ' . esc_attr($ocm_menu_btn_bg_color ) . ';
      height: 36px;
    }

    @media only screen and (max-width: 1024px) {
      body #nectar-nav #logo {
       position: relative;
       margin: ' . esc_attr($mobile_padding_mod) . 'px 0;
      }

      #nectar-nav-spacer {
 			 padding-top: ' . ( $mobile_header_space + ($mobile_padding_mod * 2)) . 'px;
 		 }

    #top #mobile-cart-link, #top .mobile-search, #nectar-nav #top .mobile-user-account {
      padding: 0 10px;
    }

    }';

    if( true === $menu_label &&
    ! empty($mobile_breakpoint) &&
    $mobile_breakpoint != 1025 &&
    $headerFormat !== 'left-header' &&
    $has_main_menu === 'true' ) {
      echo '@media only screen and (min-width: 1025px) and (max-width: ' . esc_attr($mobile_breakpoint) . 'px) {
        body #nectar-nav[data-format="menu-left-aligned"]:not([data-format="left-header"]):not([data-format="centered-menu-bottom-bar"]) #top nav > .buttons {
          margin-right: 140px;
        }
      }';
    }

  }

  // Circular ocm icon.
  if( false === $menu_label &&
  false !== $ocm_menu_btn_bg_color &&
  isset($nectar_options['header-slide-out-widget-area-icon-style']) &&
  ! empty( $nectar_options['header-slide-out-widget-area-icon-style'] ) &&
  'circular' === $nectar_options['header-slide-out-widget-area-icon-style']) {
    echo 'body #nectar-nav #top .slide-out-widget-area-toggle[data-custom-color] a:before {
      height: 46px;
      padding-bottom: 0;
    }
    body #nectar-nav #top .slide-out-widget-area-toggle[data-custom-color] a {
      padding: 0 12px;
    }
    body[data-button-style] #nectar-nav .slide-out-widget-area-toggle[data-custom-color="true"] a:before {
      border-radius: 100px!important;
    }
    body[data-slide-out-widget-area-style*="fullscreen"] #top .slide-out-widget-area-toggle:not(.small) a .close-wrap {
      height: 22px;
    }
    body[data-slide-out-widget-area-style*="fullscreen"] #top .slide-out-widget-area-toggle .close-line {
      left: 10px;
    }
    #nectar-nav[data-format="centered-menu-bottom-bar"] #top .slide-out-widget-area-toggle[data-custom-color="true"] a:before,
    #nectar-nav[data-format="centered-menu-under-logo"] #top .slide-out-widget-area-toggle[data-custom-color="true"] a:before {
      transform: translateY(-14px);
    }

    @media only screen and (min-width: 1025px) {
      #nectar-nav li[class*="menu-item-btn-style"] > a:before,
      #nectar-nav li[class*="menu-item-btn-style"] > a:after {
        height: calc(100% + 28px);
      }
    }
    @media only screen and (max-width: 1024px) {
      body #nectar-nav #top .slide-out-widget-area-toggle[data-custom-color] a:before {
        height: 40px;
      }
      body #nectar-nav #top .slide-out-widget-area-toggle[data-custom-color] a {
        padding: 0 9px;
      }
      #top .slide-out-widget-area-toggle[data-custom-color] a > span {
        transform: scale(0.8);
      }
    }

    @media only screen and (max-width: 767px) {
      body #nectar-nav[data-full-width="true"] header > .container {
        padding: 0 25px;
      }
    }
    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 5. Extended Responsive Theme Option
  /*-------------------------------------------------------------------------*/
    global $woocommerce;

  $ext_padding = '90';

    if( isset( $nectar_options['ext_responsive_padding'] ) &&
    ! empty( $nectar_options['ext_responsive_padding'] ) &&
    '90' !== $nectar_options['ext_responsive_padding'] ) {
        $ext_padding = $nectar_options['ext_responsive_padding'];
    }

    echo ':root {
      --wp--style--root--padding-left: ' . esc_attr($ext_padding) . 'px;
      --wp--style--root--padding-right: ' . esc_attr($ext_padding) . 'px;
      --container-padding: ' . esc_attr($ext_padding) . 'px;
      --nectar-resp-container-padding: ' . esc_attr($ext_padding) . 'px;
   }

   @media only screen and (max-width: 1024px) {
    :root {
      --nectar-resp-container-padding: calc( (100% - var(--mobile-container-width))/2 );
    }
   }';

        echo '@media only screen and (min-width: 1025px) {
			    .container,
					body[data-header-format="left-header"] .container,
					.woocommerce-tabs .full-width-content .tab-container,
					.material #search-outer #search  {
			      max-width: 1425px;
					  width: 100%;
					  margin: 0 auto;
					  padding: 0px ' . esc_attr($ext_padding) . 'px;
			    }';

          if( 'left-header' === $headerFormat ) {
            echo 'body[data-header-format="left-header"] .container,
            body[data-header-format="left-header"] .woocommerce-tabs .full-width-content .tab-container {
  			    	padding: 0 60px;
  			    }';
          }

                echo 'body .container .page-submenu.stuck .container:not(.tab-container):not(.normal-container),
			    #nectar_fullscreen_rows[data-footer="default"] #footer-widgets .container,
					#nectar_fullscreen_rows[data-footer="default"] #copyright .container {
			    	  padding: 0px ' . esc_attr($ext_padding) . 'px!important;
			    }

  				.swiper-slide .content {
  				  padding: 0px ' . esc_attr($ext_padding) . 'px;
  				}';

          if( 'left-header' === $headerFormat ) {
                    echo 'body[data-header-format="left-header"] .container .page-submenu.stuck .container:not(.tab-container) {
    			    	  padding: 0px 60px!important;
    			    }

    				body[data-header-format="left-header"] .swiper-slide .content {
    				  padding: 0px 60px;
    				}';
          }

                echo 'body .container .container:not(.tab-container):not(.recent-post-container):not(.normal-container) {
					width: 100%!important;
					padding: 0!important;
				}

				body .carousel-heading .container .carousel-next {
					right: 10px;
				}
				body .carousel-heading .container .carousel-prev {
					right: 35px;
				}
				.carousel-wrap[data-full-width="true"] .carousel-heading .portfolio-page-link {
					left: ' . esc_attr($ext_padding) . 'px;
				}
				.carousel-wrap[data-full-width="true"] .carousel-heading {
					margin-left: -20px;
					margin-right: -20px;
				}
				#nectar-content-wrap .carousel-wrap[data-full-width="true"] .carousel-next {
					right: ' . esc_attr($ext_padding) . 'px;
				}
		   	#nectar-content-wrap .carousel-wrap[data-full-width="true"] .carousel-prev {
					right: ' . (intval($ext_padding) + 25) . 'px;
				}
				.carousel-wrap[data-full-width="true"] {
					padding: 0;
				}
				.carousel-wrap[data-full-width="true"] .caroufredsel_wrapper {
					padding: 20px;
				}

				#search-outer #search #close a {
					right: ' . esc_attr($ext_padding) . 'px;
				}

        body.material #search-outer #search #close {
					right: ' . esc_attr($ext_padding) . 'px;
				}
        body.material #search-outer #search #close a {
          right: 12px;
        }';

             echo '}';

          // Custom max width theme option.
          if(! empty($nectar_options['max_container_width'])) {
               echo '@media only screen and (min-width: 1025px) {
						 .container,
						 body[data-header-format="left-header"] .container,
						 .woocommerce-tabs .full-width-content .tab-container,
						 .material #search-outer #search {
							 max-width: ' . esc_attr($nectar_options['max_container_width']) . 'px;
						 }
					 }

           :root {
              --container-width: ' . esc_attr($nectar_options['max_container_width']) . 'px;
           }
           html body {
            --wp--style--global--content-size: ' . esc_attr($nectar_options['max_container_width']) . 'px;
            --wp--style--global--wide-size: ' . intval($nectar_options['max_container_width'] + 300) . 'px;
           }';
          } else {
        echo '@media only screen and (min-width: 1025px) {
          :root {
            --container-width: 1425px;
         }
         html body {
          --wp--style--global--content-size: 1425px;
         }
        }';
      }

    /*-------------------------------------------------------------------------*/
    /* 6. Form Sizing
    /*-------------------------------------------------------------------------*/

    /*-------------------------------------------------------------------------*/
    /* 6.1. Fancy Selects
    /*-------------------------------------------------------------------------*/
    if( isset( $nectar_options['form-fancy-select'] ) &&
        ! empty($nectar_options['form-fancy-select']) &&
        '1' === $nectar_options['form-fancy-select'] ) {
      echo '
      body[data-form-select-js="1"] .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #aaa;
        padding: 4px;
      }
      body[data-form-select-js="1"] .variations select {
        padding: 8px;
      }

      body[data-form-select-js="1"] .select2-container .select2-choice,
      body[data-form-select-js="1"] .select2-container--default .select2-selection--single {
        height: auto;
        background-color:transparent;
        border-color:#e0e0e0;
        padding-top:5px;
        padding-bottom:5px;
        -webkit-transition:background-color 0.15s cubic-bezier(.39,.71,.56,.98),color 0.15s cubic-bezier(.39,.71,.56,.98);
        transition:background-color 0.15s cubic-bezier(.39,.71,.56,.98),color 0.15s cubic-bezier(.39,.71,.56,.98)
      }
      body[data-form-select-js="1"].woocommerce-checkout .select2-container--default .select2-selection--single {
        color: #555;
      }
      body[data-form-select-js="1"] .select2-container .select2-choice:hover .select2-arrow b:after{
        -webkit-transition:border-color 0.15s cubic-bezier(.39,.71,.56,.98);
        transition:border-color 0.15s cubic-bezier(.39,.71,.56,.98)
      }
      body[data-form-select-js="1"] .select2-drop,
      body[data-form-select-js="1"] .select2-dropdown{
        border:none;
        background-color:#fff;
        box-shadow:0 0 6px rgba(0,0,0,0.2)
      }
      body[data-form-select-js="1"] .woocommerce-ordering .select2-dropdown {
        box-shadow: 0 6px 28px rgba(0,0,0,0.08);
      }
      body[data-form-select-js="1"] .select2-container,
      body[data-form-select-js="1"] .select2-drop,
      body[data-form-select-js="1"] .select2-search,
      .select2-search input{
        font-size:14px
      }
      body[data-form-select-js="1"] .select2-container:not(.select2-dropdown-open) .select2-choice:hover .select2-arrow b:after,
      body[data-form-select-js="1"] .select2-container--default:hover .select2-selection--single .select2-selection__arrow b,
      body[data-form-select-js="1"] .select2-container--open .select2-selection__arrow b {
        border-top-color:#fff
      }
      body[data-form-select-js="1"] .select2-dropdown-open .select2-choice .select2-arrow b:after,
      body[data-form-select-js="1"] .select2-container--default:hover .select2-selection--single .select2-selection__arrow b,
      body[data-form-select-js="1"] .select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-bottom-color:#fff
      }
      body[data-form-select-js="1"] .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        width: 30px;
        top: 0;
      }
      body[data-form-select-js="1"] .select2-container .select2-selection--single .select2-selection__rendered {
        padding-left: 10px;
        padding-right: 30px;
      }
      body[data-form-select-js="1"] .select2-container .select2-dropdown {
        color: #000;
      }
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container .select2-selection--single .select2-selection__rendered {
        padding-left: 0;
        padding-right: 20px;
        line-height: 1.9;
      }
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default .select2-selection--single .select2-selection__arrow {
        width: 12px;
      }
      body[data-form-select-js="1"] .select2-container--default .select2-results__option[aria-selected=true],
      body[data-form-select-js="1"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #f0f0f0!important;
      }
      body[data-form-select-js="1"] .select2-drop.select2-drop-above .select2-search input,
      body[data-form-select-js="1"] .select2-drop.select2-drop-below .select2-search input,
      body[data-form-select-js="1"] .select2-drop .select2-search input[type="text"]{
        padding:0 4px!important;
        margin-top:7px!important
      }
      body[data-form-select-js="1"] .select2-container .select2-choice:hover,
      body[data-form-select-js="1"] .select2-container .select2-choice:hover >.select2-chosen,
      body[data-form-select-js="1"] .select2-dropdown-open .select2-choice,
      body[data-form-select-js="1"] .select2-dropdown-open .select2-choice >.select2-chosen{
        color:#fff!important;
        box-shadow:none;
        -webkit-box-shadow:none
      }
      body[data-form-select-js="1"].admin-bar .select2-drop.select2-drop-above.select2-drop-active{
        margin-top:-33px
      }
      body[data-form-select-js="1"] .fancy-select-wrap{
        padding-top:12px
      }
      body[data-form-select-js="1"] .fancy-select-wrap label{
        padding-bottom:0;
        font-size:12px;
        display:inline-block;
        color:#acacac!important
      }
      body[data-form-select-js="1"] .woocommerce-ordering select,
      body[data-form-select-js="1"] select {
        color: inherit;
      }
      body[data-form-select-js="1"] .select2-container--default .select2-selection--single .select2-selection__rendered,
      body[data-form-select-js="1"] .select2-container--default .select2-results__option--highlighted[aria-selected],
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default:hover .select2-selection--single .select2-selection__rendered,
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default.select2-container--open .select2-selection--single .select2-selection__rendered {
        color: inherit!important;
      }

      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default .select2-selection--single .select2-selection__arrow b,
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default:hover .select2-selection--single .select2-selection__arrow b {
        border-top-color: inherit;
        transition: transform 0.3s ease;
      }

      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: inherit transparent transparent transparent;
        border-width: 5px 4px 0 4px;
        transform: rotate(180deg);
      }

      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-bottom-color: inherit;
      }
      body[data-form-select-js="1"] .select2-container--default:hover .select2-selection--single .select2-selection__rendered,
      body[data-form-select-js="1"] .select2-container--default.select2-container--open .select2-selection--single .select2-selection__rendered {
        color: #fff!important;
      }
      body[data-form-select-js="1"] .select2-container--default .select2-selection--single:hover .select2-selection__placeholder {
        color: #fff;
      }';
    }

    /*-------------------------------------------------------------------------*/
    /* 6.2. Input Coloring
    /*-------------------------------------------------------------------------*/
    $woo_qty_style = ( isset( $nectar_options['qty_button_style'] ) && ! empty( $nectar_options['qty_button_style'] ) ) ? $nectar_options['qty_button_style'] : 'default';
    $form_input_bg_color = ( isset( $nectar_options['form-input-bg-color'] ) && ! empty($nectar_options['form-input-bg-color']) ) ? $nectar_options['form-input-bg-color'] : false;
    $form_input_text_color = ( isset( $nectar_options['form-input-text-color'] ) && ! empty($nectar_options['form-input-text-color']) ) ? $nectar_options['form-input-text-color'] : false;
    $form_input_border_color = ( isset( $nectar_options['form-input-border-color'] ) && ! empty($nectar_options['form-input-border-color']) ) ? $nectar_options['form-input-border-color'] : false;
    $form_input_border_color_h = ( isset( $nectar_options['form-input-border-color-hover'] ) && ! empty($nectar_options['form-input-border-color-hover']) ) ? $nectar_options['form-input-border-color-hover'] : false;
    $form_input_border_color_f = ( isset( $nectar_options['form-input-border-color-focus'] ) && ! empty($nectar_options['form-input-border-color-focus']) ) ? $nectar_options['form-input-border-color-focus'] : false;

    $minimal_form_style = ( isset( $nectar_options['form-style'] ) && ! empty($nectar_options['form-style']) && 'minimal' === $nectar_options['form-style'] ) ? true : false;

    $form_input_props = '';
    $form_input_props_hover = '';
    $form_input_props_focus = '';

    // Input BG
    if( $form_input_bg_color ) {
      $form_input_props .= 'background-color: ' . esc_attr($form_input_bg_color) . ';';
    }

    // Input Border
    if( $form_input_border_color ) {

      echo ':root {
        --nectar-input-border-color: ' . esc_attr($form_input_border_color) . ';
      }';

      if( 'default' !== $woo_qty_style ) {
        echo 'body .cart div.quantity, body .woocommerce-mini-cart div.quantity {
          border-color: ' . esc_attr($form_input_border_color) . ';
        }';
      }

      $form_input_props .= 'border-color: ' . esc_attr($form_input_border_color) . ';';
    }

    // Input Border Hover
    if( $form_input_border_color_h ) {

      if( 'default' !== $woo_qty_style ) {
        echo 'body .cart div.quantity:hover, body .woocommerce-mini-cart div.quantity:hover {
          border-color: ' . esc_attr($form_input_border_color_h) . ';
        }';
      }

      $form_input_props_hover .= 'border-color: ' . esc_attr($form_input_border_color_h) . ';';
    }

    // Input Border Focus
    if( $form_input_border_color_f ) {
      $form_input_props_focus .= 'border-color: ' . esc_attr($form_input_border_color_f) . '!important;';
    }

    // Input Text Coloring
    if( $form_input_text_color ) {
      $form_input_props .= 'color: ' . esc_attr($form_input_text_color) . ';';
      echo '.widget_search .search-form input[type=text]::placeholder {
        color: ' . esc_attr($form_input_text_color) . ';
        opacity: 0.7;
      }';
    }

    /*-------------------------------------------------------------------------*/
    /* 6.3. Input Padding
    /*-------------------------------------------------------------------------*/

    // Form input button padding.
    if( isset( $nectar_options['form-input-spacing'] ) && ! empty($nectar_options['form-input-spacing']) ) {

      // Top.
      $form_input_padding_top = false;
      if( isset( $nectar_options['form-input-spacing']['padding-top'] ) &&
          ! empty($nectar_options['form-input-spacing']['padding-top']) ) {
        $form_input_padding_top = $nectar_options['form-input-spacing']['padding-top'];
      }

      // Right.
      $form_input_padding_right = false;
      if( isset( $nectar_options['form-input-spacing']['padding-right'] ) &&
          ! empty($nectar_options['form-input-spacing']['padding-right']) ) {
        $form_input_padding_right = $nectar_options['form-input-spacing']['padding-right'];
      }

      // Verify a custom val was set for atleast one prop before creating rule.
      if( false !== $form_input_padding_top ||
      false !== $form_input_padding_right ) {

        $skin_selector = ( 'ascend' === $theme_skin || true === $minimal_form_style ) ? 'body .container-wrap ' : '';

        echo esc_attr($skin_selector) . 'input[type=text],
        ' . esc_attr($skin_selector) . 'input[type=email],
        ' . esc_attr($skin_selector) . 'input[type=password],
        ' . esc_attr($skin_selector) . 'input[type=tel],
        ' . esc_attr($skin_selector) . 'input[type=url],
        ' . esc_attr($skin_selector) . 'input[type=search],
        ' . esc_attr($skin_selector) . 'input[type=date],
        ' . esc_attr($skin_selector) . 'input[type=number],
        ' . esc_attr($skin_selector) . 'textarea,
        .woocommerce input#coupon_code {';
          if( false !== $form_input_padding_top ) {
            echo 'padding-top: ' . esc_attr($form_input_padding_top) . '; padding-bottom: ' . esc_attr($form_input_padding_top) . ';';
          }
          if( false !== $form_input_padding_right ) {
            echo 'padding-right: ' . esc_attr($form_input_padding_right) . '; padding-left: ' . esc_attr($form_input_padding_right) . ';';
          }

          echo 'line-height: 1em;';

        echo '}';

        echo 'body[data-form-select-js="1"] .variations select,
        body[data-form-select-js="1"] .select2-container--default .select2-selection--single {';
          if( false !== $form_input_padding_top ) {
            echo 'padding-top: ' . esc_attr($form_input_padding_top) . '; padding-bottom: ' . esc_attr($form_input_padding_top) . ';';
          }
        echo '}';

        echo '.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.6em; }';

      }

    } // End form input padding.

    /*-------------------------------------------------------------------------*/
    /* 6.4. Submit Button Sizing
    /*-------------------------------------------------------------------------*/

    // See through submit
    if( isset( $nectar_options['form-submit-btn-style'] ) && 'see-through' === $nectar_options['form-submit-btn-style'] ) {
      echo '
      body[data-form-b-style="see-through"] input[type=submit]:hover,
      body[data-form-b-style="see-through"] button[type=submit]:not(.search-widget-btn):hover {
        color: var(--nectar-accent-text-color, #fff)!important;
      }
      body[data-form-b-style="see-through"] .container-wrap input[type=submit],
      body[data-form-b-style="see-through"] .container-wrap button[type=submit]:not(.search-widget-btn) {
        padding:15px 22px!important
      }

      body[data-form-b-style="see-through"] input[type=submit],
      body[data-form-b-style="see-through"].woocommerce #respond input#submit,
      body[data-form-b-style="see-through"] button[type=submit]:not(.search-widget-btn),
      [data-form-b-style="see-through"] .woocommerce #order_review #payment #place_order {
        background-color:transparent!important;
        border:2px solid #000;
      }
    ';
    }

    // Form submit button padding.
    if( isset( $nectar_options['form-submit-spacing'] ) && ! empty($nectar_options['form-submit-spacing']) ) {

      // Top.
      $form_submit_padding_top = false;
      if( isset( $nectar_options['form-submit-spacing']['padding-top'] ) &&
          ! empty($nectar_options['form-submit-spacing']['padding-top']) ) {
        $form_submit_padding_top = $nectar_options['form-submit-spacing']['padding-top'];
      }

      // Right.
      $form_submit_padding_right = false;
      if( isset( $nectar_options['form-submit-spacing']['padding-right'] ) &&
          ! empty($nectar_options['form-submit-spacing']['padding-right']) ) {
        $form_submit_padding_right = $nectar_options['form-submit-spacing']['padding-right'];
      }

      // Verify a custom val was set for atleast one prop before creating rule.
      if( false !== $form_submit_padding_top ||
      false !== $form_submit_padding_right) {

        echo 'body[data-form-b-style="default"] .container-wrap input[type=submit],
        body[data-form-b-style="regular"] .container-wrap input[type=submit],
        body[data-form-b-style="regular"] .container-wrap button[type=submit]:not(.search-widget-btn),
        body[data-form-b-style="see-through"] .container-wrap input[type=submit],
        body[data-form-b-style="see-through"] .container-wrap button[type=submit]:not(.search-widget-btn),
        .wc-proceed-to-checkout .button.checkout-button,
        .woocommerce #order_review #payment #place_order,
        body.woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
        .woocommerce-page button[type="submit"].single_add_to_cart_button,
        body[data-form-b-style="regular"].woocommerce-page .container-wrap button[type=submit].single_add_to_cart_button,
        .nectar-post-grid-wrap .load-more,
        .row .wpforms-form button[type="submit"] {';

          if( false !== $form_submit_padding_top ) {
            echo 'padding-top: ' . esc_attr($form_submit_padding_top) . '!important; padding-bottom: ' . esc_attr($form_submit_padding_top) . '!important;';
          }
          if( false !== $form_submit_padding_right ) {
            echo 'padding-right: ' . esc_attr($form_submit_padding_right) . '!important; padding-left: ' . esc_attr($form_submit_padding_right) . '!important;';
          }

          echo 'line-height: 1.2em; height: auto;';

        echo '}';

      }

    } // End form submit button padding.

    /*-------------------------------------------------------------------------*/
    /* 6.5. Minimal Styling
    /*-------------------------------------------------------------------------*/
    if( isset( $nectar_options['form-style'] ) && ! empty($nectar_options['form-style']) && 'minimal' === $nectar_options['form-style'] ) {
      echo 'body[data-form-style="minimal"] input[type="text"],
      body[data-form-style="minimal"] textarea,
      body[data-form-style="minimal"] input[type="email"],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="email"],
      body[data-form-style="minimal"] input[type=password],
      body[data-form-style="minimal"] input[type=tel],
      body[data-form-style="minimal"] input[type=url],
      body[data-form-style="minimal"] input[type=search],
      body[data-form-style="minimal"] input[type=date],
      body[data-form-style="minimal"] input[type=number],
      body[data-form-style="minimal"] select {
        background-color: rgba(0,0,0,0.035);
        box-shadow:none;
        -webkit-box-shadow:none;
        border:none;
        position:relative;
        margin:0;
        font-size:14px;
        border-bottom: 2px solid #e0e0e0;
        -webkit-transition: border-color 0.2s ease;
        transition: border-color 0.2s ease;
        border-radius: 0;
      }
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="text"],
      body[data-form-style="minimal"] .container-wrap .span_12.light textarea,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="email"],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=password],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=tel],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=url],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=search],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=date],
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=number],
      body[data-form-style="minimal"] .container-wrap .span_12.light select {
        color:#fff;
        border-top: 0;
        border-left: 0;
        border-right: 0;
        background-color: rgba(255,255,255,0.04);
      	box-shadow: none;
        border-bottom: 2px solid transparent;
      }
      body[data-form-style="minimal"].material .span_12.light .select2-container--default .select2-selection--single,
      body[data-form-style="minimal"][data-form-select-js="1"].material .span_12.light .select2-container--default .select2-selection--single {
        background-color: rgba(255,255,255,0.04);
      }

      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="text"]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light textarea:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="email"]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=password]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=tel]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=url]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=search]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=date]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type=number]:focus,
      body[data-form-style="minimal"] .container-wrap .span_12.light select:focus {
      	border-top: 0;
        border-left: 0;
        border-right: 0;
      	border-color: #fff;
      }

      body[data-form-style="minimal"] textarea,
      body[data-form-style="minimal"].woocommerce #review_form #respond textarea{
        padding: 20px;
      }
      body[data-form-style="minimal"] .widget_search .search-form .search-submit{
        top:30px
      }';
    } // End minimal styling.

    /*-------------------------------------------------------------------------*/
    /* 6.6. Input Sizing
    /*-------------------------------------------------------------------------*/
    if( isset( $nectar_options['form-input-font-size'] ) && ! empty($nectar_options['form-input-font-size']) ) {

      echo 'span.wpcf7-not-valid-tip,
      .woocommerce input#coupon_code,
      body[data-form-select-js="1"] .select2-container,
      body[data-form-select-js="1"] .select2-drop,
      body[data-form-select-js="1"] .select2-search,
      .select2-search input,
      body[data-form-style="minimal"] .container-wrap .span_12.light input[type="email"] {
        font-size: ' . esc_attr($nectar_options['form-input-font-size']) . 'px;
      }';

      $form_input_props .= 'font-size: ' . esc_attr($nectar_options['form-input-font-size']) . 'px;';

    }

    /*-------------------------------------------------------------------------*/
    /* 6.8. Input Border Width
    /*-------------------------------------------------------------------------*/
    if( isset( $nectar_options['form-input-border-width'] ) &&
        ! empty($nectar_options['form-input-border-width']) &&
        'default' !== $nectar_options['form-input-border-width'] ) {

        if( 'default' !== $woo_qty_style ) {
          echo 'body .cart div.quantity, body .woocommerce-mini-cart div.quantity {
            border-width: ' . esc_attr($nectar_options['form-input-border-width']) . ';
          }';
        }

        if( 'original' === $theme_skin && false === $minimal_form_style ) {
          $form_input_props .= 'border-style: solid;';
        }

        $form_input_props .= 'border-width: ' . esc_attr($nectar_options['form-input-border-width']) . ';';

    }

    /*-------------------------------------------------------------------------*/
    /* 6.9. Input Styling Output
    /*-------------------------------------------------------------------------*/

    if( ! empty($form_input_props) ) {

      echo '.container-wrap input[type=text],
      .container-wrap input[type=email],
      .container-wrap input[type=password],
      .container-wrap input[type=tel],
      .container-wrap input[type=url],
      .container-wrap input[type=search],
      .container-wrap input[type=date],
      .container-wrap input[type=number],
      .container-wrap textarea,
      .container-wrap select,
      body > #review_form_wrapper.modal input[type=text],
      body > #review_form_wrapper.modal textarea,
      body > #review_form_wrapper.modal select,
      body > #review_form_wrapper.modal input[type=email],
      .select2-container--default .select2-selection--single,
      body[data-form-select-js="1"] .select2-container--default .select2-selection--single,
      .woocommerce input#coupon_code,
      .material.woocommerce-page[data-form-style="default"] input#coupon_code,
      body[data-form-style="minimal"] input[type="text"],
      body[data-form-style="minimal"] textarea,
      body[data-form-style="minimal"] input[type="email"],
      body[data-form-style="minimal"] input[type=password],
      body[data-form-style="minimal"] input[type=tel],
      body[data-form-style="minimal"] input[type=url],
      body[data-form-style="minimal"] input[type=search],
      body[data-form-style="minimal"] input[type=date],
      body[data-form-style="minimal"] input[type=number],
      body[data-form-style="minimal"] select { ' . $form_input_props . ' }';

    }

    if( ! empty($form_input_props_hover) ) {

      echo '.container-wrap input[type=text]:hover,
      .container-wrap input[type=email]:hover,
      .container-wrap input[type=password]:hover,
      .container-wrap input[type=tel]:hover,
      .container-wrap input[type=url]:hover,
      .container-wrap input[type=search]:hover,
      .container-wrap input[type=date]:hover,
      .container-wrap input[type=number]:hover,
      .container-wrap textarea:hover,
      .container-wrap select:hover,
      body > #review_form_wrapper.modal input[type=text]:hover,
      body > #review_form_wrapper.modal textarea:hover,
      body > #review_form_wrapper.modal select:hover,
      body > #review_form_wrapper.modal input[type=email]:hover,
      .select2-container--default .select2-selection--single:hover,
      .woocommerce input#coupon_code:hover,
      .material.woocommerce-page[data-form-style="default"] input#coupon_code:hover,
      body[data-form-style="minimal"] input[type="text"]:hover,
      body[data-form-style="minimal"] textarea:hover,
      body[data-form-style="minimal"] input[type="email"]:hover,
      body[data-form-style="minimal"] input[type=password]:hover,
      body[data-form-style="minimal"] input[type=tel]:hover,
      body[data-form-style="minimal"] input[type=url]:hover,
      body[data-form-style="minimal"] input[type=search]:hover,
      body[data-form-style="minimal"] input[type=date]:hover,
      body[data-form-style="minimal"] input[type=number]:hover,
      body[data-form-style="minimal"] select:hover { ' . $form_input_props_hover . ' }';

    }

    if( ! empty($form_input_props_focus) ) {

      echo '.container-wrap input[type=text]:focus,
      .container-wrap input[type=email]:focus,
      .container-wrap input[type=password]:focus,
      .container-wrap input[type=tel]:focus,
      .container-wrap input[type=url]:focus,
      .container-wrap input[type=search]:focus,
      .container-wrap input[type=date]:focus,
      .container-wrap input[type=number]:focus,
      .container-wrap textarea:focus,
      .container-wrap select:focus,
      body > #review_form_wrapper.modal input[type=text]:focus,
      body > #review_form_wrapper.modal textarea:focus,
      body > #review_form_wrapper.modal select:focus,
      body > #review_form_wrapper.modal input[type=email]:focus,
      .select2-container--default .select2-selection--single:focus,
      .woocommerce input#coupon_code:focus,
      .material.woocommerce-page[data-form-style="default"] input#coupon_code:focus
       { ' . $form_input_props_focus . ' }';

    }

    /*-------------------------------------------------------------------------*/
    /* 7. Blog
    /*-------------------------------------------------------------------------*/

    /*-------------------------------------------------------------------------*/
    /* 7.1. Blog Single Width
    /*-------------------------------------------------------------------------*/
    $blog_hide_sidebar = ( isset( $nectar_options['blog_hide_sidebar'] ) && ! empty($nectar_options['blog_hide_sidebar']) ) ? $nectar_options['blog_hide_sidebar'] : false;

    if( '1' === $blog_hide_sidebar && isset( $nectar_options['blog_width'] ) && ! empty($nectar_options['blog_width']) ) {

      $blog_width = ( 'default' === $nectar_options['blog_width'] ) ? 'var(--container-width, 1000px)' : $nectar_options['blog_width'];

      echo '
      @media only screen and (min-width: 1025px) {
        body.single-post .post-area {
          --wp--style--global--content-size: calc(' . esc_attr($blog_width) . ' + (var(--wp--style--root--padding-right) * 2));
        }
        body.single-post .featured-media-under-header,
        body.single-post #nectar-content-wrap.container-wrap.no-sidebar .post-area,
        body.single-post #nectar-content-wrap.container-wrap.no-sidebar .comment-list >li,
        body.single-post #nectar-content-wrap.container-wrap.no-sidebar .comment-wrap h3#comments,
        body.single-post #nectar-content-wrap .comment-wrap #respond,
        body.single-post #page-header-wrap #page-header-bg[data-post-hs="default_minimal"] h1,
        body.single-post #nectar-content-wrap .heading-title[data-header-style="default_minimal"] .entry-title,
        .single-post .featured-media-under-header__content,
        [data-style="parallax_next_only"].blog_next_prev_buttons .inner,
        .nectar_template_single__post > .container {
          max-width: ' . esc_attr($blog_width) . ';
          margin-left: auto;
          margin-right: auto;
        }';

        $blog_header_type = ( ! empty( $nectar_options['blog_header_type'] ) ) ? $nectar_options['blog_header_type'] : 'default';

        if ( $blog_header_type === 'image_under' ) {
          echo '.blog_next_prev_buttons:not(.full-width-content) {
            max-width: ' . esc_attr($blog_width) . ';
            margin-left: auto;
            margin-right: auto;
          }
          .blog_next_prev_buttons:not(.full-width-content) .col {
            padding: 10%;
          }';

        }

      echo '}';

    } // custom width set.

    /*-------------------------------------------------------------------------*/
    /* 7.2. Blog Archives
    /*-------------------------------------------------------------------------*/
    if( (is_archive() || is_author() || is_category() || is_home() || is_tag()) && 'post' == get_post_type() ) {

        $blog_type = (isset($nectar_options['blog_type']) && ! empty($nectar_options['blog_type'])) ? $nectar_options['blog_type'] : 'masonry-blog-fullwidth';

        if( $blog_type === 'masonry-blog-full-screen-width' ) {

            $blog_masonry_type = (! empty($nectar_options['blog_masonry_type'])) ? $nectar_options['blog_masonry_type'] : 'auto_meta_overlaid_spaced';

            if( $theme_skin === 'material' ) {
                echo 'body[data-header-resize] .container-wrap {
  					padding-top: 0;
  				}
  				body[data-bg-header="false"].archive .container-wrap {
  					padding-top: 40px;
  				}
  				body[data-bg-header="true"].archive .container-wrap {
  					padding-top: 40px!important;
  				}';

                if( $blog_masonry_type === 'auto_meta_overlaid_spaced' || $blog_masonry_type === 'meta_overlaid' ) {
                    echo 'body[data-bg-header="true"].archive .container-wrap {
  						padding-top: 0!important;
  					}';
                }

            } else {

                echo 'body[data-bg-header="false"].archive .container-wrap {
  					padding-top: 40px;
  					margin-top: 0;
  				}
  				body[data-bg-header="true"].archive .container-wrap {
  						padding-top: 40px!important;
  				}';

                if( $blog_masonry_type === 'auto_meta_overlaid_spaced' || $blog_masonry_type === 'meta_overlaid' ) {
                    echo 'body[data-bg-header="true"].archive .container-wrap {
  						padding-top: 0!important;
  					}';
                }

            }

        } // using full width masonry blog.

    } // if archive.

    $blog_archive_text_align = (isset($nectar_options['blog_archive_text_alignment'])) ? $nectar_options['blog_archive_text_alignment'] : 'default';

    if( 'center' === $blog_archive_text_align ) {
      echo '.blog-archive-header .col.section-title,
      .blog-archive-header .inner-wrap {
        text-align: center;
      }
      @media only screen and (min-width: 1025px) {
        .blog-archive-header .col.section-title p,
        .blog-archive-header .inner-wrap p{
          padding: 0 22%;
        }
      }
      @media only screen and (max-width: 1024px) and (min-width: 691px) {
        .blog-archive-header .col.section-title p,
        .blog-archive-header .inner-wrap p{
          padding: 0 15%;
        }
      }';
    }
    else if( 'right' === $blog_archive_text_align ) {
      echo '.blog-archive-header .col.section-title,
      .blog-archive-header .inner-wrap {
        text-align: right;
      }';
    }

    /*-------------------------------------------------------------------------*/
    /* 7.3. Blog Comments
    /*-------------------------------------------------------------------------*/
    if( isset($nectar_options['blog_comment_author_style']) &&
        $nectar_options['blog_comment_author_style'] === 'author_badge' ) {

          echo '
          .comment-list .bypostauthor .comment-author cite.fn {
            --nectar-author-text: "' . esc_html__('Author', 'nectar-blocks-theme') . '";
          }
          .comment-list .bypostauthor .comment-author cite.fn:after {
            display: inline-block;
            padding: 4px 8px;
            margin-left: 5px;
            line-height: 1;
            font-size: 11px;
            color: #fff;
            border-radius: 15px;
            content: var(--nectar-author-text);
            background-color: var(--nectar-accent-color);
          }
          .comment-list .bypostauthor .comment-author cite.fn,
          .comment-list .bypostauthor .comment-author cite.fn a {
            display: flex;
            align-items: center;
          }

          .comment-list .says {
            display: none;
          }
          body .comment-list .comment-meta {
            line-height: 20px;
            font-size: 12px;
          }

          #nectar-content-wrap .bypostauthor > .comment-body > .comment-author > img.avatar {
            border: none;
          }
          ';

    }

    /*-------------------------------------------------------------------------*/
    /* 7.4. Blog Category Coloring
    /*-------------------------------------------------------------------------*/

    // Blog categories.
    $categories = get_categories();

    if(! empty($categories)){

      foreach($categories as $k => $v) {

        $t_id = esc_attr($v->term_id);
        $terms = get_option( "taxonomy_$t_id" );

        if( ! empty($terms['category_color']) ) {

          $button_text_color = (isset($terms['category_text_color']) && ! empty($terms['category_text_color']) ) ? esc_attr($terms['category_text_color']) : '#fff';

          echo '
          .featured-media-under-header__cat-wrap .meta-category .' . esc_attr($v->slug) . ':hover,
          .single .heading-title[data-header-style="default_minimal"] .meta-category .' . esc_attr($v->slug) . ':hover,
          .single .heading-title[data-header-style="default_minimal"] .meta-category .' . esc_attr($v->slug) . ':focus,
          #page-header-bg.fullscreen-header .inner-wrap >a.' . esc_attr($v->slug) . ',
          #page-header-bg[data-post-hs="default_minimal"] .inner-wrap > .' . esc_attr($v->slug) . ':hover,
          #page-header-bg[data-post-hs="default_minimal"] .inner-wrap > .' . esc_attr($v->slug) . ':focus,
          .blog-recent.related-posts[data-style="classic_enhanced"] .meta-category .' . esc_attr($v->slug) . ':hover,
          .blog-recent.related-posts[data-style="classic_enhanced"] .meta-category .' . esc_attr($v->slug) . ':focus,
          .related-posts[data-style="material"] .meta-category .' . esc_attr($v->slug) . ':before,
          .post-area.featured_img_left .meta-category .' . esc_attr($v->slug) . ':before,
          .post-area.featured_img_left .quote.category-' . esc_attr($v->slug) . ' .quote-inner:before {
            background-color: ' . esc_attr($terms['category_color']) . '!important;
            color: ' . esc_attr($button_text_color) . ';
          }

          [data-style="list_featured_first_row"] .meta-category .' . esc_attr($v->slug) . ',
          .post-area.featured_img_left .meta-category .' . esc_attr($v->slug) . ',
          .related-posts[data-style="material"] .meta-category .' . esc_attr($v->slug) . ' {
            color: ' . esc_attr($terms['category_color']) . '!important;
          }';
        }

      }
    }

    /*-------------------------------------------------------------------------*/
    /* 8. Page Transitions
    /*-------------------------------------------------------------------------*/

    $enable_page_transitions = ( isset($nectar_options['page-transitions']) && ! empty($nectar_options['page-transitions']) ) ? esc_attr($nectar_options['page-transitions']) : false;
    $enable_page_transitions_mobile = ( isset($nectar_options['page-transitions-mobile']) && ! empty($nectar_options['page-transitions-mobile']) ) ? esc_attr($nectar_options['page-transitions-mobile']) : false;
    if( $enable_page_transitions ) {

      $transition_effect = ( isset($nectar_options['page-transitions-effect']) && ! empty($nectar_options['page-transitions-effect']) ) ? esc_attr($nectar_options['page-transitions-effect']) : 'fade';
      $transition_bg_color = ( isset($nectar_options['page-transitions-bg-color']) && ! empty($nectar_options['page-transitions-bg-color']) ) ? esc_attr($nectar_options['page-transitions-bg-color']) : 'var(--nectar-overall-bg-color, #ffffff)';

      $media_query_min = '1025px';
      if( $enable_page_transitions_mobile ) {
        $media_query_min = '1px';
      }
      // Cross Fade base.
      echo '
      @media only screen and (min-width: ' . $media_query_min . ') {

        @view-transition {
          navigation: auto;
        }

        html body,
        html body.compensate-for-scrollbar {
          overflow: visible;
          touch-action: pan-y;
        }

        ';

      // Fade
      if ( 'fade' === $transition_effect ) {
        echo '
        html {
            background-color: ' . esc_attr($transition_bg_color) . ';
          }
          ::view-transition-old(*),
          ::view-transition-new(*) {
              mix-blend-mode: normal;
              backface-visibility: hidden;
          }
         @keyframes nectarblocks-view-transition-start {
              0% {
                opacity: 0;
              }
              100% {
                opacity: 1;
              }
          }
          @keyframes nectarblocks-view-transition-end {
              0% {
                opacity: 1;
              }
              100% {
                opacity: 0;
              }
          }
          ::view-transition-old(root) {
            animation: nectarblocks-view-transition-end 0.35s cubic-bezier(0.5, 0, 0.35, 1.0);
            animation-delay: 0s;
            animation-fill-mode: both;
        }

        ::view-transition-new(root) {
            animation: nectarblocks-view-transition-start 0.65s cubic-bezier(0.5, 0, 0.35, 1.0);
            animation-delay: 0.35s;
            animation-fill-mode: both;
            z-index: 1000;
            position: relative;
        }
        ';
      }

      else if ( 'gradient-fade' === $transition_effect ) {
        echo '
            ::view-transition-old(*),
            ::view-transition-new(*) {
                mix-blend-mode: normal;
                backface-visibility: hidden;
            }

            @property --nectarblocks-view-transition-gradient-wipe-progress {
                syntax: "<number>";
                initial-value: 0;
                inherits: false;
            }

            @keyframes nectarblocks-view-transition-start {
                0% {
                  opacity: 1;
                    transform: none;
                    --nectarblocks-view-transition-gradient-wipe-progress: 0;
                }

                100% {
                  opacity: 1;
                  transform: none;
                  --nectarblocks-view-transition-gradient-wipe-progress: 1;
                }
            }

            @keyframes nectarblocks-view-transition-end {
                0% {
                  opacity: 1;
                  transform: none;
                }

                100% {
                  opacity: 1;
                  transform: none;

                }
            }

            ::view-transition-old(root) {
              animation: nectarblocks-view-transition-end 1.2s cubic-bezier(0.45, 0, 0.35, 1.0);
              animation-delay: 0s;
              animation-fill-mode: both;
            }

            ::view-transition-new(root) {
              animation: nectarblocks-view-transition-start 1.2s cubic-bezier(0.45, 0, 0.35, 1.0);
              animation-fill-mode: both;
              mask-image: linear-gradient(
                270deg,
                #000000 calc( -70% + calc(170% * var(--nectarblocks-view-transition-gradient-wipe-progress))),
                transparent calc(170% * var(--nectarblocks-view-transition-gradient-wipe-progress))
              );
              -webkit-mask-image: linear-gradient(
                270deg,
                #000000 calc( -70% + calc(170% * var(--nectarblocks-view-transition-gradient-wipe-progress))),
                transparent calc(170% * var(--nectarblocks-view-transition-gradient-wipe-progress))
              );
            }
        ';
      }

      else if ( 'push-reveal' === $transition_effect ) {
        // Push Reveal.
        echo '

          html {
            background-color: ' . esc_attr($transition_bg_color) . ';
          }
          ::view-transition-old(*),
          ::view-transition-new(*) {
              mix-blend-mode: normal;
              backface-visibility: hidden;
          }

          @keyframes nectarblocks-view-transition-start {
              0% {
                clip-path: inset(100% 0% 0% 0%);
                transform: translateY(10%);
              }
              50%,100% {
                opacity: 1;
                animation-timing-function: ease-out;
              }
              100% {
                transform: translateY(0%);
                clip-path: inset(0% 0% 0% 0%);
              }
          }

          @keyframes nectarblocks-view-transition-end {
              0% {
                opacity: 1;
                transform: translateY(0%) scale(1);
              }
              100% {
                opacity: 0.4;
                transform: translateY(-10%) scale(0.93);
              }
          }


        ::view-transition-old(root) {
            animation: nectarblocks-view-transition-end 1.2s cubic-bezier(0.6, 0, 0.1, 1.0);
            animation-delay: 0s;
            animation-fill-mode: both;
        }

        ::view-transition-new(root) {
            animation: nectarblocks-view-transition-start 1.2s cubic-bezier(0.6, 0, 0.1, 1.0);
            animation-delay: 0s;
            animation-fill-mode: both;
            z-index: 1000;
            position: relative;
        }';

      } // end reveal from bottom

      echo '}'; // closing media query

    }

    /*-------------------------------------------------------------------------*/
    /* 9. Page Header
    /*-------------------------------------------------------------------------*/

    // Blog header overlay

    $blog_header_type = ( isset($nectar_options['blog_header_type']) ) ? esc_attr($nectar_options['blog_header_type']) : '';

    if( isset($nectar_options['std_blog_header_overlay_color']) &&
       ! empty($nectar_options['std_blog_header_overlay_color']) &&
        isset($nectar_options['std_blog_header_overlay_opacity']) && in_array($blog_header_type, ['default', 'fullscreen']) ) {

          $std_blog_header_overlay_color = esc_attr($nectar_options['std_blog_header_overlay_color']);
          $std_blog_header_overlay_opacity = esc_attr($nectar_options['std_blog_header_overlay_opacity']);

          echo '.single-post .page-header-bg-image-wrap .page-header-bg-image:after {
            display: block;
            content: " ";
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            background-color: ' . $std_blog_header_overlay_color . ';
            opacity: ' . $std_blog_header_overlay_opacity . ';
          }';
    }

    // Animated in effect.
    if( isset($nectar_options['header-animate-in-effect']) ) {

      $animate_in_effect = $nectar_options['header-animate-in-effect'];

      if( 'slide-down' === $animate_in_effect ) {
        echo '#page-header-wrap[data-animate-in-effect="slide-down"],
        #page-header-wrap[data-animate-in-effect="slide-down"] #page-header-bg{
          transition:height 0.85s cubic-bezier(0.725,0,0,0.995);
          -webkit-transition:height 0.85s cubic-bezier(0.725,0,0,0.995);
        }';
      }
      else if( 'zoom-out' === $animate_in_effect ) {

        echo '
        @media only screen and (min-width: 1025px) {
          #page-header-wrap #page-header-bg[data-animate-in-effect="zoom-out"] .page-header-bg-image-wrap,
          #page-header-wrap #page-header-bg[data-animate-in-effect="zoom-out"] .nectar-video-wrap,
          .top-level .nectar-slider-wrap[data-animate-in-effect="zoom-out"]:not([data-bg-animation="ken_burns"]) .slide-bg-wrap,
          .nectar-recent-posts-slider[data-animate-in-effect="zoom-out"] .nectar-recent-posts-slider-inner:not(.loaded) .nectar-recent-post-bg {
            -webkit-transform:scale(1.11) translateZ(0);
            transform:scale(1.11) translateZ(0);
            -webkit-transition:0.95s 0s cubic-bezier(0.3,0.58,0.42,0.9);
            transition:0.95s 0s cubic-bezier(0.3,0.58,0.42,0.9)
          }
        }


        #page-header-wrap #page-header-bg[data-animate-in-effect="zoom-out"].loaded .page-header-bg-image-wrap,
        #page-header-wrap #page-header-bg[data-animate-in-effect="zoom-out"].loaded .nectar-video-wrap,
        .top-level .nectar-slider-wrap[data-animate-in-effect="zoom-out"]:not([data-bg-animation="ken_burns"]).loaded .slide-bg-wrap,
        .nectar-recent-posts-slider[data-animate-in-effect="zoom-out"].loaded .nectar-recent-post-bg {
          -webkit-transform:scale(1) translateZ(0);
          transform:scale(1) translateZ(0)
        }

        ';

      }
      else if( 'fade-in' === $animate_in_effect ) {
        echo '
        #page-header-bg[data-animate-in-effect="fade-in"] .scroll-down-wrap {
        	opacity: 0;
        	transition: opacity 1s ease 0.6s;
        }

        #page-header-bg[data-animate-in-effect="fade-in"].loaded .scroll-down-wrap {
        	opacity: 1;
        	transition: opacity 1s ease 0.4s;
        }

        #page-header-bg[data-animate-in-effect="fade-in"] .page-header-bg-image-wrap,
        #page-header-wrap #page-header-bg[data-animate-in-effect="fade-in"] .nectar-video-wrap {
        	opacity: 0;
        	-webkit-animation: pageHeaderFadeIn 1.5s ease forwards;
        	animation: pageHeaderFadeIn 1.5s ease forwards;
        	animation-delay: 0.5s;
        }

        #page-header-bg[data-animate-in-effect="fade-in"] .container {
        	opacity: 0;
        	-webkit-animation: pageHeaderFadeInText 1.5s ease forwards;
        	animation: pageHeaderFadeInText 1.5s ease forwards;
        	animation-delay: 0.1s;
        }


        @keyframes pageHeaderFadeIn {
          0%{
            opacity: 0;
          }
          100%{
            opacity: 1;
          }
        }

        @keyframes pageHeaderFadeInText {
          0%{
        		-webkit-transform:translateY(40px);
        		transform:translateY(40px);
            opacity: 0;
          }
          100%{
            -webkit-transform:translateY(0);
        		transform:translateY(0);
            opacity: 1;
          }
        }';
      }

    }

    /*-------------------------------------------------------------------------*/
    /* 10. Button Roundness
    /*-------------------------------------------------------------------------*/
    if( isset($nectar_options['button-styling']) ) {

      if ( 'default' === $nectar_options['button-styling'] ) {
        echo ':root {
          --nectar-border-radius: 0px;
        }';
      }

      if( 'slightly_rounded' === $nectar_options['button-styling'] ||
          'slightly_rounded_shadow' === $nectar_options['button-styling'] ) {

          $button_roundness = ( isset($nectar_options['button-styling-roundness']) && ! empty($nectar_options['button-styling-roundness']) ) ? intval( $nectar_options['button-styling-roundness'] ) : 4;

          $ascend_button_selector = '';

      echo '
      .nectar-inherit-border-radius,
      body[data-button-style*="slightly_rounded"] .nectar-cta:not([data-style="material"]) .link_wrap,
      body[data-button-style*="slightly_rounded"] .slide-out-widget-area-toggle[data-custom-color="true"] a:before,
      body[data-button-style*="slightly_rounded"] .flex-direction-nav a,
      body[data-button-style*="slightly_rounded"] #pagination span,
      body[data-button-style*="slightly_rounded"] #pagination a,
      body[data-button-style*="slightly_rounded"] #pagination .next.inactive,
      body[data-button-style*="slightly_rounded"] #pagination .prev.inactive,
      body[data-button-style*="slightly_rounded"].woocommerce nav.woocommerce-pagination ul li a,
      body[data-button-style*="slightly_rounded"].woocommerce .container-wrap nav.woocommerce-pagination ul li span,
      body[data-button-style*="slightly_rounded"] .container-wrap nav.woocommerce-pagination ul li span,
      body[data-button-style*="slightly_rounded"].woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
      body[data-button-style*="slightly_rounded"] input[type=submit],
      body[data-button-style*="slightly_rounded"] button[type=submit],
      body[data-button-style*="slightly_rounded"] input[type="button"],
      body[data-button-style*="slightly_rounded"] button,
      body[data-button-style*="slightly_rounded"] #top nav > ul > li[class*="button_solid_color"] > a:before,
      body[data-button-style*="slightly_rounded"] #top nav > ul > li[class*="button_bordered"] > a:before,
      body[data-button-style*="slightly_rounded"] #nectar-nav .widget_shopping_cart a.button,
      body[data-button-style*="slightly_rounded"] .comment-list .reply a,
      body[data-button-style*="slightly_rounded"].material #page-header-bg.fullscreen-header .inner-wrap >a,
      body[data-button-style*="slightly_rounded"].single .heading-title[data-header-style="default_minimal"] .meta-category a,
      body[data-button-style*="slightly_rounded"] #page-header-bg[data-post-hs="default_minimal"] .inner-wrap > a,
      body[data-button-style*="slightly_rounded"] .blog-recent[data-style*="classic_enhanced"] .meta-category a,
      body[data-button-style*="slightly_rounded"] .woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
      .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart a.button,
      li[class*="menu-item-btn-style"] > a:before,
      li[class*="menu-item-btn-style"] > a:after {
        border-radius: ' . intval($button_roundness) . 'px!important;
        -webkit-border-radius: ' . intval($button_roundness) . 'px!important;
        box-shadow: none;
        -webkit-transition: opacity .45s cubic-bezier(0.25, 1, 0.33, 1), transform .45s cubic-bezier(0.25, 1, 0.33, 1), border-color .45s cubic-bezier(0.25, 1, 0.33, 1), color .45s cubic-bezier(0.25, 1, 0.33, 1), background-color .45s cubic-bezier(0.25, 1, 0.33, 1), box-shadow .45s cubic-bezier(0.25, 1, 0.33, 1);
        transition: opacity .45s cubic-bezier(0.25, 1, 0.33, 1), transform .45s cubic-bezier(0.25, 1, 0.33, 1), border-color .45s cubic-bezier(0.25, 1, 0.33, 1), color .45s cubic-bezier(0.25, 1, 0.33, 1), background-color .45s cubic-bezier(0.25, 1, 0.33, 1), box-shadow .45s cubic-bezier(0.25, 1, 0.33, 1);
      }
      .nectar-shop-filters .nectar-shop-filter-trigger,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select,
      body[data-button-style*="slightly_rounded"] .widget_layered_nav_filters ul li a,
      .nectar-menu-label:before,
     .nectar-ext-menu-item__button,
     .nectar-post-grid .meta-category .style-button {
        border-radius: ' . intval($button_roundness) . 'px;
      }';

      if( 'material' === $theme_skin ) {
        echo '.material[data-button-style*="slightly_rounded"] .widget .tagcloud a:before,
        .material[data-button-style*="slightly_rounded"] #sidebar .widget .tagcloud a:before,
        .single[data-button-style*="slightly_rounded"] .post-area .content-inner > .post-tags a:before,
        .material[data-button-style*="slightly_rounded"] .widget .tagcloud a,
        .material[data-button-style*="slightly_rounded"] #sidebar .widget .tagcloud a,
        .single[data-button-style*="slightly_rounded"] .post-area .content-inner > .post-tags a,
        #slide-out-widget-area.fullscreen-inline-images .menuwrapper li.back >a {
          border-radius: ' . intval($button_roundness) . 'px!important;
        }';
      }

      echo ':root {
        --nectar-border-radius: ' . intval($button_roundness) . 'px;
      }';

    } // slightly rounded end.

    if( 'rounded' === $nectar_options['button-styling'] || 'rounded_shadow' === $nectar_options['button-styling'] ) {

      $ascend_button_selector = '';

      echo '
      .nectar-inherit-border-radius,
      body[data-button-style^="rounded"] .nectar-cta:not([data-style="material"]) .link_wrap,
      body[data-button-style^="rounded"] .portfolio-filters-inline .container ul li a,
      body[data-button-style^="rounded"] .slide-out-widget-area-toggle[data-custom-color="true"] a:before,
      body[data-button-style^="rounded"] #to-top,
      body[data-button-style^="rounded"] .flex-direction-nav a,
      body[data-button-style^="rounded"] #pagination span,
      body[data-button-style^="rounded"] #pagination a,
      body[data-button-style^="rounded"] #pagination .next.inactive,
      body[data-button-style^="rounded"] #pagination .prev.inactive,
      body[data-button-style^="rounded"].woocommerce nav.woocommerce-pagination ul li a,
      body[data-button-style^="rounded"].woocommerce .container-wrap nav.woocommerce-pagination ul li span,
      body[data-button-style^="rounded"] .container-wrap nav.woocommerce-pagination ul li span,
      body[data-button-style^="rounded"].woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
      body[data-button-style^="rounded"] input[type=submit],
      body[data-button-style^="rounded"] button[type=submit],
      body[data-button-style^="rounded"] input[type="button"],
      body[data-button-style^="rounded"] button,
      body[data-button-style^="rounded"] #top nav > ul > li[class*="button_solid_color"] > a:before,
      body[data-button-style^="rounded"] #top nav > ul > li[class*="button_bordered"] > a:before,
      body[data-button-style^="rounded"] .woocommerce.add_to_cart_inline a.button.add_to_cart_button,
      body[data-button-style^="rounded"] #nectar-nav .widget_shopping_cart a.button,
      .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart a.button,
      li[class*="menu-item-btn-style"] > a:before,
      li[class*="menu-item-btn-style"] > a:after {
        border-radius: 200px!important;
        box-shadow: none;
        transition: opacity .45s cubic-bezier(0.25, 1, 0.33, 1), transform .45s cubic-bezier(0.25, 1, 0.33, 1), border-color .45s cubic-bezier(0.25, 1, 0.33, 1), color .45s cubic-bezier(0.25, 1, 0.33, 1), background-color .45s cubic-bezier(0.25, 1, 0.33, 1), box-shadow .45s cubic-bezier(0.25, 1, 0.33, 1);
      }

      .nectar-shop-filters .nectar-shop-filter-trigger,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select,
      .nectar-menu-label:before,
      #slide-out-widget-area.fullscreen-inline-images .menuwrapper li.back >a,
      .nectar-ext-menu-item__button,
      .nectar-post-grid .meta-category .style-button {
        border-radius: 200px;
      }';

      echo ':root {
        --nectar-border-radius: 200px;
      }';

    } // rounded end.

    if( 'rounded_reveal' === $nectar_options['button-styling'] ) {
      echo ':root {
        --nectar-border-radius: 200px;
      }

      .nectar-inherit-border-radius,
      .nectar-cta:not([data-style="material"]) .link_wrap,
      .slide-out-widget-area-toggle[data-custom-color="true"] a:before,
      #to-top,
      #pagination span,
      #pagination a,
      #pagination .next.inactive,
      #pagination .prev.inactive,
      body[data-button-style^="rounded"].woocommerce nav.woocommerce-pagination ul li a,
      body[data-button-style^="rounded"].woocommerce .container-wrap nav.woocommerce-pagination ul li span,
      .container-wrap nav.woocommerce-pagination ul li span,
      body[data-button-style^="rounded"].woocommerce-cart .wc-proceed-to-checkout a.checkout-button,
      input[type=submit],
      button[type=submit],
      input[type="button"],
      button,
      #top nav > ul > li[class*="button_solid_color"] > a:before,
      #top nav > ul > li[class*="button_bordered"] > a:before,
      .woocommerce.add_to_cart_inline a.button.add_to_cart_button,
      #nectar-nav .widget_shopping_cart a.button,
      .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart a.button,
      li[class*="menu-item-btn-style"] > a:before,
      li[class*="menu-item-btn-style"] > a:after,
      .nectar-shop-filters .nectar-shop-filter-trigger,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select,
      .widget_layered_nav_filters ul li a,
      .nectar-menu-label:before,
      .nectar-ext-menu-item__button,
      .nectar-post-grid .meta-category .style-button {
        border-radius: var(--nectar-border-radius)!important;
      }';
    }

    if( 'rounded_shadow' === $nectar_options['button-styling'] ) {
      echo 'body[data-button-style*="rounded_shadow"] .wp-block-button > .wp-block-button__link:hover,
      body[data-button-style*="rounded_shadow"] input[type="submit"]:hover,
      body[data-button-style*="rounded_shadow"] input[type="submit"]:focus,
      body[data-button-style*="rounded_shadow"].woocommerce-cart .wc-proceed-to-checkout a.checkout-button:hover,
      body[data-button-style*="rounded_shadow"] .woocommerce.add_to_cart_inline a.button.add_to_cart_button:hover,
      body[data-button-style*="rounded_shadow"] .container-wrap input[type="submit"]:hover,
      body[data-button-style="rounded_shadow"] .container-wrap button[type="submit"]:hover,
      body[data-button-style="rounded_shadow"] .woocommerce-cart .wc-proceed-to-checkout a.checkout-button:hover,
      .nectar-button:hover {
        box-shadow: 0 20px 38px rgba(0, 0, 0, 0.16);
        -ms-transform: translateY(-3px);
        transform: translateY(-3px);
        -webkit-transform: translateY(-3px);
      }';
    } // rounded shadow hover end.

    if( 'slightly_rounded_shadow' === $nectar_options['button-styling'] ) {
      echo 'body[data-button-style="slightly_rounded_shadow"] .wp-block-button > .wp-block-button__link:hover,
      body[data-button-style="slightly_rounded_shadow"] input[type="submit"]:hover,
      body[data-button-style="slightly_rounded_shadow"] input[type="submit"]:focus,
      body[data-button-style*="slightly_rounded"].single .heading-title[data-header-style="default_minimal"] .meta-category a:hover,
      body[data-button-style*="slightly_rounded"] #page-header-bg[data-post-hs="default_minimal"] .inner-wrap > a:hover,
      body[data-button-style="slightly_rounded_shadow"].woocommerce-cart .wc-proceed-to-checkout a.checkout-button:hover,
      body[data-button-style="slightly_rounded_shadow"].woocommerce-page .woocommerce p.return-to-shop a.wc-backward:hover,
      body[data-button-style="slightly_rounded_shadow"] .woocommerce.add_to_cart_inline a.button.add_to_cart_button:hover,
      body[data-button-style="slightly_rounded_shadow"] .container-wrap input[type="submit"]:hover,
      body[data-button-style="slightly_rounded_shadow"] .container-wrap button[type="submit"]:hover,
      body[data-button-style="slightly_rounded_shadow"] .woocommerce-cart .wc-proceed-to-checkout a.checkout-button:hover,
      .nectar-button:hover {
        box-shadow: 0 20px 38px rgba(0, 0, 0, 0.16)!important;
        -ms-transform: translateY(-3px);
        transform: translateY(-3px);
        -webkit-transform: translateY(-3px);
      }';
    } // slightly rounded shadow hover end.

    if( 'default' === $nectar_options['button-styling'] ) {
      echo '.nectar-cta .link_wrap {
        transition: border-color .45s cubic-bezier(0.25, 1, 0.33, 1), color .45s cubic-bezier(0.25, 1, 0.33, 1), background-color .45s cubic-bezier(0.25, 1, 0.33, 1);
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 11. Call to action
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['cta-text']) && ! empty($nectar_options['cta-text']) ) {
    echo '#call-to-action{
      font-weight:300;
      position:relative;
      z-index:10;
      background-color:#eeedec;
      text-align:center;
      vertical-align:bottom;
      font-size:22px
    }
    #boxed #call-to-action .container,
    #call-to-action .container,
    #footer-outer[data-full-width="1"] #call-to-action .container{
      padding:42px 0 36px 0
    }
    #call-to-action .container span{
      display:inline-block
    }
    #call-to-action .container a{
      background:none repeat scroll 0 0 #000;
      position:relative;
      top:-3px;
      color:#FFF;
      margin-bottom:0;
      font-size:12px;
      box-shadow:0 -3px rgba(0,0,0,0.1) inset;
      -webkit-box-shadow:0 -3px rgba(0,0,0,0.1) inset;
      text-transform:uppercase;
      font-weight:700;
      letter-spacing:2px;
      margin-left:20px;
      line-height:24px;
      display:inline-block;
      border-radius:5px 5px 5px 5px;
      padding:16px 21px
    }
    #call-to-action .triangle{
      border-left:10px solid transparent;
      border-right:10px solid transparent;
      border-top:10px solid #f8f8f8;
      height:10px;
      width:10px;
      left:440px;
      margin:-42px auto 33px auto
    }
    #call-to-action a{
      color:#45484a;
      opacity:1;
      transition:opacity 0.3s linear;
      -webkit-transition:opacity 0.3s linear;
    }
    #call-to-action a:hover{
      opacity:0.75
    }
    #call-to-action span{
      color:#4b4f52;
      font-weight:600
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 12. Body Border
  /*-------------------------------------------------------------------------*/

  if( isset($nectar_options['body-border']) && ! empty($nectar_options['body-border']) && '1' === $nectar_options['body-border'] ) {
    echo '
    .body-border-bottom{
      height:20px;
      width:100%;
      bottom:0;
      left:0;
      position:fixed;
      z-index:10000;
      background-color:#fff;
    }

    .body-border-top{
      height:20px;
      width:100%;
      top:0;
      left:0;
      z-index:10000;
      position:fixed;
      background-color:#fff;
    }

    .admin-bar .body-border-top{
      top:32px
    }

    .body-border-right{
      height:100%;
      width:20px;
      top:0;
      right:0;
      z-index:10000;
      position:fixed;
      background-color:#fff;
    }

    .body-border-left{
      height:100%;
      width:20px;
      top:0;
      left:0;
      z-index:10000;
      position:fixed;
      background-color:#fff;
    }

    @media only screen and (max-width: 1024px) {
      .body-border-right,
      .body-border-left,
      .body-border-top,
      .body-border-bottom {
        display: none;
      }
    }
    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 13. Mobile Animations
  /*-------------------------------------------------------------------------*/
  $mobile_animations = false;
  if( isset($nectar_options['column_animation_mobile']) &&
      ! empty($nectar_options['column_animation_mobile']) &&
      'enable' === $nectar_options['column_animation_mobile']) {
    $mobile_animations = true;
  }

  $mobile_remove_parallax = false;

  /*-------------------------------------------------------------------------*/
  /* 14.1. Core Footer
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['enable-main-footer-area']) &&
    ! empty($nectar_options['enable-main-footer-area']) &&
    '1' === $nectar_options['enable-main-footer-area'] ) {

      echo '
      @media only screen and (min-width: 1025px) {
        #footer-outer .container > .row {
          display: flex;
          gap: 50px;
        }
        #footer-outer .container > .row > .col {
          margin-right: 0;
        }
      }

      #footer-outer img {
        margin-bottom: 0;
      }

      #footer-outer #footer-widgets .col .tagcloud a:hover,
      #footer-outer .nectar-button:hover {
        color:#fff!important
      }
      #footer-outer {
        position:relative;
        z-index:10;
      }
      #footer-outer .row{
        padding:55px 0;
        margin-bottom:0
      }
      #footer-outer #footer-widgets[data-has-widgets="false"] .row {
        padding: 0;
      }
      #footer-outer .widget h4 {
        color:#777;
        font-size:14px;
        font-weight:600;
        margin-bottom:20px
      }
      #footer-outer .widget h3,
      #footer-outer .widget h5,
      #footer-outer .widget h6 {
        color: inherit;
      }
      #footer-outer .widget {
        margin-bottom:30px
      }
      #footer-outer .col > .widget:last-child {
        margin-bottom: 0;
      }
      #footer-outer .widget.widget_categories ul ul,
      #footer-outer .widget.widget_pages ul ul,
      #footer-outer .widget.widget_nav_menu ul ul{
        margin:0!important;
        padding:0 0 0 20px
      }

      #footer-outer #footer-widgets .widget.widget_pages li,
      #footer-outer #footer-widgets .widget.widget_nav_menu li{
        border-bottom:0;
        padding:0!important
      }

      #footer-outer .widget.widget_pages li a,
      #footer-outer .widget.widget_nav_menu li a{
        padding:8px 0;
        display:block;
      }
      #footer-outer .widget_pages li a,
      #footer-outer .widget_nav_menu li a {
        border-bottom:1px solid #444
      }
      #nectar-content-wrap #footer-outer #footer-widgets .widget.widget_categories li a,
      #nectar-content-wrap #footer-outer #footer-widgets .widget.widget_archive li a {
        display: inline-block;
        border-bottom: 0;
      }

      #footer-outer .widget.widget_categories >ul >li:first-child >a,
      #footer-outer .widget.widget_pages >ul >li:first-child >a,
      #footer-outer .widget.widget_nav_menu >ul >li:first-child >a {
        padding-top:0
      }
      #footer-outer .span_3 .widget:last-child{
        margin-bottom:0
      }
      #footer-outer a{
        color:#ccc
      }
      #footer-outer a:hover{
        color:#000
      }

      #footer-outer .widget ul li{
        margin-bottom:7px
      }

      #footer-outer[data-full-width="1"] .container{
        width:100%;
        padding:0 28px;
        max-width:none
      }

      #footer-outer .col {
        z-index: 10;
        min-height: 1px;
      }

      #footer-outer .col .widget_recent_entries span,
      #footer-outer .col .recent_posts_extra_widget .post-widget-text span{
        display:block;
        line-height:17px;
        color:#999;
        font-size:11px;
        margin-bottom:6px
      }

      #footer-outer #footer-widgets .col ul li{
        padding:8px 0;
        list-style:none;
        margin-bottom:0;
        border-bottom:1px solid #444
      }
      #footer-outer #footer-widgets .col ul li:last-child{
        margin-bottom:0
      }
      #footer-outer .widget.widget_nav_menu li a,
      #footer-outer #footer-widgets .col ul ul li:last-child{
        border-bottom:0!important
      }

      #footer-outer #footer-widgets .col p{
        padding-bottom:20px
      }
      #footer-outer #footer-widgets .col p:last-child{
        padding-bottom:0
      }

      #footer-outer #footer-widgets .col .widget_calendar table th{
        text-align:center
      }
      #footer-outer #footer-widgets .col .widget_calendar table tbody td{
        border:0;
        color:#666;
        padding:8px;
        font-size:14px
      }
      #footer-outer #footer-widgets .col .widget_calendar table{
        border-collapse:collapse
      }
      #footer-outer #footer-widgets .col .widget_calendar table tbody tr td:first-child{
        border-left:0
      }
      #footer-outer #footer-widgets .col .widget_calendar table tbody tr:nth-child(2n+1){
        background-color:rgba(0,0,0,0.1)
      }
      #footer-outer #footer-widgets .col .widget_calendar table th{
        border-bottom:0;
        padding-bottom:10px;
        font-weight:700;
        padding: 10px;
        color:#666
      }
      #footer-outer #footer-widgets .col .widget_calendar table tfoot tr{
        margin-top:20px
      }

      #footer-outer .widget_search .search-form input[type=submit],
      #footer-outer .newsletter-widget form input[type=submit]{
        padding:10px 11px 11px 10px
      }
      #footer-outer #footer-widgets .col .tagcloud a{
        background-color: rgba(0,0,0,0.1);
        color:#A0A0A0;
        cursor:pointer;
        display:inline-block;
        float:left;
        margin:3px 3px 0 0;
        padding:5px 7px;
        position:relative;
        font-size:8pt;
        text-transform:capitalize;
        transition:all 0.2s linear;
        border-radius:2px;
        line-height:22px;
      }
      #footer-outer #footer-widgets .col .widget_tag_cloud:after{
        display:block;
        height:0;
        clear:both;
        content:"";
        visibility:hidden
      }

      #footer-outer #footer-widgets .col .tagcloud a:hover {
        background-color:#000;
      }

      #footer-outer #footer-widgets .col .widget_recent_comments ul li {
        background:none repeat scroll 0 0 rgba(0,0,0,0.15);
        border:medium none;
        display:block;
        margin-bottom:18px;
        padding:15px;
        position:relative
      }

      #footer-outer #footer-widgets .col .widget.widget_recent_comments ul li {
        padding:15px!important;
      }
      #footer-outer #footer-widgets .col .widget_recent_comments ul li:last-child {
        margin-bottom:0
      }
      #footer-outer #footer-widgets .col input[type=text],
      #footer-outer #footer-widgets .col input[type=email]{
        padding:10px;
        width:100%
      }


      body[data-form-style="minimal"] #footer-outer #footer-widgets .col input[type=text]{
        color:#fff
      }
      body:not([data-form-style="minimal"]) #footer-outer #footer-widgets .col input[type=text]:focus,
      body:not([data-form-style="minimal"]) #footer-outer #footer-widgets .col input[type=email]:focus{
        background-color:#fff
      }
      #footer-outer #footer-widgets .col input[type=submit]{
        background-color:#000;
        opacity:0.9;
        transition:opacity 0.2s linear 0s;
        -webkit-transition:opacity 0.2s linear 0s;
      }
      #footer-outer #footer-widgets .col input[type=submit]:hover{
        opacity:1
      }
      #footer-outer #footer-widgets .col .search-form form,
      #footer-outer #footer-widgets .col .search-form label,
      #footer-outer #footer-widgets .col .newsletter-widget form,
      #footer-outer #footer-widgets .col .search-form{
        line-height:12px
      }
      #footer-outer .recent_projects_widget img{
        background-color:#444;
        border-color:#4d4d4d
      }
      #footer-outer .recent_projects_widget a:hover img{
        border-color:#616161;
        background-color:#616161
      }

      #footer-outer #footer-widgets .col ul li:first-child >a,
      #footer-outer #footer-widgets .col ul li:first-child {
        padding-top:0!important
      }


      #footer-outer #footer-widgets .rsswidget img{
        margin-bottom:-2px;
        margin-right:2px
      }
      #footer-outer .recent_projects_widget img {
        margin-bottom:0;
      }';

      echo '@media only screen and (min-width : 691px) and (max-width: 1024px) {
        #footer-outer .one-fourths.span_3,
        #footer-outer .one-fourths.vc_span3,
        #footer-outer .one-fourths.vc_col-sm-3:not([class*="vc_col-xs-"]) {
          width: 48%!important;
          margin-bottom: 2%;
          margin-right: 15px;
          margin-left: 0!important;
          padding: 15px;
          float: left;
        }

        #footer-widgets .container .col {
          margin-left: 15px;
          width: 48%;
        }

        #footer-widgets .one-fourths .span_3:nth-child(2n+1) {
          margin-left: 0;
        }

        #footer-widgets .container .col.span_6,
        #footer-widgets .container .col.span_4 {
          margin-left: 0;
          margin-right: 15px;
          padding: 15px;
          margin-bottom: 0;
        }

        #footer-widgets .container .col.span_4 {
          margin-bottom: 40px;
        }

        #footer-widgets .container .row > div:last-child,
        #footer-widgets .container .row > div.col_last {
          margin-right: 0;
        }

      }

      @media only screen and (max-width : 767px) {

        #nectar-content-wrap #footer-widgets .container .col:nth-child(3) {
          margin-bottom: 40px;
        }

        #footer-outer #flickr img, #sidebar #flickr img {
          width: 95px;
        }

      }

      @media only screen and (min-width : 1px) and (max-width: 1024px) {

        #footer-widgets .container .col {
          margin-bottom: 40px;
        }


      }';

      if( isset($nectar_options['footer-link-hover']) && 'underline' === $nectar_options['footer-link-hover'] ) {
        echo '
        [data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
        [data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button) {
          background-repeat: no-repeat;
          background-size: 0% 2px;
          background-position: left bottom;
          opacity: 1;
          background-image: linear-gradient(to right, #000000 0%, #000000 100%);
          transition: background-size 0.55s cubic-bezier(.2,.75,.5,1), color 0.5s ease!important;
          text-decoration: none;
        }
        #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button) {
          transition: background-size 0.55s cubic-bezier(.2,.75,.5,1), color 0.5s ease;
        }

        #nectar-content-wrap #footer-outer[data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button),
        #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button) {
          display: inline;
          opacity: 1;
        }

        [data-link-hover="underline"] #footer-widgets ul:not([class*="nectar_blog_posts"]) li > a:not(.tag-cloud-link):not(.nectar-button):hover {
          background-size: 100% 2px;
          opacity: 1;
        }
        #footer-outer[data-link-hover="underline"] #footer-widgets .textwidget a:not(.nectar-button):hover {
          opacity: 1;
        }';
      }

    }

    $disable_footer_copyright = 'false';

    if ( isset( $nectar_options['disable-copyright-footer-area'] ) &&
        ! empty( $nectar_options['disable-copyright-footer-area'] ) &&
        $nectar_options['disable-copyright-footer-area'] === '1' ) {
            $disable_footer_copyright = 'true';
    }

    if( 'false' == $disable_footer_copyright ) {

      /* Copyright */
      echo '#footer-outer #copyright{
        padding: 30px 0;
        color: rgba(0,0,0,0.7);
      }
      #footer-outer #copyright > .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 2rem;
        flex-wrap: wrap;
      }
      #footer-outer #copyright > .container > .col {
        width: auto;
        float: none;
        margin: 0;
      }
      #footer-outer #copyright li{
        float:left;
        margin-left:20px
      }

      #footer-outer #copyright .container div:last-child{
        margin-bottom:0
      }


      #footer-outer #copyright p{
        line-height:22px;
        margin: 0;
      }

      #footer-outer[data-cols="1"] #copyright {
        padding: 45px 0;
      }


      #footer-outer #copyright .widget_products img {
        display: none;
      }

     ';

      echo '@media only screen and (min-width : 691px) and (max-width: 1024px) {

        #footer-outer #copyright .col {
          width: 49%;
          margin-bottom: 0;
        }
      }

';

    }

  /*-------------------------------------------------------------------------*/
  /* 14.3. Footer Layouts
  /*-------------------------------------------------------------------------*/

    $footer_columns = ( isset($nectar_options['footer_columns']) && ! empty( $nectar_options['footer_columns'] ) ) ? $nectar_options['footer_columns'] : '4';

    if( '5' === $footer_columns ) {
      echo '
      @media only screen and (min-width:1025px) {
        #footer-widgets[data-cols="5"] .container .row >div{
          width:19.5%
        }
        #footer-widgets[data-cols="5"] .container .row >div:first-child{
          width:35%
        }
      }';
    } else if( '1' == $footer_columns ) {

      echo '#footer-outer[data-cols="1"] #copyright .social li a {
        height: 50px;
      }
      #footer-outer[data-cols="1"] .col {
        text-align: center;
      }
      #footer-outer[data-cols="1"] #footer-widgets .container .col {
        width: 100%;
      }
      #footer-outer[data-cols="1"] #footer-widgets .span_12 > div:last-child {
        margin-bottom: 0;
      }

      #footer-outer[data-cols="1"] #copyright > .container {
        flex-direction: column;
        align-items: center;
      }

      .material #footer-outer[data-cols="1"] #footer-widgets .widget.widget_nav_menu li:first-child {
        padding-top: 4px!important;
      }
      #footer-outer[data-cols="1"] #copyright li,
      #footer-outer[data-cols="1"] #footer-widgets .widget.widget_nav_menu li {
        float: none;
        display: inline-block;
        margin: 0 10px;
        width: auto;
      }
      #footer-outer[data-cols="1"] #copyright .widget {
        margin-bottom: 0;
      }
      #footer-outer[data-cols="1"] #footer-widgets .widget.widget_nav_menu li,
      #footer-outer[data-cols="1"] #copyright .widget_nav_menu li,
      #footer-outer[data-cols="1"] #copyright .widget_pages li {
        vertical-align: top;
        text-align: left;
        margin: 0 15px;
      }
      #footer-outer[data-cols="1"] #footer-widgets .widget.widget_nav_menu li ul,
      #footer-outer[data-cols="1"] #copyright .widget_nav_menu li ul,
      #footer-outer[data-cols="1"] #copyright .widget_pages li ul {
        padding-left: 0;
        margin-left: 0;
      }
      #footer-outer[data-cols="1"] #footer-widgets .widget.widget_nav_menu li ul li,
      #footer-outer[data-cols="1"] #copyright .widget_nav_menu li ul li,
      #footer-outer[data-cols="1"] #copyright .widget_pages li ul li {
        display: block;
        margin-left: 0;
      }

      #footer-outer[data-cols="1"] #copyright .widget [data-style="minimal-counter"] > li::before,
      #footer-outer[data-cols="1"] #copyright .arrow-circle {
        display: none;
      }
      #footer-outer[data-cols="1"] #copyright .widget_search {
        margin: 20px 0;
      }
      #footer-outer[data-cols="1"] #copyright li a i:after {
        position: absolute;
        -webkit-transition: all .45s cubic-bezier(0.25, 1, 0.33, 1);
        transition: all .45s cubic-bezier(0.25, 1, 0.33, 1);
        pointer-events: none;
        display: block;
        content: "";
        top: 0;
        left: 0;
        opacity: 0.2;
        border-radius: 50%;
        height: 46px;
        width: 46px;
      }
      #footer-outer[data-cols="1"] #copyright a i:after {
        border: 2px solid #fff;
      }
      #footer-outer[data-cols="1"] #copyright li a:hover i:after {
        opacity: 1;
      }
      body #footer-outer[data-cols="1"][data-disable-copyright="false"] .row {
        padding-top: 70px;
        padding-bottom: 40px;
      }
      #footer-outer[data-cols="1"][data-disable-copyright="false"] #copyright {
        padding-bottom: 70px;
      }
      body #footer-outer[data-cols="1"][data-disable-copyright="false"][data-using-widget-area="false"][data-copyright-line="false"][data-matching-section-color="true"] #copyright,
      body #footer-outer[data-cols="1"][data-disable-copyright="false"][data-using-widget-area="false"] #copyright {
        padding-top: 70px;
      }
      body #footer-outer[data-cols="1"][data-disable-copyright="false"][data-copyright-line="false"][data-matching-section-color="true"] .row {
        padding-bottom: 0;
      }
      body #footer-outer[data-cols="1"][data-disable-copyright="false"][data-copyright-line="false"][data-matching-section-color="true"] #copyright {
        padding-top: 30px;
      }';

    }

  // Footer BG
  if( isset($nectar_options['footer-background-image']) &&
      isset( $nectar_options['footer-background-image']['url'] ) &&
     ! empty( $nectar_options['footer-background-image']['url'] ) ) {

       echo '#footer-outer[data-using-bg-img="true"] {
         background-size: cover;
         background-position: center;
       }
       #footer-outer[data-using-bg-img="true"]:after {
         position: absolute;
         width: 100%;
         height: 100%;
         content:"";
         left:0;
         top:0;
         background-color: inherit;
         opacity: 0.9;
       }';

       $footer_overlay_opacity = (isset($nectar_options['footer-background-image-overlay']) && ! empty($nectar_options['footer-background-image-overlay'])) ? $nectar_options['footer-background-image-overlay'] : false;

       if( $footer_overlay_opacity ) {
         echo '#footer-outer[data-using-bg-img="true"][data-bg-img-overlay="' . esc_attr($footer_overlay_opacity) . '"]:after {
           opacity: ' . esc_attr($footer_overlay_opacity) . ';
         }';
       }
  }

  // Centered Copyright.
  $disable_footer_copyright = 'false';

  if ( isset( $nectar_options['disable-copyright-footer-area'] ) &&
       ! empty( $nectar_options['disable-copyright-footer-area'] ) &&
       $nectar_options['disable-copyright-footer-area'] === '1' ) {
           $disable_footer_copyright = 'true';
  }

  if( 'false' === $disable_footer_copyright ) {

    $copyright_footer_layout = ( isset($nectar_options['footer-copyright-layout']) && ! empty( $nectar_options['footer-copyright-layout'] ) ) ? $nectar_options['footer-copyright-layout'] : 'default';
    if( 'centered' === $copyright_footer_layout ) {
      echo '

      #footer-outer #copyright[data-layout="centered"] > .container {
        flex-direction: column;
        align-items: center;
      }

      #footer-outer #copyright[data-layout="centered"] {
        padding: 45px 0;
      }

      #footer-outer #copyright[data-layout="centered"] li {
        float: none;
        display: inline-block;
        margin: 0 10px;
        width: auto;
      }
      @media only screen and (min-width: 1025px) {
        #footer-outer #copyright[data-layout="centered"] .col.span_5 {
          max-width: 70%;
          margin: 0 auto;
        }
      }
      @media only screen and (max-width: 1024px) {
        #footer-outer #copyright[data-layout="centered"] .col.span_5 {
          margin-bottom: 0;
        }
      }
      #footer-outer #copyright[data-layout="centered"] .widget  {
        margin-bottom: 0;
      }

      #footer-outer #copyright[data-layout="centered"] .widget_nav_menu li,
      #footer-outer #copyright[data-layout="centered"] .widget_pages li {
        vertical-align: top;
        text-align: left;
        margin: 0 15px;
      }

      #footer-outer #copyright[data-layout="centered"] .widget_nav_menu li ul,
      #footer-outer #copyright[data-layout="centered"] .widget_pages li ul {
        padding-left: 0;
        margin-left: 0;
      }

      #footer-outer #copyright[data-layout="centered"] .widget_nav_menu li ul li,
      #footer-outer #copyright[data-layout="centered"] .widget_pages li ul li {
        display: block;
        margin-left: 0;
      }
      #footer-outer #copyright[data-layout="centered"] .widget [data-style="minimal-counter"] > li::before,
      #footer-outer #copyright[data-layout="centered"] .widget .arrow-circle {
        display: none;
      }
      #footer-outer #copyright[data-layout="centered"] .widget_search {
        margin: 20px 0;
      }
      #footer-outer #copyright[data-layout="centered"] .col {
        text-align: center;
      }';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 14.4. To Top Button
  /*-------------------------------------------------------------------------*/
  if( isset($nectar_options['back-to-top']) &&
      ! empty($nectar_options['back-to-top']) &&
      '1' === $nectar_options['back-to-top'] ) {
        echo '
        #to-top{
          display:block;
          position:fixed;
          text-align:center;
          line-height:12px;
          right:17px;
          bottom:0px;
        	transform: translateY(105%);
          color:#fff;
          cursor:pointer;
          border-radius:2px;
          -webkit-border-radius:2px;
          z-index:9994;
          height:29px;
          width:29px;
          background-color:rgba(0,0,0,0.25);
          background-repeat:no-repeat;
          background-position:center;
          transition:background-color 0.1s linear;
          -webkit-transition:background-color 0.1s linear;
        }
        body[data-button-style*="rounded"] #to-top{
          transition:box-shadow 0.3s cubic-bezier(.55,0,.1,1),background-color 0.1s linear;
          -webkit-transition:-webkit-box-shadow 0.3s cubic-bezier(.55,0,.1,1),background-color 0.1s linear;
          background-color:rgba(0,0,0,0.25)
        }
        body[data-button-style*="rounded"] #to-top:hover,
        body[data-button-style*="rounded"] #to-top.dark:hover{
          transition:box-shadow 0.3s cubic-bezier(.55,0,.1,1),background-color 0.05s linear 0.25s;
          -webkit-transition:-webkit-box-shadow 0.3s cubic-bezier(.55,0,.1,1),background-color 0.05s linear 0.25s;
          box-shadow:1px 2px 3px rgba(0,0,0,0.16);
          background-color:transparent!important
        }
        body[data-button-style*="rounded"] #to-top:after,
        body[data-button-style*="rounded"] #to-top:before{
          display:block;
          content:" ";
          height:100%;
          width:100%;
          position:absolute;
          top:0;
          left:0;
          z-index:1;
          background-color:#000;
          transform:scale(0);
          -webkit-transform:scale(0);
          transition:all 0.3s cubic-bezier(.55,0,.1,1);
          -webkit-transition:all 0.3s cubic-bezier(.55,0,.1,1);
          border-radius:100px;
          -webkit-border-radius:100px
        }
        body[data-button-style*="rounded"] #to-top:before{
          background-color:rgba(255,255,255,0.25);
          transform:scale(1);
          -webkit-transform:scale(1);
          transition:all 0.5s cubic-bezier(0.165,0.84,0.44,1);
          -webkit-transition:all 0.5s cubic-bezier(0.165,0.84,0.44,1);
          opacity:0;
          z-index:2
        }
        body[data-button-style*="rounded"] #to-top:hover:after{
          transform:scale(1);
          -webkit-transform:scale(1);
        }
        body[data-button-style*="rounded"] #to-top{
          overflow:hidden
        }
        body[data-button-style*="rounded"] #to-top i.fa-angle-up.top-icon,
        body[data-button-style*="rounded"] #to-top i.fa-angle-up{
          -webkit-transform:translate(0,0px);
          transform:translate(0,0px);
          transition:transform 0.2s ease;
          -webkit-transition:transform 0.2s ease;
        }
        body[data-button-style*="rounded"] #to-top:hover i.fa-angle-up.top-icon,
        body[data-button-style*="rounded"] #to-top:hover i.fa-angle-up,
        body[data-button-style*="rounded"] #to-top.hovered i.fa-angle-up.top-icon,
        body[data-button-style*="rounded"] #to-top.hovered i.fa-angle-up{
          -webkit-transform:translate(0,-29px);
          transform:translate(0,-29px)
        }
        body[data-button-style*="rounded"] #to-top:active:before{
          opacity:1
        }
        #to-top i{
          line-height:29px;
          width:29px;
          height:29px;
          font-size:14px;
          top:0;
          left:0;
          text-align:center;
          position:relative;
          z-index:10;
          background-color:transparent
        }
        #to-top:hover,
        #to-top.dark:hover{
          background-color:#000
        }
        #to-top.dark{
          background-color:rgba(0,0,0,0.87)
        }
        body[data-button-style*="slightly_rounded"] #to-top {
          border-radius: 200px!important;
          -webkit-border-radius: 200px!important;
        }
        ';
  }

  /*-------------------------------------------------------------------------*/
  /* 14.5. Global Section
  /*-------------------------------------------------------------------------*/
  if( isset( $nectar_options['global-section-above-footer'] ) &&
      ! empty( $nectar_options['global-section-above-footer']) ) {
        echo '
        #nectar-content-wrap.container-wrap {
        	padding-bottom: 0;
        }
        .woocommerce-checkout .nectar-global-section.before-footer,
        .woocommerce-account .nectar-global-section.before-footer,
        .woocommerce-cart .nectar-global-section.before-footer,
        body:not(.page):not(.single-post):not(.single-portfolio) .nectar-global-section.before-footer,
        .single-portfolio #regular_portfolio [data-nav-pos="in_header"] .nectar-global-section.before-footer {
          padding-top: 40px;
        }

        .container-wrap .row >.wpb_row:not(.full-width-section):not(.full-width-content):last-child {
          margin-bottom: 1.5em;
        }';
  }

  /*-------------------------------------------------------------------------*/
  /* 15. Column Spacing
  /*-------------------------------------------------------------------------*/
  if( isset( $nectar_options['column-spacing'] ) &&
  ! empty( $nectar_options['column-spacing']) &&
  'default' !== $nectar_options['column-spacing']) {

    $column_spacing = intval($nectar_options['column-spacing']);

    if( in_array( $column_spacing, [30,40,50,60,70]) ) {

      if( in_array( $column_spacing, [30,40,50]) ) {
        echo '
        body[data-col-gap="' . $column_spacing . 'px"] .wpb_row:not(.full-width-section):not(.full-width-content) {
          margin-bottom: ' . $column_spacing . 'px;
        }';
      }

      echo '
      body[data-col-gap="' . $column_spacing . 'px"] .vc_row-fluid .span_12 {
        margin-left: -' . ($column_spacing / 2) . 'px;
        margin-right: -' . ($column_spacing / 2) . 'px;
      }

      body[data-col-gap="' . $column_spacing . 'px"] .vc_row-fluid .wpb_column {
        padding-left: ' . ($column_spacing / 2) . 'px;
        padding-right: ' . ($column_spacing / 2) . 'px;
      }
      ';

      /* One fourths */
      echo '
      @media only screen and (max-width: 1024px) and (min-width: 691px) {

        body[data-col-gap="' . $column_spacing . 'px"] .vc_row-fluid:not(.inner_row):not(.full-width-content) > .span_12 > .one-fourths:not([class*="vc_col-xs-"]),
        body[data-col-gap="' . $column_spacing . 'px"] .vc_row-fluid:not(.full-width-content) .vc_row-fluid.inner_row > .span_12 > .one-fourths:not([class*="vc_col-xs-"]) {
          margin-bottom: ' . $column_spacing . 'px;
        }

      }';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 16. Off Canvas Menu
  /*-------------------------------------------------------------------------*/
  if( 'fullscreen-inline-images' === $side_widget_class ) {
    echo '

    .fullscreen-inline-images .nectar-ocm-image-wrap-outer {
      background-color: #000;
    }
    @media only screen and (min-width : 1px) and (max-width: 1024px) {
      #nectar-nav.lighten-logo.transparent #logo[data-supplied-ml="true"] img:not(.mobile-only-logo) {
        display: none;
      }
      #nectar-nav.lighten-logo.transparent #logo[data-supplied-ml="true"] img.mobile-only-logo {
        filter: brightness(0) invert(1);
        opacity: 1!important;
        display: block;
      }

      #nectar-nav.lighten-logo.transparent #top #logo:not([data-supplied-ml="true"]) img.stnd {
        filter: brightness(0) invert(1);
        opacity: 1!important;
      }
      #nectar-nav.lighten-logo.transparent #top #logo:not([data-supplied-ml="true"]) img:not(.stnd) {
        opacity: 0!important;
      }
    }


    @media only screen and (min-width: 1025px) {
      #nectar-nav.lighten-logo.transparent #top #logo img:not(.stnd) {
        opacity: 0!important;
      }
      #nectar-nav.lighten-logo.transparent #top #logo img.stnd {
        filter: brightness(0) invert(1);
        opacity: 1!important;
      }
    }


    #nectar-nav.side-widget-open.transparent #header-secondary-outer {
      position: absolute!important;
      visibility: hidden;
    }


    #slide-out-widget-area.fullscreen-inline-images{
      position:fixed;
      width:100%;
      height:100%;
      right:auto;
      left:0;
      top:0;
      z-index:9997;
      background-color:transparent!important;
      text-align:center;
      display: block;
      overflow-y:scroll;
      overflow-x:hidden;
      box-sizing:content-box;
      -ms-overflow-style: none;
      scrollbar-width: none;
      transform: none!important;
  }


    #slide-out-widget-area-bg.hidden,
    #slide-out-widget-area.hidden {
      z-index: -999;
      pointer-events: none;
      visibility: hidden;
    }

    #slide-out-widget-area-bg.hidden {
      top: 150%;
    }

    @media only screen and (min-width: 1025px) {
      #slide-out-widget-area-bg.hidden .nectar-ocm-image.current,
      #slide-out-widget-area-bg.hidden .nectar-ocm-image.default {
       position: fixed;
       top: 0;
      }
    }
    @media only screen and (max-width: 1024px) {
      #slide-out-widget-area-bg.hidden {
        top: 250%;
      }
    }


    #slide-out-widget-area-bg {
      width: 100%;
      height: 100%;
      -webkit-transition: opacity 0.35s ease;
      transition: opacity 0.35s ease;
      opacity: 0;
      background-color: transparent!important;
    }

    #slide-out-widget-area-bg .nectar-ocm-image {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      opacity: 0;
      background-position: center;
      background-size: cover;
      transform: scale(1.06);
      transition: transform 0.3s ease 0.35s, opacity 0.35s cubic-bezier(0.25,0,0.4,1);
    }

    #slide-out-widget-area-bg .nectar-ocm-image.active {
      opacity: 0.15;
      transform: scale(1);
      z-index: 100;
      transition: transform 4s cubic-bezier(0.07, 0.37, 0.23, 0.99) 0s, opacity 0.35s cubic-bezier(0.25,0,0.4,1);
    }

    #slide-out-widget-area-bg svg,
    #slide-out-widget-area-bg .nectar-ocm-image-wrap-outer,
    #slide-out-widget-area-bg .nectar-ocm-image-wrap {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }

    #slide-out-widget-area-bg .nectar-ocm-image-wrap-outer  {
      overflow: hidden;
    }
    #slide-out-widget-area-bg.hidden .nectar-ocm-image-wrap-outer  {
      transform: translateY(-100%);
    }
    #slide-out-widget-area-bg.hidden .nectar-ocm-image-wrap {
      transform: translateY(100%);

  }
   #slide-out-widget-area-bg.open .nectar-ocm-image-wrap-outer,
    #slide-out-widget-area-bg.open .nectar-ocm-image-wrap {
        transform: translateY(0);
        transition: transform 1s cubic-bezier(0.76, 0, 0.3, 1);
    }

    #slide-out-widget-area-bg .nectar-ocm-image.current {
      transform: scale(1.12);
    }
    #slide-out-widget-area-bg.open .nectar-ocm-image.current {
      opacity: 0.15;
      transform: scale(1);
      transition: transform 1.7s cubic-bezier(0.4, 0, 0.3, 1) 0s, opacity 0.35s cubic-bezier(0.25,0,0.4,1);
      z-index: 10;
    }

    #slide-out-widget-area-bg.open.medium .nectar-ocm-image.current,
    #slide-out-widget-area-bg.medium .nectar-ocm-image.active {
      opacity: 0.4;
    }
    #slide-out-widget-area-bg.open.light .nectar-ocm-image.current,
    #slide-out-widget-area-bg.light .nectar-ocm-image.active {
      opacity: 0.65;
    }
    #slide-out-widget-area-bg.open.solid .nectar-ocm-image.current,
    #slide-out-widget-area-bg.solid .nectar-ocm-image.active {
      opacity: 0;
    }

    #slide-out-widget-area-bg .nectar-ocm-image.current.hidden {
      opacity: 0!important;
    }

    #slide-out-widget-area-bg.open{
      opacity: 1;
    }


    #slide-out-widget-area.fullscreen-inline-images::-webkit-scrollbar {
      display: none;
    }

    #slide-out-widget-area .inner-wrap {
      min-height: 100%;
      width: 100%;
    }

    #slide-out-widget-area.fullscreen-inline-images .inner {
        width:100%;
        position:relative;
        top: 0;
        left:0;
        height: auto;
        margin: auto;
        padding: 30px 0;
    }


    #slide-out-widget-area.fullscreen-inline-images .inner-wrap {
      display: flex;
      flex-direction: column;
  }

    @media only screen and (min-width: 691px) {

      #slide-out-widget-area .off-canvas-menu-container .menu, #slide-out-widget-area .off-canvas-menu-container .menu ul {
        flex-wrap: wrap;
        display: flex;
        justify-content: center;
      }
      .menuwrapper >.sub-menu.dl-animate-in-4,
      #slide-out-widget-area .menuwrapper >.sub-menu,
      body #slide-out-widget-area .menu.subview .subview,
      body #slide-out-widget-area .menu.subview .subview .sub-menu,
      body #slide-out-widget-area .menu.subview .subviewopen,
      body #slide-out-widget-area .menu.subview .subviewopen>.sub-menu {
        display: flex!important;
        flex-wrap: wrap;
        justify-content: center;
      }
    }

    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(1) > a .wrap {transition-delay: 0.5s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(2) > a .wrap {transition-delay: 0.54s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(3) > a .wrap {transition-delay: 0.58s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(4) > a .wrap {transition-delay: 0.62s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(5) > a .wrap {transition-delay: 0.66s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(6) > a .wrap {transition-delay: 0.7s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(7) > a .wrap {transition-delay: 0.74s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(8) > a .wrap {transition-delay: 0.78s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(9) > a .wrap {transition-delay: 0.82s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(10) > a .wrap {transition-delay: 0.86s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li:nth-child(11) > a .wrap {transition-delay: .9s; }
    #slide-out-widget-area.open .off-canvas-menu-container .menu > li > a .wrap {transition-delay: .9s; }

    .fullscreen-inline-images.hidden .inner .off-canvas-menu-container li[class*="current"] a span:after {
      opacity: 0;
    }
    #slide-out-widget-area.fullscreen-inline-images.open .inner .off-canvas-menu-container li[class*="current"] a span:after {
      opacity: 1;
      transition: opacity 0.7s ease, transform 0.4s cubic-bezier(0.52,0.01,0.16,1);
      transition-delay: 1.05s;
    }

    #slide-out-widget-area .off-canvas-menu-container .menu li a,
    .menuwrapper >.sub-menu.dl-animate-in-4 > li >a,
    #slide-out-widget-area .menuwrapper >.sub-menu > li > a {
      margin: 0.35em;
      padding: 0.1em;
      display: block;
      overflow: hidden;
      transition: color 0.4s cubic-bezier(0.52, 0.01, 0.16, 1);
    }

    #slide-out-widget-area .menuwrapper >.sub-menu > li.back,
    .menuwrapper >.sub-menu.dl-animate-in-4 .back,
    body #slide-out-widget-area .menu.subview .subviewopen >.sub-menu >li.back {
        width: 100%;
        margin-bottom: 0!important;
    }

    #slide-out-widget-area .off-canvas-menu-container .menu > li > a .wrap {
      transition: transform 0.2s ease 0.35s, opacity 0.25s ease;
      line-height: 1.1;
      opacity: 0;
      transform: translateY(103%);
    }

    #slide-out-widget-area:not(.open) .off-canvas-menu-container .sub-menu {
      opacity: 0;
      transition: opacity 0.25s ease;
    }

    #slide-out-widget-area.open .off-canvas-menu-container .menu > li > a .wrap {
      transition: transform 1.1s cubic-bezier(0.25, 1, 0.5, 1);
    }
    #slide-out-widget-area .off-canvas-menu-container .menu li a .wrap {
      display: block;
      position: relative;
    }

    #slide-out-widget-area .off-canvas-menu-container .menu > li > a .wrap .nav_desc,
    #slide-out-widget-area .off-canvas-menu-container .menu li a .wrap .item_desc {
      max-width: 200px;
      margin: 0 auto;
      text-align: center;
    }

    #slide-out-widget-area.open .off-canvas-menu-container .menu > li > a .wrap {
      transform: translateY(0);
      opacity: 1;
    }

    #slide-out-widget-area .off-canvas-menu-container {
      padding: 0 15%;
    }


  .fullscreen-inline-images .inner .widget.widget_nav_menu li a,
  .fullscreen-inline-images .inner .off-canvas-menu-container li > a{
      font-size:48px;
      line-height:48px;
  }

    @media only screen and (max-width: 767px) {

        #slide-out-widget-area.fullscreen-inline-images .inner .widget.widget_nav_menu li a,
        #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a{
            font-size:34px;
            line-height:34px;
            margin: 0.1em;
        }

    }

    #slide-out-widget-area.fullscreen-inline-images .inner .widget.widget_nav_menu li a,
    #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li > a {
        display:inline-block;
        position:relative;
        opacity:1;
    }
    .fullscreen-inline-images .inner .widget.widget_nav_menu li a,
    .fullscreen-inline-images .inner .off-canvas-menu-container li > a {
        color:rgba(255,255,255,1);
    }

    #slide-out-widget-area.fullscreen-inline-images .inner .widget.widget_nav_menu li.no-pointer-events,
    #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li.no-pointer-events {
        pointer-events:none
    }


    #slide-out-widget-area .off-canvas-menu-container li > a .wrap:after {
      position:absolute;
      display:block;
      left:0;
      width:100%;
      -webkit-transform:scaleX(0);
      transform:scaleX(0);
      border-top:2px solid #000;
      content:"";
      transform-origin: left;
      pointer-events: none;
      bottom: -2px;
      transition: none;
      border-color: #fff;
      transition: transform 0.4s cubic-bezier(0.52, 0.01, 0.16, 1);
    }

    @media only screen and (max-width: 1024px) {
      #slide-out-widget-area .off-canvas-menu-container li > a{
       transition: none!important;
      }
      #slide-out-widget-area.open .off-canvas-menu-container li > a span:after {
        transition: opacity 0.5s ease 1.1s!important;
       }
      #slide-out-widget-area .bottom-meta-wrap {
        margin-bottom: 25px;
      }
    }

    #slide-out-widget-area .off-canvas-menu-container li > a:hover span:after,
    #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li[class*="current"] a span:after {
       transform: scaleX(1);
    }

    #slide-out-widget-area .off-canvas-menu-container li.back a span:after {
      display: none;
    }

    #slide-out-widget-area.fullscreen-inline-images .inner .widget{
        max-width: 800px;
        width:100%;
        padding: 0 50px;
        margin:20px auto 20px auto;
    }

    #slide-out-widget-area.fullscreen-inline-images .inner .widget p:last-child {
      padding-bottom: 0;
    }



    #slide-out-widget-area.fullscreen-inline-images .widget_recent_comments ul li{
        background:transparent;
        margin-bottom:0px;
        padding:0px
    }


    #slide-out-widget-area.fullscreen-inline-images .tagcloud a{
        float:none;
        display:inline-block
    }

    #slide-out-widget-area.fullscreen-inline-images .widget_calendar table tbody td{
        padding:20px
    }


    body[data-slide-out-widget-area-style="fullscreen-inline-images"] #nectar-nav{
        border-bottom-color:transparent
    }


    #slide-out-widget-area.fullscreen-inline-images .nectar-header-text-content,
    #slide-out-widget-area.fullscreen-inline-images .bottom-meta-wrap,
    #slide-out-widget-area.fullscreen-inline-images .widget,
    #slide-out-widget-area.fullscreen-inline-images .nectar-global-section {
      opacity: 0;
      -webkit-transition: opacity 0.5s ease;
      transition: opacity 0.5s ease;
    }
    #slide-out-widget-area.fullscreen-inline-images.open .widget,
    #slide-out-widget-area.fullscreen-inline-images.open .nectar-global-section {
      transition-delay: 0.6s;
    }
    #slide-out-widget-area-bg.open + #slide-out-widget-area.fullscreen-inline-images .nectar-header-text-content,
    #slide-out-widget-area.fullscreen-inline-images.open .bottom-meta-wrap,
    #slide-out-widget-area.fullscreen-inline-images.open .widget,
    #slide-out-widget-area.fullscreen-inline-images.open .nectar-global-section {
      opacity: 1;
    }
    .nectar-global-section.nectar_hook_ocm_after_menu {
      margin-top: 25px;
    }
    #slide-out-widget-area.fullscreen-inline-images.open .bottom-meta-wrap {
      transition-delay: 0.8s;
    }
    #slide-out-widget-area-bg.fullscreen-inline-images.padding-removed{
        padding:0!important
    }
    .admin-bar #slide-out-widget-area-bg.fullscreen-inline-images.padding-removed{
        padding-top:32px!important
    }
    .admin-bar #slide-out-widget-area-bg.fullscreen-inline-images{
        padding-top:52px
    }

    .admin-bar #slide-out-widget-area {
      padding-top: 0;
    }


    @media only screen and (min-width: 1025px) {
      #slide-out-widget-area.fullscreen-inline-images .off-canvas-social-links{
          position:fixed;
          bottom:18px;
          right:18px
      }
      #slide-out-widget-area.fullscreen-inline-images .bottom-text{
        position:fixed;
        bottom:28px;
        left:28px;
        padding-bottom:0
    }
    }


    #slide-out-widget-area.fullscreen-inline-images .bottom-text {
        color:#fff
    }
    @media only screen and (min-width:1025px){

        #slide-out-widget-area.fullscreen-inline-images .bottom-text[data-has-desktop-social="false"]{
            left:50%;
            transform:translateX(-50%);
            -webkit-transform:translateX(-50%)
        }
    }




    body #slide-out-widget-area .inner >div:first-of-type {
      margin-top: 3%;
    }
    #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container {
      margin-bottom: 0;
    }

    body #slide-out-widget-area.fullscreen-inline-images .slide_out_area_close{
        display:none!important
    }

    #slide-out-widget-area.fullscreen-inline-images .menuwrapper li a{
        display:block;
        position:relative;
        color:#fff;

    }
    #slide-out-widget-area.fullscreen-inline-images .menuwrapper li a{
        overflow:hidden
    }
    #slide-out-widget-area.fullscreen-inline-images .menuwrapper li small {
        display:block
    }
    #slide-out-widget-area.fullscreen-inline-images .menuwrapper li.back >a{
        transform:scale(0.5);
        -webkit-transform:scale(0.5);
        background-color:transparent!important;
        margin-left: .35em!important;
        border: 2px solid;
        transform-origin: center bottom;
        padding: 0.25em 0.5em;
    }


    #slide-out-widget-area.fullscreen-inline-images .widget_shopping_cart {
      max-width: 450px;
    }
    #slide-out-widget-area.fullscreen-inline-images .wp-block-search .wp-block-search__inside-wrapper {
      display: flex;
    }


    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 16.1 Font Sizing
  /*-------------------------------------------------------------------------*/
  $ocm_custom_font_size_desktop = (isset($nectar_options['header-slide-out-widget-area-custom-font-size'])) ? $nectar_options['header-slide-out-widget-area-custom-font-size'] : false;
  $ocm_custom_font_size_mobile = (isset($nectar_options['header-slide-out-widget-area-custom-font-size-mobile'])) ? $nectar_options['header-slide-out-widget-area-custom-font-size-mobile'] : false;

  if( $ocm_custom_font_size_desktop && ! empty($ocm_custom_font_size_desktop) && class_exists('NectarElDynamicStyles') ) {

    $ocm_font_size_selector = 'body #slide-out-widget-area .inner .off-canvas-menu-container li.menu-item > a';

    if( 'fullscreen-inline-images' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'fullscreen' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.fullscreen .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'fullscreen-alt' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.fullscreen-alt .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'slide-out-from-right-hover' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li.menu-item > a';
    }

    echo '@media only screen and (min-width: 1025px) {
      ' . $ocm_font_size_selector . ' {
      font-size: ' . esc_attr(NectarElDynamicStyles::font_sizing_format($ocm_custom_font_size_desktop)) . '!important;
      line-height: 1!important;
     }
    }';
  }

  if( $ocm_custom_font_size_mobile && ! empty($ocm_custom_font_size_mobile) && class_exists('NectarElDynamicStyles') ) {

    $ocm_font_size_selector = 'body #slide-out-widget-area .inner .off-canvas-menu-container li.menu-item > a';

    if( 'fullscreen-inline-images' === $side_widget_class ) {
      $ocm_font_size_selector .= ', body #slide-out-widget-area.fullscreen-inline-images .inner .widget.widget_nav_menu li.menu-item > a,
      body #slide-out-widget-area.fullscreen-inline-images .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'fullscreen' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.fullscreen .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'fullscreen-alt' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.fullscreen-alt .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'slide-out-from-right-hover' === $side_widget_class ) {
      $ocm_font_size_selector = ' body #slide-out-widget-area.slide-out-from-right-hover .inner .off-canvas-menu-container li.menu-item > a';
    }
    else if( 'simple' === $side_widget_class ) {
      $ocm_font_size_selector = ' #nectar-nav #mobile-menu ul li.menu-item > a';
    }

    echo '
    @media only screen and (max-width: 1024px) {
      ' . $ocm_font_size_selector . ' {
        font-size: ' . esc_attr(NectarElDynamicStyles::font_sizing_format($ocm_custom_font_size_mobile)) . '!important;
        line-height: 1!important;
      }
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 16.2 Overlay Stength - ocm
  /*-------------------------------------------------------------------------*/
  $slide_out_widget_overlay = (isset($nectar_options['header-slide-out-widget-area-overlay-opacity']) && ! empty($nectar_options['header-slide-out-widget-area-overlay-opacity'])) ? $nectar_options['header-slide-out-widget-area-overlay-opacity'] : 'dark';

if( $slide_out_widget_overlay === 'dark' ) {
    echo 'body #slide-out-widget-area-bg {
		background-color: rgba(0,0,0,0.8);
	}';
} else if( $slide_out_widget_overlay === 'medium' ) {
    echo 'body #slide-out-widget-area-bg {
		background-color: rgba(0,0,0,0.6);
	}';
} else if( $slide_out_widget_overlay === 'none' ) {
    echo 'body #slide-out-widget-area-bg {
		background-color: transparent;
	}';
} else {
    echo 'body #slide-out-widget-area-bg {
		background-color: rgba(0,0,0,0.4);
	}';
}

/*-------------------------------------------------------------------------*/
/* 16.3 Styling
/*-------------------------------------------------------------------------*/

$slide_out_widget_roundness = (isset($nectar_options['header-slide-out-widget-area-roundness']) && ! empty($nectar_options['header-slide-out-widget-area-roundness'])) ? $nectar_options['header-slide-out-widget-area-roundness'] : 0;
$slide_out_widget_width = (isset($nectar_options['header-slide-out-widget-area-slide-from-side-width']) && ! empty($nectar_options['header-slide-out-widget-area-slide-from-side-width'])) ? $nectar_options['header-slide-out-widget-area-slide-from-side-width'] : 33;
$slide_out_widget_offset = (isset($nectar_options['header-slide-out-widget-area-offset']) && ! empty($nectar_options['header-slide-out-widget-area-offset'])) ? $nectar_options['header-slide-out-widget-area-offset'] : 0;

echo '
#slide-out-widget-area {
  --roundness: ' . $slide_out_widget_roundness . 'px;
  --desktop-width: ' . $slide_out_widget_width . 'vw;
  --offset: ' . $slide_out_widget_offset . 'px;
}
';

  /*-------------------------------------------------------------------------*/
  /* 17. Animations
  /*-------------------------------------------------------------------------*/
  $cubic_beziers = nectar_cubic_bezier_easings();
  $animation_easing = isset($nectar_options['column_animation_easing']) && ! empty($nectar_options['column_animation_easing']) ? $nectar_options['column_animation_easing'] : 'easeOutCubic';
  $animation_duration = isset($nectar_options['column_animation_timing']) && ! empty($nectar_options['column_animation_timing']) ? $nectar_options['column_animation_timing'] : '800';

  if( isset($cubic_beziers[$animation_easing]) ) {
    echo '.nectar-waypoint-el {
      transition: transform ' . $animation_duration . 'ms cubic-bezier(' . $cubic_beziers[$animation_easing] . '), opacity 450ms ease;
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 18. Third Party
  /*-------------------------------------------------------------------------*/
  /*-------------------------------------------------------------------------*/
  /* 18.1. WooCommerce Theme Skin
  /*-------------------------------------------------------------------------*/

  if( function_exists( 'is_woocommerce' ) && 'material' === $theme_skin ) {
    echo '
    .woocommerce #sidebar .widget_layered_nav ul li a {
      padding-left: 25px!important;
    }

    body:not(.ascend).woocommerce #sidebar .widget_layered_nav ul li a:before,
    body:not(.ascend).woocommerce-page #sidebar .widget_layered_nav ul li a:before,
    body:not(.ascend).woocommerce #sidebar .widget_layered_nav ul li:first-child a:before {
      top: 14px;
    }

    body.material #nectar-nav[data-transparent-header="true"] .cart-outer .cart-notification,
    body.material #nectar-nav[data-transparent-header="true"] .cart-outer .widget_shopping_cart {
      margin-top: 0;
    }

    #nectar-nav .nectar-woo-cart .widget_shopping_cart,
    #nectar-nav .nectar-woo-cart .cart-notification{
      top: 100%;
    }

    #nectar-nav .nectar-woo-cart .cart-menu-wrap {
      position: relative;
      width: auto;
      overflow: visible;
      right: auto!important;
    }

    .woocommerce #sidebar div ul li,
    .material.woocommerce #sidebar div ul li {
      padding:3px 0
    }


    body:not(.ascend).woocommerce.material #sidebar .widget_layered_nav ul li:first-child a:before,
    body:not(.ascend).woocommerce.material #sidebar .widget_layered_nav ul li a:before,
    body:not(.ascend).woocommerce-page.material #sidebar .widget_layered_nav ul li a:before,
    body:not(.ascend).woocommerce.material #sidebar .widget_layered_nav ul li a:after,
    body:not(.ascend).woocommerce-page.material #sidebar .widget_layered_nav ul li a:after {
      top: 50%;
      margin-top: -1px;
      -webkit-transform: translateY(-50%);
      transform: translateY(-50%);
    }

    body:not(.ascend).material #sidebar .widget.woocommerce li:first-child > .count {
      top: 6px;
    }

    body:not(.ascend) #sidebar .widget.woocommerce li .count {
      top: 6px;
    }

    body.woocommerce-page.material .product-categories .children {
      margin-top: 3px;
    }


    #nectar-nav:not([data-format="left-header"]) .cart-menu .cart-icon-wrap {
      height: 22px;
      line-height: 22px;
    }

    body.material #nectar-nav:not([data-format="left-header"]) .cart-menu {
      position: relative;
      right: auto;
      padding: 0;
      background-color: transparent;
      top: auto;
    }


    body[data-header-format="left-header"] #nectar-nav .cart-outer {
      -webkit-transition: opacity .35s cubic-bezier(0.12,0.75,0.4,1);
      transition: opacity .35s cubic-bezier(0.12,0.75,0.4,1);
    }
    body[data-header-format="left-header"] #nectar-nav.material-search-open .cart-outer {
      opacity: 0;
      pointer-events: none;
    }

    body.material #nectar-nav[data-format="left-header"] .cart-menu > a,
    body[data-header-format="left-header"].material #nectar-nav nav ul li .cart-menu > a {
      padding: 0;
    }
    body.material #nectar-nav:not([data-format="left-header"]) .cart-wrap {
      margin-right: 0;
      top: -6px;
      right: -10px;
      color: #fff;
    }
    @media only screen and (max-width: 1024px) {
      body.material #nectar-nav[data-format="left-header"] .cart-wrap {
        margin-right: 0;
        top: -5px;
        right: -9px;
        color: #fff;
      }
    }

    #nectar-nav .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart {
      left:0;
      transition:all .2s ease;
      -webkit-transition:all .2s ease
    }
    body.material #nectar-nav .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart {
      font-size:22px
    }
    .icon-nectar-blocks-cart:before, body.material .icon-nectar-blocks-cart:before {
      content:"\e901"
    }
    .material #nectar-nav a.cart-contents .cart-wrap span:before {
      display:none
    }
    #nectar-nav .cart-wrap {
      font-size:11px
    }

    #nectar-nav a.cart-contents .cart-wrap span {
      visibility:hidden;
    }
    body.material #nectar-nav a.cart-contents .cart-wrap span {
      border-radius:99px;
      font:bold 11px/16px Arial;
      line-height:18px;
      width:18px;
      padding:0 1px;
      box-shadow: 0 5px 12px rgba(0,0,0,0.2);
    }

    #nectar-nav .static a.cart-contents span {
      visibility:visible;
    }
    #nectar-nav .has_products .cart-menu .cart-icon-wrap .icon-nectar-blocks-cart {
      transition:all .2s ease;
      -webkit-transition:all .2s ease
    }
    body.material #nectar-nav .cart-menu {
      border-left:1px solid rgba(0,0,0,0.07);
      background-color:transparent
    }

    @media only screen and (min-width: 1025px) {
      .woocommerce.archive #sidebar.span_3,
      .woocommerce.single #sidebar.span_3 {
        width: 21%;
      }
      .woocommerce.archive #sidebar.span_3.col_last {
        width: 20%;
      }
      .woocommerce.archive .post-area.span_9,
      .woocommerce.single .post-area.span_9 {
        width: 75.5%;
      }
      .woocommerce.single .post-area.span_9:not(.col_last) {
        margin-right: 0;
        padding-right: 20px;
      }
      .woocommerce.single #sidebar.span_3.col_last {
        margin-left: 2%;
      }
    }

    .woocommerce nav.woocommerce-pagination ul li a,
    .woocommerce .container-wrap nav.woocommerce-pagination ul li span {
      border-radius:0!important
    }


    .woocommerce .widget_price_filter .ui-slider .ui-slider-handle,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle {
      top: -7px!important;
    }
    .woocommerce .widget_price_filter .ui-slider .ui-slider-range,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-range,
    .woocommerce .widget_price_filter .price_slider_wrapper .ui-widget-content,
    .woocommerce-page .widget_price_filter .price_slider_wrapper .ui-widget-content {
      height: 4px;
    }
    .woocommerce .widget_price_filter .ui-slider .ui-slider-handle,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .woocommerce .widget_price_filter .ui-slider .ui-slider-handle:before,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle:before {
      position: absolute;
      content: "";
      display: block;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border-radius: 100px;
      box-shadow: 0 0 0 10px #000 inset;
      transition: box-shadow 0.2s ease;
    }
    .woocommerce .widget_price_filter .ui-slider .ui-slider-handle.ui-state-active,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle.ui-state-active {
      transform: scale(1.2);
      box-shadow:0px 5px 12px rgba(0,0,0,0.2)!important;
    }
    .woocommerce .widget_price_filter .price_slider_wrapper .ui-widget-content,
    .woocommerce-page .widget_price_filter .price_slider_wrapper .ui-widget-content,
    .woocommerce .widget_price_filter .ui-slider .ui-slider-handle,
    .woocommerce-page .widget_price_filter .ui-slider .ui-slider-handle {
      box-shadow: none!important;
      border: 0!important;
    }

    .woocommerce .actions .button {
      height:auto!important;
      padding:14px!important
    }
    ';
  }

  /*-------------------------------------------------------------------------*/
  /* 18. WooCommerce AJAX Cart
  /*-------------------------------------------------------------------------*/

  $product_archive_layout = ( isset($nectar_options['product_archive_layout']) ) ? $nectar_options['product_archive_layout'] : 'default';

  if( function_exists( 'is_woocommerce' ) &&
      isset( $nectar_options['enable-cart'] ) &&
      ! empty( $nectar_options['enable-cart']) &&
      '1' === $nectar_options['enable-cart'] ) {

    $nav_cart_style = ( isset( $nectar_options['ajax-cart-style'] ) ) ? $nectar_options['ajax-cart-style'] : 'default';

    if( 'slide_in' === $nav_cart_style || 'slide_in_click' === $nav_cart_style ) {

      $cart_underline_color = $nectar_options["accent-color"];

      if( ! empty($nectar_options['header-color']) && $nectar_options['header-color'] === 'custom' ) {
        if( ! empty($nectar_options['header-font-hover-color']) ) {
          $cart_underline_color = $nectar_options['header-font-hover-color'];
        }
      }

      echo '
      .nectar-slide-in-cart .widget_shopping_cart .cart_list li {
      	padding: 20px 20px;
      }
      .nectar-slide-in-cart .widget_shopping_cart .cart_list li span.quantity {
      	line-height: 12px;
      }
      .nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart .cart_list li span.quantity {
        display: block;
      }
      .nectar-slide-in-cart {
      	position: fixed;
      	height: 100%;
      	right: 0;
      	top: 0;
      	z-index: 100000;
      	-ms-transform: translateX(107%);
      	transform: translateX(107%) translateZ(0);
      	-webkit-transform: translateX(107%) translateZ(0);
      }

      .nectar-slide-in-cart,
      .nectar-slide-in-cart.style_slide_in_click > .inner {
        -webkit-transition: all .8s cubic-bezier(0.2,1,.3,1);
        transition: all .8s cubic-bezier(0.2,1,.3,1);
      }

      .nectar-slide-in-cart.open {
          -ms-transform: translateX(0%);
          transform: translateX(0%) translateZ(0);
          -webkit-transform: translateX(0%) translateZ(0);
      }

      .nectar-slide-in-cart .widget_shopping_cart {
      	position: relative;
      	height: 100%;
      	left: 0;
      	top: 0;
      	display: block!important;
      	opacity: 1!important;
      }

      .nectar-slide-in-cart .widget_shopping_cart .cart_list > li:first-child {
      	padding-top: 0;
      	min-height: 98px;
      }

      .nectar-slide-in-cart .widgettitle {
           display: none;
      }

      .nectar-slide-in-cart .total,
      .nectar-slide-in-cart .woocommerce.widget_shopping_cart .total {
           padding: 20px 0 20px;
      }

      .nectar-slide-in-cart.style_slide_in_click .total .tax_label {
        margin-left: 7px;
      }

      body .nectar-slide-in-cart ul.product_list_widget li dl dd {
           color: inherit;
      }

      body .nectar-slide-in-cart ul.product_list_widget li dl {
           width: 220px;
      }

      body .nectar-slide-in-cart .total,
      body .nectar-slide-in-cart .total strong {
           color: #000;
      }

      .nectar-slide-in-cart {
           background-color: #fff;
      }
      .nectar-slide-in-cart:not(.style_slide_in_click) {
           box-shadow: 0 3px 20px rgba(0,0,0,0.09);
      }

      .nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart_content {
           height: 100%;
           padding: 35px;
      }

       .nectar-slide-in-cart .widget_shopping_cart .cart_list .mini_cart_item > a {
           font-size: 18px;
           line-height: 24px;
      }

       .nectar-slide-in-cart .widget_shopping_cart .buttons a {
           display: block;
           padding: 20px;
           font-size: 16px;
           margin-top: 8px;
           margin-left: 0;
      }
      body .nectar-slide-in-cart:not(.style_slide_in_click) .woocommerce.widget_shopping_cart .cart_list li a.remove {
           position: absolute;
           right: 0;
           height: 23px;
           width: 23px;
           padding: 3px!important;
           line-height: 14px;
           margin: 0;
           font-size: 24px;
           transition: all 0.47s cubic-bezier(0.3, 1, 0.3, 0.95) 0s;
           -webkit-transition: all 0.47s cubic-bezier(0.3, 1, 0.3, 0.95) 0s;
      }
       .nectar-slide-in-cart:not(.style_slide_in_click) .woocommerce.widget_shopping_cart .cart_list li a.remove:hover {
           transform: rotate(90deg) translateZ(0);
           -webkit-transform: rotate(90deg) translateZ(0);
      }
      body  .nectar-slide-in-cart .widget_shopping_cart .cart_list a img {
          width: 75px;
           height: auto;
      }
       .nectar-slide-in-cart .widget_shopping_cart .cart_list {
           display: block!important;
           max-height: 65%;
           overflow-y: auto;
           overflow-x: hidden;
           width: 300px;
      }

     .nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart .cart_list a img,
     .nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart .cart_list .no-permalink img {
         position: absolute;
         left: 0;
         float: none;
      }
       .nectar-slide-in-cart:not(.style_slide_in_click) .widget_shopping_cart .cart_list li {
           padding-left: 100px;
           min-height: 112px;
           border-bottom: 1px solid #eee;
      }
       body .nectar-slide-in-cart ul.product_list_widget li dl {
           clear: none;
           float: none;
           margin-bottom: 10px;
      }
       body.admin-bar .nectar-slide-in-cart .widget_shopping_cart {
           top: 32px;
      }

      .nectar-slide-in-cart:not(.style_slide_in_click) .woocommerce.widget_shopping_cart .total {
         border: 0;
      }

      .nectar-slide-in-cart .widget_shopping_cart .cart_list {
        width: 100%;
      }
      .nectar-slide-in-cart.style_slide_in .widget_shopping_cart .cart_list {
        max-width: 300px;
      }';

      if( 'slide_in_click' === $nav_cart_style ) {

        echo '
        @media only screen and (max-width: 850px) and (-webkit-min-device-pixel-ratio: 2) {
           body .nectar-slide-in-cart {
            transform: translateX(50%);
            opacity: 0;
            pointer-events: none;
          }
          body .nectar-slide-in-cart.open {
            opacity: 1;
            pointer-events: all;
            transform: translateX(0%);
          }
          body .nectar-slide-in-cart.style_slide_in_click > .inner {
            -webkit-transform: none;
            transform: none;
          }
        }

        .nectar-slide-in-cart .widget_shopping_cart_content {
           height: 100%;
           padding: min(var(--container-padding), 40px);
        }
        .nectar-slide-in-cart.style_slide_in_click .close-cart {
          transition: opacity 0.2s ease;
        }
        body:not(.material) .nectar-slide-in-cart.style_slide_in_click .close-cart:hover {
          opacity: 0.55;
        }
        .nectar-slide-in-cart.style_slide_in_click .woocommerce.widget_shopping_cart .total {
           border-top: 1px solid rgba(0,0,0,0.1);
        }
        body .nectar-slide-in-cart .woocommerce.widget_shopping_cart .cart_list li a.remove {
          float: none;
          margin-left: 0;
          margin-bottom: 0;
          border: none;
          font-size: inherit;
          height: auto;
          line-height: 17px;
          width: auto;
          transition: opacity 0.2s ease;
        }
        body .nectar-slide-in-cart .woocommerce.widget_shopping_cart .cart_list li a.remove:hover {
          opacity: 0.55;
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list {
         max-height: none;
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list li {
        	padding: 20px 0;
          display: flex;
          align-items: center;
          border-top: 1px solid rgba(0,0,0,0.1);
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list li:first-child {
          border-top: none;
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart_content {
          width: 600px;
        }
        body .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list a img {
          float: left;
        }
        .nectar-slide-in-cart.style_slide_in_click {
          overflow: hidden;
        }
        .nectar-slide-in-cart.style_slide_in_click .cart_list .product-meta .product-details > a:not(.remove) {
          display: inline;
        }
        .nectar-slide-in-cart.style_slide_in_click .buttons a {
          margin-right: 0;
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart_content {
          display: flex;
          flex-direction: column;
        }

        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list {
          flex: 1;
        }
        .nectar-slide-in-cart.style_slide_in_click .woocommerce-mini-cart__total,
        .nectar-slide-in-cart.style_slide_in_click .nectar-inactive {
          margin-top: auto;
        }
        .nectar-slide-in-cart.style_slide_in_click .nectar-inactive {
          cursor: not-allowed;
        }
        .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart_content .nectar-inactive a {
          opacity: 0.3;
          pointer-events: none;
          filter: grayscale(1);
        }
        .nectar-slide-in-cart.style_slide_in_click > .inner {
          -webkit-transform: translateX(-107%);
          transform: translateX(-107%);
          height: 100%;
        }

        .style_slide_in_click .cart_list .product-meta a:not(.remove) {
          background-repeat: no-repeat;
          background-size: 0% 2px;
          background-image: linear-gradient(to right, ' . esc_attr($cart_underline_color) . ' 0%, ' . esc_attr($cart_underline_color) . ' 100%);
          -webkit-transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
          transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
          background-position: left bottom;
        }
        .style_slide_in_click .cart_list .product-meta a:hover:not(.remove) {
          background-size: 100% 2px;
        }
        .nectar-slide-in-cart.style_slide_in_click.open > .inner {
          -webkit-transform: translateX(0);
          transform: translateX(0);
        }

        .style_slide_in_click .product-meta {
          display: flex;
          position: relative;
          flex: 1;
        }
        .style_slide_in_click .product-meta > .product-details {
          width: 50%;
          padding-right: 30px;
          line-height: 1.5;
          align-self: center;
        }

        .style_slide_in_click .product-meta > .quantity {
          flex: 1;
          display: flex;
          align-items: center;
        }
        .style_slide_in_click .product-meta .modify > .quantity {
          margin: 0;
        }
        .style_slide_in_click .product-meta > .quantity > * {
          display: inline-block;
        }
        .style_slide_in_click .product-meta > .quantity .product-price {
          margin-left: auto;
        }
        .style_slide_in_click .product-meta > .quantity .product-price > * {
          display: block;
          line-height: 1!important;
        }
        .style_slide_in_click .product-meta > .quantity .amount bdi {
          font-size: 16px;
        }
        .style_slide_in_click .product-meta > .quantity .modify {
          min-width: 100px;
        }
        body .nectar-slide-in-cart ul.product_list_widget li dl {
         width: auto;
         line-height: 1.5;
         margin: 5px 0 0 0;
         padding-left: 0;
         border-left: none;
      }

      .nectar-slide-in-cart.nectar-slide-in-cart .widget_shopping_cart {
        height: calc(100% - 45px);
        top: 38px;
      }
      body.admin-bar .nectar-slide-in-cart.nectar-slide-in-cart .widget_shopping_cart {
        top: 70px;
        height: calc(100% - 75px);
       }
      #slide-out-widget-area .widget_shopping_cart .cart_list li .product-meta .product-details {
          line-height: 1.4;
        }
       body #slide-out-widget-area .widget_shopping_cart .cart_list li .product-meta .product-details > a {
        max-width: 100%;
        margin-bottom: 0;
        display: inline;
       }
       body #slide-out-widget-area .widget_shopping_cart .cart_list li {
        padding-bottom: 35px;
      }
      body #slide-out-widget-area ul.product_list_widget li img {
        width: 75px;
      }
      #slide-out-widget-area .widget_shopping_cart .qty {
        color: inherit;
      }
      body #slide-out-widget-area .widget_shopping_cart .woocommerce-Price-amount {
     	 display: block;
      }
      #slide-out-widget-area .woocommerce.widget_shopping_cart .cart_list li a.remove.with_text {
         margin-bottom: 10px;
      }


       @media only screen and (max-width:767px) {
         .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart_content {
           width: 100vw;
           padding: 30px 20px 20px 20px;
         }
        body .nectar-slide-in-cart.style_slide_in_click .inner>.header {
          left: 20px;
          width: calc(100% - 20px * 2);
         }
         .style_slide_in_click .product-meta > .product-details {
           width: 57%;
         }
         .nectar-slide-in-cart .widget_shopping_cart_content {
            padding: 30px;
        }
        .nectar-slide-in-cart.style_slide_in_click .inner > .header {
            left: 30px;
            width: calc(100% - 60px);
        }
         .style_slide_in_click .product-meta > .quantity {
            align-items: flex-end;
            flex-direction: column-reverse;
        }
        .style_slide_in_click .product-meta > .quantity .modify {
            margin-top: 25px;
        }
        .nectar-slide-in-cart.style_slide_in_click .product-meta > .quantity .modify {
          min-width: 75px;
        }
       .nectar-slide-in-cart.style_slide_in_click .product-meta > .product-details {
           padding-right: 15px;
       }
        body .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .cart_list .mini_cart_item>a {
          margin-right: 15px;
        }
        .nectar-slide-in-cart.style_slide_in_click ul.product_list_widget li img {
          margin-right: 0!important;
        }
        .nectar-slide-in-cart.style_slide_in_click .woocommerce-mini-cart div.quantity,
        #nectar-nav .widget_shopping_cart .cart_list a img, #nectar-nav ul.product_list_widget li img,
        body .nectar-slide-in-cart.style_slide_in_click ul.product_list_widget li img,
        body .nectar-slide-in-cart.style_slide_in_click ul.product_list_widget li img:not(.nectar-lazy):not([srcset]) {
          width: 70px;
        }
        body .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart .product-meta a:not(.remove),
        body .style_slide_in_click .product-meta > .quantity .amount bdi,
        body .nectar-slide-in-cart.style_slide_in_click .total {
          font-size: 14px;
        }
        body .nectar-slide-in-cart.style_slide_in_click .widget_shopping_cart a.button {
          padding: 10px;
        }
        html  body .woocommerce-mini-cart div.quantity .qty {
         font-size: 13px!important;
        }
        html body[data-header-format] .woocommerce-mini-cart .quantity input.minus {
          font-size: 14px!important;
        }
       }';

      }

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.3. WooCommerce Quantity Style
  /*-------------------------------------------------------------------------*/
  $qty_style = 'default';

  if( function_exists( 'is_woocommerce' ) ) {

    $qty_style = ( isset( $nectar_options['qty_button_style'] ) && ! empty( $nectar_options['qty_button_style'] ) ) ? $nectar_options['qty_button_style'] : 'default';

    if( 'default' === $qty_style ) {

      echo '


      .woocommerce .quantity,
      .woocommerce-page .quantity,
      .woocommerce #content .quantity,
      .woocommerce-page #content .quantity {
      	width: auto!important;
      }
      .woocommerce div.product form.cart div.quantity {
        width: auto;
      }

      .cart .quantity input.plus,
      .cart .quantity input.minus,
      .woocommerce-mini-cart .quantity input.plus,
      .woocommerce-mini-cart .quantity input.minus {
      	color: #666;
      	width: 35px;
      	height: 35px;
      	text-shadow: none;
      	padding: 0;
      	margin: 0;
      	background-color: transparent;
      	display: inline-block;
      	vertical-align: middle;
      	border: none;
      	position: relative;
      	box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      	transition: all 0.25s ease;
      	border-radius: 50px!important;
      	line-height: 24px!important;
      	font-size: 18px;
      	background-color: #fff;
        -webkit-appearance: none;

      	font-weight: 400;
      }

      #slide-out-widget-area .woocommerce-mini-cart .quantity input.plus,
      #slide-out-widget-area .woocommerce-mini-cart .quantity input.minus {
      	width: 30px;
      	height: 30px;
      }

      .cart .quantity input.plus:hover,
      .cart .quantity input.minus:hover,
      .woocommerce-mini-cart .quantity input.plus:hover,
      .woocommerce-mini-cart .quantity input.minus:hover {
      	box-shadow: 0 2px 12px rgba(0,0,0,0.25);
      }

      .cart .quantity input.plus:hover,
      .cart .quantity input.minus:hover,
      .woocommerce-mini-cart .quantity input.plus:hover,
      .woocommerce-mini-cart .quantity input.minus:hover {
      	color: #fff!important;
      }

      .cart .quantity input.qty,
      .woocommerce-mini-cart .quantity input.qty {
          border: none;
          margin: 0 10px;
          display: inline-block;
          height: 35px;
          line-height: 35px;
          margin: 0;
      		font-size: 20px;
      		font-weight: 700;
          padding: 0 5px;
          text-align: center;
          vertical-align: middle;
      		background-color: transparent;
      		background-image: none;
      		box-shadow: none;
          width: 46px;
          position: relative;
      }
      .cart .quantity input.qty,
      body[data-form-style="minimal"] .woocommerce-mini-cart .quantity input.qty {
        color: inherit;
      }

      .entry-summary .cart .quantity input.qty {
        color: ' . esc_attr( $global_font_color ) . ';
      }

      @media only screen and ( max-width: 767px ) {
        .style_slide_in_click .woocommerce-mini-cart .quantity input.plus,
        .style_slide_in_click .woocommerce-mini-cart .quantity input.minus {
          height: 22px;
          width: 22px;
        }
      }

      @media only screen and (max-width: 770px) {
        .woocommerce .cart .quantity { width: auto!important; }
      }';

    }

    else if( 'grouped_together' === $qty_style ) {
      echo 'body[data-header-format] .cart .quantity input.plus,
      body[data-header-format] .cart .quantity input.minus,
      body[data-header-format] .woocommerce-mini-cart .quantity input.plus,
      body[data-header-format] .woocommerce-mini-cart .quantity input.minus {
        background-color: transparent!important;
        border-radius: 0!important;
        font-size: 18px!important;
        color: inherit;
        font-weight: 400!important;
        line-height: 1!important;
        width: 33.3%;
      }

      body[data-header-format] .cart .quantity input.minus,
      body[data-header-format] .woocommerce-mini-cart .quantity input.minus {
        padding-left: 11%;
        padding-right: 0;
      }

      body[data-header-format] .cart .quantity input.plus,
      body[data-header-format] .woocommerce-mini-cart .quantity input.plus {
        padding-right: 11%;
        padding-left: 0;
      }

      .cart div.quantity,
      .woocommerce-mini-cart div.quantity {
        border: 1px solid rgba(0,0,0,0.4);
        border-radius: 5px;
        overflow: hidden;
        display: flex;
        flex-wrap: nowrap;
        width: 110px;
        transition: border-color 0.2s ease;
      }
      .cart div.quantity:hover,
      .woocommerce-mini-cart div.quantity:hover {
        border-color: rgba(0,0,0,1);
      }
      #slide-out-widget-area .woocommerce-mini-cart div.quantity {
        border: 1px solid rgba(255,255,255,0.4);
      }
      #slide-out-widget-area .woocommerce-mini-cart div.quantity:hover {
        border: 1px solid rgba(255,255,255,1);
      }
      .woocommerce div.product form.cart div.quantity.hidden {
        visibility: hidden;
      }
      .style_slide_in_click .woocommerce-mini-cart div.quantity {
        width: 100px;
      }
      body[data-button-style^="rounded"] .cart div.quantity,
      body[data-button-style^="rounded"] .woocommerce-mini-cart div.quantity {
        border-radius: 200px;
      }
      body[data-header-format] .cart div.quantity .qty,
      body .woocommerce-mini-cart div.quantity .qty {
        border: none;
        font-weight: 600;
        font-size: 16px!important;
        width: 33.3%;
        background-color: transparent;
        box-sizing: content-box;
        color: inherit;
        line-height: 1!important;
        color: inherit;
        padding: 1px 2px;
        box-shadow: none;
        text-align: center;
      }
      body[data-header-format] .woocommerce-mini-cart div.quantity .qty {
        background-color: transparent;
      }
      body #slide-out-widget-area .widget_shopping_cart div.quantity {
        display: flex;
      }
      @media only screen and (max-width: 767px) {
        .cart div.quantity,
        .woocommerce-mini-cart div.quantity {
          margin-left: auto;
        }
      }';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.4. WooCommerce Sidebar Toggles
  /*-------------------------------------------------------------------------*/
  $nectar_blocks_woo_sidebar_toggles = true;

  if( has_filter('nectar-blocks_woocommerce_sidebar_toggles') ) {
    $nectar_blocks_woo_sidebar_toggles = apply_filters('nectar-blocks_woocommerce_sidebar_toggles', $nectar_blocks_woo_sidebar_toggles);
  }

  if( function_exists( 'is_woocommerce' ) && true === $nectar_blocks_woo_sidebar_toggles) {

    // Compatibility with new WooCommerce Product Filters Block
    echo '
      @media (min-width: 1px) {
        body :where(.wc-block-product-filters) .wc-block-product-filters__overlay,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__overlay {
          background: inherit;
          color: inherit;
          inset: 0;
          pointer-events: auto;
          position: relative;
          transition: none;
        }
        body :where(.wc-block-product-filters) .wc-block-product-filters__overlay-wrapper,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__overlay-wrapper {
          background: inherit;
          color: inherit;
          height: auto;
          width: auto;
        }
        body :where(.wc-block-product-filters) .wc-block-product-filters__overlay-dialog,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__overlay-dialog {
          background: inherit;
          color: inherit;
          position: relative;
          transform: none;
        }
        body :where(.wc-block-product-filters) .wc-block-product-filters__open-overlay,
        body :where(.wc-block-product-filters) .wc-block-product-filters__overlay-footer,
        body :where(.wc-block-product-filters) .wc-block-product-filters__overlay-header,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__open-overlay,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__overlay-footer,
        body :where(.wc-block-product-filters).is-overlay-opened .wc-block-product-filters__overlay-header {
          display: none;
        }
      }
    ';
    echo '
    @media only screen and (min-width: 1025px) {
    	.woocommerce #sidebar .widget.woocommerce > ul,
    	.woocommerce #sidebar .widget.widget_product_tag_cloud > div,
      .woocommerce #sidebar .widget.woocommerce-widget-layered-nav > .woocommerce-widget-layered-nav-dropdown {
    		display: block!important;
    	}
    }

    @media only screen and (max-width: 1024px) {
    	.woocommerce #sidebar > div,
    	.woocommerce #sidebar > div.widget,
      .woocommerce #sidebar .inner > div.widget {
    		margin-bottom: 0;
    	}
    	.woocommerce  #sidebar .widget.woocommerce {
    		margin-top: 8px;
    		position: relative;
    	}
    	.woocommerce  #sidebar .widget.woocommerce:not(.widget_price_filter) h4 {
    		margin-bottom: 0;
    	}
    	.woocommerce  #sidebar .widget.woocommerce:not(.widget_price_filter) h4 {
    		cursor: pointer;
    		line-height: 34px;
    		padding-left: 35px;
    		font-size: 14px;
    	}

    	.woocommerce  #sidebar .widget.woocommerce:not(.widget_price_filter) h4:before {
    	    content: " ";
    	    top: 10px;
    	    left: 14px;
    	    width: 2px;
    	    margin-left: -2px;
    	    height: 14px;
    	    position: absolute;
    	    background-color: #888;
    	    -ms-transition: transform 0.45s cubic-bezier(.3,.4,.2,1), background-color 0.15s ease;
    	    transition: transform 0.45s cubic-bezier(.3,.4,.2,1), background-color 0.15s ease;
    	    -webkit-transition: -webkit-transform 0.45s cubic-bezier(.3,.4,.2,1), background-color 0.15s ease;
    	}
    	.woocommerce #sidebar .widget.woocommerce:not(.widget_price_filter) h4:after {
    	    content: " ";
    	    top: 18px;
    	    left: 6px;
    	    margin-top: -2px;
    	    width: 14px;
    	    height: 2px;
    	    position: absolute;
    	    background-color: #888;
    	    -ms-transition: background-color 0.15s ease;
    	    transition: background-color 0.15s ease;
    	    -webkit-transition: background-color 0.15s ease;
    	}

    	.woocommerce #sidebar .widget.woocommerce:not(.widget_price_filter).open-filter h4:before {
    		transform: scaleY(0);
    		-webkit-transform: scaleY(0);
    	}

    	.woocommerce #sidebar .widget.woocommerce:not(.no-widget-title) > ul,
    	.woocommerce #sidebar .widget.widget_product_tag_cloud > div,
      .woocommerce #sidebar .widget.woocommerce-widget-layered-nav > .woocommerce-widget-layered-nav-dropdown {
    		display: none;
    		padding-left: 35px;
    	}

    }';

  }

  /*-------------------------------------------------------------------------*/
  /* 18.5. WooCommerce Single Gallery Variants
  /*-------------------------------------------------------------------------*/

  $product_gallery_style = (! empty($nectar_options['single_product_gallery_type'])) ? $nectar_options['single_product_gallery_type'] : 'default';

  if( function_exists( 'is_woocommerce' ) ) {

    if( 'left_thumb_sticky' === $product_gallery_style ) {
      // Match right side product info padding based on container ext left/right padding
      if( intval($ext_padding) < 70 ) {
        echo '@media only screen and (min-width: 1025px) {
          .single-product .product[data-gallery-style="left_thumb_sticky"] .summary.entry-summary {
            padding-right: ' . (70 - intval($ext_padding)) . 'px;
          }
        }';
      }

    }

    if( 'left_thumb_sticky_fullwidth' === $product_gallery_style ) {

      echo 'body[data-header-resize].single-product .container-wrap {
        padding-top: 0;
      }
      .product-thumbs-wrap {
        display: flex;
        max-height: 60vh;
        height: 60vh;
        align-items: center;
      }
      .single-product .product-thumbs {
        margin: 0;
        width: 100%;
      }
      .single-product .single-product-wrap {
        position: relative;
      }

      .single-product .product .summary.entry-summary {
        padding: 6% 6% 0 6%;
      }
      .single-product .product[data-gallery-style="left_thumb_sticky"] .single-product-main-image {
        padding: 6% 0 0 30px;
      }

      .single-product .product[data-gallery-style="left_thumb_sticky"] .images .slider > div:last-child {
        margin-bottom: 0;
      }
      @media only screen and (min-width: 1025px) {
        .single-product .product[data-gallery-style="left_thumb_sticky"] div.images {
          padding-right: 30px;
        }
        .single-product .row > .product[data-gallery-style="left_thumb_sticky"] .single-product-main-image {
            width: 60%;
        }
        .single-product .row > .product[data-gallery-style="left_thumb_sticky"][data-tab-pos*="fullwidth"] .summary.entry-summary {
          width: 40%;
        }
      }
      ';

    }

    /*left aligned thumbs fullwidth */
    if( 'left_thumb_slider' === $product_gallery_style ) {

      echo '

     .single-product .product-thumbs .thumb-inner {
       line-height: 0;
     }

      .single-product .images .product-slider {
        position: relative;
      }

      .single-product-main-image {
        flex: 1;
        display: flex;
        flex-direction: row-reverse;
      }

      .woocommerce div.product .product-slider.woocommerce-product-gallery > .woocommerce-product-gallery__trigger {
        display: none;
      }

      .woocommerce div.product div.images .zoomImg {
        pointer-events: none;
      }

      .woocommerce div.product div.images .swiper-slide .woocommerce-product-gallery__trigger {
        position: relative;
        top: 0;
        right: 0;
        width: auto;
        height: auto;
        background: transparent;
        text-indent: 0;
        border-radius: 0;
        display: block;
        box-shadow: none;
        transition: none;
        z-index: auto;
      }
      .woocommerce div.product div.images .is-moving .slide .woocommerce-product-gallery__trigger {
        pointer-events: none;
      }
      .woocommerce div.product div.images .swiper-slide .woocommerce-product-gallery__trigger:before,
      .woocommerce div.product div.images .swiper-slide .woocommerce-product-gallery__trigger:after {
        display: none;
      }

      .swiper-container.product-slider:hover .button-next,
      .swiper-container.product-slider:hover .button-prev {
        opacity: 1;
      }

      .swiper-container.product-slider .button-next,
      .swiper-container.product-slider .button-prev {
        position: absolute;
        top: 50%;
        z-index: 100;
        width: 20px;
        text-align: center;
        height: 20px;
        opacity: 0;
        transition: opacity 0.2s ease;
      }

      .swiper-container.product-slider .button-next {
        right: 20px;
      }

      .swiper-container.product-slider .button-prev {
        left: 20px;
      }




      @media only screen and (min-width: 1025px) {

        .product[data-tab-pos="fullwidth"] .summary.entry-summary {
          width: 40%;
          padding-left: 5%;
        }

        .woocommerce.single-product .single-product-wrap {
          display: flex;
          position: relative;
          align-items: center;
          min-height: calc( 100vh - ' . esc_attr($header_space) . 'px - 80px)!important;
        }

        .admin-bar.woocommerce.single-product .single-product-wrap {
          min-height: calc( 100vh - ' . esc_attr($header_space) . 'px - 110px)!important;
        }

        .woocommerce div.product .single-product-wrap div.images:not([data-has-gallery-imgs="false"]) {
          width: calc(100% - 130px)!important;
        }

        /*
        .single-product .product-thumbs .swiper-wrapper > div {
          height: 0;
          opacity: 0;
          margin-bottom: 0!important;
          padding-bottom: 0px;
          transition: opacity 0.2s ease;
        }
        .single-product .product-thumbs .swiper-wrapper > div:nth-child(1),
        .single-product .product-thumbs .swiper-wrapper > div:nth-child(2),
        .single-product .product-thumbs .swiper-wrapper > div:nth-child(3) {
          opacity: 1;
          height: auto;
          padding-bottom: 10px;
        } */

        .single-product .product-thumbs .thumb,
        .single-product .product-thumbs {
          width: 100px;
        }

        .single-product .product-thumbs {
          margin-left: 0;
          justify-content: center;
          display:flex;
          height: auto;
          max-height: 440px;
        }
        .single-product .product-thumbs .swiper-wrapper{
            height: auto;
            margin: auto 0;
        }
        .single-product .product-thumbs .thumb {
          height: auto;
          margin: 0 0 10px;
        }

        .single-product .product-thumbs .thumb {
          border: 1px solid rgba(0,0,0,0);
          padding: 2px;
          transition: border-color 0.2s ease;
        }
        .single-product .product-thumbs .thumb.swiper-slide-thumb-active,
        .single-product .product-thumbs .thumb:hover {
          border: 1px solid rgba(0,0,0,1);
        }

        .single-product .product-thumbs-wrap {
          margin-right: 30px;
          align-items: center;
          justify-content: center;
          flex-direction: column;
          display:flex;
        }
        .single-product .product-thumbs-wrap .button-next,
        .single-product .product-thumbs-wrap .button-prev {
          text-align: center;
          height: 20px;
          pointer-events: none;
          opacity: 0;
          line-height: 20px;
        }
        .single-product .product-thumbs-wrap.visible .button-next,
        .single-product .product-thumbs-wrap.visible .button-prev {
          pointer-events: all;
          opacity: 1;
        }
        .single-product .product-thumbs-wrap .button-next {
          margin-bottom: auto;
        }
        .single-product .product-thumbs-wrap .button-prev {
          margin-top: auto;
        }


      }

      @media only screen and (max-width: 1024px) {
        .single-product-main-image {
            flex-direction: column;
            margin-right: 0;
        }
        .single-product .product-thumbs .thumb {
          width: calc(20% - 10px);
          margin: 0 5px;
        }
        .single-product .product-thumbs {
          width: calc(100% + 10px);
          margin-left: -5px;
        }
      }

      ';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.6. WooCommerce Single Gallery Width
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $use_custom_gallery_width = ( isset($nectar_options['single_product_gallery_custom_width']) && '1' === $nectar_options['single_product_gallery_custom_width'] ) ? true : false;
    $single_gallery_width = ( isset($nectar_options['single_product_gallery_width']) ) ? $nectar_options['single_product_gallery_width'] : false;
    $product_style = ( ! empty( $nectar_options['single_product_gallery_type'] ) ) ? $nectar_options['single_product_gallery_type'] : 'ios_slider';

    // Modern styles get a default.
    if( in_array($product_style, ['two_column_images']) && false === $use_custom_gallery_width) {
      $use_custom_gallery_width = true;
      $single_gallery_width = '65';
    }

    if( $use_custom_gallery_width && $single_gallery_width ) {

      echo '@media only screen and (min-width: 1025px) {';

      echo '.single-product .nectar-prod-wrap {
        display: flex;
      }';

      // Left Thumb Sticky Style.
      if( 'left_thumb_sticky' === $product_gallery_style ) {
        echo '
        .single-product .row > .product[data-gallery-style="left_thumb_sticky"][data-tab-pos="in_sidebar"] .single-product-summary,
        .single-product .row > .product[data-gallery-style="left_thumb_sticky"][data-tab-pos*="fullwidth"] .summary.entry-summary,
        .single-product .product[data-gallery-style="left_thumb_sticky"][data-tab-pos*="fullwidth"] .summary.entry-summary {
          flex: 1;
          min-width: 340px;
          width: auto;
        }
        .single-product .row > .product[data-gallery-style="left_thumb_sticky"] .single-product-main-image,
        .single-product .product[data-gallery-style="left_thumb_sticky"] .single-product-main-image {
          width: ' . intval( $single_gallery_width ) . '%;
        }';
        if( is_rtl() ) {
          echo '.single-product .nectar-prod-wrap {
            flex-direction: row-reverse;
          }';
        }
      }
      else {
        echo '
        .single-product .row > .product[data-gallery-style][data-tab-pos] .single-product-summary,
        .single-product .row > .product[data-gallery-style][data-tab-pos] .summary.entry-summary,
        .single-product .product[data-tab-pos] .summary.entry-summary {
          flex: 1;
          min-width: 340px;
          width: auto;
        }
        .single-product .row > .product[data-gallery-style] .single-product-main-image,
        .single-product .product[data-gallery-style] .single-product-main-image {
          width: ' . intval( $single_gallery_width ) . '%;
          margin-right: var(--container-padding);
        }
        .woocommerce-tabs[data-tab-style="fullwidth"] #reviews #comments {
          margin-right: 5%;
        }
        .woocommerce-tabs[data-tab-style="fullwidth"] #reviews #comments,
        .woocommerce-tabs[data-tab-style="fullwidth"] #reviews #review_form_wrapper {
          width: 47.5%;
        }
        ';

        if( is_rtl() ) {
            echo '.single-product .row > .product[data-gallery-style] .single-product-main-image,
            .single-product .product[data-gallery-style] .single-product-main-image {
                margin-left: 5%;
                margin-right: 0;
            }';
        }

        if( intval( $single_gallery_width ) > 45 && intval( $single_gallery_width ) < 60 ) {
          echo '.woocommerce div.product div.images .flex-control-thumbs li {
            width: 20%;
            clear: none!important;
          }';
        } else if( intval( $single_gallery_width ) >= 60 ) {
          echo '.woocommerce div.product div.images .flex-control-thumbs li,
          .single-product [data-gallery-style="ios_slider"] .slider > .thumb {
            width: 16.66%;
            clear: none!important;
          }';
        }

      }

      echo '}'; // End media query.

    } // Using custom width.

    // Custom Aspect Ratios.
    $use_custom_ratio = ( isset($nectar_options['single_product_use_custom_image_aspect_ratio']) ) ? $nectar_options['single_product_use_custom_image_aspect_ratio'] : 0;
    if ( $use_custom_ratio === '1' ) {
      $custom_ratio = ( isset($nectar_options['single_product_custom_image_aspect_ratio']) ) ? $nectar_options['single_product_custom_image_aspect_ratio'] : '1-1';
      $custom_ratio = explode('-', $custom_ratio);
      echo '.single-product-main-image .woocommerce-product-gallery__image img:not([role="presentation"]) {
        object-fit: cover;
        aspect-ratio: ' . $custom_ratio[0] . ' / ' . $custom_ratio[1] . ';
      }';
    }
  }

  /*-------------------------------------------------------------------------*/
  /* 18.7. WooCommerce Add to Cart Style
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $product_add_to_cart_style = ( isset($nectar_options['product_add_to_cart_style']) ) ? $nectar_options['product_add_to_cart_style'] : 'default';

    if( 'fullwidth' === $product_add_to_cart_style ) {

      echo '.nectar-prod-wrap .single_add_to_cart_button {
        width: 100%;
      }';

    }
    else if( 'fullwidth_qty' === $product_add_to_cart_style ) {

      if( 'default' === $qty_style ) {
          echo '.woocommerce div.product .nectar-prod-wrap form.cart div.quantity {
            display: flex;
            margin-right: 30px;
            align-items: center;
          }';
      }

      echo '
      .nectar-prod-wrap .cart .flex-break {
        flex-basis: 100%;
        height: 0;
      }

      .woocommerce-page .nectar-prod-wrap button.single_add_to_cart_button {
        margin-top: 0;
        justify-content: center;
        align-items: center;
        display: flex;
        flex: 1;
      }
      .nectar-prod-wrap .woocommerce-variation-add-to-cart,
      .woocommerce div.product .nectar-prod-wrap form.cart:not(.variations_form):not(.grouped_form),
      .woocommerce div.product .nectar-prod-wrap form.cart.cart_group.bundle_form .cart.bundle_data .bundle_button {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
      }
      .nectar-prod-wrap form.cart.grouped_form .single_add_to_cart_button,
      .woocommerce div.product .nectar-prod-wrap form.cart.cart_group.bundle_form .cart.bundle_data {
        width: 100%;
      }
      .woocommerce-page button[type="submit"].single_add_to_cart_button,
      body[data-form-b-style="regular"].woocommerce-page .container-wrap button[type=submit].single_add_to_cart_button {
        padding-left: 10px!important;
        padding-right: 10px!important;
      }
      .single-product[data-header-format="left-header"] div.product .cart div.quantity,
      .single-product[data-header-format="left-header"] .container-wrap button[type=submit].single_add_to_cart_button {
        margin-top: 10px;
      }
      .woocommerce div.product .nectar-prod-wrap .cart div.quantity.hidden {
        width: 0px;
        margin: 0;
      }';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.8. WooCommerce Filters
  /*-------------------------------------------------------------------------*/

  if( function_exists( 'is_woocommerce' ) ) {

    // Filter Sidebar Toggle
    if( true === NectarThemeManager::$woo_product_filters ) {
      echo '
      .nectar-shop-filters .nectar-shop-filter-trigger {
        background-color: var(--nectar-woo-secondary-btn-bg);
        transition: background-color 0.2s ease;
        padding: 10px 20px;
        display: flex;
        justify-content: center;
        color: inherit;
        line-height: 1.2;
        margin-right: 10px;
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container--default .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select {
        background-color: var(--nectar-woo-secondary-btn-bg)!important;
        transition: background-color 0.2s ease;
        padding: 10px 35px 10px 20px;
        border: none;
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select {
        font-weight: inherit;
        font-family: inherit;
        letter-spacing: inherit;
        text-transform: inherit;
        padding: 10px 20px 10px 20px;
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container--default .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container {
          font-size: inherit;
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select {
        line-height: 1.2;
        font-size: inherit!important;
        width: 160px;
        opacity: 1;
        -webkit-appearance: none;
        appearance: none;
        position: relative;
      }

      .nectar-shop-header-bottom .woocommerce-ordering .select2-container--default .select2-selection--single .select2-selection__arrow {
        right: 17px;
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container--default .select2-selection__rendered {
        line-height: 1.2;
        font-size: inherit!important;
        padding: 0;
      }
      .nectar-shop-filters .nectar-shop-filter-trigger:hover {
        background-color: rgba(0,0,0,0.065);
      }
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container:hover .select2-selection--single,
      body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container--open .select2-selection--single {
        background-color: rgba(0,0,0,0.065)!important;
      }
      body #nectar-content-wrap .nectar-shop-header-bottom .widget_layered_nav_filters ul li a {
        line-height: 1.2;
        font-size: inherit!important;
      }
      .nectar-shop-filters .nectar-shop-filter-trigger .text-wrap {
      	display: flex;
        line-height: 20px;
      }

      .nectar-shop-filters .nectar-shop-filter-trigger .text-wrap .dynamic {
        margin-right: 5px;
        height: 20px;
        line-height: 20px;
      }
      ';

      // Icon.
      echo '
      .nectar-shop-filter-trigger .toggle-icon {
        display: block;
        border-top: 2px solid ' . esc_html($global_font_color) . ';
        width: 20px;
        height: 9px;
        margin-right: 10px;
        border-bottom: 2px solid ' . esc_html($global_font_color) . ';
        position: relative;
      }
      .nectar-shop-filter-trigger .toggle-icon .top-line,
      .nectar-shop-filter-trigger .toggle-icon .bottom-line {
        border: 2px solid ' . esc_html($global_font_color) . ';
        border-radius: 50px;
        background-color: #fff;
        position: absolute;
        height: 6px;
        width: 6px;
        transition: transform 0.3s ease;
        display: block;
        content: "";
      }
      .nectar-shop-filter-trigger .toggle-icon .top-line {
        top: -4px;
        left: 3px;
      }
      .nectar-shop-filter-trigger .toggle-icon .bottom-line {
        bottom: -4px;
        right: 3px;
      }
      ';

      // Structure.
      echo '
      .nectar-shop-header h1.page-title {
        margin-bottom: 0;
      }

      .nectar-shop-header .nectar-shop-header-bottom {
        display: flex;
        align-items: center;
        margin-bottom: min(var(--nectar-element-spacing, 40px), var(--container-padding, 40px));

      }
      .full-width-content .nectar-shop-header .nectar-shop-header-bottom {
        padding: 0 2%;
      }

      .nectar-shop-header-bottom .left-side {
        max-width: 65%;
      }
      .nectar-shop-header .nectar-shop-header-bottom .right-side {
        margin-left: auto;
        display: flex;
        align-items: center;
      }

      body .nectar-shop-header .nectar-shop-header-bottom .woocommerce-result-count {
        margin-right: 20px;
      }
      body .full-width-content .nectar-shop-header .nectar-shop-header-bottom .woocommerce-result-count  {
        padding-left: 0;
      }


      body.woocommerce .nectar-shop-header .nectar-shop-header-bottom .woocommerce-ordering {
        margin-left: 0;
      }
       body.woocommerce .nectar-shop-header .nectar-shop-header-bottom .woocommerce-ordering,
       body.woocommerce .nectar-shop-header .nectar-shop-header-bottom .woocommerce-result-count {
         position: relative;
         bottom: 0;
         right: 0;
         margin-bottom: 0;
         margin-top: 0;
         padding-bottom: 0;
         float: none;
       }

      @media only screen and (min-width: 1025px) {
         .archive.woocommerce .container-wrap > .main-content > .row {
           display: -webkit-flex;
           display: flex;
         }
          .archive.woocommerce .container-wrap > .main-content > .row:after {
            display: none;
          }

         .archive.woocommerce .container-wrap > .main-content #sidebar .inner {
           transition: transform 0.4s ease;
         }

         body.woocommerce #sidebar .inner > .nectar-active-product-filters {
           display: none;
         }

         .woocommerce .woocommerce-breadcrumb {
           padding: 0;
         }
         .archive.woocommerce .container-wrap > .main-content #sidebar {
           transition: margin 0.4s ease, transform 0.4s ease, opacity 0.4s ease;
           margin: 0;
           padding-right: var(--container-padding, 4%);
           width: 25%;
         }
         .archive.woocommerce .container-wrap > .main-content #sidebar:not(.col_last) {
           transition: margin 0.4s ease, transform 0.4s ease, opacity 0.2s ease;
         }
         .archive.woocommerce .container-wrap > .main-content #sidebar.col_last {
           padding: 0 0 0 var(--container-padding, 4%);
           z-index: 1;
         }
         .archive.woocommerce .container-wrap > .main-content > .row .post-area.span_9 {
          z-index: 100;
         }

         .archive.woocommerce .container-wrap > .main-content > .row .post-area.span_9 {
            flex: 1;
            margin-right: 0;
            padding-left: 0;
            padding-right: 0;
         }
         .archive.woocommerce .container-wrap > .main-content #sidebar > .header,
         .archive.woocommerce .container-wrap > .main-content #sidebar .theiaStickySidebar > .header {
           display: none;
         }

      }

      @media only screen and (max-width: 1024px) {

        .nectar-shop-filters .nectar-shop-filter-trigger .dynamic .hide {
          display: none!important;
        }
        .nectar-shop-filters .nectar-shop-filter-trigger .dynamic .show,
        body.woocommerce #sidebar .inner > .nectar-active-product-filters ul {
          display: block!important;
        }
        body.woocommerce #sidebar .nectar-active-product-filters ul {
          padding-left: 0!important;
        }
        .nectar-shop-filters .nectar-shop-filter-trigger {
            width: 100%;
        }
        .nectar-shop-filters {
          flex-direction: column;
        }

        .archive.woocommerce .container-wrap > .main-content #sidebar {
          position: fixed!important;
          display: flex;
          justify-content: center;
          align-items: flex-start;
          top: 0;
          width: 100%;
          z-index: 20000;
          background-color: ' . esc_html($global_bg_color) . ';
          padding: 100px 40px 60px 40px;
          overflow-y: auto!important;
          height: 100vh;
          max-height: 100vh;
          flex-wrap: wrap;
          transform: none;
          opacity: 0!important;
          pointer-events: none;
          left: -9999px;
          margin-right: 0!important;
          transition: opacity 0.5s ease;
        }
        .admin-bar.archive.woocommerce .container-wrap > .main-content #sidebar {
          padding-top: 130px;
        }
        .archive.woocommerce .main-content #sidebar .widget.woocommerce {
          margin-bottom: 15px;
        }

        .archive.woocommerce .container-wrap > .main-content #sidebar .inner,
        .archive.woocommerce .container-wrap > .main-content #sidebar .theiaStickySidebar {
          min-width: 100%;
          transform: none!important;
        }

        .archive.woocommerce .container-wrap > .main-content #sidebar.open {
          opacity: 1!important;
          left: 0;
          pointer-events: all;
        }

        .archive.woocommerce .container-wrap > .main-content #sidebar .nectar-close-btn .close-line {
          background-color: ' . esc_html($global_font_color) . ';
        }

        .archive.woocommerce .container-wrap > .main-content #sidebar > .header h4,
        .archive.woocommerce .container-wrap > .main-content #sidebar .theiaStickySidebar > .header h4 {
          margin-bottom: 0;
        }
        .archive.woocommerce .container-wrap > .main-content #sidebar > .header .nectar-font-h5 {
          font-size: 14px;
        }
        .archive.woocommerce .container-wrap > .main-content #sidebar > .header,
        .archive.woocommerce .container-wrap > .main-content #sidebar .theiaStickySidebar > .header {
          align-items: center;
          width: 100%;
          display: flex;
          position: fixed;
          z-index: 100;
          top: 0;
          left: 0;
          margin-bottom: 0;
          padding: 20px 40px;
          background-color: ' . esc_html($global_bg_color) . ';
          border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .admin-bar.archive.woocommerce .container-wrap > .main-content #sidebar > .header,
        .admin-bar.archive.woocommerce .container-wrap > .main-content #sidebar .theiaStickySidebar > .header {
          top: 32px;
        }

        .archive.woocommerce #sidebar .nectar-close-btn-wrap {
          margin-left: auto;
        }

        .nectar-shop-header .nectar-shop-header-bottom {
          align-items: flex-start;
          font-size: 14px;
          margin-bottom: 60px;
        }
        .nectar-shop-header .woocommerce-breadcrumb {
          font-size: 14px;
          padding: 0;
          margin-bottom: 25px;
        }
        .nectar-shop-header .nectar-active-product-filters {
          display: none;
        }
        body.woocommerce #sidebar .nectar-active-product-filters {
          margin-bottom: 40px;
        }
        body.woocommerce .nectar-shop-header .nectar-shop-header-bottom .woocommerce-result-count {
          padding-top: 10px;
          line-height: 1.2;
          margin-right: 0;
          position: absolute;
          left: 0;
          bottom: -30px;
        }
        body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select,
        body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container--default .select2-selection__rendered {
          line-height: 1.4;
        }
        .nectar-shop-header .nectar-shop-header-bottom .left-side,
        .nectar-shop-header .nectar-shop-header-bottom .right-side {
          width: 50%;
        }


        body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering,
        body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering select,
        body[data-form-select-js="1"] .nectar-shop-header-bottom .woocommerce-ordering .select2-container {
            width: 100%!important;
            text-align: center;
            text-align-last:center;
        }

      }
      @media only screen and (max-width: 1024px) and (min-width: 768px) {
        .nectar-shop-header .nectar-shop-header-bottom .right-side > .nectar-font-label {
            width: 100%;
          }
      }
      @media only screen and (max-width: 767px) {
        .nectar-shop-filters .nectar-shop-filter-trigger .dynamic .show {
          display: none!important;
        }
        .nectar-shop-header .nectar-shop-header-bottom .left-side {
            width: 40%;
        }
        .nectar-shop-header .nectar-shop-header-bottom .right-side {
            width: 60%;
        }
        .archive.woocommerce .container-wrap > .main-content #sidebar {
          padding-right: 20px;
          padding-left: 20px;
        }
      }



       ';
    }
    // Filter area not active.
    else {
      echo 'body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default .select2-selection--single,
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default .select2-selection--single:hover,
      body[data-form-select-js="1"] .woocommerce-ordering .select2-container--default.select2-container--open .select2-selection--single,
      body[data-form-select-js="1"] .woocommerce-ordering select {
      	background-color: transparent!important;
      	border: none!important;
      }';
    }

    // Display Active Filters Next To Toggle
    if( isset($nectar_options['product_show_filters']) && '1' === $nectar_options['product_show_filters']) {
      echo '.nectar-active-product-filters {
        line-height: 1;
      }
      .woocommerce .nectar-shop-filters .nectar-active-product-filters ul {
        margin-top: -10px;
      }
      .nectar-active-product-filters h2 {
        display: none;
      }
      .nectar-active-product-filters .widget_layered_nav_filters ul li {
        margin-top: 10px;
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.9. WooCommerce Archive Header
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $product_archive_header_size = ( isset($nectar_options['product_archive_header_size'] ) ) ? $nectar_options['product_archive_header_size'] : 'default';
    $product_archive_border_radius = ( isset($nectar_options['product_archive_header_br'] ) ) ? $nectar_options['product_archive_header_br'] : '0';
    $product_archive_auto_height = ( isset($nectar_options['product_archive_header_auto_height'] ) ) ? $nectar_options['product_archive_header_auto_height'] : '0';
    $product_archive_max_text_width = ( isset($nectar_options['product_archive_header_text_width'] ) ) ? intval($nectar_options['product_archive_header_text_width']) : 900;

    echo '
    .woocommerce.archive #page-header-wrap + .container-wrap {
      padding-top: 20px;
    }
    .woocommerce.archive #page-header-wrap + .container-wrap .woocommerce-breadcrumb {
      margin-bottom: 20px;
    }';

    // Contained Header
    if( 'contained' === $product_archive_header_size ) {

      echo '
      .woocommerce.archive #nectar-content-wrap.container-wrap {
        padding-top: ' . ((intval($ext_padding) < 70) ? intval($ext_padding) . 'px' : '50px' ) . ';
      }
      .woocommerce.archive .container-wrap .nectar-shop-header .woocommerce-breadcrumb {
        margin-bottom: 20px;
      }
      body #page-header-wrap.woo-archive-header.container {
        width: 100%;
        margin-bottom: 20px;
      }
      body #page-header-wrap.woo-archive-header.container .span_6 {
        padding: 0 6%;
      }
      body #page-header-wrap.woo-archive-header.container.align-text-center .span_6 {
        padding: 0 8%;
      }';

      if( '1' === $boxed_layout ) {
        echo '@media only screen and (min-width: 1025px) {
          body #page-header-wrap.woo-archive-header.container {
            width: 92%;
          }
      }';
      }

    }

    // Max Content Width
    echo '.woocommerce.archive #page-header-wrap .inner-wrap {
      max-width: ' . intval($product_archive_max_text_width) . 'px;
    }
    .woocommerce.archive #page-header-bg[data-alignment="center"] .inner-wrap,
    .woocommerce.archive #page-header-wrap.align-text-center .inner-wrap{
      margin: 0 auto;
    }
    .woocommerce.archive #page-header-bg[data-alignment="right"] .span_6,
    .woocommerce.archive #page-header-wrap.align-text-right .span_6 {
      display: flex;
      justify-content: flex-end;
    }';

    // Auto Height
    if( '1' === $product_archive_auto_height ) {
      echo '.woocommerce.archive #page-header-wrap, .woocommerce.archive #page-header-bg {
        height: auto!important;
      }

      .woocommerce.archive #page-header-bg[data-bg-pos="top"] .page-header-bg-image {
        background-position: center;
      }

      .woocommerce.archive #page-header-bg {
        padding: 6% 0;
      }
      .woocommerce.archive #page-header-bg .span_6 {
        -webkit-transform: none;
        transform: none;
        top: 0;
      }

      #page-header-wrap.align-content-right .span_6 {
        display: flex;
        justify-content: flex-end;
      }
      #page-header-wrap.align-content-center .span_6 {
        display: flex;
        justify-content: center;
      }
      #page-header-wrap.align-content-left .span_6 {
        display: flex;
        justify-content: flex-start;
      }


      #page-header-wrap.align-text-right .span_6 {
        text-align: right;
      }
      #page-header-wrap.align-text-center .span_6 {
        text-align: center;
      }
      #page-header-wrap.align-text-left .span_6 {
        text-align: left;
      }
      ';

    }

    // Border Radius
    if( '0' !== $product_archive_border_radius ) {
      echo '#page-header-wrap.woo-archive-header.container #page-header-bg {
        border-radius: ' . intval($product_archive_border_radius) . 'px;
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.10. WooCommerce Product Styles
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {
    $product_style = ( ! empty( $nectar_options['single_product_gallery_type'] ) ) ? $nectar_options['single_product_gallery_type'] : 'ios_slider';

    if( 'two_column_images' === $product_style ) {
      echo '
      .single-product-main-image.col {
        float: none;
      }
      .woocommerce.single-product div.product div.summary {
        margin-bottom: 0;
        float: none;
      }
      .woocommerce.single-product [data-tab-pos*="full"] div.product_meta:last-child {
        margin-bottom: 3px;
      }

      .woocommerce div.product .single-product-main-image div.images .woocommerce-product-gallery__wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: var(--nectar-product-layout-gap);
      }
      .woocommerce.single-product div.product div.images .woocommerce-product-gallery__image img {
        margin-bottom: 0;
      }
      .woocommerce.single-product div.product .single-product-main-image div.images .woocommerce-product-gallery__image a {
        display: block;
        cursor: zoom-in;
      }
      .woocommerce.single-product div.product .single-product-main-image div.images .woocommerce-product-gallery__image {
        width: calc(50% - var(--nectar-product-layout-gap)/2);
      }
      .woocommerce.single-product div.product div.woocommerce-product-gallery-with-single-image.images .woocommerce-product-gallery__image {
        width: 100%;
      }

      @media only screen and (max-width: 1024px) {
        .woocommerce div.product .single-product-main-image div.images .woocommerce-product-gallery__wrapper {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          flex-wrap: nowrap;
        }
        .woocommerce div.product .single-product-main-image div.images .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image {
          flex: 0 0 auto;
          width: 66.6%;
        }
        .woocommerce div.product .single-product-main-image .images.woocommerce-product-gallery-with-single-image  .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image {
          width: 100%;
        }
        .single-product-main-image.col {
          margin-bottom: 35px;
          margin-left: -50vw;
          margin-left: calc(-50vw + var(--scroll-bar-w)/2);
          left: 50%;
        	width: 100vw;
          width: calc(100vw - var(--scroll-bar-w));
        }
      }

      @media only screen and (max-width: 1024px) and (min-width: 691px) {
        #boxed .single-product-main-image.col {
          margin-left: -7%;
          width: 114%;
          left: 0;
        }
      }

      @media only screen and (max-width: 767px) {
        .woocommerce div.product .single-product-main-image div.images .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image {
          width: 75%;
        }
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.11. WooCommerce Product Style Mods
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $product_border_radius = ( isset( $nectar_options['product_border_radius'] ) ) ? esc_html($nectar_options['product_border_radius']) : false;
    $product_style = ( ! empty( $nectar_options['product_style'] ) ) ? $nectar_options['product_style'] : 'classic';
    $product_gap = ( isset( $nectar_options['product_gap'] ) ) ? intval(esc_html($nectar_options['product_gap'])) : 20;
    $product_minimal_border_color = ( isset( $nectar_options['product_minimal_border_color'] ) && ! empty($nectar_options['product_minimal_border_color'] ) ) ? esc_html($nectar_options['product_minimal_border_color']) : 'rgba(0,0,0,0.1)';
    $product_minimal_button_color = ( isset( $nectar_options['product_minimal_button_color'] ) && ! empty($nectar_options['product_minimal_button_color'] ) ) ? esc_html($nectar_options['product_minimal_button_color']) : '#ffffff';
    $product_minimal_button_text_color = ( isset( $nectar_options['product_minimal_button_text_color'] ) && ! empty($nectar_options['product_minimal_button_text_color'] ) ) ? esc_html($nectar_options['product_minimal_button_text_color']) : '#000000';
    $product_minimal_button_color_hover = ( isset( $nectar_options['product_minimal_button_color_hover'] ) && ! empty($nectar_options['product_minimal_button_color_hover'] ) ) ? esc_html($nectar_options['product_minimal_button_color_hover']) : '#ffffff';
    echo ':root {
      --nectar-product-gap: ' . $product_gap . 'px;
      --nectar-product-layout-gap: ' . $product_gap . 'px;
      --nectar-product-button-border-color: ' . $product_minimal_border_color . ';
      --nectar-product-button-color: ' . $product_minimal_button_color . ';
      --nectar-product-button-color-hover: ' . $product_minimal_button_color_hover . ';
      --nectar-product-button-text-color: ' . $product_minimal_button_text_color . ';
    }';

    // Minimal
    if( 'minimal' === $product_style ) {

      // Border Radius
      if( $product_border_radius && $product_border_radius !== 'default' ) {
        echo ':root {
          --nectar-product-border-radius: ' . $product_border_radius . ';
        }';
        echo '.products li.product.minimal .background-color-expand,
              .products li.product.minimal .background-color-expand:before,
              .products li.product.minimal .product-wrap,
              #search-outer .products li.product.minimal,
              .products li.product.minimal .product-image-wrap,
              body .widget_shopping_cart ul.product_list_widget li img,
              body.material .widget_shopping_cart ul.product_list_widget li img {
           border-radius: var(--nectar-product-border-radius);
        }
        li.product.minimal .product-wrap img,
        li.product.minimal:hover .product-wrap img,
        body.woocommerce #nectar-content-wrap ul.products li.minimal.product span.onsale,
        #nectar-content-wrap ul.products li.minimal.product span.onsale,
        .nectar-woo-carousel ul.products li.minimal.product span.onsale,
        .nectar-ajax-search-results ul.products li.minimal.product span.onsale {
          border-radius: var(--nectar-product-border-radius);
        }
        body .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart a{
          border-radius: var(--nectar-border-radius);
        }

        ';
      }
      $product_minimal_border = ( isset( $nectar_options['product_minimal_border'] ) ) ? esc_html($nectar_options['product_minimal_border']) : '0';
      // Bordered Design.
      if( '1' === $product_minimal_border ) {
        echo '.products[data-product-style="minimal"] li.product.minimal {
          border: 1px solid transparent;
        }
        .woocommerce ul.products li.product.minimal .product-wrap {
          background-color: transparent!important;
        }
        .products[data-product-style="minimal"] li.product.minimal .background-color-expand {
          box-shadow: 0px 0px 0px 1px inset rgba(0,0,0,0.08);
          backface-visibility: hidden;
          -webkit-backface-visibility: hidden;
          transition: box-shadow 0.55s cubic-bezier(.2,.75,.5,1), transform 0.55s cubic-bezier(.2,.75,.5,1);
        }
        .products[data-product-style="minimal"] li.product.minimal:hover .background-color-expand {
          box-shadow: 0px 0px 0px 1px inset rgba(0,0,0,0);
        }
        .products[data-product-style="minimal"] li.product.minimal:hover .background-color-expand:before {
          top: 1px;
          left: 1px;
          width: calc(100% - 2px);
          height: calc(100% - 2px);
        }
        .woocommerce ul.products li.product:hover img.hover-gallery-image {
          opacity: 1;
        }

        ';
      }

      $product_minimal_hover_effect = ( isset( $nectar_options['product_minimal_hover_effect'] ) ) ? esc_html($nectar_options['product_minimal_hover_effect']) : 'default';
      $product_minimal_text_alignment = ( isset( $nectar_options['product_minimal_text_alignment'] ) ) ? esc_html($nectar_options['product_minimal_text_alignment']) : 'default';

      if( 'right' === $product_minimal_text_alignment ) {
        echo '.products li.product.minimal .product-meta {
            text-align: right;
        }
        .products li.product.minimal .price-hover-wrap {
          width: 100%;
        }';
      }
      if( 'center' === $product_minimal_text_alignment ) {
        echo '
        .products li.product.minimal .product-meta h2 {
          display: inline-block;
        }
        .products li.product.minimal .product-meta {
            text-align: center;
        }
        .products li.product.minimal .price-hover-wrap {
          width: 100%;
        }';
      }

      // Minimal Product Hover Effect.

      //// Default shadow.
      if( 'default' === $product_minimal_hover_effect ) {

        if( 'right' === $product_minimal_text_alignment ) {
          echo '
          .products li.product.minimal.hover-bound:hover .product-meta {
            transform: translateY(6px) translateX(6px);
          }';
        }
        if( 'center' === $product_minimal_text_alignment ) {
          echo '
          .products li.product.minimal.hover-bound:hover .product-meta {
              transform: translateY(6px);
          }';
        }
        if( 'left' === $product_minimal_text_alignment ||
            'default' === $product_minimal_text_alignment ) {
              echo '
              .products li.product.minimal.hover-bound:hover .product-meta {
                transform: translateY(6px) translateX(-6px);
              }';
        }

        echo 'body .products li.product.minimal .product-image-wrap .product-add-to-cart {
          transform: none;
        }';

      }

      //// Image Zoom.
      else if( 'image_zoom' === $product_minimal_hover_effect ) {
        echo 'body .products li.product.minimal.hover-bound:hover .product-meta {
          transform: none;
        }
        .products li.product.minimal .background-color-expand {
          display: none;
        }
        .single-product .product[data-n-lazy="1"] .minimal img.nectar-lazy,
        ul.products li.product.minimal img {
          transition: border-color 0.1s ease, opacity 0.5s ease, transform 0.4s cubic-bezier(.2,.75,.5,1);
        }
        .woocommerce ul.products li.product.minimal .product-wrap img.hover-gallery-image {
          transition: transform .55s cubic-bezier(0.25,1,0.33,1), opacity .55s cubic-bezier(0.25,1,0.33,1);
        }

        .products li.product.minimal .product-image-wrap {
          overflow: hidden;
        }
        ul.products li.product.minimal.product-image-wrap:not(.has-hover-image) img {
          transform: translateZ(0);
        }
        ul.products li.product.minimal:hover .product-image-wrap:not(.has-hover-image) img {
          transform: scale(1.04) translateZ(0);
        }
        ul.products li.product.minimal .product-image-wrap.has-hover-image .hover-gallery-image {
          transform: scale(1) translateZ(0);
        }
        ul.products li.product.minimal:hover .product-image-wrap.has-hover-image .hover-gallery-image {
          transform: scale(1.04) translateZ(0);
        }';
      }

      $product_minimal_bg_color = isset($nectar_options['product_minimal_bg_color']) ? $nectar_options['product_minimal_bg_color'] : '';
      $product_minimal_shop_color = isset($nectar_options['product_archive_bg_color']) ? $nectar_options['product_archive_bg_color'] : '';

      if( empty($product_minimal_shop_color) ) {
        $product_minimal_shop_color = $global_bg_color;
      }

      if( empty($product_minimal_bg_color) ) {
        $product_minimal_bg_color = $global_bg_color;
      }

      if ( $product_minimal_shop_color === $product_minimal_bg_color && 'bordered' !== $product_archive_layout) {
        echo '.products li.product.minimal .product-meta {
          padding-left: 0;
          padding-right: 0;
        }';
      }

      // Minimal Product Hover Layout.
      $product_minimal_hover_layout = ( isset( $nectar_options['product_minimal_hover_layout'] ) ) ? esc_html($nectar_options['product_minimal_hover_layout']) : 'default';
      if( 'price_visible_flex_buttons' === $product_minimal_hover_layout ) {

        echo '

        body.woocommerce #nectar-content-wrap ul.products li.minimal.product span.onsale,
        #nectar-content-wrap ul.products li.minimal.product span.onsale,
        .nectar-woo-carousel ul.products li.minimal.product span.onsale,
        .nectar-ajax-search-results ul.products li.minimal.product span.onsale {
          border-radius: var(--nectar-border-radius);
        }

        body .nectar-woo-carousel > ul.products[data-product-style] > li.product,
        body .full-width-content .nectar-woo-carousel > ul.products[data-product-style] > li.product {
          margin: 0 10px!important;
        }
        .wpb_row:not(.full-width-content) .woocommerce .nectar-woo-carousel,
        .related-upsell-carousel.nectar-woo-carousel {
          width: calc(100% + 20px);
          margin-left: -10px;
          padding: 10px 0;
        }
        .related-upsell-carousel.nectar-woo-carousel h2 {
        	margin-left: 10px;
          padding-right: 10px;
        }
        @media only screen and (max-width: 1600px) and (min-width: 1025px) {
          .nectar-woo-carousel > ul.products[data-product-style].columns-dynamic > li.product {
              width: calc(25% - 20px)!important;
          }
        }
        @media only screen and (min-width: 1600px) {
          .nectar-woo-carousel > ul.products[data-product-style].columns-dynamic > li.product {
              width: calc(20% - 20px)!important;
          }
        }
        @media only screen and (max-width: 1024px) and (min-width: 691px) {
          .nectar-woo-carousel > ul.products[data-product-style].columns-dynamic > li.product {
              width: calc(33% - 20px)!important;
          }
        }
        @media only screen and (min-width: 1025px) {
          .nectar-woo-carousel > ul.products[data-product-style].columns-4 > li.product {
              width: calc(25% - 20px)!important;
          }
          .nectar-woo-carousel > ul.products[data-product-style].columns-3 > li.product {
              width: calc(33.3% - 20px)!important;
          }
        }
        @media only screen and (min-width: 691px) and (max-width: 1024px){
          .nectar-woo-carousel > ul.products[data-product-style].columns-3 > li.product {
              width: calc(50% - 20px)!important;
          }
        }
        @media only screen and (min-width: 691px) {
          .nectar-woo-carousel > ul.products[data-product-style].columns-2 > li.product {
              width: calc(50% - 20px)!important;
          }
        }
        body .woocommerce .nectar-woo-carousel[data-controls="arrows-overlaid"]:not(.related-upsell-carousel) .nectar-woo-carousel-top {
        	top: calc(50% - 50px);
        }

        .products li.product.minimal:hover .product-meta .price {
            opacity: 1;
            width: 100%;
        }

        .products li.product.minimal .background-color-expand,
        .products li.product.minimal .product-meta > a h2,
        .products li.product.minimal .product-meta,
        .products li.product.minimal .product-meta .price,
        .products li.product.minimal .background-color-expand:before {
          transition: opacity 0.4s cubic-bezier(.2,.75,.5,1), transform 0.4s cubic-bezier(.2,.75,.5,1);
        }

        .nectar-quick-view-box.visible:before {
          transition: opacity 0.8s ease 0.65s;
        }

        .products li.product.minimal .background-color-expand:before {
          box-shadow: 0px 5px 75px -10px rgba(0,0,0,0.12)
        }
        .products li.product.minimal.no-trans .background-color-expand:before {
          box-shadow: none;
        }

        .products li.product.minimal .product-image-wrap .product-add-to-cart a {
          transition: opacity 0.4s cubic-bezier(.2,.75,.5,1), background-color 0.4s cubic-bezier(.2,.75,.5,1), background-size 0.55s cubic-bezier(.2,.75,.5,1);
        }

        .woocommerce ul.products li.product .product-wrap img.hover-gallery-image,
        .woocommerce ul.products li.product .product-wrap picture.hover-gallery-image {
          transition: opacity 0.4s cubic-bezier(.2,.75,.5,1);
        }

        .products li.product.minimal .product-image-wrap {
          position: relative;
        }
        ul.products li.minimal.product .product-wrap {
          height: 100%;
        }
        .products li.product.minimal:hover .product-image-wrap .product-add-to-cart .nectar_quick_view {
          transition-delay: 0.05s;
        }

        .products li.product.minimal .product-image-wrap .product-add-to-cart {
          display: flex;
          transform: translateZ(0);
          position: absolute;
          bottom: 0;
          left: 0;
          z-index: 10;
          top: auto;
          margin: 10px;
          width: calc(100% - 20px);
          gap: 10px;
        }
        .products li.product.minimal:hover .product-add-to-cart a {
          opacity: 1;
          transform: translateY(0%);
        }
        .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart a {
          width: 50%;
          flex: 1;
          background-color: var(--nectar-product-button-color)!important;
          padding: 10px 5px!important;
          color: var(--nectar-product-button-text-color);
          border-radius: 0;
          justify-content: center;
          align-items: center;
          text-align: center;
          opacity: 0;
          display: inline-flex;
          line-height: 20px;
          transition:
            transform .55s cubic-bezier(0.25,1,0.33,1),
            opacity .55s cubic-bezier(0.25,1,0.33,1),
            background-color .55s cubic-bezier(0.25,1,0.33,1);
          transform: translateY(calc(100% + 20px));
          border-radius: var(--nectar-product-border-radius);
          border: 1px solid var(--nectar-product-button-border-color, rgba(0,0,0,0.1));
        }
        @media only screen and (max-width: 1024px) {
          .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart a {
            transform: none;
            opacity: 1;
          }
        }
        .products li.product.minimal:hover .product-wrap .product-image-wrap .product-add-to-cart a {
          transform: translateY(0%);
          opacity: 1;
        }
        .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart a:hover {
          background-color: var(--nectar-product-button-color-hover)!important;
        }

        .products li.product.minimal [data-nectar-quickview="true"] .nectar_quick_view,
        body .products li.product.minimal .product-add-to-cart a {
          font-size: 14px;
        }

        .products li.product.minimal .product-add-to-cart .loading:after {
            display: none;
        }

        .products li.product.minimal .product-add-to-cart .loading  {
          cursor: not-allowed;
          opacity: 0.6!important;
        }

        .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart .added_to_cart,
        .products li.product.minimal .product-add-to-cart .loading i {
          display: none;
        }
        .products li.product.minimal .product-add-to-cart .button i {
          display: none;
        }

        .products li.product.minimal .product-wrap .product-image-wrap [data-nectar-quickview="true"] a:first-child,
        .products li.product.minimal .product-wrap .product-image-wrap .product-add-to-cart .added_to_cart {
          margin-right: 0;
        }

        .woocommerce ul.products .minimal.product span.onsale,
        .woocommerce-page ul.products .minimal.product span.onsale {
          left: 10px;
          top: 10px;
        }
        .products li.product.minimal .product-image-wrap .product-add-to-cart a i.normal {
          top: 0;
          font-size: 14px;
          vertical-align: middle;
          color: #000!important;
        }

        ';
      }
      // Non flex buttons
      else {

        if( 'image_zoom' === $product_minimal_hover_effect ) {
          echo '.products li.product.minimal .product-wrap > a {
            overflow: hidden;
            position: relative;
            display: block;
          }
          ul.products li.product.minimal:hover .product-wrap > a img {
            transform: scale(1.04) translateZ(0);
          }';

          if( $product_border_radius && $product_border_radius !== 'default' ) {
            echo '.products li.product.minimal .product-wrap > a {
              border-radius: ' . $product_border_radius . ';
            }';
          }

        }

        echo '
          .products li.product.minimal .product-wrap .price-hover-wrap .product-add-to-cart .added_to_cart,
          .products li.product.minimal .product-add-to-cart .loading i {
            display: none;
          }
           .products li.product.minimal .product-add-to-cart .loading:after {
            display: none;
          }

          .products li.product.minimal [data-nectar-quickview=true] .button.product_type_simple.added {
              margin-right: 14px;
          }

          .products li.product.minimal:hover .product-add-to-cart .loading  {
            cursor: not-allowed;
            opacity: 0.6!important;
          }
          .products li.product.minimal .product-add-to-cart .button i {
            display: none;
         }
        ';

      } // End default minimal button layout.

    } // End minimal product style.

    else if( 'material' === $product_style ) {
      // Border Radius
      if( $product_border_radius && 'default' !== $product_border_radius ) {
        echo '.woocommerce .material.product .product-wrap {
          border-radius: ' . $product_border_radius . ' ' . $product_border_radius . ' 0 0;
        }
         :root {
          --nectar-product-border-radius: ' . $product_border_radius . ';
        }
        .woocommerce .material.product,
        .woocommerce .material.product:before,
        .widget_shopping_cart ul.product_list_widget li img {
            border-radius: ' . $product_border_radius . ';
        }';
      }
    }

    else if( 'text_on_hover' === $product_style || 'classic' === $product_style  ) {
      // Border Radius
      if( $product_border_radius && 'default' !== $product_border_radius ) {
        echo '.product .product-wrap, .widget_shopping_cart ul.product_list_widget li img {
          border-radius: ' . $product_border_radius . ';
        }';
      }
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.12. WooCommerce Related/Upsell Carousel
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {
    $related_carousel = ( isset($nectar_options['single_product_related_upsell_carousel'] ) ) ? $nectar_options['single_product_related_upsell_carousel'] : '0';
    if( '1' === $related_carousel ) {
      echo '
      .related-upsell-carousel.nectar-woo-carousel section > h2 {
        display: flex;
        align-items: center;
      }';
    } else {
      echo '
      .woocommerce .products.related .products:before,
      .woocommerce .products.upsells .products:before {
        display: none;
      }
      .woocommerce .products.related .products,
      .woocommerce .products.upsells .products {
          gap: var(--nectar-product-gap, 15px);
          display: flex;
          flex-wrap: wrap;
          width: calc(100% + var(--nectar-product-gap, 15px));
      }

      .woocommerce #nectar-content-wrap .products.related .product,
      .woocommerce #nectar-content-wrap .products.upsells .product {
        margin: 0!important;
      }

      @media only screen and (min-width: 1025px) {
        .woocommerce .products.related .product,
        .woocommerce .products.upsells .product {
          width: calc(25% - var(--nectar-product-gap, 15px)) !important;
          margin: 0!important;
        }
      }


      .woocommerce .span_9 .products.related .products li:nth-child(4),
      .woocommerce .span_9 .products.upsells .products li:nth-child(4) {
      	display: none;
      }';
    }
  }

  /*-------------------------------------------------------------------------*/
  /* 18.13. WooCommerce lightbox Gallery Background
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {
    $woo_gallery_bg_color = ( isset($nectar_options['product_gallery_bg_color']) && ! empty($nectar_options['product_gallery_bg_color']) ) ? $nectar_options['product_gallery_bg_color'] : false;

    if( false !== $woo_gallery_bg_color ) {

        echo '.single-product .pswp__bg {
          background-color: ' . esc_attr($woo_gallery_bg_color) . ';
        }
        .single-product .pswp__caption__center {
          color: #fff;
        }
        .single-product .pswp__top-bar,
        .single-product .pswp__caption {
          background-color: transparent;
        }
        .single-product .pswp__button,
        .single-product .pswp__caption,
        .single-product .pswp__top-bar {
            mix-blend-mode: difference;
        }';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.14. WooCommerce Review Style
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $woo_review_style = ( isset($nectar_options['product_reviews_style']) && ! empty($nectar_options['product_reviews_style']) ) ? $nectar_options['product_reviews_style'] : 'default';

    if( 'off_canvas' === $woo_review_style ) {

        echo '
        .woocommerce-tabs #review_form_wrapper {
          display: none;
        }

        .woocommerce-tabs[data-tab-style] #tab-reviews #comments {
          display: flex;
          flex-wrap: wrap;
        }
        .woocommerce-tabs[data-tab-style="in_sidebar"] #tab-reviews #comments {
          flex-direction: column;
        }

        body > #review_form_wrapper.modal,
        body > #review_form_wrapper.modal #review_form {
            -webkit-transition: all .8s cubic-bezier(0.2,1,.3,1);
            transition: all .8s cubic-bezier(0.2,1,.3,1);
        }
        body > #review_form_wrapper.modal {
            position: fixed;
            height: 100%;
            right: 0;
            top: 0;
            z-index: 100000;
            -ms-transform: translateX(107%);
            transform: translateX(107%);
            -webkit-transform: translateX(107%);
            position: fixed;
            top: 0;
            overflow: hidden;
            background-color: ' . esc_attr($global_bg_color) . ';
            max-width: 80%;
        }

        body > #review_form_wrapper.modal #review_form {
            -webkit-transform: translateX(-107%);
            transform: translateX(-107%);
            height: 100%;
            padding: 40px;
            min-height: calc(100vh - 80px);
            overflow-y: auto;
        }
        body > #review_form_wrapper.modal #review_form::-webkit-scrollbar {
          display: none;
        }

        body.admin-bar > #review_form_wrapper.modal #review_form {
          padding-top: 70px;
        }

        body > #review_form_wrapper.modal.open,
        body > #review_form_wrapper.modal.open #review_form  {
            -webkit-transform: translateX(0);
            transform: translateX(0);
        }

        body > #review_form_wrapper.modal .comment-form {
          display: flex;
          flex-wrap: wrap;
        }

        body > #review_form_wrapper.modal #respond .comment-form > * {
          width: 100%;
          padding: 0 0 20px 0;
          margin: 0!important;
        }
        body > #review_form_wrapper.modal #respond .comment-form > * p {
          padding-bottom: 0;
          margin-bottom: 0;
        }
        body > #review_form_wrapper.modal #respond #reply-title {
          display: block;
          padding-right: 60px;
        }
        body.material > #review_form_wrapper.modal #respond input#submit {
          padding-left: 35px;
          padding-right: 35px;
        }
        body > #review_form_wrapper.modal .nectar-close-btn-wrap {
          position: absolute;
          right: 40px;
          top: 48px;
        }
        body.admin-bar > #review_form_wrapper.modal .nectar-close-btn-wrap {
          top: 78px;
        }
        @media only screen and (min-width: 691px) {
          body > #review_form_wrapper.modal #respond .comment-form > .comment-form-author {
            padding-right: 20px;
            width: 50%;
          }
          body > #review_form_wrapper.modal #respond .comment-form > .comment-form-email {
            padding-left: 20px;
            width: 50%;
          }
        }
        @media only screen and (min-width: 1025px) {
          body > #review_form_wrapper.modal {
            max-width: 50%;
          }
          body > #review_form_wrapper.modal textarea {
            height: 150px;
          }

        }


        @media only screen and (min-width: 1025px) {
          .woocommerce-tabs[data-tab-style] #reviews #comments ol.commentlist {
            padding-left: 7.5%;
            flex: 1;
          }
          .woocommerce-tabs:not([data-tab-style="in_sidebar"]) #reviews .woocommerce-Reviews-title {
            padding-right: 7.5%;
            width: auto;
            border-right: 1px solid rgba(0,0,0,0.1);
          }
        }
        .woocommerce [data-tab-pos="fullwidth_stacked"] .commentlist > li .comment_container {
          margin-top: 7.5%;
        }

        @media only screen and (min-width: 1350px) {

          .woocommerce-tabs[data-tab-style] #reviews #comments ol.commentlist {
            padding-left: 5.5%;
          }
          .woocommerce-tabs[data-tab-style] #reviews .woocommerce-Reviews-title {
            padding-right: 5.5%;
          }
          .woocommerce [data-tab-pos="fullwidth_stacked"] .commentlist > li .comment_container {
            margin-top: 5.5%;
          }

          .woocommerce-tabs[data-tab-style] #tab-reviews .woocommerce-pagination {
            padding-left: calc(160px + 5.5%);
            margin-top: 5.5%;
          }

        }

        .woocommerce-tabs[data-tab-style="fullwidth"] #tab-reviews > #reviews #comments {
          width: 100%;
          flex-direction: row;
        }
        .woocommerce-tabs[data-tab-style="fullwidth"] #reviews p.woocommerce-noreviews {
          border: none;
          align-items: center;
          padding: 0;
          justify-content: flex-start;
        }

        .woocommerce .woocommerce-tabs[data-tab-style] .commentlist li .comment_container {
          border: none;
        }

        .woocommerce-tabs[data-tab-style] #reviews .commentlist li  .star-rating {
          float: none;
        }

        .woocommerce-tabs[data-tab-style] #reviews #comments ol.commentlist li img.avatar {
          left: 0;
          top: 0;
        }

        .woocommerce-tabs[data-tab-style] #reviews #comments ol.commentlist li .comment-text .description p:last-child {
          padding-bottom: 0;
          margin-bottom: 0;
        }


        .woocommerce-tabs[data-tab-style] #reviews #comments ol.commentlist li .comment-text {
          margin-left: 90px;
          padding: 0;
          border-radius: 0;
          display: flex;
          flex-direction: column;
        }

        .woocommerce-tabs[data-tab-style] #comments li .comment-text .meta {
          padding-top: 10px;
        }

        .woocommerce-tabs[data-tab-style] .woocommerce-noreviews,
        .woocommerce-tabs[data-tab-style] .nectar-no-reviews {
          display: block;
          margin: 0 0 20px;
          min-width: 100%;
          padding-bottom: 0;
        }


        @media only screen and (min-width: 1025px) {
          .woocommerce-tabs[data-tab-style] #tab-reviews .woocommerce-pagination {
            padding-left: calc(160px + 7.5%);
            margin-top: 7.5%;
          }
        }


        .woocommerce-tabs[data-tab-style] #tab-reviews .woocommerce-pagination {
          width: 100%;
        }



        .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-average {
          line-height: 1.1;
        }
        .woocommerce-tabs #reviews .woocommerce-Reviews-title .star-rating {
          width: 133px;
          font-size: 22px;
          float: none;
        }

        .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-average-count {
          font-size: 50px;
          line-height: 1;
          margin-bottom: 30px;
          display: block;
        }
        .woocommerce-tabs[data-tab-style="fullwidth_stacked"] #reviews .star-rating {
          margin-bottom: 0;
        }

        .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-average-count-wrap .total-num {
          font-size: 14px;
        }
        .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-button {
          margin-top: 40px;
        }

        .woocommerce-verification-required {
          margin-top: 50px;
          padding: 15px;
          border: 1px solid rgba(0,0,0,0.1);
        }

        @media only screen and (max-width: 1024px) {


          .woocommerce-tabs[data-tab-style] #reviews .woocommerce-Reviews-title  {
            margin: 0 auto 40px auto;
            text-align: center;
          }
          .woocommerce-tabs[data-tab-style] #reviews .woocommerce-Reviews-title .star-rating {
            margin: 0 auto;
          }
          .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-average-count {
            margin-bottom: 15px;
          }
          .woocommerce-tabs #reviews .woocommerce-Reviews-title .nectar-button {
            margin-top: 25px;
          }
          .woocommerce .woocommerce-tabs[data-tab-style] .commentlist > li .comment_container {
            margin-top: 50px;
          }

        }


        ';

    } else {
      echo '.woocommerce-verification-required {
        margin-top: 15px;
      }';
    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.15. WooCommerce Variation Dropdown Style
  /*-------------------------------------------------------------------------*/
  if( function_exists( 'is_woocommerce' ) ) {

    $woo_variation_dropdown_style = ( isset($nectar_options['product_variable_select_style']) && ! empty($nectar_options['product_variable_select_style']) ) ? $nectar_options['product_variable_select_style'] : 'default';

    if( 'underline' === $woo_variation_dropdown_style ) {

      echo '
      .variations_form .select2-container--default .select2-selection__rendered,
      .variations_form .variations select {
      	background-repeat: no-repeat;
      	background-size: 100% 2px;
      	background-position: left bottom;
      	background-image: linear-gradient(to right, ' . esc_html($global_font_color) . ' 0%, ' . esc_html($global_font_color) . ' 100%);
      	transition: background-size 0.55s cubic-bezier(.2,.75,.5,1);
      }
      body[data-form-select-js="1"].woocommerce div.product form.cart .variations select {
         -webkit-appearance: none;
        -moz-appearance: none;
      }
      .variations_form .variations .select2-container .select2-selection--single .select2-selection__rendered {
        padding: 0 0 0 0;
      }
      .variations_form .variations .select2-container .select2-selection--single .select2-selection__arrow {
        right: -15px;
      }
      body .variations_form .variations select {
        padding: 5px 0;
        line-height: 1.2;
      }
      .variations_form .select2-container--default:hover .select2-selection__rendered,
      .variations_form .select2-container--default.select2-container--open .select2-selection__rendered,
      .variations_form .variations select:hover {
      	background-size: 0% 2px;
        background-position: right bottom;
      }

      .variations_form .variations .select2-container--default .select2-selection--single,
      .variations_form .variations .select2-container--default .select2-selection--single:hover,
      .variations_form .variations .select2-container--default.select2-container--open .select2-selection--single,
      .variations_form .variations select {
        color: var(--body-color, #000);
        background-color: transparent!important;
        border: none!important;
      }
      .variations_form .variations select {
        box-shadow: none;
      }
      .variations_form .variations .select2-container--default:hover .select2-selection--single .select2-selection__rendered,
      .variations_form .variations .select2-container--default.select2-container--open .select2-selection--single .select2-selection__rendered {
        color: inherit!important;
      }
      .variations_form .variations .select2-container--default .select2-selection--single .select2-selection__arrow {
        width: 12px;
      }
      .variations_form .variations .select2-container--default:hover .select2-selection--single .select2-selection__arrow b {
        border-top-color: inherit;
      }
      .variations_form .variations .select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-bottom-color: inherit!important;
      }
      body[data-form-select-js="1"] .variations_form.cart .variations .select2-container,
      body[data-form-select-js="1"].woocommerce div.product form.cart .variations select {
      	min-width: 0;
        margin-right: 0;
      }
      .woocommerce div.product .variations_form.cart .variations td.label {
        padding-bottom: 0;
      }
      .variations_form .variations .select2-results__option {
          line-height: 1.3;
      }

      body .variations_form .select2-dropdown {
        box-shadow: 0 6px 28px rgba(0,0,0,0.08);
      }
      ';

    }

  }

  /*-------------------------------------------------------------------------*/
  /* 18.16. MISC Third Party
  /*-------------------------------------------------------------------------*/

  /*-------------------------------------------------------------------------*/
  /* 18.17. Product Typography
  /*-------------------------------------------------------------------------*/
  $product_tab_typography = isset($nectar_options['product_tab_heading_typography']) ? $nectar_options['product_tab_heading_typography'] : '';
  if ( in_array($product_tab_typography, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) &&
  class_exists('Nectar\Global_Settings\Global_Typography') ) {

    $global_typography_options = get_option('nectar_global_typography');
    $selected_global_typography_option = '';
    if ( isset($global_typography_options['coreTypography'][$product_tab_typography]) ) {
      $selected_global_typography_option = $global_typography_options['coreTypography'][$product_tab_typography];
    }

    if ( is_array($selected_global_typography_option)) {

    $selectors = '#nectar-content-wrap .related.products > h2,
    #nectar-content-wrap .related.products .nectar-woo-carousel__top > h2,
			#nectar-content-wrap .upsells.products > h2,
      #nectar-content-wrap .upsells.products .nectar-woo-carousel__top > h2,
			#nectar-content-wrap #tab-reviews #reviews #comments h2,
			.woocommerce .woocommerce-tabs #tab-additional_information h2,
			#nectar-content-wrap #reviews #reply-title,
			#review_form_wrapper.modal .comment-reply-title,
			.woocommerce-tabs[data-tab-style="fullwidth_stacked"] p.woocommerce-noreviews';

      echo $selectors . ' {';
          echo Nectar\Global_Settings\Global_Typography::get_core_font_properties($selected_global_typography_option);
        echo '}';

      echo Nectar\Global_Settings\Global_Typography::get_font_size_rules($selected_global_typography_option, $selectors);
      echo Nectar\Global_Settings\Global_Typography::get_line_height_rules($selected_global_typography_option, $selectors);
    }
  }

  /*-------------------------------------------------------------------------*/
  /* 18.18. Product Layout
  /*-------------------------------------------------------------------------*/

  if ( 'bordered' === $product_archive_layout ) {
    echo 'html body {
      --nectar-product-gap: 1px;
      --nectar-product-border-radius: 0;
      --nectar-product-layout-border-color: #000;
    }
    body .products[data-product-style="minimal"] {
      width: 100%;
    }
    .archive.woocommerce .container-wrap > .main-content #sidebar {
      padding-top: var(--nectar-product-layout-gap, 25px);
      padding-left: var(--nectar-product-layout-gap, 25px);
      padding-right: var(--nectar-product-layout-gap, 25px);
      border-top: 1px solid var(--nectar-product-layout-border-color);
      border-left: 1px solid var(--nectar-product-layout-border-color);
      border-bottom: 1px solid var(--nectar-product-layout-border-color);
    }
    body .nectar-shop-header {
      margin-bottom: 0;
    }
    .archive.woocommerce .container-wrap > .main-content > .row {
      padding-bottom: 0;
    }
    body .nectar-shop-header .nectar-shop-header-bottom {
      border-top: 1px solid var(--nectar-product-layout-border-color);
      border-left: 1px solid var(--nectar-product-layout-border-color);
      border-right: 1px solid var(--nectar-product-layout-border-color);
      margin-bottom: 0;
      margin-top: var(--container-padding);
      padding: var(--nectar-product-layout-gap, 25px);
    }
    body .nectar-shop-header {
      margin-top: var(--container-padding);
      padding-top: 0;
    }
    .archive.woocommerce .container-wrap > .main-content > .row .post-area.span_9 {
      margin-top: 0;
    }
    .woocommerce.archive #nectar-content-wrap.container-wrap {
      padding-top: 0;
    }
    .woocommerce .products ul, .woocommerce ul.products {
       background-color: var(--nectar-product-layout-border-color);
       padding-left: 1px;
       padding-top: 1px;
    }

    .products li.product.minimal .product-wrap {
      border-radius: 0;
    }

   ';
  }

  /*-------------------------------------------------------------------------*/
  /* 18.19. Full Width Layout
  /*-------------------------------------------------------------------------*/
  $main_shop_layout_full_width = ( isset($nectar_options['main_shop_layout_full_width']) && '1' === $nectar_options['main_shop_layout_full_width'] ) ? true : false;
  if ( true === $main_shop_layout_full_width ) {
    echo '
    @media only screen and (min-width: 1025px) {
      .archive.woocommerce #nectar-content-wrap > .main-content,
      .archive.woocommerce #page-header-bg .container {
        margin-left: calc(-50vw + var(--scroll-bar-w)/2)!important;
        left: 50%;
        max-width: 100vw;
        width: calc(100vw - var(--scroll-bar-w));
      }
    }';
  }

  /*-------------------------------------------------------------------------*/
  /* 18.20. Button Styling
  /*-------------------------------------------------------------------------*/
  $woocommerce_button_typo = ( isset($nectar_options['woocommerce_button_typography']) ) ? $nectar_options['woocommerce_button_typography'] : 'default';

  if ( $woocommerce_button_typo === 'label' ) {
    echo '.wc-block-cart .wc-block-cart__submit-container .wc-block-components-button:not(.is-link),
          .wp-block-woocommerce-checkout .wc-block-components-checkout-place-order-button:not(.is-link),
          .woocommerce a.button,
          .woocommerce-page a.button,
          .woocommerce button.button,
          .woocommerce-page button.button,
          .woocommerce input.button,
          .nectar-quick-view-box a.button,
          .woocommerce-page input.button,
          .woocommerce #respond input#submit,
          .woocommerce-page #respond input#submit,
          .woocommerce #content input.button,
          .woocommerce-page #content input.button,
          .products li.product .nectar_quick_view,
          .nectar-quick-view-box .nectar-full-product-link a,
          .nectar-product-reviews-trigger {
        font-family: var(--label-font-family, inherit);
        font-style: var(--label-font-style, inherit);
        font-weight: var(--label-font-weight, inherit);
        letter-spacing: var(--label-letter-spacing, inherit);
        text-transform: var(--label-text-transform, inherit);
      }';
  }

?>
