<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * Class PostType
 * @package NoviOnline\Core
 */
class PostType
{
    /**
     * Get labels for post type or taxonomy
     * @param string $singular
     * @param string $plural
     * @return array
     */
    public static function getLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => sprintf(__('New %1s', Core::TEXT_DOMAIN), $singular),
            'add_new_item' => sprintf(__('Add new %1s', Core::TEXT_DOMAIN), $singular),
            'edit_item' => sprintf(__('Edit %1s', Core::TEXT_DOMAIN), $singular),
            'new_item' => sprintf(__('New %1s', Core::TEXT_DOMAIN), $singular),
            'view_item' => sprintf(__('View %1s', Core::TEXT_DOMAIN), $singular),
            'search_items' => sprintf(__('Search %1s', Core::TEXT_DOMAIN), $singular),
            'not_found' => sprintf(__('No %1s found', Core::TEXT_DOMAIN), $singular),
            'not_found_in_trash' => sprintf(__('No %1s found in trash', Core::TEXT_DOMAIN), $plural),
            'all_items' => sprintf(__('All %1s', Core::TEXT_DOMAIN), $plural),
            'menu_name' => $plural,
            'name_admin_bar' => $singular
        ];
    }

    /**
     * More solid way to get current post type
     * @param bool|int $postId
     * @return string|false
     */
    public static function getPostType(bool|int $postId = false): string|false
    {
        if (!$postId) $postId = get_the_ID();

        $postType = get_post_type($postId);

        if (!$postType) {
            $queriedObject = get_queried_object();
            if ($queriedObject && is_a($queriedObject, '\WP_Post_Type')) $postType = $queriedObject->name;
        }

        return $postType;
    }
}