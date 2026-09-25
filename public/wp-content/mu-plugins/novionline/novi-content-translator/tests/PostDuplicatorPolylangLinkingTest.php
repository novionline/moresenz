<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorPolylangLinkingTest extends TestCase
{
    private function callLink(int $sourceId, int $targetId, string $sourceLang, string $targetLang): void
    {
        $rm = new \ReflectionMethod(PostDuplicator::class, 'linkPostTranslationsInPolylang');
        $rm->setAccessible(true);
        $rm->invoke(null, $sourceId, $targetId, $sourceLang, $targetLang);
    }

    public function testLinkPostTranslationsInPolylangMergesWithoutOverwritingExisting(): void
    {
        $GLOBALS['__nct_pll_post_translations_groups'] = [
            ['en' => 309, 'de' => 2036],
        ];

        $this->callLink(309, 4233, 'en', 'nl');

        $map = pll_get_post_translations(309);
        $this->assertSame(309, (int) ($map['en'] ?? 0));
        $this->assertSame(2036, (int) ($map['de'] ?? 0));
        $this->assertSame(4233, (int) ($map['nl'] ?? 0));

        $this->assertSame(4233, pll_get_post(309, 'nl'));
        $this->assertSame(2036, pll_get_post(309, 'de'));
    }
}

