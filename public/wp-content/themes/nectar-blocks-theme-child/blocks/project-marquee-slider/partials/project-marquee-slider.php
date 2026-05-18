<?php

use NoviOnline\Core\Partial;
use NoviOnline\ProjectMarqueeSliderBlock;
use NoviOnline\ProjectPostType;
use NoviOnline\Theme;

/**
 * @var int|string $post_id The post ID this block is saved to.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during AJAX preview.
 * @var array $block The block settings and attributes.
 */

$posts = [];
$isAutomaticSelection = get_field('project_marquee_selection_method') === true;

if ($isAutomaticSelection) {
    $sortPostsBy = get_field('sort_project_marquee_posts_by') ?: 'menu_order';
    $sortOrder = get_field('project_marquee_sort_order') ?: 'ASC';
    $numberOfPosts = get_field('amount_of_project_posts_to_show_project_marquee') ?: -1;
    $categories = get_field('project_marquee_block_categories') ?: false;

    $args = [
        'posts_per_page' => (int)$numberOfPosts,
        'post_type' => ProjectPostType::TYPE,
        'orderby' => $sortPostsBy,
        'order' => $sortOrder,
        'post_status' => 'publish',
    ];

    if ($categories) {
        $args['tax_query'] = [[
            'taxonomy' => ProjectPostType::TAXONOMY_SLUG,
            'terms' => $categories,
            'include_children' => false,
        ]];
    }

    $posts = get_posts($args);
} else {
    $posts = get_field('selected_project_marquee_items') ?: [];
}

$marqueeSpeed = get_field('project_marquee_speed');
$marqueeSpeed = $marqueeSpeed !== null && $marqueeSpeed !== '' ? (float)$marqueeSpeed : 0.5;
$speedDuration = ProjectMarqueeSliderBlock::speedToCssDuration($marqueeSpeed);

$blockFallbackLogo = get_field('project_marquee_fallback_logo');
$fallbackLogoId = ProjectMarqueeSliderBlock::resolveFallbackLogoId(
    is_numeric($blockFallbackLogo) ? (int)$blockFallbackLogo : null
);

//duplicate posts for seamless marquee loop when list is short
$minItems = 16;
if (count($posts) > 0 && count($posts) < $minItems) {
    $originalPosts = $posts;
    while (count($posts) < $minItems) {
        $posts = array_merge($posts, $originalPosts);
    }
}

$sectionLabel = __('Projects carousel', Theme::TEXT_DOMAIN);
?>

<section class="project-marquee-slider"
         aria-label="<?php echo esc_attr($sectionLabel); ?>"
         data-marquee-speed="<?php echo esc_attr((string)$marqueeSpeed); ?>">

    <?php if ($is_preview && count($posts) === 0): ?>
        <p class="project-marquee-slider__empty">
            <?php esc_html_e('No projects found. Add projects or adjust the block selection settings.', Theme::TEXT_DOMAIN); ?>
        </p>
    <?php elseif (count($posts) > 0): ?>
        <motion.div class="nectar-blocks-marquee project-marquee-slider__track"
                    style="--speed: <?php echo esc_attr($speedDuration); ?>; --direction: normal;">

            <motion.div class="nectar-blocks-marquee__inner" aria-hidden="false">
                <?php foreach ($posts as $postItem): ?>
                    <?php Partial::render('project-marquee-item', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                        'fallbackLogoId' => $fallbackLogoId,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>
                <?php endforeach; ?>
            </motion.div>

            <motion.div class="nectar-blocks-marquee__inner" aria-hidden="true" tabindex="-1">
                <?php foreach ($posts as $postItem): ?>
                    <?php Partial::render('project-marquee-item', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                        'fallbackLogoId' => $fallbackLogoId,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>
                <?php endforeach; ?>
            </motion.div>

        </motion.div>
    <?php endif; ?>

</section>
