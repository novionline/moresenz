<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\MenuBlockIdReplacer;
use PHPUnit\Framework\TestCase;

final class MenuBlockIdReplacerTest extends TestCase
{
    public function testReplacesMenuIdInSingleNoviMenuBlock(): void
    {
        $content = (string) file_get_contents(__DIR__ . '/fixtures/acf-novi-menu-heading.txt');
        $stats = ['mapped' => 0, 'unmapped' => 0];
        $mapFn = static function (int $sourceMenuId): int {
            return $sourceMenuId === 123 ? 999 : 0;
        };

        $out = MenuBlockIdReplacer::replaceNoviMenuBlockMenuIds($content, $mapFn);
        $this->assertSame(1, (int) $out['updated_blocks']);
        $this->assertStringContainsString('"menu":"999"', (string) $out['content']);
        $this->assertStringNotContainsString('"menu":"123"', (string) $out['content']);
    }

    public function testReplacesMenuIdInNestedBlocks(): void
    {
        $content = (string) file_get_contents(__DIR__ . '/fixtures/acf-novi-menu-nested.txt');
        $mapFn = static function (int $sourceMenuId): int {
            return $sourceMenuId === 123 ? 456 : 0;
        };

        $out = MenuBlockIdReplacer::replaceNoviMenuBlockMenuIds($content, $mapFn);
        $this->assertSame(1, (int) $out['updated_blocks']);
        $this->assertStringContainsString('"menu":"456"', (string) $out['content']);
    }

    public function testMapperBySuffixSwapsLocaleAndLooksUpMenu(): void
    {
        $GLOBALS['__nct_menus'] = [
            18 => ['name' => 'Main navigation - EN'],
            284 => ['name' => 'Main navigation - NL'],
        ];
        $GLOBALS['__nct_terms'] = [
            18 => ['taxonomy' => 'nav_menu', 'name' => 'Main navigation - EN', 'slug' => 'main-navigation-en'],
            284 => ['taxonomy' => 'nav_menu', 'name' => 'Main navigation - NL', 'slug' => 'main-navigation-nl'],
        ];

        if (!function_exists('get_term')) {
            $this->markTestSkipped('get_term shim not available');
        }

        // Provide get_term shim for nav_menu if not already present.
        // (Some test runs may include core shims; keep this defensive.)
        $stats = ['mapped' => 0, 'unmapped' => 0];
        $mapFn = MenuBlockIdReplacer::buildMenuIdMapperBySuffix('en', 'nl', $stats);

        $this->assertSame(284, $mapFn(18));
        $this->assertSame(1, (int) $stats['mapped']);
    }
}

