<?php

namespace Nectar\Render;

use Nectar\Editor\Blocks;
use Nectar\Global_Settings\{ Global_Colors, Global_Typography, Nectar_Plugin_Options, Nectar_Custom_Fonts, Nectar_Adobe_Fonts };
use Nectar\Render\{RenderJS, Compatibility, Conditional_Script_Manager, Conditional_Scripts, Local_Google_Fonts, Lazy_Load_Images, Preload_BG_Images, Enhanced_Navigation, Header_Variant_Initial};
use Nectar\Utilities\{Log, HTTP};
use Nectar\Dynamic_Data\{Frontend_Render};
use WP_Post;

/**
 * Blocks Editor configuration
 * @version 1.3.4
 * @since 0.0.2
 */
class Render {
  protected ?Conditional_Scripts $conditional_assets_detector = null;

  // Blocks whose frontend-style.css has been delivered (or excluded) this
  // request, keyed by block name — the dedup tracker for all delivery paths.
  private array $delivered_block_styles = [];

  // Subset of delivered blocks whose CSS went out as a body-prepended <style>
  // via the render_block filter — discardable by a dynamic ancestor
  // re-rendering its inner blocks, so eligible for the <link> backstop.
  private array $discardable_inline_styles = [];

  function __construct() {
    $this->initialize_hooks();
    $renderJS = new RenderJS();
    $compatibility = new Compatibility();
    $lazy_load_images = new Lazy_Load_Images();
    $preload_bg_images = new Preload_BG_Images();
    $enhanced_navigation = new Enhanced_Navigation();
    // Walks saved post content for the first top-level Row's `headerVariant`,
    // stores it as post meta, and exposes a filter the theme reads when
    // rendering `<nav id="nectar-nav">` so the variant lands on first paint.
    $header_variant_initial = new Header_Variant_Initial();
  }

  function initialize_hooks() {
    add_filter( 'should_load_separate_core_block_assets', '__return_true' );
    add_filter( 'render_block', [$this, 'render_template_part'], 10, 2 );

    add_filter( 'render_block', [$this, 'accessible_svgs'], 10, 2 );
    add_filter( 'wp_content_img_tag', [$this, 'image_sizes_override_cleanup'], 20 );
    add_action( 'after_setup_theme', [$this, 'block_styles'] );
    add_action( 'plugins_loaded', [$this, 'localization'] );
    add_action( 'wp_enqueue_scripts', [$this, 'frontend_render_styles'], 99 );
    add_action( 'wp_enqueue_scripts', [$this, 'frontend_render_scripts'] );
    add_action( 'admin_enqueue_scripts', [$this, 'admin_enqueue'], 99 );
    add_action( 'admin_head', [$this, 'admin_head'], 1 );
    add_action( 'customize_controls_enqueue_scripts', [$this, 'customize_controls_enqueue_scripts'], 99 );
    add_action( 'wp_head', [$this, 'render_head'] );
    add_action( 'wp_body_open', [$this, 'wp_body_open'] );

    // Add Nectarblocks HTML comment for discoverability
    add_action( 'wp_head', [$this, 'render_nectarblocks_generator'], 0 );
  }

  function accessible_svgs( $block_content, $block ) {
    if (empty($block_content)) {
      return $block_content;
    }

    // Ensure it only applies to blocks in the 'nectar-blocks/' namespace
    if (! isset($block['blockName']) || strpos($block['blockName'], 'nectar-blocks/') !== 0) {
      return $block_content;
    }

     // Regex pattern to find <svg> elements that don't have a role attribute
     $pattern = '/<svg(?![^>]*\brole=)[^>]*>/i';

     // Callback function to add role="none"
     $block_content = preg_replace_callback($pattern, function ($matches) {
         return str_replace('<svg', '<svg role="none"', $matches[0]);
     }, $block_content);

     return $block_content;

  }

  /**
   * Strips the "auto, " prefix that WordPress 6.7+ prepends to the sizes attribute
   * on lazy-loaded images when a custom sizes override is set.
   *
   * Hooks into wp_content_img_tag which fires per-image during wp_filter_content_tags(),
   * after WP adds the prefix. Works in all contexts (post content, template parts, patterns).
   *
   * Without this, the browser uses the element's current rendered width (auto)
   * which defeats the purpose of a manual override — e.g. when GSAP animations
   * scale an image from a small starting size to full viewport width.
   *
   * @since 3.0.0
   */
  function image_sizes_override_cleanup( $image ) {
    if ( strpos( $image, 'data-nectar-sizes-override' ) === false ) {
      return $image;
    }

    // Remove "auto, " prefix from sizes attribute.
    return preg_replace( '/\bsizes="auto,\s*/', 'sizes="', $image );
  }

  /**
   * Adds the _nectar_blocks_css post metadata field when we are in a block theme for wp_template_parts.
   * @since 0.2.2
   * @version 0.2.2
   */
  function render_template_part( $block_content, $block ) {
    // Bail early if we are not in a block theme
    if ( ! wp_is_block_theme() ) {
      return $block_content;
    }
    global $nectar_template_parts_css;
    if ( empty($nectar_template_parts_css) ) {
      $nectar_template_parts_css = '';
    }

    // Skip non core/template-part blocks
    if (array_key_exists('blockName', $block) && $block['blockName'] !== 'core/template-part') {
      return $block_content;
    }

    if (! array_key_exists('attrs', $block)) {
      Log::error('No attrs found for wp_template_part');
      return $block_content;
    }
    $attrs = $block['attrs'];

    if (! array_key_exists('slug', $attrs)) {
      Log::error('No attrs slug found for wp_template_part');
      return $block_content;
    }
    $slug = $attrs['slug'];

    if (! array_key_exists('theme', $attrs)) {
      Log::error('No attrs theme found for wp_template_part');
      return $block_content;
    }
    $theme = $attrs['theme'];
    $post_name = $theme . '//' . $slug;
    $block_template = get_block_template($post_name, 'wp_template_part');
    $post_id = $block_template->wp_id;

    $theme_part_css = $this->get_dynamic_block_css($post_id);
    Log::debug('render_template_part', [
      'time' => time(),
      'post_name' => $post_name,
      'post_id' => $post_id,
      'theme_part_css' => $theme_part_css
    ]);

    if ($theme_part_css) {
      $nectar_template_parts_css .= $theme_part_css;
    }

    return $block_content;
  }

  /**
   * Render-block fallback: inlines block CSS for blocks not caught by the
   * primary detection in collect_page_block_css() (e.g. synced patterns
   * inside template parts, dynamically generated blocks).
   *
   * Uses the same dedup tracker — if the main path already handled the
   * block, get_block_inline_css() returns empty and the filter is a no-op.
   */
  function block_styles() {

    foreach ( Blocks::$block_list as $block => $args ) {
      $path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block . '/frontend-style.css';

      if ( ! file_exists( $path ) || is_admin() ) {
        continue;
      }

      // Blocks can opt out of inline CSS delivery via `'frontend_style_delivery' => 'link'`
      // in Blocks::$block_list. Required when a block lives inside another block whose
      // render rebuilds inner HTML and drops siblings of the block wrapper (e.g. megamenu
      // inside core/navigation-submenu), which would strip the prepended <style>.
      $delivery = ( $args['frontend_style_delivery'] ?? 'inline' ) === 'link' ? 'link' : 'inline';

      add_filter(
          "render_block_nectar-blocks/$block",
          function ( $block_content ) use ( $block, $delivery ) {
          return $this->deliver_block_style( $block, $delivery, $block_content );
        },
          10,
          1
      );
    }
  }

  /**
   * Per-render delivery decision for a block's frontend-style.css.
   */
  private function deliver_block_style( string $block, string $delivery, $block_content ) {
    // Already delivered — but if the earlier delivery was a body-prepended
    // <style> and we're now rendering inside a dynamic ancestor's
    // render_callback (WP_Block_Supports::$block_to_render is set), that
    // ancestor may have discarded the pass carrying the <style> and be
    // re-rendering its inner blocks (e.g. woocommerce/product-filters).
    // Enqueue the <link> as a backstop so the CSS survives the discard.
    if ( isset( $this->delivered_block_styles[$block] ) ) {
      if ( isset( $this->discardable_inline_styles[$block] ) && null !== \WP_Block_Supports::$block_to_render ) {
        $this->enqueue_block_style_link( $block );
        unset( $this->discardable_inline_styles[$block] );
      }
      return $block_content;
    }

    $this->enqueue_block_style_deps( $block );

    if ( 'link' === $delivery ) {
      $this->enqueue_block_style_link( $block );
      $this->delivered_block_styles[$block] = true;
      return $block_content;
    }

    $css = $this->get_block_inline_css( $block );
    if ( ! $css ) {
      // Inline read failed — fall back to fetching the CSS file via HTTP.
      $this->enqueue_block_style_link( $block );
      return $block_content;
    }
    $this->discardable_inline_styles[$block] = true;
    return '<style id="nectar-blocks-' . esc_attr( $block ) . '-css">' . $css . '</style>' . $block_content;
  }

  /**
   * Registers (if needed) and enqueues a block's frontend-style.css as a real <link>.
   * Used by both the link-delivery opt-in and the inline-read-failed fallback.
   */
  private function enqueue_block_style_link( string $block ): void {
    $handle = "nectar-blocks-$block";
    if ( ! wp_style_is( $handle, 'registered' ) ) {
      wp_register_style(
          $handle,
          NECTAR_BLOCKS_BUILD_PATH . '/blocks/' . $block . '/frontend-style.css',
          [],
          NECTAR_BLOCKS_VERSION
      );
    }
    wp_enqueue_style( $handle );
  }

  /**
   * Enqueues any stylesheet deps declared for a block (e.g. Swiper, LightGallery).
   * Safe to call multiple times per block — wp_enqueue_style is idempotent.
   */
  private function enqueue_block_style_deps( string $block ): void {
    $args = Blocks::$block_list[$block] ?? [];
    if ( ! empty( $args['deps'] ) ) {
      foreach ( $args['deps'] as $dep ) {
        wp_enqueue_style( $dep );
      }
    }
  }

  /**
   * Returns a block's frontend CSS for inlining and marks the block delivered
   * to prevent duplicates. Callers delivering via a discardable body-prepended
   * <style> also record the block in $discardable_inline_styles.
   */
  private function get_block_inline_css( string $block ): string {
    if ( isset( $this->delivered_block_styles[$block] ) ) {
      return '';
    }

    $path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block . '/frontend-style.css';
    if ( ! file_exists( $path ) ) {
      return '';
    }

    $css = file_get_contents( $path );
    if ( $css ) {
      $this->delivered_block_styles[$block] = true;
      return $css;
    }

    return '';
  }

  /**
   * Get all block names in the post content.
   *
   * @param string   $content The content to search for blocks.
   * @return array   $block_names The block names.
   */
  function get_blocks_in_content($content) {

     $blocks = parse_blocks($content);

      $block_names = [];
      $this->collect_block_names($blocks, $block_names);

      // remove all blocks that are not nectar blocks
      $block_names = array_filter($block_names, function($block_name) {
        return str_starts_with($block_name, 'nectar-blocks/');
      });
      // remove nectar-blocks/ from the block name
      $block_names = array_map(function($block_name) {
        return str_replace('nectar-blocks/', '', $block_name);
      }, $block_names);

     // Remove duplicates and return unique block names
     if ( $block_names ) {
      return array_unique($block_names);
     }

     return []; // Return empty array if post not found
  }

  // Recursive function to process blocks and collect their names
  function collect_block_names($blocks, &$block_names) {
    foreach ($blocks as $block) {
        // Add block name to the array if it exists
        if (! empty($block['blockName'])) {
            $block_names[] = $block['blockName'];
        }

        // Check for nested blocks in innerBlocks
        if (! empty($block['innerBlocks'])) {
            $this->collect_block_names($block['innerBlocks'], $block_names);
        }
    }
  }

  function localization() {
    load_plugin_textdomain( 'nectar-blocks', false, NECTAR_BLOCKS_FOLDER_NAME . '/languages' );
  }

  function admin_enqueue() {
    $screen = get_current_screen();
    if( $screen && $screen->id && 'customize' === $screen->id) {
      // Uploaded fonts.
      $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('frontend');
      if ( $uploaded_fonts ) {
        wp_add_inline_style( 'nectar-customizer-css', $uploaded_fonts);
      }
    }
  }

  /**
   * Registers or Enqueues JS for frontend
   */
  function frontend_render_scripts() {
    global $post;
    // Single GSAP bundle — gsap core + ScrollTrigger + CustomEase in one file.
    wp_register_script( 'gsap', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/gsap.bundle.min.js', [], '3.12.7', true );

    wp_register_script( 'split-type', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/split-type/index.min.js', [], '0.3.4', true );
    wp_register_script( 'count-up', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/countup/countup.js', [], '2.8.0', true );
    wp_register_script( 'swiper', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/swiper/swiper-bundle.min.js', [], '12.1.2', true );
    wp_register_script( 'fitty', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/fitty/fitty.min.js', [], '2.4.2', true);

    $frontend_JS_asset_path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-frontend.asset.php';
    $args_array = include($frontend_JS_asset_path);
    // localize page id to nectar-frontend

    wp_register_script( 'nectar-blocks-frontend', NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-frontend.js', $args_array['dependencies'], $args_array['version'], true );
    wp_enqueue_script( 'nectar-blocks-frontend' );
    if ( isset($post->ID) ) {
      // Post JS
      $post_JS = get_post_meta( $post->ID, '_nectar_blocks_page_js', true );
      wp_add_inline_script( 'nectar-blocks-frontend', $post_JS );
    }
    wp_localize_script( 'nectar-blocks-frontend', 'nectarBlocksData', [
      'postID' => get_the_ID()
    ]);

    $conditional_scripts = $this->get_conditional_scripts_detector( $post instanceof WP_Post ? $post : null );
    $conditional_script_manager = new Conditional_Script_Manager( $conditional_scripts );
    $conditional_script_manager->register_scripts();

    $lightbox_style_requirements = $conditional_script_manager->get_requirements_for_handle( 'nectar-blocks-lightbox' );
    if ( $conditional_scripts->should_enqueue( $lightbox_style_requirements ) ) {
      wp_enqueue_style( 'nectar-blocks-lightgallery' );
    }
  }

  protected function get_conditional_scripts_detector( ?WP_Post $post = null ): Conditional_Scripts {
    if ( $this->conditional_assets_detector instanceof Conditional_Scripts ) {
      return $this->conditional_assets_detector;
    }

    $this->conditional_assets_detector = Conditional_Scripts::from_post( $post );
    return $this->conditional_assets_detector;
  }

  /**
   * Detects which nectar blocks are on the current page, reads their
   * frontend-style.css files, and returns one concatenated CSS string.
   *
   * Called once from frontend_render_styles() before dynamic CSS is added,
   * so block base styles sit below dynamic overrides in the cascade.
   */
  protected function collect_page_block_css(): string {
    global $post;

    // Always include foundational layout blocks — they appear on virtually
    // every page and missing them causes visible FOUC.
    $block_names = [ 'row', 'column', 'text' ];

    // 1. Conditional detector — post content, nectar templates, global sections,
    //    synced patterns, block widgets in registered sidebars.
    $detector = $this->get_conditional_scripts_detector( $post instanceof WP_Post ? $post : null );
    $block_names = array_merge( $block_names, $detector->get_detected_block_names() );

    // 2. Block themes — also scan WP template + template parts.
    if ( wp_is_block_theme() ) {
      $block_names = array_merge( $block_names, $this->get_template_block_names() );
    }

    // 3. Deduplicate and filter to registered blocks.
    $block_names = array_unique( $block_names );
    $registered = array_keys( Blocks::$block_list );
    $blocks = array_intersect( $block_names, $registered );

    // Allow exclusion of specific blocks from inlining (e.g. for asset management plugins).
    // Mark excluded blocks in the dedup tracker so the render_block fallback skips them too.
    $excluded = apply_filters( 'nectar_blocks/excluded_inline_styles', [] );
    if ( ! empty( $excluded ) ) {
      foreach ( $excluded as $excluded_block ) {
        $this->delivered_block_styles[$excluded_block] = true;
      }
      $blocks = array_diff( $blocks, $excluded );
    }

    if ( empty( $blocks ) ) {
      return '';
    }

    // 4. Concatenate CSS + enqueue deps. Blocks opted into 'link' delivery
    // get enqueued as a <link> instead of bundled into this inline string.
    $css = '';
    foreach ( $blocks as $block ) {
      $this->enqueue_block_style_deps( $block );
      $args = Blocks::$block_list[$block] ?? [];
      if ( ( $args['frontend_style_delivery'] ?? 'inline' ) === 'link' ) {
        $this->enqueue_block_style_link( $block );
        $this->delivered_block_styles[$block] = true;
        continue;
      }
      $block_css = $this->get_block_inline_css( $block );
      if ( $block_css ) {
        $css .= $block_css;
      }
    }

    return $css;
  }

  /**
   * Returns nectar block names found in the current WP template and its
   * template parts. Block themes only.
   *
   * @return string[] Block names without the nectar-blocks/ prefix.
   */
  private function get_template_block_names(): array {
    global $_wp_current_template_id;
    if ( empty( $_wp_current_template_id ) ) {
      return [];
    }

    $block_template = get_block_template( $_wp_current_template_id, 'wp_template' );
    if ( ! $block_template || empty( $block_template->content ) ) {
      return [];
    }

    // Blocks in the template itself.
    $names = $this->get_blocks_in_content( $block_template->content );

    // Blocks in template parts.
    $blocks = parse_blocks( $block_template->content );
    $flat = $this->flatten_blocks( $blocks );
    foreach ( $flat as $block ) {
      if ( ( $block['blockName'] ?? '' ) !== 'core/template-part' ) {
        continue;
      }
      $theme = $block['attrs']['theme'] ?? get_stylesheet();
      $slug = $block['attrs']['slug'] ?? '';
      if ( empty( $slug ) ) {
        continue;
      }

      $part = get_block_template( $theme . '//' . $slug, 'wp_template_part' );
      if ( $part && ! empty( $part->content ) ) {
        $names = array_merge( $names, $this->get_blocks_in_content( $part->content ) );
      }
    }

    return $names;
  }

  /**
   * Enqueues the admin scripts in the customizer.
   * @since 1.3.5
   * @version 1.3.5
   */
  function customize_controls_enqueue_scripts() {
    wp_register_script( 'nectar-blocks-env-variables', '', );
    wp_enqueue_script( 'nectar-blocks-env-variables' );
    wp_add_inline_script(
        'nectar-blocks-env-variables',
        'window.nectarblocks_env =' . wp_json_encode($this->get_nectar_blocks_env_variables(), JSON_HEX_TAG | JSON_HEX_AMP) . ';',
        'before'
    );
  }

  /**
   * Gets the nectar blocks env variables.
   * @since 1.3.5
   * @version 2.0.0
   * @return array
   */
  function get_nectar_blocks_env_variables() {
    $output = [];

    if (defined('NECTAR_BLOCKS_VERSION')) {
      $output['NB_PLUGIN_VERSION'] = NECTAR_BLOCKS_VERSION;
    } else {
      $output['NB_PLUGIN_VERSION'] = null;
    }

    if (defined('NB_THEME_VERSION')) {
      $output['NB_THEME_VERSION'] = NB_THEME_VERSION;
    } else {
      $output['NB_THEME_VERSION'] = null;
    }

    if (defined('NB_IE_VERSION')) {
      $output['NB_IE_VERSION'] = NB_IE_VERSION;
    } else {
      $output['NB_IE_VERSION'] = null;
    }

    $output['PHP_VERSION'] = phpversion();
    $wp_version = get_bloginfo('version');
    if (str_contains($wp_version, '-')) {
      $wp_version = explode('-', $wp_version)[0];
    }
    $output['WORDPRESS_VERSION'] = $wp_version;
    $output['IS_BLOCK_THEME'] = wp_is_block_theme();
    $output['WOOCOMMERCE_ACTIVE'] = class_exists('WooCommerce');
    $output['FLUENT_FORMS_ACTIVE'] = function_exists('wpFluent');

    // WooCommerce page URLs for header action blocks.
    if ( class_exists('WooCommerce') && function_exists('wc_get_page_id') ) {
      $my_account_page_id = wc_get_page_id('myaccount');
      $output['WC_MY_ACCOUNT_URL'] = $my_account_page_id ? get_permalink($my_account_page_id) : '/my-account/';
    } else {
      $output['WC_MY_ACCOUNT_URL'] = '/my-account/';
    }
    // Theme-dependent header builder blocks rely on Nectar Blocks theme JS/CSS.
    // Keep this in sync with `Enhanced_Navigation::is_nectar_blocks_theme_active()`.
    if ( defined( 'NECTAR_BLOCKS_FORCE_THEME_ACTIVE' ) && true === NECTAR_BLOCKS_FORCE_THEME_ACTIVE ) {
      $output['NB_THEME_ACTIVE'] = true;
    } else {
      $output['NB_THEME_ACTIVE'] = class_exists( '\NectarThemeManager' );
    }

    // Check if header builder (header navigation template) is in use.
    $output['HAS_HEADER_BUILDER'] = \Nectar\Nectar_Templates\Nectar_Templates::has_header_navigation_template();

    // Off-canvas-menu template parts (`[{ id, title }, ...]`, empty when none
    // assigned). Used by the enhanced core/navigation inspector to swap the
    // overlay menu-source dropdown for "Edit template" link(s) when the theme
    // is active. Multiple are possible — each can be condition-scoped.
    $output['OCM_TEMPLATE_PARTS'] = \Nectar\Nectar_Templates\Nectar_Templates::get_ocm_template_parts();

    $output['IS_WIDGET_EDITOR'] = false;
    $screen = get_current_screen();
    if ( property_exists($screen, 'base') ) {
      if ($screen->base === 'widgets') {
        $output['IS_WIDGET_EDITOR'] = true;
      }
    }
    $output['ASSETS_PATH'] = NECTAR_BLOCKS_PLUGIN_PATH . '/assets';

    $output['WORDPRESS_ADMIN_URL'] = admin_url();
    $output['THEME_OPTIONS_URL'] = admin_url('customize.php');

    $nectar_plugin_options = Nectar_Plugin_Options::get_options();
    $output['NECTAR_PLUGIN_SETTINGS'] = [
      'shouldDisableNectarGlobalTypography' => $nectar_plugin_options['shouldDisableNectarGlobalTypography'],
      'defaultTextBlock' => isset($nectar_plugin_options['defaultTextBlock']) ? $nectar_plugin_options['defaultTextBlock'] : 'nectar',
      'shouldHideTitleDefault' => isset($nectar_plugin_options['shouldHideTitleDefault']) ? (bool) $nectar_plugin_options['shouldHideTitleDefault'] : false
    ];

    return $output;
  }

  /**
   * Used to set global context variables for the admin.
   */
  function admin_head() {
    $NB_env_vars = $this->get_nectar_blocks_env_variables();
    echo '<script>window.nectarblocks_env =' . wp_json_encode($NB_env_vars, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
  }

  /**
   * Renders in the head tag.
   */
  function render_head() {
    // Custom code in head
    $custom_code = get_option('nectar_code_options');
    if ( is_array($custom_code) && ! empty($custom_code['jsCodeHead']) ) {
      echo $custom_code['jsCodeHead'];
    }
  }

  /**
   * Renders after the opening body tag.
   */
  function wp_body_open() {

    // Custom code after body
    $custom_code = get_option('nectar_code_options');
    if ( is_array($custom_code) && ! empty($custom_code['jsCodeBody']) ) {
      echo $custom_code['jsCodeBody'];
    }
  }

  /**
   * Outputs the Nectarblocks generator comment for discoverability.
   *
   * @since 3.0.0
   * @filter nectar_blocks_show_generator Enable/disable the generator output. Default true.
   */
  function render_nectarblocks_generator() {
    if ( ! apply_filters( 'nectar_blocks_show_generator', true ) ) {
      return;
    }
    echo "<!-- Built with Nectarblocks · https://nectarblocks.com -->\n";
  }

  function frontend_pattern_set($blocks) {
    $pattern_set = [];
    $blocks = $this->flatten_blocks($blocks);

    foreach( $blocks as $block ) {
      if ( 'core/block' === $block['blockName'] && isset($block['attrs']['ref']) ) {
        $pattern_ref = $block['attrs']['ref'];
        $pattern_set[$pattern_ref] = true;
        Log::debug('Adding to pattern set: ' . $pattern_ref);

        $pattern_post = get_post($pattern_ref);
        if ( ! $pattern_post ) { continue; }
        $sub_blocks = parse_blocks($pattern_post->post_content);
        $sub_patterns = $this->frontend_pattern_set($sub_blocks);
        $pattern_set = $pattern_set + $sub_patterns;
      }
    }

    return $pattern_set;
  }

  function flatten_blocks( &$blocks ) {
    $all_blocks = [];
    $queue = [];
    foreach ( $blocks as &$block ) {
      $queue[] = &$block;
    }

    while ( count( $queue ) > 0 ) {
      $block = &$queue[0];
      array_shift( $queue );
      $all_blocks[] = &$block;

      if ( ! empty( $block['innerBlocks'] ) ) {
        foreach ( $block['innerBlocks'] as &$inner_block ) {
          $queue[] = &$inner_block;
        }
      }
    }

    return $all_blocks;
  }

  /**
   * Gets the dynamic block css.
   * @since 2.0.0
   * @version 2.0.0
   * @param int|null $id
   * @param bool $is_preview
   * @return string
   */
  public function get_dynamic_block_css($id, bool $is_preview = false): string {
    if ( $is_preview ) {
      $css = get_post_meta( $id, '_nectar_blocks_css_preview', true );
    } else {
      $css = get_post_meta( $id, '_nectar_blocks_css', true );
    }
    if ( ! $css ) {
      return '';
    }

    $FE_RENDER = new Frontend_Render();
    return $FE_RENDER->render_dynamic_content([], $css);
  }

  /**
   * Build the `#nectar-nav` state-transition baseline CSS for the current
   * request. Returns the empty string when no Nectar header-navigation
   * template is rendering — `#nectar-nav` only exists on pages that load a
   * header template, so emitting elsewhere ships dead CSS.
   *
   * Picks the first active header template that defines a non-sentinel
   * duration/easing override; otherwise emits only the defaults. The CSS
   * is page-level (vars on `#nectar-nav` + `.is-transitioning-state` rule)
   * — child blocks inherit the vars via CSS custom-property inheritance.
   *
   * SYNC: the editor canvas mirrors this output via
   * `generateNavigationStateTransitionBaseline()` in
   * `plugin/src/editor/wp-block-enhancements/core-navigation/styles/base-styles.ts`.
   * If you change the rules emitted here, update that helper too or the
   * editor preview will drift from the rendered page.
   */
  protected function build_navigation_state_transition_css(): string {
    $active_ids = \Nectar\Nectar_Templates\Render::get_active_header_template_ids();
    if ( empty( $active_ids ) ) {
      return '';
    }

    $duration_decl = '';
    $timing_decl = '';
    foreach ( $active_ids as $template_id ) {
      $meta = get_post_meta( $template_id, '_nectar_header_state_transition', true );
      if ( ! is_array( $meta ) ) {
        continue;
      }
      if ( $duration_decl === '' && isset( $meta['durationSec'] ) && is_finite( (float) $meta['durationSec'] ) && (float) $meta['durationSec'] > 0 ) {
        $duration_decl = sprintf(
            // %F (not %s) so the decimal separator is locale-independent —
            // under LC_NUMERIC locales like de_DE, %s would emit "0,42s".
            '--nectar-enhanced-navigation-transition-duration: %Fs;',
            (float) $meta['durationSec']
        );
      }
      if ( $timing_decl === '' && isset( $meta['bezier'] ) && is_array( $meta['bezier'] ) && count( $meta['bezier'] ) === 4 ) {
        $b = array_map( 'floatval', $meta['bezier'] );
        // Reject NAN/INF coefficients — sprintf('%F', NAN) emits the literal
        // "NAN", producing invalid cubic-bezier() CSS (mirrors the durationSec
        // is_finite() guard above).
        if ( count( array_filter( $b, 'is_finite' ) ) === 4 ) {
          $timing_decl = sprintf(
              // %F (not %s) keeps the cubic-bezier coefficients locale-independent;
              // a comma-decimal locale would otherwise break the CSS function.
              '--nectar-enhanced-navigation-transition-timing: cubic-bezier(%F, %F, %F, %F);',
              $b[0],
              $b[1],
              $b[2],
              $b[3]
          );
        }
      }
      if ( $duration_decl !== '' && $timing_decl !== '' ) {
        break;
      }
    }

    $override_rule = '';
    if ( $duration_decl !== '' || $timing_decl !== '' ) {
      $override_rule = '#nectar-nav { ' . trim( $duration_decl . ' ' . $timing_decl ) . ' }';
    }

    // Opt-out: elements that are continuously animating (e.g. ticker track,
    // GSAP-driven transforms) mark themselves with `data-nb-no-state-transition`.
    // The `:not()` excludes both the marked element AND its descendants so
    // child blocks inside an animating wrapper aren't forced into `transition: all`.
    return '#nectar-nav {'
      . '--nectar-enhanced-navigation-transition-duration: var(--nectar-swift-out-transition-duration);'
      . '--nectar-enhanced-navigation-transition-timing: var(--nectar-swift-out-transition-timing);'
      . '--nectar-enhanced-navigation-transition-properties: all;'
      . '}'
      . $override_rule
      . '#nectar-nav.is-transitioning-state,'
      . '#nectar-nav.is-transitioning-state *:not([data-nb-no-state-transition], [data-nb-no-state-transition] *) {'
      . 'transition-property: var(--nectar-enhanced-navigation-transition-properties) !important;'
      . 'transition-duration: var(--nectar-enhanced-navigation-transition-duration) !important;'
      . 'transition-timing-function: var(--nectar-enhanced-navigation-transition-timing) !important;'
      . '}';
  }

  /**
  * Search all global sections for block frontend styles to enqueue.
  */

  /**
   * Adds pattern css that is saved in _nectar_blocks_css metadata.
   */
  function frontend_pattern_css($blocks) {
    $pattern_set = $this->frontend_pattern_set($blocks);
    Log::debug('Pattern Set: ' . json_encode($pattern_set));

    $patterns_css = '';
    foreach( $pattern_set as $block_id => $v ) {
      Log::debug('Patten CSS output for ' . $block_id);
      $pattern_css = $this->get_dynamic_block_css($block_id);
      $patterns_css .= $pattern_css;
    }

    return $patterns_css;
  }

  /**
   * Registers or Enqueues CSS for frontend
   */
  function add_google_preconnect_links() {
    echo '<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>';
  }

  function add_adobe_preconnect_links(): void {
    echo '<link rel="preconnect" href="https://use.typekit.net" crossorigin>';
  }

  function add_custom_fonts_preload_links(): void {
    $custom_fonts = apply_filters('nectar_custom_font_list', Nectar_Custom_Fonts::get_options());
    if ( ! empty($custom_fonts) ) {
      foreach ($custom_fonts as $slug => $custom_font) {
        foreach ($custom_font['variations'] as $variation) {
          $exploded = explode('.', $variation['url']);
          $format = array_pop($exploded);
          if ($format !== 'woff' && $format !== 'woff2') {
            $format = 'truetype';
          }
          echo '<link rel="preload" href="' . HTTP::maybe_force_https(esc_attr($variation['url'])) . '" as="font" type="font/' . esc_attr($format) . '" crossorigin>';
        }
      }
    }
  }

  /**
   * Enqueue Google fonts - either locally or from Google CDN.
   *
   * Performance note: Heavy generation only runs when:
   * 1. Initial setup (no local fonts cached yet)
   * 2. After typography settings are updated (cache is cleared)
   * 3. After plugin options change (cache is cleared)
   *
   * On normal page loads, this just checks for a stored URL (single get_option call).
   *
   * Safety: Generation is rate-limited to prevent infinite loops. If generation fails,
   * it won't retry for 5 minutes, falling back to Google CDN in the meantime.
   *
   * @since 3.0
   */
  function enqueue_google_fonts(): void {
    // Check if local Google fonts is enabled
    // Skip in Customizer preview to allow live font changes via Google CDN
    if ( Local_Google_Fonts::is_enabled() && ! is_customize_preview() ) {
      // First, try to get the cached local CSS URL (lightweight check)
      $local_css_url = Local_Google_Fonts::get_local_css_url();

      // If we have a cached URL, use it directly without any heavy processing
      if ( ! empty( $local_css_url ) ) {
        wp_enqueue_style( 'nectar-blocks-google-fonts', $local_css_url, [], null );
        return;
      }

      // No cached URL - check if we can attempt generation
      // This prevents infinite loops: if generation failed, we wait 5 minutes before retrying
      if ( Local_Google_Fonts::can_attempt_generation() ) {
        // Generate the local fonts now
        Local_Google_Fonts::generate();

        // Try again after generation
        $local_css_url = Local_Google_Fonts::get_local_css_url();
        if ( ! empty( $local_css_url ) ) {
          wp_enqueue_style( 'nectar-blocks-google-fonts', $local_css_url, [], null );
          return;
        }
      }
      // If generation is locked (recently failed), fall through to Google CDN
    }

    // Fallback to Google CDN (if local fonts disabled, generation failed, or in retry lockout)
    $google_fonts = Global_Typography::create_google_fonts_link('frontend');
    if ( $google_fonts ) {
      add_action('wp_head', [$this, 'add_google_preconnect_links'], 4);
      wp_enqueue_style( 'nectar-blocks-google-fonts', $google_fonts, [], null );
    }
  }

  function frontend_render_styles() {
    global $post;

    // NB Plugin Options
    $nb_plugin_options = Nectar_Plugin_Options::get_options();

    // General Frontend Styles
    wp_enqueue_style( 'nectar-frontend-global', NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-core.css', [], NECTAR_BLOCKS_VERSION);

    // Third party styles — registered before block CSS so deps can be enqueued.
    wp_register_style('nectar-blocks-swiper', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/swiper/bundle.css', [], '12.1.2');
    wp_register_style('nectar-blocks-lightgallery', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/lightgallery/css/bundle.min.css', [], '9.4.1');

    // Block base CSS — detected per page, concatenated, inlined before dynamic CSS.
    $block_css = $this->collect_page_block_css();
    if ( $block_css ) {
      wp_add_inline_style( 'nectar-frontend-global', $block_css );
    }

    // Custom CSS
    $custom_code = get_option('nectar_code_options');
    if ( is_array($custom_code) && ! empty($custom_code['cssCode']) ) {
      wp_add_inline_style( 'nectar-frontend-global', $custom_code['cssCode'] );
    }

    // Google fonts.
    $this->enqueue_google_fonts();

    // Uploaded fonts.
    $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('frontend');
    if ( $uploaded_fonts ) {
      wp_add_inline_style( 'nectar-frontend-global', $uploaded_fonts);
      add_action('wp_head', [$this, 'add_custom_fonts_preload_links'], 4);
    }

    // Adobe Fonts — only load kits containing fonts actually used in typography settings.
    $used_adobe_kit_ids = Global_Typography::get_used_adobe_kit_ids();
    if ( ! empty( $used_adobe_kit_ids ) ) {
      foreach ( $used_adobe_kit_ids as $kit_id ) {
        wp_enqueue_style(
            'nectar-blocks-adobe-fonts-' . sanitize_key( $kit_id ),
            'https://use.typekit.net/' . sanitize_key( $kit_id ) . '.css',
            [],
            null
        );
      }
      add_action( 'wp_head', [ $this, 'add_adobe_preconnect_links' ], 4 );
    }

    // Post title visibility
    if ( isset($post->ID) ) {
      // Post title visibility
      $global_post_hide_title_vis = $nb_plugin_options['shouldHideTitleDefault'];
      $post_hide_title_vis = get_post_meta( $post->ID, '_nectar_blocks_hide_post_title', true );
      $post_title_css = '';

      if ( $post_hide_title_vis === '1' || ($global_post_hide_title_vis === true && is_page()) ) {
        $post_title_css = 'h1.wp-block-post-title {
          display: none;
        }';
        wp_add_inline_style( 'nectar-frontend-global', $post_title_css );
      }

      // Post CSS
      $post_CSS = get_post_meta( $post->ID, '_nectar_blocks_page_css', true );
      wp_add_inline_style( 'nectar-frontend-global', $post_CSS );
    }

    // Dynamic styles from block element settings.
    $dynamic_css = '';

    if ($post) {
      $post_content = $post->post_content;
      if ($post_content) {
        $blocks = parse_blocks($post_content);
        $patterns_css = $this->frontend_pattern_css($blocks);

        $dynamic_css .= $patterns_css;
      }
    }

    $post_status = get_post_status( get_the_ID() );
    $use_preview_css = false;
    if ( is_preview() ) {
      // Skip drafts and auto drafts. They should not use the preview CSS.

      // Published / Private posts
      if ( in_array($post_status, ['publish', 'private']) ) {
        $use_preview_css = true;
      }

      // Some post statuses will technically return true for is_preview()
      // in both the actual preview and the frontend. We can determine which is the
      // real preview by checking the query var.

      // Scheduled / Pending posts
      if ( in_array($post_status, ['future', 'pending']) && get_query_var('preview') ) {
        $use_preview_css = true;
      }

    }

    if ( $use_preview_css ) {
      $dynamic_css .= $this->get_dynamic_block_css( get_the_ID(), true );
    } else {
      $dynamic_css .= $this->get_dynamic_block_css( get_the_ID() );
    }

    wp_add_inline_style( 'nectar-frontend-global', $dynamic_css );

    // Header navigation state-transition baseline. Emitted whenever a Nectar
    // header-navigation template renders on this request — the `#nectar-nav`
    // CSS vars and `.is-transitioning-state` rule are page-level concerns, so
    // they live here rather than baked into per-post block CSS.
    $nav_state_css = $this->build_navigation_state_transition_css();
    if ( $nav_state_css !== '' ) {
      wp_add_inline_style( 'nectar-frontend-global', $nav_state_css );
    }

    // Dynamic styles from widgets
    $widget_dynamic_css = get_option('nectar_blocks_widgets_css');
    if ( ! empty($widget_dynamic_css) ) {
      wp_add_inline_style( 'nectar-frontend-global', $widget_dynamic_css );
    }

    // Dynamic styles from FSE templates
    $fse_dynamic_css = get_option('nectar_blocks_fs_templates_css');
    if ( ! empty($fse_dynamic_css) ) {

      $css = '';
      foreach ( $fse_dynamic_css as $template => $styles ) {
        $css .= $styles;
      }

      if ( ! empty($css) ) {
        wp_add_inline_style( 'nectar-frontend-global', $css );
      }

    }

    // Adds CSS vars from our Global Settings
    // -- Global Colors
    $color_css = Global_Colors::css_output();
    if ( $color_css ) {
      wp_add_inline_style( 'nectar-frontend-global', $color_css );
    }

    // -- Global Typography
    // Disable global typography output if required
    $typography_css = Global_Typography::css_output( 'render', $nb_plugin_options['shouldDisableNectarGlobalTypography'] );
    if ( $typography_css ) {
      wp_add_inline_style( 'nectar-frontend-global', $typography_css );
    }

    // Get wp_template CSS, only for block based themes
    if (wp_is_block_theme()) {
      global $_wp_current_template_id;
      $block_template = get_block_template($_wp_current_template_id, 'wp_template');
      $post_id = $block_template->wp_id;
      $template_css = $this->get_dynamic_block_css($post_id);
      if ($template_css) {
        Log::debug('Rendering wp_template ' . $_wp_current_template_id . ' ' . $post_id . ': ' . $template_css);
        wp_add_inline_style( 'nectar-frontend-global', $template_css );
      }
    }

    // wp_template_part CSS for block theme
    global $nectar_template_parts_css;
    if (! empty($nectar_template_parts_css)) {
      Log::debug('Rendering wp_template_part css:' . $nectar_template_parts_css);
      wp_add_inline_style( 'nectar-frontend-global', $nectar_template_parts_css );
    }

  }
}
