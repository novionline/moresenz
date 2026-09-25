<?php

namespace Nectar\Nectar_Templates;

use Nectar\API\Access_Utils;
use Nectar\Editor\Post_Meta;
use Nectar\Nectar_Templates\Render;
use Nectar\Dynamic_Data\Frontend_Render;
use Nectar\Utilities\FlatMap;
use Nectar\Render\Render as Blocks_Render;

/**
 * Nectar Templates Register.
 * @since 2.0.0
 * @version 2.0.0
 */
class Nectar_Templates_Register {
  /**
   * Header variant reserved-keys policy.
   *
   * SSOT: this PHP array IS the source of truth; the equivalent TS export
   * lives in `plugin/src/shared/constants/variantPolicy.ts`. Both copies must
   * stay in lockstep — the TS test in `headerVariantReserved.test.ts` pins
   * the expected values so drift fails CI.
   *
   * @return array{reservedKeys: array<int, string>, reservedLabelsLower: array<int, string>, lifecycleSlotKeys: array<int, string>, keyPattern: string}
   */
  public static function header_variant_reserved(): array {
    return [
      'reservedKeys' => [ 'default', 'transparent', 'scrolled', 'overlayMenuOpened' ],
      'reservedLabelsLower' => [ 'default', 'transparent', 'scrolled', 'overlay menu' ],
      'lifecycleSlotKeys' => [ 'transparent', 'scrolled', 'overlayMenuOpened' ],
      'keyPattern' => '^[a-z][a-z0-9-]{0,63}$',
    ];
  }

  function __construct() {
    add_action( 'init', [$this, 'init'] );
  }

  public function init() {

    // Skip if not using Nectarblocks theme.
    if ( ! defined('NB_THEME_VERSION') ) {
      return;
    }

    Render::get_instance();

    // Shortcode.
    add_shortcode('nectar_template', [$this, 'nectar_template_shortcode_callback'] );

    // Register post type.
    $this->register_post_type();

    // Register post meta exposed via REST.
    $this->register_post_meta();

    // REST endpoint feeding the per-row variant picker. Surfaces the union
    // of variants across all published header_navigation templates — we
    // can't pin to a single header at edit time (display conditions decide
    // at view time, and sites can have multiple).
    add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

    // Main admin page.
    add_filter(
        'manage_edit-' . Nectar_Templates::POST_TYPE . '_columns',
        [$this, 'define_nectar_template_columns']
    );
    add_action(
        'manage_' . Nectar_Templates::POST_TYPE . '_posts_custom_column',
        [$this, 'nectar_section_admin_columns'],
        10,
        2
    );

    add_action('admin_head', function() {
      $screen = get_current_screen();
      if ($screen && $screen->post_type === 'nectar_templates' && $screen->id === 'edit-nectar_templates') {
        echo '<style data-type="nectar-template-admin-columns-css">' . $this->admin_columns_css() . '</style>';
      }
    });

    // Flag that dynamic CSS needs regenerating when a theme builder template is saved.
    add_action('save_post_' . Nectar_Templates::POST_TYPE, [$this, 'flag_dynamic_css_update'], 10, 3);

    // Make WooCommerce Site Editor blocks available in the Theme Builder editor.
    if ( class_exists( 'WooCommerce' ) ) {
      add_filter( 'woocommerce_get_block_types', [$this, 'allow_wc_blocks_in_theme_builder'] );
      add_action( 'enqueue_block_editor_assets', [$this, 'enqueue_wc_template_editor_compat'] );
    }

    // Template creation with pre-populated content.
    add_action('admin_action_nectar_create_template', [$this, 'handle_create_template']);
    add_action('admin_footer-edit.php', [$this, 'template_chooser_script']);
  }

  /**
   * Flag that dynamic CSS needs updating when a theme builder template is saved.
   *
   * @since 3.0.0
   * @param int      $post_id Post ID.
   * @param \WP_Post $post    Post object.
   * @param bool     $update  Whether this is an existing post being updated.
   */
  public function flag_dynamic_css_update($post_id, $post, $update) {
    // Skip autosaves and revisions.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }
    if (wp_is_post_revision($post_id)) {
      return;
    }

    // Set transient to flag that theme dynamic CSS needs regenerating.
    set_transient('nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
  }

  /**
   * Re-add WooCommerce blocks that are normally restricted to the Site Editor.
   *
   * WooCommerce removes certain blocks (Breadcrumbs, CatalogSorting, etc.)
   * when $pagenow is post.php or post-new.php via array_diff in
   * BlockTypesController::get_block_types(). We re-add them when editing
   * a nectar_templates post so Theme Builder templates have full access.
   *
   * @since 3.0
   * @param array $block_types Array of WooCommerce block class name strings.
   * @return array
   */
  public function allow_wc_blocks_in_theme_builder( array $block_types ): array {
    global $pagenow;

    if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
      return $block_types;
    }

    // Detect if we're editing a nectar_templates post.
    $is_theme_builder = false;

    if ( 'post-new.php' === $pagenow ) {
      $is_theme_builder = isset( $_GET['post_type'] ) && $_GET['post_type'] === Nectar_Templates::POST_TYPE;
    } elseif ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) ) {
      $is_theme_builder = get_post_type( absint( $_GET['post'] ) ) === Nectar_Templates::POST_TYPE;
    }

    if ( ! $is_theme_builder ) {
      return $block_types;
    }

    // These class name strings match the keys WooCommerce removes via array_diff.
    // Only re-add blocks whose PHP class actually exists in the installed
    // WooCommerce version to avoid fatal "class not found" errors.
    $wc_site_editor_blocks = [
      'Breadcrumbs',
      'CatalogSorting',
      'ClassicTemplate',
      'ProductResultsCount',
      'ProductReviews',
      'ProductDetails',
      'ProductGallery',
      'RelatedProducts',
      'OrderConfirmation\Status',
      'OrderConfirmation\Summary',
      'OrderConfirmation\Totals',
      'OrderConfirmation\TotalsWrapper',
      'OrderConfirmation\Downloads',
      'OrderConfirmation\DownloadsWrapper',
      'OrderConfirmation\BillingAddress',
      'OrderConfirmation\ShippingAddress',
      'OrderConfirmation\BillingWrapper',
      'OrderConfirmation\ShippingWrapper',
      'OrderConfirmation\AdditionalInformation',
      'OrderConfirmation\AdditionalFieldsWrapper',
      'OrderConfirmation\AdditionalFields',
    ];

    $wc_namespace = 'Automattic\\WooCommerce\\Blocks\\BlockTypes\\';
    $to_add = [];
    foreach ( $wc_site_editor_blocks as $block_name ) {
      // Only re-add blocks that were actually removed and whose class exists.
      if ( ! in_array( $block_name, $block_types, true ) && class_exists( $wc_namespace . $block_name ) ) {
        $to_add[] = $block_name;
      }
    }

    return array_merge( $block_types, $to_add );
  }

  /**
   * Enqueue JS compatibility layer for WooCommerce blocks in the Theme Builder editor.
   *
   * Injects templateSlug block context and relaxes ancestor constraints so
   * WooCommerce product blocks behave the same as they do in the Site Editor.
   *
   * @since 3.0
   */
  public function enqueue_wc_template_editor_compat(): void {
    global $pagenow;

    if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
      return;
    }

    // Determine the post ID.
    $post_id = null;
    if ( 'post.php' === $pagenow && ! empty( $_GET['post'] ) ) {
      $post_id = absint( $_GET['post'] );
    } elseif ( 'post-new.php' === $pagenow && ! empty( $_GET['post_type'] ) ) {
      // New posts don't have meta yet, skip.
      return;
    }

    if ( ! $post_id || get_post_type( $post_id ) !== Nectar_Templates::POST_TYPE ) {
      return;
    }

    $meta = get_post_meta( $post_id, Nectar_Templates::META_KEY, true );
    $template_key = $meta['templatePart'] ?? '';

    if ( strpos( $template_key, 'nectar_template_wc__' ) !== 0 ) {
      return;
    }

    // Map internal template key to WooCommerce template slug.
    $slug_map = [
      'nectar_template_wc__single_product' => 'single-product',
      'nectar_template_wc__archive_product' => 'archive-product',
      'nectar_template_wc__cart' => 'page-cart',
      'nectar_template_wc__checkout' => 'page-checkout',
      'nectar_template_wc__my_account' => 'page-my-account',
      'nectar_template_wc__order_confirmation' => 'order-received',
    ];

    $wc_template_slug = $slug_map[$template_key] ?? null;
    if ( ! $wc_template_slug ) {
      return;
    }

    // 1) Earliest: mock core/edit-site store so WooCommerce's JS detects template context.
    wp_add_inline_script( 'wp-blocks', $this->get_wc_edit_site_store_mock_js( $wc_template_slug ), 'after' );

    // 2) Early script: relax ancestor constraints before blocks register.
    wp_add_inline_script( 'wp-blocks', $this->get_wc_ancestor_compat_js( $wc_template_slug ), 'after' );

    // 3) Early script: inject templateSlug/postType into each block's context prop.
    wp_add_inline_script( 'wp-blocks', $this->get_wc_context_compat_js( $wc_template_slug ), 'after' );
  }

  /**
   * Returns JS that registers a minimal core/edit-site store mock.
   *
   * WooCommerce's block registration system (product-query.js) relies on
   * select('core/edit-site') to detect template editing context. This store
   * only exists in the Site Editor (site-editor.php), not the post editor.
   * We register a minimal mock with getEditedPostType/getEditedPostId so
   * WooCommerce's subscribe() callbacks fire and register block variations
   * (e.g., woocommerce/related-products for core/query).
   *
   * @since 3.0
   */
  private function get_wc_edit_site_store_mock_js( string $wc_template_slug ): string {
    // WooCommerce extracts the slug by splitting the template ID on '//'
    // e.g. 'woocommerce/woocommerce//single-product' → 'single-product'
    $template_id_json = wp_json_encode( 'nectar//' . $wc_template_slug );

    return <<<JS
(function() {
  // Only register if core/edit-site store doesn't already exist.
  if (wp.data.select('core/edit-site')) return;

  var templateId = {$template_id_json};

  wp.data.registerStore('core/edit-site', {
    reducer: function(state, action) {
      if (state === undefined) return { postId: templateId, postType: 'wp_template' };
      if (action.type === 'NECTAR_INIT') return Object.assign({}, state);
      return state;
    },
    selectors: {
      getEditedPostType: function(state) { return state.postType; },
      getEditedPostId: function(state) { return state.postId; }
    },
    actions: {
      __nectarInit: function() { return { type: 'NECTAR_INIT' }; }
    }
  });

  // Dispatch after a tick so WooCommerce's subscribe() callbacks
  // (registered when their scripts load synchronously after this)
  // will fire and detect the template context.
  setTimeout(function() {
    wp.data.dispatch('core/edit-site').__nectarInit();
  }, 0);
})();
JS;
  }

  /**
   * Returns JS that relaxes ancestor constraints for WooCommerce product blocks
   * when editing a WooCommerce theme builder template.
   *
   * @since 3.0
   */
  private function get_wc_ancestor_compat_js( string $wc_template_slug ): string {
    $slug_json = wp_json_encode( $wc_template_slug );

    return <<<JS
(function() {
  var nectarWcTemplateSlug = {$slug_json};

  // Product element blocks that have ancestor constraints in WooCommerce.
  // In the Site Editor, WooCommerce dynamically relaxes these via BlockRegistrationManager.
  // We replicate that behavior for the Theme Builder.
  var singleProductBlocks = [
    'woocommerce/product-rating-stars',
    'woocommerce/product-rating-counter',
    'woocommerce/product-average-rating',
    'woocommerce/product-button'
  ];

  var isSingleProduct = nectarWcTemplateSlug === 'single-product';

  wp.hooks.addFilter(
    'blocks.registerBlockType',
    'nectar/wc-relax-ancestors',
    function(settings, name) {
      if (!isSingleProduct) return settings;
      if (singleProductBlocks.indexOf(name) === -1) return settings;
      // Remove ancestor constraint so the block can be placed in any container.
      var newSettings = Object.assign({}, settings);
      delete newSettings.ancestor;
      return newSettings;
    }
  );
})();
JS;
  }

  /**
   * Returns JS that injects block context for WooCommerce blocks
   * in the Theme Builder editor.
   *
   * Uses the editor.BlockEdit filter to intercept each block's context prop:
   * - Injects templateSlug so WooCommerce blocks (Product Collection, etc.)
   *   detect the template editing context via useGetLocation().
   * - Clears postType/postId for root-level blocks (not inside a query loop)
   *   so core post blocks (post-title, post-excerpt, post-date) show their
   *   template placeholder text (e.g. "This block will display the excerpt")
   *   instead of trying to load entity data from the nectar_templates post.
   *
   * @since 3.0
   */
  private function get_wc_context_compat_js( string $wc_template_slug ): string {
    $slug_json = wp_json_encode( $wc_template_slug );

    return <<<JS
(function() {
  var nectarWcTemplateSlug = {$slug_json};

  // Note: wp.compose is NOT available on wp-blocks (not a dependency),
  // so we manually set displayName for stable React component identity.
  wp.hooks.addFilter(
    'editor.BlockEdit',
    'nectar/wc-template-context',
    function(BlockEdit) {
      var Wrapped = function(props) {
        var context = props.context;
        if (context) {
          var newContext = Object.assign({}, context);
          newContext.templateSlug = nectarWcTemplateSlug;

          // Core post blocks (post-title, post-excerpt, etc.) show template
          // placeholder text when postType or postId is falsy. Clear these
          // for root-level blocks (not inside a query loop) so they display
          // descriptive placeholders instead of empty/broken entity lookups.
          // Blocks inside product-collection get queryId from providesContext
          // and retain their inherited postType/postId for rendering product data.
          if (!Number.isFinite(newContext.queryId)) {
            newContext.postType = undefined;
            newContext.postId = 0;
          }

          return wp.element.createElement(
            BlockEdit,
            Object.assign({}, props, { context: newContext })
          );
        }
        return wp.element.createElement(BlockEdit, props);
      };
      Wrapped.displayName = 'nectarWcTemplateContext(' + (BlockEdit.displayName || BlockEdit.name || 'Component') + ')';
      return Wrapped;
    }
  );
})();
JS;
  }

  /**
   * Handles the admin action for creating a new template with pre-populated content.
   *
   * @since 3.0
   */
  public function handle_create_template() {
    if ( ! current_user_can('edit_posts') ) {
      wp_die( esc_html__( 'Unauthorized', 'nectar-blocks' ) );
    }

    check_admin_referer('nectar_create_template');

    $template_type = sanitize_text_field( $_GET['template_type'] ?? '' );
    if ( empty( $template_type ) ) {
      wp_safe_redirect( admin_url( 'edit.php?post_type=nectar_templates' ) );
      exit;
    }

    // Get the label for the post title.
    $title = __( 'New Template', 'nectar-blocks' );
    $all_parts = Nectar_Templates::get_template_parts();
    foreach ( $all_parts as $part ) {
      if ( $part['value'] === $template_type ) {
        $title = $part['label'];
        break;
      }
    }

    // Get default content if available for this template type.
    $content = Template_Default_Content::get_default_content( $template_type );

    // wp_insert_post() expects slashed data — it internally calls wp_unslash()
    // which strips backslashes from JSON unicode escapes (\u003c, \u0022) in
    // block comment attributes. wp_slash() counteracts this, matching how the
    // WordPress REST API handles post content.
    $post_id = wp_insert_post( wp_slash( [
      'post_type' => Nectar_Templates::POST_TYPE,
      'post_title' => $title,
      'post_content' => $content,
      'post_status' => 'draft',
    ] ) );

    if ( is_wp_error( $post_id ) ) {
      wp_safe_redirect( admin_url( 'edit.php?post_type=nectar_templates' ) );
      exit;
    }

    // Set template part meta.
    update_post_meta( $post_id, Nectar_Templates::META_KEY, [
      'templatePart' => $template_type,
      'operator' => 'and',
      'conditions' => [],
    ] );

    wp_safe_redirect( admin_url( "post.php?post={$post_id}&action=edit" ) );
    exit;
  }

  /**
   * Outputs a template chooser script on the Theme Builder list page.
   *
   * Intercepts the "Add New Template" button to show a dropdown of available
   * template types, creating the post with pre-populated content and meta.
   *
   * @since 3.0
   */
  public function template_chooser_script() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-nectar_templates' ) {
      return;
    }

    $template_parts = Nectar_Templates::get_template_parts();
    $nonce = wp_create_nonce( 'nectar_create_template' );
    $action_url = admin_url( 'admin.php?action=nectar_create_template' );
    $new_post_url = admin_url( 'post-new.php?post_type=nectar_templates' );

    // Build grouped data for JS.
    $grouped = [];
    foreach ( $template_parts as $part ) {
      if ( empty( $part['value'] ) ) {
        continue;
      }
      $group = __( 'General', 'nectar-blocks' );
      if ( strpos( $part['value'], 'nectar_template_wc__' ) === 0 ) {
        $group = __( 'WooCommerce', 'nectar-blocks' );
      } elseif ( strpos( $part['value'], 'nectar_template_single__' ) === 0 ) {
        $group = __( 'Single', 'nectar-blocks' );
      } elseif ( strpos( $part['value'], 'nectar_template_archive__' ) === 0 ) {
        $group = __( 'Archive', 'nectar-blocks' );
      } elseif ( strpos( $part['value'], '__header_navigation' ) !== false || strpos( $part['value'], '__ocm' ) !== false ) {
        $group = __( 'Navigation', 'nectar-blocks' );
      } elseif ( strpos( $part['value'], 'nectar_hook_global_section_' ) === 0 && strpos( $part['value'], 'footer' ) !== false ) {
        $group = __( 'Footer', 'nectar-blocks' );
      }
      if ( ! isset( $grouped[$group] ) ) {
        $grouped[$group] = [];
      }
      $grouped[$group][] = $part;
    }
    ?>
    <style>
      .nectar-template-chooser-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 100000;
        align-items: center;
        justify-content: center;
      }
      .nectar-template-chooser-overlay.active { display: flex; }
      .nectar-template-chooser {
        background: #fff;
        border-radius: 8px;
        padding: 24px;
        min-width: 420px;
        max-width: 500px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
      }
      .nectar-template-chooser h2 {
        margin: 0 0 16px;
        font-size: 16px;
      }
      .nectar-template-chooser .buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
      }
      .nectar-template-chooser .buttons .button {
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.15s, box-shadow 0.15s;
        line-height: 1.6;
      }
      .nectar-template-chooser .buttons .button--secondary {
        background: #fff;
        color: #1d2327;
        box-shadow: inset 0 0 0 1px #c5c5c5;
      }
      .nectar-template-chooser .buttons .button--secondary:hover {
        box-shadow: inset 0 0 0 1px #999;
      }
      .nectar-template-chooser .buttons .button--primary {
        background: #3452ff;
        color: #fff;
      }
      .nectar-template-chooser .buttons .button--primary:hover {
        background: #4562ff;
        box-shadow: 0 2px 8px rgba(52,82,255,0.3);
      }

      /* Filtered List */
      .ntc-list {
        margin-bottom: 16px;
      }
      .ntc-list__input {
        width: 100%;
        padding: 8px 12px;
        font-size: 14px;
        border: 1px solid #8c8f94;
        border-radius: 4px;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.15s;
        background: #fff;
        margin-bottom: 8px;
      }
      .ntc-list__input:focus {
        border-color: #3858e9;
        box-shadow: 0 0 0 1px #3858e9;
      }
      .ntc-list__input::placeholder {
        color: #757575;
      }
      .ntc-list__items {
        max-height: 320px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
      }
      .ntc-list__group-label {
        padding: 8px 12px 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #1d2327;
        letter-spacing: 0.5px;
      }
      .ntc-list__group-label:not(:first-child) {
        border-top: 1px solid #f0f0f1;
        margin-top: 2px;
      }
      .ntc-list__option {
        padding: 8px 12px 8px 20px;
        font-size: 13px;
        cursor: pointer;
        transition: background-color 0.1s;
      }
      .ntc-list__option:hover { background-color: #f0f0f1; }
      .ntc-list__option.is-selected {
        background-color: #e8edff;
        font-weight: 500;
      }
      .ntc-list__option--blank {
        padding-left: 12px;
        font-style: italic;
        color: #50575e;
        border-bottom: 1px solid #f0f0f1;
      }
      .ntc-list__empty {
        padding: 16px 12px;
        font-size: 13px;
        color: #757575;
        text-align: center;
      }
      .ntc-list__option .ntc-highlight {
        background-color: #fff3cd;
        border-radius: 2px;
      }
    </style>
    <div class="nectar-template-chooser-overlay" id="nectar-template-chooser-overlay">
      <div class="nectar-template-chooser">
        <h2><?php esc_html_e( 'Choose a Template Type', 'nectar-blocks' ); ?></h2>
        <div class="ntc-list" id="ntc-list">
          <input
            type="text"
            class="ntc-list__input"
            id="ntc-list-input"
            placeholder="<?php esc_attr_e( 'Filter templates...', 'nectar-blocks' ); ?>"
            autocomplete="off"
          />
          <div class="ntc-list__items" id="ntc-list-items"></div>
        </div>
        <div class="buttons">
          <button type="button" class="button button--secondary" id="nectar-template-chooser-cancel">
            <?php esc_html_e( 'Cancel', 'nectar-blocks' ); ?>
          </button>
          <button type="button" class="button button--primary" id="nectar-template-chooser-create">
            <?php esc_html_e( 'Create Template', 'nectar-blocks' ); ?>
          </button>
        </div>
      </div>
    </div>
    <script>
    (function() {
      var overlay = document.getElementById('nectar-template-chooser-overlay');
      var input = document.getElementById('ntc-list-input');
      var listItems = document.getElementById('ntc-list-items');

      var blankLabel = <?php echo wp_json_encode( __( 'Blank Template', 'nectar-blocks' ) ); ?>;
      var noResults = <?php echo wp_json_encode( __( 'No templates found', 'nectar-blocks' ) ); ?>;
      var groups = <?php echo wp_json_encode( $grouped ); ?>;
      var actionUrl = <?php echo wp_json_encode( $action_url ); ?>;
      var nonce = <?php echo wp_json_encode( $nonce ); ?>;
      var newPostUrl = <?php echo wp_json_encode( $new_post_url ); ?>;

      var selectedValue = '';

      function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      }

      function highlightMatch(text, query) {
        if (!query) return document.createTextNode(text);
        var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        var frag = document.createDocumentFragment();
        var parts = text.split(regex);
        parts.forEach(function(part) {
          if (regex.test(part)) {
            var mark = document.createElement('span');
            mark.className = 'ntc-highlight';
            mark.textContent = part;
            frag.appendChild(mark);
            regex.lastIndex = 0;
          } else {
            frag.appendChild(document.createTextNode(part));
          }
        });
        return frag;
      }

      function renderList(query) {
        listItems.innerHTML = '';
        var lowerQuery = (query || '').toLowerCase();
        var hasResults = false;

        // Blank option
        if (!lowerQuery || blankLabel.toLowerCase().indexOf(lowerQuery) !== -1) {
          var blankEl = document.createElement('div');
          blankEl.className = 'ntc-list__option ntc-list__option--blank';
          if (selectedValue === '') blankEl.classList.add('is-selected');
          blankEl.dataset.value = '';
          blankEl.appendChild(highlightMatch(blankLabel, query));
          listItems.appendChild(blankEl);
          hasResults = true;
        }

        // Grouped options
        for (var groupLabel in groups) {
          var items = groups[groupLabel];
          var matchingItems = [];

          items.forEach(function(item) {
            if (!lowerQuery || item.label.toLowerCase().indexOf(lowerQuery) !== -1) {
              matchingItems.push(item);
            }
          });

          if (matchingItems.length === 0) continue;
          hasResults = true;

          var groupEl = document.createElement('div');
          groupEl.className = 'ntc-list__group-label';
          groupEl.textContent = groupLabel;
          listItems.appendChild(groupEl);

          matchingItems.forEach(function(item) {
            var optEl = document.createElement('div');
            optEl.className = 'ntc-list__option';
            if (item.value === selectedValue) optEl.classList.add('is-selected');
            optEl.dataset.value = item.value;
            optEl.dataset.label = item.label;
            optEl.appendChild(highlightMatch(item.label, query));
            listItems.appendChild(optEl);
          });
        }

        if (!hasResults) {
          var emptyEl = document.createElement('div');
          emptyEl.className = 'ntc-list__empty';
          emptyEl.textContent = noResults;
          listItems.appendChild(emptyEl);
        }
      }

      function selectOption(value) {
        selectedValue = value;
        // Update visual selection
        listItems.querySelectorAll('.ntc-list__option').forEach(function(el) {
          el.classList.toggle('is-selected', el.dataset.value === value);
        });
      }

      function resetState() {
        selectedValue = '';
        input.value = '';
        renderList('');
      }

      // Filter on input
      input.addEventListener('input', function() {
        renderList(input.value);
      });

      // Click to select
      listItems.addEventListener('click', function(e) {
        var optEl = e.target.closest('.ntc-list__option');
        if (optEl) {
          selectOption(optEl.dataset.value);
        }
      });

      // Intercept "Add New Template" button clicks.
      document.addEventListener('click', function(e) {
        var link = e.target.closest('a.page-title-action, a[href*="post-new.php?post_type=nectar_templates"]');
        if (link) {
          e.preventDefault();
          resetState();
          overlay.classList.add('active');
          setTimeout(function() { input.focus(); }, 50);
        }
      });

      document.getElementById('nectar-template-chooser-cancel').addEventListener('click', function() {
        overlay.classList.remove('active');
      });

      overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
          overlay.classList.remove('active');
        }
      });

      document.getElementById('nectar-template-chooser-create').addEventListener('click', function() {
        if (!selectedValue) {
          window.location.href = newPostUrl;
          return;
        }
        var url = actionUrl;
        url += '&template_type=' + encodeURIComponent(selectedValue);
        url += '&_wpnonce=' + nonce;
        window.location.href = url;
      });
    })();
    </script>
    <?php
  }

  /**
   * Registers the global section post type/tax.
   * @since 2.0.0
   * @version 2.0.0
   */
  public function register_post_type() {
    $post_type_labels = [
      'name' => esc_html__( 'Theme Builder', 'nectar-blocks' ),
      'singular_name' => esc_html__( 'Template', 'nectar-blocks' ),
      'search_items' => esc_html__( 'Search Templates', 'nectar-blocks' ),
      'all_items' => esc_html__( 'Templates', 'nectar-blocks' ),
      'parent_item' => esc_html__( 'Parent Template', 'nectar-blocks' ),
      'edit_item' => esc_html__( 'Edit Template', 'nectar-blocks' ),
      'update_item' => esc_html__( 'Update Template', 'nectar-blocks' ),
      'add_new_item' => esc_html__( 'Add New Template', 'nectar-blocks' ),
      'add_new' => esc_html__( 'Add New Template', 'nectar-blocks' ),
    ];

    $is_public = is_user_logged_in();
    $args = [
      'labels' => $post_type_labels,
      'singular_label' => esc_html__( 'Section', 'nectar-blocks' ),
      'public' => $is_public,
      'publicly_queryable' => $is_public,
      'rewrite' => false,
      'show_in_rest' => true,
      'exclude_from_search' => true,
      'show_ui' => true,
      'hierarchical' => true,
      'menu_position' => 20,
      'menu_icon' => 'dashicons-edit-page',
      'supports' => [ 'title', 'editor', 'revisions' , 'custom-fields' ],
    ];

    register_post_type( Nectar_Templates::POST_TYPE, $args );
  }

  /**
   * Register REST-exposed post meta keys on the nectar_templates post type.
   *
   * Currently registers the header color-variant registry. Each entry is
   * `{ key, label }` and is purely user-defined; lifecycle states (default,
   * transparent, scrolled, overlayMenuOpened) are seeded by the editor and
   * stored separately. See `template-state-provider/index.tsx` for that side.
   */
  public function register_post_meta() {
    // Forward the explicit $user_id WordPress passes to the auth_callback (app-password /
    // WP-CLI --user= / user-switching) so the check honors the authorized user over the ambient
    // one — same pattern as Post_Meta.php.
    $can_edit_theme_options = function ( $allowed, $meta_key, $post_id, $user_id ) {
      return Access_Utils::can_edit_theme_options( $user_id );
    };

    register_post_meta( Nectar_Templates::POST_TYPE, '_nectar_header_color_variants', [
      'show_in_rest' => [
        'schema' => [
          'type' => 'array',
          'items' => [
            'type' => 'object',
            'properties' => [
              'key' => [ 'type' => 'string' ],
              'label' => [ 'type' => 'string' ],
            ],
            'additionalProperties' => false,
          ],
        ],
      ],
      'single' => true,
      'type' => 'array',
      'default' => [],
      'auth_callback' => $can_edit_theme_options,
      'sanitize_callback' => [ $this, 'sanitize_header_color_variants' ],
    ] );
    Post_Meta::register_restricted_key( '_nectar_header_color_variants' );

    // Per-slot editor preview backgrounds. Editor-only metadata — controls
    // the canvas preview for each header state/variant. Does not affect frontend.
    register_post_meta( Nectar_Templates::POST_TYPE, '_nectar_header_state_previews', [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'additionalProperties' => [
            'type' => 'object',
            'properties' => [
              'mode' => [ 'type' => 'string', 'enum' => [ 'light', 'dark', 'color' ] ],
              'value' => [ 'type' => 'string' ],
            ],
            'additionalProperties' => false,
          ],
        ],
      ],
      'single' => true,
      'type' => 'object',
      'default' => new \stdClass(),
      'auth_callback' => $can_edit_theme_options,
      'sanitize_callback' => [ $this, 'sanitize_header_state_previews' ],
    ] );
    Post_Meta::register_restricted_key( '_nectar_header_state_previews' );

    // Per-template state-transition timing override. Sentinels for
    // "inherit theme default": durationSec === 0, bezier count !== 4.
    register_post_meta( Nectar_Templates::POST_TYPE, '_nectar_header_state_transition', [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'durationSec' => [ 'type' => 'number' ],
            'bezier' => [
              'type' => 'array',
              'items' => [ 'type' => 'number' ],
            ],
          ],
          'additionalProperties' => false,
        ],
      ],
      'single' => true,
      'type' => 'object',
      'default' => new \stdClass(),
      'auth_callback' => $can_edit_theme_options,
      'sanitize_callback' => [ $this, 'sanitize_header_state_transition' ],
    ] );
    Post_Meta::register_restricted_key( '_nectar_header_state_transition' );
  }

  /**
   * - durationSec: number in [0, 1.5]; 0 = inherit.
   * - bezier: exactly four finite numbers in [-2, 2]; otherwise [] = inherit.
   *
   * @param mixed $value Raw value from REST.
   * @return array{durationSec: float, bezier: array<int, float>}
   */
  public function sanitize_header_state_transition( $value ): array {
    $out = [ 'durationSec' => 0.0, 'bezier' => [] ];
    if ( ! is_array( $value ) && ! is_object( $value ) ) {
      return $out;
    }
    $assoc = (array) $value;

    if ( isset( $assoc['durationSec'] ) && is_numeric( $assoc['durationSec'] ) ) {
      $d = (float) $assoc['durationSec'];
      if ( is_finite( $d ) && $d >= 0 && $d <= 1.5 ) {
        $out['durationSec'] = $d;
      }
    }

    if ( isset( $assoc['bezier'] ) && is_array( $assoc['bezier'] ) && count( $assoc['bezier'] ) === 4 ) {
      $clean = [];
      $valid = true;
      foreach ( $assoc['bezier'] as $n ) {
        if ( ! is_numeric( $n ) ) {
          $valid = false;
          break;
        }
        $f = (float) $n;
        if ( ! is_finite( $f ) || $f < -2 || $f > 2 ) {
          $valid = false;
          break;
        }
        $clean[] = $f;
      }
      if ( $valid ) {
        $out['bezier'] = $clean;
      }
    }

    return $out;
  }

  /**
   * Register REST routes under `/nectar-blocks/v1/`.
   */
  public function register_rest_routes(): void {
    register_rest_route(
        'nectar-blocks/v1',
        '/header-variants',
        [
        'methods' => 'GET',
        'permission_callback' => function () {
          return current_user_can( 'edit_posts' );
        },
        'callback' => [ $this, 'rest_get_header_variants' ],
      ]
    );
  }

  /**
   * Returns the deduplicated union of header color variants across all
   * published header_navigation templates. Editors of regular posts/pages
   * use this to populate the per-row variant picker.
   *
   * Shape: `[{ "key": "cream", "label": "Cream" }, ...]`
   *
   * Dedup policy: first label-by-key wins. Two header templates that both
   * define a 'cream' variant with different labels collapse to whichever
   * was queried first — acceptable for the picker since the key is what
   * matters at runtime.
   *
   * @return \WP_REST_Response
   */
  public function rest_get_header_variants(): \WP_REST_Response {
    $template_ids = get_posts( [
      'post_type' => Nectar_Templates::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'meta_query' => [
        [
          'key' => Nectar_Templates::META_KEY,
          'value' => 'nectar_template__header_navigation',
          'compare' => 'LIKE',
        ],
      ],
    ] );

    $seen_keys = [];
    $out = [];
    foreach ( $template_ids as $template_id ) {
      $variants = get_post_meta( $template_id, '_nectar_header_color_variants', true );
      if ( ! is_array( $variants ) ) {
        continue;
      }
      foreach ( $variants as $variant ) {
        if ( ! is_array( $variant ) ) {
          continue;
        }
        $key = isset( $variant['key'] ) && is_string( $variant['key'] ) ? $variant['key'] : '';
        $label = isset( $variant['label'] ) && is_string( $variant['label'] ) ? $variant['label'] : '';
        if ( '' === $key || isset( $seen_keys[$key] ) ) {
          continue;
        }
        $seen_keys[$key] = true;
        $out[] = [ 'key' => $key, 'label' => $label ];
      }
    }

    return new \WP_REST_Response( $out );
  }

  /**
   * Sanitize the per-slot editor preview map on save.
   *   - keys must match `^[a-zA-Z][a-zA-Z0-9-]{0,63}$`
   *   - mode must be one of 'auto' | 'light' | 'dark' | 'color'
   *   - when mode is 'color', value must be a valid 3- or 6-digit hex
   */
  public function sanitize_header_state_previews( $value ) {
    if ( ! is_array( $value ) && ! is_object( $value ) ) {
      return [];
    }
    $value = (array) $value;
    $out = [];
    foreach ( $value as $slot_key => $entry ) {
      if ( ! is_string( $slot_key ) ) {
        continue;
      }
      if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9-]{0,63}$/', $slot_key ) ) {
        continue;
      }
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $mode = isset( $entry['mode'] ) && is_string( $entry['mode'] ) ? $entry['mode'] : '';
      $hex = isset( $entry['value'] ) && is_string( $entry['value'] ) ? $entry['value'] : '';
      if ( ! in_array( $mode, [ 'light', 'dark', 'color' ], true ) ) {
        continue;
      }
      if ( $mode === 'color' ) {
        if ( ! preg_match( '/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $hex ) ) {
          continue;
        }
        $out[$slot_key] = [ 'mode' => 'color', 'value' => $hex ];
      } else {
        $out[$slot_key] = [ 'mode' => $mode ];
      }
    }
    return $out;
  }

  /**
   * Sanitize the header color variants array on save. Enforces:
   *   - array of `{ key, label }` entries
   *   - key matches /^[a-z][a-z0-9-]{0,63}$/
   *   - label is non-empty after trim, max 40 chars, control chars stripped
   *   - no key collision with reserved or lifecycle keys
   *   - duplicate keys: first wins, later silently dropped
   *   - duplicate labels (case-insensitive): first wins, later silently dropped
   *
   * Mirrors the client-side validation in HeaderStatePicker/useHeaderColorVariants.
   *
   * @param mixed $value Raw value from REST.
   * @return array Sanitized variants list.
   */
  public function sanitize_header_color_variants( $value ) {
    if ( ! is_array( $value ) ) {
      return [];
    }
    $reserved = self::header_variant_reserved();
    $reserved_keys = $reserved['reservedKeys'];
    $reserved_labels_lower = $reserved['reservedLabelsLower'];
    $key_pattern = '/' . $reserved['keyPattern'] . '/';
    $seen_keys = [];
    $seen_labels_lower = [];
    $out = [];
    foreach ( $value as $entry ) {
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $key = isset( $entry['key'] ) && is_string( $entry['key'] ) ? $entry['key'] : '';
      $label = isset( $entry['label'] ) && is_string( $entry['label'] ) ? $entry['label'] : '';

      if ( ! preg_match( $key_pattern, $key ) ) {
        continue;
      }
      if ( in_array( $key, $reserved_keys, true ) ) {
        continue;
      }
      if ( isset( $seen_keys[$key] ) ) {
        continue;
      }

      // Strip ASCII control chars and trim.
      $label = preg_replace( '/[\x00-\x1F\x7F]/u', '', $label );
      $label = is_string( $label ) ? trim( $label ) : '';
      if ( $label === '' || mb_strlen( $label ) > 40 ) {
        continue;
      }
      $label_lower = mb_strtolower( $label );
      if ( in_array( $label_lower, $reserved_labels_lower, true ) ) {
        continue;
      }
      if ( isset( $seen_labels_lower[$label_lower] ) ) {
        continue;
      }

      $seen_keys[$key] = true;
      $seen_labels_lower[$label_lower] = true;
      $out[] = [ 'key' => $key, 'label' => $label ];
    }
    return $out;
  }

  /**
   * Define the nectar section columns.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $columns
   * @return array
   */
  public function define_nectar_template_columns($columns) {
    // Add global sections columns
    $columns['templatePart'] = __('Template', 'nectar-blocks');
    $columns['conditions'] = __('Conditions', 'nectar-blocks');

    // Remove default columns
    unset($columns['date']);

    return $columns;
  }

  /**
   * Display the nectar section admin columns.
   * @since 2.0.0
   * @version 2.0.0
   * @param string $column
   * @param int $post_id
   */
  public function nectar_section_admin_columns($column, $post_id) {
    $post_meta = get_post_meta($post_id, Nectar_Templates::META_KEY, true);
    switch ($column) {
      case 'conditions':
        echo $this->get_display_conditions($post_meta);
        break;
      case 'templatePart':
        echo $this->get_display_template_part($post_meta);
        break;
    }
  }

  /**
   * Get the display conditions.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_conditions($post_meta): string {
    $conditions = isset($post_meta['conditions']) && is_array($post_meta['conditions']) ? $post_meta['conditions'] : [];
    if( empty($conditions) ) {
      return '';
    }
    $conditions_output = '<div class="condition-wrapper">';
    $operator = isset($post_meta['operator']) ? $post_meta['operator'] : 'and';

    $conditions_map = [];
    $conditions_list = FlatMap::flatMap(function($condition_list_item) {
      return $condition_list_item['options'];
    }, Nectar_Templates::get_conditions());
    foreach($conditions_list as $index => $condition) {
      $conditions_map[$condition['value']] = $condition['label'];
    }

    $include_map = [
      true => 'True',
      false => 'False',
    ];

    foreach($conditions as $index => $condition) {
      $condition_key = isset($condition['condition']) ? $condition['condition'] : '';
      if ( empty($condition_key) ) {
        continue;
      }
      // Get the labels
      if (array_key_exists($condition_key, $conditions_map)) {
        $condition_label = $conditions_map[$condition_key];
      } else {
        $condition_label = $condition_key;
      }
      $include_value = isset($condition['include']) ? $condition['include'] : true;
      $include_label = $include_map[$include_value];

      $value_label = $condition_label;

      if ( 'specific_post' === $condition_key ) {
        $post_data = isset($condition['postData']) ? $condition['postData'] : ( $condition['post_data'] ?? null );
        $post_display = '';

        if ( $post_data ) {
          if ( is_object($post_data) ) {
            $post_data = (array) $post_data;
          }
          $post_display = trim(
              ( isset($post_data['title']) ? sanitize_text_field($post_data['title']) : '' ) .
            ( isset($post_data['id']) ? ' (#' . intval($post_data['id']) . ')' : '' )
          );
        }

        if ( $post_display !== '' ) {
          $value_label .= ' — ' . $post_display;
        }
      }
      else if ( in_array($condition_key, ['is_taxonomy_term', 'has_taxonomy_term'], true ) ) {
        $taxonomy_data = isset($condition['taxonomyTermData']) ? $condition['taxonomyTermData'] : null;
        $taxonomy_display = '';

        if ( $taxonomy_data ) {
          if ( is_object($taxonomy_data) ) {
            $taxonomy_data = (array) $taxonomy_data;
          }
          $taxonomy_display = trim(implode(' ', array_filter([
            isset($taxonomy_data['name']) ? sanitize_text_field($taxonomy_data['name']) : '',
            isset($taxonomy_data['taxonomyLabel']) ? sanitize_text_field($taxonomy_data['taxonomyLabel']) : ( isset($taxonomy_data['taxonomy']) ? sanitize_key($taxonomy_data['taxonomy']) : '' ),
            isset($taxonomy_data['slug']) ? '(' . sanitize_title($taxonomy_data['slug']) . ')' : ''
          ])));
        }

        if ( $taxonomy_display !== '' ) {
          $value_label .= ' — ' . $taxonomy_display;
        }
      }

      if ( $value_label !== '' ) {
        $conditions_output .= '<div class="nectar-condition-badge">';
        $conditions_output .= '<span class="label">' . $include_label . '</span>';
        $conditions_output .= '<span class="value">' . $value_label . '</span>';
        $conditions_output .= '</div>';
      }

      // Add operator if not last condition
      if (count($conditions) !== $index + 1) {
        $conditions_output .= '<span class="nectar-condition-badge--operator"><span>' . esc_html($operator) . '</span></span>';
      }
    }

    $conditions_output .= '</div>';

    return $conditions_output;
  }

  /**
   * Get the display template part.
   * @since 2.0.0
   * @version 2.0.0
   * @param array $post_meta
   * @return string
   */
  public function get_display_template_part($post_meta) {
    $template_part = $post_meta['templatePart'];
    $template_part_output = $template_part;
    $conditions_list = Nectar_Templates::get_template_parts();

    $template_part_option = array_filter($conditions_list, function ($condition) use ($template_part) {
      return $condition['value'] === $template_part;
    });
    if (count($template_part_option) === 1) {
      $template_part_output = array_values($template_part_option)[0]['label'];
    }

    // Placeholder
    if ( $template_part === '' ) {
      $template_part_output = __('None Assigned', 'nectar-blocks');
    }

    return $template_part_output;
  }

  public function nectar_template_shortcode_callback($atts) {

    extract(shortcode_atts([
      "id" => "",
      'enable_display_conditions' => ''
    ], $atts));

    if (empty($id)) {
      return;
    }

    $section_id = intval($id);
    $section_id = apply_filters('wpml_object_id', $section_id, 'post', true);

    if( $section_id === 0 ) {
      return;
    }

    $section_status = get_post_status($section_id);
    $allow_output = true;

    if ( $enable_display_conditions === 'yes' ) {
      $allow_output = Render::get_instance()->verify_conditional_display( $section_id );
    }

    if ( 'publish' !== $section_status || ! $allow_output ) {
      return;
    }

    $section_content = get_post_field('post_content', $section_id);
    if (empty($section_content)) {
      return;
    }

    $unneeded_tags = [
      '<p>[' => '[',
      ']</p>' => ']',
      ']<br />' => ']',
      ']<br>' => ']',
    ];

    if( function_exists('do_blocks')) {
      $rendered_section_content = do_blocks($section_content);
    }
    $rendered_section_content = wptexturize( $rendered_section_content);
    $rendered_section_content = convert_smilies( $rendered_section_content );
    $rendered_section_content = shortcode_unautop( $rendered_section_content );
    $rendered_section_content = wp_filter_content_tags( $rendered_section_content );
    $rendered_section_content = strtr($rendered_section_content, $unneeded_tags);

    // Process images for proper loading attributes
    if (class_exists('WP_HTML_Tag_Processor')) {
        $processor = new \WP_HTML_Tag_Processor($rendered_section_content);
        $image_count = 0;

        while ($processor->next_tag(['tag_name' => 'img'])) {
            $image_count++;

            // Get current attributes
            $attributes = [
                'class' => $processor->get_attribute('class'),
                'src' => $processor->get_attribute('src'),
                'alt' => $processor->get_attribute('alt'),
                'width' => $processor->get_attribute('width'),
                'height' => $processor->get_attribute('height'),
                'loading' => $processor->get_attribute('loading'),
                'decoding' => $processor->get_attribute('decoding')
            ];

            // Get WordPress's automatic loading optimization attributes
            $loading_attrs = wp_get_loading_optimization_attributes('img', $attributes, 'wp_get_attachment_image');

            // For the first image, ensure high priority
            if ($image_count === 1) {
                $loading_attrs['fetchpriority'] = 'high';
                // remove loading attribute if it exists
                if ($processor->get_attribute('loading')) {
                    $processor->remove_attribute('loading');
                }
            }

            // For images after the second one, set loading to lazy if no loading attribute exists
            if ($image_count > 2 && ! $processor->get_attribute('loading') && ! $processor->get_attribute('fetchpriority')) {
                $loading_attrs['loading'] = 'lazy';
            }

            // Update image attributes
            foreach ($loading_attrs as $name => $value) {
                $processor->set_attribute($name, $value);
            }
        }

        $rendered_section_content = $processor->get_updated_html();
    }

    $rendered_section_content = apply_filters('nectar_global_section_content_output', $rendered_section_content);

    $global_section_markup = '';

    ob_start();
    // Look for dynamic CSS from blocks.
    $dynamic_css = get_post_meta( $section_id, '_nectar_blocks_css', true );

    if ( ! empty( $dynamic_css ) ) {
      $FE_RENDER = new Frontend_Render();
      $dynamic_css = $FE_RENDER->render_dynamic_content([], $dynamic_css);
    } else {
      $dynamic_css = '';
    }

    // Always check for nested patterns, even when no section-level CSS exists.
    if ($section_content) {
      $blocks = parse_blocks($section_content);
      $BLOCKS_RENDER_REFLECTION = new \ReflectionClass(Blocks_Render::class);
      $BLOCKS_RENDER = $BLOCKS_RENDER_REFLECTION->newInstanceWithoutConstructor();
      $patterns_css = $BLOCKS_RENDER->frontend_pattern_css($blocks);
      $dynamic_css .= $patterns_css;
    }

    if ( $dynamic_css !== '' ) {
      echo '<style data-type="nectar-template-dynamic-css">' . $dynamic_css . '</style>';
    }
    // Output section.
    echo do_shortcode($rendered_section_content);

    $global_section_markup .= ob_get_contents();
    ob_end_clean();

    return $global_section_markup;
  }

  public function admin_columns_css() {
    return '
      .edit-php .nectar-condition-badge {
        display: flex;
        gap: 8px;
        justify-content: space-between;
        align-items: center;
      }

      .edit-php .condition-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge span {
        background-color: #fff;
        border: 1px solid #ccc;
        transition: border-color 0.2s ease;
        border-radius: 100px;
        font-size: 11px;
        line-height: 1;
        font-weight: 400;
        padding: 3px 6px;
      }

      .edit-php .nectar-condition-badge--operator {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .edit-php .nectar-condition-badge--operator span {
        background-color: #fff;
        border: 1px solid #ccc;
        transition: border-color 0.2s ease;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 6px;
        line-height: 1;
        content: var(--and-text);
        text-transform: uppercase;
      }
    ';
  }
}
