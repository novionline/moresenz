<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Regression: DeepL HTML tag_handling invents address commas around <br>
 * (e.g. "Arkerpoort 4<br>3861PS Nijkerk" → "…<br>, …" or "…4,<br>…").
 */
final class DeepLTranslatorBreakCommaTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    public function testStripSpuriousBreakCommasRemovesLeadingCommaAfterBr(): void
    {
        $source = 'Arkerpoort 4<br>3861PS Nijkerk';
        $bad = 'Arkerpoort 4<br>, 3861PS Nijkerk';
        $this->assertSame(
            'Arkerpoort 4<br>3861PS Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasRemovesCommaBeforeBr(): void
    {
        $source = 'Arkerpoort 4<br>3861PS Nijkerk';
        $bad = 'Arkerpoort 4,<br>3861PS Nijkerk';
        $this->assertSame(
            'Arkerpoort 4<br>3861PS Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasHandlesSelfClosingBr(): void
    {
        $source = 'Arkerpoort 4<br />3861PS Nijkerk';
        $bad = 'Arkerpoort 4<br />, 3861PS Nijkerk';
        $this->assertSame(
            'Arkerpoort 4<br />3861PS Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasRemovesCommaBeforeSelfClosingBr(): void
    {
        $source = 'Arkerpoort 4<br/>3861PS Nijkerk';
        $bad = 'Arkerpoort 4,<br/>3861PS Nijkerk';
        $this->assertSame(
            'Arkerpoort 4<br/>3861PS Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasHandlesUppercaseBr(): void
    {
        $source = 'Arkerpoort 4<br>3861PS Nijkerk';
        $bad = 'Arkerpoort 4<BR>, 3861PS Nijkerk';
        $this->assertSame(
            'Arkerpoort 4<BR>3861PS Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasKeepsLegitimateCommasElsewhere(): void
    {
        $source = 'Hello, visitor<br>Welcome to Nijkerk';
        $bad = 'Hello, visitor<br>, Welcome to Nijkerk';
        $this->assertSame(
            'Hello, visitor<br>Welcome to Nijkerk',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasLeavesCleanTranslationUntouched(): void
    {
        $source = 'Arkerpoort 4<br>3861PS Nijkerk';
        $good = 'Arkerpoort 4<br>3861PS Nijkerk';
        $this->assertSame(
            $good,
            DeepLTranslator::stripSpuriousBreakCommas($source, $good)
        );
    }

    public function testStripSpuriousBreakCommasStripsBothSidesOnMultiLineAddress(): void
    {
        $source = 'Arkerpoort 4<br>3861PS Nijkerk<br>The Netherlands';
        $bad = 'Arkerpoort 4,<br>3861PS Nijkerk<br>, The Netherlands';
        $this->assertSame(
            'Arkerpoort 4<br>3861PS Nijkerk<br>The Netherlands',
            DeepLTranslator::stripSpuriousBreakCommas($source, $bad)
        );
    }

    public function testStripSpuriousBreakCommasKeepsWhenSourceHasComma(): void
    {
        $source = 'Line one<br>, line two';
        $translation = 'Line one<br>, line two';
        $this->assertSame(
            $translation,
            DeepLTranslator::stripSpuriousBreakCommas($source, $translation)
        );
    }

    public function testStripSpuriousBreakCommasKeepsWhenSourceHasCommaBeforeBr(): void
    {
        $source = 'Line one,<br>line two';
        $translation = 'Line one,<br>line two';
        $this->assertSame(
            $translation,
            DeepLTranslator::stripSpuriousBreakCommas($source, $translation)
        );
    }

    public function testHtmlTranslatePipelineStripsBreakCommaFromDeepL(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace(
                    [
                        'Arkerpoort 4<br>3861PS Nijkerk',
                        'Arkerpoort 4<br />3861PS Nijkerk',
                    ],
                    [
                        'Arkerpoort 4<br>, 3861PS Nijkerk',
                        'Arkerpoort 4,<br />3861PS Nijkerk',
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

        $html = '<a href="https://maps.app.goo.gl/x" target="_blank" rel="noreferrer noopener">'
            . 'Arkerpoort 4<br>3861PS Nijkerk</a>';
        $result = DeepLTranslator::translateTexts([$html], 'nl', 'en', [
            'context' => 'html',
            'tag_handling' => 'html',
        ]);

        $this->assertTrue($result['success']);
        $translated = (string) ($result['translations'][0] ?? '');
        $this->assertStringContainsString('Arkerpoort 4<br>3861PS Nijkerk', $translated);
        $this->assertStringNotContainsString('<br>,', $translated);
        $this->assertStringNotContainsString(',<br>', $translated);
        $this->assertStringContainsString('href="https://maps.app.goo.gl/x"', $translated);
    }

    public function testHtmlTranslatePipelineStripsCommaBeforeBrVariant(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace(
                    'Arkerpoort 4<br>3861PS Nijkerk',
                    'Arkerpoort 4,<br>3861PS Nijkerk',
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = '<p>Arkerpoort 4<br>3861PS Nijkerk</p>';
        $result = DeepLTranslator::translateTexts([$html], 'nl', 'en', [
            'context' => 'html',
            'tag_handling' => 'html',
        ]);

        $this->assertTrue($result['success']);
        $translated = (string) ($result['translations'][0] ?? '');
        $this->assertSame('<p>Arkerpoort 4<br>3861PS Nijkerk</p>', $translated);
        $this->assertStringNotContainsString('4,<br>', $translated);
    }
}
