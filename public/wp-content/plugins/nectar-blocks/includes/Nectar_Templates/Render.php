<?php

namespace Nectar\Nectar_Templates;

use Nectar\Nectar_Templates\Nectar_Templates;

/**
 * Render Nectar Templates.
 * @since 2.0.0
 * @version 2.0.0
 */
class Render {
  private static $instance;

  public static $exclude = false;

  public static $post_type;

  public static $post_id;

  private function __construct() {
    add_action( 'wp', [$this, 'frontend_display'] );
  }

  public static function get_instance() {
    if (! self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function frontend_display() {
    // store post type and id outside of global section query
    // to reflect real post type and id
    if ( ! is_admin() ) {
      self::$post_type = get_post_type();
      self::$post_id = get_the_id();
    } else {
      return;
    }

    $this->render_template();
  }

  /**
   * Parse conditional statement.
   *
   * @param string $conditional
   * @param bool $include_exclude
   * @param array|object|null $meta_data
   * @return bool
   */
  public function parse_conditional($conditional, $include_exclude, $meta_data = null) {
    if ( ! is_string($conditional) ) {
      return true;
    }

    $display = true;

    if( 'is_single' === $conditional ) {
      $display = is_single();
    }
    else if( 'is_archive' === $conditional ) {
      $display = is_archive();
    }
    else if( 'is_search' === $conditional ) {
      $display = is_search();
    }
    else if( 'is_front_page' === $conditional ) {
      $display = is_front_page();
    }
    else if( 'is_user_logged_in' === $conditional ) {
      $display = is_user_logged_in();
    }
    else if( 'is_user_not_logged_in' === $conditional ) {
      $display = ! is_user_logged_in();
    }
    else if( 'is_taxonomy_term' === $conditional || 'has_taxonomy_term' === $conditional ) {
      $display = false;
      $term_id = 0;
      $term_slug = '';
      $taxonomy = '';

      if ( is_array($meta_data) ) {
        $term_id = isset($meta_data['id']) ? intval($meta_data['id']) : 0;
        $term_slug = isset($meta_data['slug']) ? sanitize_title($meta_data['slug']) : '';
        $taxonomy = isset($meta_data['taxonomy']) ? sanitize_key($meta_data['taxonomy']) : '';
      } else if ( is_object($meta_data) ) {
        $term_id = isset($meta_data->id) ? intval($meta_data->id) : 0;
        $term_slug = isset($meta_data->slug) ? sanitize_title($meta_data->slug) : '';
        $taxonomy = isset($meta_data->taxonomy) ? sanitize_key($meta_data->taxonomy) : '';
      }

      if ( empty($taxonomy) ) {
        return true;
      }

      $term_identifier = $term_id > 0 ? $term_id : ( ! empty($term_slug) ? $term_slug : '' );
      $is_tax_archive = false;

      if ( 'category' === $taxonomy ) {
        $is_tax_archive = $term_identifier !== '' ? is_category($term_identifier) : is_category();
      } else if ( 'post_tag' === $taxonomy ) {
        $is_tax_archive = $term_identifier !== '' ? is_tag($term_identifier) : is_tag();
      } else if ( taxonomy_exists($taxonomy) ) {
        $is_tax_archive = $term_identifier !== '' ? is_tax($taxonomy, $term_identifier) : is_tax($taxonomy);
      }

      if ( 'is_taxonomy_term' === $conditional ) {
        $display = $is_tax_archive;
      } else if ( 'has_taxonomy_term' === $conditional ) {
        $has_term_assigned = false;

        if ( '' === $term_identifier ) {
          return true;
        }

        if ( 'category' === $taxonomy ) {
          $has_term_assigned = is_singular() && has_category($term_identifier, self::$post_id);
        } else if ( 'post_tag' === $taxonomy ) {
          $has_term_assigned = is_singular() && has_tag($term_identifier, self::$post_id);
        } else if ( taxonomy_exists($taxonomy) ) {
          $has_term_assigned = is_singular() && has_term($term_identifier, $taxonomy, self::$post_id);
        }

        $display = $is_tax_archive || $has_term_assigned;
      }
    }
    else if( strpos($conditional, 'post_type__') !== false ) {

      $post_type = str_replace('post_type__', '', $conditional);
      if ( self::$post_type === $post_type ) {
        $display = true;
      } else {
        $display = false;
      }
    }
    else if( strpos($conditional, 'single__pt__') !== false ) {

      $post_type = str_replace('single__pt__', '', $conditional);
      if ( self::$post_type === $post_type && is_single() ) {
        $display = true;
      } else {
        $display = false;
      }
    }
    else if( strpos($conditional, 'role__') !== false ) {
      $role = str_replace('role__', '', $conditional);

      if ( current_user_can( $role ) ) {
        $display = true;
      } else {
        $display = false;
      }
    }
    else if( 'everywhere' === $conditional ) {
      $display = true;
    }

    // If excluded, short circuit and prevent display.
    if ( $include_exclude === false && $display ) {
      self::$exclude = true;
    }

    if ( $include_exclude === false && ! self::$exclude ) {
      $display = true;
    }

    return $display;
  }

  /**
   * Render Nectar Template
   */
  public function render_template() {
    // Disabled on cpt single edit.
    if ( Nectar_Templates::POST_TYPE === get_post_type() ) {
      return;
    }

    $global_sections_query_args = [
      'post_type' => Nectar_Templates::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'no_found_rows' => true
    ];

    $global_sections_query = new \WP_Query( $global_sections_query_args );

    if( $global_sections_query->have_posts() ) : while( $global_sections_query->have_posts() ) : $global_sections_query->the_post();

      $global_section_id = get_the_ID();
      $post_meta = get_post_meta($global_section_id, Nectar_Templates::META_KEY, true);

      $location = $post_meta['templatePart'];
      $location_hook = sanitize_text_field($location);
      $location_priority = 10;

      // Verify display conditions.
      $allow_output = $this->verify_conditional_display($global_section_id);

      // Add section to hook.
      if ( $allow_output ) {
        add_action(
            $location_hook,
            function() use ( $global_section_id, $location_hook ) {
            $this->output_global_section($global_section_id, $location_hook);
          },
            $location_priority
        );

      }

    endwhile; endif;

    wp_reset_query();
  }

  /**
   * Conditional Logic for global section output.
   */
  public function verify_conditional_display($global_section_id) {
    // Gather and format Conditions to be used in final output below.
    $post_meta = get_post_meta($global_section_id, Nectar_Templates::META_KEY, true);
    $conditions = isset($post_meta['conditions']) && is_array($post_meta['conditions']) ? $post_meta['conditions'] : [];
    $condition_operator = isset($post_meta['operator']) ? $post_meta['operator'] : 'and';
    self::$exclude = false;

    // Verify display conditions.
    $conditionals = [];
    foreach($conditions as $condition) {
      $conditional_value = isset($condition['condition']) ? $condition['condition'] : '';
      if ( empty($conditional_value) ) {
        continue;
      }
      $include_value = isset($condition['include']) ? $condition['include'] : true;
      $meta_data = isset($condition['taxonomyTermData']) ? $condition['taxonomyTermData'] : null;
      $conditionals[] = $this->parse_conditional($conditional_value, $include_value, $meta_data);
    }

    // If no conditions, allow output.
    $allow_output = empty($conditionals);

    if( self::$exclude === true ) {
      return apply_filters( 'salient_global_section_allow_display', $allow_output );
    }

    foreach ($conditionals as $condition) {
      if ($condition === true) {
        $allow_output = true;
      }
    }

    // operator is 'and' and one of the conditions is false, prevent output.
    if ( $condition_operator === 'and' && in_array(false, $conditionals) ) {
      $allow_output = false;
    }

    return apply_filters( 'salient_global_section_allow_display', $allow_output );
  }

  /**
   * Frontend output.
   */
  public function output_global_section($global_section_id, $location) {

    if ( $this->omit_global_section_render($location) ) {
      return;
    }

    $attrs = apply_filters('nectar_global_section_attrs', [
      'class' => 'nectar-global-section ' . $location
    ], $location);

    $inner_attrs = apply_filters('nectar_global_section_inner_attrs', [
      'class' => 'container normal-container row'
    ], $location);

    $attributes = join(' ', array_map(function($key) use ($attrs) {
      if(is_bool($attrs[$key])) {
        return $attrs[$key] ? $key : '';
      }
      return $key . '="' . $attrs[$key] . '"';
    }, array_keys($attrs)));

    $inner_attributes = join(' ', array_map(function($key) use ($inner_attrs) {
      if(is_bool($inner_attrs[$key])) {
        return $inner_attrs[$key] ? $key : '';
      }
      return $key . '="' . $inner_attrs[$key] . '"';
    }, array_keys($inner_attrs)));

    $global_section_shortcode = ' [nectar_template id="' . intval($global_section_id) . '"] ';

    echo do_shortcode('<div ' . $attributes . '><div ' . $inner_attributes . '>' . $global_section_shortcode . '</div></div>');
  }

  public function omit_global_section_render( $hook ) {
    // No Footer Templates.
    $footer_hooks = [
      'nectar_hook_global_section_footer',
      'nectar_hook_global_section_parallax_footer',
      'nectar_hook_global_section_after_footer'
    ];
    if (
      is_page_template( 'template-no-footer.php' ||
      is_page_template( 'template-no-header-footer.php' )) && in_array( $hook, $footer_hooks )
    ) {
      return true;
    }

    // Disabled locations when using contained header.
    if ( function_exists('nectar_is_contained_header') && nectar_is_contained_header() ) {
      $contained_header_non_compat_hooks = [
        'nectar_hook_before_secondary_header',
      ];
      if ( in_array( $hook, $contained_header_non_compat_hooks ) ) {
        return true;
      }
    }

    return false;
  }
}
