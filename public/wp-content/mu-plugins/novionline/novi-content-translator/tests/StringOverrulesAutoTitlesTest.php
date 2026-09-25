<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslationCache;
use NoviOnline\ContentTranslator\Core\StringOverrules;
use PHPUnit\Framework\TestCase;

final class StringOverrulesAutoTitlesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        StringOverrules::setRulesOverride(null);
        StringOverrules::setAutoTitlesOverride(null);
        StringOverrules::clearAutoTitlesCache();
        DeepLTranslationCache::disable();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        $GLOBALS['__nct_transients'] = [];
        $GLOBALS['__nct_filters'] = [];
        $GLOBALS['__nct_posts'] = [];
        $GLOBALS['__nct_pll_post_language'] = [];
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
        $GLOBALS['__nct_posts'] = [];
        $GLOBALS['__nct_pll_post_language'] = [];
    }

    public function testShouldAutoIncludeFiltersShortAndDenylistedTitles(): void
    {
        $this->assertTrue(StringOverrules::shouldAutoIncludeTitle('Daniek Brinkhof'));
        $this->assertTrue(StringOverrules::shouldAutoIncludeTitle('KIEM'));
        $this->assertTrue(StringOverrules::shouldAutoIncludeTitle('Brancheplan Verpakkingen'));

        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle('Plus'));
        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle('plus'));
        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle('And'));
        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle('AB'));
        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle(''));
        $this->assertFalse(StringOverrules::shouldAutoIncludeTitle('NL - Pattern'));
    }

    public function testGetApplicableRulesMergesAutoTitlesFromKeepAsIsPosts(): void
    {
        $GLOBALS['__nct_posts'] = [
            10 => [
                'post_title' => 'Daniek Brinkhof',
                'post_type' => 'team',
                'post_status' => 'publish',
            ],
            11 => [
                'post_title' => 'KIEM',
                'post_type' => 'project',
                'post_status' => 'publish',
            ],
            12 => [
                'post_title' => 'Plus',
                'post_type' => 'project',
                'post_status' => 'publish',
            ],
            13 => [
                'post_title' => 'About',
                'post_type' => 'page',
                'post_status' => 'publish',
            ],
            14 => [
                'post_title' => 'English Only',
                'post_type' => 'team',
                'post_status' => 'publish',
            ],
            15 => [
                'post_title' => 'Trashed Name',
                'post_type' => 'team',
                'post_status' => 'trash',
            ],
        ];
        $GLOBALS['__nct_pll_post_language'] = [
            10 => 'nl',
            11 => 'nl',
            12 => 'nl',
            13 => 'nl',
            14 => 'en',
            15 => 'nl',
        ];

        StringOverrules::setRulesOverride([]);

        $rules = StringOverrules::getApplicableRules('nl', 'en');
        $sources = array_map(static function (array $rule): string {
            return $rule['source'];
        }, $rules);

        $this->assertContains('Daniek Brinkhof', $sources);
        $this->assertContains('KIEM', $sources);
        $this->assertNotContains('Plus', $sources);
        $this->assertNotContains('About', $sources);
        $this->assertNotContains('English Only', $sources);
        $this->assertNotContains('Trashed Name', $sources);
    }

    public function testProtectUsesAutoTeamAndProjectTitlesInBodyText(): void
    {
        StringOverrules::setRulesOverride([]);
        StringOverrules::setAutoTitlesOverride([
            'nl' => ['Daniek Brinkhof', 'KIEM'],
        ]);

        [$protected, $map, $didProtect] = StringOverrules::protect(
            'Interview met Daniek Brinkhof over KIEM',
            'nl',
            'en'
        );

        $this->assertTrue($didProtect);
        $this->assertCount(2, $map);
        $this->assertStringContainsString('translate="no"', $protected);
        $this->assertStringContainsString('Daniek Brinkhof', $protected);
        $this->assertStringContainsString('KIEM', $protected);

        $restored = StringOverrules::restore(
            'Interview with <span translate="no" data-nct-ov="0">Daniek Brinkhof</span> about <span translate="no" data-nct-ov="1">KIEM</span>',
            $map
        );
        $this->assertSame('Interview with Daniek Brinkhof about KIEM', $restored);
    }

    public function testDeepLTranslatorKeepsAutoTitlesUntranslated(): void
    {
        StringOverrules::setRulesOverride([]);
        StringOverrules::setAutoTitlesOverride([
            'nl' => ['Daniek Brinkhof'],
        ]);

        $seen = [];
        DeepLTranslator::setTestTranslator(static function (array $texts) use (&$seen): array {
            $seen = $texts;
            return [
                'success' => true,
                'translations' => array_map(static function (string $text): string {
                    return str_replace('Interview met', 'Interview with', $text);
                }, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Interview met Daniek Brinkhof'],
            'nl',
            'en',
            ['context' => 'plain']
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Interview with Daniek Brinkhof'], $result['translations']);
        $this->assertStringContainsString('translate="no"', $seen[0]);
        $this->assertStringContainsString('Daniek Brinkhof', $seen[0]);
        $this->assertStringNotContainsString('data-nct-ov', $result['translations'][0]);
    }

    public function testManualSettingsWinAlongsideAutoTitles(): void
    {
        StringOverrules::setRulesOverride([
            'nl' => [
                [
                    'source' => 'KIEM',
                    'also_in_sentence' => true,
                    'ignore_casing' => false,
                ],
                [
                    'source' => 'Brancheplan Verpakkingen',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                ],
            ],
        ]);
        StringOverrules::setAutoTitlesOverride([
            'nl' => ['KIEM', 'Daniek Brinkhof'],
        ]);

        $rules = StringOverrules::getApplicableRules('nl', 'en');
        $bySource = [];
        foreach ($rules as $rule) {
            $bySource[$rule['source']] = $rule;
        }

        $this->assertArrayHasKey('KIEM', $bySource);
        $this->assertFalse($bySource['KIEM']['ignore_casing'], 'manual ignore_casing must win over auto default');
        $this->assertArrayHasKey('Brancheplan Verpakkingen', $bySource);
        $this->assertArrayHasKey('Daniek Brinkhof', $bySource);

        [$protected, $map, $didProtect] = StringOverrules::protect(
            'KIEM en Daniek Brinkhof bij Brancheplan Verpakkingen',
            'nl',
            'en'
        );
        $this->assertTrue($didProtect);
        $this->assertGreaterThanOrEqual(3, count($map));
        $this->assertStringContainsString('KIEM', $protected);
        $this->assertStringContainsString('Daniek Brinkhof', $protected);
        $this->assertStringContainsString('Brancheplan Verpakkingen', $protected);
    }

    public function testPlusIsNotAutoProtectedInBodyText(): void
    {
        StringOverrules::setRulesOverride([]);
        StringOverrules::setAutoTitlesOverride([
            'nl' => array_values(array_filter(
                ['Plus', 'KIEM'],
                static fn(string $title): bool => StringOverrules::shouldAutoIncludeTitle($title)
            )),
        ]);

        $rules = StringOverrules::getApplicableRules('nl', 'en');
        $sources = array_column($rules, 'source');
        $this->assertNotContains('Plus', $sources);
        $this->assertContains('KIEM', $sources);

        [$protected, $map, $didProtect] = StringOverrules::protect(
            'Choose Plus or KIEM today',
            'nl',
            'en'
        );
        $this->assertTrue($didProtect);
        $this->assertCount(1, $map);
        $this->assertStringContainsString('KIEM', $protected);
        $this->assertStringNotContainsString('data-nct-ov="1"', $protected);
        //Plus stays plain text (would still be sent to DeepL as a normal word)
        $this->assertMatchesRegularExpression('/\bPlus\b/', $protected);
        $this->assertStringNotContainsString('>Plus<', $protected);
    }

    public function testAutoPostTypesFilterDefaultsToKeepAsIsTypes(): void
    {
        $types = StringOverrules::getAutoDontTranslatePostTypes();
        $this->assertContains('team', $types);
        $this->assertContains('project', $types);

        add_filter('nct_auto_dont_translate_post_types', static function (): array {
            return ['vacancy'];
        });

        $this->assertSame(['vacancy'], StringOverrules::getAutoDontTranslatePostTypes());
    }

    public function testAutoTitlesFilterCanAdjustCollectedList(): void
    {
        $GLOBALS['__nct_posts'] = [
            20 => [
                'post_title' => 'Daniek Brinkhof',
                'post_type' => 'team',
                'post_status' => 'publish',
            ],
        ];
        $GLOBALS['__nct_pll_post_language'] = [20 => 'nl'];

        $seenLang = null;
        add_filter('nct_auto_dont_translate_titles', static function (array $titles, string $sourceLang) use (&$seenLang): array {
            $seenLang = $sourceLang;
            $titles[] = 'Custom Brand';
            return $titles;
        }, 10, 2);

        $titles = StringOverrules::getAutoDontTranslateTitles('nl');
        $this->assertSame('nl', $seenLang);
        $this->assertContains('Daniek Brinkhof', $titles);
        $this->assertContains('Custom Brand', $titles);
    }
}
