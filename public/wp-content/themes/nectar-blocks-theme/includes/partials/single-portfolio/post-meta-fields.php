<?php

/**
 * Post meta fields
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 2.0.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $nectar_options;

$nectar_display_project_link = isset($nectar_options['portfolio_single_project_link']) ? $nectar_options['portfolio_single_project_link'] : '1';
$nectar_project_link = get_post_meta(get_the_ID(), '_nectar_portfolio_project_link', true);

$nectar_portfolio_date = get_post_meta(get_the_ID(), '_nectar_portfolio_date', true);
$nectar_display_date = isset($nectar_options['portfolio_single_date']) ? $nectar_options['portfolio_single_date'] : '1';

$nectar_portfolio_client = get_post_meta(get_the_ID(), '_nectar_portfolio_client', true);
$nectar_display_client = isset($nectar_options['portfolio_single_client']) ? $nectar_options['portfolio_single_client'] : '1';

$nectar_meta_label_class = ' nectar-font-label';

// Client
if ($nectar_display_client === '1' && ! empty($nectar_portfolio_client)) {
  echo '<span class="meta-client' . $nectar_meta_label_class . '">' . esc_html($nectar_portfolio_client) . '</span>';
}

// Portfolio Date
if ($nectar_display_date === '1' && ! empty($nectar_portfolio_date)) {
  echo '<span class="meta-portfolio-date' . $nectar_meta_label_class . '">' . esc_html($nectar_portfolio_date) . '</span>';
}

// Project Link
if ($nectar_display_project_link === '1' && ! empty($nectar_project_link)) {
  echo '<span class="meta-project-link nectar-link-underline-effect' . $nectar_meta_label_class . '"><a href="' . esc_url($nectar_project_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View Project', 'nectar-blocks-theme') . '</a></span>';
}
