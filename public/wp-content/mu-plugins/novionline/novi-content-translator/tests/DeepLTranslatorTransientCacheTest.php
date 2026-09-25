<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslationCache;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorTransientCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        DeepLTranslationCache::disable();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        $GLOBALS['__nct_transients'] = [];
    }

    public function testTransientCacheAvoidsSecondCallForSameInputs(): void
    {
        $GLOBALS['__nct_transients'] = [];

        $calls = 0;
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$calls): array {
            $calls++;
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[' . $targetLang . ']' . $t, $texts),
                'error' => null,
            ];
        });

        DeepLTranslationCache::enable(86400);

        $r1 = DeepLTranslator::translateTexts(['Hello', 'World'], 'en', 'nl', ['context' => 'plain']);
        $this->assertTrue($r1['success']);
        $this->assertSame(['[nl]Hello', '[nl]World'], $r1['translations']);
        $this->assertSame(1, $calls);

        $r2 = DeepLTranslator::translateTexts(['Hello', 'World'], 'en', 'nl', ['context' => 'plain']);
        $this->assertTrue($r2['success']);
        $this->assertSame(['[nl]Hello', '[nl]World'], $r2['translations']);
        $this->assertSame(1, $calls, 'Second call should be served from transients');
    }

    public function testTransientCacheOnlyTranslatesMisses(): void
    {
        $GLOBALS['__nct_transients'] = [];

        $calls = 0;
        $seenPayloads = [];
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$calls, &$seenPayloads): array {
            $calls++;
            $seenPayloads[] = $texts;
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[NL]' . $t, $texts),
                'error' => null,
            ];
        });

        DeepLTranslationCache::enable(86400);

        $r1 = DeepLTranslator::translateTexts(['A', 'B'], 'en', 'nl', ['context' => 'html']);
        $this->assertTrue($r1['success']);
        $this->assertSame(['[NL]A', '[NL]B'], $r1['translations']);
        $this->assertSame(1, $calls);
        $this->assertSame([['A', 'B']], $seenPayloads);

        // second call: A is cached, C is a miss => translator sees only ["C"]
        $r2 = DeepLTranslator::translateTexts(['A', 'C'], 'en', 'nl', ['context' => 'html']);
        $this->assertTrue($r2['success']);
        $this->assertSame(['[NL]A', '[NL]C'], $r2['translations']);
        $this->assertSame(2, $calls);
        $this->assertSame([['A', 'B'], ['C']], $seenPayloads);
    }
}

