<?php

namespace Nectar\Dynamic_Data;

use Nectar\Dynamic_Data\Dynamic_Helpers;
use Nectar\Dynamic_Data\Sources\{Other_Posts, Current_Post, ACF};
use Nectar\Utilities\Log;
use Nectar\Global_Sections\Global_Sections;
use Nectar\Nectar_Templates\Nectar_Templates;
/**
 * Frontend Render
 * @since 2.0.0
 * @version 2.0.0
 */
class Frontend_Render {
  public static $namespace = 'nectar_blocks_dynamic_data';

  function __construct() {
    $this->init_data_sources();
    $this->hooks();
  }

  public function init_data_sources() {
    Other_Posts::get_instance();
    Current_Post::get_instance();
    if ( class_exists( 'ACF' ) ) {
      ACF::get_instance();
    }
  }

  public function hooks() {
    add_filter( 'register_block_type_args', [ $this, 'add_nectar_render_dynamic_content_filter' ], 11, 2 );
    add_filter( 'nectar_render_dynamic_content', [ $this, 'render_dynamic_content' ], 10, 3 );
  }

  /**
   * Adds the nectar_render_dynamic_content filter when the render_callback function is called
   *
   * @since 2.0.0
   * @version 2.0.0
   * @param array $args Array of arguments for registering a block type.
   * @param string $block_type The block type name including namespace.
   * @return array
   */
  public function add_nectar_render_dynamic_content_filter( $args, $block_type ) {
    // Ensure $args is an array before proceeding.
    if ( ! is_array( $args ) ) {
      return $args;
    }

    // Store the original render callback if it exists and is callable.
    $original_render_callback = null;
    if (isset( $args['render_callback'] ) && is_callable( $args['render_callback'] )) {
      $original_render_callback = $args['render_callback'];
    }

    // Override the render callback to include the dynamic content filter.
    $args['render_callback'] = function( $attributes, $content, $block = null ) use ( $original_render_callback ) {
      // Invoke the original render callback if available.
      if ( $original_render_callback ) {
        $content = call_user_func( $original_render_callback, $attributes, $content, $block );
      }

      // Apply the nectar_render_dynamic_content filter.
      return apply_filters( 'nectar_render_dynamic_content', $attributes, $content, $block );
    };

    return $args;
  }

  /**
   * Parses the block content for dynamic content and returns the modified content.
   *
   * @since 2.0.0
   * @version 2.0.0
   * @param array $attributes The block attributes.
   * @param string $block_content The block content.
   * @param array $block The block object.
   * @return string
   */
  public function render_dynamic_content( $attributes, $block_content, $block = null ) {

    if ( ! $block_content || strpos( $block_content, '!!nb_dynamic/' ) === false ) {
      return $block_content;
    }

    $parse_patterns = [
      // Text replacements.
      [
        'target' => Dynamic_Helpers::$NB_DYNAMIC_TEXT_PATTERN,
        'field_data' => Dynamic_Helpers::$NB_DYNAMIC_PATTERN,
        'replacement_type' => 'text'
      ],
      // Image replacements.
      [
      'target' => Dynamic_Helpers::$NB_DYNAMIC_IMAGE_PATTERN,
      'field_data' => Dynamic_Helpers::$NB_DYNAMIC_PATTERN,
      'replacement_type' => 'image'
      ],
      // Attribute replacements.
      [
      'target' => Dynamic_Helpers::$NB_DYNAMIC_PATTERN,
      'field_data' => Dynamic_Helpers::$NB_DYNAMIC_PATTERN,
      'replacement_type' => 'attribute'
      ]
    ];

    foreach ( $parse_patterns as $parse_pattern ) {
      preg_match_all( $parse_pattern['target'], $block_content, $matches );

      $element_matches = $matches[0];

      if ( ! is_array( $element_matches ) || empty( $element_matches ) ) {
        continue;
      }

      $block_content = preg_replace_callback(
          $parse_pattern['target'],
          function ($match) use ($parse_pattern) {
          if (! isset($match[0])) {
            return $match[0]; // Return original match if invalid
          }

          $dynamic_field = $match[0];

          if ( ! preg_match( $parse_pattern['field_data'], $dynamic_field, $attribute ) ) {
            return $dynamic_field; // Return original match if parsing fails
          }

          // Extract multiple dynamic fields from the attribute
          $processed_attributes = Dynamic_Helpers::parse_dynamic_fields( $attribute[0] );

          if ( ! is_array( $processed_attributes ) || empty( $processed_attributes ) ) {
            return $dynamic_field; // Return original match if parsing fails
          }

          $replacement = '';

          // Iterate through multiple matches and replace each uniquely
          foreach ( $processed_attributes as $processed_attr ) {

            if ( ! isset( $processed_attr['source'], $processed_attr['post_id'], $processed_attr['field'] ) ) {
              continue;
            }

            // class attribute
            $class = '';
            if (preg_match('/class="([^"]*)"/', $match[0], $class_matches)) {
              $class = $class_matches[1];
            }

            $content = $this->get_dynamic_content( $processed_attr, false, $parse_pattern['replacement_type'], $class );

            if ( ! empty( $content ) ) {
              $replacement .= $content;
            } else {
              // If no dynamic content is found, replace the saved dynamic field with an empty string.
              $replacement .= '';
            }
          }

          return $replacement;
        },
          $block_content
      );

    }

    return $block_content;
  }

 /**
   * Gets the dynamic content placeholder for the given attributes.
   *
   * @since 2.0.0
   * @version 2.0.0
   * @param string $args The attributes for the dynamic content.
   * @return string
   */
  public static function get_dynamic_content_placeholder( $args ) {

    $acf_field = [];
    if ( function_exists('acf_get_field') ) {
      $acf_field = acf_get_field($args['field']);
    }

    // Featured Image
    if ( in_array($args['field'], ['featured_image', 'author_profile_picture']) ) {
      $dynamic_data = NECTAR_BLOCKS_PLUGIN_PATH . '/assets/img/dynamic-image-placeholder.svg';
    } else if ( $acf_field && array_key_exists( 'type', $acf_field ) && $acf_field['type'] === 'image' ) {
      // ACF image
      $dynamic_data = NECTAR_BLOCKS_PLUGIN_PATH . '/assets/img/dynamic-image-placeholder.svg';
    } else {
      // Text.
      $dynamic_data = '[' . Dynamic_Helpers::get_field_title($args['field']) . ']';
    }

    return $dynamic_data;
  }

   /**
   * Parses the block content for dynamic content and returns the modified content.
   *
   * @since 2.0.0
   * @version 2.0.0
   * @param array $attributes [source, post_id, field]
   * @return string
   */
  public static function get_dynamic_content($attributes, $is_editor = false, $replacement_type = '', $class = '') {
    $source = $attributes['source'];
    $post_id = intval( $attributes['post_id'] );
    $field = sanitize_key( $attributes['field'] );

    // Inherit from current query.
    if ($source === 'currentPost' && $is_editor === false) {
      $post_id = get_the_ID();
    }

    Log::debug('get_dynamic_content', [
      'source' => $source,
      'post_id' => $post_id,
      'field' => $field
    ]);

    $args = [
      'post_id' => $post_id,
      'field' => $field,
      'attributes' => $attributes['attributes'],
      'replacement_type' => $replacement_type,
      'class' => $class
    ];
    $post_type = $args['post_id'] ? get_post_type( $args['post_id'] ) : false;
    $placeholder_post_types = [
      Global_Sections::POST_TYPE,
      Nectar_Templates::POST_TYPE,
      'wp_template',
      'wp_template_part'
    ];

    // Get the dynamic data
    $dynamic_data = null;

    $args['field_data'] = [];
    $fields = apply_filters(
        self::$namespace . '/' . $source . '/fields',
        [],
        $args['post_id'],
        $is_editor
    );
    Log::debug('fields', [ 'fields' => $fields ]);

    if ( array_key_exists( $args['field'], $fields ) &&
      array_key_exists( 'field_data', $fields[$args['field']] ) ) {
      $args['field_data'] = $fields[$args['field']]['field_data'];
    }

    // content.
    // Template part global sections should only display a placeholder.
    $should_render_placeholder = (
        $source === 'currentPost' &&
      $is_editor === true &&
      (
          $args['post_id'] === 0 ||
        ( $post_type && in_array( $post_type, $placeholder_post_types, true ) )
      )
    );

    if ( $should_render_placeholder ) {
      $dynamic_data = self::get_dynamic_content_placeholder( $args );
    } else {
      $dynamic_data = apply_filters(
          self::$namespace . '/' . $source . '/content',
          $dynamic_data,
          $args,
          $is_editor
      );
    }

    Log::debug('dynamic_data', [ 'dynamic_data' => $dynamic_data ]);

    if ( $dynamic_data === null ) {
      return '';
    }

    if ( $replacement_type === 'image' ) {
      return self::sanitize_image_replacement( $dynamic_data );
    }

    return wp_kses_post( $dynamic_data );
  }

  /**
   * Filters an image replacement without dropping responsive image attributes.
   *
   * Image replacements are NOT all core-generated markup: the value can come from an ACF
   * field or from arbitrary post meta (Other_Posts::render_custom_meta_field(),
   * ACF::maybe_render_image_markup_from_value(), which passes any value containing `<img`
   * straight through), both writable by users who cannot post unfiltered HTML. So the value
   * has to be filtered — but with wp_kses_post() it would lose `srcset`, `sizes`, `decoding`
   * and `fetchpriority`, none of which are in `$allowedposttags['img']`, and which
   * wp_get_attachment_image() emits on every legitimate replacement.
   *
   * @since 3.2.1
   * @param mixed $markup The replacement produced by a data source.
   * @return string
   */
  public static function sanitize_image_replacement( $markup ) {
    if ( ! is_string( $markup ) || $markup === '' ) {
      return '';
    }

    return wp_kses( $markup, self::image_allowed_html() );
  }

  /**
   * The post allowlist plus the `<img>` attributes responsive images need.
   *
   * Built from wp_kses_allowed_html( 'post' ) so it inherits the global attributes and this
   * plugin's own `wp_kses_allowed_html` additions (Editor\Blocks::nectar_wp_kses_allowed_html),
   * keeping it consistent with the wp_kses_post() path used for every other replacement type.
   * `<picture>`/`<source>` are allowed because image-optimisation plugins filter
   * wp_get_attachment_image() output into them.
   *
   * @since 3.2.1
   * @return array<string, mixed>
   */
  public static function image_allowed_html() {
    $tags = wp_kses_allowed_html( 'post' );

    if ( ! is_array( $tags ) ) {
      $tags = [];
    }

    $responsive_attributes = [
      'src' => true,
      'srcset' => true,
      'sizes' => true,
      'alt' => true,
      'width' => true,
      'height' => true,
      'loading' => true,
      'decoding' => true,
      'fetchpriority' => true
    ];

    $tags['img'] = array_merge(
        ( isset( $tags['img'] ) && is_array( $tags['img'] ) ) ? $tags['img'] : [],
        $responsive_attributes
    );

    $tags['source'] = array_merge(
        ( isset( $tags['source'] ) && is_array( $tags['source'] ) ) ? $tags['source'] : [],
        $responsive_attributes,
        [ 'type' => true, 'media' => true ]
    );

    $tags['picture'] = array_merge(
        ( isset( $tags['picture'] ) && is_array( $tags['picture'] ) ) ? $tags['picture'] : [],
        [ 'class' => true, 'id' => true, 'style' => true, 'title' => true, 'data-*' => true ]
    );

    return $tags;
  }
}