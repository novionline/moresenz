<?php
/**
 * Header search template
 *
 * @package Nectar WordPress Theme
 * @subpackage Includes
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_options = get_nectar_theme_options();
$header_search_enabled = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? false : true;

if( ! $header_search_enabled ) {
    return;
}

if ( ! empty( $nectar_options['header-disable-ajax-search'] ) && '1' === $nectar_options['header-disable-ajax-search'] ) {
    $ajax_search = 'no';
} else {
    $ajax_search = 'yes';
}

?>

<div id="search-outer" class="nectar">
    <div id="search">
        <div class="container">
             <div id="search-box">
                 <div class="inner-wrap">
                     <div class="col span_12">
                          <form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="GET">
                            <?php
                            $placeholder_text = esc_attr__( 'Search', 'nectar-blocks-theme' ) . '...';

                            if( isset($nectar_options['header-search-ph-text']) && strlen($nectar_options['header-search-ph-text']) > 2 ) {
                                $placeholder_text = $nectar_options['header-search-ph-text'];
                            } ?>
                            
                            <input type="text" name="s" <?php if ( 'yes' === $ajax_search ) { echo 'id="s"'; } ?> value="" aria-label="<?php echo esc_attr__( 'Search', 'nectar-blocks-theme' ); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" />
                        
                        <?php
                        // Limit post type
                        $post_types_list = ['post','product','portfolio'];

                        if( isset($nectar_options['header-search-limit']) && in_array($nectar_options['header-search-limit'], $post_types_list) ) {
                            echo '<input type="hidden" name="post_type" value="' . esc_attr($nectar_options['header-search-limit']) . '">';
                        }
                        ?>
                        </form>
                    </div>
                </div>
             </div>
             <div id="close"><a href="#" role="button"><span class="screen-reader-text"><?php echo esc_html__('Close Search', 'nectar-blocks-theme'); ?></span>
                <?php
                echo '<span class="close-wrap"> <span class="close-line close-line1"></span> <span class="close-line close-line2"></span> </span>';
                ?>
                 </a></div>
         </div>
    </div>
</div>
