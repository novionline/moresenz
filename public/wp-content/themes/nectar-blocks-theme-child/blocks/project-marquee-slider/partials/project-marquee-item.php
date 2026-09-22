<?php

use NoviOnline\Core\Partial;

/**
 * @var WP_Post $postItem
 * @var bool $is_preview
 */
?>

<div class="project-marquee-slider__slide">
    <?php Partial::render('project-card', [
        'postItem' => $postItem,
        'is_preview' => $is_preview,
    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>
</div>
