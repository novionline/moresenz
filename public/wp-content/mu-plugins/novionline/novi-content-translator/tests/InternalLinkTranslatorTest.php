<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

class InternalLinkTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        InternalLinkTranslator::resetRuntimeCache();
        DeepLTranslator::setTestTranslator(null);
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_permalink_map'] = [];
        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_wpdb_fuzzy_rows'] = null;
        $GLOBALS['__nct_http_redirect_map'] = [];
        $GLOBALS['__nct_filters'] = [];
        $GLOBALS['__nct_posts'] = [];
        $GLOBALS['__nct_post_types'] = ['post', 'page', 'article', 'project'];
        $GLOBALS['__nct_post_type_rewrite_slugs'] = [];
        $GLOBALS['__nct_pll_languages_list'] = ['en', 'nl', 'es'];
        $GLOBALS['__nct_pll_translated_slugs'] = [];
        $GLOBALS['__nct_red_item_candidates'] = null;
    }

    public function testRewritesAnchorHrefAndIdUsingMetadataWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '134|es' => 3995,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            3995 => 'https://example.test/es/pongase-en-contacto-con/',
        ];

        $html = 'Text <a href="https://example.test/about-us/contact-us/" type="page" id="134">Link</a> end.';
        $result = InternalLinkTranslator::rewriteAnchorsInHtmlFragment($html, 'es');

        $this->assertIsArray($result);
        $this->assertSame(
            'Text <a href="https://example.test/es/pongase-en-contacto-con/" type="page" id="3995">Link</a> end.',
            $result['html']
        );
        $this->assertSame(1, (int) ($result['stats']['rewritten_by_anchor_id'] ?? 0));
        $this->assertSame(1, (int) ($result['stats']['internal'] ?? 0));
        $this->assertSame(1, (int) ($result['stats']['encountered'] ?? 0));
    }

    public function testRewriteInternalUrlUsesDynamicResolutionWhenUrlToPostIdIsAvailable(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            // emulate a permalink provider returning without trailing slash
            770 => 'https://example.test/nl/krijg-een-demo',
        ];

        $result = InternalLinkTranslator::rewriteInternalUrl('https://example.test/get-a-demo/', 'nl', 'en');

        // must preserve the original trailing slash style
        $this->assertSame('https://example.test/nl/krijg-een-demo/', $result['url']);
        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
    }

    public function testRewriteInternalUrlDoesNotPersistInventedSlugWhenNoTwin(): void
    {
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            $out = [];
            foreach ($texts as $t) {
                $out[] = $t === 'cheeses' ? 'quesos' : $t;
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        // No url_to_postid/termid mapping => cannot snap to a translated object.
        // Full translate must keep the source URL (same policy as strict sync-links).
        $result = InternalLinkTranslator::rewriteInternalUrl('/cheeses/', 'es', 'en');

        $this->assertSame('/cheeses/', $result['url']);
        $this->assertFalse($result['changed']);
        $this->assertSame('not_rewritten', $result['reason']);
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_language_switch'] ?? 0));
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_slug_translation'] ?? 0));
    }

    public function testRewriteInternalUrlSnapsDeepLInventWhenTwinResolves(): void
    {
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            $out = [];
            foreach ($texts as $t) {
                $out[] = $t === 'cheeses' ? 'quesos' : $t;
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        // Source path does not resolve; after DeepL invent the candidate snaps via url_to_postid.
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/es/quesos/' => 11,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '11|es' => 11,
        ];
        $GLOBALS['__nct_pll_post_language'] = [
            11 => 'es',
        ];
        $GLOBALS['__nct_permalink_map'] = [
            11 => 'https://example.test/es/quesos/',
        ];

        $result = InternalLinkTranslator::rewriteInternalUrl('/cheeses/', 'es', 'en');

        $this->assertSame('/es/quesos/', $result['url']);
        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
    }

    public function testRewriteInternalUrlStrictDoesNotInventLanguageSwitchedUrls(): void
    {
        // In strict mode we must not “repair” a link by simply switching the language base
        // (that creates many 404s when the translated object doesn't exist yet).
        $result = InternalLinkTranslator::rewriteInternalUrl('/get-a-demo/', 'nl', 'en', ['strict' => true]);

        $this->assertSame('/get-a-demo/', $result['url']);
        $this->assertFalse($result['changed']);
        $this->assertSame('not_rewritten', $result['reason']);
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_language_switch'] ?? 0));
    }

    public function testRewriteAnchorsInHtmlFragmentStrictDoesNotLanguageSwitch(): void
    {
        $html = 'See <a href="/get-a-demo/">Demo</a>.';
        $result = InternalLinkTranslator::rewriteAnchorsInHtmlFragment($html, 'nl', [
            'source_lang' => 'en',
            'strict' => true,
        ]);

        $this->assertSame($html, $result['html']);
        $this->assertSame(1, (int) ($result['stats']['encountered'] ?? 0));
        $this->assertSame(1, (int) ($result['stats']['internal'] ?? 0));
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_language_switch'] ?? 0));
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_slug_translation'] ?? 0));
    }

    public function testRewriteAnchorsInHtmlFragmentDoesNotInventSlugWithoutTwin(): void
    {
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'slug_archive_resource' => [
                'slug' => 'resources',
                'translations' => [
                    'es' => 'saber',
                ],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            $out = [];
            foreach ($texts as $t) {
                if ($t === 'blog') {
                    $out[] = 'blog';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $html = 'See <a href="https://example.test/resources/blog/">Quality</a>.';
        $result = InternalLinkTranslator::rewriteAnchorsInHtmlFragment($html, 'es', ['source_lang' => 'en']);

        // No published twin to snap to → keep source href (do not persist /es/saber/blog/).
        $this->assertSame($html, $result['html']);
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_language_switch'] ?? 0));
        $this->assertSame(0, (int) ($result['stats']['rewritten_by_slug_translation'] ?? 0));
    }

    public function testRewriteInternalUrlUsesPolylangTranslatedSlugsForFullPathMappings(): void
    {
        // Polylang "translated slugs" can map entire paths, not just individual segments.
        // Example from this stack: "about-us/jobs" => "over-ons/vacatures".
        $GLOBALS['__nct_pll_translated_slugs'] = [
            [
                'slug' => 'vacancies',
                'translations' => [
                    'en' => 'about-us/jobs',
                    'nl' => 'over-ons/vacatures',
                ],
            ],
        ];

        // No url_to_postid/termid mapping available => cannot resolve to a translated object.
        // We expect language switch + full-path translated slug mapping.
        $result = InternalLinkTranslator::rewriteInternalUrl('/about-us/jobs/', 'nl', 'en');

        $this->assertSame('/nl/over-ons/vacatures/', $result['url']);
        $this->assertTrue($result['changed']);
        $this->assertSame('slug_translation', $result['reason']);
        $this->assertSame(1, (int) ($result['stats']['rewritten_by_language_switch'] ?? 0));
        $this->assertSame(1, (int) ($result['stats']['rewritten_by_slug_translation'] ?? 0));
    }

    public function testSlugTranslationWithoutTwinKeepsSourceQueryAndFragment(): void
    {
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            $out = [];
            foreach ($texts as $t) {
                $out[] = $t === 'cheeses' ? 'quesos' : $t;
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $result = InternalLinkTranslator::rewriteInternalUrl('/cheeses/?a=1#top', 'es', 'en');
        $this->assertSame('/cheeses/?a=1#top', $result['url']);
        $this->assertFalse($result['changed']);
    }

    public function testPolylangFullPathTranslatedSlugStillRewritesWithoutTwin(): void
    {
        // Deterministic Polylang full-path mapping remains allowed (also in strict mode).
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'slug_resource-category' => [
                'slug' => 'resource-category',
                'translations' => [
                    'es' => 'saber-categoria',
                ],
            ],
            [
                'slug' => 'resource-category-blog',
                'translations' => [
                    'en' => 'resource-category/blog',
                    'es' => 'saber-categoria/blog',
                ],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $result = InternalLinkTranslator::rewriteInternalUrl('https://example.test/resource-category/blog/', 'es', 'en');
        $this->assertSame('https://example.test/es/saber-categoria/blog/', $result['url']);
        $this->assertTrue($result['changed']);
        $this->assertSame('slug_translation', $result['reason']);
    }

    public function testRewriteInternalUrlSkipsWpContentUploadsLinks(): void
    {
        $url = 'https://example.test/wp-content/uploads/2026/02/datalyzer-404-not-found.svg';
        $result = InternalLinkTranslator::rewriteInternalUrl($url, 'es', 'en');

        $this->assertSame($url, $result['url']);
        $this->assertFalse($result['changed']);
        $this->assertSame('skipped', $result['reason']);
        $this->assertSame(1, (int) ($result['stats']['skipped'] ?? 0));
    }

    public function testRewriteAnchorsInHtmlFragmentSkipsWpContentUploadsLinks(): void
    {
        $html = 'Image <a href="/wp-content/uploads/2026/02/file.svg">asset</a>.';
        $result = InternalLinkTranslator::rewriteAnchorsInHtmlFragment($html, 'es');

        $this->assertSame($html, $result['html']);
        $this->assertSame(1, (int) ($result['stats']['encountered'] ?? 0));
        $this->assertSame(1, (int) ($result['stats']['skipped'] ?? 0));
        $this->assertSame(0, (int) ($result['stats']['internal'] ?? 0));
    }

    public function testSwitchLanguageInUrlDoesNotConcatenateAbsolutePathOntoHome(): void
    {
        $rm = new \ReflectionMethod(InternalLinkTranslator::class, 'switchLanguageInUrl');
        $rm->setAccessible(true);

        //simulate a bad relativeKey that still looks absolute — must not yield hosthost
        $bad = $rm->invoke(null, 'https://example.testhttps://example.test/projecten/ssi/', 'en');
        $this->assertSame('', $bad);
        $this->assertStringNotContainsString('testhttps', (string) $bad);

        $ok = $rm->invoke(null, 'https://example.test/projecten/ssi/', 'en');
        $this->assertSame('https://example.test/en/projecten/ssi/', $ok);
    }

    public function testRelatedProductionHostIsTreatedAsInternalAndRewritten(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://2bhonest.test/diensten/co2-voetafdruk' => 7628,
            'https://2bhonest.test/diensten/co2-voetafdruk/' => 7628,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '7628|en' => 11385,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            11385 => 'https://2bhonest.test/en/climate/carbon-footprint/',
        ];

        $result = InternalLinkTranslator::rewriteInternalUrl(
            'https://www.2bhonest.nl/diensten/co2-voetafdruk',
            'en',
            'nl'
        );

        $this->assertTrue($result['changed']);
        $this->assertSame('https://2bhonest.test/en/climate/carbon-footprint', $result['url']);
        $this->assertSame('dynamic_resolution', $result['reason']);
    }

    public function testTrueExternalHostIsNotRewritten(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';

        $result = InternalLinkTranslator::rewriteInternalUrl(
            'https://example.org/something/',
            'en',
            'nl'
        );

        $this->assertFalse($result['changed']);
        $this->assertSame('https://example.org/something/', $result['url']);
        $this->assertSame('external', $result['reason']);
    }

    public function testBlogPathAliasResolvesViaHeuristics(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_pll_post_map'] = [
            '500|en' => 1500,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            1500 => 'https://2bhonest.test/en/articles/climate-risks/',
        ];
        $GLOBALS['__nct_posts'] = [
            500 => [
                'post_type' => 'article',
                'post_name' => 'klimaatrisicos-komen-zelden-met-sirenes',
                'post_parent' => 0,
                'post_title' => 'NL article',
            ],
        ];

        // resolvePostIdByPathHeuristics uses get_page_by_path / WP_Query — shim may need name lookup
        $rm = new \ReflectionMethod(InternalLinkTranslator::class, 'applyPathSegmentAliases');
        $rm->setAccessible(true);
        $aliased = $rm->invoke(null, ['blog', 'interview', 'klimaatrisicos-komen-zelden-met-sirenes']);
        $this->assertSame(['artikelen', 'interview', 'klimaatrisicos-komen-zelden-met-sirenes'], $aliased);
    }

    public function testRewriteInternalUrlPrefersPolylangObjectOverDeeplInvent(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://2bhonest.test/artikelen/transparantie-in-de-keten-is-onmisbaar-in-een-wereld-zonder-vaste-regels/' => 2001,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '2001|en' => 11412,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            11412 => 'https://2bhonest.test/en/articles/supply-chain-transparency-is-essential-in-a-world-without-fixed-rules/',
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options): array {
            $out = [];
            foreach ($texts as $t) {
                // would invent the broken crawl slug if used
                if (str_contains(strtolower((string) $t), 'transparantie') || str_contains(strtolower((string) $t), 'onmisbaar')) {
                    $out[] = 'supply chain transparency is indispensable in a world without fixed rules';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $result = InternalLinkTranslator::rewriteInternalUrl(
            'https://2bhonest.test/artikelen/transparantie-in-de-keten-is-onmisbaar-in-een-wereld-zonder-vaste-regels/',
            'en',
            'nl'
        );

        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
        $this->assertSame(
            'https://2bhonest.test/en/articles/supply-chain-transparency-is-essential-in-a-world-without-fixed-rules/',
            $result['url']
        );
        $this->assertStringNotContainsString('indispensable', $result['url']);
    }

    public function testRewriteInternalUrlFollowsSameHostRedirectThenMapsTwin(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [
            // stale path does not resolve directly
            'https://2bhonest.test/projecten/royal-swinkels/' => 3001,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '3001|en' => 11416,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            11416 => 'https://2bhonest.test/en/projects/royal-swinkels/',
        ];

        add_filter('nct_follow_internal_redirect', static function ($next, string $current, int $hop) {
            if ($hop === 0 && str_contains($current, '/projecten/royal-swinkels-family-brewers')) {
                return 'https://2bhonest.test/projecten/royal-swinkels/';
            }
            return false;
        }, 10, 3);

        DeepLTranslator::setTestTranslator(function (array $texts): array {
            $out = [];
            foreach ($texts as $t) {
                $out[] = $t === 'royal swinkels family brewers' ? 'royal swinkels family brewers' : $t;
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $result = InternalLinkTranslator::rewriteInternalUrl(
            '/projecten/royal-swinkels-family-brewers/',
            'en',
            'nl'
        );

        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
        $this->assertSame('/en/projects/royal-swinkels/', $result['url']);
        $this->assertStringNotContainsString('family-brewers', $result['url']);
    }

    public function testRewriteInternalUrlSnapsDeeplInventToCanonicalViaFuzzyTokens(): void
    {
        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_pll_languages_list'] = ['nl', 'en'];
        $GLOBALS['__nct_post_types'] = ['article'];
        $GLOBALS['__nct_post_type_rewrite_slugs'] = [
            'article' => 'articles',
        ];
        $GLOBALS['__nct_posts'] = [
            11412 => [
                'post_type' => 'article',
                'post_name' => 'supply-chain-transparency-is-essential-in-a-world-without-fixed-rules',
                'post_status' => 'publish',
            ],
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '11412|en' => 11412,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            11412 => 'https://2bhonest.test/en/articles/supply-chain-transparency-is-essential-in-a-world-without-fixed-rules/',
        ];
        $GLOBALS['__nct_wpdb_fuzzy_rows'] = static function (array $prepared): array {
            return [
                [
                    'ID' => 11412,
                    'post_name' => 'supply-chain-transparency-is-essential-in-a-world-without-fixed-rules',
                ],
            ];
        };

        // block redirect lookups so invent path is exercised
        add_filter('nct_follow_internal_redirect', static fn () => false, 10, 3);

        DeepLTranslator::setTestTranslator(function (array $texts): array {
            $out = [];
            foreach ($texts as $t) {
                $lower = strtolower((string) $t);
                if (str_contains($lower, 'transparantie') || str_contains($lower, 'onmisbaar')) {
                    $out[] = 'supply chain transparency is indispensable in a world without fixed rules';
                } elseif (str_contains($lower, 'artikelen')) {
                    $out[] = 'articles';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $result = InternalLinkTranslator::rewriteInternalUrl(
            '/artikelen/transparantie-in-de-keten-is-onmisbaar-in-een-wereld-zonder-vaste-regels/',
            'en',
            'nl'
        );

        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
        $this->assertSame(
            '/en/articles/supply-chain-transparency-is-essential-in-a-world-without-fixed-rules/',
            $result['url']
        );
        $this->assertStringNotContainsString('indispensable', $result['url']);
    }

    public function testRewriteInternalUrlIgnoresUnmatchedRedirectionRegexCandidates(): void
    {
        $this->loadRedItemStub();

        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_pll_languages_list'] = ['nl', 'en', 'de'];
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_http_redirect_map'] = [];
        // get_for_url always returns the prod-shaped false-positive team regex
        $GLOBALS['__nct_red_item_candidates'] = [
            new \Red_Item([
                'url' => '^/team/(.+)',
                'action_data' => '/over-ons/team/',
                'regex' => 1,
                'match_url' => 'regex',
            ]),
        ];

        $result = InternalLinkTranslator::rewriteInternalUrl(
            'https://2bhonest.test/en/projects/',
            'de',
            'en'
        );

        $this->assertStringNotContainsString('uber-uns/team', $result['url']);
        $this->assertStringNotContainsString('over-ons/team', $result['url']);
    }

    public function testRewriteInternalUrlFollowsMatchingRedirectionRegexThenMapsTwin(): void
    {
        $this->loadRedItemStub();

        $GLOBALS['__nct_home_url'] = 'https://2bhonest.test/';
        $GLOBALS['__nct_pll_languages_list'] = ['nl', 'en', 'de'];
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://2bhonest.test/over-ons/team/' => 1588,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '1588|de' => 12371,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            12371 => 'https://2bhonest.test/de/uber-uns/team/',
        ];
        $GLOBALS['__nct_http_redirect_map'] = [];
        $GLOBALS['__nct_red_item_candidates'] = [
            new \Red_Item([
                'url' => '^/team/(.+)',
                'action_data' => '/over-ons/team/',
                'regex' => 1,
                'match_url' => 'regex',
            ]),
        ];

        $result = InternalLinkTranslator::rewriteInternalUrl(
            'https://2bhonest.test/team/someone/',
            'de',
            'en'
        );

        $this->assertTrue($result['changed']);
        $this->assertSame('dynamic_resolution', $result['reason']);
        $this->assertSame('https://2bhonest.test/de/uber-uns/team/', $result['url']);
    }

    private function loadRedItemStub(): void
    {
        if (!class_exists('\Red_Item', false)) {
            require_once __DIR__ . '/stubs/Red_Item.php';
        }
    }
}

