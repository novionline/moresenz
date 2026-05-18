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

$pauseOnHoverField = get_field('project_marquee_pause_on_hover');
$pauseOnHover = $pauseOnHoverField === null || $pauseOnHoverField === '' ? true : (bool)$pauseOnHoverField;

$enableDragField = get_field('project_marquee_enable_drag');
$enableDrag = $enableDragField === null || $enableDragField === '' ? true : (bool)$enableDragField;

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

<section class="project-marquee-slider<?php echo $is_preview ? ' is-editor-preview' : ''; ?>"
         aria-label="<?php echo esc_attr($sectionLabel); ?>"
         data-marquee-speed="<?php echo esc_attr((string)$marqueeSpeed); ?>"
         data-pause-on-hover="<?php echo $pauseOnHover ? '1' : '0'; ?>"
         data-drag-enabled="<?php echo $enableDrag ? '1' : '0'; ?>"
         <?php echo $is_preview ? ' data-editor-preview="true"' : ''; ?>>

    <?php if ($is_preview && count($posts) === 0): ?>
        <p class="project-marquee-slider__empty">
            <?php esc_html_e('No projects found. Add projects or adjust the block selection settings.', Theme::TEXT_DOMAIN); ?>
        </p>
    <?php elseif (count($posts) > 0): ?>
        <div class="nectar-blocks-marquee project-marquee-slider__track"
             style="--speed: <?php echo esc_attr($speedDuration); ?>; --direction: normal;">

            <div class="nectar-blocks-marquee__inner" aria-hidden="false">
                <?php foreach ($posts as $postItem): ?>
                    <?php Partial::render('project-marquee-item', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>
                <?php endforeach; ?>
            </div>

            <div class="nectar-blocks-marquee__inner" aria-hidden="true" tabindex="-1">
                <?php foreach ($posts as $postItem): ?>
                    <?php Partial::render('project-marquee-item', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>
                <?php endforeach; ?>
            </div>

        </div>
    <?php endif; ?>

</section>
