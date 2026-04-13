<?php

namespace NoviOnline\Core;

/**
 * Class Navigation
 * @package NoviOnline\Core
 */
class Navigation
{

    /**
     * Get links by theme location
     * @param string $themeLocation
     * @return array
     */
    public static function getLinksByThemeLocation(string $themeLocation): array
    {
        $items = [];

        if ($themeLocation) {
            $menuLocations = get_nav_menu_locations();
            if (isset($menuLocations[$themeLocation])) {
                $termObject = get_term($menuLocations[$themeLocation], 'nav_menu');
                if ($termObject) {
                    $menuItems = wp_get_nav_menu_items($termObject);
                    if ($menuItems && is_array($menuItems)) $items = self::recursiveParseMenuItems($menuItems);
                }
            }
        }

        return $items;
    }

    /**
     * Get menu links by ID
     * @param int $menuId
     * @return array
     */
    public static function getLinksByMenuId(int $menuId = 0): array
    {
        $items = [];

        if ($menuId) {
            $menuItems = wp_get_nav_menu_items($menuId);
            if ($menuItems && is_array($menuItems)) $items = self::recursiveParseMenuItems($menuItems);
        }

        return $items;
    }

    /**
     * Recursively parse WP menu to tree-like array
     * @source https://stackoverflow.com/questions/44779734/php-making-a-nested-tree-menu-structure-from-a-flat-array
     * @param array $rows
     * @return array
     */
    public static function recursiveParseMenuItems(array $rows): array
    {
        //initialize the menu structure
        $menu = []; //the menu structure
        $byId = []; //menu ID-table (temporary)

        //build the menu (hierarchy) from flat $rows traversable
        foreach ($rows as $index => $row) {

            //prevent logged-in users from seeing private pages in menu
            if (!is_user_logged_in() && $row->object_id) {
                $post = get_post($row->object_id);
                if (is_a($post, '\WP_Post') && in_array($post->post_status, ['private', 'pending', 'draft'])) {
                    unset($rows[$index]);
                    continue;
                }
            }

            //parse row from wp post to object
            $row = self::parseSingleMenuItem($row);

            //map row to local ID variables
            $id = (int)$row->id;
            $parentId = (int)$row->parentId;

            //build the entry
            $entry = $row;

            //init submenus for the entry
            if (isset($byId[$id])) {
                $children = &$byId[$id]->children;
                $entry->children = $children ? $children : [];
            } else {
                $children = [];
            }

            //register the entry in the menu structure
            if ((int)$parentId === 0) {
                //special case that an entry has no parent
                $menu[] = &$entry;
            } else {
                //second special case that an entry has a parent
                if (isset($byId[$parentId])) $byId[$parentId]->children[] = &$entry;
            }

            //register the entry as well in the menu ID-table
            $byId[$id] = &$entry;

            //unset foreach (loop) entry alias
            unset($entry);
        }

        //add depth / level to each item
        $menu = self::addDepth($menu);

        return $menu;
    }

    /**
     * Parse single menu item from WP_Post to object
     * @param $menuItem
     * @return \stdClass
     */
    public static function parseSingleMenuItem($menuItem): \stdClass
    {
        $parsed = new \stdClass();
        $parsed->id = (int)$menuItem->ID;
        $parsed->parentId = $menuItem->menu_item_parent;
        $parsed->label = $menuItem->title;
        $parsed->target = empty($menuItem->target) ? '_self' : $menuItem->target;
        $parsed->link = Link::parseLink($menuItem->url);
        $parsed->rel = trim($menuItem->xfn);
        $parsed->style = get_field('style', $menuItem->ID) ?: 'default';
        $parsed->children = [];

        return $parsed;
    }

    /**
     * Recursively add depth to menu items
     * @param array $items
     * @param int $depth
     * @return array
     */
    public static function addDepth(array $items = [], int $depth = 1): array
    {
        return array_map(function ($item) use ($depth) {
            $item->depth = $depth;
            if (count($item->children) > 0) $item->children = self::addDepth($item->children, $depth + 1);
            return $item;
        }, $items);
    }
}
