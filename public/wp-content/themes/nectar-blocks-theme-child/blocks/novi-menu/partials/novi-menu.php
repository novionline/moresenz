<?php

use NoviOnline\Core\Navigation;
use NoviOnline\Core\PreviewNotificationComponent;
use NoviOnline\NoviMenuBlock;
use NoviOnline\Theme;

/**
 * @var int|string $post_id The post ID this block is saved to.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during AJAX preview.
 * @var array $block The block settings and attributes.
 */

$headingType = get_field('heading_type') ?: ''; // 'menu-heading' | 'custom-heading' | 'no-heading'
$heading = get_field('heading') ?: '';
$headingTag = get_field('heading_tag') ?? 'span'; // 'h3' | 'h4' | 'h5' | 'h6' | 'span'
$headingSize = get_field('heading_size') ?? 'h5'; // 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6'
$menuId = get_field('menu') ?: '';
$collapsibleEnabled = get_field('collapsible') === true;
$mt = get_field('margin_top') ?? ''; // 'smaller' | 'small' | 'medium' | 'large' | 'larger'
$mb = get_field('margin_bottom') ?? ''; // 'smaller' | 'small' | 'medium' | 'large' | 'larger'

//create array of classes
$headingClasses = ['novi-menu__heading'];
if ($headingSize) $headingClasses[] = 'nectar-font-' . trim($headingSize);

//handle column separation for devices
$menuColumnsDesktop = (int)get_field('menu_columns_desktop') ?? 1; // 1 | 2 | 3
$menuColumnsTablet = (int)get_field('menu_columns_tablet') ?? 1; // 1 | 2 | 3
$menuColumnsMobile = (int)get_field('menu_columns_mobile') ?? 1; // 1 | 2 | 3

//initialize the container classes with default values
$containerClasses = ['novi-block', 'novi-menu'];

//handle extra block classes
$extraClasses = $block['className'] ?? '';
if ($extraClasses) $containerClasses[] = $extraClasses;

//handle the menu column classes for desktop, tablet, and mobile
if ($menuColumnsDesktop > 1) $containerClasses[] = 'novi-menu--desktop-columns-' . $menuColumnsDesktop;
if ($menuColumnsTablet > 1) $containerClasses[] = 'novi-menu--tablet-columns-' . $menuColumnsTablet;
if ($menuColumnsMobile > 1) $containerClasses[] = 'novi-menu--mobile-columns-' . $menuColumnsMobile;
if ($collapsibleEnabled) $containerClasses[] = 'novi-accordion';

//generate unique ID
$uniqueId = uniqid();

?>

<?php if ($is_preview & !$menuId): ?>
    <?php echo PreviewNotificationComponent::getInstance()->getNotificationHtml(
        NoviMenuBlock::getBlockLabel(),
        __("Please select a menu.", Theme::TEXT_DOMAIN),
        'warning'
    ); ?>
<?php elseif ($menuId): ?>

    <?php

    //get menu items
    $menuItems = Navigation::getLinksByMenuId($menuId);

    //get correct heading text
    $headingText = $headingType === 'custom-heading' ? $heading : '';

    //handle heading type menu
    if ($headingType === 'menu-heading') {
        $menuTerm = get_term($menuId);
        if (is_a($menuTerm, 'WP_Term')) $headingText = $menuTerm->name;
    }

    //generate unique ID for menu block
    $menuBlockId = 'novi-menu__list-' . $uniqueId;

    ?>

    <?php if ($menuItems && count($menuItems) > 0): ?>

        <nav class="<?php echo implode(' ', $containerClasses); ?>"
             <?php if ($collapsibleEnabled): ?>data-collapsible="true"<?php endif; ?>
             <?php if (!$is_preview && $mt): ?>data-mt="<?php echo $mt; ?>"<?php endif; ?>
             <?php if (!$is_preview && $mb): ?>data-mb="<?php echo $mb; ?>"<?php endif; ?>
            <?php if (isset($headingText)): ?> aria-label="<?php echo esc_attr($headingText); ?>"<?php endif; ?>>

            <?php if ($headingText): ?>
                <div class="novi-menu__top">
                    <?php if ($collapsibleEnabled && !$is_preview): ?>
                        <button class="novi-menu__toggle"
                                id="menu-<?php echo $uniqueId ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Toggle %s', Theme::TEXT_DOMAIN), $headingText)); ?>"
                                aria-expanded="false"
                                aria-controls="<?php echo $menuBlockId; ?>">
                        </button>
                    <?php endif; ?>
                    <?php echo '<' . $headingTag . ' class="' . implode(' ', $headingClasses) . '">' . strip_tags(trim($headingText)) . '</' . $headingTag . '>' ?>
                </div>
            <?php endif; ?>

            <ul class="novi-menu__list" id="<?php echo $menuBlockId; ?>">
                <?php foreach ($menuItems as $menuItem): ?>
                    <li class="novi-menu__item">
                        <a href="<?php echo $menuItem->link; ?>"
                            <?php echo($is_preview ? 'onclick="return false"' : ''); ?>
                           class="novi-menu__item-link"
                           target="<?php echo $menuItem->target; ?>"
                           <?php if ($menuItem->rel): ?>rel="<?php echo $menuItem->rel; ?>"<?php endif; ?>>
                            <?php echo $menuItem->label; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>