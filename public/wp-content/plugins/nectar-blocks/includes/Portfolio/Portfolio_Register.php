<?php

namespace Nectar\Portfolio;

use Nectar\Global_Settings\Nectar_Modules;

/**
 * Portfolio Post Type
 * @version 2.0.0
 * @since 2.0.0
 */
class Portfolio_Register {
  private static $instance = null;

  public function __construct() {
    $nb_module_options = Nectar_Modules::get_options();

    if ($nb_module_options['portfolioPostType'] === true) {
      add_action('init', [$this, 'init']);
      add_action('admin_init', [$this, 'register_permalink_settings']);
      add_action('admin_init', [$this, 'add_permalink_settings']);
      add_action('admin_notices', [$this, 'flush_rewrite_notice']);
      add_action('update_option_page_on_front', [$this, 'flush_rewrite_rules']);
      add_action('after_switch_theme', [$this, 'flush_rewrite_rules']);
      add_action('after_switch_theme', [$this, 'nectar_add_portfolio_capabilities']);
      add_action('admin_init', [$this, 'permalink_settings_save']);
      add_action('init', [$this, 'init_rewrite_rules']);
      add_action('rss2_item', [$this, 'add_portfolio_item_data_to_rss']);
    }
  }

  public static function get_instance() {
    if (self::$instance == null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Register Portfolio Post Type
   */
  public function init() {
    if ( get_option( 'nectar_portfolio_set_caps' ) !== true ) {
      $this->nectar_add_portfolio_capabilities();
      update_option( 'nectar_portfolio_set_caps', true );
    }

    $permalinks = get_option('nectar_portfolio_permalinks', []);

    if (isset($permalinks['portfolio_base'])) {
        $slug = sanitize_title($permalinks['portfolio_base']);
    } else {
        $slug = 'portfolio';
    }

    if (isset($permalinks['category_base'])) {
        $category_base = sanitize_title($permalinks['category_base']);
    } else {
        $category_base = 'portfolio-category';
    }

    if (isset($permalinks['tag_base'])) {
        $tag_base = sanitize_title($permalinks['tag_base']);
    } else {
        $tag_base = 'portfolio-tag';
    }

    // Register Portfolio Post Type
    register_post_type('nectar_portfolio', [
      'labels' => apply_filters('nectar_portfolio_labels', [
        'name' => __('Portfolio', 'nectar-blocks'),
        'singular_name' => __('Project', 'nectar-blocks'),
        'add_new' => __('Add New', 'nectar-blocks'),
        'add_new_item' => __('Add New Project', 'nectar-blocks'),
        'edit_item' => __('Edit Project', 'nectar-blocks'),
        'new_item' => __('New Project', 'nectar-blocks'),
        'view_item' => __('View Project', 'nectar-blocks'),
        'search_items' => __('Search Portfolio', 'nectar-blocks'),
        'not_found' => __('No Project found', 'nectar-blocks'),
        'not_found_in_trash' => __('No Project found in Trash', 'nectar-blocks'),
        'menu_name' => __('Portfolio', 'nectar-blocks'),
      ]),
      'public' => true,
      'has_archive' => apply_filters('nectar_portfolio_has_archive', true),
      'map_meta_cap' => true,
      'capability_type' => 'portfolio',
      'taxonomies' => [
          'portfolio_category',
          'portfolio_tag'
      ],
      'rewrite' => ['slug' => $slug, 'with_front' => false],
      'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'custom-fields'],
      'menu_icon' => 'dashicons-portfolio',
      'show_in_rest' => true,
      'menu_position' => 20,
    ]);

    // Register Portfolio Category Taxonomy
    register_taxonomy('portfolio_category', ['nectar_portfolio'], [
      'label' => __('Categories', 'nectar-blocks'),
      'rewrite' => ['slug' => $category_base, 'with_front' => false, 'hierarchical' => true],
      'hierarchical' => true,
      'show_in_rest' => true,
      'query_var' => true,
      'publicly_queryable' => true,
      'show_in_nav_menus' => true,
      'show_admin_column' => true,
      'map_meta_cap' => true,
      'capability_type' => 'portfolio'
    ]);

    // Register Portfolio Tag Taxonomy
    register_taxonomy('portfolio_tag', ['nectar_portfolio'], [
      'label' => __('Tags', 'nectar-blocks'),
      'rewrite' => ['slug' => $tag_base, 'with_front' => false],
      'hierarchical' => false,
      'show_in_rest' => true,
      'query_var' => true,
      'publicly_queryable' => true,
      'show_in_nav_menus' => true,
      'show_admin_column' => true,
      'map_meta_cap' => true,
      'capability_type' => 'portfolio'
    ]);
  }

  public function add_portfolio_item_data_to_rss($post) {
    global $post;

    if ($post->post_type !== 'nectar_portfolio') {
        return;
    }

    $terms = get_the_terms($post->ID, 'portfolio_category');

    if (! empty($terms) && ! is_wp_error($terms)) {
        foreach ($terms as $term) {
            echo '<category><![CDATA[' . esc_html($term->name) . ']]></category>' . "\n";
        }
    }
  }

  public function nectar_add_portfolio_capabilities() {
    $roles = ['administrator', 'editor'];
    $caps = [
      'edit_portfolio',
      'read_portfolio',
      'delete_portfolio',
      'edit_portfolios',
      'edit_others_portfolios',
      'publish_portfolios',
      'read_private_portfolios',
      'delete_portfolios',
      'delete_private_portfolios',
      'delete_published_portfolios',
      'delete_others_portfolios',
      'edit_private_portfolios',
      'edit_published_portfolios',
    ];

    foreach ($roles as $role_name) {
      $role = get_role($role_name);
      if (! $role) continue;

      foreach ($caps as $cap) {
        $role->add_cap($cap);
      }
    }

  }

  /**
   * Register Permalink Settings
   */
  public function register_permalink_settings() {
    register_setting('permalink', 'nectar_portfolio_permalinks', [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize_permalink_settings'],
    ]);
  }

  /**
   * Add Portfolio Permalink Settings Section
   */
  public function add_permalink_settings() {
    add_settings_section(
        'nectar_portfolio_permalink',
        __('Portfolio Permalinks', 'nectar-blocks'),
        function () {
          echo '<p>' . esc_html__('Customize the portfolio permalink structure.', 'nectar-blocks') . '</p>';
            // Add nonce field for security
          wp_nonce_field('nectar-permalinks', 'nectar-permalinks-nonce');
      },
        'permalink'
    );

    $this->add_permalink_field('portfolio_base', __('Portfolio Slug', 'nectar-blocks'), 'portfolio');
    $this->add_permalink_field('category_base', __('Portfolio Category Base', 'nectar-blocks'), 'portfolio-category');
    $this->add_permalink_field('tag_base', __('Portfolio Tag Base', 'nectar-blocks'), 'portfolio-tag');
  }

  /**
   * Add a permalink input field
   */
  private function add_permalink_field($option, $label, $default) {
    add_settings_field(
        'nectar_' . $option,
        $label,
        function () use ($option, $default) {
        $permalinks = get_option('nectar_portfolio_permalinks', []);
        if (isset($permalinks[$option]) && ! empty($permalinks[$option])) {
            $value = esc_attr($permalinks[$option]);
        } else {
            $value = $default;
        }

        echo '<input name="nectar_portfolio_permalinks[' . esc_attr($option) . ']" type="text" class="regular-text code" value="' . esc_attr($value) . '" />';

      },
        'permalink',
        'nectar_portfolio_permalink'
    );
  }

  /**
   * Sanitize Permalink Settings
   */
  public function sanitize_permalink_settings($input) {
    $sanitized = [];
    $fields = ['portfolio_base', 'category_base', 'tag_base'];

    foreach ($fields as $field) {
      if (isset($input[$field])) {
        $sanitized[$field] = sanitize_title($input[$field]);
      }
    }

    flush_rewrite_rules(); // Ensure changes take effect
    return $sanitized;
  }

  /**
   * Display Rewrite Rules Notice
   */
  public function flush_rewrite_notice() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
      echo '<div class="notice notice-success"><p>' . esc_html__('Portfolio permalink structure updated. Please refresh permalinks by visiting Settings > Permalinks and clicking "Save Changes".', 'nectar-blocks') . '</p></div>';
    }
  }

  /**
   * Initialize Rewrite Rules
   */
  public function init_rewrite_rules() {
    $permalinks = get_option('nectar_portfolio_permalinks', []);
    $portfolio_base = isset($permalinks['portfolio_base']) ? $permalinks['portfolio_base'] : 'portfolio';
    $category_base = isset($permalinks['category_base']) ? $permalinks['category_base'] : 'portfolio-category';
    $tag_base = isset($permalinks['tag_base']) ? $permalinks['tag_base'] : 'portfolio-tag';

    // Portfolio archive with pagination
    add_rewrite_rule("^{$portfolio_base}/page/([0-9]+)/?$", 'index.php?post_type=nectar_portfolio&paged=$matches[1]', 'top');

    // Portfolio single items
    add_rewrite_rule("^{$portfolio_base}/([^/]+)/?$", 'index.php?post_type=nectar_portfolio&name=$matches[1]', 'top');

    // Portfolio category archive with pagination
    add_rewrite_rule("^{$category_base}/([^/]+)/page/([0-9]+)/?$", 'index.php?taxonomy=portfolio_category&term=$matches[1]&paged=$matches[2]', 'top');

    // Portfolio category archive
    add_rewrite_rule("^{$category_base}/([^/]+)/?$", 'index.php?taxonomy=portfolio_category&term=$matches[1]', 'top');

    // Portfolio tag archive with pagination
    add_rewrite_rule("^{$tag_base}/([^/]+)/page/([0-9]+)/?$", 'index.php?taxonomy=portfolio_tag&term=$matches[1]&paged=$matches[2]', 'top');

    // Portfolio tag archive
    add_rewrite_rule("^{$tag_base}/([^/]+)/?$", 'index.php?taxonomy=portfolio_tag&term=$matches[1]', 'top');

  }

    /**
   * Save Permalink Settings
   */
  public function permalink_settings_save() {
    if (! is_admin()) {
      return;
    }

    if (
      isset($_POST['permalink_structure'], $_POST['nectar-permalinks-nonce']) &&
      wp_verify_nonce(sanitize_key($_POST['nectar-permalinks-nonce']), 'nectar-permalinks')
    ) {
      $permalinks = (array) get_option('nectar_portfolio_permalinks', []);
      $updated_permalinks = [];

      // Sanitize and store category & tag base
      $updated_permalinks['category_base'] = isset($_POST['nectar_portfolio_permalinks']['category_base'])
          ? sanitize_title(wp_unslash($_POST['nectar_portfolio_permalinks']['category_base']))
          : 'portfolio-category';

      $updated_permalinks['tag_base'] = isset($_POST['nectar_portfolio_permalinks']['tag_base'])
          ? sanitize_title(wp_unslash($_POST['nectar_portfolio_permalinks']['tag_base']))
          : 'portfolio-tag';

      // Sanitize and store portfolio base
      $updated_permalinks['portfolio_base'] = isset($_POST['nectar_portfolio_permalinks']['portfolio_base'])
          ? sanitize_title(wp_unslash($_POST['nectar_portfolio_permalinks']['portfolio_base']))
          : 'portfolio';

      // Only update and flush if there are changes
      if ($updated_permalinks !== $permalinks) {
        update_option('nectar_portfolio_permalinks', $updated_permalinks);
        self::flush_rewrite_rules(); // Flush only when settings change
      }
    }
  }

  /**
   * Flush Rewrite Rules
   */
  public static function flush_rewrite_rules() {
    if (is_admin()) {
      flush_rewrite_rules(false);
    }
  }

  /**
   * Flush Rewrite Rules if portfolio post type is enabled
   */
  public static function flush_rewrite_rules_on_upgrade() {
    $nb_module_options = Nectar_Modules::get_options();
    if ($nb_module_options['portfolioPostType'] === true) {
      self::flush_rewrite_rules();
    }
  }
}
