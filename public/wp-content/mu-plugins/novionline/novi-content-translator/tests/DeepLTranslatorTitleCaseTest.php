<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorTitleCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    public function testNormalizesDeepLTitleCaseWhenSourceIsSentenceCase(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = match ((string) $text) {
                    'Samen verder komen' => 'Moving Forward Together',
                    'Bezoek de USA vandaag' => 'Visit The USA Today',
                    default => (string) $text,
                };
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Samen verder komen', 'Bezoek de USA vandaag'],
            'nl',
            'en'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Moving forward together', $result['translations'][0]);
        $this->assertSame('Visit the USA today', $result['translations'][1]);
    }

    public function testKeepsTitleCaseWhenSourceWasAlreadyTitleCase(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Moving Forward Together'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Samen Verder Komen'], 'nl', 'en');
        $this->assertSame('Moving Forward Together', $result['translations'][0]);
    }

    public function testKeepsAllCapsTranslation(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['MOVING FORWARD TOGETHER'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Samen verder komen'], 'nl', 'en');
        $this->assertSame('MOVING FORWARD TOGETHER', $result['translations'][0]);
    }

    public function testDoesNotRewriteHtmlPayloads(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['<p>Moving Forward Together</p>'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['<p>Samen verder komen</p>'],
            'nl',
            'en',
            ['context' => 'html']
        );
        $this->assertSame('<p>Moving Forward Together</p>', $result['translations'][0]);
    }

    public function testDoesNotRewriteAlreadySentenceCaseTranslations(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Moving forward together'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Samen verder komen'], 'nl', 'en');
        $this->assertSame('Moving forward together', $result['translations'][0]);
    }

    public function testNormalizesMixedMidPhraseTitleCaseFromLowercaseSourceWords(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = match ((string) $text) {
                    'Partners in positieve impact' => 'Partners in Positive Impact',
                    default => (string) $text,
                };
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Partners in positieve impact'], 'nl', 'en');

        $this->assertTrue($result['success']);
        $this->assertSame('Partners in positive impact', $result['translations'][0]);
        $this->assertStringNotContainsString('Positive Impact', $result['translations'][0]);
    }

    public function testPreservesCapitalizedSourceWordsAndAllCapsTokensWhenAligning(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Partners With KIEM Impact'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Partners met KIEM impact'], 'nl', 'en');
        $this->assertSame('Partners with KIEM impact', $result['translations'][0]);
    }

    public function testNormalizesTitleCaseWhenSingleWordNlCompoundExpands(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = match ((string) $text) {
                    'Succesverhalen' => 'Success Stories',
                    'Strategieontwikkeling' => 'Strategy Development',
                    'Ketenimpact' => 'Supply Chain Impact',
                    'Vacatures' => 'Job Openings',
                    'CO₂-reductieplan' => 'CO₂ Reduction Plan',
                    default => (string) $text,
                };
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Succesverhalen', 'Strategieontwikkeling', 'Ketenimpact', 'Vacatures', 'CO₂-reductieplan'],
            'nl',
            'en'
        );

        $this->assertSame('Success stories', $result['translations'][0]);
        $this->assertSame('Strategy development', $result['translations'][1]);
        $this->assertSame('Supply chain impact', $result['translations'][2]);
        $this->assertSame('Job openings', $result['translations'][3]);
        $this->assertSame('CO₂ reduction plan', $result['translations'][4]);
    }

    public function testKeepsSingleWordTranslationUnchanged(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Strategy'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(['Strategie'], 'nl', 'en');
        $this->assertSame('Strategy', $result['translations'][0]);
    }

    public function testNormalizesTitleCaseWhenNlExpandsAndEnglishLeavesConnectorsLowercase(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = match ((string) $text) {
                    'Voornaam en achternaam*' => 'First Name and Last Name*',
                    'Voornaam en achternaam' => 'First Name and Last Name',
                    default => (string) $text,
                };
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Voornaam en achternaam*', 'Voornaam en achternaam'],
            'nl',
            'en'
        );

        $this->assertSame('First name and last name*', $result['translations'][0]);
        $this->assertSame('First name and last name', $result['translations'][1]);
    }

    public function testNormalizesGettingStartedTitleCaseWhenNlIsSentenceCase(): void
    {
        //NL 7 words → EN 6 words; "with" stays lowercase so strict isTitleCasePhrase misses it
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => ['Getting Started with Your Own Team'],
                'error' => null,
            ];
        });

        $result = DeepLTranslator::translateTexts(
            ['Aan de slag met een eigen team'],
            'nl',
            'en'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Getting started with your own team', $result['translations'][0]);
    }
}
