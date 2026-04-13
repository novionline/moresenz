<?php
/**
* The template for global sections.
*
* @package Nectar Blocks Theme
* @version 1.0
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

?>
<div id="nectar-content-wrap" class="container-wrap">
    <div class="container main-content">
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
</div>
<?php get_footer(); ?>
