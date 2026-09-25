<?php

namespace NoviOnline\Core;

/**
 * Class Admin
 * @package NoviOnline\Core
 */
class Admin
{
    /**
     * Get current post type in WP admin
     * @return string
     */
    public static function getCurrentPostType(): string
    {
        $postType = '';

        global $post, $parent_file, $typenow, $current_screen, $pagenow;

        if ($post && (property_exists($post, 'post_type') || method_exists($post, 'post_type'))) $postType = $post->post_type;
        if (empty($postType) && !empty($current_screen) && (property_exists($current_screen, 'post_type') || method_exists($current_screen, 'post_type')) && !empty($current_screen->post_type)) $postType = $current_screen->post_type;
        if (empty($postType) && !empty($typenow)) $postType = $typenow;
        if (empty($postType) && function_exists('get_current_screen')) $postType = get_current_screen();
        if (empty($postType) && isset($_REQUEST['post']) && !empty($_REQUEST['post']) && function_exists('get_post_type') && $get_post_type = get_post_type((int)$_REQUEST['post'])) $postType = $get_post_type;
        if (empty($postType) && isset($_REQUEST['post_type']) && !empty($_REQUEST['post_type'])) $postType = sanitize_key($_REQUEST['post_type']);
        if (empty($postType) && 'edit.php' == $pagenow) $postType = 'post';

        return $postType;
    }
}
