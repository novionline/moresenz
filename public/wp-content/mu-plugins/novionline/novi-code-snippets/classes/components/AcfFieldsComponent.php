<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class AcfFieldsComponent
 * Registers ACF field groups for code snippets: main (enable, type, code) and sidebar (priority range 1–1000).
 * @package NoviOnline\CodeSnippets
 */
class AcfFieldsComponent extends Singleton
{
    const META_PRIORITY = 'snippet_priority';

    const META_OWNER = 'snippet_owner';

    protected function __construct()
    {
        add_action('acf/init', [$this, 'registerFieldGroup']);
        add_action('acf/init', [$this, 'registerPriorityFieldGroup']);
        add_filter('acf/load_field/name=snippet_priority', [$this, 'defaultPriorityForNewSnippet']);
    }

    /**
     * Default priority = (published snippet count) + 1 for new snippets
     * @param array $field
     * @return array
     */
    public function defaultPriorityForNewSnippet(array $field): array
    {
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if ($postId > 0) {
            return $field;
        }
        $count = (int) wp_count_posts(CodeSnippetsPostType::POST_TYPE)->publish;
        $field['default_value'] = max(1, $count + 1);
        return $field;
    }

    /**
     * Register the snippet field group (only when user can manage)
     * @return void
     */
    public function registerFieldGroup(): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        acf_add_local_field_group([
            'key' => 'group_ncs_snippet',
            'title' => __('Snippet settings', NoviCodeSnippets::TEXT_DOMAIN),
            'fields' => [
                [
                    'key' => 'field_ncs_enabled',
                    'label' => __('Enable snippet', NoviCodeSnippets::TEXT_DOMAIN),
                    'name' => 'snippet_enabled',
                    'type' => 'true_false',
                    'instructions' => __('When enabled and valid, this snippet will be output on every page.', NoviCodeSnippets::TEXT_DOMAIN),
                    'default_value' => 1,
                    'ui' => 1,
                    'wrapper' => ['width' => '50']
                ],
                [
                    'key' => 'field_ncs_type',
                    'label' => __('Type', NoviCodeSnippets::TEXT_DOMAIN),
                    'name' => 'snippet_type',
                    'type' => 'select',
                    'choices' => [
                        'css' => 'CSS',
                        'js' => 'JavaScript'
                    ],
                    'default_value' => 'css',
                    'wrapper' => ['width' => '50']
                ],
                [
                    'key' => 'field_ncs_code',
                    'label' => __('Code', NoviCodeSnippets::TEXT_DOMAIN),
                    'name' => 'snippet_code',
                    'type' => 'textarea',
                    'rows' => 16,
                    'placeholder' => __('Paste your CSS or JavaScript here.', NoviCodeSnippets::TEXT_DOMAIN),
                    'wrapper' => ['class' => 'ncs-code-field']
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => CodeSnippetsPostType::POST_TYPE
                    ]
                ]
            ]
        ]);
    }

    /**
     * Register sidebar ACF group with priority range slider (1–1000)
     * @return void
     */
    public function registerPriorityFieldGroup(): void
    {
        if (!Capability::userCanManageSnippets()) {
            return;
        }
        acf_add_local_field_group([
            'key' => 'group_ncs_snippet_priority',
            'title' => __('Load order', NoviCodeSnippets::TEXT_DOMAIN),
            'fields' => [
                [
                    'key' => 'field_ncs_owner',
                    'label' => __('Owner', NoviCodeSnippets::TEXT_DOMAIN),
                    'name' => 'snippet_owner',
                    'type' => 'select',
                    'choices' => [
                        'peter' => 'Peter',
                        'philip' => 'Philip',
                        'other' => 'Other'
                    ],
                    'required' => 1,
                    'default_value' => ''
                ],
                [
                    'key' => 'field_ncs_priority',
                    'label' => __('Priority', NoviCodeSnippets::TEXT_DOMAIN),
                    'name' => 'snippet_priority',
                    'type' => 'range',
                    'instructions' => __('Lower numbers load first. Snippets are ordered by type (CSS first, JS last) then by this priority.', NoviCodeSnippets::TEXT_DOMAIN),
                    'min' => 1,
                    'max' => 1000,
                    'step' => 1,
                    'default_value' => ''
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => CodeSnippetsPostType::POST_TYPE
                    ]
                ]
            ],
            'position' => 'side'
        ]);
    }
}
