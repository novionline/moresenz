<?php
/**
 * Per-page Used CSS Optimization.
 *
 * Analyzes which theme CSS rules are actually used on each page
 * and caches an optimized subset. On subsequent visits the optimized
 * CSS replaces the full theme stylesheets.
 *
 * Cache is stored as CSS files in wp-content/uploads/nectar-blocks/theme/used-css/.
 *
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nectar_Used_CSS {
    /**
     * Option key for the global version hash.
     * Bumped when theme options or customizer settings change,
     * which invalidates all per-page caches (the directory is renamed).
     */
    const GLOBAL_VERSION_OPTION = 'nectar_used_css_version';

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * Theme stylesheet handles to dequeue when serving optimised CSS.
     *
     * @var string[]
     */
    private $theme_handles = [];

    /**
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {

        // Mark singular pages dirty on save.
        // Templates (header, footer, OCM) affect every page — invalidate all.
        add_action( 'save_post', [ $this, 'on_save_post' ], 10, 2 );

        // Invalidate ALL caches when theme options, customizer, or plugins change.
        add_action( 'customize_save_after', [ $this, 'invalidate_all' ] );
        add_action( 'activated_plugin', [ $this, 'invalidate_all' ] );
        add_action( 'deactivated_plugin', [ $this, 'invalidate_all' ] );
        add_action( 'wp_update_nav_menu', [ $this, 'invalidate_all' ] );

        // AJAX endpoints (admin only — analyser only injects for admins).
        add_action( 'wp_ajax_nectar_save_used_css', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_nectar_flush_used_css', [ $this, 'ajax_flush' ] );

        // Frontend: serve cached CSS after WooCommerce (10) but before plugin (99).
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_serve' ], 15 );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_hooks' ], 999 );

        // Customizer flush button JS.
        add_action( 'customize_controls_print_footer_scripts', [ $this, 'customizer_flush_script' ] );
    }

    /**
     * Output JS in the customizer footer for the flush button + toggle visibility.
     */
    public function customizer_flush_script() {
        $nonce = wp_create_nonce( 'nectar_flush_used_css' );
        ?>
        <script>
        (function() {
            wp.customize.bind( 'ready', function() {

                // Toggle visibility of flush button based on switch.
                var setting = wp.customize( 'used-css-optimization' );
                if ( ! setting ) return;

                function toggle( val ) {
                    // Wait for DOM — Kirki custom controls render async.
                    var attempts = 0;
                    var poll = setInterval( function() {
                        if ( ++attempts > 50 ) { clearInterval( poll ); return; }
                        var btn = document.querySelector( '.nectar-flush-used-css' );
                        if ( ! btn ) return;
                        clearInterval( poll );
                        var wrap = btn.closest( '.customize-control' );
                        if ( wrap ) {
                            wrap.style.display = ( val === '1' ) ? '' : 'none';
                        }
                    }, 200 );
                }

                toggle( setting.get() );
                setting.bind( function( val ) { toggle( val ); } );

                // Flush button click.
                document.addEventListener( 'click', function( e ) {
                    if ( ! e.target.classList.contains( 'nectar-flush-used-css' ) ) return;
                    var btn = e.target;
                    var status = document.querySelector( '.nectar-flush-used-css-status' );
                    btn.disabled = true;
                    if ( status ) status.textContent = '<?php echo esc_js( __( 'Flushing...', 'nectar-blocks-theme' ) ); ?>';

                    fetch( ajaxurl + '?action=nectar_flush_used_css&nonce=<?php echo $nonce; ?>', {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                    .then( function( r ) { return r.json(); } )
                    .then( function( data ) {
                        if ( status ) status.textContent = data.success
                            ? '<?php echo esc_js( __( 'Cache flushed.', 'nectar-blocks-theme' ) ); ?>'
                            : '<?php echo esc_js( __( 'Error flushing cache.', 'nectar-blocks-theme' ) ); ?>';
                        btn.disabled = false;
                        setTimeout( function() { if ( status ) status.textContent = ''; }, 3000 );
                    })
                    .catch( function() {
                        if ( status ) status.textContent = '<?php echo esc_js( __( 'Error.', 'nectar-blocks-theme' ) ); ?>';
                        btn.disabled = false;
                    });
                });

            });
        })();
        </script>
        <?php
    }

    // ------------------------------------------------------------------
    //  Page identification
    // ------------------------------------------------------------------

    /**
     * Build a stable cache key for the current page.
     *
     * Singular pages use post ID.
     * Archives / non-singular use a hash of the request path (pagination stripped).
     *
     * @return string  e.g. "post_42" or "archive_a1b2c3d4"
     */
    private function get_page_cache_key() {

        if ( is_singular() ) {
            return 'post_' . get_queried_object_id();
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = wp_parse_url( $uri, PHP_URL_PATH ) ?: '/';

        // Strip pagination so /category/design/ and /category/design/page/2/ share a cache.
        $path = preg_replace( '#/page/\d+/?$#', '/', $path );

        // Include query string for search/filtered archives (different results = different CSS).
        $query = wp_parse_url( $uri, PHP_URL_QUERY );
        $key = $query ? $path . '?' . $query : $path;

        return 'archive_' . substr( md5( $key ), 0, 10 );
    }

    // ------------------------------------------------------------------
    //  Cache read / write
    // ------------------------------------------------------------------

    /**
     * @return string
     */
    private function global_version() {
        $version = get_option( self::GLOBAL_VERSION_OPTION, '' );
        if ( empty( $version ) ) {
            $version = $this->regenerate_global_version();
        }
        return $version;
    }

    /**
     * @return string
     */
    private function regenerate_global_version() {
        $version = nectar_get_theme_version() . '-' . wp_generate_password( 8, false );
        update_option( self::GLOBAL_VERSION_OPTION, $version, true );
        return $version;
    }

    // ------------------------------------------------------------------
    //  Filesystem — delegates to Nectar_Uploads
    // ------------------------------------------------------------------

    const STORAGE_SUB = 'used-css';

    /**
     * Sub-path for the current version directory.
     * e.g. 'used-css/1.0-abc12345'
     *
     * @return string
     */
    private function version_sub_path() {
        return self::STORAGE_SUB . '/' . $this->global_version();
    }

    /**
     * Check if a cached CSS file exists for the given key.
     *
     * @param string $cache_key
     * @return bool
     */
    private function has_cache( $cache_key ) {
        return Nectar_Theme_Uploads::file_exists( $this->version_sub_path() . '/' . $cache_key . '.css' );
    }

    /**
     * Get the public URL for a cached CSS file.
     *
     * @param string $cache_key
     * @return string|false
     */
    private function get_css_file_url( $cache_key ) {
        return Nectar_Theme_Uploads::url( $this->version_sub_path() . '/' . $cache_key . '.css' );
    }

    /**
     * Write cached CSS to a file.
     *
     * @param string $cache_key
     * @param string $css
     */
    private function write_cache( $cache_key, $css ) {
        Nectar_Theme_Uploads::put_contents( $this->version_sub_path() . '/' . $cache_key . '.css', $css );
    }

    // ------------------------------------------------------------------
    //  Invalidation
    // ------------------------------------------------------------------

    /**
     * Route save_post to the correct invalidation.
     * Templates (header, footer, OCM) generate global CSS that affects
     * every page — saving one must invalidate all caches.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     */
    public function on_save_post( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Global sections / templates affect all pages.
        $global_post_types = [ 'nectar_templates', 'nectar_sections' ];
        if ( in_array( $post->post_type, $global_post_types, true ) ) {
            $this->invalidate_all();
            return;
        }

        // Regular posts — just clear that page's cache.
        Nectar_Theme_Uploads::delete_file( $this->version_sub_path() . '/post_' . $post_id . '.css' );
    }

    /**
     * Invalidate every page's cache by bumping the global version.
     * Old version directories are cleaned up.
     */
    public function invalidate_all() {
        // Keep the previous version directory so page caches referencing
        // old CSS files don't 404. Only delete versions older than the
        // previous one.
        $old_version = $this->global_version();
        $this->regenerate_global_version();

        $old_dirs = Nectar_Theme_Uploads::list_dirs( self::STORAGE_SUB, $this->global_version() );
        foreach ( $old_dirs as $dir_name ) {
            // Keep the immediately previous version — delete anything older.
            if ( $dir_name === $old_version ) {
                continue;
            }
            Nectar_Theme_Uploads::delete_dir( self::STORAGE_SUB . '/' . $dir_name );
        }
    }

    // ------------------------------------------------------------------
    //  Frontend
    // ------------------------------------------------------------------

    /**
     * Skip optimization for contexts where a cached CSS snapshot would be
     * wrong or pointless: admin, cron, preview, and highly dynamic pages
     * (cart/checkout/account vary by user; 404/search vary by content and
     * are typically excluded from page caches anyway).
     *
     * @return bool
     */
    private function should_skip() {

        if ( is_admin() || is_customize_preview() || wp_doing_cron() ) {
            return true;
        }

        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return true;
        }

        if ( isset( $_GET['no_css_opt'] ) ) {
            return true;
        }

        // Non-HTML responses.
        if ( is_feed() || is_embed() || is_trackback() || is_robots() ) {
            return true;
        }

        // Preview content can diverge from the published version.
        if ( is_preview() ) {
            return true;
        }

        // Same URL renders either the password form or the content.
        if ( post_password_required() ) {
            return true;
        }

        if ( is_404() || is_search() ) {
            return true;
        }

        if ( class_exists( 'WooCommerce' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
            return true;
        }

        return false;
    }

    /**
     * Enqueue used CSS file after WooCommerce (priority 15) but before plugin styles.
     */
    public function frontend_serve() {

        if ( $this->should_skip() ) {
            return;
        }

        $cache_key = $this->get_page_cache_key();

        if ( ! $this->has_cache( $cache_key ) ) {
            return;
        }

        $url = $this->get_css_file_url( $cache_key );
        if ( ! $url ) {
            return;
        }

        wp_enqueue_style( 'nectar-used-css', $url, [], null );
    }

    /**
     * Dequeue theme stylesheets (priority 999) and inject analyser if no cache.
     */
    public function frontend_hooks() {

        if ( $this->should_skip() ) {
            return;
        }

        $cache_key = $this->get_page_cache_key();

        if ( $this->has_cache( $cache_key ) ) {
            // Dequeue all theme stylesheets — used CSS is already enqueued early.
            $this->collect_theme_handles();
            foreach ( $this->theme_handles as $handle ) {
                wp_dequeue_style( $handle );
            }
        } elseif ( current_user_can( 'manage_options' ) ) {
            // Only admins trigger analysis — their pages are typically
            // not served from page cache, avoiding stale HTML issues.
            add_action( 'wp_footer', function() use ( $cache_key ) {
                $this->inject_analyser( $cache_key );
            }, 999 );
        }
    }

    /**
     * Build the list of enqueued theme stylesheet handles.
     */
    private function collect_theme_handles() {

        $theme_url = get_template_directory_uri();
        $wp_styles = wp_styles();

        $dynamic_url_fragment = 'nectar-blocks/theme/dynamic-styles';

        foreach ( $wp_styles->registered as $handle => $obj ) {

            if ( empty( $obj->src ) ) {
                continue;
            }

            $src = $obj->src;

            if (
                strpos( $src, $theme_url ) !== false ||
                strpos( $src, $dynamic_url_fragment ) !== false
            ) {
                $this->theme_handles[] = $handle;
            }
        }
    }

    // ------------------------------------------------------------------
    //  Analyser script
    // ------------------------------------------------------------------

    /**
     * Scheme-relative URLs (query string stripped) of every theme
     * stylesheet actually printed on this page. The analyser requires
     * each one to be present and readable in document.styleSheets
     * before saving a capture — a sheet whose fetch failed (404, or
     * truncated mid-regeneration) never appears there, so a capture
     * taken at that moment would permanently bake in its absence.
     *
     * @return string[]
     */
    private function expected_sheet_urls() {

        $theme_url = get_template_directory_uri();
        $dynamic_url_fragment = 'nectar-blocks/theme/dynamic-styles';
        $wp_styles = wp_styles();

        $urls = [];

        foreach ( (array) $wp_styles->done as $handle ) {

            $obj = isset( $wp_styles->registered[$handle] ) ? $wp_styles->registered[$handle] : null;
            if ( ! $obj || empty( $obj->src ) ) {
                continue;
            }

            $src = $obj->src;

            if (
                strpos( $src, $theme_url ) === false &&
                strpos( $src, $dynamic_url_fragment ) === false
            ) {
                continue;
            }

            $src = strtok( $src, '?' );
            $urls[] = preg_replace( '#^https?:#', '', $src );
        }

        return array_values( array_unique( $urls ) );
    }

    /**
     * Output the inline JS that analyses used CSS rules and POSTs them back.
     *
     * @param string $cache_key
     */
    public function inject_analyser( $cache_key ) {

        $nonce = wp_create_nonce( 'nectar_used_css_' . $cache_key );

        $theme_url = esc_js( get_template_directory_uri() );
        $dynamic_url_marker = esc_js( 'nectar-blocks/theme/dynamic-styles' );
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
        $cache_key_js = esc_js( $cache_key );

        ?>
        <script id="nectar-used-css-analyser">
        (function(){

            var THEME_URL   = '<?php echo $theme_url; ?>';
            var DYNAMIC_URL = '<?php echo $dynamic_url_marker; ?>';
            var AJAX_URL    = '<?php echo $ajax_url; ?>';
            var CACHE_KEY   = '<?php echo $cache_key_js; ?>';
            var NONCE       = '<?php echo $nonce; ?>';
            var EXPECTED_SHEETS = <?php echo wp_json_encode( $this->expected_sheet_urls() ); ?>;

            function isThemeSheet( sheet ) {
                try {
                    var href = sheet.href || '';
                    return href.indexOf( THEME_URL ) !== -1 || href.indexOf( DYNAMIC_URL ) !== -1;
                } catch(e) {
                    return false;
                }
            }

            /**
             * Resolve relative url() paths against a stylesheet's base URL
             * so they work when inlined in the page.
             */
            function resolveUrls( cssText, baseUrl ) {
                if ( ! baseUrl ) return cssText;
                return cssText.replace(
                    /url\(\s*(['"]?)(?!data:|https?:|\/\/|#)(.*?)\1\s*\)/gi,
                    function( match, quote, url ) {
                        try {
                            var abs = new URL( url, baseUrl ).href;
                            return 'url(' + quote + abs + quote + ')';
                        } catch(e) {
                            return match;
                        }
                    }
                );
            }

            function stripPseudos( sel ) {
                sel = sel.replace( /::[\w-]+(\([^)]*\))?/g, '' );
                sel = sel.replace( /:(?!not\b|is\b|where\b|has\b|root\b|nth-|first-|last-|only-)[\w-]+(\([^)]*\))?/g, '' );
                // Clean up empty :not()/:is()/:where() left after stripping inner pseudos.
                sel = sel.replace( /:(?:not|is|where|has)\(\s*\)/g, '' );
                return sel.trim();
            }

            /**
             * JS-injected or interaction-toggled selectors that may not
             * be in the initial DOM but must be kept.
             */
            /**
             * Element/component substrings — keep if they appear anywhere
             * in the selector.
             */
            var SAFELIST = [
                // Off-canvas menu / mobile menu.
                '#slide-out-widget-area', '.slide-out-widget-area-toggle',
                '.ocm-effect-wrap', '.off-canvas-menu-container',
                '#mobile-menu', '.nectar-hb-ocm', '.slide_out_area_close',

                // Search overlay / AJAX search results.
                '#search-outer', '.nectar-ajax-search-results',

                // Select2 (JS-generated dropdowns replacing <select> elements).
                'select2',

                // Close buttons (used across OCM, sidebars, modals).
                '.nectar-close-btn',

                // Megamenu panels (JS-injected/toggled).
                '.megamenu', '.nectar-megamenu', 'megamenu',

                // Blog/portfolio next-prev navigation (JS-loaded backgrounds).
                '.blog_next_prev_buttons', '.bottom_controls', '.proj-bg-img', '.post-bg-img',

                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                // WooCommerce: review form modal (JS-toggled).
                'review_form_wrapper',
                // WooCommerce: quick view / cart / notices / fragments.
                '.nectar-quick-view-box', '.nectar-slide-in-cart',
                '.woocommerce-mini-cart', '.widget_shopping_cart',
                '.woocommerce-message', '.woocommerce-error', '.woocommerce-info',
                '.cart_list', '.mini_cart', '.quantity',
                'input.plus', 'input.minus', 'input.qty',
                '.add_to_cart', '.added_to_cart', '.single_add_to_cart',
                '.cart-subtotal', '.shipping', '.order-total',
                '.woocommerce-cart', '.woocommerce-checkout',
                '.wc-forward', '.onsale',
                '.woocommerce-product-rating', '.woocommerce-product-gallery',
                '.single_add_to_cart_button', '.woocommerce-variation',
                '.woocommerce-tabs', '.woocommerce-review-link',
                'div.product', '.product_meta', '.product_title',
                // Mini cart AJAX fragment inner elements.
                '.mini_cart_item', '.product-meta', '.product-details',
                '.product-price', '.remove_from_cart_button',
                '.woocommerce-mini-cart__total', '.woocommerce-mini-cart__buttons',
                '.modify', '.close-cart', '.close-line',
                // Cart / checkout page elements.
                '.woocommerce-cart-form', '.cart-collaterals',
                '.checkout-form', '.woocommerce-checkout-payment',
                <?php endif; ?>

                // Lightbox / gallery — only safelist if page has lightbox triggers.
                '.easyzoom',

                // Floating labels (JS-added to form wrappers).
                '.nectar-floating-label', '.nectar-fl-',

                // Swiper / carousel.
                '.swiper', '.nb-swiper',
            ];

            /**
             * Regex patterns for JS-toggled state/modifier classes.
             * These are classes added by JS on interaction — they won't be
             * in the initial DOM but their rules must be kept.
             */
            var SAFELIST_PATTERNS = /[.#](?:open|close|closed|active|hover|hovered|focus|visible|hidden|modal|loaded|img-loaded|sfHover|sf-hover|scrolled-down|scrolling|fixed-menu|transparent|overlay-menu-opened|is-variant-[\w-]+|material-open|material-ocm-open|material-search-open|ocm-open|open-submenu|current-menu-item|current-menu-ancestor|menu-item-over|mobile|using-mobile-browser|ascend|nectar-inactive|detached|invisible|at-top|at-top-before-box|small-nav|no-transition|no-trans|no-pointer-events|no-material-transition|no-delay|hide-up|side-widget-open|side-widget-closed|header-not-visible|hidden-secondary|hidden-menu|is-transitioning-state|stuck|subview|subviewopen|dl-animate-in|dl-animate-out|non-human-allowed|animating|animated-in|animated-scrolling|ios-ocm-style|slide-out-from-right-hover|slide-out-hover-icon-effect|fullscreen-alt|back|mouse-accessed|mouse-leaving|edge|on-left-side|align-middle|has-ul|unhidden-line|all-hidden|effect-shown|hide_until_rendered|simple-ocm-open|menu-push-out|menuopen|menu-toggle|blurred|at-content|line-shown|dark-slide|temp-removed-dark-slide|open-search|results-shown|entrance-animation|force-condense|force-condense-remove|within-custom-breakpoint|fancy-select-wrap|hover-bound|hover-effect|calculated|nectar-parallax-enabled|translate|desktop-controls-hidden|iframe-embed|small|pre-animated|doing-text-animation|n-sticky-initialized|current-open-item<?php if ( class_exists( 'WooCommerce' ) ) : ?>|style_slide_in_click|processing|blockUI|blockOverlay|loading|product_added|first-load|has_products|nectar-allow-scroll|open-filter|no-widget-title<?php endif; ?>)(?![\w-])|centered-menu-bottom-bar/;

            /**
             * Blocklist — selectors containing these substrings are always
             * dropped when the corresponding feature is inactive.
             */
            var BLOCKLIST = [
                <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                '.woocommerce', '.wc-', '.product', '.cart_list', '.mini_cart',
                '.widget_shopping_cart', '.nectar-slide-in-cart', '.nectar-quick-view',
                '.onsale', '.add_to_cart', '.checkout',
                <?php endif; ?>
            ];

            function isBlocklisted( sel ) {
                for ( var i = 0; i < BLOCKLIST.length; i++ ) {
                    if ( sel.indexOf( BLOCKLIST[i] ) !== -1 ) return true;
                }
                return false;
            }

            /**
             * Check if a selector contains a safelisted element or
             * a JS-toggled state class. When the match comes from a
             * state-class pattern, verify that the host element
             * (the non-state part) actually exists on the page.
             */
            function isSafelisted( sel ) {
                if ( isBlocklisted( sel ) ) return false;

                // Component substrings — keep unconditionally.
                for ( var i = 0; i < SAFELIST.length; i++ ) {
                    if ( sel.indexOf( SAFELIST[i] ) !== -1 ) return true;
                }

                // State-class patterns — verify the host element exists.
                if ( ! SAFELIST_PATTERNS.test( sel ) ) return false;

                // Find the compound selector that contains the safelist match
                // and verify the base element (without the state class) exists.
                var parts = sel.trim().split( /\s*(?:[>~+]|\s)\s*/ ).filter( Boolean );
                for ( var p = 0; p < parts.length; p++ ) {
                    if ( ! SAFELIST_PATTERNS.test( parts[p] ) ) continue;

                    // Strip ALL safelisted state classes to get the base.
                    var base = parts[p];
                    while ( SAFELIST_PATTERNS.test( base ) ) {
                        base = base.replace( SAFELIST_PATTERNS, '' );
                    }
                    base = base.trim();
                    base = base.replace( /\[.*?\]/g, '' ).trim();

                    if ( ! base ) {
                        // State class is the entire compound (e.g. ".open").
                        // Check if any other compound in the chain exists.
                        for ( var a = 0; a < parts.length; a++ ) {
                            if ( a === p ) continue;
                            var ancestor = parts[a].replace( /\[.*?\]/g, '' ).trim();
                            // Skip bare tag selectors — too generic.
                            if ( ancestor && /[#.]/.test( ancestor ) ) {
                                try {
                                    if ( document.querySelector( ancestor ) ) return true;
                                } catch(e) {}
                            }
                        }
                        return parts.length <= 1;
                    }

                    // Bare tag after stripping (e.g. "li", "a", "span") — check
                    // if any other compound with a class/ID exists on the page.
                    if ( ! /[#.]/.test( base ) ) {
                        for ( var a = 0; a < parts.length; a++ ) {
                            if ( a === p ) continue;
                            var alt = parts[a].replace( /\[.*?\]/g, '' ).trim();
                            if ( alt && /[#.]/.test( alt ) ) {
                                try {
                                    if ( document.querySelector( alt ) ) return true;
                                } catch(e) {}
                            }
                        }
                        return false;
                    }

                    try {
                        if ( document.querySelector( base ) ) return true;
                    } catch(e) {
                        return true;
                    }
                    return false;
                }
                return false;
            }

            // Deferred — filtered by reference after all sheets are collected.
            var allKeyframes = [];

            /**
             * Check if a rule should always be kept regardless of selector matching.
             * Does NOT include @keyframes — those are deferred and filtered by reference.
             */
            function isAlwaysKeep( rule ) {
                if ( rule.type === CSSRule.KEYFRAMES_RULE ) {
                    return false;
                }
                // @font-face, @supports, @import, @charset, @layer, etc.
                if ( rule.type !== CSSRule.STYLE_RULE && rule.type !== CSSRule.MEDIA_RULE ) {
                    return true;
                }
                // :root / html with CSS custom properties.
                if ( rule.type === CSSRule.STYLE_RULE && /^(:root|html)\b/.test( rule.selectorText.trim() ) ) {
                    return true;
                }
                return false;
            }

            /**
             * Does the original (pre-strip) selector target a pseudo-element?
             */
            function hasPseudoElement( sel ) {
                return /::?(?:before|after|placeholder|first-line|first-letter)\b/.test( sel );
            }

            /**
             * Loose match for ::before/::after selectors.
             * Extracts the target element (last compound selector), strips
             * modifier/state classes, and tests just that base element.
             *
             * e.g. ".material-search-open ~ #nectar-content-wrap"
             *   → target: "#nectar-content-wrap" → exists → keep
             *
             * e.g. "#nectar-content-wrap.material-open"
             *   → target: "#nectar-content-wrap.material-open"
             *   → base (classes stripped): "#nectar-content-wrap" → exists → keep
             */
            function looseMatchPseudo( strippedSel ) {
                // Get the last compound selector (after last combinator).
                var match = strippedSel.match( /([^\s>~+]+)\s*$/ );
                if ( ! match ) return false;
                var target = match[1];

                // Try target as-is.
                try { if ( document.querySelector( target ) ) return true; } catch(e) {}

                // Strip modifier classes, keep ID/tag.
                var base = target.replace( /\.[\w-]+/g, '' ).trim();
                if ( base ) {
                    try { if ( document.querySelector( base ) ) return true; } catch(e) {}
                }
                return false;
            }

            /**
             * Return only the selectors that match the DOM, are safelisted,
             * or target a pseudo-element on an existing base element.
             */
            function getUsedSelectors( selectorText ) {
                var selectors = selectorText.split( ',' );
                var used = [];
                for ( var i = 0; i < selectors.length; i++ ) {
                    var original = selectors[i].trim();

                    // Drop if blocklisted (inactive feature).
                    if ( isBlocklisted( original ) ) continue;

                    // Keep if safelisted (JS-injected element).
                    if ( isSafelisted( original ) ) {
                        used.push( original );
                        continue;
                    }

                    // View Transition API selectors are standalone pseudo-elements
                    // with no base selector — always keep them.
                    if ( /^::view-transition/.test( original.trim() ) ) {
                        used.push( original );
                        continue;
                    }

                    var stripped = stripPseudos( original );
                    if ( ! stripped ) continue;
                    try {
                        if ( document.querySelector( stripped ) ) {
                            used.push( original );
                        } else if ( hasPseudoElement( original ) && looseMatchPseudo( stripped ) ) {
                            // Pseudo-element on an existing base element.
                            used.push( original );
                        }
                    } catch(e) {
                        used.push( original );
                    }
                }
                return used;
            }

            // Tokens (classes/IDs) found in matched non-media rules.
            // Used as a secondary check for media query rules.
            var matchedTokens = {};

            /**
             * Extract class/ID tokens from a selector and mark them as matched.
             */
            function recordTokens( sel ) {
                var tokens = sel.match( /[#.][\w-]+/g );
                if ( tokens ) {
                    for ( var t = 0; t < tokens.length; t++ ) {
                        matchedTokens[ tokens[t] ] = true;
                    }
                }
            }

            /**
             * Check if the majority of class/ID tokens in a selector were
             * seen in matched non-media rules. Requires >50% of tokens to
             * match to avoid false positives from generic single-token hits.
             */
            function hasMatchedToken( sel ) {
                var tokens = sel.match( /[#.][\w-]+/g );
                if ( ! tokens || tokens.length === 0 ) return false;

                // The first token is the outermost ancestor — if it doesn't
                // exist on this page, the rule can never apply.
                if ( ! matchedTokens[ tokens[0] ] ) return false;

                if ( tokens.length === 1 ) return true;

                // Multi-token: require more than half to match.
                var hits = 1; // first already confirmed
                for ( var t = 1; t < tokens.length; t++ ) {
                    if ( matchedTokens[ tokens[t] ] ) hits++;
                }
                return hits > tokens.length / 2;
            }

            /**
             * Pre-scan: quickly collect matched tokens from non-media
             * style rules. Only records tokens from selectors that
             * genuinely match querySelector — no safelist, no catch fallback.
             */
            function prescanTokens( rules ) {
                for ( var i = 0; i < rules.length; i++ ) {
                    var rule = rules[i];
                    if ( rule.type !== CSSRule.STYLE_RULE ) continue;
                    var selectors = rule.selectorText.split( ',' );
                    for ( var s = 0; s < selectors.length; s++ ) {
                        var sel = stripPseudos( selectors[s].trim() );
                        if ( ! sel ) continue;
                        try {
                            if ( document.querySelector( sel ) ) {
                                recordTokens( selectors[s].trim() );
                            }
                        } catch(e) {
                            // Skip — don't record tokens from unparseable selectors.
                        }
                    }
                }
            }

            /**
             * Filter selectors for @media rules — same as getUsedSelectors
             * but with an extra hasMatchedToken fallback.
             */
            function getUsedSelectorsForMedia( selectorText ) {
                var selectors = selectorText.split( ',' );
                var used = [];
                for ( var i = 0; i < selectors.length; i++ ) {
                    var original = selectors[i].trim();

                    if ( isBlocklisted( original ) ) continue;

                    if ( isSafelisted( original ) ) {
                        used.push( original );
                        continue;
                    }

                    if ( /^::view-transition/.test( original.trim() ) ) {
                        used.push( original );
                        continue;
                    }

                    var stripped = stripPseudos( original );
                    if ( ! stripped ) continue;
                    try {
                        if ( document.querySelector( stripped ) ) {
                            used.push( original );
                        } else if ( hasPseudoElement( original ) && looseMatchPseudo( stripped ) ) {
                            used.push( original );
                        } else if ( hasMatchedToken( stripped ) ) {
                            used.push( original );
                        }
                    } catch(e) {
                        console.warn( '[NectarUsedCSS] Kept unparseable media selector:', original, e.message );
                        used.push( original );
                    }
                }
                return used;
            }

            /**
             * Main collection — single pass, in source order.
             * Non-media rules use getUsedSelectors.
             * @media rules use getUsedSelectorsForMedia (token fallback).
             * Tokens are pre-scanned so they're available for @media processing.
             */
            function collectUsed( rules ) {
                var css = '';
                for ( var i = 0; i < rules.length; i++ ) {
                    var rule = rules[i];

                    if ( rule.type === CSSRule.KEYFRAMES_RULE ) {
                        allKeyframes.push( rule );
                    } else if ( isAlwaysKeep( rule ) ) {
                        if ( rule.type === CSSRule.FONT_FACE_RULE
                            && rule.cssText.indexOf( 'swiper-icons' ) !== -1
                            && ! document.querySelector( '[class*="swiper"]' ) ) {
                            // Skip swiper-icons @font-face if no swiper on page.
                        } else {
                            css += rule.cssText;
                        }
                    } else if ( rule.type === CSSRule.STYLE_RULE ) {
                        var used = getUsedSelectors( rule.selectorText );
                        if ( used.length ) {
                            css += used.join( ',' ) + '{' + rule.style.cssText + '}';
                        }
                    } else if ( rule.type === CSSRule.MEDIA_RULE ) {
                        var inner = '';
                        var innerRules = rule.cssRules;
                        for ( var j = 0; j < innerRules.length; j++ ) {
                            var mRule = innerRules[j];
                            if ( mRule.type === CSSRule.STYLE_RULE ) {
                                var mUsed = getUsedSelectorsForMedia( mRule.selectorText );
                                if ( mUsed.length ) {
                                    inner += mUsed.join( ',' ) + '{' + mRule.style.cssText + '}';
                                }
                            } else {
                                inner += mRule.cssText;
                            }
                        }
                        if ( inner ) {
                            css += '@media ' + rule.conditionText + '{' + inner + '}';
                        }
                    }
                }
                return css;
            }

            function collectInlineStyles() {
                var css = '';
                var inlineEls = document.querySelectorAll( 'style[id$="-inline-css"]' );
                for ( var i = 0; i < inlineEls.length; i++ ) {
                    var id = inlineEls[i].id || '';
                    var handle = id.replace( '-inline-css', '' );
                    var link = document.getElementById( handle + '-css' );
                    if ( link && isThemeSheet( link ) ) {
                        try {
                            var sheet = inlineEls[i].sheet;
                            if ( sheet && sheet.cssRules ) {
                                css += collectUsed( sheet.cssRules );
                            }
                        } catch(e) {
                            css += inlineEls[i].textContent;
                        }
                    }
                }
                return css;
            }

            // --- Main ---
            // Skip if a previous save failed for this page (avoid retry loop).
            if ( sessionStorage.getItem( 'nectar_ucss_fail_' + CACHE_KEY ) ) return;

            function runAnalysis() {

                var usedCSS = '';
                var totalRules = 0;
                var usedRuleCount = 0;
                var sheetCount = 0;
                var originalBytes = 0;

                // Measure actual file sizes from Performance API.
                var perfEntries = performance.getEntriesByType( 'resource' );

                var sheets = document.styleSheets;

                // Verify all theme sheets are readable before proceeding.
                // If any sheet is cross-origin or inaccessible, abort entirely
                // rather than saving an incomplete result.
                var themeSheets = [];
                for ( var s = 0; s < sheets.length; s++ ) {
                    if ( ! isThemeSheet( sheets[s] ) ) continue;
                    try {
                        var testRules = sheets[s].cssRules;
                        if ( ! testRules ) throw new Error( 'No rules' );
                        themeSheets.push( sheets[s] );
                    } catch(e) {
                        console.error( '[NectarUsedCSS] Cannot access sheet — aborting analysis:', sheets[s].href, e.message );
                        return;
                    }
                }

                // A sheet whose fetch failed never appears in document.styleSheets,
                // so the readability loop above can't catch it. Require every theme
                // sheet PHP printed to be present before saving.
                for ( var e = 0; e < EXPECTED_SHEETS.length; e++ ) {
                    var expectedMatch = null;
                    for ( var s = 0; s < sheets.length; s++ ) {
                        if ( sheets[s].href && sheets[s].href.indexOf( EXPECTED_SHEETS[e] ) !== -1 ) {
                            expectedMatch = sheets[s];
                            break;
                        }
                    }
                    if ( ! expectedMatch ) {
                        console.error( '[NectarUsedCSS] Expected theme sheet missing — aborting analysis:', EXPECTED_SHEETS[e] );
                        return;
                    }

                    // Only the generated dynamic sheet must be non-empty. Static
                    // files can legitimately have zero rules (e.g. the comment-only
                    // parent style.css that child themes commonly enqueue), but an
                    // empty dynamic sheet means the capture caught a bad response.
                    if ( EXPECTED_SHEETS[e].indexOf( DYNAMIC_URL ) === -1 ) continue;

                    var expectedRuleCount = 0;
                    try {
                        expectedRuleCount = expectedMatch.cssRules ? expectedMatch.cssRules.length : 0;
                    } catch( err ) {
                        expectedRuleCount = 0;
                    }
                    if ( ! expectedRuleCount ) {
                        console.error( '[NectarUsedCSS] Dynamic sheet empty — aborting analysis:', EXPECTED_SHEETS[e] );
                        return;
                    }
                }

                // Pre-scan: collect matched tokens from all non-media rules
                // so they're available when processing @media in the same pass.
                for ( var s = 0; s < themeSheets.length; s++ ) {
                    prescanTokens( themeSheets[s].cssRules );
                }

                // Main pass: collect used CSS in source order.
                for ( var s = 0; s < themeSheets.length; s++ ) {
                    sheetCount++;
                    if ( themeSheets[s].href ) {
                        for ( var p = 0; p < perfEntries.length; p++ ) {
                            if ( perfEntries[p].name === themeSheets[s].href ) {
                                originalBytes += perfEntries[p].decodedBodySize || 0;
                                break;
                            }
                        }
                    }

                    var rules = themeSheets[s].cssRules;
                    totalRules += rules.length;
                    var sheetCSS = collectUsed( rules );
                    if ( themeSheets[s].href ) {
                        sheetCSS = resolveUrls( sheetCSS, themeSheets[s].href );
                    }
                    usedCSS += sheetCSS;
                }

                usedCSS += collectInlineStyles();

                // Keep only referenced @keyframes.
                for ( var k = 0; k < allKeyframes.length; k++ ) {
                    if ( usedCSS.indexOf( allKeyframes[k].name ) !== -1 ) {
                        usedCSS += allKeyframes[k].cssText;
                    }
                }

                usedRuleCount = ( usedCSS.match( /\{/g ) || [] ).length;

                var origKB = originalBytes ? (originalBytes / 1024).toFixed(1) + ' KB files' : sheetCount + ' sheets';
                console.log( '[NectarUsedCSS] ' + origKB + ' → ' + (usedCSS.length / 1024).toFixed(1) + ' KB used (' + usedRuleCount + '/' + totalRules + ' rules kept)' );

                if ( ! usedCSS ) {
                    console.warn( '[NectarUsedCSS] No used CSS found — skipping save.' );
                    return;
                }

                var formData = new FormData();
                formData.append( 'action', 'nectar_save_used_css' );
                formData.append( 'cache_key', CACHE_KEY );
                formData.append( 'nonce', NONCE );
                formData.append( 'css', usedCSS );

                fetch( AJAX_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                }).then(function( r ) { return r.json(); })
                  .then(function( data ) {
                    if ( data.success ) {
                        console.log( '[NectarUsedCSS] Saved ' + data.data.size + ' bytes for key: ' + CACHE_KEY );
                    } else {
                        console.error( '[NectarUsedCSS] Save failed:', data.data );
                        sessionStorage.setItem( 'nectar_ucss_fail_' + CACHE_KEY, '1' );
                    }
                }).catch(function( err ) {
                    console.error( '[NectarUsedCSS] Fetch error:', err );
                    sessionStorage.setItem( 'nectar_ucss_fail_' + CACHE_KEY, '1' );
                });

            }

            // Wait for the full page to be ready — including deferred JS
            // (Delay JS feature), lazy-loaded elements, and AJAX fragments.
            // Strategy: wait for window load, then an additional delay to allow
            // deferred theme JS (init.js) to execute and modify the DOM.
            function waitAndRun() {
                setTimeout( runAnalysis, 500 );
            }

            if ( document.readyState === 'complete' ) {
                waitAndRun();
            } else {
                window.addEventListener( 'load', waitAndRun );
            }

        })();
        </script>
        <?php
    }

    // ------------------------------------------------------------------
    //  AJAX handler
    // ------------------------------------------------------------------

    /**
     * Receive the used CSS from the browser and store it.
     */
    public function ajax_save() {

        $cache_key = isset( $_POST['cache_key'] ) ? sanitize_text_field( $_POST['cache_key'] ) : '';

        if ( empty( $cache_key ) ) {
            wp_send_json_error( 'Missing cache key.', 400 );
        }

        // Validate key format.
        if ( ! preg_match( '/^(post_\d+|archive_[a-f0-9]{10})$/', $cache_key ) ) {
            wp_send_json_error( 'Invalid cache key.', 400 );
        }

        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nectar_used_css_' . $cache_key ) ) {
            wp_send_json_error( 'Invalid nonce.', 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.', 403 );
        }

        $css = isset( $_POST['css'] ) ? wp_unslash( $_POST['css'] ) : '';

        if ( empty( $css ) ) {
            wp_send_json_error( 'Empty CSS.', 400 );
        }

        // Cap at 2MB to prevent disk abuse.
        if ( strlen( $css ) > 2 * 1024 * 1024 ) {
            wp_send_json_error( 'CSS too large.', 413 );
        }

        // Sanitize: strip script tags and null bytes.
        $css = preg_replace( '/<\/?script[^>]*>/i', '', $css );
        $css = str_replace( "\0", '', $css );

        // Re-encode non-ASCII characters in content values back to CSS
        // escape sequences. The browser CSSOM expands \e60a to the raw
        // unicode character, which can get mangled during transfer/save.
        $css = preg_replace_callback(
            '/content\s*:\s*(["\'])([^"\']*?)\1/i',
            function( $m ) {
                $quote = $m[1];
                $inner = $m[2];
                $escaped = preg_replace_callback(
                    '/[\x{0080}-\x{FFFF}]/u',
                    function( $c ) {
                        return '\\' . dechex( mb_ord( $c[0], 'UTF-8' ) );
                    },
                    $inner
                );
                return 'content: ' . $quote . $escaped . $quote;
            },
            $css
        );

        $this->write_cache( $cache_key, $css );

        wp_send_json_success( [ 'size' => strlen( $css ) ] );
    }

    /**
     * AJAX: Flush all used CSS caches (admin only).
     */
    public function ajax_flush() {

        if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'nectar_flush_used_css' ) ) {
            wp_send_json_error( 'Invalid nonce.', 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.', 403 );
        }

        $this->invalidate_all();

        wp_send_json_success();
    }
}

/**
 * Boot — only when enabled in Customizer > General Settings > Performance.
 */
function nectar_used_css() {
    return Nectar_Used_CSS::get_instance();
}

$nectar_used_css_options = get_nectar_theme_options();
if ( ! empty( $nectar_used_css_options['used-css-optimization'] ) && '1' === $nectar_used_css_options['used-css-optimization'] ) {
    nectar_used_css();
}
