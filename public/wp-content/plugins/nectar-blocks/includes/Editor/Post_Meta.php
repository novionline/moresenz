<?php

namespace Nectar\Editor;

use Nectar\API\Access_Utils;
use Nectar\Nectar_Templates\Nectar_Templates;
use Nectar\Global_Sections\Global_Sections;

class Post_Meta {
  private static $instance;

  public static function get_instance() {
    if ( ! isset( self::$instance ) ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct() {
    add_action( 'init', [$this, 'init'] );
    add_filter( 'rest_request_before_callbacks', [$this, 'drop_unchanged_restricted_meta'], 10, 3 );
  }

  public function init() {
    $this->register_post_options();
    $this->register_portfolio_options();
  }

  public function register_portfolio_options() {
    // nectar_portfolio is a map_meta_cap CPT (capability_type 'portfolio'), so authorize each write
    // with the post-specific edit_post meta cap against the target $post_id: WordPress maps that to
    // edit_portfolio / edit_others_portfolios per the CPT's own capabilities, rather than the
    // unmapped standard edit_others_posts primitive. Forwards the explicit $user_id WordPress passes
    // (app-password / WP-CLI --user= / user-switching) — same closure shape as $can_edit_post.
    $can_edit_portfolio = function ( $allowed, $meta_key, $post_id, $user_id ) {
      return Access_Utils::can_edit_post( $post_id, $user_id );
    };

    // Portfolio Options.
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_client', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Client Name', 'nectar-blocks'),
      'auth_callback' => $can_edit_portfolio
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_date', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Date', 'nectar-blocks'),
      'auth_callback' => $can_edit_portfolio
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_project_link', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Project Link', 'nectar-blocks'),
      'auth_callback' => $can_edit_portfolio
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_video', [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'source' => [
              'type' => 'object',
              'properties' => [
                'id' => [
                  'type' => ['integer', 'null'], // Allows undefined (null in PHP)
                ],
                'type' => [
                  'type' => 'string',
                ],
                'url' => [
                  'type' => 'string',
                ],
              ],
            ],
          ],
        ],
      ],
      'label' => __('Video', 'nectar-blocks'),
      'single' => true,
      'default' => [
        'source' => [
          'id' => null,
          'url' => '',
          'type' => 'empty'
        ],
      ],
      'type' => 'object',
      'auth_callback' => $can_edit_portfolio
    ]);

    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_description', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Description', 'nectar-blocks'),
      'auth_callback' => $can_edit_portfolio
    ]);
    register_post_meta( 'nectar_portfolio', '_nectar_portfolio_project_url', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'label' => __('Project URL', 'nectar-blocks'),
      'auth_callback' => $can_edit_portfolio
    ]);
  }

  // Note: any CPT that wants to use these will need to have custom fields enabled in the post type.
  public function register_post_options() {
    // Per-post display settings: gate on edit_post for the target post so any
    // role that can edit the post (e.g. Editor) can save these alongside other
    // meta. Bounded enums/booleans — no code-injection surface.
    $can_edit_post = function ( $allowed, $meta_key, $post_id, $user_id ) {
      return Access_Utils::can_edit_post( $post_id, $user_id );
    };

    // Code-injection meta (raw CSS/JS rendered into the page). Stays at manage_options to avoid
    // stored-XSS escalation via Editor/Author roles. Delegates to Access_Utils::can_manage_options(
    // $user_id ) — single source of truth, mirroring $can_edit_post — so it honors the explicit
    // $user_id WordPress passes (app-password / user-switching / WP-CLI) over the ambient one.
    $can_manage_options = function ( $allowed, $meta_key, $post_id, $user_id ) {
      return Access_Utils::can_manage_options( $user_id );
    };

    // Global-section assignment (locations/conditions). Bounded arrays/enums, not a
    // code-injection surface, so the capability is filterable — admin-only by default.
    $can_assign_global_sections = function ( $allowed, $meta_key, $post_id, $user_id ) {
      $capability = Global_Sections::assign_capability();
      if ( null !== $user_id ) {
        return user_can( $user_id, $capability );
      }
      return is_user_logged_in() && current_user_can( $capability );
    };

    // Hide post title.
    register_post_meta( '', '_nectar_blocks_hide_post_title', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => $can_edit_post
    ]);

    // Transparent Header Effect.
    register_post_meta( '', '_nectar_blocks_transparent_header_effect', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => $can_edit_post
    ]);

    // Transparent Header Effect Color.
    register_post_meta( '', '_nectar_blocks_transparent_header_effect_color', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 'light',
      'type' => 'string',
      'auth_callback' => $can_edit_post,
      // Bound to the light|dark enum the UI offers (PostOptions.tsx HeaderColorList). The render
      // side already whitelists the value; this makes the stored value trustworthy too.
      'sanitize_callback' => function ( $value ) { return in_array( $value, [ 'light', 'dark' ], true ) ? $value : 'light'; }
    ]);

    // Header Animation.
    register_post_meta( '', '_nectar_blocks_header_animation', [
      'show_in_rest' => true,
      'single' => true,
      'default' => false,
      'type' => 'boolean',
      'auth_callback' => $can_edit_post
    ]);

    register_post_meta( '', '_nectar_blocks_header_animation_delay', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 0,
      'type' => 'number',
      'auth_callback' => $can_edit_post,
      // Clamp to the slider's range (seconds, fractional). Keep it a float — the value is rendered
      // as `animation-delay: {n}s`, so an int cast would drop the 0.05-step fractional delays.
      'sanitize_callback' => function ( $value ) { return max( 0, min( 2, (float) $value ) ); }
    ]);

    // Header Animation Effect.
    register_post_meta( '', '_nectar_blocks_header_animation_effect', [
      'show_in_rest' => true,
      'single' => true,
      'default' => 'fade',
      'type' => 'string',
      'auth_callback' => $can_edit_post,
      // Bound to the fade|slide enum the UI offers (PostOptions.tsx HeaderAnimationList). The render
      // side already whitelists the value; this makes the stored value trustworthy too.
      'sanitize_callback' => function ( $value ) { return in_array( $value, [ 'fade', 'slide' ], true ) ? $value : 'fade'; }
    ]);

    // Page CSS.
    register_post_meta( '', '_nectar_blocks_page_css', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'auth_callback' => $can_manage_options
    ]);
    self::register_restricted_key( '_nectar_blocks_page_css' );

    // Page JS.
    register_post_meta( '', '_nectar_blocks_page_js', [
      'show_in_rest' => true,
      'single' => true,
      'default' => '',
      'type' => 'string',
      'auth_callback' => $can_manage_options
    ]);
    self::register_restricted_key( '_nectar_blocks_page_js' );

    // Global Section Options.
    register_post_meta( Global_Sections::POST_TYPE, Global_Sections::META_KEY, [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            // Shape written by NectarGlobalSections/LocationsList.tsx: { key, priority, location }.
            // Closed so a REST write can't smuggle extra keys into the meta the front end renders.
            'locations' => [
              'type' => 'array',
              'items' => [
                'type' => 'object',
                'properties' => [
                  'key' => [ 'type' => 'string' ],
                  // `number`, not `integer`: the priority input accepts a decimal and WP would
                  // 400 the whole post save on one. Render casts to int at add_action() time.
                  'priority' => [ 'type' => 'number' ],
                  'location' => [ 'type' => 'string' ],
                ],
                'additionalProperties' => false,
              ],
            ],
            'operator' => [
              'type' => 'string',
            ],
            // Left open: conditions carry optional postData/taxonomyTermData payloads whose
            // shape varies by condition type (and by what the entity endpoints return), and
            // WP rejects the whole write on an unknown key. The render side whitelists every
            // condition value it acts on (Render::parse_conditional).
            // `additionalProperties` must be set EXPLICITLY: WP_REST_Meta_Fields runs meta
            // schemas through rest_default_additional_properties_to_false(), so an object
            // schema that omits it is closed, not open.
            'conditions' => [
              'type' => 'array',
              'items' => [
                'type' => 'object',
                'properties' => [
                  'key' => [ 'type' => 'string' ],
                  'include' => [ 'type' => 'boolean' ],
                  'condition' => [ 'type' => 'string' ],
                ],
                'additionalProperties' => true,
              ],
            ],
          ],
        ],
      ],
      'single' => true,
      'default' => Global_Sections::defaults(),
      'type' => 'object',
      'auth_callback' => $can_assign_global_sections
    ]);
    self::register_restricted_key( Global_Sections::META_KEY );

    // Template Part Options.
    register_post_meta( Nectar_Templates::POST_TYPE, Nectar_Templates::META_KEY, [
      'show_in_rest' => [
        'schema' => [
          'type' => 'object',
          'properties' => [
            'templatePart' => [
              'type' => 'string',
            ],
            'operator' => [
              'type' => 'string',
            ],
            'conditions' => [
              'type' => 'array',
            ],
          ],
        ],
      ],
      'single' => true,
      'default' => Nectar_Templates::defaults(),
      'type' => 'object',
      'auth_callback' => $can_manage_options
    ]);
    self::register_restricted_key( Nectar_Templates::META_KEY );
  }

  /**
   * Meta keys this plugin registers whose write capability is narrower than edit_post.
   *
   * Populated by register_restricted_key() from the registration sites themselves rather than
   * held as a literal list here: a hardcoded list drifted the moment another file registered a
   * restricted key (the three _nectar_header_* metas in Nectar_Templates_Register), silently
   * reintroducing the 403 this filter exists to prevent. Keyed by meta key so the hot-path
   * array_intersect_key() needs no array_flip().
   *
   * Only the keys live here, never the capability: authority is read back from the registration
   * via current_user_can( 'edit_post_meta', … ), the same expression
   * WP_REST_Meta_Fields::update_meta_value() uses to reject the write. The two therefore cannot
   * drift, and subtype-scoped keys stay scoped to their post type.
   * @since 3.2.0
   * @var array<string,true>
   */
  private static $restricted_meta_keys = [];

  /**
   * Declare a meta key whose registered auth_callback is narrower than edit_post.
   *
   * Call immediately after the register_post_meta() it describes. Registration runs on `init`
   * and this filter at REST dispatch, so every site's keys are present by the time it reads
   * them, wherever in the plugin they are registered.
   * @since 3.2.0
   * @param string $meta_key
   * @return void
   */
  public static function register_restricted_key( string $meta_key ): void {
    self::$restricted_meta_keys[$meta_key] = true;
  }

  /**
   * The restricted meta keys registered so far.
   * @since 3.2.0
   * @return list<string>
   */
  public static function restricted_meta_keys(): array {
    return array_keys( self::$restricted_meta_keys );
  }

  /**
   * Drop restricted meta the caller cannot write when the submitted value matches what is
   * already effective.
   *
   * The block editor's postType entity declares `mergedEdits: { meta: true }`, so editing one
   * meta key submits the whole merged meta bag — including keys the caller has no business
   * writing and never touched. WordPress skips the capability check for an unchanged value
   * only when a meta row already exists: update_meta_value() compares against
   * get_metadata_raw(), which ignores registered defaults. A key that has never been written
   * therefore fails the whole request, which blocks e.g. an Editor from saving a global
   * section's locations or a page's hide-title toggle.
   *
   * Comparing against get_post_meta() instead counts the registered default as the current
   * value, restoring what core's own duplicate-value check was reaching for. A real change
   * still falls through to the meta auth_callback and errors as before, so this can only ever
   * subtract from a write, never grant one.
   *
   * Runs on rest_request_before_callbacks — after core validates and sanitizes params, before
   * the route's permission callback.
   * @since 3.2.0
   * @param mixed $response
   * @param array $handler
   * @param \WP_REST_Request $request
   * @return mixed
   */
  public function drop_unchanged_restricted_meta( $response, $handler, $request ) {
    if ( is_wp_error( $response ) || ! $request instanceof \WP_REST_Request ) {
      return $response;
    }

    if ( ! in_array( $request->get_method(), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
      return $response;
    }

    // Cheapest discriminator first: this filter sees every REST write, and anything not
    // submitting one of our restricted keys leaves here before any database access.
    $meta = $request->get_param( 'meta' );
    if ( ! is_array( $meta ) ) {
      return $response;
    }
    $candidates = array_intersect_key( $meta, self::$restricted_meta_keys );
    if ( ! $candidates ) {
      return $response;
    }

    $post_id = (int) $request->get_param( 'id' );
    if ( ! $post_id || ! get_post( $post_id ) ) {
      return $response;
    }

    $stripped = $meta;
    foreach ( $candidates as $meta_key => $submitted ) {
      if ( ! current_user_can( 'edit_post_meta', $post_id, $meta_key )
        && self::is_meta_value_unchanged( $post_id, $meta_key, $submitted )
      ) {
        unset( $stripped[$meta_key] );
      }
    }

    if ( $stripped !== $meta ) {
      $request->set_param( 'meta', $stripped );
    }

    return $response;
  }

  /**
   * Whether a submitted meta value matches the one already effective for the post.
   *
   * Mirrors WP_REST_Meta_Fields::is_meta_value_same_as_stored_value(): sanitize before
   * comparing, then compare scalars as strings, because get_post_meta() returns stored scalars
   * as strings. Two deliberate deviations, both of which can only ever drop one more no-op
   * write — neither can permit one, since this comparison's only effect is to remove a key from
   * a request the caller is not allowed to make.
   *
   * 1. Object metas compare key-order-insensitively. rest_sanitize_value_from_schema() iterates
   *    the submitted object and assigns in place, so the request's key order survives into the
   *    value we see and can differ from the stored or default order for an identical value.
   * 2. The STORED side is sanitized too, not just the submitted one. Core compares against a
   *    real meta row, which was written post-sanitize; we compare against get_post_meta(), which
   *    for a never-written key yields the raw registered default — a value that has never been
   *    through the key's sanitize_callback. _nectar_header_state_transition registers
   *    `new \stdClass()` and sanitizes to `[ 'durationSec' => 0.0, 'bezier' => [] ]`, so an
   *    untouched default submitted back would read as a change and 403 the save. Sanitizing both
   *    sides puts the default in the same shape the submission arrives in. The plugin's
   *    sanitize callbacks are idempotent, so this is a no-op for keys that do have a row.
   * @since 3.2.0
   * @param int $post_id
   * @param string $meta_key
   * @param mixed $submitted
   * @return bool
   */
  private static function is_meta_value_unchanged( int $post_id, string $meta_key, $submitted ): bool {
    $subtype = get_object_subtype( 'post', $post_id );
    $stored = sanitize_meta( $meta_key, get_post_meta( $post_id, $meta_key, true ), 'post', $subtype );
    $submitted = sanitize_meta( $meta_key, $submitted, 'post', $subtype );

    $stored = self::canonicalize_meta_value( $stored );
    $submitted = self::canonicalize_meta_value( $submitted );

    if ( is_array( $stored ) && is_array( $submitted ) ) {
      return $stored === $submitted;
    }
    if ( is_scalar( $stored ) && is_scalar( $submitted ) ) {
      return (string) $stored === (string) $submitted;
    }
    return $stored === $submitted;
  }

  /**
   * Normalize a meta value for comparison: cast stdClass to an array and recursively sort array
   * keys, so two structurally equal values compare identical.
   *
   * The stdClass cast matters because a registered `'default' => new \stdClass()` (the shape
   * WordPress needs to serialize an empty object meta as `{}` rather than `[]`) comes back from
   * get_post_meta() as an object, while the same value submitted through REST arrives as an
   * array. Without this they can never compare equal, whatever their contents.
   * @since 3.2.0
   * @param mixed $value
   * @return mixed
   */
  private static function canonicalize_meta_value( $value ) {
    if ( $value instanceof \stdClass ) {
      $value = (array) $value;
    }
    if ( ! is_array( $value ) ) {
      return $value;
    }
    ksort( $value );
    foreach ( $value as $key => $item ) {
      $value[$key] = self::canonicalize_meta_value( $item );
    }
    return $value;
  }
}
