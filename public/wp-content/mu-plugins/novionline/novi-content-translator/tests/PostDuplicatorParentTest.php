<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorParentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_pll_post_map'] = [];
    }

    private function makeWpPost(array $props): \WP_Post
    {
        if (!class_exists('\WP_Post') && defined('ABSPATH')) {
            $path = ABSPATH . 'wp-includes/class-wp-post.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }

        $obj = (object) array_merge([
            'ID' => 1,
            'post_type' => 'page',
            'post_parent' => 0,
        ], $props);

        return new \WP_Post($obj);
    }

    private function callResolveTargetParentId(\WP_Post $post, string $targetLang): int
    {
        $rm = new \ReflectionMethod(PostDuplicator::class, 'resolveTargetParentId');
        $rm->setAccessible(true);
        return (int) $rm->invoke(null, $post, $targetLang);
    }

    public function testReturnsTranslatedParentIdWhenParentTranslationExists(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '10|nl' => 110,
        ];

        $child = $this->makeWpPost([
            'ID' => 20,
            'post_type' => 'page',
            'post_parent' => 10,
        ]);

        $resolved = $this->callResolveTargetParentId($child, 'nl');

        $this->assertSame(110, $resolved);
    }

    public function testReturnsZeroWhenParentTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            // no 10|nl mapping
        ];

        $child = $this->makeWpPost([
            'ID' => 20,
            'post_type' => 'page',
            'post_parent' => 10,
        ]);

        $resolved = $this->callResolveTargetParentId($child, 'nl');

        $this->assertSame(0, $resolved);
    }

    public function testReturnsZeroForNonHierarchicalPostTypes(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '10|nl' => 110,
        ];

        $child = $this->makeWpPost([
            'ID' => 20,
            'post_type' => 'post',
            'post_parent' => 10,
        ]);

        $resolved = $this->callResolveTargetParentId($child, 'nl');

        $this->assertSame(0, $resolved);
    }

    public function testSyncHierarchicalParentUpdatesWrongParent(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '10|en' => 110,
        ];
        $GLOBALS['__nct_posts'] = [
            20 => [
                'post_type' => 'page',
                'post_parent' => 10,
                'post_title' => 'Contact NL',
            ],
            200 => [
                'post_type' => 'page',
                'post_parent' => 0,
                'post_title' => 'Contact EN',
            ],
        ];

        $status = PostDuplicator::syncHierarchicalParent(20, 200, 'en');

        $this->assertSame('updated', $status);
        $this->assertSame(110, (int) ($GLOBALS['__nct_posts'][200]['post_parent'] ?? -1));
    }

    public function testSyncHierarchicalParentUnchangedWhenAlreadyCorrect(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '10|en' => 110,
        ];
        $GLOBALS['__nct_posts'] = [
            20 => [
                'post_type' => 'page',
                'post_parent' => 10,
                'post_title' => 'Contact NL',
            ],
            200 => [
                'post_type' => 'page',
                'post_parent' => 110,
                'post_title' => 'Contact EN',
            ],
        ];

        $status = PostDuplicator::syncHierarchicalParent(20, 200, 'en');

        $this->assertSame('unchanged', $status);
        $this->assertSame(110, (int) ($GLOBALS['__nct_posts'][200]['post_parent'] ?? -1));
    }
}

