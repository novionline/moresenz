<?php

namespace Nectar\Render;

use Nectar\Editor\Blocks;
use Nectar\Global_Settings\{ Global_Colors, Global_Typography, Nectar_Plugin_Options, Nectar_Custom_Fonts };
use Nectar\Render\{RenderJS, Compatibility};
use Nectar\Utilities\{Log, HTTP};
use Nectar\Dynamic_Data\{Frontend_Render};
use Nectar\Nectar_Templates\{Nectar_Templates};

/**
 * Blocks Editor configuration
 * @version 1.3.4
 * @since 0.0.2
 */
class Render {
  function __construct() {
    $this->initialize_hooks();
    $renderJS = new RenderJS();
    $compatibility = new Compatibility();
  }

  function initialize_hooks() {
    add_filter( 'should_load_separate_core_block_assets', '__return_true' );
    add_filter( 'render_block', [$this, 'render_template_part'], 10, 2 );

    add_filter( 'render_block', [$this, 'accessible_svgs'], 10, 2 );
    if ( wp_is_block_theme() ) {
      add_action( 'after_setup_theme', [$this, 'block_styles'] );
    } else {
      add_action( 'wp_enqueue_scripts', [$this, 'register_block_styles'] );
      add_action( 'wp_enqueue_scripts', [$this, 'classic_theme_frontend_block_styles'] );
      add_action( 'after_setup_theme', [$this, 'block_styles'] ); // leaving this as a fallback for safety.
    }
    add_action( 'plugins_loaded', [$this, 'localization'] );
    add_action( 'wp_enqueue_scripts', [$this, 'frontend_render_styles'], 99 );
    add_action( 'wp_enqueue_scripts', [$this, 'frontend_render_scripts'] );

    add_action( 'admin_enqueue_scripts', [$this, 'admin_enqueue'], 99 );
    add_action( 'admin_head', [$this, 'admin_head'], 1 );
    add_action( 'customize_controls_enqueue_scripts', [$this, 'customize_controls_enqueue_scripts'], 99 );
    add_action( 'wp_head', [$this, 'render_head'] );
    add_action( 'wp_body_open', [$this, 'wp_body_open'] );
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
   * Enqueues core stylesheets for each block when a block is found on the page.
   */
  function block_styles() {

    foreach ( Blocks::$block_list as $block => $args) {
      $asset_args = [
        'handle' => "nectar-blocks-$block",
        'src' => NECTAR_BLOCKS_BUILD_PATH . '/blocks/' . $block . '/frontend-style.css',
        'path' => NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block . '/frontend-style.css',
      ];

      // deps.
      if ( isset( $args['deps'] ) && ! empty($args['deps']) ) {
        $asset_args['deps'] = $args['deps'];
      }

      if ( file_exists($asset_args['path']) ) {
        // --------- LOADED IN FRONTEND ONLY -------------
        if( ! is_admin() ) {
          wp_enqueue_block_style( "nectar-blocks/$block", $asset_args );
        }
      }

    }
  }

  /**
   * Register block frontend stylesheets
   * when using a classic theme.
   */
  function register_block_styles() {
    foreach ( Blocks::$block_list as $block => $args) {
      $asset_args = [
        'handle' => "nectar-blocks-$block",
        'path' => NECTAR_BLOCKS_PLUGIN_PATH . '/build/blocks/' . $block . '/frontend-style.css',
        'deps' => isset( $args['deps'] ) && ! empty($args['deps']) ? $args['deps'] : []
      ];

      if ( isset( $args['frontend_style'] ) && $args['frontend_style'] ) {
        wp_register_style(
            "nectar-blocks-$block",
            $asset_args['path'],
            $asset_args['deps'],
            NECTAR_BLOCKS_VERSION
        );
      }

    }
  }

  /**
   * Standard way of enqueuing block styles when using a classic theme.
   */
  function classic_theme_frontend_block_styles() {
   global $post;
    if ( $post ) {
      $content = $post->post_content;

      // Regular post content.
     //var_dump($content); die();
      $this->enqueue_found_block_styles($content);

      // Patterns.
      $block_patterns = $this->frontend_pattern_set(parse_blocks($content));
      foreach( $block_patterns as $block_id => $v ) {
        $block_post = get_post($block_id);
        if ( $block_post && isset($block_post->post_content) ) {
          $this->enqueue_found_block_styles($block_post->post_content);
        }
      }

      // Global Sections.
      $this->global_sections_block_styles();

      // Nectar Template Parts.
      $this->template_block_styles();

   }
  }

  /**
   * Enqueues core stylesheets for each block when a block is found on the page
   * when using a classic theme.
   */
  function enqueue_found_block_styles($content) {

    if ($content) {

      $blocks_in_content = $this->get_blocks_in_content($content);
      $nectar_blocks = array_keys(Blocks::$block_list);
      $found_blocks = array_intersect($blocks_in_content, $nectar_blocks);

      foreach ( $found_blocks as $block ) {

        wp_enqueue_style( "nectar-blocks-$block" );
      }

    }
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
    wp_register_script( 'gsap', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/gsap.min.js', [], '3.12.7', true );
    wp_register_script( 'gsap-scroll-trigger', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/ScrollTrigger.min.js', ['gsap'], '3.12.7', true );
    wp_register_script( 'gsap-custom-ease', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/gsap/CustomEase.min.js', ['gsap'], '3.12.7', true );

    wp_register_script( 'split-type', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/split-type/index.min.js', [], '0.3.4', true );
    wp_register_script( 'count-up', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/countup/countup.js', [], '2.8.0', true );
    wp_register_script( 'swiper', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/swiper/swiper-bundle.min.js', [], '12.1.2', true );
    wp_register_script( 'fitty', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/fitty/fitty.min.js', [], '2.4.2', true);
    // wp_register_script( 'lightgallery', 'https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/lightgallery.min.js', [], '2.7.2', true);

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
        'window.nectarblocks_env =' . json_encode($this->get_nectar_blocks_env_variables()) . ';',
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
      'defaultTextBlock' => isset($nectar_plugin_options['defaultTextBlock']) ? $nectar_plugin_options['defaultTextBlock'] : 'nectar'
    ];

    return $output;
  }

  /**
   * Used to set global context variables for the admin.
   */
  function admin_head() {
    $NB_env_vars = $this->get_nectar_blocks_env_variables();
    echo '<script>window.nectarblocks_env =' . json_encode($NB_env_vars) . ';</script>';
  }

  /**
   * Renders in the head tag.
   */
  function render_head() {
    // Custom code in head
    $custom_code = get_option('nectar_code_options');
    if ( $custom_code ) {
      $custom_code_head = $custom_code['jsCodeHead'];
      if ( $custom_code_head ) {
        echo $custom_code_head;
      }
    }
  }

  /**
   * Renders after the opening body tag.
   */
  function wp_body_open() {

    // Custom code after body
    $custom_code = get_option('nectar_code_options');
    if ( $custom_code ) {
      $custom_code_body = $custom_code['jsCodeBody'];
      if ( $custom_code_body ) {
        echo $custom_code_body;
      }
    }
  }

  function frontend_pattern_set($blocks) {
    $pattern_set = [];
    $blocks = $this->flatten_blocks($blocks);

    foreach( $blocks as $block ) {
      if ( 'core/block' === $block['blockName'] ) {
        $pattern_ref = $block['attrs']['ref'];
        $pattern_set[$pattern_ref] = true;
        Log::debug('Adding to pattern set: ' . $pattern_ref);

        $pattern_post = get_post($pattern_ref);
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
  * Search all global sections for block frontend styles to enqueue.
  */
  function global_sections_block_styles() {
    $global_sections_query_args = [
      'post_type' => ['salient_g_sections', 'nectar_sections'],
      'post_status' => 'publish',
      'no_found_rows' => true,
      'posts_per_page' => -1,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ];

    $global_sections_query = new \WP_Query( $global_sections_query_args );
    foreach ( $global_sections_query->posts as $global_section ) {
      $global_section_content = get_post_field('post_content', $global_section->ID);
      if( $global_section_content && ! empty($global_section_content) ) {
        $this->enqueue_found_block_styles($global_section_content);
      }
    }
  }

  /**
   * Search all template parts for block frontend styles to enqueue.
   */
  function template_block_styles() {
    $template_parts = Nectar_Templates::get_template_parts();
    foreach ( $template_parts as $template_part ) {
      $location = sanitize_text_field($template_part['value']);
      if ( ! empty($location) ) {

        $is_active_location = Nectar_Templates::is_active_location($location);

        if ($is_active_location) {
          $active_template_part = $location;

          // Get all templates in a single optimized query
          $templates = get_posts([
            'post_type' => Nectar_Templates::POST_TYPE,
            'posts_per_page' => -1,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false
          ]);

          if (empty($templates) || ! is_array($templates)) {
            continue;
          }

          // Filter templates that have the matching template part
          $matching_templates = array_filter($templates, function($template) use ($active_template_part) {
            $meta = get_post_meta($template->ID, Nectar_Templates::META_KEY, true);
            if (empty($meta)) {
              return false;
            }

            return isset($meta['templatePart']) && $meta['templatePart'] === $active_template_part;
          });

          if (empty($matching_templates) || ! is_array($matching_templates)) {
            continue;
          }

          foreach ( $matching_templates as $template ) {
            $template_content = get_post_field('post_content', $template->ID);
            if( $template_content ) {
              $this->enqueue_found_block_styles($template_content);
            }
          }
        }
      }
    }
  }

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

  function frontend_render_styles() {
    global $post;

    // NB Plugin Options
    $nb_plugin_options = Nectar_Plugin_Options::get_options();

    // General Frontend Styles
    wp_enqueue_style( 'nectar-frontend-global', NECTAR_BLOCKS_PLUGIN_PATH . '/build/nectar-blocks-core.css', [], NECTAR_BLOCKS_VERSION);
    // Always enqueue these to skip the inline since they're likely used on every page.
    wp_enqueue_style( 'nectar-blocks-row', NECTAR_BLOCKS_PLUGIN_PATH . '/build/blocks/row/frontend-style.css', [], NECTAR_BLOCKS_VERSION);
    wp_enqueue_style( 'nectar-blocks-column', NECTAR_BLOCKS_PLUGIN_PATH . '/build/blocks/column/frontend-style.css', [], NECTAR_BLOCKS_VERSION);
    wp_enqueue_style( 'nectar-blocks-text', NECTAR_BLOCKS_PLUGIN_PATH . '/build/blocks/text/frontend-style.css', [], NECTAR_BLOCKS_VERSION);

    // Custom CSS
    $custom_code = get_option('nectar_code_options');
    if ( $custom_code ) {
      $custom_css = $custom_code['cssCode'];
      if ( $custom_css ) {
        wp_add_inline_style( 'nectar-frontend-global', $custom_css );
      }
    }

    // Third party styles.
    wp_register_style('nectar-blocks-swiper', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/swiper/bundle.css', [], '12.1.2');
    wp_register_style('nectar-blocks-lightgallery', NECTAR_BLOCKS_PLUGIN_PATH . '/assets/lightgallery/css/bundle.min.css', [], '9.4.1');
    // This has to be global since any link can trigger the lightgallery.
    wp_enqueue_style('nectar-blocks-lightgallery');

    // Google fonts.
    $google_fonts = Global_Typography::create_google_fonts_link('frontend');
    if ( $google_fonts ) {
      // add preconnect for google fonts
      add_action('wp_head', [$this, 'add_google_preconnect_links'], 4);
      // Enqueue fonts
      wp_enqueue_style( 'nectar-blocks-google-fonts', $google_fonts, [], null );
    }

    // Uploaded fonts.
    $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('frontend');
    if ( $uploaded_fonts ) {
      wp_add_inline_style( 'nectar-frontend-global', $uploaded_fonts);
      add_action('wp_head', [$this, 'add_custom_fonts_preload_links'], 4);
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

    $post_content = $post->post_content;
    if ($post_content) {
      $blocks = parse_blocks($post_content);
      $patterns_css = $this->frontend_pattern_css($blocks);

      $dynamic_css .= $patterns_css;
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
