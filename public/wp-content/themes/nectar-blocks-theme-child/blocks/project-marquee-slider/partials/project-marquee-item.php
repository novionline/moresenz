<?php

use NoviOnline\ProjectMarqueeSliderBlock;
use NoviOnline\ProjectPostType;
use NoviOnline\Theme;

/**
 * @var WP_Post $postItem
 * @var bool $is_preview
 */

$postId = $postItem->ID;
$postTitle = get_the_title($postId);
$permalink = get_permalink($postId);
$hoverType = ProjectPostType::getCardHoverTypeByPostId($postId);
$hasVideoHover = $hoverType === ProjectPostType::HOVER_TYPE_VIDEO;
$hasImageHover = $hoverType === ProjectPostType::HOVER_TYPE_IMAGE;
$videoUrl = $hasVideoHover ? ProjectPostType::getVideoUrlByPostId($postId) : '';
$videoMimeType = $hasVideoHover ? ProjectPostType::getVideoMimeTypeByPostId($postId) : 'video/mp4';
$hoverImageId = $hasImageHover ? ProjectPostType::getHoverImageIdByPostId($postId) : 0;
$hasThumbnail = has_post_thumbnail($postId);
$isWatermarkOnly = !$hasThumbnail && $hoverType === ProjectPostType::HOVER_TYPE_NONE;
$ariaLabel = sprintf(
    /* translators: %s: project title */
    __('View project: %s', Theme::TEXT_DOMAIN),
    $postTitle
);
?>

<div class="project-marquee-slider__slide">
    <article class="project-marquee-slider__item">
        <a href="<?php echo esc_url($permalink); ?>"
           class="project-marquee-slider__link<?php echo $hasVideoHover ? ' project-marquee-slider__link--has-video' : ''; ?><?php echo $hasImageHover ? ' project-marquee-slider__link--has-hover-image' : ''; ?><?php echo $isWatermarkOnly ? ' project-marquee-slider__link--watermark-only' : ''; ?><?php echo $is_preview ? ' project-marquee-slider__link--preview' : ''; ?>"
           draggable="false"
           <?php echo $is_preview ? 'onclick="return false"' : ''; ?>
           aria-label="<?php echo esc_attr($ariaLabel); ?>">

            <figure class="project-marquee-slider__media">
                <?php if ($hasThumbnail): ?>
                    <?php echo get_the_post_thumbnail($postId, ProjectMarqueeSliderBlock::IMAGE_SIZE, [
                        'class' => 'project-marquee-slider__image',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'draggable' => 'false',
                        'alt' => $postTitle,
                    ]); ?>
                <?php else: ?>
                    <motion.div class="project-marquee-slider__watermark" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="47" height="32" viewBox="0 0 47 32" fill="none" focusable="false">
                            <path fill="#fff" d="M38.047 17.404 23.504 32 12.362 20.825 0 8.413l1.58-3.216 1.91-3.899L4.104 0h38.77c.167.283.29.554.412.847l1.774 3.609L47 8.424zM34.089 1.61H13.286l10.4 16.57zm10.075 4.943-1.878-3.828-.538-1.116-5.626.003.001 15.34 8.79-8.85zM2.094 8.1l8.79 8.851.002-15.279-5.662-.001-.85 1.752L2.52 7.19l-.426.909m32.253 10.64L34.33 4.454 23.686 21.36 12.676 3.866l-.01 14.881 10.838 10.907z"/>
                        </svg>
                    </motion.div>
                <?php endif; ?>

                <?php if ($hasVideoHover && $videoUrl !== ''): ?>
                    <video class="project-marquee-slider__video"
                           data-src="<?php echo esc_url($videoUrl); ?>"
                           muted
                           loop
                           playsinline
                           preload="none"
                           aria-hidden="true">
                        <source data-src="<?php echo esc_url($videoUrl); ?>" type="<?php echo esc_attr($videoMimeType); ?>">
                    </video>
                <?php endif; ?>

                <?php if ($hasImageHover && $hoverImageId > 0): ?>
                    <?php echo wp_get_attachment_image($hoverImageId, ProjectMarqueeSliderBlock::IMAGE_SIZE, false, [
                        'class' => 'project-marquee-slider__hover-image',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'draggable' => 'false',
                        'alt' => '',
                        'aria-hidden' => 'true',
                    ]); ?>
                <?php endif; ?>
            </figure>

            <span class="project-marquee-slider__arrow" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" focusable="false">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 12h16m-4 4 4-4-4-4"/>
                </svg>
            </span>
        </a>
    </article>
</div>
