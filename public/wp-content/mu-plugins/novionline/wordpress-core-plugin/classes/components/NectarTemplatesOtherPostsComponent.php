<?php

namespace NoviOnline\Core;

use NoviOnline\Core;

/**
 * Class NectarTemplatesOtherPostsComponent
 *
 * Adds the active NectarBlocks Theme Builder template posts (post type `nectar_templates`)
 * to the global `$otherPosts` array so downstream code (e.g. Admin Bar Light, block scanning)
 * can detect them on the current frontend request.
 */
class NectarTemplatesOtherPostsComponent extends Singleton
{
    /**
     * NectarBlocks Theme Builder template post type.
     */
    private const POST_TYPE_TEMPLATES = 'nectar_templates';

    /**
     * NectarBlocks template meta key that stores templatePart + conditions/operator.
     */
    private const META_KEY_TEMPLATE_OPTIONS = '_nectar_template_part_options';

    /**
     * Component constructor.
     */
    protected function __construct()
    {
        //run after global sections prime `$otherPosts` (wp@25) and after Nectar template engine adds hooks (wp@10)
        add_action('wp', [$this, 'primeOtherPostsWithActiveTemplates'], 35);

        //rename the admin bar light section heading for these templates
        add_filter('novi_admin_bar_light_secondary_items', [$this, 'renameAdminBarLightTemplatesHeading'], 20, 1);
    }

    /**
     * Renames the nectar_templates admin bar light heading to "Active templates".
     *
     * @param array $items
     * @return array
     */
    public function renameAdminBarLightTemplatesHeading(array $items): array
    {
        foreach ($items as $item) {
            if (!is_object($item) || empty($item->heading) || !is_string($item->heading)) {
                continue;
            }

            //default label currently resolves to something like "Active theme builder"
            $heading = strtolower($item->heading);
            if (str_starts_with($heading, 'active ') && str_contains($heading, 'theme builder')) {
                $item->heading = __('Active templates', Core::TEXT_DOMAIN);
            }
        }

        return $items;
    }

    /**
     * Detect active nectar_templates for the current request and merge into global `$otherPosts`.
     *
     * @return void
     */
    public function primeOtherPostsWithActiveTemplates(): void
    {
        if (is_admin()) {
            return;
        }

        if (!class_exists('\Nectar\Nectar_Templates\Render')) {
            return;
        }

        $templateParts = $this->getRelevantTemplatePartsForRequest();
        if (empty($templateParts)) {
            return;
        }

        $activeTemplatePosts = $this->getActiveTemplatePostsForParts($templateParts);
        if (empty($activeTemplatePosts)) {
            return;
        }

        global $otherPosts;

        if (!isset($otherPosts) || !is_array($otherPosts)) {
            $otherPosts = [];
        }

        foreach ($activeTemplatePosts as $templatePost) {
            $otherPosts[$templatePost->ID] = $templatePost;
        }

        //re-index numerically to stay compatible with existing foreach loops
        $otherPosts = array_values($otherPosts);
    }

    /**
     * Returns templatePart hook values that could apply to the current request.
     *
     * @return string[]
     */
    private function getRelevantTemplatePartsForRequest(): array
    {
        $parts = [];

        if (is_404()) {
            $parts[] = 'nectar_template__404';
            return $parts;
        }

        if (is_singular()) {
            $postType = get_post_type();
            if (is_string($postType) && $postType !== '') {
                $parts[] = 'nectar_template_single__' . $postType;
            }
        }

        //blog index (is_home), post type archives, and most archive views should map to archive templates
        if (is_home() || is_archive() || is_post_type_archive()) {
            $postType = get_post_type();

            //WP archives commonly yield no singular post; ensure default `post` is considered (blog archive template)
            if (!is_string($postType) || $postType === '') {
                $postType = 'post';
            }

            $parts[] = 'nectar_template_archive__' . $postType;
        }

        return array_values(array_unique(array_filter($parts)));
    }

    /**
     * Resolves active `nectar_templates` posts for any of the given templateParts.
     *
     * @param string[] $templateParts
     * @return \WP_Post[]
     */
    private function getActiveTemplatePostsForParts(array $templateParts): array
    {
        $templateParts = array_values(array_filter($templateParts, static fn($p) => is_string($p) && $p !== ''));
        if (empty($templateParts)) {
            return [];
        }

        $ids = $this->getCandidateTemplateIdsByParts($templateParts);
        if (empty($ids)) {
            return [];
        }

        $render = \Nectar\Nectar_Templates\Render::get_instance();
        if (!$render) {
            return [];
        }

        $activePosts = [];

        foreach ($ids as $templateId) {
            $post = get_post($templateId);
            if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE_TEMPLATES) {
                continue;
            }

            $meta = get_post_meta($templateId, self::META_KEY_TEMPLATE_OPTIONS, true);
            if (!is_array($meta) || empty($meta['templatePart']) || !is_string($meta['templatePart'])) {
                continue;
            }

            //guard against LIKE false positives
            if (!in_array($meta['templatePart'], $templateParts, true)) {
                continue;
            }

            if ($render->verify_conditional_display($templateId)) {
                $activePosts[$templateId] = $post;
            }
        }

        return array_values($activePosts);
    }

    /**
     * Finds candidate template IDs by matching templatePart meta via LIKE queries.
     *
     * @param string[] $templateParts
     * @return int[]
     */
    private function getCandidateTemplateIdsByParts(array $templateParts): array
    {
        $metaQuery = ['relation' => 'OR'];

        foreach ($templateParts as $part) {
            $metaQuery[] = [
                'key' => self::META_KEY_TEMPLATE_OPTIONS,
                'value' => $part,
                'compare' => 'LIKE',
            ];
        }

        $query = new \WP_Query([
            'post_type' => self::POST_TYPE_TEMPLATES,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'suppress_filters' => true,
            'meta_query' => $metaQuery,
        ]);

        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids;
    }
}
