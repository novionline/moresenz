<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorDashSpacingTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    public function testRestoresSpacesAroundEmDashWhenSourceHadSpacedDash(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_contains((string) $text, '—')
                    ? 'Measure your carbon footprint, develop a reduction plan, and identify climate risks—for your organization and throughout the entire supply chain.'
                    : (string) $text;
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $source = 'Meet je CO₂-voetafdruk, stel een reductieplan op en breng klimaatrisico\'s in kaart — voor de organisatie én door de hele keten.';
        $result = DeepLTranslator::translateTexts([$source], 'nl', 'en');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('risks — for', $result['translations'][0]);
        $this->assertStringNotContainsString('risks—for', $result['translations'][0]);
    }

    public function testSpacesEmDashesDeepLInventedFromPeriodSentences(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            unset($texts);
            return [
                'success' => true,
                'translations' => [
                    'We support the hands-on professionals who choose to embrace change—from international chains to family-owned businesses to industry associations—whether in the food sector, manufacturing, construction, the automotive industry, or the energy sector.',
                ],
                'error' => null,
            ];
        });

        //NL source uses periods; DeepL invents unspaced em dashes
        $source = 'We ondersteunen de praktische doeners die kiezen voor verandering. Van internationale ketens tot familiebedrijven tot brancheverenigingen. Of dat nu in de voedselsector, de maakindustrie, de bouw, automotive of energiesector is.';
        $result = DeepLTranslator::translateTexts([$source], 'nl', 'en');

        $this->assertTrue($result['success']);
        $out = $result['translations'][0];
        $this->assertStringContainsString('change — from', $out);
        $this->assertStringContainsString('associations — whether', $out);
        $this->assertStringNotContainsString('change—from', $out);
        $this->assertStringNotContainsString('associations—whether', $out);
    }

    public function testDoesNotTouchHyphenMinusCompounds(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['This is a well-known hands-on issue.'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Dit is een welbekend hands-on probleem.'], 'nl', 'en');
        $this->assertSame('This is a well-known hands-on issue.', $result['translations'][0]);
    }

    public function testNormalizesPartialSpacingAroundEnDashInHtml(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Identify climate risks–for your <strong>organization</strong>.'],
                'error' => null,
            ];
        });

        $source = 'Breng klimaatrisico’s in kaart – voor de <strong>organisatie</strong>.';
        $result = DeepLTranslator::translateTexts([$source], 'nl', 'en', ['context' => 'html']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('risks – for', $result['translations'][0]);
        $this->assertStringContainsString('<strong>organization</strong>', $result['translations'][0]);
    }
}
