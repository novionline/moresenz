<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class CodeEditorComponent
 * Enqueues WordPress code editor (CodeMirror) and attaches it to the snippet code textarea.
 * @package NoviOnline\CodeSnippets
 */
class CodeEditorComponent extends Singleton
{
    protected function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueueCodeEditor']);
    }

    /**
     * Enqueue code editor only on snippet edit screen
     * @return void
     */
    public function maybeEnqueueCodeEditor(string $hook): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        //only on edit screen so the code textarea exists
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        $cssSettings = wp_enqueue_code_editor(['type' => 'text/css', 'codemirror' => ['lint' => true]]);
        if ($cssSettings === false) {
            return;
        }
        //enqueue JSHint so JS is validated in the code editor when type is JS (wp_enqueue_code_editor only loads linters for the type you pass)
        wp_enqueue_script('jshint');
        wp_enqueue_script('jsonlint');
        $jsSettings = wp_get_code_editor_settings(['type' => 'text/javascript', 'codemirror' => ['lint' => true]]);
        //ensure mode is 'css' / 'javascript' so WordPress configureLinting adds CSSLint/JSHint
        if (is_array($cssSettings['codemirror'])) {
            $cssSettings['codemirror']['mode'] = 'css';
        }
        if (is_array($jsSettings['codemirror'])) {
            $jsSettings['codemirror']['mode'] = 'javascript';
        }
        wp_enqueue_script(
            'js-beautify',
            'https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.4/beautify.min.js',
            [],
            '1.15.4',
            true
        );
        wp_enqueue_script(
            'js-beautify-css',
            'https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.4/beautify-css.min.js',
            ['js-beautify'],
            '1.15.4',
            true
        );
        wp_localize_script('code-editor', 'ncsCodeEditorSettings', [
            'css' => $cssSettings,
            'js' => $jsSettings,
            'validateUrl' => admin_url('admin-ajax.php'),
            'validateNonce' => wp_create_nonce('ncs_validate_snippet'),
            'validityValidText' => __('Valid. This snippet will be output on the front-end.', NoviCodeSnippets::TEXT_DOMAIN),
            'validityInvalidText' => __('Invalid. This snippet will NOT be shown on the front-end.', NoviCodeSnippets::TEXT_DOMAIN),
            'beautifyButtonText' => __('Beautify', NoviCodeSnippets::TEXT_DOMAIN)
        ]);
        wp_enqueue_script(
            'ncs-code-editor-init',
            NCS_PLUGIN_URL . '/assets/admin-code-editor.js',
            ['code-editor', 'js-beautify-css'],
            '1.0.0',
            true
        );
        wp_enqueue_style(
            'ncs-code-editor-dark',
            NCS_PLUGIN_URL . '/assets/admin-code-editor-dark.css',
            ['code-editor'],
            '1.0.0'
        );
    }
}
