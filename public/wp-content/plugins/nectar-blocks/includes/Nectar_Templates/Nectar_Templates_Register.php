<?php

namespace Nectar\Nectar_Templates;

use Nectar\Nectar_Templates\Render;
use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Utilities\FlatMap;
use Nectar\Render\Render as Blocks_Render;

/**
 * Nectar Templates Register.
 * @since 2.0.0
 * @version 2.0.0
 */
class Nectar_Templates_Register {
  function __construct() {
    add_action( 'init', [$this, 'init'] );
  }

  public function init() {
    // Skip if Salient Core is active.
    if( defined('SALIENT_CORE_VERSION') ) {
      return;
    }

    // Skip if not using Nectarblocks theme.
    if ( ! defined('NB_THEME_VERSION') ) {
      return;
    }

    Render::get_instance();

    // Shortcode.
    add_shortcode('nectar_template', [$this, 'nectar_template_shortcode_callback'] );

    // Register post type.
    $this->register_post_type();

    // Main admin page.
    add_filter(
        'manage_edit-' . Nectar_Templates::POST_TYPE . '_columns',
        [$this, 'define_nectar_template_columns']
    );
    add_action(
        'manage_' . Nectar_Templates::POST_TYPE . '_posts_custom_column',
        [$this, 'nectar_section_admin_columns'],
        10,
        2
    );

    add_action('admin_head', function() {
      $screen = get_current_screen();
      if ($screen && $screen->post_type === 'nectar_templates' && $screen->id === 'edit-nectar_templates') {
        echo '<style data-type="nectar-template-admin-columns-css">' . $this->admin_columns_css() . '</style>';
      }
    });
  }

  /**
   * Registers the global section post type/tax.
   * @since 2.0.0
   * @version 2.0.0
   */
  public function register_post_type() {
    $post_type_labels = [
      'name' => esc_html__( 'Theme Builder', 'nectar-blocks' ),
      'singular_name' => esc_html__( 'Template', 'nectar-blocks' ),
      'search_items' => esc_html__( 'Search Templates', 'nectar-blocks' ),
      'all_items' => esc_html__( 'Templates', 'nectar-blocks' ),
      'parent_item' => esc_html__( 'Parent Template', 'nectar-blocks' ),
      'edit_item' => esc_html__( 'Edit Template', 'nectar-blocks' ),
      'update_item' => esc_html__( 'Update Template', 'nectar-blocks' ),
      'add_new_item' => esc_html__( 'Add New Template', 'nectar-blocks' ),
      'add_new' => esc_html__( 'Add New Template', 'nectar-blocks' ),
    ];

    $is_public = is_user_logged_in();
    $args = [
      'labels' => $post_type_labels,
      'singular_label' => esc_html__( 'Section', 'nectar-blocks' ),
      'public' => $is_public,
      'publicly_queryable' => $is_public,
      'rewrite' => false,
      'show_in_rest' => true,
      'exclude_from_search' => true,
      'show_ui' => true,
      'hierarchical' => true,
      'menu_position' => 20,
      'menu_icon' => 'dashicons-edit-page',
      'supports' => [ 'title', 'editor', 'revisions' , 'custom-fields' ],
    ];

    register_post_type( Nectar_Templates::POST_TYPE, $args );
  }

  /**
   * Define the nectar section columns.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $columns
   * @return array
   */
  public function define_nectar_template_columns($columns) {
    // Add global sections columns
    $columns['templatePart'] = __('Template', 'nectar-blocks');
    $columns['conditions'] = __('Conditions', 'nectar-blocks');

    // Remove default columns
    unset($columns['date']);

    return $columns;
  }

  /**
   * Display the nectar section admin columns.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $column
   * @param int $post_id
   */
  public function nectar_section_admin_columns($column, $post_id) {
    $post_meta = get_post_meta($post_id, Nectar_Templates::META_KEY, true);
    switch ($column) {
      case 'conditions':
        echo $this->get_display_conditions($post_meta);
        break;
      case 'templatePart':
        echo $this->get_display_template_part($post_meta);
        break;
    }
  }

  /**
   * Get the display conditions.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_conditions($post_meta): string {
    $conditions = isset($post_meta['conditions']) && is_array($post_meta['conditions']) ? $post_meta['conditions'] : [];
    if( empty($conditions) ) {
      return '';
    }
    $conditions_output = '<div class="condition-wrapper">';
    $operator = isset($post_meta['operator']) ? $post_meta['operator'] : 'and';

    $conditions_map = [];
    $conditions_list = FlatMap::flatMap(function($condition_list_item) {
      return $condition_list_item['options'];
    }, Nectar_Templates::get_conditions());
    foreach($conditions_list as $index => $condition) {
      $conditions_map[$condition['value']] = $condition['label'];
    }

    $include_map = [
      true => 'True',
      false => 'False',
    ];

    foreach($conditions as $index => $condition) {
      $condition_key = isset($condition['condition']) ? $condition['condition'] : '';
      if ( empty($condition_key) ) {
        continue;
      }
      // Get the labels
      if (array_key_exists($condition_key, $conditions_map)) {
        $condition_label = $conditions_map[$condition_key];
      } else {
        $condition_label = $condition_key;
      }
      $include_value = isset($condition['include']) ? $condition['include'] : true;
      $include_label = $include_map[$include_value];

      $value_label = $condition_label;

      if ( 'specific_post' === $condition_key ) {
        $post_data = isset($condition['postData']) ? $condition['postData'] : ( $condition['post_data'] ?? null );
        $post_display = '';

        if ( $post_data ) {
          if ( is_object($post_data) ) {
            $post_data = (array) $post_data;
          }
          $post_display = trim(
              ( isset($post_data['title']) ? sanitize_text_field($post_data['title']) : '' ) .
            ( isset($post_data['id']) ? ' (#' . intval($post_data['id']) . ')' : '' )
          );
        }

        if ( $post_display !== '' ) {
          $value_label .= ' — ' . $post_display;
        }
      }
      else if ( in_array($condition_key, ['is_taxonomy_term', 'has_taxonomy_term'], true ) ) {
        $taxonomy_data = isset($condition['taxonomyTermData']) ? $condition['taxonomyTermData'] : null;
        $taxonomy_display = '';

        if ( $taxonomy_data ) {
          if ( is_object($taxonomy_data) ) {
            $taxonomy_data = (array) $taxonomy_data;
          }
          $taxonomy_display = trim(implode(' ', array_filter([
            isset($taxonomy_data['name']) ? sanitize_text_field($taxonomy_data['name']) : '',
            isset($taxonomy_data['taxonomyLabel']) ? sanitize_text_field($taxonomy_data['taxonomyLabel']) : ( isset($taxonomy_data['taxonomy']) ? sanitize_key($taxonomy_data['taxonomy']) : '' ),
            isset($taxonomy_data['slug']) ? '(' . sanitize_title($taxonomy_data['slug']) . ')' : ''
          ])));
        }

        if ( $taxonomy_display !== '' ) {
          $value_label .= ' — ' . $taxonomy_display;
        }
      }

      if ( $value_label !== '' ) {
        $conditions_output .= '<div class="nectar-condition-badge">';
        $conditions_output .= '<span class="label">' . $include_label . '</span>';
        $conditions_output .= '<span class="value">' . $value_label . '</span>';
        $conditions_output .= '</div>';
      }

      // Add operator if not last condition
      if (count($conditions) !== $index + 1) {
        $conditions_output .= '<span class="nectar-condition-badge--operator"><span>' . esc_html($operator) . '</span></span>';
      }
    }

    $conditions_output .= '</div>';

    return $conditions_output;
  }

  /**
   * Get the display template part.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_template_part($post_meta) {
    $template_part = $post_meta['templatePart'];
    $template_part_output = $template_part;
    $conditions_list = Nectar_Templates::get_template_parts();

    $template_part_option = array_filter($conditions_list, function ($condition) use ($template_part) {
      return $condition['value'] === $template_part;
    });
    if (count($template_part_option) === 1) {
      $template_part_output = array_values($template_part_option)[0]['label'];
    }

    // Placeholder
    if ( $template_part === '' ) {
      $template_part_output = __('None Assigned', 'nectar-blocks');
    }

    return $template_part_output;
  }

  public function nectar_template_shortcode_callback($atts) {

    extract(shortcode_atts([
      "id" => "",
      'enable_display_conditions' => ''
    ], $atts));

    if (empty($id)) {
      return;
    }

    $section_id = intval($id);
    $section_id = apply_filters('wpml_object_id', $section_id, 'post', true);

    if( $section_id === 0 ) {
      return;
    }

    $section_status = get_post_status($section_id);
    $allow_output = true;

    if ( $enable_display_conditions === 'yes' ) {
      $allow_output = Render::get_instance()->verify_conditional_display( $section_id );
    }

    if ( 'publish' !== $section_status || ! $allow_output ) {
      return;
    }

    $section_content = get_post_field('post_content', $section_id);
    if (empty($section_content)) {
      return;
    }

    $unneeded_tags = [
      '<p>[' => '[',
      ']</p>' => ']',
      ']<br />' => ']',
      ']<br>' => ']',
    ];

    if( function_exists('do_blocks')) {
      $rendered_section_content = do_blocks($section_content);
    }
    $rendered_section_content = wptexturize( $rendered_section_content);
    $rendered_section_content = convert_smilies( $rendered_section_content );
    $rendered_section_content = shortcode_unautop( $rendered_section_content );
    $rendered_section_content = wp_filter_content_tags( $rendered_section_content );
    $rendered_section_content = strtr($rendered_section_content, $unneeded_tags);

    // Process images for proper loading attributes
    if (class_exists('WP_HTML_Tag_Processor')) {
        $processor = new \WP_HTML_Tag_Processor($rendered_section_content);
        $image_count = 0;

        while ($processor->next_tag(['tag_name' => 'img'])) {
            $image_count++;

            // Get current attributes
            $attributes = [
                'class' => $processor->get_attribute('class'),
                'src' => $processor->get_attribute('src'),
                'alt' => $processor->get_attribute('alt'),
                'width' => $processor->get_attribute('width'),
                'height' => $processor->get_attribute('height'),
                'loading' => $processor->get_attribute('loading'),
                'decoding' => $processor->get_attribute('decoding')
            ];

            // Get WordPress's automatic loading optimization attributes
            $loading_attrs = wp_get_loading_optimization_attributes('img', $attributes, 'wp_get_attachment_image');

            // For the first image, ensure high priority
            if ($image_count === 1) {
                $loading_attrs['fetchpriority'] = 'high';
                // remove loading attribute if it exists
                if ($processor->get_attribute('loading')) {
                    $processor->remove_attribute('loading');
                }
            }

            // For images after the second one, set loading to lazy if no loading attribute exists
            if ($image_count > 2 && ! $processor->get_attribute('loading') && ! $processor->get_attribute('fetchpriority')) {
                $loading_attrs['loading'] = 'lazy';
            }

            // Update image attributes
            foreach ($loading_attrs as $name => $value) {
                $processor->set_attribute($name, $value);
            }
        }

        $rendered_section_content = $processor->get_updated_html();
    }

    $rendered_section_content = apply_filters('nectar_global_section_content_output', $rendered_section_content);

    $global_section_markup = '';

    ob_start();
    // Look for dynamic CSS from blocks.
    $dynamic_css = get_post_meta( $section_id, '_nectar_blocks_css', true );

    if ( ! empty( $dynamic_css ) ) {
      $FE_RENDER = new Frontend_Render();
      $dynamic_css = $FE_RENDER->render_dynamic_content([], $dynamic_css);
    } else {
      $dynamic_css = '';
    }

    // Always check for nested patterns, even when no section-level CSS exists.
    if ($section_content) {
      $blocks = parse_blocks($section_content);
      $BLOCKS_RENDER_REFLECTION = new \ReflectionClass(Blocks_Render::class);
      $BLOCKS_RENDER = $BLOCKS_RENDER_REFLECTION->newInstanceWithoutConstructor();
      $patterns_css = $BLOCKS_RENDER->frontend_pattern_css($blocks);
      $dynamic_css .= $patterns_css;
    }

    if ( $dynamic_css !== '' ) {
      echo '<style data-type="nectar-template-dynamic-css">' . $dynamic_css . '</style>';
    }
    // Output section.
    echo do_shortcode($rendered_section_content);

    $global_section_markup .= ob_get_contents();
    ob_end_clean();

    return $global_section_markup;
  }

  public function admin_columns_css() {
    return '
      .edit-php .nectar-condition-badge {
        display: flex;
        gap: 8px;
        justify-content: space-between;
        align-items: center;
      }

      .edit-php .condition-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge span {
        background-color: #fff;
        border: 1px solid #ccc;
        transition: border-color 0.2s ease;
        border-radius: 100px;
        font-size: 11px;
        line-height: 1;
        font-weight: 400;
        padding: 3px 6px;
      }

      .edit-php .nectar-condition-badge--operator {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge--operator span {
        background-color: #fff;
        border: 1px solid #ccc;
        transition: border-color 0.2s ease;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 6px;
        line-height: 1;
        content: var(--and-text);
        text-transform: uppercase;
      }
    ';
  }
}
