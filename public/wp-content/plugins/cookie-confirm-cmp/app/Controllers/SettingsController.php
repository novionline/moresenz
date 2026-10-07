<?php

namespace CookieConfirm\Controllers;

use CookieConfirm\Enums\ScriptConsent;
use CookieConfirm\Enums\ScriptType;
use CookieConfirm\Services\SettingsRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;

class SettingsController
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRestRoute']);
    }

    public function registerRestRoute(): void
    {
        register_rest_route(COOKIE_CONFIRM_SLUG, '/settings', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ]
        ]);
    }

    public function updateSettings(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Invalid nonce', ['status' => 403]);
        }

        $data = $this->getValidatedData($request->get_params());
        if ($data instanceof WP_Error) {
            return $data;
        }
        SettingsRepository::getInstance()->update($data);

        return new WP_REST_Response(['success'  => true, 'data' => $data], 200);
    }

    public function getValidatedData(array $data): array|WP_Error
    {
        $defaultData = SettingsRepository::getInstance()->defaults();
        $sanitized = $defaultData;

        if (array_diff_key($defaultData, $data)) {
            return new WP_Error('invalid_keys', "Invalid keys", ['status' => 400]);
        }

        // Booleans
        foreach (['adsDataRedaction', 'tagManager', 'urlPassthrough'] as $field) {
            if (!is_bool($data[$field])) {
                return new WP_Error('invalid_bool', "$field must be boolean", ['status' => 400]);
            }
            $sanitized[$field] = (bool)$data[$field];
        }

        if (!is_int($data['waitForUpdate'])) {
            return new WP_Error('invalid_int', 'Wait for update must be integer', ['status' => 400]);
        }
        $sanitized['waitForUpdate'] = absint($data['waitForUpdate']);

        // Script (nullable url)
        if ($data['script'] && !filter_var($data['script'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_script', 'Script must be url or null', ['status' => 400]);
        }
        $sanitized['script'] = is_string($data['script']) ? sanitize_text_field($data['script']) : null;

        // Consents (array)
        $allowedConsents = array_keys($defaultData['consents']);
        if (!is_array($data['consents'])) {
            return new WP_Error('invalid_consents', 'Consents must be array', ['status' => 400]);
        }

        foreach ($data['consents'] as $key => $value) {
            if (!in_array($key, $allowedConsents, true)) {
                return new WP_Error('invalid_consent', "Invalid consent: $key", ['status' => 400]);
            }

            if (!in_array($value, ['granted', 'denied'], true)) {
                return new WP_Error('invalid_consent_value', "Invalid value for $key", ['status' => 400]);
            }

            $sanitized['consents'][$key] = $value;
        }

        foreach (['scripts', 'iframes'] as $field) {
            if (!is_array($data[$field])) {
                return new WP_Error("invalid_$field", ucfirst($field) . ' must be array', ['status' => 400]);
            }

            $validated = $this->validateItems($data[$field]);
            if ($validated instanceof WP_Error) {
                return $validated;
            }

            $sanitized[$field] = $validated;
        }

        return $sanitized;
    }

    private function validateItems(array $items): array|WP_Error
    {
        $allowedTypes    = ScriptType::values();
        $allowedConsents = ScriptConsent::values();
        $validated       = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                return new WP_Error('invalid_item', 'Invalid item', ['status' => 400]);
            }

            if (!isset($item['src'], $item['type'], $item['consent'])) {
                continue;
            }

            if (!in_array($item['type'], $allowedTypes, true)) {
                return new WP_Error('invalid_type', 'Invalid type', ['status' => 400]);
            }

            if (!in_array($item['consent'], $allowedConsents, true)) {
                return new WP_Error('invalid_consent', 'Invalid consent', ['status' => 400]);
            }

            if (!is_string($item['src'])) {
                return new WP_Error('invalid_src', 'Src must be string', ['status' => 400]);
            }

            $src = trim($item['src']);
            if ($item['type'] === ScriptType::REGEX) {
                $valid = @preg_match('/' . $src . '/', '');
                if ($valid === false) {
                    return new WP_Error('invalid_regex', 'Regex is invalid', ['status' => 400]);
                }
            } else {
                $src = sanitize_text_field($src);
            }

            $validated[] = [
                'src'     => $src,
                'type'    => $item['type'],
                'consent' => $item['consent'],
            ];
        }

        return $validated;
    }
}