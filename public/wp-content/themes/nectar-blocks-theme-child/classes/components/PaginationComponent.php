<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Pagination component.
 */
class PaginationComponent extends Singleton {

    protected function __construct() {
        //prevent /page/1/ links in pagination (canonical is the archive URL).
        add_filter('paginate_links', [$this, 'filterPaginateLinksNoPageOne'], 20, 1);
        add_filter('get_pagenum_link', [$this, 'filterGetPagenumLinkNoPageOne'], 20, 2);
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
        //remove explicit first page in pretty permalinks.
        // /something/page/1/      -> /something/
        // /something/page/1/?x=1  -> /something/?x=1
        $fixed = (string) preg_replace('~/(?:page)/1/?(?=\\?|#|$)~', '/', $url);

        //remove explicit paged=1 in query strings (keep other params).
        // ?paged=1&x=1  -> ?x=1
        // ?x=1&paged=1  -> ?x=1
        $fixed = (string) preg_replace('~([?&])paged=1(&)?~', '$1', $fixed);

        //cleanup leftover separators.
        $fixed = str_replace(['?&', '&&'], ['?', '&'], $fixed);
        $fixed = (string) preg_replace('~\\?(#|$)~', '$1', $fixed);
        $fixed = (string) preg_replace('~&(#|$)~', '$1', $fixed);

        return $fixed;
    }
}
