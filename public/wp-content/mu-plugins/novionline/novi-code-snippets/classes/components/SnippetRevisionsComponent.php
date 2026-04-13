<?php

namespace NoviOnline\CodeSnippets;

use NoviOnline\Core\Singleton;

/**
 * Class SnippetRevisionsComponent
 * Ensures code snippet revisions are created when only meta is changed, and that snippet meta
 * is stored on revisions (and restored when a revision is restored).
 * @package NoviOnline\CodeSnippets
 */
class SnippetRevisionsComponent extends Singleton
{
    /**
     * Meta keys that define the snippet and should be revisioned (ACF + derived)
     * @var string[]
     */
    private const REVISIONED_META_KEYS = [
        'snippet_enabled',
        'snippet_type',
        'snippet_code',
        'snippet_owner',
        'snippet_priority',
        SnippetValidator::META_VALID,
        SnippetValidator::META_VALIDATION_ERROR,
        SnippetValidator::META_MINIFIED
    ];

    /**
     * ACF field key => meta name for comparing submitted form data
     * @var array<string, string>
     */
    private const ACF_FIELD_KEY_TO_META = [
        'field_ncs_enabled' => 'snippet_enabled',
        'field_ncs_type' => 'snippet_type',
        'field_ncs_code' => 'snippet_code',
        'field_ncs_owner' => 'snippet_owner',
        'field_ncs_priority' => 'snippet_priority'
    ];

    /**
     * Stored meta before update (for comparing in wp_save_post_revision_post_has_changed)
     * @var array<int, array<string, mixed>>
     */
    private static $metaBeforeUpdate = [];

    protected function __construct()
    {
        add_filter('wp_post_revision_meta_keys', [$this, 'revisionedMetaKeys'], 10, 2);
        add_action('pre_post_update', [$this, 'storeMetaBeforeUpdate'], 10, 2);
        add_filter('wp_save_post_revision_post_has_changed', [$this, 'postHasChangedWhenMetaChanged'], 10, 3);
        //after ACF has saved: copy current (new) meta to the revision that was just created
        add_action('acf/save_post', [$this, 'copyMetaToLatestRevision'], 99, 1);
    }

    /**
     * Register snippet meta keys so they are stored on revisions (WP 6.4+)
     * @param array<string> $keys
     * @param string $postType
     * @return array<string>
     */
    public function revisionedMetaKeys(array $keys, string $postType): array
    {
        if ($postType !== CodeSnippetsPostType::POST_TYPE) {
            return $keys;
        }
        return array_merge($keys, self::REVISIONED_META_KEYS);
    }

    /**
     * Before post update, store current meta so we can detect meta-only changes
     * @param int $postId
     * @param array<string, mixed> $data
     * @return void
     */
    public function storeMetaBeforeUpdate(int $postId, array $data): void
    {
        if (!isset($data['post_type']) || $data['post_type'] !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $meta = [];
        foreach (self::ACF_FIELD_KEY_TO_META as $metaKey) {
            $value = get_post_meta($postId, $metaKey, true);
            $meta[$metaKey] = $value;
        }
        self::$metaBeforeUpdate[$postId] = $meta;
    }

    /**
     * When only meta changed, report post as changed so WordPress creates a revision
     * @param bool $postHasChanged
     * @param \WP_Post $lastRevision
     * @param \WP_Post $post
     * @return bool
     */
    public function postHasChangedWhenMetaChanged(bool $postHasChanged, \WP_Post $lastRevision, \WP_Post $post): bool
    {
        if ($postHasChanged) {
            return true;
        }
        if ($post->post_type !== CodeSnippetsPostType::POST_TYPE) {
            return $postHasChanged;
        }
        $postId = (int) $post->ID;
        $stored = self::$metaBeforeUpdate[$postId] ?? null;
        if ($stored === null) {
            return $postHasChanged;
        }
        $submitted = $this->getSubmittedSnippetMeta();
        if ($submitted === null) {
            return $postHasChanged;
        }
        foreach (self::ACF_FIELD_KEY_TO_META as $metaKey) {
            $oldVal = $stored[$metaKey] ?? '';
            $newVal = $submitted[$metaKey] ?? '';
            if ((string) $oldVal !== (string) $newVal) {
                return true;
            }
        }
        return $postHasChanged;
    }

    /**
     * After ACF has saved, copy current post meta to the latest revision (revision was created
     * before ACF saved, so it would have had old meta; we fix that here)
     * @param int|string $postId
     * @return void
     */
    public function copyMetaToLatestRevision($postId): void
    {
        $postId = is_numeric($postId) ? (int) $postId : 0;
        if ($postId <= 0 || get_post_type($postId) !== CodeSnippetsPostType::POST_TYPE) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $revisions = wp_get_post_revisions($postId, ['posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        $latest = reset($revisions);
        if (!$latest instanceof \WP_Post) {
            return;
        }
        foreach (self::REVISIONED_META_KEYS as $metaKey) {
            $value = get_post_meta($postId, $metaKey, true);
            if ($metaKey === SnippetValidator::META_VALIDATION_ERROR && is_array($value)) {
                delete_post_meta($latest->ID, $metaKey);
                if ($value !== []) {
                    update_post_meta($latest->ID, $metaKey, $value);
                }
            } else {
                if ($value === '' || $value === null) {
                    delete_post_meta($latest->ID, $metaKey);
                } else {
                    update_post_meta($latest->ID, $metaKey, $value);
                }
            }
        }
        //clear stored meta so we don't leak memory
        unset(self::$metaBeforeUpdate[$postId]);
    }

    /**
     * Get submitted snippet meta from $_POST (ACF form data)
     * @return array<string, mixed>|null null if not a snippet save or no acf data
     */
    private function getSubmittedSnippetMeta(): ?array
    {
        $acf = isset($_POST['acf']) && is_array($_POST['acf']) ? $_POST['acf'] : null;
        if ($acf === null) {
            return null;
        }
        $result = [];
        foreach (self::ACF_FIELD_KEY_TO_META as $fieldKey => $metaKey) {
            $result[$metaKey] = isset($acf[$fieldKey]) ? $acf[$fieldKey] : '';
        }
        return $result;
    }
}
