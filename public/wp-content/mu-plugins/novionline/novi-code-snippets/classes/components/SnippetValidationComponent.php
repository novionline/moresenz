<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class SnippetValidationComponent
 * Validates snippet code on save; sets snippet_valid meta and error details.
 * Renders validity block below code editor; AJAX validate on blur returns details.
 * @package NoviOnline\CodeSnippets
 */
class SnippetValidationComponent extends Singleton
{
    protected function __construct()
    {
        //run after ACF has saved fields so get_field() returns the new values (save_post runs before ACF writes)
        add_action('acf/save_post', [$this, 'validateOnSave'], 20, 1);
        add_action('acf/render_field/name=snippet_code', [$this, 'renderValidityBlock'], 10, 1);
        add_action('wp_ajax_ncs_validate_snippet', [$this, 'ajaxValidateSnippet']);
    }

    /**
     * Run validation after ACF has saved; set snippet_valid and optional error details meta
     * @param int|string $postId post ID (acf/save_post only passes this)
     * @return void
     */
    public function validateOnSave($postId): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        if (!is_numeric($postId) || get_post_type((int) $postId) !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        $postId = (int) $postId;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $code = get_field('snippet_code', $postId);
        $type = get_field('snippet_type', $postId);
        if ($type !== 'css' && $type !== 'js') {
            $type = 'css';
        }
        $code = is_string($code) ? $code : '';
        //safety net if an older save path already stripped content:\eXXXX escapes
        $restored = SnippetCodeEscaping::restoreStrippedCssContentEscapes($code);
        if ($restored !== $code) {
            $code = $restored;
            SnippetCodeEscaping::updateMeta($postId, 'snippet_code', $code);
        }
        $result = SnippetValidator::validateWithDetails($code, $type);
        update_post_meta($postId, SnippetValidator::META_VALID, $result['valid'] ? '1' : '0');
        if ($result['valid']) {
            delete_post_meta($postId, SnippetValidator::META_VALIDATION_ERROR);
            if (trim($code) !== '') {
                try {
                    $minified = $type === 'css' ? Minify::css($code) : Minify::js($code);
                    SnippetCodeEscaping::updateMeta($postId, SnippetValidator::META_MINIFIED, $minified);
                    $post = get_post($postId);
                    if ($post) {
                        if ($type === 'css') {
                            SnippetAssetCache::writeForPost($post, 'css', $minified);
                            SnippetAssetCache::deleteForPost($postId, 'js');
                        } else {
                            SnippetAssetCache::writeForPost($post, 'js', $minified);
                            SnippetAssetCache::deleteForPost($postId, 'css');
                        }
                    }
                } catch (\Throwable $e) {
                    delete_post_meta($postId, SnippetValidator::META_MINIFIED);
                    SnippetAssetCache::deleteForPost($postId);
                }
            } else {
                delete_post_meta($postId, SnippetValidator::META_MINIFIED);
                SnippetAssetCache::deleteForPost($postId);
            }
        } else {
            update_post_meta($postId, SnippetValidator::META_VALIDATION_ERROR, [
                'message' => $result['message'],
                'line' => $result['line'],
                'column' => $result['column']
            ]);
            delete_post_meta($postId, SnippetValidator::META_MINIFIED);
            SnippetAssetCache::deleteForPost($postId);
        }
    }

    /**
     * Render validity status block below the code field (always visible; updated on blur via JS).
     * @param array $field ACF field array
     * @return void
     */
    public function renderValidityBlock($field): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        $postId = (int) get_the_ID();
        $valid = $postId ? get_post_meta($postId, SnippetValidator::META_VALID, true) : '1';
        $error = $postId ? get_post_meta($postId, SnippetValidator::META_VALIDATION_ERROR, true) : null;
        $isValid = ($valid === '1');
        $message = is_array($error) && !empty($error['message']) ? $error['message'] : null;
        $line = is_array($error) && isset($error['line']) ? (int) $error['line'] : null;
        $column = is_array($error) && isset($error['column']) ? (int) $error['column'] : null;

        echo '<div id="ncs-validity-feedback" class="ncs-validity-feedback" aria-live="polite">';
        if ($isValid) {
            echo '<span class="ncs-validity-valid">' . esc_html__('Valid. This snippet will be output on the front-end.', NoviCodeSnippets::TEXT_DOMAIN) . '</span>';
        } else {
            echo '<span class="ncs-validity-invalid">';
            echo esc_html__('Invalid. This snippet will NOT be shown on the front-end.', NoviCodeSnippets::TEXT_DOMAIN);
            if ($message) {
                echo ' <span class="ncs-validity-message">' . esc_html($message) . '</span>';
            }
            $loc = [];
            if ($line !== null) {
                $loc[] = sprintf(__('line %d', NoviCodeSnippets::TEXT_DOMAIN), $line);
            }
            if ($column !== null) {
                $loc[] = sprintf(__('column %d', NoviCodeSnippets::TEXT_DOMAIN), $column);
            }
            if (!empty($loc)) {
                echo ' <span class="ncs-validity-location">(' . esc_html(implode(', ', $loc)) . ')</span>';
            }
            echo '</span>';
        }
        echo '</div>';
    }

    /**
     * AJAX: validate snippet and return valid flag + message/line/column for display
     * @return void
     */
    public function ajaxValidateSnippet(): void
    {
        if (!Capability::userCanManageSnippets()) {
            wp_send_json_error(['valid' => false, 'message' => 'Unauthorized'], 403);
        }
        check_ajax_referer('ncs_validate_snippet', 'nonce');
        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : '';
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'css';
        if ($type !== 'css' && $type !== 'js') {
            $type = 'css';
        }
        $code = is_string($code) ? $code : '';
        $result = SnippetValidator::validateWithDetails($code, $type);
        wp_send_json_success([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'line' => $result['line'],
            'column' => $result['column']
        ]);
    }
}
