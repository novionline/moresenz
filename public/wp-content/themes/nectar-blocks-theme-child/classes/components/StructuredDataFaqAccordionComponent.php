<?php

namespace NoviOnline;

use NoviOnline\Core\Gutenberg;
use NoviOnline\Core\Singleton;

/**
 * Class StructuredDataFaqAccordionComponent
 * @package NoviOnline
 */
class StructuredDataFaqAccordionComponent extends Singleton {

    const ACCORDION_BLOCK = 'nectar-blocks/accordion';
    const ACCORDION_SECTION_BLOCK = 'nectar-blocks/accordion-section';
    const ENABLE_ATTR = 'faqStructuredDataEnabled';

    /**
     * StructuredDataFaqAccordionComponent constructor.
     */
    protected function __construct() {
        add_action('wp', function () {
            if (is_admin()) {
                return;
            }
            add_filter('wpseo_schema_graph', [$this, 'addFaqSchemaToYoastGraph'], 10, 2);
        });
    }

    /**
     * Append FAQPage schema to Yoast graph.
     *
     * @param array $graph
     * @param mixed $context
     * @return array
     */
    public function addFaqSchemaToYoastGraph(array $graph, $context): array {
        $faqItems = $this->getFaqItemsForCurrentRequest();
        if (empty($faqItems)) {
            return $graph;
        }

        $url = $this->getCurrentCanonicalUrl($context);
        if ($url === '') {
            return $graph;
        }

        $faqId = $this->buildNodeId($url, '#faq');
        foreach ($graph as $piece) {
            if (is_array($piece) && ($piece['@id'] ?? null) === $faqId) {
                return $graph;
            }
        }

        $graph[] = [
            '@type' => 'FAQPage',
            '@id' => $faqId,
            'mainEntity' => $faqItems,
        ];

        return $graph;
    }

    /**
     * Build FAQ items by flattening enabled accordion blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getFaqItemsForCurrentRequest(): array {
        $accordions = Gutenberg::getUsedBlocksByName(self::ACCORDION_BLOCK, true, true);
        if (empty($accordions)) {
            return [];
        }

        $out = [];
        $seenQuestions = [];

        foreach ($accordions as $accordionBlock) {
            if (!is_array($accordionBlock)) {
                continue;
            }
            $attrs = $accordionBlock['attrs'] ?? [];
            $enabled = !is_array($attrs) || !array_key_exists(self::ENABLE_ATTR, $attrs) ? true : (bool)$attrs[self::ENABLE_ATTR];
            if (!$enabled) {
                continue;
            }

            $sections = $accordionBlock['innerBlocks'] ?? [];
            if (!is_array($sections) || empty($sections)) {
                continue;
            }

            foreach ($sections as $sectionBlock) {
                if (!is_array($sectionBlock) || ($sectionBlock['blockName'] ?? '') !== self::ACCORDION_SECTION_BLOCK) {
                    continue;
                }

                $html = (string)render_block($sectionBlock);
                if ($html === '') {
                    continue;
                }

                $question = $this->extractQuestionFromAccordionSectionHtml($html);
                $answerHtml = $this->extractAnswerHtmlFromAccordionSectionHtml($html);
                $answerHtml = $this->sanitizeLimitedHtml($answerHtml);

                $question = $this->normalizeWhitespace($question);
                $answerHtml = $this->normalizeWhitespace($answerHtml);

                if ($question === '' || wp_strip_all_tags($answerHtml) === '') {
                    continue;
                }

                $questionKey = function_exists('mb_strtolower') ? mb_strtolower($question) : strtolower($question);
                if (isset($seenQuestions[$questionKey])) {
                    continue;
                }
                $seenQuestions[$questionKey] = true;

                $out[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $answerHtml,
                    ],
                ];
            }
        }

        return $out;
    }

    /**
     * Extract question (title) from rendered accordion section HTML.
     */
    private function extractQuestionFromAccordionSectionHtml(string $html): string {
        $dom = $this->loadHtmlDom($html);
        if (!$dom) {
            return trim(wp_strip_all_tags($html));
        }
        $xpath = new \DOMXPath($dom);

        $titleNode = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-accordion-section__title ")]')->item(0);
        if ($titleNode) {
            return trim($titleNode->textContent ?? '');
        }

        //fallback: first heading in the section
        $heading = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6')->item(0);
        if ($heading) {
            return trim($heading->textContent ?? '');
        }

        return trim(wp_strip_all_tags($html));
    }

    /**
     * Extract answer HTML from rendered accordion section HTML.
     */
    private function extractAnswerHtmlFromAccordionSectionHtml(string $html): string {
        $dom = $this->loadHtmlDom($html);
        if (!$dom) {
            return '';
        }
        $xpath = new \DOMXPath($dom);

        $contentNode = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-accordion-section__content ")]')->item(0);
        if (!$contentNode) {
            return '';
        }

        return $this->getInnerHtml($contentNode);
    }

    /**
     * Sanitize answer HTML, allowing a limited set of tags.
     */
    private function sanitizeLimitedHtml(string $html): string {
        $allowed = [
            'a' => [
                'href' => true,
                'title' => true,
                'target' => true,
                'rel' => true,
            ],
            'strong' => [],
            'em' => [],
            'b' => [],
            'i' => [],
            'br' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
        ];
        return wp_kses($html, $allowed);
    }

    /**
     * Normalize whitespace in text/HTML snippets.
     */
    private function normalizeWhitespace(string $s): string {
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        return trim((string)$s);
    }

    /**
     * Load HTML into DOMDocument.
     */
    private function loadHtmlDom(string $html): ?\DOMDocument {
        if ($html === '') {
            return null;
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        $ok = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $dom : null;
    }

    /**
     * Get inner HTML for a DOMNode.
     */
    private function getInnerHtml(\DOMNode $node): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * Best-effort canonical URL for current request.
     */
    private function getCurrentCanonicalUrl($context): string {
        if (is_object($context) && isset($context->canonical) && is_string($context->canonical) && $context->canonical !== '') {
            return esc_url_raw($context->canonical);
        }

        if (function_exists('is_singular') && is_singular()) {
            $postId = get_queried_object_id();
            $permalink = $postId ? get_permalink($postId) : '';
            return $permalink ? esc_url_raw($permalink) : '';
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri === '') {
            return '';
        }
        return esc_url_raw(home_url($requestUri));
    }

    /**
     * Build a stable @id from a URL + fragment.
     */
    private function buildNodeId(string $url, string $fragment): string {
        $url = preg_replace('/#.*$/', '', $url);
        $fragment = trim($fragment);
        if ($fragment !== '' && $fragment[0] !== '#') {
            $fragment = '#' . $fragment;
        }

        //avoid adding trailing slash when query string is present
        if (strpos($url, '?') !== false) {
            return $url . $fragment;
        }

        return trailingslashit($url) . $fragment;
    }
}
