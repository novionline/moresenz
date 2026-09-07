<?php

namespace Nectar\Render\Blocks\Megamenu;

use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Global_Sections\Global_Sections;
use Nectar\Render\Render as Blocks_Render;

/**
 * Megamenu block renderer.
 *
 * Renders a global section or pattern as a megamenu dropdown
 * within the navigation structure.
 *
 * When inside a navigation-submenu, the megamenu replaces the normal dropdown
 * and is triggered by hovering the parent menu item.
 *
 * @since 3.0.0
 */
class Megamenu {
  public $attributes;

  public function __construct( $attributes ) {
    $this->attributes = wp_parse_args( $attributes, [
      'blockId' => '',
      'sourceType' => 'global-section',
      'sourceId' => 0,
      'sourceTitle' => '',
      'size' => 'fullwidth',
      'containedWidth' => [
        'value' => 600,
        'unit' => 'px',
      ],
    ] );
  }

  /**
   * Render the megamenu block.
   *
   * Output is a special marker div that Enhanced_Navigation will process
   * to restructure the parent navigation item.
   *
   * @return string HTML output.
   */
  public function render(): string {
    try {
      $source_type = isset( $this->attributes['sourceType'] )
        ? sanitize_text_field( (string) $this->attributes['sourceType'] )
        : 'global-section';
      $source_id = isset( $this->attributes['sourceId'] ) ? $this->attributes['sourceId'] : '';
      $size = isset( $this->attributes['size'] )
        ? sanitize_text_field( (string) $this->attributes['size'] )
        : 'fullwidth';

      // Validate source - handle both string "0" and empty string.
      if ( empty( $source_id ) || '0' === $source_id ) {
        return '';
      }

      // Validate source type.
      if ( ! in_array( $source_type, [ 'global-section', 'pattern' ], true ) ) {
        $source_type = 'global-section';
      }

      // Validate size.
      if ( ! in_array( $size, [ 'fullwidth', 'contained' ], true ) ) {
        $size = 'fullwidth';
      }

      // Determine if fullwidth or contained.
      $size_class = 'fullwidth' === $size
        ? 'nectar-megamenu--fullwidth'
        : 'nectar-megamenu--contained';

      $content = '';

      if ( 'global-section' === $source_type ) {
        $content = $this->render_global_section( intval( $source_id ) );
      } else {
        $content = $this->render_pattern( intval( $source_id ) );
      }

      if ( empty( $content ) || ! is_string( $content ) ) {
        return '';
      }

      // Build inline style for contained width as CSS variable.
      $inline_style = '';
      if ( 'contained' === $size ) {
        $contained_width = isset( $this->attributes['containedWidth'] ) && is_array( $this->attributes['containedWidth'] )
          ? $this->attributes['containedWidth']
          : [];
        $width_value = isset( $contained_width['value'] ) ? floatval( $contained_width['value'] ) : 600;
        $width_unit = isset( $contained_width['unit'] ) ? sanitize_text_field( (string) $contained_width['unit'] ) : 'px';

        // Validate width values.
        $width_value = max( 100, min( 3000, $width_value ) ); // Clamp between 100 and 3000
        if ( ! in_array( $width_unit, [ 'px', 'vw', 'em', 'rem', '%' ], true ) ) {
          $width_unit = 'px';
        }

        $inline_style = sprintf( '--nectar-megamenu-width: %s%s;', $width_value, $width_unit );
      }

      // Output megamenu with data attribute for size.
      // The Enhanced_Navigation filter will:
      // 1. Move this outside the submenu-container to be a direct child of the parent li
      // 2. Add 'has-megamenu' class to the parent li
      // 3. Hide the normal submenu-container
      return sprintf(
          '<div class="nectar-megamenu %1$s" data-megamenu-size="%2$s" style="%3$s"><div class="nectar-megamenu__inner">%4$s</div></div>',
          esc_attr( $size_class ),
          esc_attr( $size ),
          esc_attr( $inline_style ),
          $content
      );

    } catch ( \Throwable $e ) {
      // Fail silently on any unexpected error.
      return '';
    }
  }

  /**
   * Render a global section by ID.
   *
   * @param int $section_id The global section post ID.
   * @return string Rendered HTML.
   */
  private function render_global_section( int $section_id ): string {
    // Early validation.
    if ( $section_id <= 0 ) {
      return '';
    }

    try {
      // Validate the post exists and is published.
      $section_status = get_post_status( $section_id );
      if ( false === $section_status || 'publish' !== $section_status ) {
        return '';
      }

      // Verify it is actually a global-section post. Without this guard a stale
      // or cross-site sourceId that happens to match any published post would
      // silently inject that unrelated post's full content into the megamenu
      // dropdown. Mirror render_pattern()'s wp_block check and fail closed.
      if ( Global_Sections::POST_TYPE !== get_post_type( $section_id ) ) {
        return '';
      }

      $section_content = get_post_field( 'post_content', $section_id );
      if ( empty( $section_content ) || ! is_string( $section_content ) ) {
        return '';
      }

      ob_start();

      // Render dynamic CSS from the global section.
      $dynamic_css = get_post_meta( $section_id, '_nectar_blocks_css', true );

      if ( ! empty( $dynamic_css ) && is_string( $dynamic_css ) ) {
        if ( class_exists( Frontend_Render::class ) ) {
          $fe_render = new Frontend_Render();
          $dynamic_css = $fe_render->render_dynamic_content( [], $dynamic_css );
        }
      } else {
        $dynamic_css = '';
      }

      // Pattern CSS - wrap in try/catch as reflection can throw.
      if ( $section_content && class_exists( Blocks_Render::class ) ) {
        try {
          $blocks = parse_blocks( $section_content );
          if ( is_array( $blocks ) && ! empty( $blocks ) ) {
            $blocks_render_reflection = new \ReflectionClass( Blocks_Render::class );
            $blocks_render = $blocks_render_reflection->newInstanceWithoutConstructor();
            if ( method_exists( $blocks_render, 'frontend_pattern_css' ) ) {
              $patterns_css = $blocks_render->frontend_pattern_css( $blocks );
              $dynamic_css .= is_string( $patterns_css ) ? $patterns_css : '';
            }
          }
        } catch ( \ReflectionException $e ) {
          // Silently continue without pattern CSS if reflection fails.
        }
      }

      if ( '' !== $dynamic_css ) {
        echo '<style data-type="nectar-megamenu-dynamic-css">' . $dynamic_css . '</style>';
      }

      // Process content.
      $rendered_content = do_blocks( $section_content );
      if ( ! is_string( $rendered_content ) ) {
        $rendered_content = '';
      }
      $rendered_content = wptexturize( $rendered_content );
      $rendered_content = convert_smilies( $rendered_content );
      $rendered_content = shortcode_unautop( $rendered_content );
      $rendered_content = wp_filter_content_tags( $rendered_content );

      echo do_shortcode( $rendered_content );

      $output = ob_get_clean();
      return is_string( $output ) ? $output : '';

    } catch ( \Throwable $e ) {
      // Clean up output buffer if exception occurred.
      if ( ob_get_level() > 0 ) {
        ob_end_clean();
      }
      // Fail silently - return empty string.
      return '';
    }
  }

  /**
   * Render a synced pattern (wp_block) by ID.
   *
   * @param int $pattern_id The pattern post ID.
   * @return string Rendered HTML.
   */
  private function render_pattern( int $pattern_id ): string {
    // Early validation.
    if ( $pattern_id <= 0 ) {
      return '';
    }

    try {
      // Validate the post exists and is published.
      $pattern_status = get_post_status( $pattern_id );
      if ( false === $pattern_status || 'publish' !== $pattern_status ) {
        return '';
      }

      // Verify it's actually a wp_block post type.
      $post_type = get_post_type( $pattern_id );
      if ( 'wp_block' !== $post_type ) {
        return '';
      }

      $pattern_content = get_post_field( 'post_content', $pattern_id );
      if ( empty( $pattern_content ) || ! is_string( $pattern_content ) ) {
        return '';
      }

      ob_start();

      // Render dynamic CSS from the pattern.
      $dynamic_css = get_post_meta( $pattern_id, '_nectar_blocks_css', true );

      if ( ! empty( $dynamic_css ) && is_string( $dynamic_css ) ) {
        if ( class_exists( Frontend_Render::class ) ) {
          $fe_render = new Frontend_Render();
          $dynamic_css = $fe_render->render_dynamic_content( [], $dynamic_css );
        }
      } else {
        $dynamic_css = '';
      }

      // Pattern CSS - wrap in try/catch as reflection can throw.
      if ( $pattern_content && class_exists( Blocks_Render::class ) ) {
        try {
          $blocks = parse_blocks( $pattern_content );
          if ( is_array( $blocks ) && ! empty( $blocks ) ) {
            $blocks_render_reflection = new \ReflectionClass( Blocks_Render::class );
            $blocks_render = $blocks_render_reflection->newInstanceWithoutConstructor();
            if ( method_exists( $blocks_render, 'frontend_pattern_css' ) ) {
              $patterns_css = $blocks_render->frontend_pattern_css( $blocks );
              $dynamic_css .= is_string( $patterns_css ) ? $patterns_css : '';
            }
          }
        } catch ( \ReflectionException $e ) {
          // Silently continue without pattern CSS if reflection fails.
        }
      }

      if ( '' !== $dynamic_css ) {
        echo '<style data-type="nectar-megamenu-dynamic-css">' . $dynamic_css . '</style>';
      }

      // Process content.
      $rendered_content = do_blocks( $pattern_content );
      if ( ! is_string( $rendered_content ) ) {
        $rendered_content = '';
      }
      $rendered_content = wptexturize( $rendered_content );
      $rendered_content = convert_smilies( $rendered_content );
      $rendered_content = shortcode_unautop( $rendered_content );
      $rendered_content = wp_filter_content_tags( $rendered_content );

      echo do_shortcode( $rendered_content );

      $output = ob_get_clean();
      return is_string( $output ) ? $output : '';

    } catch ( \Throwable $e ) {
      // Clean up output buffer if exception occurred.
      if ( ob_get_level() > 0 ) {
        ob_end_clean();
      }
      // Fail silently - return empty string.
      return '';
    }
  }
}

