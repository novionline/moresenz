<?php
/**
 * NectarBlocks WooCommerce Quickview
 *
 * @package Nectar Blocks Theme
 * @version 10.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Quickview option.
 *
 * @since 9.0
 */
if( ! class_exists('Nectar_Woo_Quickview') ) {

    class Nectar_Woo_Quickview {
      function __construct() {

          add_action( 'wp_ajax_nectar_woo_get_product', [$this,'nectar_woo_get_product_info'] );
          add_action( 'wp_ajax_nopriv_nectar_woo_get_product', [$this,'nectar_woo_get_product_info'] );
          add_action( 'nectar_woocommerce_before_add_to_cart', [$this,'nectar_woo_add_quick_view_button'] );
          add_action( 'wp_enqueue_scripts', [$this,'enqueue_scripts']);
                add_action( 'wp_enqueue_scripts', [$this,'enqueue_scripts_late'], 40);
          add_action( 'wp_footer', [$this, 'nectar_quick_view_markup']);

          $this->nectar_add_template_actions();
      }

      public function enqueue_scripts() {

        wp_register_script('nectar_woo_quick_view_js', get_template_directory_uri() . '/nectar/woo/js/quick_view_actions.js', ['jquery'], '1.1', true);
        wp_enqueue_script('nectar_woo_quick_view_js');
        wp_enqueue_script( 'swiper' );
        wp_enqueue_style('nectar-blocks-swiper');
      }

        // Variation script should always be near bottom for third party compat.
        public function enqueue_scripts_late() {
        wp_enqueue_script( 'wc-add-to-cart-variation' );
      }

      public function nectar_woo_add_quick_view_button() {

        global $nectar_options;
            global $post;

        $product_style = (! empty($nectar_options['product_style'])) ? $nectar_options['product_style'] : 'classic';
        $button_class = ($product_style === 'classic') ? 'button' : '';
        $button_icon = ($product_style !== 'material') ? '<i class="normal icon-nectar-blocks-m-eye"></i>' : '';
        $get_product = wc_get_product( $post->ID );

        if($get_product->is_type( 'grouped' ) || $get_product->is_type( 'external' ) ) {
          return;
        }

        if ( $product_style !== 'minimal' ) {
          echo '<a class="nectar_quick_view no-ajaxy ' . $button_class . '" data-product-id="' . $post->ID . '"> ' . $button_icon . '
	    <span>' . esc_html__('Quick View', 'nectar-blocks-theme') . '</span></a>';
        } else {
          echo '<a class="nectar_quick_view no-ajaxy ' . $button_class . '" data-product-id="' . $post->ID . '"> ' . $button_icon . '
	      <span class="nectar-text-reveal-button"><span class="nectar-text-reveal-button__text" data-text="' . esc_attr( esc_html__('Quick View', 'nectar-blocks-theme')) . '">' . esc_html__('Quick View', 'nectar-blocks-theme') . '</span></span></a>';
        }

        }

      public function nectar_quick_view_markup() {

        global $nectar_options;
        $quick_view_sizing = 'cropped';

            echo '<div class="nectar-quick-view-box-backdrop"></div>
	    <div class="nectar-quick-view-box nectar-modal" data-image-sizing="' . $quick_view_sizing . '">
	    <div class="inner-wrap">
	    
	    <div class="close" role="button">
	      <a href="#" class="no-ajaxy">
	        <span class="close-wrap"><span class="screen-reader-text">' . __('Close Quick View', 'nectar-blocks-theme') . '</span><span class="close-line close-line1"></span> <span class="close-line close-line2"></span> </span>		     	
	      </a>
	    </div>
	        
	        <div class="product-loading">
	          <span class="dot"></span>
	          <span class="dot"></span>
	          <span class="dot"></span>
	        </div>
	        
	        <div class="preview_image"></div>
	        
			    <div class="inner-content">
	        
	          <div class="product">  
	             <div class="product type-product"> 
	                  
	                  <div class="woocommerce-product-gallery">
	                  </div>
	                  
	                  <div class="summary entry-summary scrollable">
	                     <div class="summary-content">   
	                     </div>
	                  </div>
	                  
	             </div>
	          </div>
	          
	        </div>
	      </div>
			</div>';

        }

      public function nectar_add_template_actions() {

        add_action('nectar_quick_view_summary_content', 'woocommerce_template_single_title');
        add_action('nectar_quick_view_summary_content', 'woocommerce_template_single_rating');
        add_action('nectar_quick_view_summary_content', 'woocommerce_template_single_price');
        add_action('nectar_quick_view_summary_content', 'woocommerce_template_single_excerpt');
        add_action('nectar_quick_view_summary_content', 'woocommerce_template_single_add_to_cart');
        add_action('nectar_quick_view_sale_content', 'woocommerce_show_product_sale_flash');

      }

      public function nectar_woo_get_product_info() {

            global $woocommerce;
        global $post;

            $product_id = intval($_POST['product_id']);

            if( intval($product_id) ) {

             wp('p=' . $product_id . '&post_type=product');

           ob_start();

                while ( have_posts() ) : the_post(); ?>
          
                <script>
              var wc_add_to_cart_variation_params = {};     
                </script>
            
                <div class="product">  
                
                        <div itemscope id="product-<?php the_ID(); ?>" <?php post_class('product'); ?> >  
                      
                              <?php

                            do_action('nectar_quick_view_sale_content');

                             global $product;
                             if ( has_post_thumbnail() ) {
                              $product_attach_ids = $product->get_gallery_image_ids();
                              ?>
                              <div class="images"> 
                              <div class="nectar-product-slider swiper nb-swiper" data-multi="<?php echo ($product_attach_ids) ? 'true' : 'false'; ?>">
                              <div class="swiper-wrapper">
                                 
                               <div class="swiper-slide carousel-cell woocommerce-product-gallery__image">
                                    <a href="#">
                                        <?php echo get_the_post_thumbnail( $post->ID, 'large'); ?>
                                    </a>
                               </div>
                               
                               <?php

                                if ( $product_attach_ids ) {

                                            foreach ($product_attach_ids as $product_attach_id) {

                                                $img_link = wp_get_attachment_url( $product_attach_id );

                                                if (! $img_link)
                                                    continue;

                                                printf( '<div class="swiper-slide carousel-cell woocommerce-product-gallery__image"><a href="%s" title="%s"> %s </a></div>', wp_get_attachment_url($product_attach_id), esc_attr( get_post($product_attach_id)->post_title ), wp_get_attachment_image($product_attach_id, 'large'));

                                            }// foreach

                                        } //if attach ids

                                echo '</div>    
                                <div class="swiper-pagination"></div>
                          </div> <!--nectar-product-slider--> </div>';

                             } else { ?>
                               <div class="images">
                                    <div class="nectar-product-slider swiper nb-swiper" data-multi="false">
                                      <div class="swiper-wrapper">
                                        <div class="swiper-slide carousel-cell woocommerce-product-gallery__image">
                                          <?php printf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'woocommerce' ) ); ?>
                                          </div>
                                      </div>
                                      
                                    </div>
                               </div>
                             <?php }

                             ?>
                             
                        
                                <div class="summary entry-summary scrollable">
                                        <div class="summary-content">   
                                           <?php

                                           echo '<div class="nectar-full-product-link"><a class="nectar-button" href="' . esc_url(get_permalink()) . '"><span>' . esc_html__('More Information', 'nectar-blocks-theme') . '</span></a></div>';
                                           do_action('nectar_quick_view_summary_content');

                                          ?>
                                        </div>
                                </div>
                              
                        </div> 
                </div>
               
                <?php endwhile;

                echo ob_get_clean();

                exit();

            }
        }
    }

}

$nectar_quick_view = new Nectar_Woo_Quickview();

?>