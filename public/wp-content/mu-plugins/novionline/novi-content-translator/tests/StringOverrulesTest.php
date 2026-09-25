<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslationCache;
use NoviOnline\ContentTranslator\Core\StringOverrules;
use PHPUnit\Framework\TestCase;

final class StringOverrulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StringOverrules::setRulesOverride(null);
        //disable auto team/project titles unless a test opts in
        StringOverrules::setAutoTitlesOverride([]);
        StringOverrules::clearAutoTitlesCache();
        DeepLTranslationCache::disable();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        $GLOBALS['__nct_transients'] = [];
        $GLOBALS['__nct_filters'] = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        StringOverrules::setRulesOverride(null);
        StringOverrules::setAutoTitlesOverride(null);
        StringOverrules::clearAutoTitlesCache();
        DeepLTranslationCache::disable();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        $GLOBALS['__nct_transients'] = [];
        $GLOBALS['__nct_filters'] = [];
    }

    public function testFindMatchesWholeWordNotSubstring(): void
    {
        $matches = StringOverrules::findMatches('Welkom bij KIEMEN vandaag', 'KIEM', true, true);
        $this->assertSame([], $matches);

        $matches = StringOverrules::findMatches('Welkom bij KIEM vandaag', 'KIEM', true, true);
        $this->assertCount(1, $matches);
        $this->assertSame('KIEM', $matches[0]['text']);
    }

    public function testMultiWordPhraseIsOneTerm(): void
    {
        $matches = StringOverrules::findMatches('Yes KIEM IS COOL today', 'KIEM IS COOL', true, true);
        $this->assertCount(1, $matches);
        $this->assertSame('KIEM IS COOL', $matches[0]['text']);
    }

    public function testSentenceOffRequiresFullString(): void
    {
        $matches = StringOverrules::findMatches('Welkom bij KIEM', 'KIEM', false, true);
        $this->assertSame([], $matches);

        $matches = StringOverrules::findMatches('KIEM', 'KIEM', false, true);
        $this->assertCount(1, $matches);
    }

    public function testIgnoreCasing(): void
    {
        $matches = StringOverrules::findMatches('welkom bij kiem', 'KIEM', true, true);
        $this->assertCount(1, $matches);
        $this->assertSame('kiem', $matches[0]['text']);

        $matches = StringOverrules::findMatches('welkom bij kiem', 'KIEM', true, false);
        $this->assertSame([], $matches);
    }

    public function testLongestMatchWins(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
                [
                    'source' => 'KIEM IS COOL',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protected, $map, $didProtect] = StringOverrules::protect('Yes KIEM IS COOL today', 'nl', 'en');
        $this->assertTrue($didProtect);
        $this->assertCount(1, $map);
        $this->assertStringContainsString('KIEM IS COOL', $protected);
        $this->assertStringNotContainsString('data-nct-ov="1"', $protected);
    }

    public function testProtectRestoreKeepsWordUntranslated(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protected, $map, $didProtect] = StringOverrules::protect('Bezoek KIEM vandaag', 'nl', 'en');
        $this->assertTrue($didProtect);
        $this->assertStringContainsString('translate="no"', $protected);

        $restored = StringOverrules::restore('Visit <span translate="no" data-nct-ov="0">KIEM</span> today', $map);
        $this->assertSame('Visit KIEM today', $restored);
        $this->assertStringNotContainsString('data-nct-ov', $restored);
    }

    public function testRestoreStripsDeepLWrappingQuotesAroundProtectedSpan(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        // mid-sentence brand term (DeepL often wraps translate="no" in curly quotes + spaces)
        [$protected, $map] = StringOverrules::protect('Het KIEM-programma is speciaal', 'nl', 'en');
        $this->assertStringContainsString('data-nct-ov="0"', $protected);
        $this->assertFalse($map[0]['keep_quote_before']);
        $this->assertFalse($map[0]['keep_quote_after']);

        $deeplCurly = 'The “ <span translate="no" data-nct-ov="0">KIEM</span> ” program is special';
        $this->assertSame(
            'The KIEM program is special',
            StringOverrules::restore($deeplCurly, $map)
        );

        // heading-style ASCII quotes DeepL adds around the span
        [$protectedWhy, $mapWhy] = StringOverrules::protect('Waarom KIEM?', 'nl', 'en');
        unset($protectedWhy);
        $deeplAscii = 'Why " <span translate="no" data-nct-ov="0">KIEM</span>"?';
        $this->assertSame('Why KIEM?', StringOverrules::restore($deeplAscii, $mapWhy));
    }

    public function testRestoreKeepsIntentionalSourceQuotes(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protected, $map] = StringOverrules::protect('"KIEM" is cool', 'nl', 'en');
        $this->assertTrue($map[0]['keep_quote_before']);
        $this->assertTrue($map[0]['keep_quote_after']);
        $this->assertStringStartsWith('"', $protected);

        $restored = StringOverrules::restore(
            '"<span translate="no" data-nct-ov="0">KIEM</span>" is cool',
            $map
        );
        $this->assertSame('"KIEM" is cool', $restored);
    }

    public function testDeepLTranslatorStripsQuotedProtectSpans(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    // simulate DeepL wrapping the protect span in curly quotes
                    $text = str_replace('Het ', 'The ', $text);
                    $text = str_replace('-programma is speciaal', ' program is special', $text);
                    return preg_replace(
                        '/(<span\b[^>]*data-nct-ov[^>]*>.*?<\/span>)/is',
                        '“ $1 ”',
                        $text
                    ) ?? $text;
                }, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Het KIEM-programma is speciaal'],
            'nl',
            'en',
            ['context' => 'html']
        );
        $this->assertTrue($result['success']);
        $this->assertSame(['The KIEM program is special'], $result['translations']);
        $this->assertStringNotContainsString('“', $result['translations'][0]);
        $this->assertStringNotContainsString('”', $result['translations'][0]);
    }

    public function testApplyCasingPreservesMatchedStyleForBrands(): void
    {
        $this->assertSame('KIEM', StringOverrules::applyCasing('KIEM', 'kiem'));
        $this->assertSame('kiem', StringOverrules::applyCasing('kiem', 'KIEM'));
        $this->assertSame('Kiem', StringOverrules::applyCasing('Kiem', 'KIEM'));
    }

    public function testApplyCasingGlossaryKeepsAuthoredTitleCase(): void
    {
        //title-case source match must not force "Due Diligence"
        $this->assertSame(
            'Due diligence',
            StringOverrules::applyCasing('Ketenverantwoordelijkheid', 'Due diligence')
        );
        $this->assertSame(
            'Due diligence',
            StringOverrules::applyCasing('Ketenimpact', 'Due diligence')
        );
        $this->assertSame(
            'ESG legislation',
            StringOverrules::applyCasing('Esg Wet- En Regelgeving', 'ESG legislation')
        );
    }

    public function testApplyCasingGlossaryLowercaseAndUppercase(): void
    {
        $this->assertSame(
            'due diligence',
            StringOverrules::applyCasing('ketenverantwoordelijkheid', 'Due diligence')
        );
        $this->assertSame(
            'DUE DILIGENCE',
            StringOverrules::applyCasing('KETENVERANTWOORDELIJKHEID', 'Due diligence')
        );
        $this->assertSame(
            'ESG LEGISLATION',
            StringOverrules::applyCasing('ESG WET- EN REGELGEVING', 'ESG legislation')
        );
    }

    public function testApplyCasingGlossarySlugify(): void
    {
        //hyphenated matched token → slugify even without force flag
        $this->assertSame(
            'due-diligence',
            StringOverrules::applyCasing('keten-verantwoordelijkheid', 'Due diligence')
        );
        $this->assertSame(
            'DUE-DILIGENCE',
            StringOverrules::applyCasing('KETEN-VERANTWOORDELIJKHEID', 'Due diligence')
        );
        //force slug context (whole string is a post_name)
        $this->assertSame(
            'due-diligence',
            StringOverrules::applyCasing('ketenverantwoordelijkheid', 'Due diligence', true)
        );
        $this->assertSame(
            'esg-legislation',
            StringOverrules::applyCasing('esg-wet-en-regelgeving', 'ESG legislation', true)
        );
        $this->assertSame(
            'environment',
            StringOverrules::applyCasing('klimaat', 'Environment', true)
        );
    }

    public function testIsSlugContextText(): void
    {
        $this->assertTrue(StringOverrules::isSlugContextText('ketenverantwoordelijkheid'));
        $this->assertTrue(StringOverrules::isSlugContextText('esg-wet-en-regelgeving'));
        $this->assertFalse(StringOverrules::isSlugContextText('Bekijk ketenimpact vandaag'));
        //title-case page title must not be treated as a slug
        $this->assertFalse(StringOverrules::isSlugContextText('Ketenverantwoordelijkheid'));
        $this->assertFalse(StringOverrules::isSlugContextText('KETENVERANTWOORDELIJKHEID'));
        $this->assertFalse(StringOverrules::isSlugContextText('hello world'));
    }

    public function testValidationRequiresSourceAndBlocksDuplicates(): void
    {
        $ok = StringOverrules::validateAndNormalize([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ], ['nl', 'en', 'de']);
        $this->assertTrue($ok['ok']);
        $this->assertArrayNotHasKey('targets', $ok['rules']['nl'][0]);

        $withTargets = StringOverrules::validateAndNormalize([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence', 'de' => ''],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ], ['nl', 'en', 'de']);
        $this->assertTrue($withTargets['ok']);
        $this->assertSame(['en' => 'Due diligence'], $withTargets['rules']['nl'][0]['targets']);

        $badTargetLang = StringOverrules::validateAndNormalize([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['fr' => 'Diligence'],
                ],
            ],
        ], ['nl', 'en']);
        $this->assertFalse($badTargetLang['ok']);

        $empty = StringOverrules::validateAndNormalize([
            'nl' => [
                ['source' => ''],
            ],
        ], ['nl', 'en']);
        $this->assertFalse($empty['ok']);

        $dup = StringOverrules::validateAndNormalize([
            'nl' => [
                ['source' => 'KIEM'],
                ['source' => 'kiem'],
            ],
        ], ['nl', 'en']);
        $this->assertFalse($dup['ok']);
    }

    public function testNormalizeKeepsOptionalTargets(): void
    {
        $normalized = StringOverrules::normalizeStoredRules([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'also_in_sentence' => true,
                    'ignore_casing' => false,
                    'targets' => ['en' => 'Due diligence', 'de' => '', 'nl' => 'ignored'],
                ],
            ],
        ]);
        $this->assertSame('Ketenimpact', $normalized['nl'][0]['source']);
        $this->assertFalse($normalized['nl'][0]['ignore_casing']);
        $this->assertSame(['en' => 'Due diligence'], $normalized['nl'][0]['targets']);
    }

    public function testProtectRestoreAppliesGlossaryTarget(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protected, $map, $didProtect] = StringOverrules::protect('Bekijk Ketenimpact vandaag', 'nl', 'en');
        $this->assertTrue($didProtect);
        $this->assertStringContainsString('translate="no"', $protected);
        $this->assertSame('Due diligence', $map[0]['target']);
        $this->assertFalse($map[0]['slugify']);

        $restored = StringOverrules::restore(
            'View <span translate="no" data-nct-ov="0">Ketenimpact</span> today',
            $map
        );
        //title-case match keeps glossary authored casing
        $this->assertSame('View Due diligence today', $restored);

        [$protectedLower, $mapLower] = StringOverrules::protect('Bekijk ketenimpact vandaag', 'nl', 'en');
        $this->assertSame(
            'View due diligence today',
            StringOverrules::restore(
                'View <span translate="no" data-nct-ov="0">ketenimpact</span> today',
                $mapLower
            )
        );
    }

    public function testProtectRestoreGlossaryUppercaseAndSlugContext(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenverantwoordelijkheid',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protectedUpper, $mapUpper] = StringOverrules::protect('KETENVERANTWOORDELIJKHEID', 'nl', 'en');
        $this->assertFalse($mapUpper[0]['slugify']);
        $this->assertSame(
            'DUE DILIGENCE',
            StringOverrules::restore(
                '<span translate="no" data-nct-ov="0">KETENVERANTWOORDELIJKHEID</span>',
                $mapUpper
            )
        );

        [$protectedSlug, $mapSlug] = StringOverrules::protect('ketenverantwoordelijkheid', 'nl', 'en');
        $this->assertTrue($mapSlug[0]['slugify']);
        $this->assertSame(
            'due-diligence',
            StringOverrules::restore(
                '<span translate="no" data-nct-ov="0">ketenverantwoordelijkheid</span>',
                $mapSlug
            )
        );

        //hyphenated matched token slugifies via applyCasing even when source rule has no hyphens
        $this->assertSame(
            'due-diligence',
            StringOverrules::applyCasing('keten-verantwoordelijkheid', 'Due diligence')
        );
    }

    public function testProtectRestoreGlossaryTitlePageKeepsAuthoredCasing(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenverantwoordelijkheid',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        //standalone title-case page title → keep glossary casing (not slugify)
        [$protected, $map] = StringOverrules::protect('Ketenverantwoordelijkheid', 'nl', 'en');
        $this->assertFalse($map[0]['slugify']);
        $this->assertSame(
            'Due diligence',
            StringOverrules::restore(
                '<span translate="no" data-nct-ov="0">Ketenverantwoordelijkheid</span>',
                $map
            )
        );

        [$protectedProse, $mapProse] = StringOverrules::protect(
            'Onze aanpak: Ketenverantwoordelijkheid',
            'nl',
            'en'
        );
        $this->assertFalse($mapProse[0]['slugify']);
        $this->assertSame(
            'Our approach: Due diligence',
            StringOverrules::restore(
                'Our approach: <span translate="no" data-nct-ov="0">Ketenverantwoordelijkheid</span>',
                $mapProse
            )
        );
    }

    public function testGlossaryRespectsAlsoInSentenceAndIgnoreCasing(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => false,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protectedMid, $mapMid, $didMid] = StringOverrules::protect('Bekijk Ketenimpact vandaag', 'nl', 'en');
        $this->assertFalse($didMid);
        $this->assertSame([], $mapMid);
        $this->assertSame('Bekijk Ketenimpact vandaag', $protectedMid);

        [$protectedExact, $mapExact, $didExact] = StringOverrules::protect('Ketenimpact', 'nl', 'en');
        $this->assertTrue($didExact);
        $this->assertFalse($mapExact[0]['slugify']);
        $this->assertSame(
            'Due diligence',
            StringOverrules::restore(
                '<span translate="no" data-nct-ov="0">Ketenimpact</span>',
                $mapExact
            )
        );

        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => false,
                ],
            ],
        ]);

        [$protectedCase, $mapCase, $didCase] = StringOverrules::protect('bekijk ketenimpact vandaag', 'nl', 'en');
        $this->assertFalse($didCase);
        $this->assertSame([], $mapCase);

        [$protectedExactCase, $mapExactCase] = StringOverrules::protect('Bekijk Ketenimpact vandaag', 'nl', 'en');
        $this->assertNotEmpty($mapExactCase);
        $this->assertSame(
            'View Due diligence today',
            StringOverrules::restore(
                'View <span translate="no" data-nct-ov="0">Ketenimpact</span> today',
                $mapExactCase
            )
        );
    }

    public function testDontTranslateWithoutTargetsUnchanged(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Bezoek', 'Visit', $text);
                }, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Bezoek KIEM en kiem en Kiem'],
            'nl',
            'en',
            ['context' => 'plain']
        );
        $this->assertSame(['Visit KIEM en kiem en Kiem'], $result['translations']);
    }

    public function testMultiWordGlossaryTargetInSentence(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'ESG wet- en regelgeving',
                    'targets' => ['en' => 'ESG legislation'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        [$protected, $map] = StringOverrules::protect(
            'Lees meer over ESG wet- en regelgeving hier',
            'nl',
            'en'
        );
        $this->assertFalse($map[0]['slugify']);
        $this->assertSame(
            'Read more about ESG legislation here',
            StringOverrules::restore(
                'Read more about <span translate="no" data-nct-ov="0">ESG wet- en regelgeving</span> here',
                $map
            )
        );
    }

    public function testDeepLTranslatorAppliesGlossaryTargetAroundFakeTranslator(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Bekijk', 'View', $text);
                }, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Bekijk Ketenimpact vandaag'], 'nl', 'en', ['context' => 'plain']);
        $this->assertTrue($result['success']);
        $this->assertSame(['View Due diligence vandaag'], $result['translations']);
    }

    public function testDeepLTranslatorGlossarySlugAndAllCaps(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenverantwoordelijkheid',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        });

        $slug = DeepLTranslator::translateTexts(['ketenverantwoordelijkheid'], 'nl', 'en', ['context' => 'plain']);
        $this->assertSame(['due-diligence'], $slug['translations']);

        $caps = DeepLTranslator::translateTexts(['KETENVERANTWOORDELIJKHEID'], 'nl', 'en', ['context' => 'plain']);
        $this->assertSame(['DUE DILIGENCE'], $caps['translations']);

        $pageTitle = DeepLTranslator::translateTexts(
            ['Ketenverantwoordelijkheid'],
            'nl',
            'en',
            ['context' => 'plain']
        );
        $this->assertSame(['Due diligence'], $pageTitle['translations']);

        $title = DeepLTranslator::translateTexts(
            ['Pagina: Ketenverantwoordelijkheid'],
            'nl',
            'en',
            ['context' => 'plain']
        );
        $this->assertSame(['Pagina: Due diligence'], $title['translations']);
    }

    public function testCacheDoesNotBypassGlossaryRestore(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'Ketenimpact',
                    'targets' => ['en' => 'Due diligence'],
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        $calls = 0;
        DeepLTranslator::setTestTranslator(static function (array $texts) use (&$calls): array {
            $calls++;
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Bekijk', 'View', $text);
                }, $texts),
                'error' => null,
            ];
        });

        DeepLTranslationCache::enable(86400);

        $r1 = DeepLTranslator::translateTexts(['Bekijk Ketenimpact'], 'nl', 'en', ['context' => 'html']);
        $r2 = DeepLTranslator::translateTexts(['Bekijk Ketenimpact'], 'nl', 'en', ['context' => 'html']);

        $this->assertSame(['View Due diligence'], $r1['translations']);
        $this->assertSame(['View Due diligence'], $r2['translations']);
        $this->assertSame(1, $calls);
        $this->assertStringNotContainsString('data-nct-ov', $r2['translations'][0]);
    }

    public function testDeepLTranslatorAppliesDontTranslateAroundFakeTranslator(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        $seen = [];
        DeepLTranslator::setTestTranslator(static function (array $texts) use (&$seen): array {
            $seen = $texts;
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Bezoek', 'Visit', $text);
                }, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Bezoek KIEM vandaag'], 'nl', 'en', ['context' => 'plain']);
        $this->assertTrue($result['success']);
        $this->assertSame(['Visit KIEM vandaag'], $result['translations']);
        $this->assertStringContainsString('translate="no"', $seen[0]);
        $this->assertStringNotContainsString('data-nct-ov', $result['translations'][0]);
    }

    public function testCacheDoesNotBypassDontTranslateRestore(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);

        $calls = 0;
        DeepLTranslator::setTestTranslator(static function (array $texts) use (&$calls): array {
            $calls++;
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Bezoek', 'Visit', $text);
                }, $texts),
                'error' => null,
            ];
        });

        DeepLTranslationCache::enable(86400);

        $r1 = DeepLTranslator::translateTexts(['Bezoek KIEM'], 'nl', 'en', ['context' => 'html']);
        $r2 = DeepLTranslator::translateTexts(['Bezoek KIEM'], 'nl', 'en', ['context' => 'html']);

        $this->assertSame(['Visit KIEM'], $r1['translations']);
        $this->assertSame(['Visit KIEM'], $r2['translations']);
        $this->assertSame(1, $calls);
        $this->assertStringNotContainsString('data-nct-ov', $r2['translations'][0]);
    }

    public function testDeleteTransientsContainingNeedles(): void
    {
        $GLOBALS['__nct_transients'] = [
            'nct_deepl_t_aaa' => ['value' => 'Visit KIEM today', 'expires_at' => 0],
            'nct_deepl_t_bbb' => ['value' => 'Hello world', 'expires_at' => 0],
            'other_key' => ['value' => 'KIEM', 'expires_at' => 0],
        ];

        $deleted = DeepLTranslationCache::deleteTransientsContaining(['KIEM']);
        $this->assertSame(1, $deleted);
        $this->assertArrayNotHasKey('nct_deepl_t_aaa', $GLOBALS['__nct_transients']);
        $this->assertArrayHasKey('nct_deepl_t_bbb', $GLOBALS['__nct_transients']);
        $this->assertArrayHasKey('other_key', $GLOBALS['__nct_transients']);
    }

    public function testFindMatchesSkipsNeedlesInsideHrefAttributes(): void
    {
        $html = 'Visit <a href="https://2bhonest.test/en/environment/">our environment page</a> about environment.';
        $matches = StringOverrules::findMatches($html, '2bhonest', true, true);
        $this->assertSame([], $matches, 'host inside href must not match');

        $matches = StringOverrules::findMatches($html, 'environment', true, true);
        $this->assertCount(2, $matches, 'path segment in href skipped; link text + body text match');
        $this->assertSame('environment', $matches[0]['text']);
        $this->assertSame('environment', $matches[1]['text']);
        //first hit is link text, second is trailing body copy
        $this->assertStringContainsString('>our environment page<', mb_substr($html, $matches[0]['start'] - 5, 30));
    }

    public function testFindMatchesSkipsNeedlesInsideUnicodeEscapedHrefAttributes(): void
    {
        //Gutenberg post_content stores tags as literal \u003c / \u0022 sequences
        $html = 'Then expected to \u003ca href=\u0022https://2bhonest.test/en/strategy/reporting/\u0022\u003ecommunicate and report\u003c/a\u003e on these matters.';
        $matches = StringOverrules::findMatches($html, '2bhonest', true, true);
        $this->assertSame([], $matches, 'host inside unicode-escaped href must not match');

        StringOverrules::setRulesOverride([
            'en' => [
                [
                    'source' => '2bhonest',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);
        [$protected, $map, $did] = StringOverrules::protect($html, 'en', 'de');
        $this->assertFalse($did);
        $this->assertSame($html, $protected);
        $this->assertStringNotContainsString('data-nct-ov', $protected);
        $this->assertStringNotContainsString('<span translate="no"', $protected);
    }
}
