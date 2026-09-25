<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

final class PostDuplicatorTrashSourceTest extends TestCase
{
    public function testDuplicatePostSkipsTrashedSource(): void
    {
        $GLOBALS['__nct_posts'] = [
            501 => [
                'post_type' => 'page',
                'post_status' => 'trash',
                'post_title' => 'Trashed EN',
                'post_name' => 'trashed-en',
                'post_content' => 'Hello',
                'post_parent' => 0,
                'post_author' => 1,
            ],
        ];
        $GLOBALS['__nct_pll_post_language'] = [
            501 => 'en',
        ];

        $result = PostDuplicator::duplicatePost(501, 'de', null);

        $this->assertFalse($result);
        $this->assertStringContainsString('trash', strtolower((string) PostDuplicator::getLastError()));
    }
}
