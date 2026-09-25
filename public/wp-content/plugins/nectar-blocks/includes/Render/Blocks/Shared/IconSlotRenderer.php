<?php

namespace Nectar\Render\Blocks\Shared;

/**
 * Shared SVG slot-injection helper for icon-bearing blocks.
 *
 * Blocks whose save() emits an empty <span data-nectar-icon-slot> marker for
 * library-SVG icons (icon, icon-list-item, tabs, accordion-section, button)
 * delegate their render_callback through here. The class owns:
 *   - the slot attribute name (single source of truth, paired with
 *     `NECTAR_ICON_SLOT_ATTR` on the JS side)
 *   - the SVG KSES allowlist
 *   - the marker-find + SVG-inject logic
 *
 * Per-block render classes stay thin so they can carry block-specific
 * attribute lookups or future composition without duplicating the SVG path.
 */
class IconSlotRenderer {
  const SLOT_ATTR = 'data-nectar-icon-slot';

  /**
   * Icon-library -> bundled assets folder. Mirrors the mapping in
   * Nectar\API\Assets_API (the editor reads the same icons.json files over
   * REST) — keep the two in sync. NOTE: the folder name is not uniformly
   * "<library>-icons"; simple-icons has no suffix.
   */
  const LIBRARY_DIRS = [
    'remix' => 'remix-icons',
    'lucide' => 'lucide-icons',
    'simple-icons' => 'simple-icons',
  ];

  /** Per-request memo of decoded name->svg maps, keyed by library. */
  private static $library_svgs = [];

  /**
   * Keys are lowercase only: wp_kses_attr / wp_kses_hair both strtolower
   * element + attribute names before lookup, so mixed-case keys like
   * `viewBox` or `linearGradient` are unreachable. Browsers fix SVG case at
   * parse time, so lowercase output renders correctly.
   */
  private static $allowed_svg_html = null;

  /**
   * Inject the given SVG string into the first slot marker found in $content.
   * Convenience wrapper around fill_slots() for single-icon blocks.
   */
  public static function fill_slot($content, $svg, ?string $aria_label = null) {
    return self::fill_slots($content, is_string($svg) ? [$svg] : [], $aria_label);
  }

  /**
   * Inject SVGs into slot markers in $content, in order. The Nth slot found
   * is filled with $svgs[N]. Used by blocks that emit multiple slots per
   * render (e.g. tabs nav, accordion section list) — pass an svgs array in
   * the same order save() emitted the slots.
   *
   * When the entry for a slot is empty (e.g. the `icon` attribute was dropped
   * from the delimiter for equalling its registered default), the marker is
   * filled from its own self-describing identity (data-icon-library /
   * data-icon-name), resolving the SVG server-side from the bundled icon
   * library. So a slot renders as long as EITHER a payload is supplied OR the
   * marker carries a resolvable identity.
   *
   * No-ops (returns $content unchanged) when $content is empty or contains no
   * marker. Per-slot no-op (marker stays empty) when neither a payload nor a
   * resolvable identity is available, or wp_kses strips the SVG to empty.
   *
   * Backwards compatibility: when stored content has inline SVG (pre-dynamic
   * markup that hasn't been re-saved yet), no markers are present, so
   * $content passes through verbatim and the inline SVGs render as before.
   *
   * $aria_label (opt-in; null leaves output byte-identical for all existing
   * callers) mirrors the JS a11y emission: '' = decorative, non-empty =
   * announced name, and one label covers every slot this call fills. Applied
   * per filled slot:
   *   - the injected <svg> gets focusable="false" aria-hidden="true" (keeps
   *     embedded <title>s — e.g. Simple Icons brand marks — from announcing;
   *     the wrapper span carries the announced name or hides the subtree)
   *   - the marker span gets aria-hidden="true" OR role="img" aria-label="…"
   *     ONLY when it doesn't already carry aria/role attrs (i.e. legacy
   *     content saved before the a11y attrs landed in save()).
   */
  public static function fill_slots($content, array $svgs, ?string $aria_label = null) {
    if (! is_string($content) || $content === '' || strpos($content, self::SLOT_ATTR) === false) {
      return is_string($content) ? $content : '';
    }

    $pattern = '/<span\b([^>]*\b' . preg_quote(self::SLOT_ATTR, '/') . '\b[^>]*)>\s*<\/span>/';
    $index = 0;
    $allowed = self::get_allowed_svg_html();
    $has_a11y = $aria_label !== null;
    $label = $has_a11y ? trim($aria_label) : '';

    $result = preg_replace_callback(
        $pattern,
        function ($match) use ($svgs, &$index, $allowed, $has_a11y, $label) {
        $svg = $svgs[$index] ?? '';
        $index++;
        if (! is_string($svg) || $svg === '') {
          // No SVG supplied for this slot — e.g. the `icon` attribute was
          // omitted from the delimiter because it equalled its default. Fall
          // back to the marker's self-describing identity and resolve the SVG
          // server-side from the bundled icon library.
          $svg = self::resolve_marker_svg($match[1]);
        }
        if (! is_string($svg) || $svg === '') {
          return $match[0];
        }
        if ($has_a11y) {
          $svg = self::harden_svg($svg);
        }
        $sanitized = wp_kses($svg, $allowed);
        if ($sanitized === '') {
          return $match[0];
        }
        $span_attrs = $match[1];
        // Anchor the attribute-name start: a bare \b would also match the tail
        // of data-role / data-aria-label and wrongly suppress injection.
        if ($has_a11y && ! preg_match('/(?:^|[\s"\'])(aria-hidden|aria-label|role)\s*=/i', $span_attrs)) {
          // Legacy markup saved before save() emitted the a11y attrs — inject
          // them at render time. New markup already carries them; skip.
          $span_attrs .= $label === ''
            ? ' aria-hidden="true"'
            : ' role="img" aria-label="' . self::escape_attr_value($label) . '"';
        }
        return '<span' . $span_attrs . '>' . $sanitized . '</span>';
      },
        $content
    );
    return is_string($result) ? $result : $content;
  }

  /**
   * Mark the SVG subtree inert for assistive tech. Only adds the attributes
   * the opening tag doesn't already carry — get_allowed_svg_html() allows both
   * on `svg`, so wp_kses would keep a duplicate pair rather than strip it.
   */
  private static function harden_svg($svg) {
    $additions = '';
    if (! preg_match('/<svg\b[^>]*\sfocusable\s*=/i', $svg)) {
      $additions .= ' focusable="false"';
    }
    if (! preg_match('/<svg\b[^>]*\saria-hidden\s*=/i', $svg)) {
      $additions .= ' aria-hidden="true"';
    }
    if ($additions === '') {
      return $svg;
    }
    $replaced = preg_replace('/<svg\b/i', '<svg' . $additions, $svg, 1);
    return is_string($replaced) ? $replaced : $svg;
  }

  /**
   * esc_attr when WP is loaded; htmlspecialchars fallback for the WP-free
   * unit-test runtime (mirrors the wp_json_file_decode guard below). A plain
   * test-file esc_attr stub is NOT an option: it would block Brain Monkey
   * from stubbing esc_attr in the Wp suite (Patchwork can't re-stub a
   * plain-defined function — see RenderBlockCssDeliveryTest).
   */
  private static function escape_attr_value($value) {
    return function_exists('esc_attr')
      ? esc_attr($value)
      : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * Extract the SVG string from a Nectar icon attribute record (the
   * `attributes.icon` shape used by every icon-bearing block).
   * Returns '' when the icon source is custom (uploaded image, no SVG) or
   * when the record is missing/malformed.
   */
  public static function get_svg_from_icon_attr($icon) {
    if (! is_array($icon)) {
      return '';
    }
    if (($icon['source'] ?? '') === 'custom') {
      // Custom uploads render as <img> in save() — no SVG slot in $content.
      return '';
    }
    $svg = $icon['icon']['svg'] ?? '';
    return is_string($svg) ? $svg : '';
  }

  /**
   * Resolve a library SVG from a slot marker's self-describing identity
   * attributes (data-icon-library / data-icon-name). Returns '' when the
   * marker carries no identity or the icon can't be found.
   */
  private static function resolve_marker_svg($attrs) {
    if (! is_string($attrs)) {
      return '';
    }
    $library = '';
    $name = '';
    if (preg_match('/\bdata-icon-library\s*=\s*("|\')(.*?)\1/', $attrs, $m)) {
      $library = $m[2];
    }
    if (preg_match('/\bdata-icon-name\s*=\s*("|\')(.*?)\1/', $attrs, $m)) {
      $name = $m[2];
    }
    return self::get_library_svg($library, $name);
  }

  /**
   * Look up a bundled library icon's raw SVG by library + name (the
   * icons.json key, e.g. "vip-crown-line.svg"). Decoded maps are memoised per
   * library for the request. Returns '' when unavailable.
   */
  public static function get_library_svg($library, $name) {
    if (! is_string($library) || ! is_string($name) || $library === '' || $name === '') {
      return '';
    }
    if (! isset(self::$library_svgs[$library])) {
      self::$library_svgs[$library] = self::load_library_svgs($library);
    }
    $svg = self::$library_svgs[$library][$name] ?? '';
    return is_string($svg) ? $svg : '';
  }

  /**
   * Load and decode a library's name->svg map from its bundled icons.json —
   * the same files Assets_API serves to the editor. Returns [] when the
   * library is unknown or the file / WP helpers are unavailable (e.g. unit
   * tests run WP-free).
   */
  private static function load_library_svgs($library) {
    $dir = self::LIBRARY_DIRS[$library] ?? '';
    if ($dir === '' || ! defined('NECTAR_BLOCKS_ROOT_DIR_PATH') || ! function_exists('wp_json_file_decode')) {
      return [];
    }
    $decoded = wp_json_file_decode(
        NECTAR_BLOCKS_ROOT_DIR_PATH . '/assets/build/' . $dir . '/icons.json',
        ['associative' => true]
    );
    return is_array($decoded) ? $decoded : [];
  }

  public static function get_allowed_svg_html() {
    if (self::$allowed_svg_html !== null) {
      return self::$allowed_svg_html;
    }

    $core_attrs = [
      'class' => true,
      'id' => true,
      'fill' => true,
      'fill-opacity' => true,
      'fill-rule' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true,
      'stroke-dasharray' => true,
      'stroke-dashoffset' => true,
      'stroke-opacity' => true,
      'opacity' => true,
      'transform' => true,
      'clip-path' => true,
      'clip-rule' => true,
      'mask' => true,
    ];

    self::$allowed_svg_html = [
      'svg' => array_merge($core_attrs, [
        'xmlns' => true,
        'xmlns:xlink' => true,
        'viewbox' => true,
        'width' => true,
        'height' => true,
        'role' => true,
        'aria-hidden' => true,
        'aria-label' => true,
        'aria-labelledby' => true,
        'focusable' => true,
        'preserveaspectratio' => true,
        'version' => true,
      ]),
      'g' => $core_attrs,
      'defs' => $core_attrs,
      'symbol' => array_merge($core_attrs, [
        'viewbox' => true,
        'preserveaspectratio' => true,
      ]),
      // NOTE: <use> is deliberately NOT allowed. wp_kses does not protocol-check
      // `xlink:href` at all (it isn't in wp_kses_uri_attributes()), so a bare-true
      // entry would let `xlink:href="javascript:..."` survive; and even `href`
      // (which IS protocol-checked) would still pass any external https sprite ref
      // through, a cross-origin fetch/tracking vector. The three bundled libraries
      // (remix/lucide/simple-icons) never emit <use>, so excluding it has no cost.
      'path' => array_merge($core_attrs, [
        'd' => true,
        'pathlength' => true,
      ]),
      'circle' => array_merge($core_attrs, [
        'cx' => true,
        'cy' => true,
        'r' => true,
      ]),
      'rect' => array_merge($core_attrs, [
        'x' => true,
        'y' => true,
        'width' => true,
        'height' => true,
        'rx' => true,
        'ry' => true,
      ]),
      'ellipse' => array_merge($core_attrs, [
        'cx' => true,
        'cy' => true,
        'rx' => true,
        'ry' => true,
      ]),
      'line' => array_merge($core_attrs, [
        'x1' => true,
        'y1' => true,
        'x2' => true,
        'y2' => true,
      ]),
      'polyline' => array_merge($core_attrs, [
        'points' => true,
      ]),
      'polygon' => array_merge($core_attrs, [
        'points' => true,
        'fill-rule' => true,
      ]),
      'text' => array_merge($core_attrs, [
        'x' => true,
        'y' => true,
        'dx' => true,
        'dy' => true,
        'font-family' => true,
        'font-size' => true,
        'font-weight' => true,
        'text-anchor' => true,
        'dominant-baseline' => true,
      ]),
      'tspan' => array_merge($core_attrs, [
        'x' => true,
        'y' => true,
        'dx' => true,
        'dy' => true,
      ]),
      'title' => [],
      'desc' => [],
      'mask' => array_merge($core_attrs, [
        'maskunits' => true,
        'maskcontentunits' => true,
        'x' => true,
        'y' => true,
        'width' => true,
        'height' => true,
      ]),
      'clippath' => array_merge($core_attrs, [
        'clippathunits' => true,
      ]),
      'lineargradient' => array_merge($core_attrs, [
        'x1' => true,
        'y1' => true,
        'x2' => true,
        'y2' => true,
        'gradientunits' => true,
        'gradienttransform' => true,
        'spreadmethod' => true,
      ]),
      'radialgradient' => array_merge($core_attrs, [
        'cx' => true,
        'cy' => true,
        'r' => true,
        'fx' => true,
        'fy' => true,
        'gradientunits' => true,
        'gradienttransform' => true,
      ]),
      'stop' => array_merge($core_attrs, [
        'offset' => true,
        'stop-color' => true,
        'stop-opacity' => true,
      ]),
    ];

    return self::$allowed_svg_html;
  }
}
