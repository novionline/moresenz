<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\TermDuplicator;
use PHPUnit\Framework\TestCase;

class TermDuplicatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DeepLTranslator::setTestTranslator(null);
        $GLOBALS['__nct_terms'] = [];
        $GLOBALS['__nct_next_term_id'] = 2000;
        $GLOBALS['__nct_term_meta'] = [];
        $GLOBALS['__nct_wp_insert_term_term_exists'] = [];
        $GLOBALS['__nct_pll_translated_taxonomies'] = [];
        $GLOBALS['__nct_pll_term_map'] = [];
        $GLOBALS['__nct_pll_term_translations'] = [];
        $GLOBALS['__nct_pll_term_language'] = [];
        $GLOBALS['__nct_object_terms'] = [];
        $GLOBALS['__nct_object_terms_calls'] = [];
    }

    public function testTranslatableTaxonomyCreatesAndConnectsTranslatedTermsAndAssignsThem(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];
        $GLOBALS['__nct_terms'][10] = [
            'taxonomy' => 'resource-category',
            'name' => 'Resource Category',
            'slug' => 'resource-category',
            'description' => 'Some description',
            'parent' => 0,
        ];
        $GLOBALS['__nct_term_meta'][10]['wpseo_title'] = ['SEO title'];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $t) {
                if ($t === 'Resource Category') {
                    $out[] = 'Saber categoria';
                } elseif ($t === 'resource category') {
                    $out[] = 'saber categoria';
                } elseif ($t === 'Some description') {
                    $out[] = 'Alguna descripción';
                } elseif ($t === 'SEO title') {
                    $out[] = 'Título SEO';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $sourceTaxonomies = [
            'resource-category' => [
                [
                    'termId' => 10,
                    'slug' => 'resource-category',
                    'name' => 'Resource Category',
                    'description' => 'Some description',
                    'parent' => 0,
                ],
            ],
        ];

        TermDuplicator::syncPostTaxonomies(99, $sourceTaxonomies, 'en', 'es');

        //new term created
        $this->assertNotEmpty($GLOBALS['__nct_terms']);
        $createdId = null;
        foreach ($GLOBALS['__nct_terms'] as $id => $row) {
            if ((int) $id !== 10) {
                $createdId = (int) $id;
            }
        }
        $this->assertNotNull($createdId);
        $this->assertSame('saber-categoria', (string) ($GLOBALS['__nct_terms'][$createdId]['slug'] ?? ''));

        //translations connected
        $tr = $GLOBALS['__nct_pll_term_translations'][$createdId] ?? [];
        $this->assertIsArray($tr);
        $this->assertSame($createdId, (int) ($tr['es'] ?? 0));
        $this->assertSame(10, (int) ($tr['en'] ?? 0));

        //meta translated & copied
        $targetMeta = $GLOBALS['__nct_term_meta'][$createdId]['wpseo_title'][0] ?? '';
        $this->assertSame('Título SEO', $targetMeta);

        //assigned to post
        $assigned = $GLOBALS['__nct_object_terms'][99]['resource-category'] ?? null;
        $this->assertSame([$createdId], $assigned);
    }

    public function testNonTranslatableTaxonomyReusesExistingTermIdsButResyncsAssignments(): void
    {
        $GLOBALS['__nct_terms'][20] = [
            'taxonomy' => 'post_tag',
            'name' => 'Foo',
            'slug' => 'foo',
            'description' => '',
            'parent' => 0,
        ];

        $sourceTaxonomies = [
            'post_tag' => [
                [
                    'termId' => 20,
                    'slug' => 'foo',
                    'name' => 'Foo',
                    'description' => '',
                    'parent' => 0,
                ],
            ],
        ];

        //existing assignment should be cleared then re-added; our shim stores the last call, so final equals expected
        TermDuplicator::syncPostTaxonomies(77, $sourceTaxonomies, 'en', 'es');
        $assigned = $GLOBALS['__nct_object_terms'][77]['post_tag'] ?? null;
        $this->assertSame([20], $assigned);
    }

    public function testCopiesTargetLanguageTermWhenSourceTermLanguageIsMisassignedButUnmappable(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        // Shared/acronym category term exists only once and is tagged as NL.
        $GLOBALS['__nct_terms'][109] = [
            'taxonomy' => 'resource-category',
            'name' => 'APQP',
            'slug' => 'apqp',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][109] = 'nl';
        $GLOBALS['__nct_pll_term_map'] = [
            '109|nl' => 109,
            '109|en' => 0,
        ];

        $sourceTaxonomies = [
            'resource-category' => [
                [
                    'termId' => 109,
                    'slug' => 'apqp',
                    'name' => 'APQP',
                    'description' => '',
                    'parent' => 0,
                ],
            ],
        ];

        TermDuplicator::syncPostTaxonomies(4065, $sourceTaxonomies, 'en', 'nl');

        $assigned = $GLOBALS['__nct_object_terms'][4065]['resource-category'] ?? null;
        $this->assertSame([109], $assigned);
        $this->assertSame('nl', (string) ($GLOBALS['__nct_pll_term_language'][109] ?? ''));
    }

    public function testMatchesExistingTranslatedTermBySourcePointerMetaWhenSlugChanged(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        //source term
        $GLOBALS['__nct_terms'][10] = [
            'taxonomy' => 'resource-category',
            'name' => 'Resource Category',
            'slug' => 'resource-category',
            'description' => '',
            'parent' => 0,
        ];

        //existing translated term was renamed by client (slug differs from our current translated slug)
        $GLOBALS['__nct_terms'][555] = [
            'taxonomy' => 'resource-category',
            'name' => 'Custom renamed',
            'slug' => 'cliente-renombrado',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_id'] = ['10'];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_tax'] = ['resource-category'];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_lang'] = ['en'];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $t) {
                if ($t === 'resource category') {
                    $out[] = 'saber categoria';
                } elseif ($t === 'Resource Category') {
                    $out[] = 'Saber categoria';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $sourceTaxonomies = [
            'resource-category' => [
                [
                    'termId' => 10,
                    'slug' => 'resource-category',
                    'name' => 'Resource Category',
                    'description' => '',
                    'parent' => 0,
                ],
            ],
        ];

        TermDuplicator::syncPostTaxonomies(123, $sourceTaxonomies, 'en', 'es');

        $assigned = $GLOBALS['__nct_object_terms'][123]['resource-category'] ?? null;
        $this->assertSame([555], $assigned);

        //still stores pointer meta (kept authoritative)
        $this->assertSame('10', (string) ($GLOBALS['__nct_term_meta'][555]['_nct_source_term_id'][0] ?? ''));
    }

    public function testUpdatePathUpdatesExistingTranslatedTermAndClearsThenReassignsPostTerms(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        //source term
        $GLOBALS['__nct_terms'][10] = [
            'taxonomy' => 'resource-category',
            'name' => 'Resource Category',
            'slug' => 'resource-category',
            'description' => 'Old description',
            'parent' => 0,
        ];
        $GLOBALS['__nct_term_meta'][10]['wpseo_title'] = ['Old SEO'];

        //existing translated term (already connected via our pointer meta)
        $GLOBALS['__nct_terms'][555] = [
            'taxonomy' => 'resource-category',
            'name' => 'Old translated name',
            'slug' => 'old-slug',
            'description' => 'Old translated description',
            'parent' => 0,
        ];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_id'] = ['10'];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_tax'] = ['resource-category'];
        $GLOBALS['__nct_term_meta'][555]['_nct_source_term_lang'] = ['en'];
        $GLOBALS['__nct_term_meta'][555]['wpseo_title'] = ['Old SEO ES'];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $t) {
                if ($t === 'Resource Category') {
                    $out[] = 'Saber categoria';
                } elseif ($t === 'resource category') {
                    $out[] = 'saber categoria';
                } elseif ($t === 'Old description') {
                    $out[] = 'Nueva descripción';
                } elseif ($t === 'Old SEO') {
                    $out[] = 'Nuevo SEO';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $sourceTaxonomies = [
            'resource-category' => [
                [
                    'termId' => 10,
                    'slug' => 'resource-category',
                    'name' => 'Resource Category',
                    'description' => 'Old description',
                    'parent' => 0,
                ],
            ],
        ];

        TermDuplicator::syncPostTaxonomies(123, $sourceTaxonomies, 'en', 'es');

        //term was updated (not recreated)
        $this->assertSame('Saber categoria', (string) ($GLOBALS['__nct_terms'][555]['name'] ?? ''));
        $this->assertSame('saber-categoria', (string) ($GLOBALS['__nct_terms'][555]['slug'] ?? ''));
        $this->assertSame('Nueva descripción', (string) ($GLOBALS['__nct_terms'][555]['description'] ?? ''));

        //term meta was overwritten & translated
        $this->assertSame('Nuevo SEO', (string) ($GLOBALS['__nct_term_meta'][555]['wpseo_title'][0] ?? ''));

        //post terms: clear then reassign
        $calls = $GLOBALS['__nct_object_terms_calls'] ?? [];
        $this->assertIsArray($calls);

        $taxCalls = array_values(array_filter($calls, static function ($c) {
            return is_array($c)
                && (int) ($c['objectId'] ?? 0) === 123
                && (string) ($c['taxonomy'] ?? '') === 'resource-category';
        }));

        $this->assertCount(2, $taxCalls);
        $this->assertSame([], $taxCalls[0]['terms']);
        $this->assertSame([555], $taxCalls[1]['terms']);
    }

    public function testPostPrefetchMapsMisassignedTermToSourceLanguageTermBeforeSync(): void
    {
        // Simulate: source post is EN but has an ES term assigned; we should map it back to the EN term first.
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        // Terms in our fake store.
        $GLOBALS['__nct_terms'][101] = [
            'taxonomy' => 'resource-category',
            'name' => 'Blog (ES term assigned incorrectly)',
            'slug' => 'blog',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][101] = 'es';

        $GLOBALS['__nct_terms'][201] = [
            'taxonomy' => 'resource-category',
            'name' => 'Blog (EN)',
            'slug' => 'blog',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][201] = 'en';

        // Polylang term translation mapping: term 101 -> EN is 201.
        $GLOBALS['__nct_pll_term_map']['101|en'] = 201;

        // Spanish target translation for EN term 201.
        $GLOBALS['__nct_pll_term_map']['201|es'] = 301;

        // SourceTaxonomies as PostDuplicator would build from wp_get_object_terms (termId=101, but lang=es).
        $sourceTaxonomies = [
            'resource-category' => [
                [
                    'termId' => 101,
                    'slug' => 'blog',
                    'name' => 'Blog (ES term assigned incorrectly)',
                    'description' => '',
                    'parent' => 0,
                ],
            ],
        ];

        // When syncing to target post, TermDuplicator should prefer pll_get_term(sourceTermId, targetLang).
        // But because 101 isn't an EN term, the correct flow is: map to 201 first (done in PostDuplicator prefetch),
        // then resolve to 301. Here we validate the resolution piece by asserting we assign 301.
        TermDuplicator::syncPostTaxonomies(999, $sourceTaxonomies, 'en', 'es');

        // Without the prefetch fix, we'd either keep 101 or create incorrect terms; this asserts we land on 301.
        $assigned = $GLOBALS['__nct_object_terms'][999]['resource-category'] ?? null;
        $this->assertSame([301], $assigned);
    }

    public function testEnsureTranslatedTermUsesActualTermLanguageAsSourceLanguage(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        $GLOBALS['__nct_terms'][101] = [
            'taxonomy' => 'resource-category',
            'name' => 'Blog',
            'slug' => 'blog',
            'description' => 'Desc',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][101] = 'es';

        $seen = [];
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$seen): array {
            $seen[] = $sourceLang . '->' . $targetLang;
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        TermDuplicator::ensureTranslatedTerm(
            [
                'termId' => 101,
                'slug' => 'blog',
                'name' => 'Blog',
                'description' => 'Desc',
                'parent' => 0,
            ],
            'resource-category',
            'en',
            'nl'
        );

        $this->assertNotEmpty($seen);
        $this->assertSame('es->nl', $seen[0]);
    }

    public function testBulkRunDoesNotOverwriteExistingTranslationGroupWhenSourceTermIsDifferentLanguage(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        // ES term that was mistakenly attached to an EN post.
        $GLOBALS['__nct_terms'][101] = [
            'taxonomy' => 'resource-category',
            'name' => 'Blog',
            'slug' => 'blog',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][101] = 'es';

        // Canonical EN term in the same group.
        $GLOBALS['__nct_terms'][201] = [
            'taxonomy' => 'resource-category',
            'name' => 'Blog',
            'slug' => 'blog',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][201] = 'en';

        // existing translation group already has EN + ES.
        $GLOBALS['__nct_pll_term_translations'][201] = ['en' => 201, 'es' => 101];
        $GLOBALS['__nct_pll_term_translations'][101] = ['en' => 201, 'es' => 101];

        // allow pll_get_term to hop between languages.
        $GLOBALS['__nct_pll_term_map']['101|en'] = 201;
        $GLOBALS['__nct_pll_term_map']['201|es'] = 101;

        // Create NL translation from the ES term, but sourceLang is EN (bulk job context).
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlId = TermDuplicator::ensureTranslatedTerm(
            [
                'termId' => 101,
                'slug' => 'blog',
                'name' => 'Blog',
                'description' => '',
                'parent' => 0,
            ],
            'resource-category',
            'en',
            'nl'
        );

        $this->assertGreaterThan(0, $nlId);

        $group = $GLOBALS['__nct_pll_term_translations'][$nlId] ?? [];
        $this->assertIsArray($group);
        $this->assertSame(201, (int) ($group['en'] ?? 0));
        $this->assertSame(101, (int) ($group['es'] ?? 0));
        $this->assertSame($nlId, (int) ($group['nl'] ?? 0));
    }

    public function testEnsureTranslatedTermReusesExistingTermWhenSlugMustBeShared(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['resource-category'];

        // Source term (EN) with an acronym-like slug that should not change after translation.
        $GLOBALS['__nct_terms'][109] = [
            'taxonomy' => 'resource-category',
            'name' => 'APQP',
            'slug' => 'apqp',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][109] = 'en';

        // Simulate real WordPress behavior: cannot insert a second term with same slug in taxonomy.
        $GLOBALS['__nct_wp_insert_term_term_exists']['resource-category|apqp'] = 109;

        // Translation returns identical text; slug stays "apqp".
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $beforeCount = is_array($GLOBALS['__nct_terms'] ?? null) ? count($GLOBALS['__nct_terms']) : 0;

        $outId = TermDuplicator::ensureTranslatedTerm(
            [
                'termId' => 109,
                'slug' => 'apqp',
                'name' => 'APQP',
                'description' => '',
                'parent' => 0,
            ],
            'resource-category',
            'en',
            'nl'
        );

        $afterCount = is_array($GLOBALS['__nct_terms'] ?? null) ? count($GLOBALS['__nct_terms']) : 0;

        // WordPress enforces unique slugs per taxonomy, so we should reuse the existing term as a shared term.
        $this->assertSame(109, (int) $outId);
        $this->assertSame($beforeCount, $afterCount);
    }

    public function testEnsureTranslatedTermReusesExistingTermWhenSlugExistsButLanguageIsWrongAndNameMatchesTranslated(): void
    {
        $GLOBALS['__nct_pll_translated_taxonomies'] = ['product-feature'];

        // Source EN term.
        $GLOBALS['__nct_terms'][121] = [
            'taxonomy' => 'product-feature',
            'name' => 'Cp Cpk analysis',
            'slug' => 'cp-cpk-analysis',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][121] = 'en';

        // Existing target term already exists with translated slug+name, but is incorrectly labeled as EN.
        $GLOBALS['__nct_terms'][227] = [
            'taxonomy' => 'product-feature',
            'name' => 'Cp Cpk analyse',
            'slug' => 'cp-cpk-analyse',
            'description' => '',
            'parent' => 0,
        ];
        $GLOBALS['__nct_pll_term_language'][227] = 'en';

        // Simulate WP behavior: insert with slug cp-cpk-analyse fails due to existing term id 227.
        $GLOBALS['__nct_wp_insert_term_term_exists']['product-feature|cp-cpk-analyse'] = 227;

        // Translator: return Dutch values.
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $t) {
                if ($t === 'Cp Cpk analysis') {
                    $out[] = 'Cp Cpk analyse';
                } elseif ($t === 'cp cpk analysis') {
                    $out[] = 'cp cpk analyse';
                } else {
                    $out[] = $t;
                }
            }
            return ['success' => true, 'translations' => $out, 'error' => null];
        });

        $outId = TermDuplicator::ensureTranslatedTerm(
            [
                'termId' => 121,
                'slug' => 'cp-cpk-analysis',
                'name' => 'Cp Cpk analysis',
                'description' => '',
                'parent' => 0,
            ],
            'product-feature',
            'en',
            'nl'
        );

        // Should reuse existing term, relabel to nl, and connect translations.
        $this->assertSame(227, (int) $outId);
        $this->assertSame('nl', (string) ($GLOBALS['__nct_pll_term_language'][227] ?? ''));
        $group = $GLOBALS['__nct_pll_term_translations'][227] ?? [];
        $this->assertSame(121, (int) ($group['en'] ?? 0));
        $this->assertSame(227, (int) ($group['nl'] ?? 0));
    }
}

