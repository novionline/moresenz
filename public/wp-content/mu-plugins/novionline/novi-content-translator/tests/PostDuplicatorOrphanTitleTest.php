<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

final class PostDuplicatorOrphanTitleTest extends TestCase
{
    public function testFindOrphanTranslationByTitlePrefersMatchingSlug(): void
    {
        $GLOBALS['__nct_posts'] = [
            10 => [
                'post_type' => 'team',
                'post_status' => 'publish',
                'post_title' => 'Nikki Laarakker',
                'post_name' => 'nikki-laarakker',
                'post_content' => 'NL bio',
            ],
            20 => [
                'post_type' => 'team',
                'post_status' => 'publish',
                'post_title' => 'Nikki Laarakker',
                'post_name' => 'nikki-laarakker',
                'post_content' => 'orphan DE',
            ],
            21 => [
                'post_type' => 'team',
                'post_status' => 'publish',
                'post_title' => 'Nikki Laarakker',
                'post_name' => 'nikki-other',
                'post_content' => 'other orphan',
            ],
        ];
        $GLOBALS['__nct_pll_post_language'] = [
            10 => 'nl',
            20 => 'de',
            21 => 'de',
        ];
        $GLOBALS['__nct_pll_post_translations_groups'] = [];

        $source = get_post(10);
        $this->assertNotNull($source);

        $orphanId = PostDuplicator::findOrphanTranslationByTitle($source, 'de');
        $this->assertSame(20, $orphanId);
    }

    public function testFindOrphanTranslationByTitleSkipsAlreadyLinkedPosts(): void
    {
        $GLOBALS['__nct_posts'] = [
            10 => [
                'post_type' => 'team',
                'post_status' => 'publish',
                'post_title' => 'Nikki Laarakker',
                'post_name' => 'nikki',
                'post_content' => 'NL',
            ],
            20 => [
                'post_type' => 'team',
                'post_status' => 'publish',
                'post_title' => 'Nikki Laarakker',
                'post_name' => 'nikki',
                'post_content' => 'DE linked',
            ],
        ];
        $GLOBALS['__nct_pll_post_language'] = [
            10 => 'nl',
            20 => 'de',
        ];
        $GLOBALS['__nct_pll_post_translations_groups'] = [
            ['en' => 99, 'de' => 20],
        ];

        $source = get_post(10);
        $orphanId = PostDuplicator::findOrphanTranslationByTitle($source, 'de');
        $this->assertSame(0, $orphanId);
    }
}
