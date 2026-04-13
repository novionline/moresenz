<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Extends WordPress search so that content inside reusable blocks (wp_block / block patterns)
 * is matched. Pages that only reference a block via "ref" will be found when the block's
 * content contains the search term.
 *
 * @package NoviOnline
 */
class SearchComponent extends Singleton {

    /**
     * Constructor.
     */
    protected function __construct() {
        //override nectar quick search results so we can reliably exclude patterns
        add_action('wp_ajax_nectar_ajax_ext_search_results', [$this, 'handleNectarQuickSearch'], 0);
        add_action('wp_ajax_nopriv_nectar_ajax_ext_search_results', [$this, 'handleNectarQuickSearch'], 0);
        //override legacy nectar autocomplete search as well
        add_action('wp_ajax_myprefix_autocompletesearch', [$this, 'handleNectarAutocompleteSuggestions'], 0);
        add_action('wp_ajax_nopriv_myprefix_autocompletesearch', [$this, 'handleNectarAutocompleteSuggestions'], 0);

        add_filter('posts_search', [$this, 'includeReusableBlockContentInSearch'], 10, 2);
        add_action('pre_get_posts', [$this, 'expandMainSearchPostTypes'], 11, 1);
    }

    /**
     * Replace Nectar header AJAX search handler with our own.
     *
     * This is more reliable than only filtering WP_Query clauses inside admin-ajax.php.
     *
     * @return void
     */
    public function handleNectarQuickSearch(): void {
        if (!isset($_POST['search'])) {
            wp_die();
        }

        //make sure the class exists (normally included by the parent theme for material skin)
        if (!class_exists('NectarQuickSearch') && defined('NECTAR_THEME_DIRECTORY')) {
            $quickSearchPath = rtrim((string) NECTAR_THEME_DIRECTORY, '/') . '/includes/class-nectar-quick-search.php';
            if (is_readable($quickSearchPath)) {
                include_once $quickSearchPath;
            }
        }

        if (!class_exists('NectarQuickSearch')) {
            wp_die();
        }

        $searchTerm = sanitize_text_field($_POST['search']);
        $searchTerm = apply_filters('get_search_query', $searchTerm);

        //initialize and read configured post type/style
        $quickSearch = \NectarQuickSearch::get_instance();
        $postType = \NectarQuickSearch::$post_type ?? 'any';

        $queryArgs = [
            'posts_per_page' => 6,
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'has_password' => false,
            's' => $searchTerm,
            'post_type' => $this->allowedSearchPostTypes($postType),
            'suppress_filters' => false,
        ];

        if ($postType === 'product') {
            $queryArgs['tax_query'] = [
                [
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'exclude-from-catalog',
                    'operator' => 'NOT IN',
                ],
            ];
        }

        //allow user filtering but always re-sanitize post types to prevent patterns from being returned
        $queryArgs = apply_filters('nectar_quick_search_query', $queryArgs);
        $queryArgs['post_type'] = $this->allowedSearchPostTypes($queryArgs['post_type'] ?? $postType);
        $queryArgs['suppress_filters'] = false;

        $searchQuery = new \WP_Query($queryArgs);

        $content = '';
        if ($searchQuery->have_posts()) {
            while ($searchQuery->have_posts()) {
                $searchQuery->the_post();
                global $post;

                $method = $postType . '_markup';
                if (is_callable([$quickSearch, $method])) {
                    $content .= call_user_func([$quickSearch, $method], \NectarQuickSearch::$style, $post);
                }
            }
        }

        wp_reset_postdata();

        if (!empty($content)) {
            $contentStart = '';
            $contentEnd = '';

            if ($postType === 'product' && \NectarQuickSearch::$ajax_style === 'extended') {
                $contentStart = '<div class="woocommerce"><ul class="products columns-4" data-rm-m-hover="on" data-n-desktop-columns="5" data-n-desktop-small-columns="5" data-n-tablet-columns="2" data-n-phone-columns="2" data-product-style="classic">';
                $contentEnd = '</ul></div>';
            } else {
                $contentStart = '<div class="nectar-search-results">';
                $contentEnd = '</div>';
            }

            wp_send_json([
                'content' => $contentStart . $content . $contentEnd,
            ]);
        }

        wp_die();
    }

    /**
     * Replace Nectar legacy header autocomplete AJAX handler with our own.
     *
     * The parent theme uses get_posts() with suppress_filters=true which prevents us from
     * excluding patterns and from extending search into reusable blocks.
     *
     * @return void
     */
    public function handleNectarAutocompleteSuggestions(): void {
        $term = isset($_REQUEST['term']) ? sanitize_text_field($_REQUEST['term']) : '';
        $term = apply_filters('get_search_query', $term);

        if (trim((string) $term) === '') {
            wp_die();
        }

        $nectarOptions = function_exists('get_nectar_theme_options') ? get_nectar_theme_options() : [];
        $showPostsNum = (!empty($nectarOptions['theme-skin']) && $nectarOptions['theme-skin'] === 'ascend') ? 3 : 6;

        $postTypesList = ['post', 'product', 'portfolio'];
        $postType = 'any';
        if (isset($nectarOptions['header-search-limit']) && in_array($nectarOptions['header-search-limit'], $postTypesList, true)) {
            $postType = esc_attr($nectarOptions['header-search-limit']);
        }

        $queryArgs = [
            's' => $term,
            'posts_per_page' => $showPostsNum,
            'post_type' => $this->allowedSearchPostTypes($postType),
            'post_status' => 'publish',
            'post_password' => '',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ];

        $query = new \WP_Query($queryArgs);
        $suggestions = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;

                $suggestion = [];
                $suggestion['label'] = esc_html($post->post_title);
                $suggestion['link'] = esc_url(get_permalink());
                $suggestion['image'] = has_post_thumbnail($post->ID)
                    ? get_the_post_thumbnail($post->ID, 'thumbnail', ['title' => ''])
                    : '<i class="icon-nectar-blocks-pencil"></i>';
                $suggestion['target'] = '_self';

                $currentPostType = get_post_type($post->ID);
                if ($currentPostType === 'post') {
                    if (get_post_format() === 'link') {
                        $postLinkUrl = get_post_meta($post->ID, '_nectar_link', true);
                        $postLinkText = get_the_content();
                        if (empty($postLinkText) && !empty($postLinkUrl)) {
                            $suggestion['link'] = esc_url($postLinkUrl);
                            $suggestion['target'] = '_blank';
                        }
                    }
                    $suggestion['post_type'] = esc_html__('Blog Post', 'nectar-blocks-theme');
                } elseif ($currentPostType === 'page') {
                    $suggestion['post_type'] = esc_html__('Page', 'nectar-blocks-theme');
                } elseif ($currentPostType === 'portfolio') {
                    $suggestion['post_type'] = esc_html__('Portfolio Item', 'nectar-blocks-theme');
                } elseif ($currentPostType === 'product') {
                    $suggestion['post_type'] = esc_html__('Product', 'nectar-blocks-theme');
                } else {
                    $ptObj = get_post_type_object($currentPostType);
                    $suggestion['post_type'] = ($ptObj && isset($ptObj->labels->singular_name))
                        ? esc_html($ptObj->labels->singular_name)
                        : esc_html($currentPostType);
                }

                $suggestions[] = $suggestion;
            }
        }

        wp_reset_postdata();

        $callback = isset($_GET['callback']) ? sanitize_text_field($_GET['callback']) : '';
        $callback = htmlentities($callback, ENT_QUOTES, 'UTF-8');
        echo $callback . '(' . wp_json_encode($suggestions) . ')';
        exit;
    }

    /**
     * Add an OR clause so posts that reference a reusable block containing the search term are included.
     *
     * @param string   $search search SQL clause
     * @param \WP_Query $query the query
     * @return string modified search clause
     */
    public function includeReusableBlockContentInSearch(string $search, \WP_Query $query): string {
        // don't rely on is_search() during AJAX; check actual query var
        $term = $query->get('s');
        if ($term === null || trim((string) $term) === '') {
            return $search;
        }
        // don't affect unrelated admin queries
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return $search;
        }

        global $wpdb;

        $like = '%' . $wpdb->esc_like($term) . '%';
        $postTypes = $this->getSearchPostTypes($query);

        if (empty($postTypes)) {
            return $search;
        }

        $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));
        $sql = $wpdb->prepare(
            " OR ({$wpdb->posts}.ID IN (SELECT DISTINCT p.ID FROM {$wpdb->posts} p " .
            "INNER JOIN {$wpdb->posts} block ON block.post_type = 'wp_block' AND block.post_status = 'publish' AND block.post_content LIKE %s " .
            "AND (p.post_content LIKE CONCAT('%%\"ref\":', block.ID, '%%') OR p.post_content LIKE CONCAT('%%\"ref\": ', block.ID, '%%')) " .
            "WHERE p.post_type IN ($placeholders) AND p.post_status = 'publish'))",
            array_merge([$like], $postTypes)
        );

        //wrap our OR addition so it doesn't break the overall WHERE precedence
        $searchNoAnd = preg_replace('/^\\s*AND\\s*/', '', $search);
        return ' AND (' . $searchNoAnd . $sql . ')';
    }

    /**
     * Post types to include in the "references block" subquery (same as main search scope).
     *
     * @param \WP_Query $query
     * @return array list of post type slugs
     */
    private function getSearchPostTypes(\WP_Query $query): array {
        $postType = $query->get('post_type');

        if (!empty($postType)) {
            $types = is_array($postType) ? $postType : [$postType];
            return array_values(array_filter($types, 'is_string'));
        }

        // default: public types that are not excluded from search
        return array_values(get_post_types([
            'public' => true,
            'exclude_from_search' => false,
        ]));
    }

    /**
     * Expand front-end main search to include pages and other public searchable types.
     *
     * WP core defaults search to post type "post" only, which would miss pages that reference
     * synced patterns/reusable blocks. Nectar's search template can render pages fine, it just
     * needs them included in the query.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function expandMainSearchPostTypes(\WP_Query $query): void {
        if (is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return;
        }
        if (!$query->is_main_query()) {
            return;
        }
        $term = $query->get('s');
        if ($term === null || trim((string) $term) === '') {
            return;
        }

        $postType = $query->get('post_type');
        // Respect explicit post_type requests. Only expand when defaulting to posts.
        if (!empty($postType) && $postType !== 'post') {
            return;
        }

        $query->set('post_type', $this->allowedSearchPostTypes('any'));
        $query->set('suppress_filters', false);
    }

    /**
     * Post types allowed in search (never includes wp_block).
     *
     * @param string|array $postType 'any' or single type or array of types
     * @return array list of post type slugs
     */
    private function allowedSearchPostTypes($postType): array {
        if ($postType !== 'any' && !is_array($postType)) {
            return $postType === 'wp_block' ? ['post', 'page'] : [$postType];
        }
        if ($postType === 'any') {
            $types = get_post_types([
                'public' => true,
                'exclude_from_search' => false,
            ], 'names');
        } else {
            $types = $postType;
        }
        $types = is_array($types) ? $types : [];
        $types = array_values(array_diff($types, ['wp_block', 'attachment', 'wp_template', 'wp_template_part', 'wp_navigation']));
        return !empty($types) ? $types : ['post', 'page'];
    }
}
