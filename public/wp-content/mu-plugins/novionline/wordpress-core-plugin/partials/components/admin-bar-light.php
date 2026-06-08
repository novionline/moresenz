<?php

use NoviOnline\Core;
use NoviOnline\Core\AdminBarLightComponent;

//handle primary items
$primaryItems = AdminBarLightComponent::getPrimaryAdminBarLightItems();
$hasPrimaryItems = count($primaryItems) > 0;

//handle secondary items
$secondaryItems = AdminBarLightComponent::getSecondaryAdminBarLightItems();
$hasSecondaryItems = count($secondaryItems) > 0;

//bail if no items
if (!$hasPrimaryItems && !$hasSecondaryItems) return false;

/**
 * Renders icon markup for a secondary/primary item.
 *
 * @param object $item
 * @return void
 */
$renderItemIcon = static function (object $item): void {
    if (!empty($item->icon_data)) {
        echo '<span class="admin-bar-light__link-icon-container" role="presentation">';
        if ($item->icon_data['type'] === 'dashicon') {
            echo '<span class="admin-bar-light__link-icon admin-bar-light__link-icon--dashicon ' . esc_attr($item->icon_data['value']) . '"></span>';
        } elseif ($item->icon_data['type'] === 'svg_data') {
            echo '<img class="admin-bar-light__link-icon admin-bar-light__link-icon--svg" src="' . esc_attr($item->icon_data['value']) . '" alt="" role="presentation">';
        } elseif ($item->icon_data['type'] === 'image_url') {
            echo '<img class="admin-bar-light__link-icon admin-bar-light__link-icon--image" src="' . esc_url($item->icon_data['value']) . '" alt="" role="presentation">';
        }
        echo '</span>';
    } elseif (!empty($item->icon) && defined('WCP_ICON_PATH')) {
        echo '<span class="admin-bar-light__link-icon-container" role="presentation">';
        echo '<svg class="admin-bar-light__link-icon"><use xlink:href="' . esc_attr(WCP_ICON_PATH . $item->icon) . '"/></svg>';
        echo '</span>';
    }
};

/**
 * Recursively renders synced pattern items with accordion for nested children.
 *
 * @param array $patternItems
 * @param callable $renderItemIcon
 * @return void
 */
$renderPatternItems = static function (array $patternItems) use (&$renderPatternItems, $renderItemIcon): void {
    foreach ($patternItems as $patternItem) {
        $label = !empty($patternItem->label) ? $patternItem->label : __('N/A', Core::TEXT_DOMAIN);
        $children = !empty($patternItem->children) && is_array($patternItem->children) ? $patternItem->children : [];
        $hasChildren = count($children) > 0;
        $patternId = !empty($patternItem->pattern_id) ? (int) $patternItem->pattern_id : 0;
        $depth = isset($patternItem->pattern_depth) ? (int) $patternItem->pattern_depth : 0;
        $toggleId = 'admin-bar-light-pattern-' . $patternId . '-' . $depth;
        ?>
        <li class="admin-bar-light__item<?php echo $hasChildren ? ' admin-bar-light__item--pattern' : ''; ?>">
            <?php if ($hasChildren): ?>
                <input type="checkbox"
                       id="<?php echo esc_attr($toggleId); ?>"
                       class="admin-bar-light__pattern-toggle"/>
                <div class="admin-bar-light__pattern-row">
                    <a class="admin-bar-light__link admin-bar-light__link--style-secondary admin-bar-light__pattern-link"
                       title="<?php echo !empty($patternItem->title) ? esc_attr($patternItem->title) : esc_attr($label); ?>"
                       href="<?php echo !empty($patternItem->href) ? esc_url($patternItem->href) : '#'; ?>"
                       target="<?php echo !empty($patternItem->target) ? esc_attr($patternItem->target) : '_self'; ?>">
                        <?php $renderItemIcon($patternItem); ?>
                        <span class="admin-bar-light__link-text"><?php echo esc_html($label); ?></span>
                    </a>
                    <label for="<?php echo esc_attr($toggleId); ?>"
                           class="admin-bar-light__pattern-toggle-label"
                           aria-label="<?php esc_attr_e('Toggle nested patterns', Core::TEXT_DOMAIN); ?>">
                        <?php if (defined('WCP_ICON_PATH')): ?>
                            <svg class="admin-bar-light__pattern-toggle-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z"/>
                            </svg>
                        <?php endif; ?>
                    </label>
                </div>
                <ul class="admin-bar-light__pattern-children">
                    <?php $renderPatternItems($children); ?>
                </ul>
            <?php else: ?>
                <a class="admin-bar-light__link admin-bar-light__link--style-secondary"
                   title="<?php echo !empty($patternItem->title) ? esc_attr($patternItem->title) : esc_attr($label); ?>"
                   href="<?php echo !empty($patternItem->href) ? esc_url($patternItem->href) : '#'; ?>"
                   target="<?php echo !empty($patternItem->target) ? esc_attr($patternItem->target) : '_self'; ?>">
                    <?php $renderItemIcon($patternItem); ?>
                    <span class="admin-bar-light__link-text"><?php echo esc_html($label); ?></span>
                </a>
            <?php endif; ?>
        </li>
        <?php
    }
};

?>

<nav class="admin-bar-light" aria-label="<?php esc_attr_e('Admin bar light', Core::TEXT_DOMAIN); ?>">

    <ul class="admin-bar-light__list">
        <?php if ($hasPrimaryItems): ?>
            <?php foreach ($primaryItems as $primaryItem): ?>
                <?php $label = !empty($primaryItem->label) ? $primaryItem->label : __('N/A', Core::TEXT_DOMAIN); ?>
                <li class="admin-bar-light__item">
                    <a class="admin-bar-light__link admin-bar-light__link--style-primary"
                       title="<?php echo !empty($primaryItem->title) ? esc_attr($primaryItem->title) : esc_attr($label); ?>"
                       href="<?php echo !empty($primaryItem->href) ? esc_url($primaryItem->href) : '#'; ?>"
                       target="<?php echo !empty($primaryItem->target) ? esc_attr($primaryItem->target) : '_self'; ?>">
                        <?php $renderItemIcon($primaryItem); ?>
                        <span class="admin-bar-light__link-text">
                            <?php echo esc_html($label); ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($hasSecondaryItems): ?>
            <li class="admin-bar-light__item">
                <input type="checkbox" id="admin-bar-light-more" class="admin-bar-light__checkbox-more"/>
                <label for="admin-bar-light-more"
                       aria-label="<?php esc_attr_e('Toggle the secondary action list', Core::TEXT_DOMAIN); ?>"
                       class="admin-bar-light__link admin-bar-light__link--toggle-more admin-bar-light__link--style-primary">
                    <?php if (defined('WCP_ICON_PATH')): ?>
                    <span class="admin-bar-light__link-icon-container" role="presentation">
                        <svg class="admin-bar-light__link-icon admin-bar-light__link-icon--open">
                            <use xlink:href="<?php echo esc_attr(WCP_ICON_PATH . 'icon-dots-vertical'); ?>"/>
                        </svg>
                        <svg class="admin-bar-light__link-icon admin-bar-light__link-icon--close">
                            <use xlink:href="<?php echo esc_attr(WCP_ICON_PATH . 'icon-close'); ?>"/>
                        </svg>
                    </span>
                    <?php endif; ?>
                </label>
                <ul class="admin-bar-light__list-more">
                    <?php foreach ($secondaryItems as $secondaryItem): ?>
                        <?php if (!empty($secondaryItem->heading)): ?>
                            <li class="admin-bar-light__item">
                                <strong class="admin-bar-light__heading admin-bar-light__heading--style-secondary">
                                    <?php echo esc_html($secondaryItem->heading); ?>
                                </strong>
                            </li>
                        <?php elseif (!empty($secondaryItem->type) && $secondaryItem->type === 'pattern'): ?>
                            <?php $renderPatternItems([$secondaryItem]); ?>
                        <?php else: ?>
                            <?php $label = !empty($secondaryItem->label) ? $secondaryItem->label : __('N/A', Core::TEXT_DOMAIN); ?>
                            <li class="admin-bar-light__item">
                                <a class="admin-bar-light__link admin-bar-light__link--style-secondary"
                                   title="<?php echo !empty($secondaryItem->title) ? esc_attr($secondaryItem->title) : esc_attr($label); ?>"
                                   href="<?php echo !empty($secondaryItem->href) ? esc_url($secondaryItem->href) : '#'; ?>"
                                   target="<?php echo !empty($secondaryItem->target) ? esc_attr($secondaryItem->target) : '_self'; ?>">
                                    <?php $renderItemIcon($secondaryItem); ?>
                                    <span class="admin-bar-light__link-text">
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</nav>
