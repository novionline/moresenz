<?php
/*template name: No Header */
?>

<!DOCTYPE html>

<html <?php language_attributes(); ?> class="no-js">
<head>
    
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    
    <?php
    $nectar_options = get_nectar_theme_options();

    nectar_meta_viewport();

    wp_head();

    ?>
    
</head>

<?php

global $post;
global $woocommerce;
$nectar_header_options = nectar_get_header_variables();

?>

<body <?php body_class(); ?> <?php nectar_body_attributes(); ?>>
    
    <?php

    nectar_hook_after_body_open();
    nectar_hook_before_header_nav();

    ?>
    
    <div id="nectar-nav" <?php nectar_header_nav_attributes(); ?>> 
        <header id="top"> <div class="span_3"></div><div class="span_9"></div> </header> 
    </div>
    

        <?php

        nectar_hook_after_outer_wrap_open();

        nectar_page_header($post->ID);

        $nectar_fp_options = nectar_get_full_page_options();

        ?>
        
        <div id="nectar-content-wrap" class="container-wrap">
            <div class="<?php if ( $nectar_fp_options['page_full_screen_rows'] !== 'on' ) { echo 'container'; } ?> main-content">
                    <?php

                    nectar_hook_before_content();

                    if ( have_posts() ) :
                        while ( have_posts() ) :

                            the_post();
                            the_content();

                        endwhile;
                    endif;

                    nectar_hook_after_content();

                    ?>
                
            </div>
            <?php nectar_hook_before_container_wrap_close(); ?>
        </div>
        
        <?php get_footer(); ?>