<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class NoviCodeSnippets
 * @package NoviOnline\CodeSnippets
 */
class NoviCodeSnippets extends Singleton
{
    const TEXT_DOMAIN = 'novi-code-snippets';

    protected function __construct()
    {
        if (!defined('NCS_PLUGIN_PATH')) {
            define('NCS_PLUGIN_PATH', dirname(__DIR__));
        }
        if (!defined('NCS_PLUGIN_URL')) {
            define('NCS_PLUGIN_URL', get_home_url() . '/wp-content/mu-plugins/novionline/novi-code-snippets');
        }
        self::initComponents();
    }

    /**
     * Init components: post type and admin only when user can manage; output always
     * @return void
     */
    public static function initComponents(): void
    {
        //always register CPT so front-end can query snippets; UI gated inside components
        CodeSnippetsPostType::getInstance();
        SnippetsOutputComponent::getInstance();
        $userCanManage = Capability::userCanManageSnippets();
        if ($userCanManage) {
            AcfFieldsComponent::getInstance();
            CodeEditorComponent::getInstance();
            SnippetValidationComponent::getInstance();
            SnippetRevisionsComponent::getInstance();
        }
    }

    /**
     * Whether current user can manage code snippets (Novi admin only)
     * @return bool
     */
    public static function userCanManageSnippets(): bool
    {
        return Capability::userCanManageSnippets();
    }
}
