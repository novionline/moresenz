<?php

use NoviOnline\ProjectMarqueeSliderBlock;
use NoviOnline\ProjectPostType;
use NoviOnline\Theme;

/**
 * @var WP_Post $postItem
 * @var bool $is_preview
 * @var int $fallbackLogoId
 */

$postId = $postItem->ID;
$postTitle = get_the_title($postId);
$permalink = get_permalink($postId);
$videoUrl = ProjectPostType::getVideoUrlByPostId($postId);
$hasVideo = $videoUrl !== '';
$hasThumbnail = has_post_thumbnail($postId);
$ariaLabel = sprintf(
    /* translators: %s: project title */
    __('View project: %s', Theme::TEXT_DOMAIN),
    $postTitle
);
?>

<div class="project-marquee-slider__slide">
    <article class="project-marquee-slider__item">
        <a href="<?php echo esc_url($permalink); ?>"
           class="project-marquee-slider__link<?php echo $hasVideo ? ' project-marquee-slider__link--has-video' : ''; ?><?php echo $is_preview ? ' project-marquee-slider__link--preview' : ''; ?>"
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
                    <motion.div class="project-marquee-slider__fallback" aria-hidden="true">
                        <?php if ($fallbackLogoId > 0): ?>
                            <?php echo wp_get_attachment_image($fallbackLogoId, 'medium', false, [
                                'class' => 'project-marquee-slider__fallback-logo',
                                'loading' => 'lazy',
                                'decoding' => 'async',
                                'draggable' => 'false',
                                'alt' => '',
                            ]); ?>
                        <?php endif; ?>
                    </motion.div>
                <?php endif; ?>

                <?php if ($hasVideo): ?>
                    <video class="project-marquee-slider__video"
                           muted
                           loop
                           playsinline
                           preload="none"
                           aria-hidden="true">
                        <source src="<?php echo esc_url($videoUrl); ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            </figure>

            <span class="project-marquee-slider__arrow" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                    <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </a>
    </article>
</div>
