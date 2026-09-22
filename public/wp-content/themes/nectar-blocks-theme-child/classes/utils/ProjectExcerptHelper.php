<?php

namespace NoviOnline;

/**
 * Derive archive-friendly project excerpts from NectarBlocks text content
 */
class ProjectExcerptHelper {

    /**
     * Minimum word count for a nectar-blocks/text paragraph to count as body copy
     */
    const MIN_PARAGRAPH_WORDS = 20;

    /**
     * Fallback archive trim length when project settings return an invalid value
     */
    const DEFAULT_WORD_LIMIT = 24;

    /**
     * Get excerpt for a project: prefer post_excerpt, else first long NB text paragraph
     *
     * @param int $postId
     * @param int|null $wordLimit null = use Project settings excerpt word count
     * @return string
     */
    public static function getForPost(int $postId, ?int $wordLimit = null): string {
        if ($postId <= 0) {
            return '';
        }

        if ($wordLimit === null) {
            $wordLimit = ProjectSettings::getExcerptWordCount();
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $excerpt = trim((string)$post->post_excerpt);
        if ($excerpt === '') {
            $excerpt = self::extractFromBlocks((string)$post->post_content);
        }

        if ($excerpt === '') {
            return '';
        }

        if ($wordLimit > 0) {
            return wp_trim_words($excerpt, $wordLimit, '…');
        }

        return $excerpt;
    }

    /**
     * Extract the first long static paragraph from nectar-blocks/text blocks
     *
     * @param string $content
     * @return string plain text (untrimmed)
     */
    public static function extractFromBlocks(string $content): string {
        if ($content === '') {
            return '';
        }

        if (!preg_match_all(
            '/<!-- wp:nectar-blocks\/text(?:\s+(\{.*?\}))?\s*-->\s*(.*?)\s*<!-- \/wp:nectar-blocks\/text -->/s',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return '';
        }

        foreach ($matches as $match) {
            $attrsJson = $match[1] ?? '';
            $innerHtml = $match[2] ?? '';
            $attrs = $attrsJson !== '' ? json_decode($attrsJson, true) : null;

            //skip dynamic / shortcode-driven text blocks
            if (is_array($attrs) && !empty($attrs['nb_dynamic'])) {
                continue;
            }

            $raw = '';
            if (is_array($attrs) && !empty($attrs['content']) && is_string($attrs['content'])) {
                $raw = $attrs['content'];
            } elseif ($innerHtml !== '') {
                $raw = $innerHtml;
            }

            if ($raw === '') {
                continue;
            }

            if (stripos($raw, 'nb_dynamic') !== false) {
                continue;
            }

            $plain = self::toPlainText($raw);
            if ($plain === '') {
                continue;
            }

            //skip crumbs / labels / one-liners under the project hero
            if (self::countWords($plain) < self::MIN_PARAGRAPH_WORDS) {
                continue;
            }

            return $plain;
        }

        return '';
    }

    /**
     * Strip tags / entities and collapse whitespace
     *
     * @param string $html
     * @return string
     */
    public static function toPlainText(string $html): string {
        $plain = wp_strip_all_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = preg_replace('/\s+/u', ' ', $plain);
        return is_string($plain) ? trim($plain) : '';
    }

    /**
     * Count whitespace-separated words (locale-safe for Dutch)
     *
     * @param string $text
     * @return int
     */
    public static function countWords(string $text): int {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }
}
