<?php

namespace NoviOnline\Core;

/**
 * Class Taxonomy
 * @package NoviOnline\Core
 */
class Taxonomy
{
    /**
     * Get array of (primary) post terms by ID
     * @param int $postId
     * @param string $taxonomy
     * @param int $maxAmount
     * @return array
     */
    public static function getPrimaryPostTerms(int $postId, string $taxonomy, int $maxAmount = -1): array
    {
        $terms = [];
        $primaryTermId = false;

        if ($postId) {

            //add primary term
            if (function_exists('yoast_get_primary_term_id')) {
                if ($primaryTermId = yoast_get_primary_term_id($taxonomy, $postId)) {
                    $primaryTerm = get_term($primaryTermId, $taxonomy);
                    if (is_a($primaryTerm, '\WP_Term')) $terms[] = $primaryTerm;
                }
            }

            //add other terms until amount reached
            $otherTermArgs = [];
            if ($primaryTermId) $otherTermArgs['exclude'] = $primaryTermId;
            $otherTerms = wp_get_post_terms($postId, $taxonomy, $otherTermArgs);
            if ($otherTerms && count($otherTerms) > 0) {
                foreach ($otherTerms as $otherTerm) {
                    if ($maxAmount !== -1 && count($terms) >= $maxAmount) break;
                    $terms[] = $otherTerm;
                }
            }
        }

        return apply_filters('novi_get_primary_post_terms', $terms, $postId, $taxonomy, $maxAmount);
    }
}