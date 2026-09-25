<?php

namespace {
    // Minimal global shims for this test file (not provided by the base bootstrap).
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (!class_exists('\\WP_Post') && defined('ABSPATH')) {
        $path = ABSPATH . 'wp-includes/class-wp-post.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    if (!function_exists('get_page_by_path')) {
        function get_page_by_path(string $path, string $output = OBJECT, $post_type = 'post')
        {
            // Only support the specific slug used in this test.
            if ($path === 'spc-software') {
                $obj = (object) [
                    'ID' => 1146,
                    'post_type' => (string) $post_type,
                    'post_parent' => 0,
                ];
                return class_exists('\\WP_Post') ? new \WP_Post($obj) : null;
            }
            return null;
        }
    }
}

namespace NoviOnline\ContentTranslator\Tests {
    use NoviOnline\ContentTranslator\Core\PostDuplicator;
    use PHPUnit\Framework\TestCase;

    class PostDuplicatorSlugDedupTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['__nct_pll_post_map'] = [];
            $GLOBALS['__nct_pll_post_language'] = [];
        }

        protected function tearDown(): void
        {
            parent::tearDown();
            $ref = new \ReflectionClass(PostDuplicator::class);
            $prop = $ref->getProperty('currentSlugInsertLang');
            $prop->setAccessible(true);
            $prop->setValue(null, '');
        }

        public function testAllowDuplicateSlugAcrossLanguagesKeepsOriginalSlug(): void
        {
            // Set up language lookup: existing post is EN, insert lang is NL.
            $GLOBALS['__nct_pll_post_language'] = [
                1146 => 'en',
            ];

            $ref = new \ReflectionClass(PostDuplicator::class);
            $prop = $ref->getProperty('currentSlugInsertLang');
            $prop->setAccessible(true);
            $prop->setValue(null, 'nl');

            $out = PostDuplicator::allowDuplicateSlugAcrossLanguages(
                'spc-software-2',
                0,
                'publish',
                'novi-product',
                0,
                'spc-software'
            );

            $this->assertSame('spc-software', $out);
        }
    }
}

