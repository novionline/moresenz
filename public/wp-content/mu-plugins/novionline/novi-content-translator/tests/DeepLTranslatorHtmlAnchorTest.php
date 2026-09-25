<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\StringOverrules;
use PHPUnit\Framework\TestCase;

final class DeepLTranslatorHtmlAnchorTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        StringOverrules::setRulesOverride(null);
        StringOverrules::setAutoTitlesOverride(null);
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    public function testHtmlModeRestoresMapsHrefAndHealsEmptiedAnchorText(): void
    {
        $seenHtmlPayloads = [];
        $seenPlainPayloads = [];

        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$seenHtmlPayloads, &$seenPlainPayloads): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                if ($isHtml) {
                    $seenHtmlPayloads[] = $text;
                    //simulate DeepL gutting anchor labels (and never seeing the real Maps href)
                    $out[] = preg_replace('/(<a\b[^>]*>)(.*?)(<\/a>)/is', '$1$3', (string) $text);
                } else {
                    $seenPlainPayloads[] = $text;
                    $out[] = str_replace('Routebeschrijving', 'Directions', (string) $text);
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = '<strong>Werf35</strong><br>Mussenstraat 9<br><a href="https://maps.app.goo.gl/LjgtLZ3ueRN5o4bA7" target="_blank" rel="noreferrer noopener">Routebeschrijving</a>';
        $result = DeepLTranslator::translateTexts([$html], 'nl', 'en', ['context' => 'html']);

        $this->assertTrue($result['success']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertStringContainsString('href="https://maps.app.goo.gl/LjgtLZ3ueRN5o4bA7"', $translated);
        $this->assertStringContainsString('>Directions</a>', $translated);
        $this->assertDoesNotMatchRegularExpression('/maps\\.app\\.goo\\.gl[^"]*"[^>]*>\\s*<\\/a>/', $translated);

        $this->assertNotEmpty($seenHtmlPayloads);
        $this->assertStringNotContainsString('maps.app.goo.gl', implode("\n", $seenHtmlPayloads));
        $this->assertStringContainsString('data-nct-a=', implode("\n", $seenHtmlPayloads));
        $this->assertContains('Routebeschrijving', $seenPlainPayloads);
    }

    public function testHtmlModeKeepsTranslatedAnchorTextWhenDeepLDoesNotEmptyIt(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace('Routebeschrijving', 'Directions', (string) $text);
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = '<a href="https://maps.app.goo.gl/LjgtLZ3ueRN5o4bA7">Routebeschrijving</a>';
        $result = DeepLTranslator::translateTexts([$html], 'nl', 'en', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertSame(
            '<a href="https://maps.app.goo.gl/LjgtLZ3ueRN5o4bA7">Directions</a>',
            $translated
        );
    }

    public function testHtmlModeProtectsAnchorsBeforeStringOverrulesSoHrefNeedlesCannotSplitTags(): void
    {
        StringOverrules::setRulesOverride([
            'en' => [
                [
                    'source' => '2bhonest',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                    'target' => '2bhonest',
                ],
                [
                    'source' => 'environment',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                    'target' => 'environment',
                ],
            ],
        ]);
        StringOverrules::setAutoTitlesOverride([]);

        $seenHtmlPayloads = [];

        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$seenHtmlPayloads): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                if ($isHtml) {
                    $seenHtmlPayloads[] = $text;
                    //simulate DeepL keeping protected anchors intact
                    $out[] = str_replace(
                        ['strategy', 'climate', 'circularity', 'supply chain responsibility'],
                        ['Strategie', 'Klimaschutz', 'Kreislaufwirtschaft', 'Verantwortung in der Lieferkette'],
                        (string) $text
                    );
                } else {
                    $out[] = (string) $text;
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = 'We focus on ESG topics: environment, social. Such as '
            . '<a href="https://2bhonest.test/en/strategy/">strategy</a>, '
            . '<a href="https://2bhonest.test/en/environment/">climate</a>, '
            . '<a href="https://2bhonest.test/en/circularity/">circularity</a>, and '
            . '<a href="https://2bhonest.test/en/due-diligence/">supply chain responsibility</a>.';

        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertTrue($result['success']);
        $payload = implode("\n", $seenHtmlPayloads);
        //href host/path must not be split by overrule spans before DeepL
        $this->assertStringNotContainsString('2bhonest</span>', $payload);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*<span/i', $payload);
        $this->assertStringContainsString('data-nct-a=', $payload);

        //body text "environment" may still be overruled; path /environment/ must stay intact in map restore
        $this->assertStringContainsString('href="https://2bhonest.test/en/strategy/"', $translated);
        $this->assertStringContainsString('href="https://2bhonest.test/en/environment/"', $translated);
        $this->assertStringContainsString('>Strategie</a>', $translated);
        $this->assertStringContainsString('>Klimaschutz</a>', $translated);
        $this->assertStringContainsString('>Kreislaufwirtschaft</a>', $translated);
        $this->assertStringContainsString('>Verantwortung in der Lieferkette</a>', $translated);
        $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*&gt;/i', $translated);
        $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*>\s*,\s*<\/a>/', $translated);
    }

    public function testHtmlModeHealsPunctuationOnlyAnchorLabelsAndTrailingEmDashBleed(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                if ($isHtml) {
                    //simulate DeepL emptying labels to punctuation / &gt; entity in the label
                    $replaced = (string) $text;
                    $replaced = preg_replace(
                        '/(<a\b[^>]*data-nct-a="0"[^>]*>)(.*?)(<\/a>)/is',
                        '$1&gt;, $3',
                        $replaced
                    ) ?? $replaced;
                    $replaced = preg_replace(
                        '/(<a\b[^>]*data-nct-a="1"[^>]*>)(.*?)(<\/a>)/is',
                        '$1 — $3',
                        $replaced
                    ) ?? $replaced;
                    $out[] = $replaced;
                } else {
                    //plain heal of original inners
                    $out[] = str_replace(
                        ['strategy', 'Royal Swinkels Family Brewers — '],
                        ['Strategie', 'Royal Swinkels Family Brewers – '],
                        (string) $text
                    );
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = 'Such as <a href="https://2bhonest.test/en/strategy/">strategy</a> and '
            . '<a href="https://2bhonest.test/en/projects/royal-swinkels/">Royal Swinkels Family Brewers — </a>'
            . 'one of the largest breweries.';

        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('href="https://2bhonest.test/en/strategy/">Strategie</a>', $translated);
        $this->assertStringContainsString(
            'href="https://2bhonest.test/en/projects/royal-swinkels/">Royal Swinkels Family Brewers – </a>',
            $translated
        );
        $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*&gt;/i', $translated);
        $this->assertStringContainsString('one of the largest breweries.', $translated);
    }

    /**
     * Footer Kontakt icon-list: mailto + LinkedIn titles embed <a> whose href contains
     * the brand needle "2bhonest". Regression for DE footer empty labels after EN→DE
     * (envelope/LinkedIn icons with blank text; phone/address still OK).
     */
    public function testHtmlModeKeepsFooterMailtoAndLinkedInLabelsWithBrandInHref(): void
    {
        StringOverrules::setRulesOverride([
            'en' => [
                [
                    'source' => '2bhonest',
                    'also_in_sentence' => true,
                    'ignore_casing' => true,
                    'target' => '2bhonest',
                ],
            ],
        ]);
        StringOverrules::setAutoTitlesOverride([]);

        $seenHtmlPayloads = [];

        DeepLTranslator::setTestTranslator(static function (array $texts, string $sourceLang, string $targetLang, array $options = []) use (&$seenHtmlPayloads): array {
            $isHtml = (($options['tag_handling'] ?? '') === 'html') || (($options['context'] ?? '') === 'html');
            $out = [];
            foreach ($texts as $text) {
                if ($isHtml) {
                    $seenHtmlPayloads[] = $text;
                    //simulate DeepL gutting anchor labels (footer bug symptom)
                    $out[] = preg_replace('/(<a\b[^>]*>)(.*?)(<\/a>)/is', '$1$3', (string) $text);
                } else {
                    //plain heal: keep email + LinkedIn brand as-is
                    $out[] = (string) $text;
                }
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $titles = [
            '<a href="mailto:info@2bhonest.nl">info@2bhonest.nl</a>',
            '<a href="https://www.linkedin.com/company/2bhonest" target="_blank" rel="noreferrer noopener">LinkedIn</a>',
        ];

        $result = DeepLTranslator::translateTexts($titles, 'en', 'de', ['context' => 'html']);
        $this->assertTrue($result['success']);

        $email = (string) ($result['translations'][0] ?? '');
        $linkedin = (string) ($result['translations'][1] ?? '');

        $payload = implode("\n", $seenHtmlPayloads);
        $this->assertStringContainsString('data-nct-a=', $payload);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*<span/i', $payload);
        $this->assertStringNotContainsString('mailto:info@', $payload);

        $this->assertStringContainsString('href="mailto:info@2bhonest.nl"', $email);
        $this->assertStringContainsString('>info@2bhonest.nl</a>', $email);
        $this->assertDoesNotMatchRegularExpression('/mailto:[^"]*"[^>]*>\s*<\/a>/', $email);

        $this->assertStringContainsString('href="https://www.linkedin.com/company/2bhonest"', $linkedin);
        $this->assertStringContainsString('>LinkedIn</a>', $linkedin);
        $this->assertDoesNotMatchRegularExpression('/linkedin\.com\/company\/2bhonest"[^>]*>\s*<\/a>/', $linkedin);
    }

    public function testHtmlModeStripsTextFragmentFromHrefOnRestore(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        });

        $html = 'See <a href="https://example.test/page/#:~:text=Hello%20world">directive</a> details.';
        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertStringContainsString('href="https://example.test/page/"', $translated);
        $this->assertStringNotContainsString('#:~:text=', $translated);
    }

    public function testHtmlModeCollapsesConsecutiveSameHrefAnchors(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                //simulate DeepL splitting one phrase into two same-href anchors
                $out[] = str_replace(
                    '<a data-nct-a="0">supply chain due diligence is considered</a>',
                    '<a data-nct-a="0">supply chain due diligence is</a><a data-nct-a="0">considered</a>',
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = 'Here <a href="https://example.test/due-diligence/">supply chain due diligence is considered</a> carefully.';
        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertSame(
            1,
            preg_match_all('/<a\b[^>]*href="https:\/\/example\.test\/due-diligence\/"/', $translated)
        );
        $this->assertStringContainsString(
            '>supply chain due diligence is considered</a>',
            $translated
        );
    }

    public function testHtmlModeCollapsesSameHrefAnchorsAcrossPlainTextGap(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace(
                    '<a data-nct-a="0">Supply chain responsibility is </a> seen as a formality',
                    '<a data-nct-a="0">Die Verantwortung in der Lieferkette wird </a> als reine Formalität <a href="https://example.test/due-diligence/">betrachtet</a>',
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = 'In conversations <a href="https://example.test/due-diligence/">Supply chain responsibility is </a> seen as a formality.';
        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertSame(
            1,
            preg_match_all('/<a\b[^>]*href="https:\/\/example\.test\/due-diligence\/"/', $translated)
        );
        $this->assertStringContainsString(
            '>Die Verantwortung in der Lieferkette wird  als reine Formalität betrachtet</a>',
            $translated
        );
    }

    public function testHtmlModeInsertsSpaceAfterClosingStrongFollowingAnchor(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = str_replace(
                    '</a></strong> is a',
                    '</a></strong>is a',
                    (string) $text
                );
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $html = '<strong><a href="https://www.avrotros.nl">AVROTROS</a></strong> is a Dutch broadcaster.';
        $result = DeepLTranslator::translateTexts([$html], 'en', 'de', ['context' => 'html']);
        $translated = (string) ($result['translations'][0] ?? '');

        $this->assertStringContainsString('</a></strong> is a', $translated);
        $this->assertStringNotContainsString('</strong>is a', $translated);
    }

    public function testStripTextFragmentsFromHtmlHelper(): void
    {
        $in = 'href="https://x.test/a/#:~:text=Foo%20bar" and more';
        $out = DeepLTranslator::stripTextFragmentsFromHtml($in);
        $this->assertSame('href="https://x.test/a/" and more', $out);
    }
}
