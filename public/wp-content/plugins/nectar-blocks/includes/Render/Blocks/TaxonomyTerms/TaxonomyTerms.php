<?php

namespace Nectar\Render\Blocks\TaxonomyTerms;
use Nectar\Utilities\BlockAnimations;

/**
 * PostContent Rendering
 * @version 2.0.0
 * @since 2.0.0
 */
class TaxonomyTerms {
  private $block_attributes;

  public $block_class_name = 'nectar-blocks-taxonomy-terms';

  function __construct($block_attributes, $content) {
    $this->block_attributes = $block_attributes;
  }

  public function get_class_names() {

    $classnames = [$this->block_class_name];

    // Typography
    if ( ! empty($this->block_attributes['typography']) ) {
      $classnames[] = $this->typography_class_name($this->block_attributes['typography']);
    }

    return implode(' ', $classnames);
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

  public function placeholder_render($taxonomy = null) {
    $is_rest = defined('REST_REQUEST') && REST_REQUEST;
    $is_template = $is_rest && in_array(get_post_type(), ['nectar_templates', 'nectar_sections']);
    $taxonomy_label = __('Select a Taxonomy', 'nectar-blocks');

    if ($is_template && $taxonomy) {
      return sprintf(
          '<span class="placeholder">%s</span>',
          /* translators: the taxonomy name */
          esc_html(sprintf(__('"%s" terms will render here.', 'nectar-blocks'), $taxonomy))
      );
    }

    if ($taxonomy) {
      /* translators: the taxonomy name */
      $taxonomy_label = sprintf(__('No terms in "%s"', 'nectar-blocks'), $taxonomy);
    }

    return sprintf(
        '<span class="placeholder">%s</span>',
        esc_html($taxonomy_label)
    );
  }

  private function should_show_active_class($taxonomy, $post_types, $all_link_href) {
    // Check if we're on a post type archive
    if (is_post_type_archive() && in_array(get_post_type(), $post_types)) {
      return true;
    }

    // Check if we're on a taxonomy archive without a specific term
    if (is_tax($taxonomy) && ! is_tax($taxonomy, get_queried_object()->term_id)) {
      return true;
    }

    // Check if we're on the home page with post type
    if (is_home() && in_array('post', $post_types)) {
      return true;
    }

    // Check if we're on the front page with post type
    if (is_front_page() && $all_link_href === home_url('/') && in_array('post', $post_types)) {
      return true;
    }

    return false;
  }

  public function render() {

    $rest_context = defined('REST_REQUEST') && REST_REQUEST;
    $taxonomy = $this->block_attributes['taxonomy'];

    if (! $taxonomy) {
      if ($rest_context || is_admin()) {
        return '<div id="' . esc_attr($this->block_attributes['blockId']) . '" class="' . $this->get_class_names() . '">' . $this->placeholder_render() . '</div>';
      }
      return;
    }

    // Get all terms
    if ($this->block_attributes['displayType'] === 'all_terms') {
      $assigned_terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => true
      ]);
    } else if ($this->block_attributes['displayType'] === 'parent_terms') {
      $assigned_terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'parent' => 0
      ]);
    } else {
      $assigned_terms = get_the_terms(get_the_ID(), $taxonomy);
    }

    $output = '';

    if ($assigned_terms && ! is_wp_error($assigned_terms)) {
      $last_term_key = array_key_last($assigned_terms);

      // Parent terms only
      if ( $this->block_attributes['displayType'] === 'current_parent_terms' ) {
        $assigned_terms = array_filter($assigned_terms, function ($term) {
          return $term->parent == 0;
        });
      }

      // Hover Effect Class.
      $hover_effect_class = '';
      if ($this->block_attributes['linkHoverEffect'] !== 'none') {
        $hover_effect_class = 'nectar-hover-effect--' . esc_attr($this->block_attributes['linkHoverEffect']);
      }

      // All link
      if ($this->block_attributes['enableLink'] && $this->block_attributes['enableAllLink']) {
        // Get post types associated with this taxonomy
        $post_types = get_taxonomy($taxonomy)->object_type;
        $all_link_href = '';

        // Use the first post type that has an archive
        foreach ($post_types as $post_type) {
          if ($post_type === 'post') {
            // Special handling for blog posts
            $posts_page_id = get_option('page_for_posts');
            if ($posts_page_id) {
              $all_link_href = get_permalink($posts_page_id);
            } else {
              $all_link_href = home_url('/');
            }
            break;
          } else if (get_post_type_object($post_type)->has_archive) {
            $all_link_href = get_post_type_archive_link($post_type);
            break;
          }
        }

        $all_link_classes = [
          $this->block_class_name . '__all-link',
          $this->block_class_name . '__term',
          $hover_effect_class
        ];

        // Active class - check if we're on the post type archive page or taxonomy archive without a term
        $queried_object = get_queried_object();
        if ($this->should_show_active_class($taxonomy, $post_types, $all_link_href)) {
          $all_link_classes[] = $this->block_class_name . '__term--active';
        }

        $all_link_text = __('All', 'nectar-blocks');
        if (! empty($hover_effect_class)) {
          $all_link_text = '<span class="text"><span class="text__inner" data-text="' . esc_attr($all_link_text) . '">' . $all_link_text . '</span></span>';
        }

        $output .= "<a class=\"" . esc_attr(implode(' ', $all_link_classes)) . "\" href=\"" . esc_url($all_link_href) . "\">" . $all_link_text . "</a>";
      }

      foreach ($assigned_terms as $key => $term) {
        $separator = ($key !== $last_term_key) ? '<span class="separator">,</span>' : '';
        $tag = $this->block_attributes['enableLink'] ? 'a' : 'span';
        $href = $this->block_attributes['enableLink'] ? ' href="' . esc_url(get_term_link($term)) . '"' : '';

        // Build classes array
        $term_classes = [
          $this->block_class_name . '__term',
          $hover_effect_class
        ];

        // Add active class if current term
        $queried_object = get_queried_object();
        if ($queried_object instanceof \WP_Term &&
            $queried_object->taxonomy === $taxonomy &&
            $queried_object->term_id === $term->term_id) {
          $term_classes[] = $this->block_class_name . '__term--active';
        }

        $term_text = esc_html($term->name);
        if (! empty($hover_effect_class)) {
          $term_text = '<span class="text"><span class="text__inner" data-text="' . esc_attr($term->name) . '">' . $term_text . '</span></span>';
        }

        $output .= "<{$tag} class=\"" . esc_attr(implode(' ', $term_classes)) . "\"{$href}>"
            . $term_text . $separator . "</{$tag}>";
      }
    } else if ($rest_context || is_admin()) {
      $output = $this->placeholder_render( $taxonomy );
    }

    $animation_attrs = '';
    if (isset($this->block_attributes['animation'])) {
      $animation_attrs = BlockAnimations::get_animation_attrs($this->block_attributes['animation']);
    }

    return '<div id="' . esc_attr($this->block_attributes['blockId']) . '" class="' . $this->get_class_names() . '"' . $animation_attrs . '>' . $output . '</div>';
  }
}