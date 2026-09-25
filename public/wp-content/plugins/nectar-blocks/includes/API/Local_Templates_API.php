<?php

namespace Nectar\API;

/**
 * Local Templates API
 *
 * Dev convenience: serves the Nectarblocks template library directly from a
 * local directory of `metadata.json` + `template.html` files instead of the
 * remote Nectarblocks API. Completely dormant unless explicitly enabled via
 * the `nectar_blocks_dev` config filter.
 *
 * See `backend/template-library/readme.md` for setup instructions.
 */
class Local_Templates_API {
  public const ROUTE = 'local_templates';

  public const DEFAULT_VERSION = '2.0.0';

  public const PER_PAGE = 20;

  /**
   * Backwards compatibility: v3 renamed/split several category slugs. Older
   * template versions (1.x, 2.x) still carry the legacy slugs, so a request
   * for a new-UI category must also match its legacy equivalent — otherwise
   * those categories render empty when an older version is loaded.
   *
   * Keyed by the current UI slug → legacy slugs that should also match.
   */
  private const LEGACY_CATEGORY_ALIASES = [
    'hero-section' => [ 'intro' ],
    'testimonials' => [ 'quotes' ],
    'blog' => [ 'query' ],
    'gallery' => [ 'media' ],
    'video' => [ 'media' ],
  ];

  /** @var string Absolute path to the templates root directory. */
  private $templates_root;

  /** @var string Version subdirectory to scan. */
  private $version;

  public function __construct( $templates_root, $version = self::DEFAULT_VERSION ) {
    $this->templates_root = rtrim( $templates_root, '/' );
    $this->version = $version;
  }

  /**
   * Reads the `nectar_blocks_dev` filter and, if `localTemplates` is configured
   * with a `path`, returns a configured instance. Returns null otherwise.
   */
  public static function from_filter() {
    $dev = apply_filters( 'nectar_blocks_dev', [] );
    if ( ! \is_array( $dev ) || empty( $dev['localTemplates'] ) ) {
      return null;
    }

    $config = $dev['localTemplates'];
    if ( ! \is_array( $config ) || empty( $config['path'] ) ) {
      return null;
    }

    return new self( $config['path'], $config['version'] ?? self::DEFAULT_VERSION );
  }

  /**
   * Self-registers the REST route + the editor window flag that flips
   * `TemplatesPanel` onto the local source.
   */
  public function register() {
    add_action( 'rest_api_init', [ $this, 'build_routes' ] );
    add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_flag' ] );
  }

  public function build_routes() {
    register_rest_route( 'nectar/v1', '/' . self::ROUTE, [
      'callback' => [ $this, 'get_local_templates' ],
      'methods' => 'POST',
      'permission_callback' => function() {
        return current_user_can( 'edit_posts' );
      },
    ] );
  }

  /**
   * Sets `window.nectarLocalTemplatesEnabled = true` in the editor so
   * `TemplatesPanel` knows to use the local route. The version is bound to
   * this instance server-side and never round-trips through the editor.
   */
  public function enqueue_editor_flag() {
    wp_add_inline_script( 'wp-hooks', 'window.nectarLocalTemplatesEnabled = true;' );
  }

  public function get_local_templates( \WP_REST_Request $request ) {
    $json_body = $request->get_json_params();
    $page = isset( $json_body['page'] ) ? (int) $json_body['page'] : 0;
    // get_json_params() returns raw json_decode output with no coercion, so a
    // non-string value (e.g. {"category": []}) would TypeError when used as an
    // array key / by strtolower() on PHP 8+. Normalise to strings up front.
    $category = $json_body['category'] ?? '';
    $category = is_string( $category ) ? $category : '';
    $search_string = $json_body['s'] ?? '';
    $search_string = strtolower( is_string( $search_string ) ? $search_string : '' );

    $version_dir = $this->templates_root . '/' . $this->version;
    if ( ! is_dir( $version_dir ) ) {
      return new \WP_REST_Response( [
        'status' => 'failure',
        'message' => 'Local template directory not found: ' . $version_dir,
      ], 404 );
    }

    $templates = $this->scan_templates( $version_dir );

    // Filter by category + search.
    $filtered = [];
    foreach ( $templates as $template ) {
      if ( $category !== 'all' && $category !== '' && ! $this->category_matches( $category, $template['categories'] ) ) {
        continue;
      }
      if ( $search_string !== '' && strpos( strtolower( $template['title'] ), $search_string ) === false ) {
        continue;
      }
      $filtered[] = $template;
    }

    // Sort: newest first, then highest priority. `_ts` is precomputed in
    // scan_templates so we don't re-parse dates on every comparison.
    usort( $filtered, function( $a, $b ) {
      return ( $b['_ts'] <=> $a['_ts'] ) ?: ( $b['priority'] <=> $a['priority'] );
    } );

    // Strip the sort-only key from the wire payload.
    foreach ( $filtered as &$t ) {
      unset( $t['_ts'] );
    }
    unset( $t );

    $start = \min( $page * self::PER_PAGE, \count( $filtered ) );
    $paged = \array_slice( $filtered, $start, self::PER_PAGE );

    return new \WP_REST_Response( [
      'status' => 'success',
      'data' => $paged,
    ], 200 );
  }

  /**
   * Whether a template belongs to the requested category, honouring the
   * legacy-slug aliases so older template versions still appear under the
   * current UI categories.
   */
  private function category_matches( $category, $template_categories ) {
    if ( \in_array( $category, $template_categories, true ) ) {
      return true;
    }
    foreach ( self::LEGACY_CATEGORY_ALIASES[$category] ?? [] as $legacy_slug ) {
      if ( \in_array( $legacy_slug, $template_categories, true ) ) {
        return true;
      }
    }
    return false;
  }

  private function scan_templates( $version_dir ) {
    $templates = [];
    $entries = scandir( $version_dir );
    if ( $entries === false ) {
      return $templates;
    }

    foreach ( $entries as $entry ) {
      if ( $entry === '.' || $entry === '..' ) {
        continue;
      }
      $template_dir = $version_dir . '/' . $entry;
      if ( ! is_dir( $template_dir ) ) {
        continue;
      }

      $metadata_path = $template_dir . '/metadata.json';
      $template_path = $template_dir . '/template.html';
      if ( ! file_exists( $metadata_path ) || ! file_exists( $template_path ) ) {
        continue;
      }

      $metadata = wp_json_file_decode( $metadata_path, [ 'associative' => true ] );
      if ( ! \is_array( $metadata ) ) {
        continue;
      }

      $date_added = $metadata['dateAdded'] ?? '';
      $template = [
        'title' => $metadata['title'] ?? $entry,
        'categories' => $metadata['categories'] ?? [],
        'dateAdded' => $date_added,
        'priority' => (int) ( $metadata['priority'] ?? 0 ),
        '_ts' => $date_added ? (int) strtotime( $date_added ) : 0,
        'data' => file_get_contents( $template_path ),
        'previewData' => null,
        'colorPaletteTransforms' => null,
      ];

      $preview_path = $template_dir . '/preview-template.html';
      if ( file_exists( $preview_path ) ) {
        $template['previewData'] = file_get_contents( $preview_path );
      }

      $transforms_path = $template_dir . '/colorPaletteTransforms.json';
      if ( file_exists( $transforms_path ) ) {
        $transforms = wp_json_file_decode( $transforms_path, [ 'associative' => true ] );
        if ( \is_array( $transforms ) ) {
          $template['colorPaletteTransforms'] = $transforms;
        }
      }

      $templates[] = $template;
    }

    return $templates;
  }
}
