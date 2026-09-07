<?php

namespace NoviOnline;

use NoviOnline\Core\Formatting;
use NoviOnline\Core\Image;
use NoviOnline\Core\Log;
use NoviOnline\Core\Singleton;

/**
 * Block customizations (e.g. taxonomy-terms link hash and scroll).
 *
 * @package NoviOnline
 */
class BlockCustomizationComponent extends Singleton {

    /**
     * BlockCustomizationComponent constructor.
     */
    protected function __construct() {
        //append hash to taxonomy-terms block links so filter scrolls into view (e.g. below hero)
        add_filter('render_block', [$this, 'taxonomyTermsAddLinkHash'], 10, 2);

        //carousel: add novi mouse follower indicator when block option is enabled
        add_filter('render_block', [$this, 'carouselMouseFollowerMarkup'], 10, 2);

        //image block: fallback alt tag from attachment when none is configured
        add_filter('render_block', [$this, 'imageBlockFallbackAlt'], 10, 2);

        //image block: LCP priority loading when block option is enabled
        add_filter('render_block', [$this, 'imageBlockPriorityLoading'], 10, 2);

        //strip decoding=async re-added by wp_filter_content_tags for LCP priority images
        add_filter('wp_content_img_tag', [$this, 'stripDecodingForLcpPriorityImages'], 10, 1);

        //choose which hash is used per taxonomy (taxonomy slug + "-filters")
        add_filter('nectar_blocks_taxonomy_terms_link_hash', [$this, 'taxonomyTermsLinkHashByTaxonomy'], 10, 2);

        //disable Nectar animated anchor scroll (init.js) in favour of native hash + scroll-padding-top
        add_filter('nectar_animated_anchors', [$this, 'disableNectarAnimatedAnchors']);
    }

    /**
     * Disable Nectar theme smooth hash scrolling for native browser behaviour
     * @return string
     */
    public static function disableNectarAnimatedAnchors(): string {
        return 'false';
    }

    /**
     * Append a hash to All + term links in the taxonomy-terms block so the target page scrolls to the filter (e.g. below hero).
     *
     * @param string|null $block_content
     * @param array $block
     * @return string|null
     */
    public function taxonomyTermsAddLinkHash($block_content, array $block) {

        if (($block['blockName'] ?? '') !== 'nectar-blocks/taxonomy-terms') {
            return $block_content;
        }

        if (!is_string($block_content)) {
            return $block_content;
        }

        $blockId = $block['attrs']['blockId'] ?? '';
        if ($blockId === '') {
            return $block_content;
        }

        $hash = apply_filters('nectar_blocks_taxonomy_terms_link_hash', $blockId, $block['attrs'] ?? []);
        if ($hash === '' || !is_string($hash)) {
            return $block_content;
        }

        $hash = preg_replace('/[^a-zA-Z0-9_-]/', '', $hash);
        if ($hash === '') {
            return $block_content;
        }

        //ensure unique id when multiple blocks on the same page share the same taxonomy
        static $usedHashes = [];
        if (!isset($usedHashes[$hash])) {
            $usedHashes[$hash] = 0;
        }
        $usedHashes[$hash]++;
        $finalId = $usedHashes[$hash] === 1 ? $hash : $hash . '-' . $usedHashes[$hash];

        //invisible anchor before filters; align-self flex-start so flex-row parents (align-items:center)
        //do not vertically center this 0-height item — otherwise filters sit under the sticky header
        $anchor = '<div id="' . esc_attr($finalId) . '" class="novi-taxonomy-terms-scroll-anchor" style="height:0;width:0;margin:0;padding:0;overflow:hidden;pointer-events:none;align-self:flex-start;flex:0 0 auto" aria-hidden="true"></div>';
        $block_content = $anchor . $block_content;

        return preg_replace_callback('/href="([^"]+)"/', function ($m) use ($finalId) {
            $url = preg_replace('/#.*/', '', $m[1]);
            //ensure path has trailing slash so WordPress redirects don't drop the hash
            $q = strpos($url, '?');
            if ($q !== false) {
                $path = substr($url, 0, $q);
                if ($path !== '' && substr($path, -1) !== '/') {
                    $url = $path . '/' . substr($url, $q);
                }
            } else {
                if ($url !== '' && substr($url, -1) !== '/') {
                    $url = $url . '/';
                }
            }
            return 'href="' . $url . '#' . esc_attr($finalId) . '"';
        }, $block_content);
    }

    /**
     * Add data-novi-mouse-follower and inject the novi drag indicator div for carousel blocks when enabled.
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function carouselMouseFollowerMarkup($blockContent, array $block) {

        if (($block['blockName'] ?? '') !== 'nectar-blocks/carousel') {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        $attrs = $block['attrs'] ?? [];
        if (empty($attrs['mouseFollowerEnabled'])) {
            return $blockContent;
        }

        //indicator markup: circle + left/right arrows (datalyzer arrow SVG, right + rotated left)
        $arrowSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="novi-drag-indicator-arrow novi-drag-indicator-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m0 0-4-4m4 4-4 4"/></svg>';
        $arrowLeftSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="novi-drag-indicator-arrow novi-drag-indicator-arrow-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m0 0-4-4m4 4-4 4"/></svg>';
        $indicatorHtml = '<div class="novi-drag-indicator novi-carousel-mouse-follower" data-type="solid" aria-hidden="true">'
            . '<span class="novi-drag-indicator-circle"></span>'
            . '<span class="novi-drag-indicator-arrows">'
            . $arrowLeftSvg
            . $arrowSvg
            . '</span>'
            . '</div>';

        //add data attribute to carousel wrapper and inject indicator as first child
        $pattern = '/(<div\s[^>]*\bnectar-blocks-carousel\b[^>]*)(>)/s';
        if (preg_match($pattern, $blockContent)) {
            $blockContent = preg_replace($pattern, '$1 data-novi-mouse-follower="true"$2' . $indicatorHtml, $blockContent, 1);
        }

        return $blockContent;
    }

    /**
     * When the Nectar image block has no alt in attributes, set the rendered img alt from Image::altFromId().
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function imageBlockFallbackAlt($blockContent, array $block) {

        if (($block['blockName'] ?? '') !== 'nectar-blocks/image') {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        $imageAttrs = $block['attrs']['image'] ?? [];
        if (!is_array($imageAttrs)) {
            return $blockContent;
        }

        $debugEnabled = defined('WP_DEBUG') && WP_DEBUG && isset($_GET['novi_debug_alt']) && $_GET['novi_debug_alt'] === '1';

        $configuredAlt = isset($imageAttrs['alt']) ? trim((string) $imageAttrs['alt']) : '';
        if ($configuredAlt !== '') {
            if ($debugEnabled) {
                Log::log('[novi][nectar-image-alt] configured alt present; skipping. blockId=' . ($block['attrs']['blockId'] ?? '') . ' alt=' . $configuredAlt);
            }
            return $blockContent;
        }

        $attachmentId = $imageAttrs['id'] ?? 0;
        if (!$attachmentId) {
            if ($debugEnabled) {
                Log::log('[novi][nectar-image-alt] missing attachment id; skipping. blockId=' . ($block['attrs']['blockId'] ?? ''));
            }
            return $blockContent;
        }

        $fallbackAlt = Image::altFromId($attachmentId);
        if ($fallbackAlt === '') {
            //Image::altFromId falls back to attachment title; some attachments might have an empty title.
            //as a final fallback, use the block-configured title if available
            $fallbackAlt = isset($imageAttrs['title']) ? trim((string) $imageAttrs['title']) : '';
            if ($fallbackAlt !== '') {
                $fallbackAlt = esc_attr(ucfirst(str_replace(['-', '_'], ' ', $fallbackAlt)));
            }
        }

        if ($fallbackAlt === '') {
            if ($debugEnabled) {
                Log::log('[novi][nectar-image-alt] empty fallback alt; skipping. blockId=' . ($block['attrs']['blockId'] ?? '') . ' attachmentId=' . $attachmentId);
            }
            return $blockContent;
        }

        $updated = preg_replace_callback(
            '/<img\b[^>]*>/iu',
            static function (array $m) use ($fallbackAlt): string {
                $tag = $m[0];
                if (preg_match('/\balt\s*=\s*"/iu', $tag)) {
                    return (string) preg_replace('/\balt\s*=\s*"[^"]*"/iu', 'alt="' . $fallbackAlt . '"', $tag, 1);
                }
                if (preg_match('/\balt\s*=\s*\'/iu', $tag)) {
                    return (string) preg_replace('/\balt\s*=\s*\'[^\']*\'/iu', 'alt="' . $fallbackAlt . '"', $tag, 1);
                }
                if (preg_match('/\/\s*>\s*$/', $tag)) {
                    return (string) preg_replace('/\/\s*>\s*$/', ' alt="' . $fallbackAlt . '" />', $tag, 1);
                }

                return (string) preg_replace('/>$/', ' alt="' . $fallbackAlt . '">', $tag, 1);
            },
            $blockContent,
            1
        );

        if ($debugEnabled) {
            $changed = is_string($updated) && $updated !== $blockContent;
            Log::log('[novi][nectar-image-alt] processed. blockId=' . ($block['attrs']['blockId'] ?? '') . ' attachmentId=' . $attachmentId . ' fallbackAlt=' . $fallbackAlt . ' changed=' . ($changed ? '1' : '0'));
            if (!$changed && preg_match('/<img\b[^>]*>/iu', $blockContent, $m)) {
                Log::log('[novi][nectar-image-alt] img-before: ' . $m[0]);
            }
            if ($changed && preg_match('/<img\b[^>]*>/iu', (string) $updated, $m)) {
                Log::log('[novi][nectar-image-alt] img-after: ' . $m[0]);
            }
        }

        return $updated !== null ? $updated : $blockContent;
    }

    /**
     * When priority loading is enabled on a Nectar image block, set loading=eager, fetchpriority=high,
     * strip decoding, and tag the img so wp_content_img_tag can strip decoding re-added by WordPress.
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function imageBlockPriorityLoading($blockContent, array $block) {

        if (($block['blockName'] ?? '') !== 'nectar-blocks/image') {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        if (($block['attrs']['priorityLoading'] ?? false) !== true) {
            return $blockContent;
        }

        $updated = preg_replace_callback(
            '/<img\b[^>]*>/iu',
            static function (array $m): string {
                $tag = $m[0];

                if (preg_match('/\bloading\s*=\s*"/iu', $tag)) {
                    $tag = (string) preg_replace('/\bloading\s*=\s*"[^"]*"/iu', 'loading="eager"', $tag, 1);
                } elseif (preg_match('/\bloading\s*=\s*\'/iu', $tag)) {
                    $tag = (string) preg_replace('/\bloading\s*=\s*\'[^\']*\'/iu', 'loading="eager"', $tag, 1);
                } elseif (preg_match('/\/\s*>\s*$/', $tag)) {
                    $tag = (string) preg_replace('/\/\s*>\s*$/', ' loading="eager" />', $tag, 1);
                } else {
                    $tag = (string) preg_replace('/>$/', ' loading="eager">', $tag, 1);
                }

                if (preg_match('/\bfetchpriority\s*=\s*"/iu', $tag)) {
                    $tag = (string) preg_replace('/\bfetchpriority\s*=\s*"[^"]*"/iu', 'fetchpriority="high"', $tag, 1);
                } elseif (preg_match('/\bfetchpriority\s*=\s*\'/iu', $tag)) {
                    $tag = (string) preg_replace('/\bfetchpriority\s*=\s*\'[^\']*\'/iu', 'fetchpriority="high"', $tag, 1);
                } elseif (preg_match('/\/\s*>\s*$/', $tag)) {
                    $tag = (string) preg_replace('/\/\s*>\s*$/', ' fetchpriority="high" />', $tag, 1);
                } else {
                    $tag = (string) preg_replace('/>$/', ' fetchpriority="high">', $tag, 1);
                }

                $tag = (string) preg_replace('/\bdecoding\s*=\s*(?:"[^"]*"|\'[^\']*\')/iu', '', $tag);

                if (preg_match('/\bclass\s*=\s*"/iu', $tag)) {
                    if (!preg_match('/\bclass\s*=\s*"[^"]*\bnovi-lcp-priority\b/iu', $tag)) {
                        $tag = (string) preg_replace(
                            '/\bclass\s*=\s*"([^"]*)"/iu',
                            'class="$1 novi-lcp-priority"',
                            $tag,
                            1
                        );
                    }
                } elseif (preg_match('/\bclass\s*=\s*\'/iu', $tag)) {
                    if (!preg_match('/\bclass\s*=\s*\'[^\']*\bnovi-lcp-priority\b/iu', $tag)) {
                        $tag = (string) preg_replace(
                            '/\bclass\s*=\s*\'([^\']*)\'/iu',
                            'class="$1 novi-lcp-priority"',
                            $tag,
                            1
                        );
                    }
                } elseif (preg_match('/\/\s*>\s*$/', $tag)) {
                    $tag = (string) preg_replace('/\/\s*>\s*$/', ' class="novi-lcp-priority" />', $tag, 1);
                } else {
                    $tag = (string) preg_replace('/>$/', ' class="novi-lcp-priority">', $tag, 1);
                }

                return $tag;
            },
            $blockContent,
            1
        );

        return $updated !== null ? $updated : $blockContent;
    }

    /**
     * Remove decoding=async from LCP priority images after wp_filter_content_tags runs.
     *
     * @param string $filteredImage
     * @return string
     */
    public function stripDecodingForLcpPriorityImages(string $filteredImage): string {

        if (!preg_match('/\bclass\s*=\s*["\'][^"\']*\bnovi-lcp-priority\b/iu', $filteredImage)) {
            return $filteredImage;
        }

        return (string) preg_replace('/\s*\bdecoding\s*=\s*(?:"[^"]*"|\'[^\']*\')/iu', '', $filteredImage);
    }

    /**
     * Use taxonomy slug + "filters" as the scroll anchor hash so no mapping is needed.
     *
     * @param string $hash Default blockId
     * @param array $attrs Block attributes (e.g. taxonomy)
     * @return string
     */
    public function taxonomyTermsLinkHashByTaxonomy(string $hash, array $attrs): string {

        $taxonomy = $attrs['taxonomy'] ?? '';
        if ($taxonomy === '') {
            return $hash;
        }

        $slug = Formatting::slugify($taxonomy);
        return $slug !== '' ? $slug . '-filters' : $hash;
    }
}
