<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorPersistPostContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_posts'] = [];
    }

    public function testPersistPostContentPreservesUnicodeEscapesThroughWpUnslash(): void
    {
        $GLOBALS['__nct_posts'][501] = [
            'post_title' => 'Slash test',
            'post_type' => 'page',
            'post_content' => '',
        ];

        $content = '<!-- wp:nectar-blocks/flex-box {"metadata":{"name":"Name \\u0026 Role"}} /-->';
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($content));

        $result = PostDuplicator::persistPostContent(501, $content);
        $this->assertSame(501, $result);

        $saved = (string) ($GLOBALS['__nct_posts'][501]['post_content'] ?? '');
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($saved));
        $this->assertStringContainsString('\\u0026', $saved);
        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)u0026/', $saved);
    }

    public function testPersistPostContentRepairsAlreadyBrokenEscapes(): void
    {
        $GLOBALS['__nct_posts'][502] = [
            'post_title' => 'Repair test',
            'post_type' => 'project',
            'post_content' => '',
        ];

        $broken = '<!-- wp:nectar-blocks/flex-box {"metadata":{"name":"Name u0026 Role"}} /-->';
        $this->assertTrue(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($broken));

        $result = PostDuplicator::persistPostContent(502, $broken);
        $this->assertSame(502, $result);

        $saved = (string) ($GLOBALS['__nct_posts'][502]['post_content'] ?? '');
        $this->assertFalse(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($saved));
        $this->assertStringContainsString('Name \\u0026 Role', $saved);
    }

    public function testSavingWithoutWpSlashCorruptsUnicodeEscapes(): void
    {
        $GLOBALS['__nct_posts'][503] = [
            'post_title' => 'Bug repro',
            'post_type' => 'page',
            'post_content' => '',
        ];

        $content = '<!-- wp:nectar-blocks/flex-box {"metadata":{"name":"Name \\u0026 Role"}} /-->';
        //bug: pass content without wp_slash — bootstrap mirrors WP unslash and eats the backslash
        wp_update_post([
            'ID' => 503,
            'post_content' => $content,
        ], true);

        $saved = (string) ($GLOBALS['__nct_posts'][503]['post_content'] ?? '');
        $this->assertTrue(BlockContentTranslator::hasLostUnicodeEscapeBackslashes($saved));
        $this->assertStringContainsString('Name u0026 Role', $saved);
    }
}
