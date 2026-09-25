<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\GravityFormsTranslator;
use PHPUnit\Framework\TestCase;

final class GravityFormsTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GravityFormsTranslator::consumeWarnings();
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
    }

    protected function tearDown(): void
    {
        DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator'] ?? null);
        GravityFormsTranslator::consumeWarnings();
        GravityFormsTranslator::setTestFormTitlesById(null);
        parent::tearDown();
    }

    public function testBuildTargetTitleStripsSourceSuffix(): void
    {
        $this->assertSame(
            'Contact - EN',
            GravityFormsTranslator::buildTargetTitle('Contact - NL', 'nl', 'en')
        );
        $this->assertSame(
            'Download brochure - EN',
            GravityFormsTranslator::buildTargetTitle('Download brochure - NL', 'nl', 'en')
        );
    }

    public function testStripLocaleSuffixLeavesUnrelatedTitles(): void
    {
        $this->assertSame(
            'Contact form',
            GravityFormsTranslator::stripLocaleSuffix('Contact form', 'NL')
        );
    }

    public function testProtectAndRestoreMergeTags(): void
    {
        $subject = 'Nieuwe aanmelding {form_title} met {all_fields}';
        [$protected, $map] = GravityFormsTranslator::protectMergeTags($subject);

        $this->assertStringNotContainsString('{form_title}', $protected);
        $this->assertStringNotContainsString('{all_fields}', $protected);
        $this->assertNotSame($subject, $protected);

        $restored = GravityFormsTranslator::restoreMergeTags($protected, $map);
        $this->assertSame($subject, $restored);
    }

    public function testResolveFixedLabelMapsVerzendenToSubmitNotShipping(): void
    {
        $this->assertSame('Submit', GravityFormsTranslator::resolveFixedLabel('Verzenden', 'nl', 'en'));
        $this->assertSame('Submit', GravityFormsTranslator::resolveFixedLabel('Verstuur', 'nl', 'en'));
        $this->assertSame('Download', GravityFormsTranslator::resolveFixedLabel('Downloaden', 'nl', 'en'));
        $this->assertNull(GravityFormsTranslator::resolveFixedLabel('Voornaam', 'nl', 'en'));
    }

    public function testTranslateFormMetaForcesVerzendenToSubmitEvenWhenDeepLReturnsShipping(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                //DeepL historically mistranslates Verzenden as Shipping — fixed map must win
                $out[] = $text === 'Verzenden' ? 'Shipping' : '[EN] ' . $text;
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $form = [
            'title' => 'Visit showroom - NL',
            'description' => '',
            'button' => ['type' => 'text', 'text' => 'Verzenden'],
            'fields' => [
                ['id' => 1, 'type' => 'text', 'label' => 'Naam'],
            ],
            'confirmations' => [],
            'notifications' => [],
        ];

        $out = GravityFormsTranslator::translateFormMeta($form, 'nl', 'en');

        $this->assertSame('Submit', $out['button']['text']);
        $this->assertStringNotContainsString('Shipping', (string) $out['button']['text']);
        //field label still goes through DeepL (not the fixed submit map)
        $this->assertNotSame('Naam', $out['fields'][0]['label']);
    }

    public function testTranslateFormMetaTranslatesLabelsAndPreservesDistinctChoiceValues(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                //merge-tag tokens must pass through unchanged
                if (preg_match('/^NCTGFMT\d+X$/', $text)) {
                    $out[] = $text;
                    continue;
                }
                $mapped = match (true) {
                    $text === 'Voornaam' => 'First name',
                    $text === 'Via derden' => 'Via third parties',
                    $text === 'Anders' => 'Other',
                    $text === 'Verstuur' => 'Shipping', //fixed map must still force Submit
                    $text === 'Bedankt' => 'Thank you',
                    $text === 'Contactformulier' => 'Contact form',
                    str_starts_with($text, 'Nieuwe inzending ') => 'New submission ' . substr($text, strlen('Nieuwe inzending ')),
                    default => '[EN] ' . $text,
                };
                $out[] = $mapped;
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $form = [
            'title' => 'Contact - NL',
            'description' => '',
            'button' => ['text' => 'Verstuur'],
            'customRequiredIndicator' => '(Vereist)',
            'fields' => [
                [
                    'id' => 1,
                    'type' => 'text',
                    'label' => 'Voornaam',
                    'placeholder' => '',
                    'choices' => '',
                ],
                [
                    'id' => 6,
                    'type' => 'radio',
                    'label' => 'Bron',
                    'choices' => [
                        ['text' => 'Via derden', 'value' => 'Via derden'],
                        ['text' => 'Anders', 'value' => 'other_key'],
                    ],
                ],
            ],
            'confirmations' => [
                'c1' => [
                    'id' => 'c1',
                    'name' => 'Default Confirmation',
                    'type' => 'message',
                    'message' => 'Bedankt',
                    'pageId' => '',
                    'url' => '',
                ],
            ],
            'notifications' => [
                'n1' => [
                    'id' => 'n1',
                    'name' => 'Contactformulier',
                    'subject' => 'Nieuwe inzending {form_title}',
                    'message' => '{all_fields}',
                ],
            ],
        ];

        $out = GravityFormsTranslator::translateFormMeta($form, 'nl', 'en');

        $this->assertSame('First name', $out['fields'][0]['label']);
        $this->assertSame('Submit', $out['button']['text']);
        $this->assertSame('Via third parties', $out['fields'][1]['choices'][0]['text']);
        $this->assertSame('Via third parties', $out['fields'][1]['choices'][0]['value']);
        $this->assertSame('Other', $out['fields'][1]['choices'][1]['text']);
        $this->assertSame('other_key', $out['fields'][1]['choices'][1]['value']);
        $this->assertSame('Thank you', $out['confirmations']['c1']['message']);
        $this->assertSame('Contact form', $out['notifications']['n1']['name']);
        $this->assertSame('New submission {form_title}', $out['notifications']['n1']['subject']);
        $this->assertSame('{all_fields}', $out['notifications']['n1']['message']);
    }

    public function testTranslateFormMetaNormalizesDeepLTitleCaseOnRequiredFieldLabel(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            $out = [];
            foreach ($texts as $text) {
                $out[] = match ((string) $text) {
                    'Voornaam en achternaam*' => 'First Name and Last Name*',
                    default => (string) $text,
                };
            }
            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        $form = [
            'title' => 'Contact - NL',
            'description' => '',
            'button' => ['text' => 'Versturen'],
            'fields' => [
                [
                    'id' => 1,
                    'type' => 'text',
                    'label' => 'Voornaam en achternaam*',
                ],
            ],
            'confirmations' => [],
            'notifications' => [],
        ];

        $out = GravityFormsTranslator::translateFormMeta($form, 'nl', 'en');
        $this->assertSame('First name and last name*', $out['fields'][0]['label']);
    }

    public function testTranslateFormMetaKeepsFileRedirectUrl(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        });

        $pdf = 'https://example.test/wp-content/uploads/2026/02/report.pdf';
        $form = [
            'title' => 'Download - NL',
            'fields' => [],
            'confirmations' => [
                'c1' => [
                    'id' => 'c1',
                    'name' => 'Default Confirmation',
                    'type' => 'redirect',
                    'message' => 'Thanks',
                    'url' => $pdf,
                    'pageId' => '',
                ],
            ],
            'notifications' => [],
        ];

        $out = GravityFormsTranslator::translateFormMeta($form, 'nl', 'en');
        $this->assertSame($pdf, $out['confirmations']['c1']['url']);
        $this->assertSame([], GravityFormsTranslator::consumeWarnings());
    }

    public function testTranslateFormMetaRemapsPageConfirmationWhenTranslationExists(): void
    {
        DeepLTranslator::setTestTranslator(static function (array $texts): array {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        });

        $GLOBALS['__nct_pll_post_map'] = [
            '4819|en' => 9001,
        ];

        if (!function_exists('pll_get_post')) {
            $this->markTestSkipped('pll_get_post shim missing');
        }

        $form = [
            'title' => 'Brochure - NL',
            'fields' => [],
            'confirmations' => [
                'c1' => [
                    'id' => 'c1',
                    'name' => 'Default Confirmation',
                    'type' => 'page',
                    'message' => 'Thanks',
                    'pageId' => 4819,
                    'page' => 4819,
                    'url' => '',
                ],
            ],
            'notifications' => [],
        ];

        $out = GravityFormsTranslator::translateFormMeta($form, 'nl', 'en');
        $this->assertSame(9001, (int) $out['confirmations']['c1']['pageId']);
    }

    public function testResolveTargetFormIdByTitleSuffix(): void
    {
        GravityFormsTranslator::setTestFormTitlesById([
            1 => 'Contact - NL',
            14 => 'Contact - EN',
        ]);

        $this->assertSame(14, GravityFormsTranslator::resolveTargetFormId(1, 'nl', 'en'));
        $this->assertSame(0, GravityFormsTranslator::resolveTargetFormId(1, 'nl', 'de'));
        $this->assertSame(0, GravityFormsTranslator::resolveTargetFormId(99, 'nl', 'en'));
    }

    public function testBuildFormIdMapperBySuffixCachesAndTracksStats(): void
    {
        GravityFormsTranslator::setTestFormTitlesById([
            1 => 'Contact - NL',
            14 => 'Contact - EN',
            5 => 'Download brochure - NL',
        ]);

        $stats = ['mapped' => 0, 'unmapped' => 0];
        $mapFn = GravityFormsTranslator::buildFormIdMapperBySuffix('nl', 'en', $stats);

        $this->assertSame(14, $mapFn(1));
        $this->assertSame(14, $mapFn(1)); //cached; stats should not double-count
        $this->assertSame(0, $mapFn(5)); //no EN form
        $this->assertSame(1, (int) $stats['mapped']);
        $this->assertSame(1, (int) $stats['unmapped']);
    }

    public function testReplaceGravityFormsBlockFormIdsUpdatesStringAttr(): void
    {
        $content = '<!-- wp:gravityforms/form {"formId":"1","title":false,"ajax":true} /-->';
        $mapFn = static function (int $id): int {
            return $id === 1 ? 14 : 0;
        };

        $out = GravityFormsTranslator::replaceGravityFormsBlockFormIds($content, $mapFn);
        $this->assertSame(1, (int) $out['updated_blocks']);
        $this->assertStringContainsString('"formId":"14"', (string) $out['content']);
        $this->assertStringNotContainsString('"formId":"1"', (string) $out['content']);
    }

    public function testReplaceGravityFormsBlockFormIdsLeavesUnmapped(): void
    {
        $content = '<!-- wp:gravityforms/form {"formId":"1","title":false} /-->';
        $mapFn = static function (int $id): int {
            return 0;
        };

        $out = GravityFormsTranslator::replaceGravityFormsBlockFormIds($content, $mapFn);
        $this->assertSame(0, (int) $out['updated_blocks']);
        $this->assertStringContainsString('"formId":"1"', (string) $out['content']);
    }
}
