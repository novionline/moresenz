<?php

/**
 * Nectar Blocks Local Google Fonts Generator
 * Downloads and serves Google Fonts locally for better performance and GDPR compliance.
 *
 * @package Nectar\Render
 * @version 1.0.0
 * @since 3.0
 */

namespace Nectar\Render;

use Nectar\Global_Settings\Global_Typography;
use Nectar\Global_Settings\Nectar_Plugin_Options;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class Local_Google_Fonts {
  private const MAX_FAMILIES = 30;

  private const MAX_FONT_BYTES = 2097152; // 2MB safety cap per file

  private const OPTION_NAME = 'nectar_blocks_local_google_fonts_css';

  private const STORAGE_DIR = 'nectar-blocks/local-fonts';

  private const GENERATION_LOCK_TRANSIENT = 'nectar_lgf_gen_lock';

  private const GENERATION_RETRY_SECONDS = 300; // 5 minutes between retry attempts

  /**
   * Check if local Google fonts is enabled.
   */
  public static function is_enabled(): bool {
    $options = Nectar_Plugin_Options::get_options();
    // Default to false if not set
    return isset( $options['localGoogleFonts'] ) && $options['localGoogleFonts'] === true;
  }

  /**
   * Get the local CSS URL if available and valid.
   * This is the lightweight method called on every page load.
   *
   * Validates the stored URL points to an existing file to handle
   * edge cases like manual file deletion or plugin reinstalls.
   */
  public static function get_local_css_url(): string {
    $url = get_option( self::OPTION_NAME, '' );

    if ( empty( $url ) ) {
      return '';
    }

    // Validate the file still exists (handles manual deletion edge case)
    if ( ! self::validate_stored_url( $url ) ) {
      // File is missing - clear the stored URL so regeneration can occur
      delete_option( self::OPTION_NAME );
      return '';
    }

    return $url;
  }

  /**
   * Validate that a stored CSS URL points to an existing local file.
   * This is a lightweight filesystem check.
   * @since 3.0
   */
  private static function validate_stored_url( string $url ): bool {
    $upload_info = wp_upload_dir();
    if ( ! empty( $upload_info['error'] ) || empty( $upload_info['basedir'] ) ) {
      return false;
    }

    // Extract the relative path from the URL
    $base_url = $upload_info['baseurl'];

    // Strip scheme for comparison (handles http/https mismatch after SSL changes)
    $url_no_scheme = preg_replace( '#^https?://#i', '', $url );
    $base_url_no_scheme = preg_replace( '#^https?://#i', '', $base_url );

    if ( strpos( $url_no_scheme, $base_url_no_scheme ) !== 0 ) {
      return false;
    }

    $relative_path = substr( $url_no_scheme, strlen( $base_url_no_scheme ) );
    // Remove query string if present (e.g., ?v=timestamp)
    $relative_path = strtok( $relative_path, '?' );

    $file_path = $upload_info['basedir'] . $relative_path;

    // Security: Ensure path doesn't escape uploads directory
    $real_basedir = realpath( $upload_info['basedir'] );
    $real_filepath = realpath( dirname( $file_path ) );

    if ( ! $real_basedir || ! $real_filepath || strpos( $real_filepath, $real_basedir ) !== 0 ) {
      return false;
    }

    return file_exists( $file_path );
  }

  /**
   * Check if generation can be attempted.
   * Prevents infinite loops by rate-limiting generation attempts.
   * @since 3.0
   */
  public static function can_attempt_generation(): bool {
    // Check if we're within the retry lockout period
    $lock = get_transient( self::GENERATION_LOCK_TRANSIENT );
    return $lock === false;
  }

  /**
   * Set the generation lock to prevent repeated attempts.
   * @since 3.0
   */
  private static function set_generation_lock(): void {
    set_transient( self::GENERATION_LOCK_TRANSIENT, time(), self::GENERATION_RETRY_SECONDS );
  }

  /**
   * Clear the generation lock (called when cache is cleared).
   * @since 3.0
   */
  private static function clear_generation_lock(): void {
    delete_transient( self::GENERATION_LOCK_TRANSIENT );
  }

  /**
   * Clear all cached transients for local Google fonts.
   * Call this when font options are changed to force regeneration.
   */
  public static function clear_cache() {
    delete_option( self::OPTION_NAME );

    $typography = get_option( Global_Typography::$OPTION_NAME );
    if ( is_array( $typography ) ) {
      $families = self::collect_selected_families( $typography );

      if ( ! empty( $families ) && is_array( $families ) ) {
        $families = array_map( [ __CLASS__, 'sanitize_family_entry' ], $families );
        $families = array_filter( $families );

        if ( ! empty( $families ) && is_array( $families ) ) {
          sort( $families, SORT_STRING | SORT_FLAG_CASE );
          $family_query = implode( '%7C', $families );
          $google_css_url = 'https://fonts.googleapis.com/css?family=' . $family_query . '&display=swap';

          $site_suffix = is_multisite() ? '_' . get_current_blog_id() : '';
          $css_response_key = 'nectar_lgf_css_' . md5( $google_css_url );
          delete_transient( $css_response_key . $site_suffix );

          if ( is_multisite() ) {
            delete_transient( $css_response_key );
          }
        }
      }
    }

    self::clear_preload_transients();
    self::delete_all_font_caches();
    self::clear_generation_lock(); // Allow immediate regeneration after explicit cache clear
  }

  /**
   * Delete all existing font cache directories.
   */
  private static function delete_all_font_caches() {
    $upload_info = wp_upload_dir();
    if ( ! empty( $upload_info['error'] ) ) {
      return;
    }

    $base_dir = $upload_info['basedir'];
    $parent_dir = trailingslashit( $base_dir ) . self::STORAGE_DIR;

    if ( ! is_dir( $parent_dir ) ) {
      return;
    }

    if ( ! function_exists( 'WP_Filesystem' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;

    if ( empty( $wp_filesystem ) ) {
      return;
    }

    $entries = @scandir( $parent_dir );
    $parent_real = @realpath( $parent_dir );

    if ( ! is_array( $entries ) || ! $parent_real ) {
      return;
    }

    foreach ( $entries as $entry ) {
      if ( $entry === '.' || $entry === '..' ) {
        continue;
      }

      // Only delete directories that look like MD5 hashes (our cache dirs)
      if ( ! preg_match( '/^[a-f0-9]{32}$/i', $entry ) ) {
        continue;
      }

      $path = trailingslashit( $parent_dir ) . $entry;
      $path_real = @realpath( $path );

      if ( ! $path_real || strpos( $path_real, $parent_real ) !== 0 ) {
        continue;
      }

      if ( is_dir( $path_real ) ) {
        $wp_filesystem->delete( $path_real, true );
      }
    }
  }

  /**
   * Clear preload transients safely using WordPress APIs.
   */
  private static function clear_preload_transients() {
    if ( ! wp_using_ext_object_cache() ) {
      if ( function_exists( 'delete_expired_transients' ) ) {
        delete_expired_transients( true );
      }
    }
  }

  /**
   * Generate local Google fonts CSS file.
   *
   * This method is rate-limited to prevent infinite loops. If generation fails,
   * it won't be re-attempted for GENERATION_RETRY_SECONDS (5 minutes).
   * The lock is cleared when clear_cache() is called (typography changes).
   */
  public static function generate() {
    // Set the generation lock FIRST to prevent repeated attempts on failure
    // This lock persists for 5 minutes, preventing generate() from running
    // on every page load if there's a persistent error
    self::set_generation_lock();

    if ( ! self::is_enabled() ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    $typography = get_option( Global_Typography::$OPTION_NAME );
    if ( empty( $typography ) || ! is_array( $typography ) ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    $families = self::collect_selected_families( $typography );
    if ( empty( $families ) ) {
      // No Google fonts configured - this is not an error, just nothing to do
      delete_option( self::OPTION_NAME );
      return;
    }

    $families = array_map( [ __CLASS__, 'sanitize_family_entry' ], $families );
    $families = array_filter( $families );
    if ( empty( $families ) ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    if ( count( $families ) > self::MAX_FAMILIES ) {
      $families = array_slice( $families, 0, self::MAX_FAMILIES );
    }

    sort( $families, SORT_STRING | SORT_FLAG_CASE );

    $family_query = implode( '%7C', $families );
    $google_css_url = 'https://fonts.googleapis.com/css?family=' . $family_query . '&display=swap';

    $parsed_css_url = wp_parse_url( $google_css_url );
    if ( empty( $parsed_css_url['scheme'] ) || strtolower( $parsed_css_url['scheme'] ) !== 'https' || empty( $parsed_css_url['host'] ) || strtolower( $parsed_css_url['host'] ) !== 'fonts.googleapis.com' ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    $cache_key = md5( $google_css_url );

    $upload_info = wp_upload_dir();
    if ( ! empty( $upload_info['error'] ) ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    $base_dir = $upload_info['basedir'];
    $base_url = $upload_info['baseurl'];
    $parent_dir = trailingslashit( $base_dir ) . self::STORAGE_DIR;
    $target_dir = trailingslashit( $parent_dir ) . $cache_key;
    $target_url = trailingslashit( $base_url ) . self::STORAGE_DIR . '/' . $cache_key;

    $target_dir = apply_filters( 'nectar_lgf_storage_dir', $target_dir, $cache_key );
    $target_url = apply_filters( 'nectar_lgf_storage_url', $target_url, $cache_key );

    $css_path = $target_dir . '/fonts.css';
    $css_url = $target_url . '/fonts.css';
    if ( is_ssl() ) {
      $css_url = set_url_scheme( $css_url, 'https' );
    }

    if ( ! function_exists( 'wp_mkdir_p' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'WP_Filesystem' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;

    if ( empty( $wp_filesystem ) ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    if ( ! ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $target_dir ) ) ) {
      if ( function_exists( 'wp_mkdir_p' ) ) { wp_mkdir_p( $target_dir ); }
    }

    $dir_writable = true;
    if ( method_exists( $wp_filesystem, 'is_writable' ) ) {
      $dir_writable = (bool) $wp_filesystem->is_writable( $target_dir );
    }
    if ( ! $dir_writable ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    // If this configuration is already cached locally, validate it has font files before reusing.
    if ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $css_path ) ) {
      $has_fonts = self::validate_cached_fonts( $target_dir, $wp_filesystem );
      if ( $has_fonts ) {
        update_option( self::OPTION_NAME, esc_url_raw( $css_url ) );
        return;
      }
    }

    // Fetch CSS with transient cache to avoid repeated network calls.
    $site_suffix = is_multisite() ? '_' . get_current_blog_id() : '';
    $css_response_key = 'nectar_lgf_css_' . md5( $google_css_url ) . $site_suffix;

    $force_refresh = apply_filters( 'nectar_lgf_force_refresh', false );

    $response = $force_refresh ? false : get_transient( $css_response_key );
    if ( false === $response ) {
      $default_args = [
        'timeout' => (int) apply_filters( 'nectar_lgf_http_timeout', 12, 'css' ),
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36'
        ]
      ];
      $args = apply_filters( 'nectar_lgf_http_args', $default_args, 'css', $google_css_url );
      $response = wp_remote_get( $google_css_url, $args );
      if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 200 ) {
        set_transient( $css_response_key, $response, DAY_IN_SECONDS );
      }
    }
    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    $css = wp_remote_retrieve_body( $response );
    if ( ! is_string( $css ) || $css === '' ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    // Strip non-woff2 sources from src declarations.
    $css = preg_replace_callback( '/src\s*:\s*([^;]+);/i', function( $m ) {
      $sources = $m[1];
      preg_match_all( '/url\(([^)]+)\)[^,;]*/i', $sources, $matches );
      $kept = [];
      if ( ! empty( $matches[0] ) ) {
        foreach ( $matches[0] as $i => $full ) {
          $u = trim( $matches[1][$i] );
          $u = trim( $u, "\"'" );
          if ( preg_match( '/^https:\\/\\/fonts\\.gstatic\\.com\//i', $u ) && preg_match( '/\\.woff2(\\?|\\)|$)/i', $u ) ) {
            $kept[] = $full;
          }
        }
      }
      if ( empty( $kept ) ) {
        return $m[0];
      }
      return 'src: ' . implode( ', ', $kept ) . ';';
    }, $css );

    // Mirror font files locally and rewrite urls.
    $max_bytes = (int) apply_filters( 'nectar_lgf_max_font_bytes', self::MAX_FONT_BYTES );
    $css = preg_replace_callback( '/url\(([^)]+)\)/i', function( $m ) use ( $target_dir, $target_url, $wp_filesystem, $max_bytes ) {
      $raw = trim( $m[1] );
      $raw = trim( $raw, "\"'" );

      if ( strpos( $raw, '//' ) === 0 ) {
        $src = 'https:' . $raw;
      } else {
        $src = $raw;
      }

      $p = wp_parse_url( $src );
      if ( empty( $p['scheme'] ) || strtolower( $p['scheme'] ) !== 'https' || empty( $p['host'] ) || strtolower( $p['host'] ) !== 'fonts.gstatic.com' ) {
        return $m[0];
      }

      if ( ! preg_match( '/\\.woff2(\\?|$)/i', $src ) ) {
        return $m[0];
      }

      if ( empty( $p['path'] ) ) {
        return $m[0];
      }
      $filename = basename( $p['path'] );
      if ( ! $filename || ! preg_match( '/^[A-Za-z0-9._-]+\\.woff2$/', $filename ) ) {
        return $m[0];
      }

      $dest_path = trailingslashit( $target_dir ) . $filename;
      $dest_url = trailingslashit( $target_url ) . $filename;
      if ( is_ssl() ) {
        $dest_url = set_url_scheme( $dest_url, 'https' );
      }

      if ( ! ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $dest_path ) ) ) {
        $default_args = [
          'timeout' => (int) apply_filters( 'nectar_lgf_http_timeout', 12, 'font' ),
          'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36'
          ]
        ];
        $args = apply_filters( 'nectar_lgf_http_args', $default_args, 'font', $src );
        $file_resp = wp_remote_get( $src, $args );
        if ( is_wp_error( $file_resp ) || (int) wp_remote_retrieve_response_code( $file_resp ) !== 200 ) {
          return $m[0];
        }
        $len_hdr = wp_remote_retrieve_header( $file_resp, 'content-length' );
        if ( is_numeric( $len_hdr ) && (int) $len_hdr > $max_bytes ) {
          return $m[0];
        }
        $bytes = wp_remote_retrieve_body( $file_resp );
        if ( ! is_string( $bytes ) || $bytes === '' || strlen( $bytes ) > $max_bytes ) {
          return $m[0];
        }
        $written = (bool) $wp_filesystem->put_contents( $dest_path, $bytes, FS_CHMOD_FILE );
        if ( ! $written ) {
          return $m[0];
        }
      }

      if ( ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $dest_path ) ) ) {
        $ver = '';
        if ( function_exists('filemtime') && @file_exists( $dest_path ) ) { $ver = @filemtime( $dest_path ); }
        if ( empty( $ver ) ) { $ver = null; }
        if ( $ver ) { $dest_url = add_query_arg( 'v', $ver, $dest_url ); }
      }
      return 'url(' . esc_url_raw( $dest_url ) . ')';
    }, $css );

    // Ensure font-display: swap is present.
    $css = preg_replace_callback( '/@font-face\s*\{[^}]*\}/i', function( $m ) {
      $block = $m[0];
      if ( stripos( $block, 'font-display' ) === false ) {
        $block = rtrim( $block, '}' ) . 'font-display: swap;}';
      }
      return $block;
    }, $css );

    $css_written = (bool) $wp_filesystem->put_contents( $css_path, $css, FS_CHMOD_FILE );
    if ( ! $css_written || ! file_exists( $css_path ) ) {
      delete_option( self::OPTION_NAME );
      return;
    }

    update_option( self::OPTION_NAME, esc_url_raw( $css_url ) );

    self::cleanup_old_caches( $cache_key, $base_dir );
  }

  /**
   * Collect selected font families from global typography settings.
   */
  private static function collect_selected_families( $typography ) {
    $families = [];

    $all_typography = [];
    if ( isset( $typography['coreTypography'] ) && is_array( $typography['coreTypography'] ) ) {
      $all_typography = array_merge( $all_typography, $typography['coreTypography'] );
    }
    if ( isset( $typography['userTypography'] ) && is_array( $typography['userTypography'] ) ) {
      $all_typography = array_merge( $all_typography, $typography['userTypography'] );
    }

    // Add Google fonts from theme if available
    if ( function_exists( 'Nectar_Dynamic_Fonts' ) ) {
      $Nectar_Dynamic_Fonts = Nectar_Dynamic_Fonts();
      if ( method_exists( $Nectar_Dynamic_Fonts, 'get_used_theme_google_fonts' ) ) {
        $theme_google_fonts = $Nectar_Dynamic_Fonts::get_used_theme_google_fonts();
        $all_typography = array_merge( $all_typography, $theme_google_fonts );
      }
    }

    foreach ( $all_typography as $font ) {
      if ( ! is_array( $font ) ) {
        continue;
      }

      // Skip non-Google fonts
      if ( ! isset( $font['fontSource'] ) || $font['fontSource'] !== 'Google' ) {
        continue;
      }

      // Skip reassigned fonts
      if ( isset( $font['reassigned'] ) ) {
        continue;
      }

      if ( empty( $font['fontFamily'] ) ) {
        continue;
      }

      $family = str_replace( ' ', '+', $font['fontFamily'] );

      $variants = [];
      $weight = isset( $font['fontWeight'] ) ? $font['fontWeight'] : '';

      // Get available variants for this font
      $available_variants = [];
      if ( isset( $font['fontData']['variants'] ) && is_array( $font['fontData']['variants'] ) ) {
        $available_variants = $font['fontData']['variants'];
      }

      // Normalize weight
      if ( $weight === 'regular' ) {
        $weight = '400';
      }

      // Extract numeric weight
      $numeric_weight = preg_replace( '/[^0-9]/', '', (string) $weight );
      $is_italic = ( strpos( $weight, 'italic' ) !== false ) ||
                   ( isset( $font['fontStyle'] ) && strtolower( (string) $font['fontStyle'] ) === 'italic' );

      // Determine the variant to request
      $variant = '';
      if ( $numeric_weight !== '' ) {
        $variant = $numeric_weight . ( $is_italic ? 'italic' : '' );
      } elseif ( $is_italic ) {
        $variant = '400italic';
      }

      // Validate variant against available variants if we have that data
      if ( ! empty( $variant ) && ! empty( $available_variants ) ) {
        // Normalize variant for comparison (Google uses 'regular' for 400, 'italic' for 400italic)
        $variant_check = $variant;
        // Must replace '400italic' before '400' to avoid creating 'regularitalic'
        $variant_regular = str_replace( [ '400italic', '400' ], [ 'italic', 'regular' ], $variant );

        $variant_exists = in_array( $variant, $available_variants, true ) ||
                          in_array( $variant_check, $available_variants, true ) ||
                          in_array( $variant_regular, $available_variants, true );

        if ( ! $variant_exists && $is_italic ) {
          // Italic variant doesn't exist - fall back to non-italic
          $fallback_variant = $numeric_weight !== '' ? $numeric_weight : '400';
          $fallback_regular = $fallback_variant === '400' ? 'regular' : $fallback_variant;

          if ( in_array( $fallback_variant, $available_variants, true ) ||
               in_array( $fallback_regular, $available_variants, true ) ) {
            $variant = $fallback_variant;
          }
        }
      }

      if ( ! empty( $variant ) ) {
        $variants[] = $variant;
      }

      if ( ! empty( $variants ) ) {
        $families[] = $family . ':' . implode( ',', array_unique( $variants ) );
      } else {
        $families[] = $family;
      }
    }

    return array_values( array_unique( $families ) );
  }

  /**
   * Sanitize a font family entry.
   */
  private static function sanitize_family_entry( $entry ) {
    if ( ! is_string( $entry ) ) {
      return '';
    }
    $entry = trim( $entry );
    $entry = preg_replace( '/[^A-Za-z0-9+:_,-]/', '', $entry );
    $entry = preg_replace( '/,+/', ',', $entry );

    if ( ! preg_match( '/^[A-Za-z0-9+_-]+(?::(?:(?:[0-9]{2,4}(?:italic)?)|italic)(?:,(?:(?:[0-9]{2,4}(?:italic)?)|italic))*)?$/', $entry ) ) {
      $family_only = preg_replace( '/:.*/', '', $entry );
      if ( preg_match( '/^[A-Za-z0-9+_-]+$/', $family_only ) ) {
        return $family_only;
      }
      return '';
    }
    return $entry;
  }

  /**
   * Clean up old font cache directories.
   */
  private static function cleanup_old_caches( $current_key, $base_dir ) {
    $parent = trailingslashit( $base_dir ) . self::STORAGE_DIR;
    if ( ! is_dir( $parent ) ) {
      return;
    }
    if ( ! function_exists( 'WP_Filesystem' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    global $wp_filesystem;
    if ( empty( $wp_filesystem ) ) {
      return;
    }
    $entries = @scandir( $parent );
    $parent_real = @realpath( $parent );
    if ( ! is_array( $entries ) || ! $parent_real ) {
      return;
    }
    foreach ( $entries as $entry ) {
      if ( $entry === '.' || $entry === '..' ) {
        continue;
      }
      if ( $entry === $current_key || ! preg_match( '/^[a-f0-9]{32}$/i', $entry ) ) {
        continue;
      }
      $path = trailingslashit( $parent ) . $entry;
      $path_real = @realpath( $path );
      if ( ! $path_real || strpos( $path_real, $parent_real ) !== 0 ) {
        continue;
      }
      if ( is_dir( $path_real ) ) {
        $wp_filesystem->delete( $path_real, true );
      }
    }
  }

  /**
   * Validate that a cached font directory contains font files.
   */
  private static function validate_cached_fonts( $target_dir, $wp_filesystem ) {
    if ( ! is_object( $wp_filesystem ) ) {
      return false;
    }
    $entries = @scandir( $target_dir );
    if ( ! is_array( $entries ) ) {
      return false;
    }
    foreach ( $entries as $entry ) {
      if ( $entry === '.' || $entry === '..' || $entry === 'fonts.css' ) {
        continue;
      }
      if ( preg_match( '/\.woff2$/i', $entry ) ) {
        $file_path = trailingslashit( $target_dir ) . $entry;
        if ( method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $file_path ) ) {
          return true;
        }
      }
    }
    return false;
  }
}

