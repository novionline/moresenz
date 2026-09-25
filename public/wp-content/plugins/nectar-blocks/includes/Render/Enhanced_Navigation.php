<?php

namespace Nectar\Render;

/**
 * Enhanced Navigation (core/navigation) frontend output adjustments.
 *
 * This follows "Option A": keep core/navigation saved markup intact, but alter
 * frontend render output when a marker attribute is set (nectarEnhanced).
 */
class Enhanced_Navigation {
  /**
   * Selector shims for core/navigation markup.
   * Core markup can evolve; keep all selector variance centralized here.
   */
  private const XPATH_OPEN_BUTTONS = [
    // Current core selector.
    "//*[self::button][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__responsive-container-open ')]",
    // Fallback: tolerate class prefix changes while keeping intent narrow.
    "//*[self::button][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__responsive-container-open')]",
  ];

  private const XPATH_NAV_CONTAINERS = [
    "//*[self::nav][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation ')]",
    // Fallback: allow non-nav wrapper if core ever changes wrapper element type.
    "//*[self::div][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation ')]",
  ];

  /**
   * Captured menu item markup (li elements) from the last rendered enhanced navigation block.
   * Used by the Nectar Blocks theme off-canvas navigation when present.
   */
  private static ?string $ocm_menu_items_html = null;

  public function __construct() {
    add_filter( 'render_block_core/navigation', [$this, 'render'], 10, 2 );
    add_filter( 'nectar_ocm_block_menu_items', [__CLASS__, 'filter_ocm_block_menu_items'], 10, 1 );
  }

  public static function filter_ocm_block_menu_items( $html ): string {
    if ( is_string( self::$ocm_menu_items_html ) && '' !== self::$ocm_menu_items_html ) {
      return self::$ocm_menu_items_html;
    }
    return is_string( $html ) ? $html : '';
  }

  private function is_nectar_blocks_theme_active(): bool {
    // Theme files reference this class; using it as the most reliable guard.
    if ( defined( 'NECTAR_BLOCKS_FORCE_THEME_ACTIVE' ) && true === NECTAR_BLOCKS_FORCE_THEME_ACTIVE ) {
      return true;
    }
    return class_exists( '\NectarThemeManager' );
  }

  /**
   * Enqueue the dropdown alignment script for enhanced navigation.
   * Only enqueues once per page load.
   */
  private function enqueue_dropdown_alignment_script(): void {
    static $enqueued = false;
    if ( $enqueued ) {
      return;
    }

    $script_path = NECTAR_BLOCKS_BUILD_PATH . '/nectar-blocks-enhanced-navigation.js';
    $asset_path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-enhanced-navigation.asset.php';
    $deps = [];
    $version = NECTAR_BLOCKS_VERSION;

    if ( file_exists( $asset_path ) ) {
      $asset = include $asset_path;
      $deps = isset( $asset['dependencies'] ) ? $asset['dependencies'] : [];
      $version = isset( $asset['version'] ) ? $asset['version'] : $version;
    }

    wp_enqueue_script(
        'nectar-blocks-enhanced-navigation',
        $script_path,
        $deps,
        $version,
        true // Load in footer.
    );

    $enqueued = true;
  }

  /**
   * Enqueue the FLIP animation script and styles for dropdown transitions.
   */
  private function enqueue_dropdown_flip_assets(): void {
    $script_path = NECTAR_BLOCKS_BUILD_PATH . '/nectar-blocks-dropdown-flip.js';
    $style_path = NECTAR_BLOCKS_BUILD_PATH . '/nectar-blocks-dropdown-flip-styles.css';
    $asset_path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/nectar-blocks-dropdown-flip.asset.php';
    $deps = [];
    $version = NECTAR_BLOCKS_VERSION;

    if ( file_exists( $asset_path ) ) {
      $asset = include $asset_path;
      $deps = isset( $asset['dependencies'] ) ? $asset['dependencies'] : [];
      $version = isset( $asset['version'] ) ? $asset['version'] : $version;
    }

    // Register first, then enqueue (needed for late-enqueues after wp_enqueue_scripts has fired)
    if ( ! wp_script_is( 'nectar-blocks-dropdown-flip', 'registered' ) ) {
      wp_register_script(
          'nectar-blocks-dropdown-flip',
          $script_path,
          $deps,
          $version,
          true // Load in footer.
      );
    }
    wp_enqueue_script( 'nectar-blocks-dropdown-flip' );

    if ( ! wp_style_is( 'nectar-blocks-dropdown-flip', 'registered' ) ) {
      wp_register_style(
          'nectar-blocks-dropdown-flip',
          $style_path,
          [],
          $version
      );
    }
    wp_enqueue_style( 'nectar-blocks-dropdown-flip' );
  }

  private static function wp_esc_html__( string $text, string $domain ): string {
    return function_exists( 'esc_html__' ) ? (string) esc_html__( $text, $domain ) : $text;
  }

  private static function wp_esc_html( string $text ): string {
    return function_exists( 'esc_html' ) ? (string) esc_html( $text ) : $text;
  }

  private static function wp_esc_attr( string $text ): string {
    return function_exists( 'esc_attr' ) ? (string) esc_attr( $text ) : $text;
  }

  private static function wp_esc_url( string $url ): string {
    return function_exists( 'esc_url' ) ? (string) esc_url( $url ) : $url;
  }

  private static function wp_sanitize_html_class( string $value ): string {
    if ( function_exists( 'sanitize_html_class' ) ) {
      return (string) sanitize_html_class( $value );
    }
    // Conservative fallback; keeps tests runnable without WordPress loaded.
    return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) ?? '';
  }

  /**
   * Strip the XML encoding declaration added for DOMDocument UTF-8 support.
   *
   * PHP's DOMDocument::loadHTML() defaults to ISO-8859-1 encoding and will mangle
   * non-ASCII characters (accented letters, emoji, etc.) without an explicit encoding hint.
   * Prepending `<?xml encoding="utf-8" ?>` before loadHTML() forces UTF-8 interpretation.
   * This method removes that declaration from the final output.
   */
  private static function strip_xml_declaration( string $html ): string {
    return preg_replace( '/^<\?xml[^?]*\?>\s*/i', '', $html ) ?? $html;
  }

  private function query_first_node_list( \DOMXPath $xpath, array $queries ): ?\DOMNodeList {
    foreach ( $queries as $query ) {
      if ( ! is_string( $query ) || '' === $query ) {
        continue;
      }
      $nodes = $xpath->query( $query );
      if ( $nodes instanceof \DOMNodeList && $nodes->length ) {
        return $nodes;
      }
    }
    return null;
  }

  private function apply_ocm_icon_markup(
      \DOMDocument $dom,
      \DOMElement $btn,
      string $icon_style = 'three-lines',
      string $icon_text = '',
      string $text_align = 'right',
      string $link_animation = 'Default'
  ): void {
    // Clear existing contents (core often provides an icon SVG).
    while ( $btn->firstChild ) {
      $btn->removeChild( $btn->firstChild );
    }

    $outer = $dom->createElement( 'span' );
    $outer->setAttribute( 'aria-hidden', 'true' );

    $is_two_lines = 'two-lines' === $icon_style;
    $is_text_only = 'text-only' === $icon_style;
    $has_text = '' !== trim( (string) $icon_text );

    if ( ! $is_text_only ) {
      // Icon wrapper: contains both lines-button and close-wrap.
      // Positioned relatively so close lines can be absolutely positioned within.
      $icon_wrap = $dom->createElement( 'span' );
      $icon_wrap->setAttribute( 'class', 'nectar-ocm-icon-wrap' );

      $lines_btn = $dom->createElement( 'svg' );
      $lines_btn->setAttribute( 'class', 'lines-button' );
      $lines_btn->setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
      $lines_btn->setAttribute( 'viewBox', $is_two_lines ? '0 0 22 12' : '0 0 22 14' );
      // Intrinsic dimensions so the icon never renders at the SVG 300x150 default
      // before the sizing CSS resolves height:auto (iOS Safari paints the default
      // for a frame, making the lines appear to collapse/animate on load). CSS
      // (--ocm-icon-width) still overrides these.
      $lines_btn->setAttribute( 'width', '22' );
      $lines_btn->setAttribute( 'height', $is_two_lines ? '12' : '14' );
      $lines_btn->setAttribute( 'fill', 'none' );
      $lines_btn->setAttribute( 'stroke', 'currentColor' );
      $lines_btn->setAttribute( 'stroke-width', '2' );
      $lines_btn->setAttribute( 'stroke-linecap', 'round' );
      $lines_btn->setAttribute( 'aria-hidden', 'true' );

      // Line elements for each hamburger bar (rounded via stroke-linecap).
      $line1 = $dom->createElement( 'line' );
      $line1->setAttribute( 'class', 'line line-1' );
      $line1->setAttribute( 'x1', '1' );
      $line1->setAttribute( 'y1', '1' );
      $line1->setAttribute( 'x2', '21' );
      $line1->setAttribute( 'y2', '1' );
      $lines_btn->appendChild( $line1 );

      if ( ! $is_two_lines ) {
        $line2 = $dom->createElement( 'line' );
        $line2->setAttribute( 'class', 'line line-2' );
        $line2->setAttribute( 'x1', '1' );
        $line2->setAttribute( 'y1', '7' );
        $line2->setAttribute( 'x2', '21' );
        $line2->setAttribute( 'y2', '7' );
        $lines_btn->appendChild( $line2 );
      }

      $line3 = $dom->createElement( 'line' );
      $line3->setAttribute( 'class', 'line line-3' );
      $line3->setAttribute( 'x1', '1' );
      $line3->setAttribute( 'y1', $is_two_lines ? '11' : '13' );
      $line3->setAttribute( 'x2', '21' );
      $line3->setAttribute( 'y2', $is_two_lines ? '11' : '13' );
      $lines_btn->appendChild( $line3 );

      $icon_wrap->appendChild( $lines_btn );

      $close_wrap = $dom->createElement( 'span' );
      $close_wrap->setAttribute( 'class', 'close-wrap loaded' );

      $close_line1 = $dom->createElement( 'span' );
      $close_line1->setAttribute( 'class', 'close-line close-line1' );
      $close_wrap->appendChild( $close_line1 );

      $close_line2 = $dom->createElement( 'span' );
      $close_line2->setAttribute( 'class', 'close-line close-line2' );
      $close_wrap->appendChild( $close_line2 );

      $icon_wrap->appendChild( $close_wrap );

      if ( $has_text && 'left' === $text_align ) {
        $outer->appendChild( $this->create_trigger_text_el( $dom, $icon_text, $link_animation ) );
        $outer->appendChild( $icon_wrap );
      } else {
        $outer->appendChild( $icon_wrap );
        if ( $has_text ) {
          $outer->appendChild( $this->create_trigger_text_el( $dom, $icon_text, $link_animation ) );
        }
      }
    } else {
      // Text-only: just the text span, no icon wrapper.
      if ( $has_text ) {
        $outer->appendChild( $this->create_trigger_text_el( $dom, $icon_text, $link_animation ) );
      }
    }

    $btn->appendChild( $outer );
  }

  /**
   * Create the trigger text element with a data-close-text attribute for JS text swap.
   * Optionally wraps the text in animation markup (Reveal / Wave) to match menu item FX.
   */
  private function create_trigger_text_el( \DOMDocument $dom, string $text, string $link_animation = 'Default' ): \DOMElement {
    $el = $dom->createElement( 'span' );
    $el->setAttribute( 'class', 'nectar-ocm-trigger-text' );

    // Open label — visible by default, hidden via CSS when .nectar-ocm-text-swapped is active.
    $open = $dom->createElement( 'span' );
    $open->setAttribute( 'class', 'nectar-ocm-trigger-text__open' );

    if ( 'Reveal' === $link_animation ) {
      $open->appendChild( $this->build_reveal_markup( $dom, $text ) );
    } elseif ( 'Wave' === $link_animation ) {
      $open->appendChild( $this->build_wave_markup( $dom, $text ) );
    } else {
      $open->appendChild( $dom->createTextNode( $text ) );
    }

    // Close label — hidden by default, shown by JS when the menu opens.
    $close = $dom->createElement( 'span' );
    $close->setAttribute( 'class', 'nectar-ocm-trigger-text__close' );
    $close->appendChild( $dom->createTextNode( self::wp_esc_html__( 'Close', 'nectar-blocks' ) ) );

    $el->appendChild( $open );
    $el->appendChild( $close );

    return $el;
  }

  /**
   * Move submenu icons (dropdown arrows) inside their sibling anchor elements.
   *
   * This allows all styling (padding, background, border, effects) to be applied
   * to the anchor element only, eliminating the need for complex li-based styling
   * workarounds to visually unify the text and arrow.
   *
   * Before: <li><a>Text</a><span class="submenu-icon">▼</span><ul>...</ul></li>
   * After:  <li><a>Text<span class="submenu-icon">▼</span></a><ul>...</ul></li>
   */
  private function move_submenu_icons_inside_anchors( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );

    // Find all submenu icons that are direct children of navigation items.
    $icons = $xpath->query(
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item ')]" .
        "/*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-icon ')]"
    );

    if ( $icons instanceof \DOMNodeList && $icons->length ) {
      foreach ( iterator_to_array( $icons ) as $icon ) {
        if ( ! $icon instanceof \DOMElement || ! $icon->parentNode instanceof \DOMElement ) {
          continue;
        }

        $li = $icon->parentNode;

        // Find the sibling anchor element.
        $anchor = null;
        foreach ( $li->childNodes as $child ) {
          if ( $child instanceof \DOMElement ) {
            $tag = strtolower( $child->tagName );
            $class = $child->getAttribute( 'class' );
            if (
                ( 'a' === $tag || 'button' === $tag ) &&
                false !== strpos( ' ' . $class . ' ', ' wp-block-navigation-item__content ' )
            ) {
              $anchor = $child;
              break;
            }
          }
        }

        if ( $anchor instanceof \DOMElement ) {
          // Remove icon from li and append to anchor.
          $li->removeChild( $icon );
          $anchor->appendChild( $icon );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Process megamenu blocks within navigation items.
   *
   * When a navigation item contains a nectar-megamenu block:
   * 1. Move the megamenu div outside the submenu-container to be a direct child of the li
   * 2. Add 'has-megamenu' class to the parent li
   * 3. Hide the submenu-container (it becomes obsolete as megamenu replaces it)
   * 4. Remove the dropdown arrow since megamenu replaces the dropdown
   *
   * Before:
   * <li class="wp-block-navigation-item has-child">
   *   <a>Link</a>
   *   <span class="submenu-icon">▼</span>
   *   <ul class="wp-block-navigation__submenu-container">
   *     <li>...</li>
   *     <div class="nectar-megamenu">...</div>
   *   </ul>
   * </li>
   *
   * After:
   * <li class="wp-block-navigation-item has-child has-megamenu">
   *   <a>Link</a>
   *   <div class="nectar-megamenu">...</div>
   * </li>
   */
  private function process_megamenus( string $html ): string {
    if ( '' === trim( $html ) || false === strpos( $html, 'nectar-megamenu' ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );

    // Find all megamenu elements.
    $megamenus = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' nectar-megamenu ')]" );

    if ( ! $megamenus instanceof \DOMNodeList || 0 === $megamenus->length ) {
      return $html;
    }

    foreach ( iterator_to_array( $megamenus ) as $megamenu ) {
      if ( ! $megamenu instanceof \DOMElement ) {
        continue;
      }

      // Find the parent navigation item (li.wp-block-navigation-item).
      $nav_item = null;
      $parent = $megamenu->parentNode;
      while ( $parent instanceof \DOMElement ) {
        $class = $parent->getAttribute( 'class' );
        if ( false !== strpos( ' ' . $class . ' ', ' wp-block-navigation-item ' ) ) {
          $nav_item = $parent;
          break;
        }
        $parent = $parent->parentNode;
      }

      if ( ! $nav_item instanceof \DOMElement ) {
        continue;
      }

      // Add 'has-megamenu' class to the navigation item.
      $existing_class = $nav_item->getAttribute( 'class' );
      if ( false === strpos( ' ' . $existing_class . ' ', ' has-megamenu ' ) ) {
        $nav_item->setAttribute( 'class', trim( $existing_class . ' has-megamenu' ) );
      }

      // Move megamenu from inside submenu-container to be direct child of nav_item.
      // First, detach it from its current parent.
      $megamenu->parentNode->removeChild( $megamenu );

      // Append megamenu as last child of nav_item.
      $nav_item->appendChild( $megamenu );

      // Find and remove the submenu-container (megamenu replaces it).
      // Keep the dropdown arrow to indicate there's content on hover.
      $children_to_remove = [];
      foreach ( $nav_item->childNodes as $child ) {
        if ( ! $child instanceof \DOMElement ) {
          continue;
        }
        $child_class = $child->getAttribute( 'class' );

        // Remove submenu container only.
        if ( false !== strpos( ' ' . $child_class . ' ', ' wp-block-navigation__submenu-container ' ) ) {
          $children_to_remove[] = $child;
        }
      }

      foreach ( $children_to_remove as $child ) {
        $nav_item->removeChild( $child );
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Remove core navigation submenu toggle buttons (dropdown arrows) from markup.
   */
  private function strip_submenu_toggle_buttons( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $toggles = $xpath->query(
        "//*[self::button][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ') or " .
      "contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item__toggle ')]"
    );
    if ( $toggles instanceof \DOMNodeList && $toggles->length ) {
      foreach ( iterator_to_array( $toggles ) as $btn ) {
        if ( ! $btn instanceof \DOMElement || ! $btn->parentNode ) {
          continue;
        }

        $parent = $btn->parentNode;

        // Check if there's a sibling anchor link in the same parent (the <li>).
        // WordPress renders submenu items without a URL as button-only (no <a>).
        // In that case, we need to convert the button to an anchor to preserve the text.
        $has_sibling_link = false;
        if ( $parent instanceof \DOMElement ) {
          foreach ( $parent->childNodes as $sibling ) {
            if (
              $sibling instanceof \DOMElement &&
              'a' === strtolower( $sibling->tagName ) &&
              false !== strpos( ' ' . $sibling->getAttribute( 'class' ) . ' ', ' wp-block-navigation-item__content ' )
            ) {
              $has_sibling_link = true;
              break;
            }
          }
        }

        if ( $has_sibling_link ) {
          // There's already a link, safe to remove the toggle button entirely.
          $parent->removeChild( $btn );
        } else {
          // No sibling link - convert the button to an anchor to preserve the text.
          // Extract text content (excluding any nested SVG icons).
          $text_content = '';
          foreach ( $btn->childNodes as $child ) {
            if ( $child instanceof \DOMText ) {
              $text_content .= $child->textContent;
            } elseif (
              $child instanceof \DOMElement &&
              'span' === strtolower( $child->tagName )
            ) {
              $text_content .= $child->textContent;
            }
          }
          $text_content = trim( $text_content );

          if ( '' !== $text_content ) {
            $anchor = $dom->createElement( 'a' );
            $anchor->setAttribute( 'class', 'wp-block-navigation-item__content' );
            $anchor->setAttribute( 'href', '#' );
            $anchor->textContent = $text_content;
            $parent->insertBefore( $anchor, $btn );
          }

          $parent->removeChild( $btn );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Remove core navigation submenu icon markup from captured OCM items.
   * The theme renders/handles its own dropdown arrows in the off-canvas menu.
   */
  private function strip_submenu_icons( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $icons = $xpath->query(
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-icon ')]"
    );
    if ( $icons instanceof \DOMNodeList && $icons->length ) {
      foreach ( iterator_to_array( $icons ) as $icon ) {
        if ( $icon instanceof \DOMElement && $icon->parentNode ) {
          $icon->parentNode->removeChild( $icon );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Inject a right-arrow SVG into submenu toggle buttons for the plugin OCM.
   */
  private function add_ocm_toggle_icons( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $toggles = $xpath->query(
        "//*[self::button][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ') or " .
      "contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item__toggle ')]"
    );
    if ( $toggles instanceof \DOMNodeList && $toggles->length ) {
      $svg_markup = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' .
        '<path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z" fill="currentColor" />' .
        '</svg>';
      foreach ( iterator_to_array( $toggles ) as $btn ) {
        if ( ! $btn instanceof \DOMElement ) {
          continue;
        }
        while ( $btn->firstChild ) {
          $btn->removeChild( $btn->firstChild );
        }
        $frag = $dom->createDocumentFragment();
        $frag->appendXML( $svg_markup );
        $btn->appendChild( $frag );
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Remove link animation markup from captured OCM items.
   * Mobile nav should render plain text without wave/reveal spans.
   */
  private function strip_link_animation_markup( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );

    // Replace wave markup with the screen-reader text (avoid doubled text).
    $wave_nodes = $xpath->query(
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' nectar-enhanced-navigation__wave ')]"
    );
    if ( $wave_nodes instanceof \DOMNodeList && $wave_nodes->length ) {
      foreach ( iterator_to_array( $wave_nodes ) as $wave ) {
        if ( ! $wave instanceof \DOMElement ) {
          continue;
        }
        $parent = $wave->parentNode;
        if ( ! $parent instanceof \DOMElement ) {
          continue;
        }
        $sr = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' nectar-enhanced-navigation__sr-text ')]",
            $parent
        );
        $text = '';
        if ( $sr instanceof \DOMNodeList && $sr->length ) {
          $text = trim( $sr->item( 0 )->textContent );
        } else {
          $text = trim( $parent->textContent );
        }
        if ( $parent->parentNode ) {
          $parent->parentNode->replaceChild( $dom->createTextNode( $text ), $parent );
        }
      }
    }

    // Replace reveal markup with plain text.
    $reveal_nodes = $xpath->query(
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' nectar-enhanced-navigation__text__inner ')]"
    );
    if ( $reveal_nodes instanceof \DOMNodeList && $reveal_nodes->length ) {
      foreach ( iterator_to_array( $reveal_nodes ) as $inner ) {
        if ( ! $inner instanceof \DOMElement ) {
          continue;
        }
        $text = trim( $inner->textContent );
        $outer = $inner->parentNode instanceof \DOMElement ? $inner->parentNode : $inner;
        if ( $outer->parentNode ) {
          $outer->parentNode->replaceChild( $dom->createTextNode( $text ), $outer );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Remove empty submenu containers and related toggle/icon classes.
   * Ensures we don't render toggles for items without submenu items.
   */
  private function strip_empty_submenus( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $submenu_containers = $xpath->query(
        "//*[self::ul][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-container ')]"
    );
    if ( $submenu_containers instanceof \DOMNodeList && $submenu_containers->length ) {
      foreach ( iterator_to_array( $submenu_containers ) as $submenu ) {
        if ( ! $submenu instanceof \DOMElement ) {
          continue;
        }
        $has_li = false;
        foreach ( $submenu->childNodes as $child ) {
          if ( $child instanceof \DOMElement && strtolower( $child->tagName ) === 'li' ) {
            $has_li = true;
            break;
          }
        }
        if ( $has_li ) {
          continue;
        }

        $parent = $submenu->parentNode;
        if ( $parent instanceof \DOMElement ) {
          // Remove has-child/menu-item-has-children classes.
          $class = $parent->getAttribute( 'class' );
          if ( $class ) {
            $class = preg_replace(
                '/\b(has-child|menu-item-has-children|wp-block-navigation-item--has-child)\b/',
                '',
                $class
            );
            $parent->setAttribute( 'class', trim( preg_replace( '/\s+/', ' ', (string) $class ) ) );
          }

          // Remove any submenu toggle/icon elements under this item.
          $toggles = $xpath->query(
              ".//*[self::button][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ') or " .
            "contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item__toggle ')]",
              $parent
          );
          if ( $toggles instanceof \DOMNodeList && $toggles->length ) {
            foreach ( iterator_to_array( $toggles ) as $btn ) {
              if ( $btn instanceof \DOMElement && $btn->parentNode ) {
                $btn->parentNode->removeChild( $btn );
              }
            }
          }

          $icons = $xpath->query(
              ".//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-icon ')]",
              $parent
          );
          if ( $icons instanceof \DOMNodeList && $icons->length ) {
            foreach ( iterator_to_array( $icons ) as $icon ) {
              if ( $icon instanceof \DOMElement && $icon->parentNode ) {
                $icon->parentNode->removeChild( $icon );
              }
            }
          }
        }

        if ( $submenu->parentNode ) {
          $submenu->parentNode->removeChild( $submenu );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * For plugin OCM: restructure each menu item so the link + toggle live in a single row element,
   * and the submenu container renders below that row (not inline as a sibling in a flex/grid layout).
   *
   * Result:
   * <li>
   *   <div class="nectar-nb-ocm__item-row"> <a/> <button/> </div>
   *   <ul class="wp-block-navigation__submenu-container">...</ul>
   * </li>
   */
  private function wrap_ocm_menu_item_rows( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $items = $xpath->query(
        "//*[self::li][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item ')]"
    );

    if ( $items instanceof \DOMNodeList && $items->length ) {
      foreach ( iterator_to_array( $items ) as $li ) {
        if ( ! $li instanceof \DOMElement ) {
          continue;
        }

        $link = null;
        $toggle = null;
        $submenu = null;

        foreach ( $li->childNodes as $child ) {
          if ( ! $child instanceof \DOMElement ) {
            continue;
          }
          $class = ' ' . $child->getAttribute( 'class' ) . ' ';
          $tag = strtolower( $child->tagName );

          if ( 'a' === $tag && false !== strpos( $class, ' wp-block-navigation-item__content ' ) ) {
            $link = $child;
            continue;
          }
          if (
            'button' === $tag &&
            (
                false !== strpos( $class, ' wp-block-navigation-submenu__toggle ' ) ||
              false !== strpos( $class, ' wp-block-navigation-item__toggle ' )
            )
          ) {
            $toggle = $child;
            continue;
          }
          if ( 'ul' === $tag && false !== strpos( $class, ' wp-block-navigation__submenu-container ' ) ) {
            $submenu = $child;
            continue;
          }
        }

        // Nothing to wrap.
        if ( ! $link && ! $toggle ) {
          continue;
        }

        $row = $dom->createElement( 'div' );
        $row->setAttribute( 'class', 'nectar-nb-ocm__item-row' );

        // Insert row at the top of the LI.
        $first_el = null;
        foreach ( $li->childNodes as $child ) {
          if ( $child instanceof \DOMElement ) {
            $first_el = $child;
            break;
          }
        }
        if ( $first_el ) {
          $li->insertBefore( $row, $first_el );
        } else {
          $li->appendChild( $row );
        }

        // Move link and toggle into the row.
        if ( $link instanceof \DOMElement ) {
          $row->appendChild( $link );
        }
        if ( $toggle instanceof \DOMElement ) {
          $row->appendChild( $toggle );
        }

        // Ensure submenu is below the row (direct child of li, after row).
        if ( $submenu instanceof \DOMElement ) {
          $li->appendChild( $submenu );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Remove megamenu markup from captured OCM items.
   * Mobile nav should only include menu list items.
   */
  private function strip_megamenu_markup( string $html ): string {
    if ( '' === trim( $html ) ) {
      return $html;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    $megamenus = $xpath->query(
        "//*[contains(concat(' ', normalize-space(@class), ' '), ' nectar-megamenu ')]"
    );
    if ( $megamenus instanceof \DOMNodeList && $megamenus->length ) {
      foreach ( iterator_to_array( $megamenus ) as $node ) {
        if ( $node instanceof \DOMElement && $node->parentNode ) {
          $node->parentNode->removeChild( $node );
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  /**
   * Convert core navigation markup into OCM-style list item markup (li elements only).
   * This is intentionally best-effort; theme CSS/JS primarily expects a list structure.
   */
  private function capture_ocm_menu_items( array $block, string $block_content, string $menu_source = '' ): void {
    if ( ! $this->is_nectar_blocks_theme_active() ) {
      return;
    }

    $items_html = $this->build_ocm_menu_items_from_source( $block, $block_content, $menu_source, true, true );
    if ( '' !== $items_html ) {
      self::$ocm_menu_items_html = $items_html;
    }
  }

  /**
   * Build OCM-friendly <li> markup for the navigation menu.
   */
  private function build_ocm_menu_items_html(
      array $block,
      string $block_content,
      bool $strip_toggles,
      bool $strip_icons
  ): string {
    /**
     * Preferred: build from parsed innerBlocks to preserve ordering while skipping
     * non-navigation blocks (e.g. core/spacer) that can produce invalid <ul> markup.
     */
    $inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
    if ( ! empty( $inner_blocks ) ) {
      $items_html = '';

      foreach ( $inner_blocks as $inner ) {
        if ( ! is_array( $inner ) ) {
          continue;
        }
        // Render each child block and keep only any <li> markup it produces.
        // This allows us to ignore non-item blocks (Spacer, Group, etc) safely.
        $rendered = render_block( $inner );
        if ( ! is_string( $rendered ) || '' === trim( $rendered ) ) {
          continue;
        }

        $frag_dom = new \DOMDocument( '1.0', 'UTF-8' );
        $prev2 = libxml_use_internal_errors( true );
        $frag_dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $rendered,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev2 );

        $frag_xpath = new \DOMXPath( $frag_dom );
        $containers = $frag_xpath->query(
            "//*[self::ul][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__container ') or " .
            "contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-container ')]"
        );

        $found = false;
        if ( $containers instanceof \DOMNodeList && $containers->length ) {
          foreach ( $containers as $container ) {
            if ( ! $container instanceof \DOMElement ) {
              continue;
            }
            foreach ( $container->childNodes as $child ) {
              if ( $child instanceof \DOMElement && strtolower( $child->tagName ) === 'li' ) {
                $items_html .= $frag_dom->saveHTML( $child );
                $found = true;
              }
            }
          }
        }

        if ( ! $found ) {
          // Fallback: capture only top-level <li> (avoid flattening nested submenu items).
          $lis = $frag_xpath->query( "//*[self::li][not(ancestor::li)]" );
          if ( $lis instanceof \DOMNodeList && $lis->length ) {
            foreach ( $lis as $li ) {
              if ( $li instanceof \DOMElement ) {
                $items_html .= $frag_dom->saveHTML( $li );
              }
            }
          }
        }
      }

      $items_html = trim( $items_html );
      if ( '' !== $items_html ) {
        return $this->postprocess_ocm_menu_items_html( $items_html, $strip_toggles, $strip_icons );
      }
      // Fall through to HTML parsing if nothing rendered.
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $block_content,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    // Collect top-level <li> children from *all* navigation containers.
    // This is resilient to invalid markup where a non-li element causes the <ul>
    // to be closed and a new <ul> started later.
    $containers = $xpath->query(
        "//*[self::ul][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__container ')]"
    );
    if ( ! $containers instanceof \DOMNodeList || ! $containers->length ) {
      return '';
    }

    $items_html = '';
    foreach ( $containers as $container ) {
      if ( ! $container instanceof \DOMElement ) {
        continue;
      }
      foreach ( $container->childNodes as $child ) {
        if ( $child instanceof \DOMElement && strtolower( $child->tagName ) === 'li' ) {
          $items_html .= $dom->saveHTML( $child );
        }
      }
    }

    $items_html = trim( $items_html );
    if ( '' === $items_html ) {
      return '';
    }

    return $this->postprocess_ocm_menu_items_html( $items_html, $strip_toggles, $strip_icons );
  }

  private function build_ocm_menu_items_from_source(
      array $block,
      string $block_content,
      string $menu_source,
      bool $strip_toggles,
      bool $strip_icons
  ): string {
    $menu_source = trim( $menu_source );
    if ( '' !== $menu_source && preg_match( '/^(block|classic):(\d+)$/', $menu_source, $matches ) ) {
      $type = $matches[1];
      $id = (int) $matches[2];

      if ( 'block' === $type ) {
        return $this->build_ocm_menu_items_from_navigation_post( $id, $strip_toggles, $strip_icons );
      }
      if ( 'classic' === $type ) {
        return $this->build_ocm_menu_items_from_classic_menu( $id, $strip_toggles, $strip_icons );
      }
    }

    return $this->build_ocm_menu_items_html( $block, $block_content, $strip_toggles, $strip_icons );
  }

  private function build_ocm_menu_items_from_navigation_post(
      int $post_id,
      bool $strip_toggles,
      bool $strip_icons
  ): string {
    if ( ! function_exists( 'get_post' ) || ! function_exists( 'parse_blocks' ) ) {
      return '';
    }
    $post = get_post( $post_id );
    if ( ! $post || 'wp_navigation' !== $post->post_type ) {
      return '';
    }
    $content = (string) $post->post_content;
    if ( '' === trim( $content ) ) {
      return '';
    }
    $inner_blocks = parse_blocks( $content );
    $fake_block = [
      'innerBlocks' => is_array( $inner_blocks ) ? $inner_blocks : [],
    ];
    return $this->build_ocm_menu_items_html( $fake_block, $content, $strip_toggles, $strip_icons );
  }

  private function build_ocm_menu_items_from_classic_menu(
      int $menu_id,
      bool $strip_toggles,
      bool $strip_icons
  ): string {
    if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
      return '';
    }
    $items = wp_get_nav_menu_items( $menu_id, [
      'update_post_term_cache' => false,
    ] );
    if ( ! is_array( $items ) || empty( $items ) ) {
      return '';
    }

    $children = [];
    foreach ( $items as $item ) {
      if ( ! is_object( $item ) ) {
        continue;
      }
      $parent_id = (int) ( $item->menu_item_parent ?? 0 );
      if ( ! isset( $children[$parent_id] ) ) {
        $children[$parent_id] = [];
      }
      $children[$parent_id][] = $item;
    }

    $render = function( int $parent_id ) use ( &$render, $children ): string {
      if ( empty( $children[$parent_id] ) ) {
        return '';
      }
      $html = '';
      foreach ( $children[$parent_id] as $item ) {
        $item_id = (int) ( $item->ID ?? 0 );
        $has_children = ! empty( $children[$item_id] );
        $classes = [ 'wp-block-navigation-item', 'menu-item' ];
        if ( $has_children ) {
          $classes[] = 'has-child';
          $classes[] = 'menu-item-has-children';
        }
        $label = self::wp_esc_html( (string) ( $item->title ?? '' ) );
        $url = self::wp_esc_url( (string) ( $item->url ?? '#' ) );
        $html .= '<li class="' . self::wp_esc_attr( implode( ' ', $classes ) ) . '">';
        $html .= '<a class="wp-block-navigation-item__content" href="' . $url . '">';
        $html .= '<span class="wp-block-navigation-item__label">' . $label . '</span>';
        $html .= '</a>';
        if ( $has_children ) {
          $html .= '<button class="wp-block-navigation-submenu__toggle" aria-expanded="false" aria-label="' .
            self::wp_esc_html__( 'Toggle submenu', 'nectar-blocks' ) . '"></button>';
          $html .= '<ul class="wp-block-navigation__submenu-container sub-menu">';
          $html .= $render( $item_id );
          $html .= '</ul>';
        }
        $html .= '</li>';
      }
      return $html;
    };

    $items_html = $render( 0 );
    if ( '' === trim( $items_html ) ) {
      return '';
    }

    return $this->postprocess_ocm_menu_items_html( $items_html, $strip_toggles, $strip_icons );
  }

  private function postprocess_ocm_menu_items_html(
      string $items_html,
      bool $strip_toggles,
      bool $strip_icons
  ): string {
    $items_html = $this->strip_megamenu_markup( $items_html );
    $items_html = $this->strip_link_animation_markup( $items_html );
    $items_html = $this->strip_empty_submenus( $items_html );
    if ( $strip_toggles ) {
      $items_html = $this->strip_submenu_toggle_buttons( $items_html );
    }
    if ( $strip_icons ) {
      $items_html = $this->strip_submenu_icons( $items_html );
    }
    $items_html = $this->add_ocm_toggle_icons( $items_html );
    $items_html = $this->wrap_ocm_menu_item_rows( $items_html );

    // Class mapping for theme expectations.
    // Note: keep core classes too; we only add familiar theme classes.
    $items_html = preg_replace(
        '/\bwp-block-navigation-item\b/',
        'wp-block-navigation-item menu-item',
        $items_html
    );
    $items_html = preg_replace(
        '/\bwp-block-navigation__submenu-container\b/',
        'wp-block-navigation__submenu-container sub-menu',
        $items_html
    );
    $items_html = preg_replace(
        '/\bhas-child\b/',
        'has-child menu-item-has-children',
        $items_html
    );

    return $items_html;
  }

  /**
   * Swap core's responsive menu trigger (when present) to open Nectar's OCM instead,
   * and remove the core responsive overlay markup to avoid duplicate mobile menus.
   */
  private function apply_ocm_mobile_overrides( string $block_content, array $attrs, bool $force_override = false ): string {
    $is_theme_active = $this->is_nectar_blocks_theme_active();
    $should_override = $is_theme_active || $force_override;
    if ( ! $should_override ) {
      return $block_content;
    }
    $block_id = isset( $attrs['blockId'] ) ? self::wp_sanitize_html_class( (string) $attrs['blockId'] ) : '';

    $overlay_devices = isset( $attrs['nectarOverlayMenuDevices'] ) && is_array( $attrs['nectarOverlayMenuDevices'] )
      ? array_values( array_filter( $attrs['nectarOverlayMenuDevices'], 'is_string' ) )
      : [ 'mobile' ];
    $icon_style_raw = isset( $attrs['nectarMenuIconStyle'] ) ? (string) $attrs['nectarMenuIconStyle'] : 'three-lines';
    $icon_style = in_array( $icon_style_raw, [ 'three-lines', 'two-lines', 'text-only' ], true ) ? $icon_style_raw : 'three-lines';
    $icon_size_raw = isset( $attrs['nectarMenuIconSize'] ) ? $attrs['nectarMenuIconSize'] : null;
    $icon_size = 22;
    if ( is_array( $icon_size_raw ) && isset( $icon_size_raw['desktop']['size'] ) ) {
      $icon_size = (int) $icon_size_raw['desktop']['size'];
    } elseif ( is_numeric( $icon_size_raw ) ) {
      // Back-compat: plain number from before responsive conversion.
      $icon_size = (int) $icon_size_raw;
    }

    $link_animation = isset( $attrs['linkAnimation'] ) ? (string) $attrs['linkAnimation'] : 'Default';
    $menu_text_enabled = isset( $attrs['nectarMenuTextEnabled'] ) ? (bool) $attrs['nectarMenuTextEnabled'] : false;
    $icon_text = isset( $attrs['nectarMenuIconText'] ) ? (string) $attrs['nectarMenuIconText'] : '';

    $text_align = isset( $attrs['nectarMenuIconTextAlign'] ) ? (string) $attrs['nectarMenuIconTextAlign'] : 'right';
    $text_align = ( 'left' === $text_align ) ? 'left' : 'right';

    $icon_text_to_render = $menu_text_enabled ? $icon_text : '';

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $block_content,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );

    // Find the open button and convert it into an OCM trigger.
    $open_btns = $this->query_first_node_list( $xpath, self::XPATH_OPEN_BUTTONS );
    if ( $open_btns instanceof \DOMNodeList && $open_btns->length ) {
      foreach ( iterator_to_array( $open_btns ) as $btn ) {
        if ( ! $btn instanceof \DOMElement ) {
          continue;
        }
        $btn->setAttribute( 'type', 'button' );
        $class = $btn->getAttribute( 'class' );
        if ( strpos( ' ' . $class . ' ', ' nectar-ocm-trigger-open ' ) === false ) {
          $btn->setAttribute( 'class', trim( $class . ' nectar-ocm-trigger-open' ) );
        }
        if ( ! $btn->hasAttribute( 'aria-label' ) ) {
          $btn->setAttribute( 'aria-label', self::wp_esc_html__( 'Open menu', 'nectar-blocks-theme' ) );
        }

        // Responsive visibility classes.
        $class = ' ' . $btn->getAttribute( 'class' ) . ' ';
        $show_map = [
          'desktop' => 'nectar-ocm-show-desktop',
          'tablet' => 'nectar-ocm-show-tablet',
          'mobile' => 'nectar-ocm-show-mobile',
        ];
        foreach ( $show_map as $device => $device_class ) {
          if ( in_array( $device, $overlay_devices, true ) && false === strpos( $class, ' ' . $device_class . ' ' ) ) {
            $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' ' . $device_class ) );
            $class = ' ' . $btn->getAttribute( 'class' ) . ' ';
          }
        }

        // Icon style class (our custom option).
        if ( 'two-lines' === $icon_style ) {
          $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-icon--two-lines' ) );
        } elseif ( 'text-only' === $icon_style ) {
          $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-icon--text' ) );
        }

        // Icon size via CSS variable.
        if ( 22 !== $icon_size ) {
          $existing_style = $btn->getAttribute( 'style' );
          $size_style = '--ocm-icon-width: ' . esc_attr( $icon_size ) . 'px';
          $btn->setAttribute( 'style', $existing_style ? $existing_style . '; ' . $size_style : $size_style );
        }

        if ( '' !== trim( $icon_text_to_render ) ) {
          $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-has-text nectar-ocm-text--' . $text_align ) );
        }
        $this->apply_ocm_icon_markup( $dom, $btn, $icon_style, $icon_text_to_render, $text_align, $link_animation );

        // Theme-only: remove core interactivity bindings so the click opens Nectar OCM.
        if ( $is_theme_active || $force_override ) {
          $to_remove = [];
          if ( $btn->hasAttributes() ) {
            foreach ( $btn->attributes as $attr ) {
              if ( $attr instanceof \DOMAttr && strpos( $attr->name, 'data-wp-' ) === 0 ) {
                $to_remove[] = $attr->name;
              }
            }
          }
          foreach ( $to_remove as $attr_name ) {
            $btn->removeAttribute( $attr_name );
          }
          $btn->removeAttribute( 'aria-controls' );
        }

        if ( ! $is_theme_active && $block_id ) {
          $target_id = 'nectar-nb-ocm-' . $block_id;
          $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-nb-ocm-trigger' ) );
          $btn->setAttribute( 'data-nectar-ocm-target', $target_id );
          $btn->setAttribute( 'aria-controls', $target_id );
          $btn->setAttribute( 'aria-expanded', 'false' );
        }
      }
    } else {
      // If core didn't output an open button (e.g. overlayMenu="never"), inject a minimal one.
      if ( $should_override ) {
        $navs = $this->query_first_node_list( $xpath, self::XPATH_NAV_CONTAINERS );
        if ( $navs instanceof \DOMNodeList && $navs->length ) {
          $nav = $navs->item( 0 );
          if ( $nav instanceof \DOMElement ) {
            $btn = $dom->createElement( 'button' );
            $btn->setAttribute( 'type', 'button' );
            $btn->setAttribute( 'class', 'wp-block-navigation__responsive-container-open nectar-ocm-trigger-open' );
            $btn->setAttribute( 'aria-label', self::wp_esc_html__( 'Open menu', 'nectar-blocks-theme' ) );

            // Apply device visibility classes so our CSS can show/hide the trigger by breakpoint.
            $show_map = [
              'desktop' => 'nectar-ocm-show-desktop',
              'tablet' => 'nectar-ocm-show-tablet',
              'mobile' => 'nectar-ocm-show-mobile',
            ];
            foreach ( $show_map as $device => $device_class ) {
              if ( in_array( $device, $overlay_devices, true ) ) {
                $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' ' . $device_class ) );
              }
            }

            // Icon style classes (our custom option).
            if ( 'two-lines' === $icon_style ) {
              $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-icon--two-lines' ) );
            } elseif ( 'text-only' === $icon_style ) {
              $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-icon--text' ) );
            }

            // Icon size via CSS variable.
            if ( 22 !== $icon_size ) {
              $btn->setAttribute( 'style', '--ocm-icon-width: ' . esc_attr( $icon_size ) . 'px' );
            }

            if ( '' !== trim( $icon_text_to_render ) ) {
              $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-ocm-has-text nectar-ocm-text--' . $text_align ) );
            }
            $this->apply_ocm_icon_markup( $dom, $btn, $icon_style, $icon_text_to_render, $text_align, $link_animation );
            if ( ! $is_theme_active && $block_id ) {
              $target_id = 'nectar-nb-ocm-' . $block_id;
              $btn->setAttribute( 'class', trim( $btn->getAttribute( 'class' ) . ' nectar-nb-ocm-trigger' ) );
              $btn->setAttribute( 'data-nectar-ocm-target', $target_id );
              $btn->setAttribute( 'aria-controls', $target_id );
              $btn->setAttribute( 'aria-expanded', 'false' );
            }
            $nav->insertBefore( $btn, $nav->firstChild );
          }
        }
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  private function encode_style_slug( string $value ): string {
    $value = trim( $value );
    $value = preg_replace( '/\s+/', '-', $value );
    $value = strtolower( $value );
    return self::wp_sanitize_html_class( $value );
  }

  private function build_wave_markup( \DOMDocument $dom, string $text ): \DOMElement {
    $wrap = $dom->createElement( 'span' );

    // Screen-reader only text to avoid reading characters individually.
    $sr = $dom->createElement( 'span' );
    $sr->setAttribute( 'class', 'nectar-enhanced-navigation__sr-text' );
    $sr->appendChild( $dom->createTextNode( $text ) );

    $wave = $dom->createElement( 'span' );
    $wave->setAttribute( 'class', 'nectar-enhanced-navigation__wave' );
    $wave->setAttribute( 'aria-hidden', 'true' );

    $chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
    if ( is_array( $chars ) ) {
      foreach ( $chars as $i => $char ) {
        $char_el = $dom->createElement( 'span' );
        $char_el->setAttribute( 'class', 'char' );
        $char_el->setAttribute( 'style', 'animation-delay:' . ( (int) $i * 0.02 ) . 's' );
        $char_el->appendChild( $dom->createTextNode( $char === ' ' ? "\u{00A0}" : $char ) );
        $wave->appendChild( $char_el );
      }
    }

    $wrap->appendChild( $sr );
    $wrap->appendChild( $wave );
    return $wrap;
  }

  private function build_reveal_markup( \DOMDocument $dom, string $text ): \DOMElement {
    $outer = $dom->createElement( 'span' );
    $outer->setAttribute( 'class', 'nectar-enhanced-navigation__text' );

    $inner = $dom->createElement( 'span' );
    $inner->setAttribute( 'class', 'nectar-enhanced-navigation__text__inner' );
    $inner->setAttribute( 'data-text', $text );
    $inner->appendChild( $dom->createTextNode( $text ) );

    $outer->appendChild( $inner );
    return $outer;
  }

  private function apply_link_animation_markup( string $block_content, string $mode ): string {
    if ( empty( $block_content ) ) {
      return $block_content;
    }

    if ( ! in_array( $mode, [ 'Reveal', 'Wave' ], true ) ) {
      return $block_content;
    }

    $dom = new \DOMDocument( '1.0', 'UTF-8' );
    $prev = libxml_use_internal_errors( true );
    // Wrap as a fragment.
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $block_content,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );

    $xpath = new \DOMXPath( $dom );
    // Top-level only: `.wp-block-navigation__container > li.wp-block-navigation-item > .wp-block-navigation-item__content`
    // Avoid touching dropdown/submenu items.
    // Top-level items: direct children of __container OR children inside a page-list <ul> wrapper.
    $container = "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__container ')]";
    $navItem = "/*[self::li][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item ')]";
    $content = "/*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item__content ')]";
    $pageList = "/*[self::ul][contains(concat(' ', normalize-space(@class), ' '), ' wp-block-page-list ')]";
    $nodes = $xpath->query(
        $container . $navItem . $content . ' | ' . $container . $pageList . $navItem . $content
    );
    if ( ! $nodes instanceof \DOMNodeList ) {
      return $block_content;
    }

    foreach ( $nodes as $node ) {
      if ( ! $node instanceof \DOMElement ) {
        continue;
      }

      // Prefer the dedicated label span if present.
      $label = null;
      foreach ( $node->childNodes as $child ) {
        if ( $child instanceof \DOMElement ) {
          $class = $child->getAttribute( 'class' );
          if ( strpos( ' ' . $class . ' ', ' wp-block-navigation-item__label ' ) !== false ) {
            $label = $child;
            break;
          }
        }
      }

      $target = $label instanceof \DOMElement ? $label : $node;

      // Only transform simple text labels to avoid breaking custom markup.
      foreach ( $target->childNodes as $child ) {
        if ( $child instanceof \DOMElement ) {
          continue 2;
        }
      }

      $text = trim( $target->textContent );
      if ( '' === $text ) {
        continue;
      }

      // Clear contents.
      while ( $target->firstChild ) {
        $target->removeChild( $target->firstChild );
      }

      if ( 'Reveal' === $mode ) {
        $target->appendChild( $this->build_reveal_markup( $dom, $text ) );
      } elseif ( 'Wave' === $mode ) {
        $target->appendChild( $this->build_wave_markup( $dom, $text ) );
      }
    }

    return self::strip_xml_declaration( $dom->saveHTML() );
  }

  public function render( string $block_content, array $block ): string {
    // Return original content if empty - fail gracefully.
    if ( empty( $block_content ) || ! is_string( $block_content ) ) {
      return is_string( $block_content ) ? $block_content : '';
    }

    try {
      $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
      $raw_block_content = $block_content;
      $is_theme_active = $this->is_nectar_blocks_theme_active();
      // Only run enhanced logic when the marker is explicitly enabled.
      $has_marker_class = false;
      if ( isset( $attrs['className'] ) && is_string( $attrs['className'] ) ) {
        $has_marker_class = false !== strpos( ' ' . $attrs['className'] . ' ', ' nectar-enhanced-navigation-marker ' );
      }
      $is_enhanced = ! empty( $attrs['nectarEnhanced'] ) || $has_marker_class;
      if ( ! $is_enhanced ) {
        return $block_content;
      }

      $block_id = isset( $attrs['blockId'] ) ? self::wp_sanitize_html_class( (string) $attrs['blockId'] ) : '';
      $typography = isset( $attrs['typography'] ) ? self::wp_sanitize_html_class( (string) $attrs['typography'] ) : '';
      $dropdown_typography = isset( $attrs['dropdownTypography'] ) ? self::wp_sanitize_html_class( (string) $attrs['dropdownTypography'] ) : '';
      $link_animation = isset( $attrs['linkAnimation'] ) ? (string) $attrs['linkAnimation'] : 'Default';
      $dropdown_animation = isset( $attrs['dropdownAnimation'] ) ? (string) $attrs['dropdownAnimation'] : 'fade';
      $overlay_devices = isset( $attrs['nectarOverlayMenuDevices'] ) && is_array( $attrs['nectarOverlayMenuDevices'] )
        ? array_values( array_filter( $attrs['nectarOverlayMenuDevices'], 'is_string' ) )
        : [ 'mobile' ];
      // Treat an empty array as "mobile" to avoid accidentally disabling plugin OCM due to missing defaults.
      if ( empty( $overlay_devices ) ) {
        $overlay_devices = [ 'mobile' ];
      }
      $menu_source = isset( $attrs['nectarOverlayMenuSource'] ) ? (string) $attrs['nectarOverlayMenuSource'] : '';
      // WP 7.0 introduced a template-part-driven overlay via the `overlay`
      // attribute on core/navigation. When the user has opted into that, defer
      // to core: skip rewiring the open button and skip emitting our own OCM
      // panel so both systems don't double-render on top of each other.
      $core_overlay_tp = isset( $attrs['overlay'] ) && is_string( $attrs['overlay'] )
        ? trim( $attrs['overlay'] )
        : '';
      $has_core_overlay_tp = '' !== $core_overlay_tp;
      $should_plugin_ocm = ! $is_theme_active && ! empty( $overlay_devices ) && ! $has_core_overlay_tp;
      $plugin_ocm_markup = '';

    // Ensure we have a stable block id to target for OCM + dynamic styles.
    if ( '' === $block_id ) {
      $p0 = new \WP_HTML_Tag_Processor( $block_content );
      if ( $p0->next_tag() ) {
        $existing_id = (string) $p0->get_attribute( 'id' );
        if ( '' !== $existing_id ) {
          $block_id = self::wp_sanitize_html_class( $existing_id );
        } else {
          $block_id = self::wp_sanitize_html_class( wp_unique_id( 'nectar-nb-nav-' ) );
          $p0->set_attribute( 'id', $block_id );
          $block_content = $p0->get_updated_html();
        }
      }
    }

    // Enqueue the enhanced navigation frontend script for dropdown alignment.
    $this->enqueue_dropdown_alignment_script();

    // Only enqueue the FLIP animation script when morph animation is selected.
    if ( 'morph' === $dropdown_animation ) {
      $this->enqueue_dropdown_flip_assets();
    }

    $classes = [ 'nectar-enhanced-navigation' ];
    if ( $typography ) {
      // Mirror `getTypographyClassName()` in JS.
      if ( strpos( $typography, 'nectar-gt' ) === 0 ) {
        $classes[] = $typography;
      } else {
        $classes[] = 'nectar-font-' . $typography;
      }
    }
    if ( $link_animation && 'Default' !== $link_animation ) {
      $classes[] = 'nectar-enhanced-navigation--' . $this->encode_style_slug( $link_animation );
    }

      $processor = new \WP_HTML_Tag_Processor( $block_content );
    if ( $processor->next_tag() ) {
      // Add id for scoping our inline styles.
      if ( $block_id ) {
        $processor->set_attribute( 'id', $block_id );
      }
      if ( $is_enhanced && ! $has_marker_class ) {
        $processor->add_class( 'nectar-enhanced-navigation-marker' );
        $has_marker_class = true;
      }
      foreach ( $classes as $class ) {
        $processor->add_class( $class );
      }
      if ( $should_plugin_ocm ) {
        $processor->add_class( 'nectar-nb-ocm-enabled' );
      }

      // Add dropdown animation data attribute.
      if ( $dropdown_animation && 'fade' !== $dropdown_animation ) {
        $processor->set_attribute( 'data-dropdown-animation', $dropdown_animation );
      }

      // Theme OCM typography class - store as data attribute for theme JS to read.
      if ( $is_theme_active ) {
        $ocm_typography = isset( $attrs['nectarOcmTypography'] ) ? (string) $attrs['nectarOcmTypography'] : '';
        if ( '' !== $ocm_typography ) {
          // Generate the class name (mirrors getTypographyClassName() in JS).
          $ocm_typography_class = '';
          if ( strpos( $ocm_typography, 'nectar-gt' ) === 0 ) {
            $ocm_typography_class = self::wp_sanitize_html_class( $ocm_typography );
          } else {
            $ocm_typography_class = 'nectar-font-' . self::wp_sanitize_html_class( $ocm_typography );
          }
          if ( '' !== $ocm_typography_class ) {
            $processor->set_attribute( 'data-nectar-ocm-typography', $ocm_typography_class );
          }
        }
      }

      // Add overlay device classes for theme CSS to hide menu/show trigger per breakpoint.
      // Safe even outside the theme because only the theme CSS consumes these.
      $overlay_map = [
        'desktop' => 'nectar-overlay-desktop',
        'tablet' => 'nectar-overlay-tablet',
        'mobile' => 'nectar-overlay-mobile',
      ];
      foreach ( $overlay_map as $device => $device_class ) {
        if ( in_array( $device, $overlay_devices, true ) ) {
          $processor->add_class( $device_class );
        }
      }
      $block_content = $processor->get_updated_html();
    }

    // Apply dropdown typography class to submenu containers.
    if ( $dropdown_typography ) {
      $dropdown_class = '';
      // Mirror `getTypographyClassName()` in JS.
      if ( strpos( $dropdown_typography, 'nectar-gt' ) === 0 ) {
        $dropdown_class = $dropdown_typography;
      } else {
        $dropdown_class = 'nectar-font-' . $dropdown_typography;
      }

      if ( '' !== $dropdown_class ) {
        $p3 = new \WP_HTML_Tag_Processor( $block_content );
        while ( $p3->next_tag() ) {
          $class_attr = (string) $p3->get_attribute( 'class' );
          if ( '' === $class_attr ) {
            continue;
          }
          if ( false !== strpos( ' ' . $class_attr . ' ', ' wp-block-navigation__submenu-container ' ) ) {
            $p3->add_class( $dropdown_class );
          }
        }
        $block_content = $p3->get_updated_html();
      }
    }

    // Theme-only: Apply responsive hide classes when overlay is enabled.
    // The theme uses these responsive utility classes to toggle visibility.
    if ( $is_theme_active && ! empty( $overlay_devices ) ) {
      $p2 = new \WP_HTML_Tag_Processor( $block_content );
      $overlay_tablet = in_array( 'tablet', $overlay_devices, true );
      $overlay_mobile = in_array( 'mobile', $overlay_devices, true );

      $apply_hide_classes = function( \WP_HTML_Tag_Processor $p ) use ( $overlay_tablet, $overlay_mobile ) {
        if ( $overlay_tablet ) {
          $p->add_class( 'nectar-hidden-tablet' );
          // `nectar-hidden-tablet` targets <= tablet max (includes mobile).
          // If tablet overlay is enabled but mobile overlay is NOT enabled, re-show on mobile.
          if ( ! $overlay_mobile ) {
            $p->add_class( 'nectar-visible-mobile' );
          }
        }
        if ( $overlay_mobile ) {
          $p->add_class( 'nectar-hidden-mobile' );
        }
      };

      // Preferred: hide the responsive container wrapper.
      $applied = false;
      while ( $p2->next_tag( [ 'tag_name' => 'div' ] ) ) {
        $class = (string) $p2->get_attribute( 'class' );
        if ( $class && strpos( ' ' . $class . ' ', ' wp-block-navigation__responsive-container ' ) !== false ) {
          $apply_hide_classes( $p2 );
          $applied = true;
          break;
        }
      }

      // Fallback: hide all core menu containers (multiple ULs can exist).
      if ( ! $applied ) {
        while ( $p2->next_tag( [ 'tag_name' => 'ul' ] ) ) {
          $class = (string) $p2->get_attribute( 'class' );
          if ( $class && strpos( ' ' . $class . ' ', ' wp-block-navigation__container ' ) !== false ) {
            $apply_hide_classes( $p2 );
          }
        }
      }

      // Desktop overlay uses the theme CSS rule on `.nectar-overlay-desktop` (kept).
      $block_content = $p2->get_updated_html();
    }

    /*
     * NOTE: previously we added these classes to only the first `ul.wp-block-navigation__container`.
     * Core can output multiple ULs (and/or wrap them), which caused partial hiding on tablet.
     */

    // Capture menu items for the theme's off-canvas menu before we apply fancy label markup.
    $this->capture_ocm_menu_items( $block, $block_content, $menu_source );

    // Process megamenu blocks: move them out of submenu containers and into parent nav items.
    // This must happen BEFORE move_submenu_icons_inside_anchors to avoid processing removed elements.
    $block_content = $this->process_megamenus( $block_content );

    // Move dropdown arrows inside anchors so all styling can target the anchor only.
    // This would eliminate the need for li-based styling workarounds to unify text + arrow
    // appearance (background, padding, border, effects all on one element).
    $block_content = $this->move_submenu_icons_inside_anchors( $block_content );

    // Apply complex label markup after wrapper attribute/class adjustments.
    if ( $link_animation && 'Default' !== $link_animation ) {
      $block_content = $this->apply_link_animation_markup( $block_content, $link_animation );
    }

    // Important: do NOT strip submenu toggle buttons from the main navigation output.
    // Core uses these toggles to open submenus (especially on touch/mobile).
    // We only strip them for the OCM-captured markup (see `capture_ocm_menu_items()`).

    // Build + append plugin OCM markup when theme is NOT active and overlay is enabled.
      if ( $should_plugin_ocm && $block_id ) {
        // Use the original block content (before megamenu extraction/animation markup)
        // so submenu items remain available for the mobile menu.
        $items_html = $this->build_ocm_menu_items_from_source( $block, $raw_block_content, $menu_source, false, false );
      $back_text = self::wp_esc_attr( __( 'Back', 'nectar-blocks' ) );

      // Build OCM container classes including typography if set.
      $ocm_classes = [ 'nectar-nb-ocm', 'nectar-nb-ocm--right' ];
      $ocm_typography = isset( $attrs['nectarOcmTypography'] ) ? (string) $attrs['nectarOcmTypography'] : '';
      if ( '' !== $ocm_typography ) {
        if ( strpos( $ocm_typography, 'nectar-gt' ) === 0 ) {
          $ocm_classes[] = self::wp_sanitize_html_class( $ocm_typography );
        } else {
          $ocm_classes[] = 'nectar-font-' . self::wp_sanitize_html_class( $ocm_typography );
        }
      }

      $plugin_ocm_markup =
        '<div id="nectar-nb-ocm-' . $block_id . '" class="' . implode( ' ', $ocm_classes ) . '" aria-hidden="true" data-nectar-ocm-back-text="' . $back_text . '">' .
        '<div class="nectar-nb-ocm__backdrop" data-nectar-ocm-close></div>' .
        '<div class="nectar-nb-ocm__panel" role="dialog" aria-modal="true" aria-label="' .
          self::wp_esc_html__( 'Menu', 'nectar-blocks' ) . '">' .
          '<button type="button" class="nectar-nb-ocm__back" data-nectar-ocm-back="1" aria-label="' .
            $back_text . '">' .
            '<span class="nectar-nb-ocm__back-icon" aria-hidden="true"></span>' .
          '</button>' .
          '<button type="button" class="nectar-nb-ocm__close" data-nectar-ocm-close aria-label="' .
            self::wp_esc_html__( 'Close menu', 'nectar-blocks' ) . '">' .
            '<span class="nectar-nb-ocm__close-icon" aria-hidden="true"></span>' .
          '</button>' .
          '<nav class="nectar-nb-ocm__nav" aria-label="' . self::wp_esc_html__( 'Menu', 'nectar-blocks' ) . '">' .
            '<ul class="nectar-nb-ocm__menu">' . $items_html . '</ul>' .
          '</nav>' .
        '</div>' .
        '</div>';
    }

    if ( ! $has_core_overlay_tp ) {
      $block_content = $this->apply_ocm_mobile_overrides( $block_content, $attrs, $should_plugin_ocm );
    }
    if ( $plugin_ocm_markup ) {
      $block_content .= $plugin_ocm_markup;
    }

    return $block_content;

    } catch ( \Throwable $e ) {
      // If any error occurs during enhancement, return the original block content.
      // This ensures the navigation still renders, just without Nectar enhancements.
      return is_string( $block_content ) ? $block_content : '';
    }
  }
}

