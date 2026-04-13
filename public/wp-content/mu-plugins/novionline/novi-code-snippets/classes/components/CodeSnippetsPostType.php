<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class CodeSnippetsPostType
 * Registers the novi-code-snippets CPT and admin columns (Active, Priority).
 * Shows CSS/JS type as a post state after the title (display_post_states) with coloured styling.
 * @package NoviOnline\CodeSnippets
 */
class CodeSnippetsPostType extends Singleton
{
    const POST_TYPE = 'novi-code-snippets';

    protected function __construct()
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('admin_menu', [$this, 'registerSnippetsSubmenu'], 20);
        add_filter('wp_insert_post_data', [$this, 'forcePublishOnSave'], 10, 4);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'filterColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('posts_join', [$this, 'searchJoinSnippetCode'], 10, 2);
        add_filter('posts_search', [$this, 'searchWhereSnippetCode'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortableColumns']);
        add_action('pre_get_posts', [$this, 'defaultOrderSnippetsList'], 10, 1);
        add_filter('display_post_states', [$this, 'displaySnippetTypeState'], 10, 2);
        add_filter('post_states_html', [$this, 'postStatesHtmlWithTypeClass'], 10, 3);
        add_action('admin_head-edit.php', [$this, 'outputTypeStateCss']);
    }

    /**
     * Force snippet posts to be published when saving (not draft)
     * @param array $data
     * @param array $postarr
     * @param array $unsanitized_postarr
     * @param bool $update
     * @return array
     */
    public function forcePublishOnSave(array $data, array $postarr, array $unsanitized_postarr, bool $update): array
    {
        if ($data['post_type'] !== self::POST_TYPE) {
            return $data;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $data;
        }
        if (isset($data['post_status']) && $data['post_status'] === 'trash') {
            return $data;
        }
        $data['post_status'] = 'publish';
        return $data;
    }

    /**
     * Add Code Snippets as a submenu under Nectar Blocks (admin.php?page=nectar-blocks)
     * @return void
     */
    public function registerSnippetsSubmenu(): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        add_submenu_page(
            'nectar-blocks',
            __('Code Snippets', NoviCodeSnippets::TEXT_DOMAIN),
            __('Code Snippets', NoviCodeSnippets::TEXT_DOMAIN),
            'manage_options',
            'edit.php?post_type=' . self::POST_TYPE
        );
    }

    /**
     * Make Priority column sortable
     * @param array $columns
     * @return array
     */
    public function sortableColumns(array $columns): array
    {
        $columns['ncs_priority'] = 'ncs_priority';
        return $columns;
    }

    /**
     * Default admin list order: type (CSS first) then priority; handle sort-by-priority
     * @param \WP_Query $query
     * @return void
     */
    public function defaultOrderSnippetsList(\WP_Query $query): void
    {
        if (!is_admin() || !$query->get('post_type') || $query->get('post_type') !== self::POST_TYPE) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'edit-' . self::POST_TYPE) {
            return;
        }
        if ($query->get('orderby') === 'ncs_priority') {
            $query->set('meta_key', 'snippet_priority');
            $query->set('orderby', 'meta_value_num');
            $query->set('order', $query->get('order') ?: 'ASC');
            return;
        }
        if ($query->get('orderby')) {
            return;
        }
        $query->set('meta_query', [
            'ncs_type' => ['key' => 'snippet_type', 'compare' => 'EXISTS'],
            'ncs_priority' => ['key' => 'snippet_priority', 'compare' => 'EXISTS', 'type' => 'NUMERIC']
        ]);
        $query->set('orderby', ['ncs_type' => 'ASC', 'ncs_priority' => 'ASC']);
        $query->set('order', 'ASC');
    }

    /**
     * Join postmeta (snippet_code) when searching snippets in admin
     * @param string $join
     * @param \WP_Query $query
     * @return string
     */
    public function searchJoinSnippetCode(string $join, \WP_Query $query): string
    {
        if (!$this->isSnippetsSearchQuery($query)) {
            return $join;
        }
        global $wpdb;
        $join .= " LEFT JOIN {$wpdb->postmeta} AS ncs_search_meta ON ncs_search_meta.post_id = {$wpdb->posts}.ID AND ncs_search_meta.meta_key = 'snippet_code' ";
        return $join;
    }

    /**
     * Extend search to include snippet_code meta when searching snippets in admin
     * @param string $search
     * @param \WP_Query $query
     * @return string
     */
    public function searchWhereSnippetCode(string $search, \WP_Query $query): string
    {
        if (!$this->isSnippetsSearchQuery($query)) {
            return $search;
        }
        global $wpdb;
        $term = $query->get('s');
        if ($term !== '' && $term !== null) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $search .= $wpdb->prepare(" OR (ncs_search_meta.meta_value LIKE %s)", $like);
        }
        return $search;
    }

    /**
     * Whether the query is the admin snippets list with a search term
     * @param \WP_Query $query
     * @return bool
     */
    protected function isSnippetsSearchQuery(\WP_Query $query): bool
    {
        if (!is_admin() || !$query->get('s')) {
            return false;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return false;
        }
        $postType = $query->get('post_type');
        return ($postType === self::POST_TYPE || (is_array($postType) && in_array(self::POST_TYPE, $postType, true)));
    }

    /**
     * Add CSS or JS as a post state so it shows after the title in the list table
     * @param array $postStates
     * @param \WP_Post $post
     * @return array
     */
    public function displaySnippetTypeState(array $postStates, \WP_Post $post): array
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $postStates;
        }
        $type = get_field('snippet_type', $post->ID);
        if ($type === 'js') {
            $postStates['ncs_type_js'] = __('JS', NoviCodeSnippets::TEXT_DOMAIN);
        } else {
            $postStates['ncs_type_css'] = __('CSS', NoviCodeSnippets::TEXT_DOMAIN);
        }
        return $postStates;
    }

    /**
     * Add a class to our type post-state span so we can style CSS vs JS with different background colors
     * @param string $postStatesHtml
     * @param array $postStates
     * @param \WP_Post $post
     * @return string
     */
    public function postStatesHtmlWithTypeClass(string $postStatesHtml, array $postStates, \WP_Post $post): string
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $postStatesHtml;
        }
        $label = $postStates['ncs_type_css'] ?? $postStates['ncs_type_js'] ?? null;
        if ($label === null) {
            return $postStatesHtml;
        }
        $class = isset($postStates['ncs_type_js']) ? 'ncs-type-js' : 'ncs-type-css';
        $labelEsc = esc_html($label);
        $spanPlain = "<span class='post-state'>" . $label . "</span>";
        $spanWithComma = "<span class='post-state'>" . $label . ", </span>";
        $replacementPlain = "<span class='post-state " . esc_attr($class) . "'>" . $labelEsc . "</span>";
        $replacementWithComma = "<span class='post-state " . esc_attr($class) . "'>" . $labelEsc . ", </span>";
        $postStatesHtml = str_replace($spanWithComma, $replacementWithComma, $postStatesHtml);
        $postStatesHtml = str_replace($spanPlain, $replacementPlain, $postStatesHtml);
        return $postStatesHtml;
    }

    /**
     * Output CSS for type post-state (coloured CSS/JS label) on the edit list screen
     * @return void
     */
    public function outputTypeStateCss(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        echo '<style type="text/css">';
        echo '.post-state.ncs-type-css{background:#2271b1;color:#fff;padding:2px 6px;border-radius:3px;margin-left:4px;}';
        echo '.post-state.ncs-type-js{background:#dba617;color:#1d2327;padding:2px 6px;border-radius:3px;margin-left:4px;}';
        echo '</style>';
    }

    /**
     * Register the post type (only called when user can manage snippets)
     * @return void
     */
    public function registerPostType(): void
    {
        $showUi = Capability::userCanManageSnippets();
        $singular = __('Code Snippet', NoviCodeSnippets::TEXT_DOMAIN);
        $plural = __('Code Snippets', NoviCodeSnippets::TEXT_DOMAIN);
        $labels = [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => sprintf(__('Add New %s', NoviCodeSnippets::TEXT_DOMAIN), $singular),
            'add_new_item' => sprintf(__('Add New %s', NoviCodeSnippets::TEXT_DOMAIN), $singular),
            'edit_item' => sprintf(__('Edit %s', NoviCodeSnippets::TEXT_DOMAIN), $singular),
            'new_item' => sprintf(__('New %s', NoviCodeSnippets::TEXT_DOMAIN), $singular),
            'view_item' => sprintf(__('View %s', NoviCodeSnippets::TEXT_DOMAIN), $singular),
            'search_items' => sprintf(__('Search %s', NoviCodeSnippets::TEXT_DOMAIN), $plural),
            'not_found' => sprintf(__('No %s found', NoviCodeSnippets::TEXT_DOMAIN), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in trash', NoviCodeSnippets::TEXT_DOMAIN), $plural),
            'all_items' => sprintf(__('All %s', NoviCodeSnippets::TEXT_DOMAIN), $plural),
            'menu_name' => $plural,
            'name_admin_bar' => $singular
        ];
        register_post_type(self::POST_TYPE, [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => $showUi,
            'show_in_rest' => false,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-editor-code',
            'supports' => ['title', 'revisions'],
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'manage_options',
                'read_post' => 'manage_options',
                'delete_post' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options'
            ]
        ]);
    }

    /**
     * Add Active and Type columns to the list table
     * @param array $columns
     * @return array
     */
    public function filterColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;
            if ($key === 'title') {
                $newColumns['ncs_active'] = __('Active', NoviCodeSnippets::TEXT_DOMAIN);
                $newColumns['ncs_owner'] = __('Owner', NoviCodeSnippets::TEXT_DOMAIN);
                $newColumns['ncs_priority'] = __('Priority', NoviCodeSnippets::TEXT_DOMAIN);
            }
        }
        return $newColumns;
    }

    /**
     * Output column content for Active and Type
     * @param string $column
     * @param int $postId
     * @return void
     */
    public function renderColumn(string $column, int $postId): void
    {
        if ($column === 'ncs_active') {
            $enabled = get_field('snippet_enabled', $postId);
            echo $enabled ? __('Yes', NoviCodeSnippets::TEXT_DOMAIN) : __('No', NoviCodeSnippets::TEXT_DOMAIN);
        }
        if ($column === 'ncs_owner') {
            $owner = get_post_meta($postId, AcfFieldsComponent::META_OWNER, true);
            $labels = ['peter' => 'Peter', 'philip' => 'Philip', 'other' => 'Other'];
            echo isset($labels[$owner]) ? esc_html($labels[$owner]) : '—';
        }
        if ($column === 'ncs_priority') {
            $priority = get_post_meta($postId, AcfFieldsComponent::META_PRIORITY, true);
            echo max(1, (int) $priority);
        }
    }
}
