<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use PHPUnit\Framework\TestCase;

class BlockTranslationConfigFilterTest extends TestCase
{
    private function loadFixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name;
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Fixture not found: ' . $path);
        return $contents;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_filters'] = [];
    }

    public function testBlockConfigsAreFilterableFromTheme(): void
    {
        add_filter('nct_block_translation_configs', function (array $configs) {
            $configs['dummy/block'] = [
                'strategy' => 'html',
            ];
            return $configs;
        });

        $content = $this->loadFixture('dummy-block-html.txt');
        $translated = BlockContentTranslator::translatePostContent($content, 'en', 'nl');

        $this->assertTrue(has_blocks($translated));
        $this->assertStringContainsString('Welkom bij Datalyzer', $translated);
    }
}

