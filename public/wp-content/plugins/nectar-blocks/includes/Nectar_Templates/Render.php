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

  /**
   * IDs of header-navigation templates whose conditions matched for the
   * current request. Populated during `render_template()` and consumed by
   * the frontend CSS pipeline to emit the `#nectar-nav` state-transition
   * baseline scoped to this page's active header.
   *
   * @var int[]
   */
  private static $active_header_template_ids = [];

  public static function get_active_header_template_ids(): array {
    return self::$active_header_template_ids;
  }

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
        && intval(self::$post_id) === $selected_post_id
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
    // When previewing a nectar_templates post directly, skip only the
    // template being previewed to avoid recursion. Other templates
    // (e.g., header navigation during an OCM preview) should still render.
    $previewing_template_id = null;
    if ( Nectar_Templates::POST_TYPE === get_post_type() ) {
      $previewing_template_id = get_the_ID();
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

      // Skip the template being previewed to avoid rendering itself.
      if ( $previewing_template_id && $global_section_id === $previewing_template_id ) {
        continue;
      }

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

        $this->maybe_suppress_theme_comments($location_hook, $global_section_id);

        if ( $location_hook === 'nectar_template__header_navigation' ) {
          self::$active_header_template_ids[] = $global_section_id;
        }
      }

    endwhile; endif;

    wp_reset_query();
  }

  /**
   * The theme appends its default comments area after single templates
   * (post-after-content.php). When the template already renders a comments
   * block, suppress the theme's copy so comments don't appear twice.
   *
   * Scoped to the single hook the theme fires for the current request's post
   * type — templates registered for other post types must not leak the
   * suppression onto posts they never render on.
   *
   * @return bool Whether the theme comments were suppressed.
   */
  public function maybe_suppress_theme_comments($location_hook, $template_id): bool {
    if ( $location_hook !== 'nectar_template_single__' . self::$post_type ) {
      return false;
    }

    $content = get_post_field('post_content', $template_id);
    if ( ! $content ) {
      return false;
    }

    foreach ( [ 'core/comments', 'core/post-comments', 'core/post-comments-form' ] as $block ) {
      if ( has_block($block, $content) ) {
        add_filter( 'nectar_single_show_comments', '__return_false' );
        return true;
      }
    }

    return false;
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

      $meta_data = null;
      if ( 'specific_post' === $conditional_value ) {
        // JS serialises this under `postData`; `post_data` is the snake_case
        // fallback used elsewhere (Global Sections render + both sanitisers).
        if ( isset($condition['postData']) ) {
          $meta_data = $condition['postData'];
        } else if ( isset($condition['post_data']) ) {
          $meta_data = $condition['post_data'];
        }
      } else if ( in_array($conditional_value, ['is_taxonomy_term', 'has_taxonomy_term'], true) ) {
        $meta_data = isset($condition['taxonomyTermData']) ? $condition['taxonomyTermData'] : null;
      }

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
   * Location-specific outer and inner attribute overrides.
   *
   * Locations not listed here use the default single-div markup.
   * Locations listed here get a two-div structure (outer + inner)
   * to match the Global Sections rendering for that hook.
   *
   * @since 3.0.0
   * @return array|null Null for default markup, or ['outer' => [...], 'inner' => [...]]
   */
  private function get_location_attrs( string $location ): ?array {
    $map = [
      'nectar_hook_global_section_parallax_footer' => [
        'outer' => [
          'class' => 'nectar-global-section ' . $location,
        ],
        'inner' => [
          'class' => 'container normal-container row nectar-el-parallax-scroll',
          'data-scroll-animation' => 'true',
          'data-scroll-animation-intensity' => '-5',
        ],
      ],
    ];

    return $map[$location] ?? null;
  }

  /**
   * Get the semantic HTML element for a given template location.
   *
   * @since 3.0.1
   */
  private function get_semantic_tag( string $location ): string {
    if ( $location === 'nectar_template__header_navigation' ) {
      return 'header';
    }

    if ( in_array($location, ['nectar_hook_global_section_footer', 'nectar_hook_global_section_parallax_footer'], true) ) {
      return 'footer';
    }

    return 'div';
  }

  /**
   * Whether the current page uses the left-header layout without a header builder template.
   *
   * @since 3.0.1
   */
  private function is_left_header_without_builder(): bool {
    if ( function_exists('nectar_has_header_nav_template') && nectar_has_header_nav_template() ) {
      return false;
    }

    if ( ! function_exists('get_nectar_theme_options') ) {
      return false;
    }

    $nectar_options = get_nectar_theme_options();
    $header_format = ! empty( $nectar_options['header_format'] ) ? $nectar_options['header_format'] : 'default';

    return 'left-header' === $header_format;
  }

  /**
   * Build an HTML attribute string from an associative array.
   *
   * @since 3.0.0
   */
  private function build_attr_string( array $attrs ): string {
    return join(' ', array_map(function($key) use ($attrs) {
      if(is_bool($attrs[$key])) {
        return $attrs[$key] ? $key : '';
      }
      return esc_attr( $key ) . '="' . esc_attr( $attrs[$key] ) . '"';
    }, array_keys($attrs)));
  }

  /**
   * Frontend output.
   */
  public function output_global_section($global_section_id, $location) {

    if ( $this->omit_global_section_render($location) ) {
      return;
    }

    $global_section_shortcode = ' [nectar_template id="' . intval($global_section_id) . '"] ';
    $location_attrs = $this->get_location_attrs($location);
    $tag = $this->get_semantic_tag($location);

    if ( $location_attrs ) {
      $outer = apply_filters('nectar_global_section_attrs', $location_attrs['outer'], $location);
      $inner = $location_attrs['inner'];

      echo do_shortcode(
          '<' . $tag . ' ' . $this->build_attr_string($outer) . '>' .
          '<div ' . $this->build_attr_string($inner) . '>' .
            $global_section_shortcode .
          '</div>' .
        '</' . $tag . '>'
      );
      return;
    }

    // Left-header without header builder needs nested container markup.
    if ( $this->is_left_header_without_builder() ) {
      $outer = apply_filters('nectar_global_section_attrs', [
        'class' => $location . ' nectar-global-section',
      ], $location);

      echo do_shortcode(
          '<' . $tag . ' ' . $this->build_attr_string($outer) . '>' .
        '<div class="container normal-container row">' .
          $global_section_shortcode .
        '</div>' .
        '</' . $tag . '>'
      );
      return;
    }

    $attrs = apply_filters('nectar_global_section_attrs', [
      'class' => $location . ' nectar-global-section container normal-container row'
    ], $location);

    echo do_shortcode('<' . $tag . ' ' . $this->build_attr_string($attrs) . '>' . $global_section_shortcode . '</' . $tag . '>');
  }

  public function omit_global_section_render( $hook ) {
    // No Footer Templates.
    $footer_hooks = [
      'nectar_hook_global_section_footer',
      'nectar_hook_global_section_parallax_footer',
      'nectar_hook_global_section_after_footer'
    ];
    if (
      ( is_page_template( 'template-no-footer.php' ) ||
        is_page_template( 'template-no-header-footer.php' ) ) &&
      in_array( $hook, $footer_hooks )
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
