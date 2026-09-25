<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\TermDuplicator;
use PHPUnit\Framework\TestCase;

class TermDuplicatorResolveCanonicalSourceTermIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_pll_term_map'] = [];
        $GLOBALS['__nct_pll_term_translations'] = [];
    }

    public function testResolvesViaTranslationMapWhenDirectLookupMissing(): void
    {
        $GLOBALS['__nct_terms'] = [
            208 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'NL Term'],
            133 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'DE Term'],
            50 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'EN Term'],
        ];
        $GLOBALS['__nct_pll_term_language'] = [
            208 => 'nl',
            133 => 'de',
            50 => 'en',
        ];

        // term 208 is "nl" but has no direct en mapping
        $GLOBALS['__nct_pll_term_translations'][208] = [
            'nl' => 208,
            'de' => 133,
        ];

        // direct fails
        $GLOBALS['__nct_pll_term_map']['208|en'] = 0;
        // but via de we can reach en=50
        $GLOBALS['__nct_pll_term_map']['133|en'] = 50;

        $resolved = TermDuplicator::resolveCanonicalSourceTermId(208, 'en', 'product-feature');
        $this->assertSame(50, $resolved);
    }

    public function testReturnsZeroWhenNoMappingExists(): void
    {
        $GLOBALS['__nct_terms'] = [
            208 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'NL Term'],
            133 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'DE Term'],
        ];
        $GLOBALS['__nct_pll_term_language'] = [
            208 => 'nl',
            133 => 'de',
        ];

        $GLOBALS['__nct_pll_term_translations'][208] = [
            'nl' => 208,
            'de' => 133,
        ];
        $resolved = TermDuplicator::resolveCanonicalSourceTermId(208, 'en', 'product-feature');
        $this->assertSame(0, $resolved);
    }

    public function testSlugScanFallbackFindsCanonicalTermInSourceLanguage(): void
    {
        $GLOBALS['__nct_terms'] = [
            208 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'NL Term'],
            173 => ['taxonomy' => 'product-feature', 'slug' => 'fmea-integration', 'name' => 'EN Term'],
        ];
        $GLOBALS['__nct_pll_term_language'] = [
            208 => 'nl',
            173 => 'en',
        ];

        // No Polylang maps available.
        $GLOBALS['__nct_pll_term_map'] = [];
        $GLOBALS['__nct_pll_term_translations'] = [];

        $resolved = TermDuplicator::resolveCanonicalSourceTermId(208, 'en', 'product-feature');
        $this->assertSame(173, $resolved);
    }
}

