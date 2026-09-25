<?php

namespace Nectar\Render;

use WP_Post;
use WP_Query;
use Nectar\Global_Sections\{Global_Sections as GlobalSectionsPostType, Render as GlobalSectionsRender};
use Nectar\Nectar_Templates\{Nectar_Templates as NectarTemplatesPostType, Render as NectarTemplatesRender};

/**
 * Determines whether optional frontend scripts should load.
 */
class Conditional_Scripts {
  /**
   * The compiled page content that will be scanned.
   */
  protected string $content = '';

  /**
   * Cached block names extracted from the page content.
   *
   * @var string[]
   */
  protected array $block_names = [];

  protected bool $block_names_populated = false;

  /**
   * Cached block attributes keyed by block name without namespace.
   *
   * @var array<string, array<int, array>>
   */
  protected array $block_data = [];

  protected bool $block_data_populated = false;

  public function __construct( string $content = '' ) {
    $this->content = $content;
  }

  public static function from_post( ?WP_Post $post = null ): self {
    $content = '';
    $resolved_post = null;

    if ( is_singular() && $post instanceof WP_Post ) {
      $resolved_post = $post;
    } elseif ( is_home() && ! is_front_page() ) {
      $posts_page_id = (int) get_option( 'page_for_posts' );
      if ( $posts_page_id ) {
        $posts_page = get_post( $posts_page_id );
        if ( $posts_page instanceof WP_Post ) {
          $resolved_post = $posts_page;
        }
      }
    }

    if ( $resolved_post instanceof WP_Post ) {
      $content = $resolved_post->post_content ?? '';
    }
    /**
     * Filter the content that conditional scripts will inspect.
     *
     * @param string       $content The content string.
     * @param WP_Post|null $post    The current post object, if available.
     */
    $content = apply_filters( 'nectar_blocks/conditional_scripts/content', $content, $resolved_post );
    $instance = new self( $content );
    $instance->append_template_content();
    $instance->append_global_section_content();
    $instance->append_block_widget_content();
    return $instance;
  }

  /**
   * Appends additional content that should be scanned for block and string matches.
   */
  public function append_content( string $content ): void {
    if ( '' === trim( $content ) ) {
      return;
    }
    $this->content .= "\n" . $content;
    $this->block_names_populated = false;
    $this->block_data_populated = false;
  }

  /**
   * Determines whether the provided requirements indicate the script should load.
   *
   * @param array{
   *   blocks?: string[],
   *   strings?: string[],
   *   attributes?: array<int, array{block: string, attributes: array}>,
   *   filter?: string
   * } $requirements
   */
  public function should_enqueue( array $requirements ): bool {
    $block_targets = isset( $requirements['blocks'] ) && is_array( $requirements['blocks'] ) ? $requirements['blocks'] : [];
    $string_targets = isset( $requirements['strings'] ) && is_array( $requirements['strings'] ) ? $requirements['strings'] : [];
    $attribute_rules = isset( $requirements['attributes'] ) && is_array( $requirements['attributes'] ) ? $requirements['attributes'] : [];
    $filter_tag = isset( $requirements['filter'] ) ? $requirements['filter'] : '';

    $needs = false;

    if ( ! empty( $block_targets ) ) {
      $needs = $needs || $this->has_block_match( $block_targets );
    }

    if ( ! empty( $string_targets ) ) {
      $needs = $needs || $this->contains_strings( $string_targets );
    }

    if ( ! empty( $attribute_rules ) ) {
      $needs = $needs || $this->has_attribute_match( $attribute_rules );
    }

    if ( $filter_tag ) {
      /**
       * Allows overriding the detection logic for the provided filter tag.
       *
       * @param bool   $needs        Whether the script is required.
       * @param array  $block_names  The detected block names.
       * @param string $content      The scanned content.
       */
      $needs = apply_filters( $filter_tag, $needs, $this->get_block_names(), $this->content );
    }

    return $needs;
  }

  protected function get_block_names(): array {
    $this->ensure_block_data();
    return $this->block_names;
  }

  public function get_detected_block_names(): array {
    return $this->get_block_names();
  }

  protected function has_block_match( array $target_blocks ): bool {
    if ( empty( $target_blocks ) ) {
      return false;
    }
    $block_names = $this->get_block_names();
    foreach ( $target_blocks as $block ) {
      if ( in_array( $block, $block_names, true ) ) {
        return true;
      }
    }
    return false;
  }

  protected function contains_strings( array $needles ): bool {
    if ( empty( $needles ) ) {
      return false;
    }

    // Ensure pattern content is resolved and appended before string matching.
    $this->ensure_block_data();

    if ( '' === $this->content ) {
      return false;
    }

    foreach ( $needles as $needle ) {
      if ( $needle && str_contains( $this->content, $needle ) ) {
        return true;
      }
    }
    return false;
  }

  protected function has_attribute_match( array $rules ): bool {
    if ( empty( $rules ) ) {
      return false;
    }
    $block_data = $this->get_block_data();
    foreach ( $rules as $rule ) {
      if ( empty( $rule['block'] ) || empty( $rule['attributes'] ) || ! is_array( $rule['attributes'] ) ) {
        continue;
      }
      $block_name = $rule['block'];
      if ( ! isset( $block_data[$block_name] ) ) {
        continue;
      }
      foreach ( $block_data[$block_name] as $instance_attrs ) {
        if ( $this->attributes_match( $instance_attrs, $rule['attributes'] ) ) {
          return true;
        }
      }
    }
    return false;
  }

  protected function attributes_match( array $instance_attrs, array $required_attrs ): bool {
    foreach ( $required_attrs as $key => $value ) {
      if ( ! array_key_exists( $key, $instance_attrs ) ) {
        return false;
      }

      $actual = $instance_attrs[$key];

      if ( is_array( $value ) && is_array( $actual ) ) {
        if ( ! $this->attributes_match( $actual, $value ) ) {
          return false;
        }
        continue;
      }

      if ( $actual !== $value ) {
        return false;
      }
    }

    return true;
  }

  protected function get_block_data(): array {
    $this->ensure_block_data();
    return $this->block_data;
  }

  protected function ensure_block_data(): void {
    if ( $this->block_data_populated ) {
      return;
    }

    $this->block_data = [];
    $this->block_names = [];

    if ( '' !== trim( $this->content ) ) {
      $parsed_blocks = parse_blocks( $this->content );
      $resolved_patterns = [];
      $this->collect_block_data( $parsed_blocks, $resolved_patterns );

      // Append resolved pattern content for string matching.
      foreach ( array_keys( $resolved_patterns ) as $pattern_id ) {
        $pattern_post = get_post( $pattern_id );
        if ( $pattern_post instanceof WP_Post && ! empty( $pattern_post->post_content ) ) {
          $this->content .= "\n" . $pattern_post->post_content;
        }
      }
    }

    $this->block_names = array_keys( $this->block_data );
    $this->block_data_populated = true;
    $this->block_names_populated = true;
  }

  protected function collect_block_data( array $blocks, array &$resolved_patterns = [] ): void {
    foreach ( $blocks as $block ) {
      // Handle WordPress patterns (synced patterns / reusable blocks).
      // These appear as core/block with a ref attribute pointing to the pattern's post ID.
      if ( isset( $block['blockName'] ) && 'core/block' === $block['blockName'] ) {
        $pattern_ref = isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0;

        // Prevent infinite recursion from circular pattern references.
        if ( $pattern_ref && ! isset( $resolved_patterns[$pattern_ref] ) ) {
          $resolved_patterns[$pattern_ref] = true;
          $pattern_post = get_post( $pattern_ref );

          if ( $pattern_post instanceof WP_Post && ! empty( $pattern_post->post_content ) ) {
            $pattern_blocks = parse_blocks( $pattern_post->post_content );
            $this->collect_block_data( $pattern_blocks, $resolved_patterns );
          }
        }
        continue;
      }

      if ( isset( $block['blockName'] ) && str_starts_with( $block['blockName'], 'nectar-blocks/' ) ) {
        $block_name = str_replace( 'nectar-blocks/', '', $block['blockName'] );
        if ( ! isset( $this->block_data[$block_name] ) ) {
          $this->block_data[$block_name] = [];
        }

        $attrs = [];
        if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
          $attrs = $block['attrs'];
        }
        $this->block_data[$block_name][] = $attrs;
      }

      if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
        $this->collect_block_data( $block['innerBlocks'], $resolved_patterns );
      }
    }
  }

  protected function append_template_content(): void {
    if ( ! class_exists( NectarTemplatesPostType::class ) || ! class_exists( NectarTemplatesRender::class ) ) {
      return;
    }

    $query = new WP_Query([
      'post_type' => NectarTemplatesPostType::POST_TYPE,
      'post_status' => 'publish',
      'no_found_rows' => true,
      'fields' => 'ids',
      'posts_per_page' => -1,
    ]);

    if ( empty( $query->posts ) ) {
      return;
    }

    $render = NectarTemplatesRender::get_instance();

    foreach ( $query->posts as $template_id ) {
      $template_id = (int) $template_id;
      if ( ! $this->template_should_render( $render, $template_id ) ) {
        continue;
      }

      $template_content = get_post_field( 'post_content', $template_id );
      if ( $template_content ) {
        $this->append_content( $template_content );
      }
    }
  }

  protected function template_should_render( NectarTemplatesRender $render, int $template_id ): bool {
    $meta = get_post_meta( $template_id, NectarTemplatesPostType::META_KEY, true );
    if ( ! is_array( $meta ) ) {
      $meta = [];
    }
    $meta = wp_parse_args( $meta, NectarTemplatesPostType::defaults() );

    $location = isset( $meta['templatePart'] ) ? $meta['templatePart'] : '';
    if ( ! $location || ! NectarTemplatesPostType::is_active_location( $location ) ) {
      return false;
    }

    return (bool) $render->verify_conditional_display( $template_id );
  }

  protected function append_global_section_content(): void {
    if ( ! class_exists( GlobalSectionsPostType::class ) || ! class_exists( GlobalSectionsRender::class ) ) {
      return;
    }

    $query = new WP_Query([
      'post_type' => GlobalSectionsPostType::POST_TYPE,
      'post_status' => 'publish',
      'no_found_rows' => true,
      'fields' => 'ids',
      'posts_per_page' => -1,
    ]);

    if ( empty( $query->posts ) ) {
      return;
    }

    $render = GlobalSectionsRender::get_instance();

    foreach ( $query->posts as $section_id ) {
      $section_id = (int) $section_id;

      $meta = get_post_meta( $section_id, GlobalSectionsPostType::META_KEY, true );
      $locations = is_array( $meta ) && isset( $meta['locations'] ) ? $meta['locations'] : [];
      if ( empty( $locations ) ) {
        continue;
      }

      if ( ! $render->verify_conditional_display( $section_id ) ) {
        continue;
      }

      $section_content = get_post_field( 'post_content', $section_id );
      if ( $section_content ) {
        $this->append_content( $section_content );
      }
    }
  }

  /**
   * Appends block-widget content from registered sidebars. Widget content
   * lives in the widget_block option and renders during dynamic_sidebar(),
   * so the post/template scans never see it. Sidebars registered but not
   * rendered by the current template contribute a little unused CSS —
   * accepted so widget blocks get head-inlined styles and conditional JS.
   */
  protected function append_block_widget_content(): void {
    $sidebars = wp_get_sidebars_widgets();
    if ( empty( $sidebars ) ) {
      return;
    }

    $block_widgets = get_option( 'widget_block', [] );
    if ( empty( $block_widgets ) || ! is_array( $block_widgets ) ) {
      return;
    }

    foreach ( $sidebars as $sidebar_id => $widget_ids ) {
      // Skips wp_inactive_widgets and orphaned sidebars.
      if ( ! is_registered_sidebar( $sidebar_id ) || ! is_array( $widget_ids ) ) {
        continue;
      }
      foreach ( $widget_ids as $widget_id ) {
        $parsed = wp_parse_widget_id( (string) $widget_id );
        if ( 'block' !== ( $parsed['id_base'] ?? '' ) || ! isset( $parsed['number'] ) ) {
          continue;
        }
        $instance = $block_widgets[$parsed['number']] ?? null;
        if ( ! empty( $instance['content'] ) ) {
          $this->append_content( $instance['content'] );
        }
      }
    }
  }
}

