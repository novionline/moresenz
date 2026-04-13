<?php

namespace Nectar\Global_Sections;

use Nectar\Global_Sections\Render;
use Nectar\Global_Sections\Visual_Hook_Locations;
use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Utilities\FlatMap;
use Nectar\Render\Render as Blocks_Render;

/**
 * Global Sections Register.
 * @since 0.1.4
 * @version 2.0.0
 */
class Global_Sections_Register {
  private static $instance;

  function __construct() {
    add_action( 'init', [$this, 'init'] );
  }

  public static function get_instance() {
    if ( ! self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function init() {

    // Skip if Salient Core is active.
    if ( defined('SALIENT_CORE_VERSION') ) {
      return;
    }

    // Skip if not using Nectarblocks theme.
    if ( ! defined('NB_THEME_VERSION') ) {
      return;
    }

    // Init classes.
    Visual_Hook_Locations::get_instance();
    Render::get_instance();

    // Shortcode.
    add_shortcode('nectar_global_section', [$this, 'global_section_shortcode_callback'] );

    // Register post type.
    $this->register_post_type();

    // Main admin page.
    add_filter('manage_edit-nectar_sections_columns', [$this, 'define_nectar_section_columns']);
    add_action('manage_nectar_sections_posts_custom_column', [$this, 'nectar_section_admin_columns'], 10, 2);
    add_action('admin_head', function() {
      $screen = get_current_screen();
      if ($screen && $screen->post_type === 'nectar_sections' && $screen->id === 'edit-nectar_sections') {
        echo '<style data-type="nectar-global-section-admin-columns-css">' . $this->admin_columns_css() . '</style>';
      }
    });

    add_filter('allowed_block_types_all', function ($allowed_blocks, $block_editor_context) {

      $all_blocks = [];
      $disallowed_blocks = [];
      $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

      foreach ( $registered_blocks as $registered_block ) {
        $all_blocks[] = $registered_block->name;
      }

      // Limit template based blocks to global sections
      if (! empty($block_editor_context->post) && isset($block_editor_context->post->post_type)) {
        $post_type = $block_editor_context->post->post_type;

        // Allow all blocks for the "nectar_sections" post type
        if ($post_type !== 'nectar_templates') {
          $disallowed_blocks = [
            'nectar-blocks/post-content',
          ];
        }
      }

      // Return allowed blocks.
      $allowed_block_types = array_values( array_diff( $all_blocks, $disallowed_blocks ) );

      return $allowed_block_types;

    }, 10, 2);
  }

  /**
  * Registers the global section post type/tax.
  * @since 0.1.4
  * @version 2.0.0
  */
  public function register_post_type() {
    $post_type_labels = [
      'name' => esc_html__( 'Global Sections', 'nectar-blocks' ),
      'singular_name' => esc_html__( 'Global Section', 'nectar-blocks' ),
      'search_items' => esc_html__( 'Search Global Sections', 'nectar-blocks' ),
      'all_items' => esc_html__( 'Global Sections', 'nectar-blocks' ),
      'parent_item' => esc_html__( 'Parent Global Section', 'nectar-blocks' ),
      'edit_item' => esc_html__( 'Edit Global Section', 'nectar-blocks' ),
      'update_item' => esc_html__( 'Update Global Section', 'nectar-blocks' ),
      'add_new_item' => esc_html__( 'Add New Global Section', 'nectar-blocks' ),
      'add_new' => esc_html__( 'Add New Global Section', 'nectar-blocks' ),
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
      'menu_icon' => 'dashicons-layout',
      'supports' => [ 'title', 'editor', 'revisions' , 'custom-fields'],
    ];

    register_post_type( 'nectar_sections', $args );
  }

  /**
   * Define the nectar section columns.
   * @since 0.1.4
   * @version 2.0.0
   * @param array $columns
   * @return array
   */
  public function define_nectar_section_columns($columns) {
    // Add global sections columns
    $columns['display_locations'] = __('Locations', 'nectar-blocks');
    $columns['conditions'] = __('Conditions', 'nectar-blocks');
    // Remove default columns
    unset($columns['date']);

    return $columns;
  }

  /**
   * Display the nectar section admin columns.
   * @since 0.1.4
   * @version 2.0.0
   * @param string $column
   * @param int $post_id
   */
  public function nectar_section_admin_columns($column, $post_id) {
    $post_meta = get_post_meta($post_id, Global_Sections::META_KEY, true);
    switch ($column) {
      case 'conditions':
        echo $this->get_display_conditions($post_meta);
        break;
      case 'display_locations':
        echo $this->get_display_locations($post_meta);
        break;
    }
  }

  /**
   * Get the display conditions.
   * @since 0.1.4
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_conditions($post_meta): string {
    $conditions = [];
    if ( isset($post_meta['conditions']) && is_array($post_meta['conditions']) ) {
      $conditions = $post_meta['conditions'];
    }
    if( empty($conditions) ) {
      return '';
    }
    $conditions_output = '<div class="condition-wrapper">';
    $operator = isset($post_meta['operator']) ? $post_meta['operator'] : 'and';

    $conditions_map = [];
    $conditions_list = FlatMap::flatMap(function($condition_list_item) {
      return $condition_list_item['options'];
    }, Global_Sections::get_conditions());
    foreach($conditions_list as $index => $condition) {
      $conditions_map[$condition['value']] = $condition['label'];
    }

    $include_map = [
      true => 'True',
      false => 'False',
    ];

    foreach($conditions as $index => $condition) {
      $condition_key = isset($condition['condition']) ? $condition['condition'] : '';
      // Get the labels
      if (array_key_exists($condition_key, $conditions_map)) {
        $condition_label = $conditions_map[$condition_key];
      } else if ( ! empty($condition_key) ) {
        $condition_label = $condition_key;
      } else {
        $condition_label = '';
      }
      $include_value = isset($condition['include']) ? (bool) $condition['include'] : true;
      $include_label = $include_map[$include_value];

      if ( 'specific_post' === $condition_key ) {
        $post_value = $condition['postData'] ?? $condition['post_data'] ?? null;
        $post_display = '';

        if ( $post_value ) {
          if ( is_object($post_value) ) {
            $post_value = (array) $post_value;
          }
          $post_display = trim(
              ( isset($post_value['title']) ? sanitize_text_field($post_value['title']) : '' ) .
            ( isset($post_value['id']) ? ' (#' . intval($post_value['id']) . ')' : '' )
          );
        }

        if ( $post_display !== '' ) {
          $condition_label .= ' — ' . $post_display;
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
          $condition_label .= ' — ' . $taxonomy_display;
        }
      }

      if ( $condition_label !== '' ) {
      $conditions_output .= '<div class="nectar-condition-badge">';
      $conditions_output .= '<span class="label">' . $include_label . '</span>';
      $conditions_output .= '<span class="value">' . $condition_label . '</span>';
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
   * Get the display locations.
   * @since 0.1.4
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_locations($post_meta) {
    $locations = $post_meta['locations'];
    $locations_list = Global_Sections::get_locations();

    $location_labels = [];
    $locations_list_flat = FlatMap::flatMap(function($location_list_item) {
      return $location_list_item['options'];
    }, $locations_list);

    foreach ($locations as $location) {
      $template_part_option = array_filter($locations_list_flat, function ($location_list_item) use ($location) {
        return $location_list_item['value'] === $location['location'];
      });
      if (count($template_part_option) === 1) {
        array_push($location_labels, array_values($template_part_option)[0]['label']);
      } else {
        array_push($location_labels, $location['location']);
      }
    }
    return implode('', array_map(fn($x) => '<div class="location">' . $x . '</div>', $location_labels));
  }

  public function global_section_shortcode_callback($atts) {

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

    // Pattern CSS should run even if the section itself has no saved CSS.
    if ($section_content) {
      $blocks = parse_blocks($section_content);
      $BLOCKS_RENDER_REFLECTION = new \ReflectionClass(Blocks_Render::class);
      $BLOCKS_RENDER = $BLOCKS_RENDER_REFLECTION->newInstanceWithoutConstructor();
      $patterns_css = $BLOCKS_RENDER->frontend_pattern_css($blocks);
      $dynamic_css .= $patterns_css;
    }

    if ( $dynamic_css !== '' ) {
      echo '<style data-type="nectar-global-section-dynamic-css">' . $dynamic_css . '</style>';
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
