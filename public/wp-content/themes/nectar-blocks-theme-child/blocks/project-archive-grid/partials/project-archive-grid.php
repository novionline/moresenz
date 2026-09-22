<?php

use NoviOnline\Core\Partial;
use NoviOnline\ProjectArchiveGridBlock;
use NoviOnline\ProjectExcerptHelper;
use NoviOnline\ProjectPostType;
use NoviOnline\ProjectSettings;
use NoviOnline\Theme;

/**
 * @var array $block
 * @var bool $is_preview
 */

$postsPerPage = ProjectSettings::getPostsPerPage();
$currentPage = $is_preview ? 1 : ProjectArchiveGridBlock::getCurrentPage();

$queryArgs = [
    'post_type' => ProjectPostType::TYPE,
    'post_status' => 'publish',
    'posts_per_page' => $postsPerPage,
    'paged' => $currentPage,
    'orderby' => [
        'menu_order' => 'ASC',
        'date' => 'ASC',
    ],
    'order' => 'ASC',
    'suppress_filters' => false,
];

$projectsQuery = new WP_Query($queryArgs);
$posts = $projectsQuery->posts;
$totalPages = (int) $projectsQuery->max_num_pages;
$paginationMarkup = (!$is_preview && $postsPerPage !== -1)
    ? ProjectArchiveGridBlock::getPaginationMarkup($totalPages, $currentPage)
    : '';

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
                $permalink = get_permalink($postId);
                //prefer stored excerpt; fall back to first long nectar-blocks/text paragraph
                $excerpt = ProjectExcerptHelper::getForPost($postId, 24);
                ?>
                <li class="project-archive-grid__item">
                    <?php Partial::render('project-card', [
                        'postItem' => $postItem,
                        'is_preview' => $is_preview,
                        'imageSize' => ProjectArchiveGridBlock::IMAGE_SIZE,
                        'imageSizesAttr' => ProjectArchiveGridBlock::IMAGE_SIZES_ATTR,
                    ], true, get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'); ?>

                    <div class="project-archive-grid__meta">
                        <a class="project-archive-grid__title-link"
                           href="<?php echo esc_url($permalink); ?>">
                            <h2 class="project-archive-grid__title"><?php echo esc_html($postTitle); ?></h2>
                        </a>
                        <?php if ($excerpt !== ''): ?>
                            <p class="project-archive-grid__excerpt"><?php echo esc_html($excerpt); ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($paginationMarkup !== ''): ?>
            <div class="project-archive-grid__pagination nectar-font-h6 align-center">
                <?php echo $paginationMarkup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built via paginate_links + esc_url ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
wp_reset_postdata();
