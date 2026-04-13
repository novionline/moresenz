<?php

namespace Nectar\Menu_Options;

class Style_Manager {  
     private static $instance;

     public static $upload_dir;

     public static $upload_url;

     public static $theme_options_name = 'Salient';

     private function __construct() {

       if( is_admin() ) {

         $theme = wp_get_theme();

         if( $theme->exists() ) {
           self::$theme_options_name = sanitize_html_class( $theme->get( 'Name' ) );
         }

       }

       $this->set_content_locations();
       $this->actions();

     }

     /**
      * Stores the WP uploads dir and
      * destination for menu css file.
      *
      * @since 1.8
      */
     public function set_content_locations() {

       $upload_dir = wp_upload_dir();

       self::$upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'nectarblocks/';
       self::$upload_url = trailingslashit( $upload_dir['baseurl'] ) . 'nectarblocks/';

     }

     /**
      * Adds WP actions.
      *
      * @since 1.8
      */
     public function actions() {

       add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_css' ] );

     }

     /**
      * Creates the dyanmic styles for each
      * menu location passed in.
      *
      * @since 1.8
      */
     public static function menu_dynamic_css($menu_object) {

       if( defined( 'NECTAR_THEME_NAME' ) && function_exists('get_nectar_theme_options') ) {
         $nectar_options = get_nectar_theme_options();
       } else {
         $nectar_options = [
           'accent-color' => '#000',
           'extra-color-1' => '#000',
           'extra-color-gradient' => '#000',
           'header-slide-out-widget-area-style' => 'slide-out-from-right',
           'header-dropdown-hover-effect' => 'color_change',
           'header_format' => 'default',
           'header-hover-effect-button-bg-size' => 'default',
           'header-hover-effect' => 'animated_underline',
           'header-slide-out-widget-area-image-display' => 'default',
           'use-logo' => 'false',
           'mobile-logo-height' => '28'
         ];
       }

  		 $menu_items = wp_get_nav_menu_items($menu_object);

  		 $menu_item_css = '';

       // No menu items found.
       if( false === $menu_items ) {
         return $menu_item_css;
       }

  		 foreach( $menu_items as $item ) {

         if( ! isset($item->ID) ) {
           continue;
         }

         $menu_item_options = maybe_unserialize( get_post_meta( $item->ID, 'nectar_menu_options', true ) );

         // Menu item has nectar options saved.
         if( $menu_item_options && ! empty($menu_item_options) ) {

         // Icon Sizing.
         if( isset($menu_item_options['menu_item_icon_size']) &&
             ! empty($menu_item_options['menu_item_icon_size']) ) {

             $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon,
             #slide-out-widget-area li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon {
               font-size: ' . intval($menu_item_options['menu_item_icon_size']) . 'px;
               line-height: 1;
             }
             #nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon.svg-icon svg,
             #slide-out-widget-area li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon.svg-icon svg{
              height: ' . intval($menu_item_options['menu_item_icon_size']) . 'px;
              width: ' . intval($menu_item_options['menu_item_icon_size']) . 'px;
            }';

             $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img,
             #nectar-nav #header-secondary-outer li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img,
             #slide-out-widget-area li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img {
               width: ' . intval($menu_item_options['menu_item_icon_size']) . 'px;
             }';

           }

           // Icon Border Radius.
           if( isset($menu_item_options['menu_item_icon_custom_border_radius']) &&
               ! empty($menu_item_options['menu_item_icon_custom_border_radius']) ) {
                 $menu_item_css .= 'li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img {
                   border-radius: ' . intval($menu_item_options['menu_item_icon_custom_border_radius']) . 'px;
                 }';
           }

           // Coloring.

           //// Parent Only
           if( ! $item->menu_item_parent ) {

                $button_text_color = ( isset($menu_item_options['menu_item_link_button_color_text']) && ! empty($menu_item_options['menu_item_link_button_color_text']) ) ? $menu_item_options['menu_item_link_button_color_text'] : false;
                $button_bg_color = ( isset($menu_item_options['menu_item_link_button_color']) && ! empty($menu_item_options['menu_item_link_button_color']) ) ? $menu_item_options['menu_item_link_button_color'] : false;
                $button_bg_color_hover = ( isset($menu_item_options['menu_item_link_button_color_hover']) && ! empty($menu_item_options['menu_item_link_button_color_hover']) ) ? $menu_item_options['menu_item_link_button_color_hover'] : false;
                $button_text_color_hover = ( isset($menu_item_options['menu_item_link_button_color_text_hover']) && ! empty($menu_item_options['menu_item_link_button_color_text_hover']) ) ? $menu_item_options['menu_item_link_button_color_text_hover'] : false;

                $button_color_border = ( isset($menu_item_options['menu_item_link_button_color_border']) && ! empty($menu_item_options['menu_item_link_button_color_border']) ) ? $menu_item_options['menu_item_link_button_color_border'] : false;
                $button_color_border_text = ( isset($menu_item_options['menu_item_link_button_color_border_text']) && ! empty($menu_item_options['menu_item_link_button_color_border_text']) ) ? $menu_item_options['menu_item_link_button_color_border_text'] : false;
                $button_color_border_hover = ( isset($menu_item_options['menu_item_link_button_color_border_hover']) && ! empty($menu_item_options['menu_item_link_button_color_border_hover']) ) ? $menu_item_options['menu_item_link_button_color_border_hover'] : false;

                $menu_item_link_link_style = ( isset($menu_item_options['menu_item_link_link_style']) && ! empty($menu_item_options['menu_item_link_link_style']) ) ? $menu_item_options['menu_item_link_link_style'] : false;

                // solid button
                if ( 'regular' === $menu_item_link_link_style ) {

                  if ( $button_bg_color ) {
                    $menu_item_css .= '#nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a:before {
                      background-color: ' . esc_attr($button_bg_color) . ';
                    }';
                  }

                  if ( $button_bg_color_hover ) {
                    $menu_item_css .= '#nectar-nav li.menu-item-' . esc_attr($item->ID) . '[class*="current"] > a:before,
                    #nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a:hover:before {
                      background-color: ' . esc_attr($button_bg_color_hover) . ';
                    }';
                  }

                  if ( $button_text_color ) {
                    $menu_item_css .= '
                    #nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a > .menu-title-text {
                      color: ' . esc_attr($button_text_color) . ';
                      transition: color 0.25s ease;
                    }';
                  }

                  if ( $button_text_color_hover ) {
                    $menu_item_css .= '
                    #nectar-nav li.menu-item-' . esc_attr($item->ID) . '[class*="current"] > a > .menu-title-text,
                    #nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a:hover > .menu-title-text {
                      color: ' . esc_attr($button_text_color_hover) . ';
                    }';
                  }

                }

                // border button
                if ( 'border' === $menu_item_link_link_style ) {

                  if ( $button_color_border ) {
                    $menu_item_css .= '#nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a:before {
                      border: 1px solid ' . esc_attr($button_color_border) . ';
                    }';
                  }

                  if ( $button_color_border_text ) {
                    $menu_item_css .= '#nectar-nav:not(.transparent) li.menu-item-' . esc_attr($item->ID) . ' > a .menu-title-text {
                      color: ' . esc_attr($button_color_border_text) . ';
                    }';
                  }

                  if ( $button_color_border_hover ) {
                    $menu_item_css .= '#nectar-nav li.menu-item-' . esc_attr($item->ID) . ' > a:hover:before,
                    #nectar-nav li.menu-item-' . esc_attr($item->ID) . '[class*="current"] > a:before {
                      border-color: ' . esc_attr($button_color_border_hover ) . ';
                    }';
                  }

                } 

              // Button Styling.
              if( isset($menu_item_options['menu_item_link_link_style']) && 'default' !== $menu_item_options['menu_item_link_link_style']) {

                $mobile_logo_height = (! empty($nectar_options['use-logo']) && ! empty($nectar_options['mobile-logo-height'])) ? intval($nectar_options['mobile-logo-height']) : 24;
                $mobile_padding_mod = ( $mobile_logo_height < 38 ) ? 10 : 0;

                $underscore_pos = strrpos($menu_item_options['menu_item_link_link_style'], "_");

                $button_color_var = substr($menu_item_options['menu_item_link_link_style'], $underscore_pos + 1);

                $button_coloring = '#000';
                if( in_array($button_color_var, ['accent-color','extra-color-1','extra-color-gradient']) && isset($nectar_options[$button_color_var]) ) {

                  $button_coloring = $nectar_options[$button_color_var];

                  // Gradient.
                  if( is_array($button_coloring) && isset($button_coloring['to']) ) {

                    if( in_array($menu_item_options['menu_item_link_link_style'], ['button-animated_extra-color-gradient','button-border-animated_extra-color-gradient','button-border-white-animated_extra-color-gradient']) ) {
                      $button_coloring = 'linear-gradient(90deg, ' . $button_coloring['to'] . ', ' . $button_coloring['from'] . ', ' . $button_coloring['to'] . ')';
                    } else {
                      $button_coloring = 'linear-gradient(90deg, ' . $button_coloring['to'] . ', ' . $button_coloring['from'] . ')';
                    }

                  }

                }

                // Button Core.
                $button_padding = 'var(--nectar-nav-button-size, 24px)';
                $button_padding_w = 'calc(var(--nectar-nav-button-size, 24px) * 2)';

                if( 'button_bg' === $nectar_options['header-hover-effect'] && isset($nectar_options['header-hover-effect-button-bg-size']) ) {
                  if( 'small' === $nectar_options['header-hover-effect-button-bg-size'] ) {
                    $button_padding = '14px';
                    $button_padding_w = '28px';
                  }
                  else if ( 'medium' === $nectar_options['header-hover-effect-button-bg-size'] ) {
                    $button_padding = '18px';
                    $button_padding_w = '36px';
                  }
                }

                $menu_item_css .= '
                @media only screen and (max-width: 1024px) {
 

                  body[data-button-style^="rounded"] #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a {
                    border-left-width: 15px;
                    border-right-width: 15px;
                  }
                  body[data-button-style^="rounded"] #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:before,
                  body[data-button-style^="rounded"] #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:after {
                    left: -15px;
                    width: calc(100% + 30px);
                  }
                }

                @media only screen and (min-width: 1000px) {
                  body #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a {
                    border-left-width: ' . $button_padding . ';
                    border-right-width: ' . $button_padding . ';
                  }
                  body #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a {
                    border-left: 12px solid transparent;
                    border-right: 12px solid transparent;
                  }
               
                  body #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:before,
                  body #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:after {
                    left: calc(-1 * ' . $button_padding . ');
                    height: calc(100% + ' . $button_padding . ');
                    width: calc(100% + ' . $button_padding_w . ');
                  }

                  #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a:before,
                  #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a:after {
                    left: -12px;
                    width: calc(100% + 24px);
                  }
                }

                #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a {
                  border: 12px solid transparent;
                  opacity: 1!important;
                }

               
 
                #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a {
                  border-top: 0;
                  border-bottom: 0;
                }

                #nectar-nav #top li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon {
                  transition: none;
                }
                
                body #nectar-nav[data-has-menu][data-format] header#top nav ul.sf-menu li.menu-item.menu-item-' . esc_attr($item->ID) . '[class*="menu-item-btn-style"] > a *:not(.char),
                body #nectar-nav[data-has-menu][data-format] header#top nav ul.sf-menu li.menu-item.menu-item-' . esc_attr($item->ID) . '[class*="menu-item-btn-style"] > a:hover *:not(.char) {
                  opacity: 1;
                }';

                $menu_item_css .= '
                #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:before,
                #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:after {
                  position: absolute;
                  top: 50%!important;
                  left: -12px;
                  width: calc(100% + 24px);
                  height: calc(100% + 18px);
                  content: "";
                  display: block;
                  z-index: -1;
                  transform-origin: top;
                  transform: translateY(-50%)!important;
                  transition: opacity .45s cubic-bezier(0.25, 1, 0.33, 1), transform .45s cubic-bezier(0.25, 1, 0.33, 1), border-color .45s cubic-bezier(0.25, 1, 0.33, 1), color .45s cubic-bezier(0.25, 1, 0.33, 1), background-color .45s cubic-bezier(0.25, 1, 0.33, 1), box-shadow .45s cubic-bezier(0.25, 1, 0.33, 1);
                }

                #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a:after,
                #nectar-nav #header-secondary-outer .menu-item-' . esc_attr($item->ID) . ' > a:before {
                  height: calc(100% + 12px);
                }

                #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a:after {
                  opacity: 0;
                  transition: opacity 0.3s ease, transform 0.3s ease;
                }

                #nectar-nav .menu-item-' . esc_attr($item->ID) . ' > a .menu-title-text:after {
                  display: none!important;
                }
               ';

              }

           }

    			 ////////// Dropdown only items.
    			 if( $item->menu_item_parent ) {

               $mobile_menu_style = ( isset($nectar_options['header-slide-out-widget-area-style']) ) ? $nectar_options['header-slide-out-widget-area-style'] : '#slide-out-widget-area';
    					 $mobile_menu_id = ( 'simple' === $mobile_menu_style ) ? '#mobile-menu' : '#slide-out-widget-area';

                 // Icon Alignment.
                 $icon_margin_target = 'right';

                 if( isset($menu_item_options['menu_item_icon_position']) &&
                     ! empty($menu_item_options['menu_item_icon_position']) &&
                     'above' === $menu_item_options['menu_item_icon_position'] ) {

                    $icon_margin_target = 'bottom';

                     $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon,
                     #nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img {
                       top: 0;
                       display: block;
                       margin: 0 0 5px 0;
                       text-align: inherit;
                     }
                     #nectar-nav header li li.menu-item-' . esc_attr($item->ID) . ' > a {
                       display: inline-block;
                     }';

                     // When the item is in a dropdown, we need to handle the alignment.
                     if( isset($menu_item_options['menu_item_link_content_alignment']) &&
                        ! empty($menu_item_options['menu_item_link_content_alignment']) ) {

                         $alignment = $menu_item_options['menu_item_link_content_alignment'];

                         if( strpos($alignment, '-center') !== false ) {
                           $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-ext-menu-item .nectar-menu-icon-img {
                             margin: 0 auto 5px auto;
                           }';
                         }
                         else if( strpos($alignment, '-right') !== false ) {
                           $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-ext-menu-item .nectar-menu-icon-img {
                             margin-left: auto;
                           }';
                         }

                       }
                 }

                 // Icon Spacing.
                 if( isset($menu_item_options['menu_item_icon_spacing']) &&
                     ! empty($menu_item_options['menu_item_icon_spacing']) ) {

                   $menu_item_css .= '#nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon,
                   #nectar-nav header li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img,
                   #header-secondary-outer li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon,
                   #header-secondary-outer li.menu-item-' . esc_attr($item->ID) . ' > a .nectar-menu-icon-img {
                     margin-' . esc_attr($icon_margin_target) . ': ' . intval($menu_item_options['menu_item_icon_spacing']) . 'px;
                   }';

                 }

            } // dropdown only.

  			 } // nectar menu options are set.

  		 } // menu item loop.

  		 return $menu_item_css;

     }

     /**
      * Loops through all menu locations
      * and gathers the needed CSS for each.
      *
      * @since 1.8
      */
      public static function generate_all_css() {

        $css = '';
        $locations = get_nav_menu_locations();
        $stored_locations = [];

        // Dynamic CSS.
        if( $locations && ! empty($locations) ) {

          foreach ($locations as $location => $id ) {

            if( $id && ! isset($stored_locations[$id]) ) {
              $css .= self::menu_dynamic_css($id);
              $stored_locations[$id] = true;
            }

          }

        }

        return self::minify_css( $css );

      }

     /**
      * Enqueues the dynamic CSS on the front.
      *
      * @since 1.8
      */
     public static function enqueue_css() {

      // Fallback to internal css.
      $css = self::generate_all_css();

      if( ! empty($css) ) {

        $css = self::minify_css($css);

        wp_register_style( 'nectar-blocks-wp-menu-dynamic-fallback', false );
        wp_enqueue_style( 'nectar-blocks-wp-menu-dynamic-fallback' );
        wp_add_inline_style( 'nectar-blocks-wp-menu-dynamic-fallback', $css );

      }

     }

     /**
      * Quick minify for CSS
      *
      * @since 1.8
      */
     public static function minify_css( $css ) {

       	$css = preg_replace( '/\s+/', ' ', $css );

       	$css = preg_replace( '/\/\*[^\!](.*?)\*\//', '', $css );

       	$css = preg_replace( '/(,|:|;|\{|}) /', '$1', $css );

       	$css = preg_replace( '/ (,|;|\{|})/', '$1', $css );

       	$css = preg_replace( '/(:| )0\.([0-9]+)(%|em|ex|px|in|cm|mm|pt|pc)/i', '${1}.${2}${3}', $css );

       	$css = preg_replace( '/(:| )(\.?)0(%|em|ex|px|in|cm|mm|pt|pc)/i', '${1}0', $css );

       	return trim( $css );

     }

     /**
      * Initiator.
      */
     public static function get_instance() {
       if ( ! self::$instance ) {
         self::$instance = new self;
       }
       return self::$instance;
     }
   }

