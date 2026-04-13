<?php

namespace NoviOnline;

use NoviOnline\Core\PostType;
use NoviOnline\Core\Singleton;

/**
 * Class ProjectPostType
 * @package NoviOnline
 */
class ProjectPostType extends Singleton {

    /**
     * Define post type slug
     */
    const TYPE = 'novi-project';

    /**
     * Define default archive page slug
     */
    const BASE_SLUG = 'projects';

    /**
     * Define taxonomy slug for project categories
     */
    const TAXONOMY_SLUG = 'novi-project-category';

    /**
     * ProjectPostType constructor.
     */
    protected function __construct() {

        //register post type (priority 5 so we appear above Global section / Theme builder in menu)
        add_action('init', [$this, 'registerPostType'], 5);

        //register project category taxonomy
        add_action('init', [$this, 'registerTaxonomy'], 5);
    }

    /**
     * Register post type
     * @return void
     */
    public static function registerPostType(): void {
        //register post type
        register_post_type(self::TYPE, [
            'public' => true,
            'labels' => PostType::getLabels(
                __('Project', Theme::TEXT_DOMAIN),
                __('Projects', Theme::TEXT_DOMAIN)
            ),
            'supports' => ['title', 'excerpt', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => [
                'slug' => self::BASE_SLUG,
                'with_front' => true
            ],
            'menu_icon' => 'dashicons-portfolio',
            'show_in_rest' => true, //needed to enable Gutenberg :)
            'taxonomies' => [self::TAXONOMY_SLUG],
            'menu_position' => 20,
        ]);
    }

    /**
     * Register project category taxonomy
     * @return void
     */
    public static function registerTaxonomy(): void {
        register_taxonomy(self::TAXONOMY_SLUG, [self::TYPE], [
            'public' => true,
            'labels' => PostType::getLabels(
                __('Project category', Theme::TEXT_DOMAIN),
                __('Project categories', Theme::TEXT_DOMAIN)
            ),
            'hierarchical' => true,
            'rewrite' => [
                'slug' => 'project-category',
                'with_front' => true
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
        ]);
    }
}

