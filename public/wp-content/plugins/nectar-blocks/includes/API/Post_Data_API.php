<?php

namespace Nectar\API;

use Nectar\API\{Router, API_Route};
use Nectar\Dynamic_Data\Sources\ACF;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Global_Sections\Global_Sections;
/**
 * Post Data API
 * @version 2.0.0
 * @since 0.0.9
 */
class Post_Data_API implements API_Route {
  const API_BASE = '/post-data';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/taxonomies', [
      'callback' => [$this, 'get_taxonomies'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'postType' => [
          'type' => 'string',
          'required' => false,
          'description' => 'Post ID.'
        ]
      ]
    ]);

    Router::add_route($this::API_BASE . '/taxonomy-names', [
      'callback' => [$this, 'get_taxonomy_names'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'postType' => [
          'type' => 'array',
          'required' => false,
          'description' => 'Post Type.'
        ]
      ]
    ]);

    Router::add_route($this::API_BASE . '/post-types', [
      'callback' => [$this, 'get_post_types'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/post-types-for-template-part', [
      'callback' => [$this, 'get_post_types_for_template_part'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/image-sizes', [
      'callback' => [$this, 'get_image_sizes'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/other-posts', [
      'callback' => [$this, 'get_other_posts'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/dynamic-fields', [
      'callback' => [$this, 'get_dynamic_fields'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'postId' => [
          'type' => 'string',
          'required' => false,
          'description' => 'Post Type for which to get taxonomies.'
        ]
      ]
    ]);

    Router::add_route($this::API_BASE . '/conditions', [
      'callback' => [$this, 'get_conditions'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'postType' => [
          'type' => 'string',
          'required' => true,
          'description' => 'Post Type for which to get conditions.'
        ]
      ]
    ]);

    Router::add_route($this::API_BASE . '/locations', [
      'callback' => [$this, 'get_locations'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/search-posts', [
      'callback' => [$this, 'search_posts'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'search' => [
          'type' => 'string',
          'required' => false,
          'description' => 'Term used to search posts by ID, title, or slug.'
        ],
      ]
    ]);

    Router::add_route($this::API_BASE . '/search-taxonomy-terms', [
      'callback' => [$this, 'search_taxonomy_terms'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      },
      'args' => [
        'search' => [
          'type' => 'string',
          'required' => false,
          'description' => 'Term used to search for taxonomy terms by name or slug.'
        ],
        'taxonomy' => [
          'type' => 'string',
          'required' => false,
          'description' => 'Specific taxonomy to limit the search results.'
        ],
      ]
    ]);
  }

  public function get_taxonomies(\WP_REST_Request $request) {

    $post_type = sanitize_text_field($request->get_param( 'postType' ));

    $taxonomies = get_object_taxonomies( $post_type, 'objects' );
    // Skip visually hidden taxonomies.
    $ui_taxonomies = array_filter($taxonomies, function($taxonomy) {
      return $taxonomy->public && $taxonomy->show_ui;
    });
    $taxonomy_names = array_keys($ui_taxonomies);

    $taxonomy_to_terms = [];
    foreach ( $taxonomy_names as $taxonomy ) {
      $terms = get_terms( $taxonomy );
      $terms_filtered = array_map(fn ($item) => [
        "term_id" => $item->term_id,
        "name" => $item->name,
        "slug" => $item->slug
      ], $terms);
      $taxonomy_to_terms[$taxonomy] = array_values($terms_filtered);
    }
    $response = new \WP_REST_Response($taxonomy_to_terms, 200);
    return $response;
  }

  /**
   * Get the taxonomy names for the given post types.
   * @since 2.0.0
   * @version 2.0.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_taxonomy_names(\WP_REST_Request $request) {
    $post_types = $request->get_param('postType');

    if (empty($post_types) || ! is_array($post_types)) {
      return new \WP_REST_Response([
        'success' => false,
        'message' => 'Invalid or missing postType parameter.',
      ], 400);
    }

    $sanitized_post_types = [];
    foreach ($post_types as $key => $value) {
      $sanitized_key = sanitize_text_field($key);
      $sanitized_post_types[$sanitized_key] = $value;
    }

    if ( count($sanitized_post_types) === 1 && reset($sanitized_post_types) === 'all' ) {
        $sanitized_post_types = get_post_types( [ 'public' => true ], 'names' );
    }

    $taxonomies = get_object_taxonomies($sanitized_post_types, 'objects');

    if (empty($taxonomies)) {
      return new \WP_REST_Response([
        'success' => false,
        'message' => 'No taxonomies found for the given post types.',
      ], 404);
    }

    // Skip visually hidden taxonomies.
    $ui_taxonomies = array_filter($taxonomies, function ($taxonomy) {
      return $taxonomy->public && $taxonomy->show_ui;
    });

    $taxonomy_list = array_values(array_map(function ($taxonomy) {
      return [
        'key' => $taxonomy->name,
        'name' => $taxonomy->name
      ];
    }, $ui_taxonomies));

    return new \WP_REST_Response([
        'success' => true,
        'data' => $taxonomy_list,
    ], 200);
  }

  /**
   * Get the post types.
   * @since 0.0.9
   * @version 0.0.9
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_post_types() {
    $to_remove = apply_filters('nectar_blocks_get_taxonomies_excluded_post_types', [
      'attachment',
      'nav_menu_item',
      'wp_block',
      'wp_template',
      'wp_template_part',
      'wp_navigation',
      'wp_font_family'
    ]);

    $post_types = get_post_types( [ 'public' => true ], 'objects' );
    $post_types_clean = array_filter(
        $post_types,
        fn ($item) => ! in_array($item->name, $to_remove)
    );

    $mapped = array_map(
        fn ($item) => [
        "name" => $item->label,
        "slug" => $item->name
      ],
        $post_types_clean
    );

    $response = new \WP_REST_Response(array_values($mapped), 200);
    return $response;
  }

  /**
   * Get the post types for the template part.
   * @since 2.0.0
   * @version 2.0.0
   * @return \WP_REST_Response
   */
  public function get_post_types_for_template_part() {
    $formatted_post_types = Nectar_Templates::get_template_parts();
    return new \WP_REST_Response($formatted_post_types, 200);
  }

  /**
   * Get the image sizes.
   * @since 0.0.9
   * @version 0.0.9
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_image_sizes() {
    $sizes_to_remove = [
      '1536x1536',
      '2048x2048'
    ];

    $sizes_to_add = [
      'full'
    ];

    $image_sizes = get_intermediate_image_sizes();
    $image_sizes = array_filter($image_sizes, fn ($i) => ! in_array($i, $sizes_to_remove));
    $image_sizes = [
      ...$image_sizes,
      ...$sizes_to_add
    ];

    $response = new \WP_REST_Response($image_sizes, 200);
    return $response;
  }

  /**
   * Get the other posts for the given post types.
   * @since 2.0.0
   * @version 2.0.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_other_posts() {
    $to_remove = apply_filters('nectar_blocks_get_taxonomies_excluded_post_types', [
      'attachment',
      'nav_menu_item',
      'wp_block',
      'wp_template',
      'wp_template_part',
      'wp_navigation',
      'wp_font_family',
      'nectar_sections',
      'nectar_templates' // TODO: Is this correct?
    ]);

    $post_types = get_post_types( [ 'public' => true ], 'objects' );
    $post_types_clean = array_filter(
        $post_types,
        fn ($item) => ! in_array($item->name, $to_remove)
    );

     // Initialize an array to store the result
     $all_posts = [];

     // Loop through each post type
     foreach ($post_types_clean as $post_type) {
      // Get all posts of this post type
      $query_args = [
        'post_type' => $post_type->name,
        'post_status' => 'publish',
        'posts_per_page' => -1, // Get all posts
      ];

      $query = new \WP_Query($query_args);

      // Store the post type and its posts in the result array
      $build_arr = [
        'label' => $post_type->label,
        'options' => [],
      ];

      // Loop through each post
      if ($query->have_posts()) {

        while ($query->have_posts()) {
          $query->the_post();

          // Add the post to the array
          $build_arr['options'][] = [
            //  'ID'    => get_the_ID(),
            //  'title' => get_the_title(),
            //  'url'   => get_permalink(),
              'value' => get_the_ID(),
              'label' => get_the_title() . '(' . get_the_ID() . ')',
          ];
        }

        array_push($all_posts, $build_arr);
      }

      // Reset post data
      wp_reset_postdata();
    }

    $response = new \WP_REST_Response($all_posts, 200);
    return $response;
  }

  /**
   * The dynamic fields available for a given post id
   * @since 2.0.0
   * @version 2.0.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_dynamic_fields(\WP_REST_Request $request) {
    global $post;
    $post_id = $request->get_param( 'postId' );

    if ( ! $post_id && $post ) {
      $post_id = $post->ID;
    }

    $post_type = get_post_type($post_id);
    // Dict used as a set to loop up all the field keys
    $all_fiends_lookup = [];
    $excluded_fields = [
      // Nectarblocks
      'nectar_blog_post_view_count',
      '_nectar_blocks_page_css',
      '_nectar_blocks_page_js',
      '_nectar_blocks_css',
      '_nectar_blocks_css_preview',
      '_nectar_blocks_hide_post_title',
      '_nectar_blocks_transparent_header_effect',
      '_nectar_blocks_transparent_header_effect_color',
      '_nectar_blocks_header_animation',
      '_nectar_blocks_header_animation_effect',
      '_nectar_blocks_header_animation_delay',
      '_nectar_template_part_options',
      // Core WP
      '_edit_lock',
      '_edit_last',
      '_wp_old_slug',
      '_wp_page_template',
      '_encloseme',
      '_pingme',
      'footnotes',
      // SVG support plugin
      'inline_featured_image'
    ];

    // ACF.
    $acf_fields = ACF::get_instance()->get_acf_fields();
    $acf_field_values = [];

    foreach ($acf_fields as $acf_field_group) {
      if (empty($acf_field_group['options'])) {
        continue;
      }
      foreach ($acf_field_group['options'] as $field) {
        $acf_field_values[] = $field['value'];
      }
    }

    foreach ($acf_field_values as $field_value) {
      $all_fiends_lookup[$field_value] = true;
      $all_fiends_lookup['_' . $field_value] = true;
    }

    // Metabox.
    // TODO: Future implementation.
    // if (function_exists('rwmb_get_object_fields')) {
    //   $meta_boxes = rwmb_get_object_fields($post_id);
    //   if (! empty($meta_boxes)) {
    //     $build_arr = [
    //       'label' => esc_html__('Meta Box', 'nectar-blocks'),
    //       'options' => [],
    //     ];
    //     foreach ($meta_boxes as $key => $value) {
    //       $build_arr['options'][] = ['value' => $key, 'label' => $key];
    //     }
    //     array_push($all_meta, $build_arr);
    //   }
    // }

    // Regular custom meta fields.
    $post_meta_fields = get_post_meta($post_id);
    $custom_meta_fields = [];
    foreach ($post_meta_fields as $key => $value) {
      // ACF will add two versions of the field, one with an underscore and one without (internal use and public use)
      // check both.
      if (array_key_exists($key, $all_fiends_lookup)) {
        continue;
      }
      if (array_key_exists('_' . $key, $all_fiends_lookup)) {
        continue;
      }
      // If this is an underscored meta key, also check the un-prefixed version.
      if (substr($key, 0, 1) === '_' && array_key_exists(substr($key, 1), $all_fiends_lookup)) {
        continue;
      }
      if (in_array($key, $excluded_fields)) {
        continue;
      }

      array_push($custom_meta_fields, [
        'value' => $key,
        'label' => $key,
        'group' => 'custom_meta'
      ]);
    }

    $all_meta = [];
    if (! empty($acf_fields)) {
      $all_meta = array_merge($all_meta, $acf_fields);
    }

    if (! empty($custom_meta_fields)) {
      array_push($all_meta, [
        'label' => esc_html__('Custom Fields', 'nectar-blocks'),
        'options' => $custom_meta_fields,
      ]);
    }

    // Grouped Registered Meta Fields by post type

    $custom_post_types = get_post_types(['_builtin' => false], 'objects');

    // Exclude our own post types.
    unset(
      $custom_post_types[Global_Sections::POST_TYPE],
      $custom_post_types[Nectar_Templates::POST_TYPE]
    );

    foreach ($custom_post_types as $post_type => $post_type_obj) {
      $meta_keys = get_registered_meta_keys('post', $post_type);
      $meta_field_options = [];

      foreach ($meta_keys as $meta_key => $args) {
        if (array_key_exists($meta_key, $all_fiends_lookup)) {
          continue;
        }
        if (in_array($meta_key, $excluded_fields)) {
          continue;
        }

        $meta_field_options[] = [
          'value' => $meta_key,
          'label' => ! empty($args['label']) ? esc_html($args['label']) : $meta_key,
          'group' => 'registered_meta',
        ];
      }

      if (! empty($meta_field_options)) {
        $all_meta[] = [
          /* translators: the Meta Field name */
          'label' => sprintf(esc_html__('Meta Fields: %s', 'nectar-blocks'), $post_type_obj->labels->singular_name),
          'options' => $meta_field_options,
        ];
      }
    }

    $response = new \WP_REST_Response($all_meta, 200);
    return $response;
  }

  /**
   * Get the conditions for Nectar Templates.
   * @since 2.0.0
   * @version 2.0.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_conditions(\WP_REST_Request $request) {
    $post_type = $request->get_param( 'postType' );
    if ( $post_type === 'nectar_sections' ) {
      $conditions = Global_Sections::get_conditions();
    } else {
      $conditions = Nectar_Templates::get_conditions();
    }
    $response = new \WP_REST_Response($conditions, 200);
    return $response;
  }

  /**
   * Get the locations for Nectar Global Sections.
   * @since 2.0.0
   * @version 2.0.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function get_locations() {
    $locations = Global_Sections::get_locations();
    $response = new \WP_REST_Response($locations, 200);
    return $response;
  }

  /**
   * Search posts for the Specific Post condition.
   *
   * @since 2.1.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function search_posts(\WP_REST_Request $request) {
    $search_term = sanitize_text_field($request->get_param('search'));

    if ( empty($search_term) ) {
      return new \WP_REST_Response([], 200);
    }

    $post_types = get_post_types([ 'public' => true ]);
    $excluded_post_types = apply_filters('nectar_global_sections_search_excluded_post_types', [
      Global_Sections::POST_TYPE,
      'home_slider',
      'nectar_templates',
      'nectar_slider',
      'attachment'
    ]);
    $allowed_post_types = array_values(array_diff($post_types, $excluded_post_types));

    if ( empty($allowed_post_types) ) {
      return new \WP_REST_Response([], 200);
    }

    $query_args = [
      'post_status' => 'publish',
      'posts_per_page' => 20,
      'post_type' => $allowed_post_types,
      'no_found_rows' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ];

    if ( is_numeric($search_term) ) {
      $query_args['p'] = absint($search_term);
    } else {
      $query_args['s'] = $search_term;
      $query_args['orderby'] = 'relevance';
      $query_args['order'] = 'DESC';
    }

    $query = new \WP_Query($query_args);
    $results = [];

    if ( $query->have_posts() ) {
      while ( $query->have_posts() ) {
        $query->the_post();

        $post_id = get_the_ID();
        $post_type = get_post_type($post_id);
        $post_type_obj = get_post_type_object($post_type);
        $type_label = $post_type_obj && isset($post_type_obj->labels->singular_name)
          ? $post_type_obj->labels->singular_name
          : ( $post_type_obj->label ?? $post_type );

        $results[] = [
          'id' => $post_id,
          'title' => wp_kses_post(get_the_title()),
          'slug' => sanitize_title(get_post_field('post_name', $post_id)),
          'type' => sanitize_text_field($type_label),
        ];
      }
    }

    wp_reset_postdata();

    return new \WP_REST_Response($results, 200);
  }

  /**
   * Search taxonomy terms used in the template conditions.
   *
   * @since 2.1.0
   * @param \WP_REST_Request $request
   * @return \WP_REST_Response
   */
  public function search_taxonomy_terms(\WP_REST_Request $request) {
    $search_term = sanitize_text_field($request->get_param('search'));
    $taxonomy = sanitize_key($request->get_param('taxonomy'));

    if ( empty($search_term) ) {
      return new \WP_REST_Response([], 200);
    }

    if ( ! empty($taxonomy) && ! taxonomy_exists($taxonomy) ) {
      return new \WP_REST_Response([], 200);
    }

    $taxonomies = [];
    if ( ! empty($taxonomy) ) {
      $taxonomies[] = $taxonomy;
    } else {
      $taxonomies = get_taxonomies([
        'public' => true,
      ], 'names');
    }

    if ( empty($taxonomies) ) {
      return new \WP_REST_Response([], 200);
    }

    $results = [];

    foreach ( $taxonomies as $taxonomy_slug ) {
      if ( ! taxonomy_exists($taxonomy_slug) ) {
        continue;
      }

      $args = [
        'taxonomy' => $taxonomy_slug,
        'hide_empty' => false,
        'number' => 20,
      ];

      if ( is_numeric($search_term) ) {
        $args['include'] = [ absint($search_term) ];
      } else {
        $args['search'] = $search_term;
      }

      $terms = get_terms($args);

      if ( is_wp_error($terms) || empty($terms) ) {
        continue;
      }

      $taxonomy_obj = get_taxonomy($taxonomy_slug);
      $taxonomy_label = $taxonomy_obj && isset($taxonomy_obj->labels->singular_name)
        ? $taxonomy_obj->labels->singular_name
        : $taxonomy_slug;

      foreach ( $terms as $term ) {
        if ( ! isset($term->term_id) ) {
          continue;
        }

        $results[] = [
          'id' => $term->term_id,
          'name' => $term->name,
          'slug' => $term->slug,
          'taxonomy' => $taxonomy_slug,
          'taxonomyLabel' => $taxonomy_label,
        ];
      }
    }

    return new \WP_REST_Response($results, 200);
  }
}