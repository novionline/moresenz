<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorFormalityRetryTest extends TestCase
{
    public function testRetriesWithoutFormalityWhenTargetDoesNotSupportIt(): void
    {
        $calls = 0;
        $GLOBALS['__nct_transients'] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$calls): array {
            $calls++;

            // first call: simulate DeepL "formality not supported" rejection
            if (
                $calls === 1
                && $targetLang === 'th'
                && isset($options['formality'])
            ) {
                return [
                    'success' => false,
                    'translations' => $texts,
                    'error' => "DeepL translation failed: Bad request, message: 'formality' is not supported for given 'target_lang'.",
                ];
            }

            // retry call: should arrive without formality
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[TH]' . $t, $texts),
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Hello'], 'en', 'th', ['context' => 'html', 'formality' => 'more']);

        $this->assertTrue($result['success']);
        $this->assertSame(['[TH]Hello'], $result['translations']);
        $this->assertSame(2, $calls);
    }

    public function testCachesFormalityUnsupportedForOneDayToAvoidSecondRequest(): void
    {
        $calls = 0;
        $GLOBALS['__nct_transients'] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$calls): array {
            $calls++;

            // if formality is present, simulate "not supported" so the first call triggers caching+retry
            if (isset($options['formality'])) {
                return [
                    'success' => false,
                    'translations' => $texts,
                    'error' => "DeepL translation failed: Bad request, message: 'formality' is not supported for given 'target_lang'.",
                ];
            }

            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[TH]' . $t, $texts),
                'error' => null,
            ];
        });

        $r1 = DeepLTranslator::translateTexts(['Hello'], 'en', 'th', ['context' => 'html', 'formality' => 'more']);
        $this->assertTrue($r1['success']);
        $this->assertSame(['[TH]Hello'], $r1['translations']);

        // second call should skip formality entirely (transient cache) and succeed in one call
        $callsBefore = $calls;
        $r2 = DeepLTranslator::translateTexts(['Hello'], 'en', 'th', ['context' => 'html', 'formality' => 'more']);
        $this->assertTrue($r2['success']);
        $this->assertSame(['[TH]Hello'], $r2['translations']);
        $this->assertSame($callsBefore + 1, $calls);
    }
}

