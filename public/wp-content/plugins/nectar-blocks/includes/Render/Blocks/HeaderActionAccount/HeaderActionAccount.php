<?php

namespace Nectar\Render\Blocks\HeaderActionAccount;

/**
 * Header Account Block Rendering (dynamic)
 *
 * Server-renders the account link so the My Account URL is resolved at render
 * time (never frozen from a save-time browser global) and the href is escaped.
 *
 * @version 3.0.0
 * @since 3.0.0
 */
class HeaderActionAccount {
  private $attrs;

  public function __construct( $block_attributes, $content ) {
    $this->attrs = is_array( $block_attributes ) ? $block_attributes : [];
  }

  /**
   * Resolve the account URL: a custom link if set, otherwise the WooCommerce
   * My Account page resolved at render time, with a sane fallback.
   */
  private function get_account_url(): string {
    $link = $this->attrs['link'] ?? null;
    if ( is_array( $link ) && isset( $link['href'] ) && is_array( $link['href'] ) && ! empty( $link['href']['value'] ) ) {
      // Strip dangerous schemes (javascript:, data:, …) at the source so the
      // value is safe even for non-HTML callers; render() additionally applies
      // esc_url() for the HTML attribute context.
      return esc_url_raw( (string) $link['href']['value'] );
    }
    if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
      $url = wc_get_page_permalink( 'myaccount' );
      if ( ! empty( $url ) ) {
        return (string) $url;
      }
    }
    return home_url( '/my-account/' );
  }

  /**
   * Build the user/account SVG icon for the chosen style.
   */
  private function get_icon( string $style ): string {
    $stroke = '2';
    if ( 'minimal' === $style ) {
      $stroke = '1.5';
    } elseif ( 'bold' === $style ) {
      $stroke = '2.5';
    }
    $stroke = esc_attr( $stroke );
    return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" class="nectar-header-actions__icon">'
      . '<circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="' . $stroke . '"></circle>'
      . '<path d="M5 20c0-3 3-5 7-5s7 2 7 5" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round"></path>'
      . '</svg>';
  }

  /**
   * Build sanitized custom attributes from an allowlist: data- and aria-
   * prefixed names plus a small set of safe global attributes. An allowlist
   * (rather than a blocklist) avoids having to exhaustively enumerate unsafe
   * names — style, on* handlers, id, ping, formaction, download, accesskey
   * are simply not permitted. `rel` and the core `className` are handled in
   * render() so they are merged rather than emitted here.
   *
   * Valueless attributes are intentionally skipped (presence-only attributes
   * are unsupported), matching the prior static save() which required both a
   * name and a value.
   */
  private function get_custom_attrs(): string {
    $link = $this->attrs['link'] ?? [];
    if ( ! is_array( $link ) || empty( $link['customAttributes'] ) || ! is_array( $link['customAttributes'] ) ) {
      return '';
    }
    $safe_globals = [ 'title', 'role', 'lang', 'dir', 'tabindex', 'translate' ];
    $out = '';
    foreach ( $link['customAttributes'] as $attr ) {
      if ( ! is_array( $attr ) ) {
        continue;
      }
      $name = isset( $attr['attribute'] ) ? strtolower( trim( (string) $attr['attribute'] ) ) : '';
      $value = isset( $attr['value'] ) ? (string) $attr['value'] : '';
      if ( '' === $name || '' === $value ) {
        continue;
      }
      $allowed = in_array( $name, $safe_globals, true )
        || 1 === preg_match( '/^(?:data|aria)-[a-z0-9]+(?:-[a-z0-9]+)*$/', $name );
      if ( ! $allowed ) {
        continue;
      }
      $out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
    }
    return $out;
  }

  /**
   * The user-supplied custom `rel` value, if any (last entry wins). Returned
   * raw; the caller escapes it. render() ignores this when the link opens in a
   * new tab so the forced noopener/noreferrer cannot be removed.
   */
  private function get_custom_rel(): string {
    $link = $this->attrs['link'] ?? [];
    if ( ! is_array( $link ) || empty( $link['customAttributes'] ) || ! is_array( $link['customAttributes'] ) ) {
      return '';
    }
    $rel = '';
    foreach ( $link['customAttributes'] as $attr ) {
      if ( is_array( $attr ) && isset( $attr['attribute'] ) && 'rel' === strtolower( trim( (string) $attr['attribute'] ) ) ) {
        $rel = isset( $attr['value'] ) ? (string) $attr['value'] : '';
      }
    }
    return $rel;
  }

  public function render(): string {
    $icon_style = ( isset( $this->attrs['iconStyle'] ) && is_string( $this->attrs['iconStyle'] ) ) ? $this->attrs['iconStyle'] : 'default';
    $url = $this->get_account_url();

    $link = $this->attrs['link'] ?? [];
    $opens_new_tab = is_array( $link ) && ! empty( $link['openInNewTab'] );

    // Merge any custom rel (e.g. nofollow/sponsored) with noopener/noreferrer,
    // which are forced whenever the link opens in a new tab. The custom value
    // is preserved (the old static save let it through) while the security
    // tokens can never be dropped.
    $rel_raw = trim( $this->get_custom_rel() );
    $rel_tokens = '' !== $rel_raw ? preg_split( '/\s+/', $rel_raw ) : [];
    if ( ! is_array( $rel_tokens ) ) {
      $rel_tokens = [];
    }
    if ( $opens_new_tab ) {
      $rel_tokens[] = 'noopener';
      $rel_tokens[] = 'noreferrer';
    }
    $rel_tokens = array_values( array_unique( $rel_tokens ) );
    $target_rel = ( $opens_new_tab ? ' target="_blank"' : '' )
      . ( ! empty( $rel_tokens ) ? ' rel="' . esc_attr( implode( ' ', $rel_tokens ) ) . '"' : '' );

    // Honor the link-control "Open Lightbox" on-click action. The class drives
    // the frontend lightbox handler and is the string the conditional script
    // manager scans for to enqueue nectar-blocks-lightbox.
    $click_event = ( is_array( $link ) && isset( $link['clickEvent'] ) ) ? (string) $link['clickEvent'] : 'regular';
    $link_class = 'nectar-header-account__link';
    if ( 'lightbox' === $click_event ) {
      $link_class .= ' nectar__link--lightbox';
    }

    // Carry the editor's "Additional CSS class(es)" (core className) onto the
    // block root, matching the old static save's useBlockProps.save output.
    $li_class = 'wp-block-nectar-blocks-header-action-account nectar-header-actions__item nectar-header-actions__item--account';
    if ( isset( $this->attrs['className'] ) && is_string( $this->attrs['className'] ) && '' !== $this->attrs['className'] ) {
      $li_class .= ' ' . $this->attrs['className'];
    }

    return '<li class="' . esc_attr( $li_class ) . '">'
      . '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $link_class ) . '"' . $target_rel . $this->get_custom_attrs() . '>'
      . $this->get_icon( $icon_style )
      . '<span class="screen-reader-text">' . esc_html__( 'Account', 'nectar-blocks' ) . '</span>'
      . '</a></li>';
  }
}
