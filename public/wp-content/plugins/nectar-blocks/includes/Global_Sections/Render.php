<?php

namespace Nectar\Global_Sections;

/**
 * Render Global Sections.
 * @since 0.1.4
 * @version 2.0.0
 */
class Render {
  private static $instance;

  public $exclude = false;

  public $post_type;

  public $post_id;

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
      $this->post_type = get_post_type();
      $this->post_id = get_the_id();
    } else {
      return;
    }

    $this->render_global_sections();
    $this->render_global_section_filters();
  }

  /**
   * Parse conditional statement.
   *
   * @param string $conditional
   * @param bool $is_included
   * @param array|object|null $meta_data
   * @return bool
   */
  public function parse_conditional($conditional, $is_included, $meta_data = null) {
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
    else if( 'specific_post' === $conditional ) {
      $display = false;
      $selected_post_id = 0;

      if ( is_array($meta_data) && isset($meta_data['id']) ) {
        $selected_post_id = intval($meta_data['id']);
      } else if ( is_object($meta_data) && isset($meta_data->id) ) {
        $selected_post_id = intval($meta_data->id);
      }

      $is_selected_singular = (
          $selected_post_id > 0
        && is_singular()
        && intval($this->post_id) === $selected_post_id
      );

      $posts_page_id = intval( get_option('page_for_posts') );
      // When the selected page is set as the Posts page, is_home() is true and is_singular() is false.
      $is_selected_posts_page = (
          $selected_post_id > 0
        && is_home()
        && $posts_page_id === $selected_post_id
      );

      $display = $is_selected_singular || $is_selected_posts_page;
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
          $has_term_assigned = is_singular() && has_category($term_identifier, $this->post_id);
        } else if ( 'post_tag' === $taxonomy ) {
          $has_term_assigned = is_singular() && has_tag($term_identifier, $this->post_id);
        } else if ( taxonomy_exists($taxonomy) ) {
          $has_term_assigned = is_singular() && has_term($term_identifier, $taxonomy, $this->post_id);
        }

        $display = $is_tax_archive || $has_term_assigned;
      }
    }
    else if( strpos($conditional, 'post_type__') !== false ) {

      $post_type = str_replace('post_type__', '', $conditional);
      if ( $this->post_type === $post_type ) {
        $display = true;
      } else {
        $display = false;
      }
    }
    else if( strpos($conditional, 'single__pt__') !== false ) {

      $post_type = str_replace('single__pt__', '', $conditional);
      if ( $this->post_type === $post_type && is_single() ) {
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
    if ( $is_included === false && $display ) {
      $this->exclude = true;
    }

    if ( $is_included === false && ! $this->exclude ) {
      $display = true;
    }

    return $display;
  }

  /**
   * Render Global Section
   */
  public function render_global_sections() {

    // Disabled on cpt single edit.
    if ( Global_Sections::POST_TYPE === get_post_type() ) {
      return;
    }

    $global_sections_query_args = [
      'post_type' => Global_Sections::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'no_found_rows' => true
    ];

    $global_sections_query = new \WP_Query( $global_sections_query_args );

    if( $global_sections_query->have_posts() ) : while( $global_sections_query->have_posts() ) : $global_sections_query->the_post();

      $global_section_id = get_the_ID();
      $post_meta = get_post_meta($global_section_id, Global_Sections::META_KEY, true);

      // Locations.
      $locations = $post_meta['locations'];
      if( empty( $locations ) || ! is_array($locations) ) {
        continue;
      }

      foreach($locations as $location) {

        $location_options = (array) $location;
        $location_hook = sanitize_text_field($location_options['location']);
        $location_priority = sanitize_text_field($location_options['priority']);

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

          $this->modify_salient_markup($location_hook);
        }

      } // end foreach locations.

    endwhile; endif;

    wp_reset_query();
  }

  /**
   * Conditional Logic for global section output.
   */
  public function verify_conditional_display($global_section_id) {
    // Gather and format Conditions to be used in final output below.
    $post_meta = get_post_meta($global_section_id, Global_Sections::META_KEY, true);
    $conditions = [];
    $condition_operator = 'and';

    if ( is_array($post_meta) ) {
      $conditions = isset($post_meta['conditions']) && is_array($post_meta['conditions']) ? $post_meta['conditions'] : [];
      if ( isset($post_meta['operator']) ) {
        $condition_operator = sanitize_text_field($post_meta['operator']);
      }
    }
    $condition_operator = in_array($condition_operator, ['and', 'or'], true) ? $condition_operator : 'and';

    $this->exclude = false;

    // Verify display conditions.
    $conditionals = [];
    foreach($conditions as $condition) {
      $conditional_value = isset($condition['condition']) ? $condition['condition'] : '';
      if ( empty($conditional_value) || ! is_string($conditional_value) ) {
        continue;
      }
      $include_value = isset($condition['include']) ? (bool) $condition['include'] : true;

      $meta_data = null;
      if ( 'specific_post' === $conditional_value ) {
        if ( isset($condition['postData']) ) {
          $meta_data = $condition['postData'];
        } else if ( isset($condition['post_data']) ) {
          $meta_data = $condition['post_data'];
        }
      }
      else if ( in_array($conditional_value, ['is_taxonomy_term', 'has_taxonomy_term'], true) ) {
        if ( isset($condition['taxonomyTermData']) ) {
          $meta_data = $condition['taxonomyTermData'];
        }
      }

      $conditionals[] = $this->parse_conditional($conditional_value, $include_value, $meta_data);
    }

    // If no conditions, allow output.
    $allow_output = empty($conditionals);

    if( $this->exclude === true ) {
      return apply_filters( 'salient_global_section_allow_display', $allow_output );
    }

    foreach ($conditionals as $condition) {
      if ($condition === true) {
        $allow_output = true;
      }
    }

    // Operator is 'and' and one of the conditions is false, prevent output.
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

    $global_section_shortcode = ' [nectar_global_section id="' . intval($global_section_id) . '"] ';

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

  /**
   * Frontend output markup alterations.
   */
  public function render_global_section_filters() {

    add_filter('nectar_global_section_inner_attrs', function($attrs, $location) {

      if( 'nectar_hook_global_section_parallax_footer' === $location ) {
        $attrs['class'] .= ' nectar-el-parallax-scroll';
        $attrs['data-scroll-animation'] = 'true';
        $attrs['data-scroll-animation-intensity'] = '-5';
      }

      return $attrs;
    }, 10, 3);
  }

  /**
   * Changes to Salient markup based on certain global sections being active.
   */
  public function modify_salient_markup($hook) {
    // Calculate nectar_hook_before_secondary_header height asap.
    if ( $hook === 'nectar_hook_before_secondary_header' &&
      function_exists('nectar_is_contained_header') &&
      ! nectar_is_contained_header() ) {
      add_action('nectar_hook_before_secondary_header', function(){
        echo '<script>
          var contentHeight = 0;
          var headerHooks = document.querySelectorAll(".nectar_hook_before_secondary_header");

          if( headerHooks ) {

            Array.from(headerHooks).forEach(function(el){
              contentHeight += el.getBoundingClientRect().height;
            });
          }

          document.documentElement.style.setProperty("--before_secondary_header_height", contentHeight + "px");
        </script>';
      }, 99);
    }

    // Global sections that disabled transparent header.
    $transparent_non_compat_hooks = [
      'nectar_hook_global_section_after_header_navigation',
    ];

    if( in_array( $hook, $transparent_non_compat_hooks ) ) {

      if ( function_exists('nectar_is_contained_header') && ! nectar_is_contained_header() ) {
        add_filter('nectar_activate_transparent_header', [$this,'after_header_navigation_remove_transparency'], 70);
      }
    }
  }

  public function after_header_navigation_remove_transparency() {
    return false;
  }
}
