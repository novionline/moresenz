<?php

namespace Nectar\API\Global_Settings;
use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Utilities\Log;
use Nectar\Global_Settings\Nectar_Adobe_Fonts;

/**
 * Adobe_Fonts_API
 * @version 3.0.0
 * @since 3.0.0
 */
class Adobe_Fonts_API implements API_Route {
  const API_BASE = '/settings/adobe_fonts';

  const TYPEKIT_API_BASE = 'https://typekit.com/api/v1/json';

  // CSS font-family names: letters, numbers, hyphens, spaces.
  const CSS_NAME_PATTERN = '/^[a-zA-Z0-9\- ]+$/';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_adobe_fonts'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/connect', [
      'callback' => [$this, 'connect'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/disconnect', [
      'callback' => [$this, 'disconnect'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/refresh', [
      'callback' => [$this, 'refresh'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  /**
   * Get stored Adobe Fonts data with masked token.
   */
  public function get_adobe_fonts() {
    $data = Nectar_Adobe_Fonts::get_options();

    if (empty($data) || ! is_array($data)) {
      return new \WP_REST_Response([
        'token' => '',
        'isConnected' => false,
        'kits' => [],
        'fonts' => []
      ], 200);
    }

    $data['token'] = $this->mask_token($data['token'] ?? '');

    return new \WP_REST_Response($data, 200);
  }

  /**
   * Connect to Adobe Fonts by saving token and fetching kits.
   */
  public function connect(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $token = sanitize_text_field($json_body['token'] ?? '');

    if (empty($token)) {
      return new \WP_REST_Response([
        'status' => 'failure',
        'message' => 'Token is required.'
      ], 400);
    }

    $result = $this->fetch_and_store_kits($token);

    if (is_wp_error($result)) {
      return new \WP_REST_Response([
        'status' => 'failure',
        'message' => $result->get_error_message()
      ], 400);
    }

    // Return stored data with masked token.
    $data = Nectar_Adobe_Fonts::get_options();
    $data['token'] = $this->mask_token($data['token']);

    return new \WP_REST_Response([
      'status' => 'success',
      'data' => $data
    ], 200);
  }

  /**
   * Disconnect Adobe Fonts — clears all stored data.
   */
  public function disconnect() {
    Nectar_Adobe_Fonts::update_options([
      'token' => '',
      'isConnected' => false,
      'kits' => [],
      'fonts' => []
    ]);

    return new \WP_REST_Response([
      'status' => 'success'
    ], 200);
  }

  /**
   * Refresh Adobe Fonts — re-fetch kits using stored token.
   */
  public function refresh() {
    $data = Nectar_Adobe_Fonts::get_options();

    if (empty($data['token'])) {
      return new \WP_REST_Response([
        'status' => 'failure',
        'message' => 'No token stored. Please connect first.'
      ], 400);
    }

    $result = $this->fetch_and_store_kits($data['token']);

    if (is_wp_error($result)) {
      return new \WP_REST_Response([
        'status' => 'failure',
        'message' => $result->get_error_message()
      ], 400);
    }

    $updated_data = Nectar_Adobe_Fonts::get_options();
    $updated_data['token'] = $this->mask_token($updated_data['token']);

    return new \WP_REST_Response([
      'status' => 'success',
      'data' => $updated_data
    ], 200);
  }

  /**
   * Fetches all kits from Adobe API and stores the result.
   *
   * @param string $token Adobe Fonts API token.
   * @return true|\WP_Error True on success, WP_Error on failure.
   */
  private function fetch_and_store_kits(string $token) {
    // 1. Fetch kit list.
    $kits_response = $this->typekit_request('/kits', $token);

    if (is_wp_error($kits_response)) {
      Log::debug('Adobe Fonts: Failed to fetch kits', ['error' => $kits_response->get_error_message()]);
      return new \WP_Error('adobe_fonts_error', 'Failed to connect to Adobe Fonts. Please verify your API token.');
    }

    if (! isset($kits_response['kits']) || ! is_array($kits_response['kits'])) {
      return new \WP_Error('adobe_fonts_error', 'Unexpected response from Adobe Fonts API.');
    }

    // 2. Fetch published details for each kit.
    $kits_data = [];
    $all_fonts = [];

    foreach ($kits_response['kits'] as $kit_summary) {
      $kit_id = sanitize_key($kit_summary['id'] ?? '');
      if (empty($kit_id)) {
        continue;
      }

      $kit_details = $this->typekit_request('/kits/' . $kit_id . '/published', $token);

      if (is_wp_error($kit_details) || ! isset($kit_details['kit'])) {
        Log::debug('Adobe Fonts: Failed to fetch kit details', ['kit_id' => $kit_id]);
        continue;
      }

      $kit = $kit_details['kit'];
      $families = [];

      if (isset($kit['families']) && is_array($kit['families'])) {
        foreach ($kit['families'] as $family) {
          $css_name = '';
          if (! empty($family['css_names']) && is_array($family['css_names'])) {
            $css_name = sanitize_text_field($family['css_names'][0]);
          }

          // Skip fonts with invalid CSS names to prevent CSS injection.
          if (! empty($css_name) && ! preg_match(self::CSS_NAME_PATTERN, $css_name)) {
            Log::debug('Adobe Fonts: Skipping font with invalid CSS name', ['css_name' => $css_name]);
            continue;
          }

          // Validate and sanitize variations before parsing.
          $raw_variations = $family['variations'] ?? [];
          $safe_variations = array_filter($raw_variations, function($v) {
            return is_string($v) && preg_match('/^[a-z][1-9]$/', $v);
          });
          $parsed_variations = $this->parse_variations($safe_variations);

          $family_data = [
            'name' => sanitize_text_field($family['name'] ?? ''),
            'slug' => sanitize_text_field($family['slug'] ?? ''),
            'cssName' => $css_name,
            'variations' => array_values($safe_variations)
          ];

          $families[] = $family_data;

          // Flatten for the typography selector.
          $all_fonts[] = [
            'family' => $css_name,
            'name' => sanitize_text_field($family['name'] ?? ''),
            'kitId' => $kit_id,
            'variants' => $parsed_variations
          ];
        }
      }

      $kits_data[] = [
        'id' => $kit_id,
        'name' => sanitize_text_field($kit['name'] ?? $kit_id),
        'families' => $families
      ];
    }

    // 3. Store everything.
    Nectar_Adobe_Fonts::update_options([
      'token' => $token,
      'isConnected' => true,
      'kits' => $kits_data,
      'fonts' => $all_fonts
    ]);

    return true;
  }

  /**
   * Make a request to the Typekit API.
   *
   * @param string $endpoint API endpoint path (e.g., '/kits').
   * @param string $token API token.
   * @return array|\WP_Error Decoded JSON response or WP_Error.
   */
  private function typekit_request(string $endpoint, string $token) {
    // Strip any CRLF chars to prevent HTTP header injection.
    $safe_token = str_replace(["\r", "\n"], '', $token);

    $response = wp_remote_get(self::TYPEKIT_API_BASE . $endpoint, [
      'headers' => [
        'X-Typekit-Token' => $safe_token
      ],
      'timeout' => 15
    ]);

    if (is_wp_error($response)) {
      return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
      return new \WP_Error(
          'typekit_api_error',
          'Adobe Fonts API returned status ' . $status_code
      );
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return new \WP_Error('typekit_api_error', 'Failed to parse Adobe Fonts API response.');
    }

    return $decoded;
  }

  /**
   * Parse Adobe variation codes into standard weight strings.
   *
   * Adobe uses a compact format: first char = style (n=normal, i=italic),
   * second char = weight digit (1-9 mapping to 100-900).
   * e.g., 'n4' = '400', 'i7' = '700italic'
   *
   * @param array $variations Array of Adobe variation codes.
   * @return array Standard weight strings (e.g., ['300', '400', '700', '400italic']).
   */
  private function parse_variations(array $variations): array {
    $weight_map = [
      '1' => '100', '2' => '200', '3' => '300', '4' => '400',
      '5' => '500', '6' => '600', '7' => '700', '8' => '800', '9' => '900'
    ];

    $parsed = [];

    foreach ($variations as $variation) {
      if (strlen($variation) < 2) {
        continue;
      }

      $style_char = $variation[0];
      $weight_char = $variation[1];
      $weight = $weight_map[$weight_char] ?? '400';

      if ($style_char === 'i') {
        $parsed[] = $weight . 'italic';
      } else {
        $parsed[] = $weight;
      }
    }

    return $parsed;
  }

  /**
   * Mask a token for display, showing only first 4 and last 4 characters.
   *
   * @param string $token The token to mask.
   * @return string Masked token.
   */
  private function mask_token(string $token): string {
    if (empty($token)) {
      return '';
    }

    $len = strlen($token);

    if ($len <= 8) {
      return str_repeat('*', $len);
    }

    return substr($token, 0, 4) . str_repeat('*', $len - 8) . substr($token, -4);
  }
}
