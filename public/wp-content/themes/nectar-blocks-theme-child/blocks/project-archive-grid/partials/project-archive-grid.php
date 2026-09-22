<?php

use NoviOnline\Core\Partial;
use NoviOnline\ProjectArchiveGridBlock;
use NoviOnline\ProjectPostType;
use NoviOnline\Theme;

/**
 * @var array $block
 * @var bool $is_preview
 */

$posts = get_posts([
    'post_type' => ProjectPostType::TYPE,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'menu_order date',
    'order' => 'ASC',
    'suppress_filters' => false,
]);

$blockId = isset($block['id']) ? (string)$block['id'] : '';
$anchor = !empty($block['anchor']) ? (string)$block['anchor'] : $blockId;
$className = 'project-archive-grid';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if ($is_preview) {
    $className .= ' is-editor-preview';
}
?>

<section class="<?php echo esc_attr($className); ?>"
         <?php echo $anchor !== '' ? 'id="' . esc_attr($anchor) . '"' : ''; ?>
         data-project-card-hover="1">
    <?php if (empty($posts)): ?>
        <p class="project-archive-grid__empty">
            <?php esc_html_e('No projects found.', Theme::TEXT_DOMAIN); ?>
        </p>
    <?php else: ?>
        <ul class="project-archive-grid__list">
            <?php foreach ($posts as $postItem): ?>
                <?php
                $postId = $postItem->ID;
                $postTitle = get_the_title($postId);
                $excerpt = get_the_excerpt($postId);
                if ($excerpt !== '') {
                    $excerpt = wp_trim_words($excerpt, 24, '…');
                }
                ?>
                <li class="project-archive-grid__item">
                    <?php Partial::render('project-card', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                        'imageSize' => ProjectArchiveGridBlock::IMAGE_SIZE,
                        'imageSizesAttr' => ProjectArchiveGridBlock::IMAGE_SIZES_ATTR,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>

                    <div class="project-archive-grid__meta">
                        <h2 class="project-archive-grid__title"><?php echo esc_html($postTitle); ?></h2>
                        <?php if ($excerpt !== ''): ?>
                            <p class="project-archive-grid__excerpt"><?php echo esc_html($excerpt); ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
