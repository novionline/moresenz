<?php

namespace Nectar\Render\Blocks\TaxonomyGrid;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * TaxonomyGrid Rendering
 * @version 1.1.2
 * @since 1.1.2
 */
class TaxonomyGrid {
  private $block_attributes;

  function __construct($block_attributes, $content) {
    $this->block_attributes = $block_attributes;
  }

  public function nectar_excerpt( int $limit ) {
    $data = '';
    if ( has_excerpt() ) {
        $data = get_the_excerpt();
    } else {
        $data = get_the_content();
    }
    // Strip short codes, but keep short code content
    $data_replaced = preg_replace( '/\[[^\]]+\]/', '', $data );
    return wp_trim_words( $data_replaced, $limit );
  }

  public function typography_class_name($typography, $space = false) {

    $leading_space = $space ? ' ' : '';

    if ( ! $typography) {
      return '';
    }

    if (strpos($typography, 'nectar-gt') !== false) {
        return $leading_space . esc_attr($typography);
    }
    return $typography !== '' ? $leading_space . 'nectar-font-' . esc_attr($typography) : '';
  }

  public function build_args() {
    $args = [
      'include' => $this->block_attributes['taxonomies'],
      'hide_empty' => false,
      'orderby' => $this->block_attributes['orderBy'],
      'order' => $this->block_attributes['postOrder']
    ];

    return apply_filters('nectar_blocks_taxonomy_grid_query_args', $args);
  }

  public function get_title($term, $settings) {
    $typo_class_name = $settings['typography'] ? ' class="' . $this->typography_class_name($settings['typography']) . ' title-element"' : ' class="title-element"';
    $allowed_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];
    if (array_key_exists('headingLevel', $settings) &&
        in_array($settings['headingLevel'], $allowed_tags) ) {
        $h_level = esc_attr($settings['headingLevel']);

        $markup = '<a href="' . esc_attr(get_term_link($term)) . '">';
        $markup .= '<' . $h_level . $typo_class_name . '>' . esc_html($term->name) . '</' . $h_level . '>';
        $markup .= '</a>';
        return $markup;
      }
    return false;
  }

  public function get_post_count($term, $settings) {

    $after_text = '';
    if ( array_key_exists('includeLabel', $settings) && $settings['includeLabel'] === true ) {
      $save_post_type = isset( $this->block_attributes['postType']) ? $this->block_attributes['postType'] : 'post';
      $post_type = get_post_type_object($save_post_type);
      $post_type_name = $post_type->labels->name;
      $post_type_singular_name = $post_type->labels->singular_name;

      $after_text = $term->count === 1 ? ' ' . esc_html($post_type_singular_name) : ' ' . esc_html($post_type_name);
    }

    $typo_class_name = $settings['typography'] ? ' class="' . $this->typography_class_name($settings['typography']) . '"' : '';
    return '<span' . $typo_class_name . '>' . esc_html($term->count) . $after_text . '</span>';
  }

  public function get_custom($post, $settings) {
    if ( ! isset($post->ID) || $post->ID === 0 || empty($settings['value'])) {
      return;
    }

    $custom_meta = get_post_meta($post->ID, sanitize_text_field($settings['value']), true);

    if ( ! $custom_meta ) {
      return;
    }

    $before_text = '';
    if (array_key_exists('beforeText', $settings) && ! empty($settings['beforeText']) ) {
      $before_text = '<span>' . esc_html($settings['beforeText']) . '</span>';
    }

    $after_text = '';
    if ( array_key_exists('afterText', $settings) && ! empty($settings['afterText']) ) {
      $after_text = '<span>' . esc_html($settings['afterText']) . '</span>';
    }

    // TODO: Fix this if statement
    $typo_class_name = $settings['typography'] ? ' class="' . $this->typography_class_name($settings['typography']) . '"' : '';

    return '<span' . $typo_class_name . '>' . $before_text . '<span>' . esc_html($custom_meta) . '</span>' . $after_text . '</span>';
  }

  public function get_featured_image($term) {
    $featured_image = '';
    $thumbnail_id = false;
    // get the taxonomy thumbnail 
    if ( $this->block_attributes['postType'] === 'post' ) {

      $terms = get_option( "taxonomy_$term->term_id" );
      $thumbnail_id = isset($terms['category_thumbnail_image-id']) ? $terms['category_thumbnail_image-id'] : false;

    } else if ( $this->block_attributes['postType'] === 'product' ) {
      $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
    } else {
      $post_thumbnail_meta_key = apply_filters('nectar_taxonomy_grid_featured_image_key', '_thumbnail_id', $this->block_attributes['postType']);
      $thumbnail_id = get_term_meta( $term->term_id, $post_thumbnail_meta_key, true );
    }

    if ( $thumbnail_id ) {
      $image = wp_get_attachment_image( $thumbnail_id, $this->block_attributes['imageSize'] );
      $featured_image = $image;
    }
    return $featured_image;
  }

  function get_bg_animation_attrs() {
    if ( isset($this->block_attributes['postGridStyle']['bgScrollAnimation']['enabled']) &&
         $this->block_attributes['postGridStyle']['bgScrollAnimation']['enabled'] === true
    ) {

      return esc_attr(wp_json_encode($this->block_attributes['postGridStyle']['bgScrollAnimation']));
    }

    return '';
  }

  function get_query_data() {
    $args = $this->build_args();
    $data = [
      'posts' => []
    ];

    if (empty($this->block_attributes['taxonomies'])) {
      return $data;
    }

    if (empty($this->block_attributes['postType'])) {
      return $data;
    }

    $post_type = $this->block_attributes['postType'];
    if (! post_type_exists($post_type)) {
      error_log('NectarBlocks: TaxonomyGrid - PostType does not exist');
      return $data;
    }

    $taxonomies = get_object_taxonomies($post_type);
    $terms = get_terms($args);

    if (! is_wp_error($terms) && ! empty($terms)) {
      foreach ($terms as $term) {
        // Make sure the taxonomy is in the post type
        if (! in_array($term->taxonomy, $taxonomies) ) {
          error_log('NectarBlocks: TaxonomyGrid - Taxonomy does not exist in post type: ' . $term->taxonomy . ' ' . $term->name);
          continue;
        }

        $active_meta = [];
        $display_meta = $this->block_attributes['displayMeta'];
        foreach( $display_meta as $settings ) {
          $type = str_replace("-", "_", $settings['type'], );
          $active_meta[] = [
            'type' => $type,
            'settings' => $settings,
            'output' => is_callable( [$this, 'get_' . $type] ) ? call_user_func([$this, 'get_' . $type], $term, $settings, $display_meta) : ''
          ];
        }

        $data['posts'][] = [
          'id' => get_the_id(),
          // 'has_featured_image' => has_post_thumbnail($post) ? true : false,
          'featured_image' => $this->get_featured_image($term),
          'permalink' => get_term_link($term),
          'meta' => $active_meta
        ];
      }
    }

    return $data;
  }

  function render() {
    $loader = new FilesystemLoader(__DIR__);
    $twig = new Environment($loader);

    $query_data = $this->get_query_data();
    $post_data = $query_data['posts'];

    $bg_animation_attrs = $this->get_bg_animation_attrs();

    if (count($post_data) > 0) {
      return $twig->render('./templates/template.twig', [
        'blockId' => $this->block_attributes['blockId'],
        'queryData' => $post_data,
        'currentPage' => isset($this->block_attributes['current_page']) ? $this->block_attributes['current_page'] : 1,
        'i18n' => [
          'load_more' => __('Load More', 'nectar-blocks'),
          'previous' => __('Previous', 'nectar-blocks'),
          'next' => __('Next', 'nectar-blocks'),
        ],
        'foundPosts' => count($post_data),
        'settings' => $this->block_attributes,
        'bgAnimation' => $bg_animation_attrs
      ]);
    } else {
      return $twig->render('./templates/empty_posts.twig', [
        'i18n' => [
          'no_posts' => __('No taxonomies selected.', 'nectar-blocks'),
          'no_posts_desc' => __('Try adjusting your query parameters.', 'nectar-blocks')
        ],
      ]);
    }
  }
};
