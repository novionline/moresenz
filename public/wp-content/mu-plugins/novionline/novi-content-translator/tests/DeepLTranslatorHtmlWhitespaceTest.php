<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorHtmlWhitespaceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    public function testHtmlModeConvertsNbspAroundInlineTagsToAsciiSpacesBeforeDeepL(): void
    {
        $seenPayloads = [];

        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$seenPayloads): array {
            unset($sourceLang, $targetLang, $options);
            $out = [];
            foreach ($texts as $text) {
                $seenPayloads[] = (string) $text;
                //simulate DeepL: keep ASCII spaces next to tags; would have dropped NBSP
                $out[] = str_replace(
                    [
                        'om <strong>het hoe</strong>',
                        'strategische <em>ESG</em>',
                        '</a>, <a',
                        'bekijk <a',
                        '</a> nu',
                    ],
                    [
                        'about <strong>the “how”</strong>',
                        'strategic <em>ESG</em>',
                        '</a>, <a',
                        'view <a',
                        '</a> now',
                    ],
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $nbsp = "\u{00A0}";
        $html = 'Want uiteindelijk draait het allemaal om' . $nbsp . '<strong>het hoe</strong>. '
            . 'Onze strategische' . $nbsp . '<em>ESG</em> aanpak. '
            . 'Bekijk' . $nbsp . '<a href="https://example.test/a">diensten</a>,' . $nbsp
            . '<a href="https://example.test/b">experts</a>' . $nbsp . 'nu.';

        $result = DeepLTranslator::translateTexts([$html], 'nl', 'en', ['context' => 'html']);
        $this->assertTrue($result['success']);

        $this->assertNotEmpty($seenPayloads);
        $payload = (string) $seenPayloads[0];
        $this->assertStringNotContainsString($nbsp, $payload);
        $this->assertStringContainsString('om <strong>het hoe</strong>', $payload);
        $this->assertStringContainsString('strategische <em>ESG</em>', $payload);
        $this->assertStringContainsString('</a>, <a', $payload);
        $this->assertStringContainsString('Bekijk <a', $payload);
        $this->assertStringContainsString('</a> nu', $payload);

        $translated = (string) ($result['translations'][0] ?? '');
        $this->assertStringContainsString('about <strong>the “how”</strong>', $translated);
        $this->assertStringContainsString('strategic <em>ESG</em>', $translated);
        $this->assertStringContainsString('</a>, <a', $translated);
        $this->assertDoesNotMatchRegularExpression('/[a-zA-Z]<(?:strong|em|a)\b/', $translated);
        $this->assertDoesNotMatchRegularExpression('/<\/(?:strong|em|a)>[a-zA-Z]/', $translated);
    }

    public function testHtmlModeAboutTheHowHomepagePatternKeepsSpaceBeforeStrong(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace(
                    'om <strong>het hoe</strong>',
                    'about <strong>the “how”</strong>',
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $src = 'Want uiteindelijk draait het allemaal om' . "\u{00A0}" . '<strong>het hoe</strong>.';
        $result = DeepLTranslator::translateTexts([$src], 'nl', 'en', [
            'context' => 'html',
            'tag_handling' => 'html',
        ]);

        $translated = (string) ($result['translations'][0] ?? '');
        $this->assertSame(
            'Want uiteindelijk draait het allemaal about <strong>the “how”</strong>.',
            $translated
        );
        $this->assertStringNotContainsString('about<strong>', $translated);
    }

    public function testPlainContextDoesNotRewriteNbsp(): void
    {
        $seenPayloads = [];

        DeepLTranslator::setTestTranslator(static function (array $texts) use (&$seenPayloads): array {
            foreach ($texts as $text) {
                $seenPayloads[] = (string) $text;
            }
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        });

        $nbsp = "\u{00A0}";
        $src = 'Het weegt 10' . $nbsp . 'kg.';
        DeepLTranslator::translateTexts([$src], 'nl', 'en', ['context' => 'plain']);

        $this->assertNotEmpty($seenPayloads);
        $this->assertStringContainsString($nbsp, (string) $seenPayloads[0]);
    }
}
