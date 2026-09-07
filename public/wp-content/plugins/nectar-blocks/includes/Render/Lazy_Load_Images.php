<?php

/**
 * Nectar Blocks Lazy Load Images
 *
 * Defers off-screen images by swapping src/srcset to data attributes
 * and restoring them via IntersectionObserver on the frontend.
 *
 * Above-the-fold detection uses multiple layers:
 * 1. Semantic HTML5 landmarks (<header>, <footer>) are fully excluded —
 *    structural site framing that should always load eagerly.
 * 2. The first nectar-blocks-row on the page (any render tag) is fully
 *    excluded — typically a hero / header-navigation section.
 * 3. A count-based threshold (matching WordPress core's strategy) skips
 *    the first N remaining images outside those protected regions.
 *
 * @package Nectar\Render
 * @since 3.0
 */

namespace Nectar\Render;

use Nectar\Global_Settings\Nectar_Plugin_Options;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class Lazy_Load_Images {
  /**
   * Default number of images at the top of the document assumed visible
   * in the initial viewport. Matches the WordPress core default.
   * Filterable via `nectarblocks_lazy_load_threshold`.
   */
  private const ABOVE_FOLD_THRESHOLD = 3;

  /**
   * HTML5 landmark elements whose images should never be deferred.
   * These represent structural site chrome (header, footer) that is
   * visible on every page load.
   *
   * `<nav>` is intentionally NOT included here. Site-chrome navs live
   * inside `<header>`/`<footer>` and are covered by those protections,
   * and content-level navs (nectar-blocks-toc, pagination, breadcrumbs)
   * should be free to lazy-load their images. Salient's header template
   * renders as a `<nav class="nectar-blocks-row">`; that's handled by
   * protect_first_row(), which matches any tag with the row class.
   */
  private const PROTECTED_ELEMENTS = [ 'header', 'footer' ];

  public function __construct() {
    if ( ! self::should_run() ) {
      return;
    }

    add_action( 'template_redirect', [ $this, 'start_buffering' ] );
    add_action( 'wp_head', [ $this, 'inline_css' ], 4 );
    add_filter( 'nectarblocks_lazy_load_threshold', [ $this, 'wc_archive_threshold' ] );
  }

  /**
   * Whether the feature is enabled in plugin settings.
   */
  public static function is_enabled(): bool {
    $options = Nectar_Plugin_Options::get_options();
    return ! empty( $options['lazyLoadImages'] );
  }

  /**
   * Guard that combines the option check with environment checks.
   */
  private static function should_run(): bool {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
      return false;
    }

    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
      return false;
    }

    if ( defined( 'WP_CLI' ) && WP_CLI ) {
      return false;
    }

    return self::is_enabled();
  }

  /**
   * Number of images to skip at the top of the page.
   * Filterable so themes can adjust for hero-heavy layouts.
   */
  private static function get_threshold(): int {
    return (int) apply_filters( 'nectarblocks_lazy_load_threshold', self::ABOVE_FOLD_THRESHOLD );
  }

  /**
   * WooCommerce archive pages — eagerly load the first row of product
   * images. When using the Nectar theme, reads the desktop column count
   * from the customizer; otherwise falls back to 6.
   */
  public function wc_archive_threshold( int $threshold ): int {
    if ( ! function_exists( 'is_shop' ) ) {
      return $threshold;
    }

    // Single product — the gallery is fully protected so everything
    // else on the page can be deferred immediately.
    if ( is_product() ) {
      return 0;
    }

    if ( ! is_shop() && ! is_product_taxonomy() ) {
      return $threshold;
    }

    if ( function_exists( 'get_nectar_theme_options' ) ) {
      $options = get_nectar_theme_options();
      $cols = ! empty( $options['product_desktop_cols'] ) ? $options['product_desktop_cols'] : 'default';

      $map = [
        '1' => 1,
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '6' => 6,
      ];

      return $map[$cols] ?? 4;
    }

    return 6;
  }

  /**
   * Start output buffering so we can rewrite <img> tags before
   * the response is sent to the browser.
   */
  public function start_buffering(): void {
    if ( is_feed() || is_robots() || is_preview() ) {
      return;
    }

    ob_start( [ $this, 'process_html' ] );
  }

  /**
   * Emit the minimal CSS needed for the fade-in transition.
   */
  public function inline_css(): void {
    // min-width/min-height ensures lazy images always have non-zero
    // geometric size so IntersectionObserver can fire. Without this,
    // flex children with max-width:100% and a tiny placeholder collapse
    // to 0×0 and never trigger the observer. CSS spec: min-* wins over
    // max-* in a conflict, so this overrides any max-width:100% rule.
    echo '<style id="nectarblocks-lazy-images">'
      . '.nectarblocks-lazy{opacity:0;min-width:1px;min-height:1px;transition:opacity 150ms ease;}'
      . '.nectarblocks-lazy.nectarblocks-loaded{opacity:1;}'
      . '</style>';
  }

  // ------------------------------------------------------------------
  // HTML processing
  // ------------------------------------------------------------------

  /**
   * Output-buffer callback. Receives the full page HTML and returns
   * the modified version with below-fold images deferred.
   */
  public function process_html( string $html ): string {
    if ( empty( $html ) ) {
      return $html;
    }

    // Only touch actual HTML pages, not JSON / XML / etc.
    if ( stripos( $html, '<html' ) === false && stripos( $html, '<!DOCTYPE' ) === false ) {
      return $html;
    }

    $image_count = 0;
    $protected_tokens = [];

    // Temporarily remove <noscript> blocks so we don't modify their
    // images — those serve as a no-JS fallback from WordPress core.
    $html = preg_replace_callback(
        '/<noscript\b[^>]*>.*?<\/noscript>/si',
        function ( $match ) use ( &$protected_tokens ) {
        $token = '<!--NBLAZY_P_' . count( $protected_tokens ) . '-->';
        $protected_tokens[$token] = $match[0];
        return $token;
      },
        $html
    ) ?? $html;

    // Protect the WordPress admin bar.
    $html = $this->protect_by_id( $html, 'div', 'wpadminbar', $protected_tokens );

    // Protect semantic HTML5 landmark elements — these are structural
    // chrome (navigation, site header, footer) that should always load
    // eagerly. Works across themes since virtually all modern WordPress
    // themes use these landmarks for accessibility.
    foreach ( self::PROTECTED_ELEMENTS as $el ) {
      $html = $this->protect_element( $html, $el, $protected_tokens );
    }

    // Protect the first nectar-blocks-row — it's typically the hero /
    // above-fold section and may contain many heavy images that should
    // all load eagerly regardless of the numeric threshold.
    $html = $this->protect_first_row( $html, $protected_tokens );

    // Protect the WooCommerce product gallery on single product pages.
    // These images are used in a slider/zoom UI and must load eagerly.
    $html = $this->protect_product_gallery( $html, $protected_tokens );

    // Process <img> tags and .nectar__bg-image divs in a single pass
    // so they share the same above-fold counter. This guarantees the
    // first N media items (mixed images + backgrounds) load eagerly,
    // matching their document order regardless of element type.
    $html = preg_replace_callback(
        '/(<img\b[^>]*>)|(<div\b[^>]*\bnectar__bg-image\b[^>]*>)/i',
        function ( $match ) use ( &$image_count ) {
        if ( ! empty( $match[1] ) ) {
          return $this->maybe_defer_image( $match[1], $image_count );
        }
        return $this->maybe_defer_bg( $match[2], $image_count );
      },
        $html
    ) ?? $html;

    // Restore all protected regions.
    //
    // Loop until stable. str_replace() with array arguments processes each
    // key/value pair in order and never re-scans after a later replacement
    // exposes an earlier key. When one protected region is nested inside
    // another (e.g. a <nav> inside the first nectar-blocks-row), the inner
    // token is stored inside the outer token's value; by the time the outer
    // token is restored, str_replace has already moved past the inner key.
    // Iterating until the HTML stops changing resolves any nesting depth.
    if ( ! empty( $protected_tokens ) ) {
      $keys = array_keys( $protected_tokens );
      $values = array_values( $protected_tokens );
      $prev = null;
      $iterations = 0;
      while ( $prev !== $html && $iterations < 10 ) {
        $prev = $html;
        $html = str_replace( $keys, $values, $html );
        $iterations++;
      }
    }

    // Force lazy-load on hidden nav content. This runs AFTER token
    // restoration so the nav HTML is back in place — no nesting issues.
    // These are hidden dropdowns whose images should always be deferred
    // and never count toward the above-fold threshold.
    $html = $this->force_lazy_in_elements( $html, 'div', 'nectar-global-section-megamenu' );
    $html = $this->force_lazy_in_elements( $html, 'ul', 'sub-menu' );

    return $html;
  }

  /**
   * Protect ALL occurrences of a semantic HTML element (e.g. header,
   * nav, footer) by replacing each with a placeholder token.
   */
  private function protect_element( string $html, string $tag_name, array &$tokens ): string {
    $tag_len = strlen( $tag_name );

    while ( true ) {
      $result = $this->extract_element( $html, $tag_name, $tag_len, "<{$tag_name}", false );
      if ( $result === null ) {
        break;
      }

      [ $start, $end ] = $result;
      $fragment = substr( $html, $start, $end - $start );
      $token = '<!--NBLAZY_P_' . count( $tokens ) . '-->';
      $tokens[$token] = $fragment;
      $html = substr( $html, 0, $start ) . $token . substr( $html, $end );
    }

    return $html;
  }

  /**
   * Protect a single element matched by ID attribute.
   */
  private function protect_by_id( string $html, string $tag_name, string $id, array &$tokens ): string {
    $result = $this->extract_element( $html, $tag_name, strlen( $tag_name ), "<{$tag_name}", true, $id );
    if ( $result === null ) {
      return $html;
    }

    [ $start, $end ] = $result;
    $fragment = substr( $html, $start, $end - $start );
    $token = '<!--NBLAZY_P_' . count( $tokens ) . '-->';
    $tokens[$token] = $fragment;

    return substr( $html, 0, $start ) . $token . substr( $html, $end );
  }

  /**
   * Find the first .nectar-blocks-row on the page and replace it with
   * a placeholder token so its images are never deferred.
   *
   * Rows can be rendered with any HTML tag via the block's renderTag
   * attribute (div, nav, section, aside, etc.), so we first detect the
   * tag used by the first occurrence of the class, then extract by that
   * tag.
   */
  private function protect_first_row( string $html, array &$tokens ): string {
    if ( ! preg_match(
        '/<([a-z][a-z0-9]*)\b[^>]*\bnectar-blocks-row\b[^>]*>/i',
        $html,
        $match
    ) ) {
      return $html;
    }

    $tag_name = strtolower( $match[1] );
    $result = $this->extract_element(
        $html,
        $tag_name,
        strlen( $tag_name ),
        '<' . $tag_name,
        true,
        'nectar-blocks-row'
    );
    if ( $result === null ) {
      return $html;
    }

    [ $start, $end ] = $result;
    $fragment = substr( $html, $start, $end - $start );
    $token = '<!--NBLAZY_P_' . count( $tokens ) . '-->';
    $tokens[$token] = $fragment;

    return substr( $html, 0, $start ) . $token . substr( $html, $end );
  }

  /**
   * Protect the WooCommerce product gallery on single product pages.
   * The gallery uses a slider/zoom so all its images must load eagerly.
   */
  private function protect_product_gallery( string $html, array &$tokens ): string {
    $result = $this->extract_element( $html, 'div', 3, '<div', true, 'woocommerce-product-gallery' );
    if ( $result === null ) {
      return $html;
    }

    [ $start, $end ] = $result;
    $fragment = substr( $html, $start, $end - $start );
    $token = '<!--NBLAZY_P_' . count( $tokens ) . '-->';
    $tokens[$token] = $fragment;

    return substr( $html, 0, $start ) . $token . substr( $html, $end );
  }

  /**
   * Force lazy-load on all images and backgrounds inside every
   * occurrence of a given element+class combination.
   *
   * Runs on the final restored HTML — no token nesting issues.
   * Uses a search offset to advance past each processed element.
   *
   * @param string $html       Full page HTML.
   * @param string $tag_name   Tag name to search for (e.g. 'div', 'ul').
   * @param string $class_name CSS class the element must have.
   */
  private function force_lazy_in_elements( string $html, string $tag_name, string $class_name ): string {
    $pattern = '/<' . $tag_name . '\b[^>]*\b' . preg_quote( $class_name, '/' ) . '\b[^>]*>/i';
    $offset = 0;

    while ( preg_match( $pattern, $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
      $open_tag = $m[0][0];
      $start = $m[0][1];
      $after_open = $start + strlen( $open_tag );

      $end = $this->find_closing_tag( $html, $tag_name, $after_open );
      if ( $end === null ) {
        break;
      }

      $fragment = substr( $html, $start, $end - $start );

      // Force defer every <img> inside (no threshold).
      $skip_threshold = self::ABOVE_FOLD_THRESHOLD + 1;
      $fragment = preg_replace_callback(
          '/<img\b[^>]*>/i',
          function ( $match ) use ( &$skip_threshold ) {
          return $this->maybe_defer_image( $match[0], $skip_threshold );
        },
          $fragment
      ) ?? $fragment;

      // Force .lazy-load-bg on block backgrounds.
      $fragment = preg_replace_callback(
          '/<div\b[^>]*\bnectar__bg-image\b[^>]*>/i',
          function ( $match ) {
          $tag = $match[0];
          if ( strpos( $tag, 'lazy-load-bg' ) !== false ) {
            return $tag;
          }
          return preg_replace(
              '/(?<=\s)(class\s*=\s*["\'])/',
              '${1}lazy-load-bg ',
              $tag
          );
        },
          $fragment
      ) ?? $fragment;

      $html = substr( $html, 0, $start ) . $fragment . substr( $html, $end );
      $offset = $start + strlen( $fragment );
    }

    return $html;
  }

  /**
   * From a position just after an opening tag, track depth to find the
   * matching closing tag. Returns the position after the closing '>'.
   */
  private function find_closing_tag( string $html, string $tag_name, int $from ): ?int {
    $open_needle = '<' . $tag_name;
    $close_needle = '</' . $tag_name;
    $tag_len = strlen( $tag_name );
    $depth = 1;
    $pos = $from;
    $len = strlen( $html );

    while ( $pos < $len && $depth > 0 ) {
      $next_open = stripos( $html, $open_needle, $pos );
      $next_close = stripos( $html, $close_needle, $pos );

      if ( $next_close === false ) {
        return null;
      }

      if ( $next_open !== false && $next_open < $next_close ) {
        $char_after = $html[$next_open + $tag_len + 1] ?? '';
        if ( $char_after === ' ' || $char_after === '>' || $char_after === '/' || $char_after === "\t" || $char_after === "\n" ) {
          $depth++;
        }
        $pos = $next_open + $tag_len + 1;
      } else {
        $close_char_after = $html[$next_close + $tag_len + 2] ?? '';
        if ( $close_char_after === '>' || $close_char_after === ' ' || $close_char_after === "\t" || $close_char_after === "\n" ) {
          $depth--;
          if ( $depth === 0 ) {
            $end_tag_close = strpos( $html, '>', $next_close );
            return $end_tag_close !== false ? $end_tag_close + 1 : null;
          }
        }
        $pos = $next_close + $tag_len + 2;
      }
    }

    return null;
  }

  /**
   * Locate a complete element in the HTML by tracking open/close depth
   * of the given tag name. Returns [ $start_pos, $end_pos ] or null.
   *
   * @param string      $html       The HTML to search.
   * @param string      $tag_name   Tag name (e.g. 'header', 'div').
   * @param int         $tag_len    strlen( $tag_name ) — passed to avoid recalculating.
   * @param string      $open_needle Lowercase opening needle (e.g. '<header', '<div').
   * @param bool        $require_class When true, the opening tag must contain $class_name.
   * @param string|null $class_name CSS class the opening tag must contain.
   * @return array{int,int}|null
   */
  private function extract_element(
      string $html,
      string $tag_name,
      int $tag_len,
      string $open_needle,
      bool $require_class = false,
      ?string $class_name = null
  ): ?array {
    if ( $require_class && $class_name ) {
      $pattern = '/<' . $tag_name . '\b[^>]*\b' . preg_quote( $class_name, '/' ) . '\b[^>]*>/i';
      if ( ! preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
        return null;
      }
      $start_pos = $match[0][1];
      $after_open = $start_pos + strlen( $match[0][0] );
    } else {
      // Find the first occurrence of the opening tag.
      $pos = stripos( $html, $open_needle );
      if ( $pos === false ) {
        return null;
      }
      // Verify the character after the tag name is valid (space, >, /).
      $char_after = $html[$pos + $tag_len + 1] ?? '';
      if ( $char_after !== ' ' && $char_after !== '>' && $char_after !== '/' && $char_after !== "\t" && $char_after !== "\n" ) {
        return null;
      }
      $start_pos = $pos;
      $end_of_open = strpos( $html, '>', $start_pos );
      if ( $end_of_open === false ) {
        return null;
      }
      $after_open = $end_of_open + 1;
    }

    $close_needle = '</' . $tag_name;
    $depth = 1;
    $pos = $after_open;
    $len = strlen( $html );

    while ( $pos < $len && $depth > 0 ) {
      $next_open = stripos( $html, $open_needle, $pos );
      $next_close = stripos( $html, $close_needle, $pos );

      if ( $next_close === false ) {
        return null; // Malformed HTML.
      }

      if ( $next_open !== false && $next_open < $next_close ) {
        $char_after = $html[$next_open + $tag_len + 1] ?? '';
        if ( $char_after === ' ' || $char_after === '>' || $char_after === '/' || $char_after === "\t" || $char_after === "\n" ) {
          $depth++;
        }
        $pos = $next_open + $tag_len + 1;
      } else {
        $close_char_after = $html[$next_close + $tag_len + 2] ?? '';
        if ( $close_char_after === '>' || $close_char_after === ' ' || $close_char_after === "\t" || $close_char_after === "\n" ) {
          $depth--;
          if ( $depth === 0 ) {
            $end_tag_close = strpos( $html, '>', $next_close );
            if ( $end_tag_close === false ) {
              return null;
            }
            return [ $start_pos, $end_tag_close + 1 ];
          }
        }
        $pos = $next_close + $tag_len + 2;
      }
    }

    return null;
  }

  /**
   * Decide whether a single <img> tag should be deferred and, if so,
   * rewrite its attributes.
   */
  private function maybe_defer_image( string $tag, int &$count ): string {
    // Already processed.
    if ( strpos( $tag, 'data-nectarblocks-src' ) !== false ) {
      return $tag;
    }

    // Must have a real src.
    if ( ! preg_match( '/(?<=\s)src\s*=\s*(["\'])([^"\']*)\1/', $tag, $src_match ) ) {
      return $tag;
    }

    $src = $src_match[2];

    // Skip data-URIs and inline SVGs — already weightless.
    if ( strpos( $src, 'data:' ) === 0 ) {
      return $tag;
    }

    // Skip SVG files — vector, tiny, no benefit.
    if ( preg_match( '/\.svg(?:\?|$)/i', $src ) ) {
      return $tag;
    }

    // WooCommerce hover gallery images are hidden overlays — always
    // defer them and never count toward the threshold, even if they
    // have fetchpriority="high" (WordPress adds it to the first image).
    $is_hover_gallery = strpos( $tag, 'hover-gallery-image' ) !== false;

    if ( ! $is_hover_gallery ) {
      // Respect explicit priority hints.
      if ( preg_match( '/(?<=\s)fetchpriority\s*=\s*["\']high["\']/i', $tag ) ) {
        $count++;
        return $tag;
      }

      // Respect explicit eager loading.
      if ( preg_match( '/(?<=\s)loading\s*=\s*["\']eager["\']/i', $tag ) ) {
        $count++;
        return $tag;
      }

      $count++;

      // Above-fold images stay untouched.
      if ( $count <= self::get_threshold() ) {
        return $tag;
      }
    }

    // --- Below the fold (or always-defer): defer this image. ---

    // Build an aspect-preserving placeholder from the img's width/height
    // attributes. A 1×1 placeholder collapses the element to 0×0 in some
    // contexts (e.g. cloned ticker items, flex children with auto sizing),
    // which prevents IntersectionObserver from ever firing and traps the
    // image in its placeholder state forever.
    $width = 1;
    $height = 1;
    if ( preg_match( '/(?<=\s)width\s*=\s*["\']?(\d+)/i', $tag, $w_match ) ) {
      $width = max( 1, (int) $w_match[1] );
    }
    if ( preg_match( '/(?<=\s)height\s*=\s*["\']?(\d+)/i', $tag, $h_match ) ) {
      $height = max( 1, (int) $h_match[1] );
    }
    $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}'%3E%3C/svg%3E";

    // Swap src → data-nectarblocks-src, insert placeholder.
    $tag = preg_replace_callback(
        '/(?<=\s)src\s*=\s*(["\'])([^"\']*)\1/',
        function ( $m ) use ( $placeholder ) {
        $q = $m[1]; // quote character
        $v = $m[2]; // original src value
        return 'src=' . $q . $placeholder . $q . ' data-nectarblocks-src=' . $q . $v . $q;
      },
        $tag
    ) ?? $tag;

    // Swap srcset → data-nectarblocks-srcset.
    $tag = preg_replace_callback(
        '/(?<=\s)srcset\s*=\s*(["\'])([^"\']*)\1/',
        function ( $m ) {
        return 'data-nectarblocks-srcset=' . $m[1] . $m[2] . $m[1];
      },
        $tag
    ) ?? $tag;

    // Remove native loading="lazy" — we handle loading ourselves.
    $tag = preg_replace( '/(?<=\s)loading\s*=\s*["\']lazy["\']\s*/i', '', $tag ) ?? $tag;

    // Add the lazy class.
    if ( preg_match( '/(?<=\s)class\s*=\s*["\']/', $tag ) ) {
      $tag = preg_replace(
          '/(?<=\s)(class\s*=\s*["\'])/',
          '${1}nectarblocks-lazy ',
          $tag
      ) ?? $tag;
    } else {
      $tag = str_replace( '<img ', '<img class="nectarblocks-lazy" ', $tag );
    }

    return $tag;
  }

  /**
   * Decide whether a .nectar__bg-image div should be deferred via the
   * .lazy-load-bg class. Shares the above-fold counter with <img> tags.
   */
  private function maybe_defer_bg( string $tag, int &$count ): string {
    // Already lazy via per-block setting — leave alone and don't count.
    if ( strpos( $tag, 'lazy-load-bg' ) !== false ) {
      return $tag;
    }

    $count++;

    // Above-fold backgrounds stay untouched.
    if ( $count <= self::get_threshold() ) {
      return $tag;
    }

    // Add .lazy-load-bg so the existing CSS rule hides it and the
    // existing lazyLoading() JS reveals it on intersection.
    return preg_replace(
        '/(?<=\s)(class\s*=\s*["\'])/',
        '${1}lazy-load-bg ',
        $tag
    ) ?? $tag;
  }
}
