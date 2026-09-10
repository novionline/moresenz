<?php

namespace NoviOnline\CodeSnippets;

/**
 * Preserve backslash escapes in snippet code through WordPress meta saves.
 * WP update_post_meta() runs wp_unslash()/stripslashes, which turns CSS content:"\e60a" into content:"e60a".
 *
 * @package NoviOnline\CodeSnippets
 */
class SnippetCodeEscaping
{
    /**
     * Persist snippet meta so a single backslash survives update_post_meta's wp_unslash.
     *
     * @param int $postId
     * @param string $metaKey
     * @param string $value
     * @return void
     */
    public static function updateMeta(int $postId, string $metaKey, string $value): void
    {
        update_post_meta($postId, $metaKey, wp_slash($value));
    }

    /**
     * Slash value for ACF update_value so the following update_metadata unslash keeps backslashes.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function slashForAcfSave($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        return wp_slash(self::restoreStrippedCssContentEscapes($value));
    }

    /**
     * Restore content:"e60a" → content:"\e60a" when a CSS unicode escape lost its backslash.
     * Only touches quoted values that are solely 4–6 hex digits (typical icon/PUA escapes).
     *
     * @param string $code
     * @return string
     */
    public static function restoreStrippedCssContentEscapes(string $code): string
    {
        return (string) preg_replace_callback(
            '/\bcontent\s*:\s*(["\'])([0-9a-fA-F]{4,6})\1/',
            static function (array $m): string {
                return 'content: ' . $m[1] . '\\' . $m[2] . $m[1];
            },
            $code
        );
    }
}
