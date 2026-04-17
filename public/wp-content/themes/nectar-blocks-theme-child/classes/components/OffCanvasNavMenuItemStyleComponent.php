<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Adds an ACF select field to menu items that controls off-canvas nav item styling.
 *
 * @package NoviOnline
 */
class OffCanvasNavMenuItemStyleComponent extends Singleton {

    public const ACF_FIELD_NAME = 'off_canvas_nav_item_style';

    /**
     * OffCanvasNavMenuItemStyleComponent constructor.
     */
    protected function __construct() {
        add_action('acf/init', [$this, 'registerAcfField']);
        add_filter('nav_menu_css_class', [$this, 'addOffCanvasNavItemStyleClass'], 10, 4);
        add_filter('nav_menu_link_attributes', [$this, 'addOffCanvasNavLinkStyleClass'], 10, 4);
    }

    /**
     * Register the select field for nav menu items.
     */
    public function registerAcfField(): void {

        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_novi_off_canvas_nav_item_style',
            'title' => __('Off canvas nav item style', Theme::TEXT_DOMAIN),
            'fields' => [
                [
                    'key' => 'field_novi_off_canvas_nav_item_style',
                    'label' => __('Off canvas style', Theme::TEXT_DOMAIN),
                    'name' => self::ACF_FIELD_NAME,
                    'type' => 'select',
                    'choices' => [
                        'default' => __('Regular text', Theme::TEXT_DOMAIN),
                        'large' => __('Large text', Theme::TEXT_DOMAIN),
                        'button' => __('White button', Theme::TEXT_DOMAIN),
                    ],
                    'default_value' => 'default',
                    'return_format' => 'value',
                    'ui' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'nav_menu_item',
                        'operator' => '==',
                        'value' => 'all',
                    ],
                ],
            ],
            'position' => 'acf_after_title',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
        ]);
    }

    /**
     * Add menu item classes for the off_canvas_nav location only.
     *
     * @param array $classes
     * @param \WP_Post $menuItem
     * @param object $args
     * @param int $depth
     * @return array
     */
    public function addOffCanvasNavItemStyleClass(array $classes, $menuItem, $args, int $depth): array {

        if (!is_object($args) || !isset($args->theme_location) || $args->theme_location !== 'off_canvas_nav') {
            return $classes;
        }

        $styleClass = $this->getMenuItemStyleClass($menuItem);

        if (!in_array($styleClass, $classes, true)) {
            $classes[] = $styleClass;
        }

        return $classes;
    }

    /**
     * Add the same class to the menu item link for easier styling.
     *
     * @param array $atts
     * @param \WP_Post $menuItem
     * @param object $args
     * @param int $depth
     * @return array
     */
    public function addOffCanvasNavLinkStyleClass(array $atts, $menuItem, $args, int $depth): array {

        if (!is_object($args) || !isset($args->theme_location) || $args->theme_location !== 'off_canvas_nav') {
            return $atts;
        }

        $styleClass = $this->getMenuItemStyleClass($menuItem);

        $existing = $atts['class'] ?? '';
        $existingClasses = is_string($existing) ? preg_split('/\s+/', trim($existing)) : [];
        if (!is_array($existingClasses)) {
            $existingClasses = [];
        }

        if (!in_array($styleClass, $existingClasses, true)) {
            $existingClasses[] = $styleClass;
        }

        $atts['class'] = trim(implode(' ', array_filter($existingClasses)));

        return $atts;
    }

    /**
     * Resolve the CSS class based on the ACF field value.
     *
     * @param \WP_Post $menuItem
     * @return string
     */
    private function getMenuItemStyleClass($menuItem): string {

        $styleValue = 'default';

        if (isset($menuItem->ID)) {

            //try ACF first (menu items are a post type; some setups store/read via numeric ID)
            if (function_exists('get_field')) {
                $maybeStyle = get_field(self::ACF_FIELD_NAME, (int)$menuItem->ID);
                if (!is_string($maybeStyle) || $maybeStyle === '') {
                    $maybeStyle = get_field(self::ACF_FIELD_NAME, 'nav_menu_item_' . $menuItem->ID);
                }
                if (is_string($maybeStyle) && $maybeStyle !== '') {
                    $styleValue = $maybeStyle;
                }
            }

            //fallback: raw meta (in case ACF context mapping differs)
            if ($styleValue === 'default') {
                $maybeMeta = get_post_meta((int)$menuItem->ID, self::ACF_FIELD_NAME, true);
                if (is_string($maybeMeta) && $maybeMeta !== '') {
                    $styleValue = $maybeMeta;
                }
            }
        }

        return match ($styleValue) {
            'large' => 'novi-ofn-style--large',
            'button' => 'novi-ofn-style--white-button',
            default => 'novi-ofn-style--regular',
        };
    }
}

