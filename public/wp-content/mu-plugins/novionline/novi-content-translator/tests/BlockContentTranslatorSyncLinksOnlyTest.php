<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

class BlockContentTranslatorSyncLinksOnlyTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($GLOBALS['__nct_default_test_translator']) && is_callable($GLOBALS['__nct_default_test_translator'])) {
            DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator']);
        }
    }

    public function testSyncLinksOnlyRewritesLinksByExactMatchWithoutNeedingDeepL(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            770 => 'https://example.test/nl/krijg-een-demo/',
        ];

        $content = <<<EOT
<!-- wp:nectar-blocks/button {"blockId":"block-a","text":"Get a demo","link":{"href":{"value":"https://example.test/get-a-demo/","type":"core"}}} -->
<div class="wp-block-nectar-blocks-button" id="block-a"><a href="https://example.test/get-a-demo/" class="nectar__link"><span class="nectar-blocks-button__text">Get a demo</span></a></div>
<!-- /wp:nectar-blocks/button -->
EOT;

        $synced = BlockContentTranslator::syncLinksOnly($content, 'en', 'nl');

        $this->assertStringContainsString('https://example.test/nl/krijg-een-demo/', $synced);
        $this->assertStringNotContainsString('https://example.test/get-a-demo/', $synced);
    }

    public function testSyncLinksOnlyRepairsClassicHtmlContentWithoutBlocks(): void
    {
        $GLOBALS['__nct_url_to_postid_map'] = [
            'https://example.test/get-a-demo/' => 77,
        ];
        $GLOBALS['__nct_pll_post_map'] = [
            '77|nl' => 770,
        ];
        $GLOBALS['__nct_permalink_map'] = [
            770 => 'https://example.test/nl/krijg-een-demo/',
        ];

        $content = '<p>See <a href="https://example.test/get-a-demo/">demo</a>.</p>';
        $synced = BlockContentTranslator::syncLinksOnly($content, 'en', 'nl');

        $this->assertStringContainsString('https://example.test/nl/krijg-een-demo/', $synced);
        $this->assertStringNotContainsString('https://example.test/get-a-demo/', $synced);
    }

    public function testSyncLinksOnlyDoesNotInventTranslatedSlugsWhenNoDeterministicMatchExists(): void
    {
        $GLOBALS['__nct_deepl_calls'] = 0;
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            $GLOBALS['__nct_deepl_calls'] = (int) ($GLOBALS['__nct_deepl_calls'] ?? 0) + 1;

            // This mimics slug translation fallback for "/cheeses/" -> "/kazen/" in NL.
            $out = [];
            foreach ($texts as $t) {
                $out[] = $t === 'cheeses' ? 'kazen' : $t;
            }

            return [
                'success' => true,
                'translations' => $out,
                'error' => null,
            ];
        });

        // Force no exact-match/dynamic resolution path so any “guessing” would be required.
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_permalink_map'] = [];

        $content = <<<EOT
<!-- wp:nectar-blocks/button {"blockId":"block-b","text":"Cheeses","link":{"href":{"value":"/cheeses/","type":"core"}}} -->
<div class="wp-block-nectar-blocks-button" id="block-b"><a href="/cheeses/" class="nectar__link"><span class="nectar-blocks-button__text">Cheeses</span></a></div>
<!-- /wp:nectar-blocks/button -->
EOT;

        $synced = BlockContentTranslator::syncLinksOnly($content, 'en', 'nl');

        // In strict repair mode, sync-links must NOT invent a translated NL URL.
        $this->assertStringContainsString('href="/cheeses/"', $synced);
        $this->assertStringNotContainsString('/nl/kazen/', $synced);
        $this->assertSame(0, (int) ($GLOBALS['__nct_deepl_calls'] ?? 0));
    }
}

