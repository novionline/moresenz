<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Pagination component.
 */
class PaginationComponent extends Singleton {

    protected function __construct() {
        // Prevent /page/1/ links in pagination (canonical is the archive URL).
        add_filter('paginate_links', [$this, 'filterPaginateLinksNoPageOne'], 20, 1);
        add_filter('get_pagenum_link', [$this, 'filterGetPagenumLinkNoPageOne'], 20, 2);

        //WCAG: name icon-only prev/next links (WP 7+ applies paginate_links to URLs only)
        add_filter('paginate_links_output', [$this, 'filterPaginateLinksOutputAriaLabels'], 25, 1);
        add_filter('nectar_blocks_post_grid_pagination', [$this, 'filterPaginateLinksOutputAriaLabels'], 25, 1);
    }

    /**
     * Rewrite "page 1" pagination links to the canonical first page URL.
     *
     * Handles HTML strings (href="...") and also cases where filters pass plain URL strings.
     *
     * @param mixed $output HTML output from paginate_links() (string) or array when type=array
     * @return mixed
     */
    public function filterPaginateLinksNoPageOne($output) {
        if (is_array($output)) {
            foreach ($output as $k => $v) {
                $output[$k] = $this->rewriteValue($v);
            }
            return $output;
        }

        return $this->rewriteValue($output);
    }

    /**
     * Add translatable aria-labels to icon-only prev/next pagination links.
     *
     * Hooks `paginate_links_output` (full HTML in WP 7+) and NB post-grid markup.
     *
     * @param mixed $output Pagination HTML
     * @return mixed
     */
    public function filterPaginateLinksOutputAriaLabels($output) {
        if (!is_string($output) || $output === '') {
            return $output;
        }

        return $this->addPrevNextAriaLabels($output);
    }

    /**
     * Ensure `get_pagenum_link(1)` never produces an explicit page=1 URL.
     *
     * @param string $result
     * @param int $pagenum
     * @return string
     */
    public function filterGetPagenumLinkNoPageOne(string $result, int $pagenum): string {
        if ($pagenum !== 1) return $result;

        return $this->normalizePaginationPageOneUrl($result);
    }

    /**
     * Rewrite either a pagination HTML fragment (with href="...") or a plain URL string.
     *
     * @param mixed $value
     * @return mixed
     */
    private function rewriteValue($value) {
        if (!is_string($value) || $value === '') return $value;

        return str_contains($value, 'href=')
            ? $this->rewriteHrefAttributes($value)
            : $this->normalizePaginationPageOneUrl($value);
    }

    /**
     * Inject aria-label on .prev / .next anchors when missing.
     *
     * @param string $html
     * @return string
     */
    private function addPrevNextAriaLabels(string $html): string {
        if (!str_contains($html, 'page-numbers') && !str_contains($html, 'prev') && !str_contains($html, 'next')) {
            return $html;
        }

        $prevLabel = esc_attr__('Previous page', Theme::TEXT_DOMAIN);
        $nextLabel = esc_attr__('Next page', Theme::TEXT_DOMAIN);

        return (string) preg_replace_callback(
            '/<a\b([^>]*)>/iu',
            static function (array $match) use ($prevLabel, $nextLabel): string {
                $attrs = $match[1];

                if (!preg_match('/\bclass\s*=\s*(["\'])([^"\']*)\1/iu', $attrs, $classMatch)) {
                    return $match[0];
                }

                $classes = preg_split('/\s+/', trim($classMatch[2])) ?: [];
                $isPrev = in_array('prev', $classes, true);
                $isNext = in_array('next', $classes, true);

                if (!$isPrev && !$isNext) {
                    return $match[0];
                }

                if (preg_match('/\baria-label\s*=/iu', $attrs)) {
                    return $match[0];
                }

                $label = $isPrev ? $prevLabel : $nextLabel;
                return '<a' . $attrs . ' aria-label="' . $label . '">';
            },
            $html
        );
    }

    /**
     * Rewrite href targets within pagination HTML.
     *
     * @param string $html
     * @return string
     */
    private function rewriteHrefAttributes(string $html): string {
        $self = $this;

        return (string) preg_replace_callback(
            '~href=(["\'])([^"\']+)\1~',
            static function(array $m) use ($self): string {
                $quote = $m[1];
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                $fixed = $self->normalizePaginationPageOneUrl($url);
                return 'href=' . $quote . esc_url($fixed) . $quote;
            },
            $html
        );
    }

    /**
     * Normalize a pagination URL so page=1 is never explicit.
     *
     * @param string $url
     * @return string
     */
    private function normalizePaginationPageOneUrl(string $url): string {
        // Remove explicit first page in pretty permalinks.
        // /something/page/1/      -> /something/
        // /something/page/1/?x=1  -> /something/?x=1
        $fixed = (string) preg_replace('~/(?:page)/1/?(?=\\?|#|$)~', '/', $url);

        // Remove explicit paged=1 in query strings (keep other params).
        // ?paged=1&x=1  -> ?x=1
        // ?x=1&paged=1  -> ?x=1
        $fixed = (string) preg_replace('~([?&])paged=1(&)?~', '$1', $fixed);

        // Cleanup leftover separators.
        $fixed = str_replace(['?&', '&&'], ['?', '&'], $fixed);
        $fixed = (string) preg_replace('~\\?(#|$)~', '$1', $fixed);
        $fixed = (string) preg_replace('~&(#|$)~', '$1', $fixed);

        return $fixed;
    }
}
