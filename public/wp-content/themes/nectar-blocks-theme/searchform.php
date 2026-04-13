<?php
/**
 * The template for the default search.
 *
 * @package Nectar Blocks Theme
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} ?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url( '/' )); ?>">
    <input type="text" class="search-field" placeholder="<?php echo esc_attr__('Search...', 'nectar-blocks-theme'); ?>" value="" name="s" title="<?php echo esc_attr__('Search for:', 'nectar-blocks-theme'); ?>" />
    <button type="submit" class="search-widget-btn"><span class="normal icon-nectar-blocks-search" aria-hidden="true"></span><span class="text"><?php echo esc_html__('Search', 'nectar-blocks-theme'); ?></span></button>
</form>