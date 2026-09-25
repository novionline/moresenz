<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;
use NoviOnline\ContentTranslator\Core\MenuTranslator;
use PHPUnit\Framework\TestCase;

final class MenuTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DeepLTranslator::setTestTranslator(null);
        InternalLinkTranslator::resetRuntimeCache();

        $GLOBALS['__nct_posts'] = [];
        $GLOBALS['__nct_permalink_map'] = [];
        $GLOBALS['__nct_terms'] = [];
        $GLOBALS['__nct_term_link_map'] = [];
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_pll_term_map'] = [];

        $GLOBALS['__nct_menus'] = [];
        $GLOBALS['__nct_menu_items'] = [];
        $GLOBALS['__nct_next_menu_id'] = 3000;
        $GLOBALS['__nct_next_menu_item_id'] = 4000;
        $GLOBALS['__nct_theme_mods'] = [];
        $GLOBALS['__nct_options'] = [];
        $GLOBALS['__nct_stylesheet'] = 'test-theme';
        $GLOBALS['__nct_pll_term_language'] = [];
        $GLOBALS['__nct_pll_term_translations'] = [];
        $GLOBALS['__nct_pll_term_translations_groups'] = [];
    }

    public function testTranslatesMenuAndCopiesStructureAndObjects(): void
    {
        // Source menu: Main - EN
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'post_type',
                'object' => 'page',
                'object_id' => 1,
                'url' => 'https://example.test/about-us/',
                'title' => 'About us',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => ['top'],
            ],
            102 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => 'https://example.test/about-us/faq/',
                'title' => 'FAQ',
                'menu_order' => 2,
                'parent' => 101,
                'classes' => ['child'],
            ],
            103 => [
                'type' => 'post_type_archive',
                'object' => 'vacancy',
                'object_id' => -1,
                'url' => 'https://example.test/vacancies/',
                'title' => 'Jobs',
                'menu_order' => 3,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        // Source post title matches menu label, so should use translated post title.
        $GLOBALS['__nct_posts'][1] = ['post_type' => 'page', 'post_title' => 'About us'];
        $GLOBALS['__nct_posts'][2] = ['post_type' => 'page', 'post_title' => 'Over ons'];
        $GLOBALS['__nct_pll_post_map']['1|nl'] = 2;
        $GLOBALS['__nct_permalink_map'][2] = 'https://example.test/nl/over-ons/';

        // InternalLinkTranslator exact mapping for FAQ.
        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        $GLOBALS['__nct_pll_translated_slugs'] = [
            [
                'slug' => 'vacancies',
                'translations' => [
                    'en' => 'about-us/faq',
                    'nl' => 'over-ons/vacatures',
                ],
            ],
            // Archive base translation for post_type_archive vacancy (real stack uses archive_{post_type})
            'archive_vacancy' => [
                'slug' => 'vacancies',
                'translations' => [
                    'nl' => 'vacatures',
                ],
            ],
        ];

        // DeepL for custom label translations.
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[NL]' . $t, $texts),
                'error' => null,
            ];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $this->assertIsInt($nlMenuId);
        $this->assertGreaterThan(0, $nlMenuId);

        $menus = wp_get_nav_menus();
        $names = array_map(static fn ($m) => (string) $m->name, $menus);
        $this->assertContains('Main - NL', $names);

        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(3, $items);

        // Find translated items by order.
        usort($items, static fn ($a, $b) => ((int) $a->menu_order) <=> ((int) $b->menu_order));
        $top = $items[0];
        $child = $items[1];
        $archive = $items[2];

        $this->assertSame('post_type', (string) $top->type);
        $this->assertSame(2, (int) $top->object_id);
        $this->assertSame('Over ons', (string) $top->title);

        $this->assertSame((int) $top->ID, (int) $child->menu_item_parent);
        $this->assertSame('custom', (string) $child->type);
        $this->assertSame('https://example.test/nl/over-ons/vacatures/', (string) $child->url);
        $this->assertSame('[NL]FAQ', (string) $child->title);

        // Archive items with a resolved target URL are stored as custom so WP keeps the URL
        // (core blanks _menu_item_url for post_type_archive).
        $this->assertSame('custom', (string) $archive->type);
        $this->assertSame('https://example.test/nl/vacatures/', (string) $archive->url);
    }

    public function testEnToDeArchiveItemsBecomeCustomWithTargetUrls(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'post_type_archive',
                'object' => 'article',
                'object_id' => -1,
                'url' => 'https://example.test/articles/',
                'title' => 'Articles',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
            102 => [
                'type' => 'post_type_archive',
                'object' => 'project',
                'object_id' => -1,
                'url' => 'https://example.test/projects/',
                'title' => 'Projects',
                'menu_order' => 2,
                'parent' => 0,
                'classes' => [],
            ],
            103 => [
                'type' => 'post_type_archive',
                'object' => 'vacancy',
                'object_id' => -1,
                'url' => 'https://example.test/about-us/vacancies/',
                'title' => 'Vacancies',
                'menu_order' => 3,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'archive_article' => [
                'slug' => 'articles',
                'translations' => [
                    'de' => 'artikel',
                ],
            ],
            'archive_project' => [
                'slug' => 'projects',
                'translations' => [
                    'de' => 'projekte',
                ],
            ],
            'archive_vacancy' => [
                'slug' => 'about-us/vacancies',
                'translations' => [
                    'de' => 'uber-uns/stellenangebote',
                ],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[DE]' . $t, $texts),
                'error' => null,
            ];
        });

        $deMenuId = MenuTranslator::translateMenu(10, 'en', 'de', ['force' => true, 'strict_links' => true]);
        $this->assertIsInt($deMenuId);
        $items = wp_get_nav_menu_items($deMenuId);
        $this->assertCount(3, $items);
        usort($items, static fn ($a, $b) => ((int) $a->menu_order) <=> ((int) $b->menu_order));

        $this->assertSame('custom', (string) $items[0]->type);
        $this->assertSame('https://example.test/de/artikel/', (string) $items[0]->url);

        $this->assertSame('custom', (string) $items[1]->type);
        $this->assertSame('https://example.test/de/projekte/', (string) $items[1]->url);

        $this->assertSame('custom', (string) $items[2]->type);
        $this->assertSame('https://example.test/de/uber-uns/stellenangebote/', (string) $items[2]->url);
    }

    public function testCreatesNotFoundItemWhenCustomLinkCannotBeTranslatedStrictly(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => 'https://example.test/nope/',
                'title' => 'Missing',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $this->assertIsInt($nlMenuId);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(1, $items);
        $this->assertSame('#', (string) $items[0]->url);
        $this->assertSame('Not found- Missing', (string) $items[0]->title);
    }

    public function testKeepsPolylangLanguageSwitcherMenuItemIntact(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#pll_switcher',
                'title' => 'Languages',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];
        $GLOBALS['__nct_post_meta'] = [
            101 => [
                '_pll_menu_item' => [[
                    'hide_if_no_translation' => 0,
                    'hide_current' => 0,
                    'force_home' => 0,
                    'show_flags' => 1,
                    'show_names' => 1,
                    'dropdown' => 1,
                ]],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $this->assertIsInt($nlMenuId);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(1, $items);
        $this->assertSame('#pll_switcher', (string) $items[0]->url);

        // Ensure meta was copied to new item.
        $newId = (int) $items[0]->ID;
        $meta = get_post_meta($newId, '_pll_menu_item', true);
        $this->assertIsArray($meta);
        $this->assertSame(1, (int) ($meta['dropdown'] ?? 0));
    }

    public function testCopiesSitemapXmlCustomLinkVerbatimAndNormalizesDoubleSlash(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => 'https://example.test//sitemap_index.xml',
                'title' => 'Sitemap',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $this->assertIsInt($nlMenuId);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(1, $items);
        $this->assertSame('https://example.test/sitemap_index.xml', (string) $items[0]->url);
        $this->assertSame('Sitemap', (string) $items[0]->title);
    }

    public function testSyncsMenuLocationsFromSourceToTargetLanguageKey(): void
    {
        // Source menu assigned to a Polylang-suffixed location key.
        $GLOBALS['__nct_theme_mods']['nav_menu_locations'] = [
            'top_nav___en' => 10,
            'top_nav___nl' => 0,
        ];

        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true]);
        $this->assertIsInt($nlMenuId);

        $loc = get_theme_mod('nav_menu_locations');
        $this->assertIsArray($loc);
        $this->assertSame((int) $nlMenuId, (int) ($loc['top_nav___nl'] ?? 0));
    }

    public function testSyncsMenuLocationsFromUnsuffixedKeyWhenTargetKeyExists(): void
    {
        // Source menu assigned to the unsuffixed location; target language key exists.
        $GLOBALS['__nct_theme_mods']['nav_menu_locations'] = [
            'top_nav' => 10,
            'top_nav___nl' => 0,
        ];

        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true]);
        $this->assertIsInt($nlMenuId);

        $loc = get_theme_mod('nav_menu_locations');
        $this->assertIsArray($loc);
        $this->assertSame((int) $nlMenuId, (int) ($loc['top_nav___nl'] ?? 0));
    }

    public function testSyncsMenuLocationsFromUnsuffixedKeyCreatesTargetKeyAndPolylangOption(): void
    {
        $GLOBALS['__nct_theme_mods']['nav_menu_locations'] = [
            'top_nav' => 10,
            'top_nav_pull_right' => 11,
        ];
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [];
        $GLOBALS['__nct_menus'][11] = ['name' => 'Right - EN'];
        $GLOBALS['__nct_menu_items'][11] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true]);
        $this->assertIsInt($nlMenuId);

        $loc = get_theme_mod('nav_menu_locations');
        $this->assertSame((int) $nlMenuId, (int) ($loc['top_nav___nl'] ?? 0));

        $opts = get_option('polylang');
        $this->assertSame(10, (int) ($opts['nav_menus']['test-theme']['top_nav']['en'] ?? 0));
        $this->assertSame((int) $nlMenuId, (int) ($opts['nav_menus']['test-theme']['top_nav']['nl'] ?? 0));
    }

    public function testSyncsPolylangNavMenusOptionEvenWithoutThemeModMatch(): void
    {
        $GLOBALS['__nct_theme_mods']['nav_menu_locations'] = [];
        $GLOBALS['__nct_options']['polylang'] = [
            'nav_menus' => [
                'test-theme' => [
                    'top_nav' => [
                        'en' => 10,
                    ],
                ],
            ],
        ];
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [];
        // pretends NL menu already exists so only_missing path still syncs locations
        $GLOBALS['__nct_menus'][55] = ['name' => 'Main - NL'];
        $GLOBALS['__nct_menu_items'][55] = [];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['only_missing' => true]);
        $this->assertSame(55, (int) $nlMenuId);

        $opts = get_option('polylang');
        $this->assertSame(55, (int) ($opts['nav_menus']['test-theme']['top_nav']['nl'] ?? 0));
        $this->assertSame('nl', (string) pll_get_term_language(55, 'slug'));
        $this->assertSame('en', (string) pll_get_term_language(10, 'slug'));
    }

    public function testMissingPostTranslationBecomesNotFoundAndKeepsChildHierarchy(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'post_type_archive',
                'object' => 'solution',
                'object_id' => -1,
                'url' => 'https://example.test/solutions/',
                'title' => 'Solutions',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
            102 => [
                'type' => 'post_type',
                'object' => 'solution',
                'object_id' => 999, // no pll mapping -> missing
                'url' => 'https://example.test/solutions/child/',
                'title' => 'Child',
                'menu_order' => 2,
                'parent' => 101,
                'classes' => [],
            ],
            103 => [
                'type' => 'post_type',
                'object' => 'solution',
                'object_id' => 1000, // no pll mapping -> missing
                'url' => 'https://example.test/solutions/grandchild/',
                'title' => 'Grand',
                'menu_order' => 3,
                'parent' => 102,
                'classes' => [],
            ],
        ];

        // Provide archive mapping so parent stays a real archive item (not Not found).
        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        $GLOBALS['__nct_pll_translated_slugs'] = [
            'archive_solution' => [
                'slug' => 'solutions',
                'translations' => [
                    'nl' => 'oplossingen',
                ],
            ],
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(3, $items);

        // Build lookup by menu_order.
        usort($items, static fn ($a, $b) => ((int) $a->menu_order) <=> ((int) $b->menu_order));
        $parent = $items[0];
        $child = $items[1];
        $grand = $items[2];

        $this->assertSame('custom', (string) $parent->type);
        $this->assertSame('https://example.test/nl/oplossingen/', (string) $parent->url);
        $this->assertSame((int) $parent->ID, (int) $child->menu_item_parent);
        $this->assertSame((int) $child->ID, (int) $grand->menu_item_parent);

        $this->assertSame('custom', (string) $child->type);
        $this->assertSame('#', (string) $child->url);
        $this->assertStringContainsString('Not found-', (string) $child->title);

        $this->assertSame('custom', (string) $grand->type);
        $this->assertSame('#', (string) $grand->url);
        $this->assertStringContainsString('Not found-', (string) $grand->title);
    }

    public function testCopiesNectarMenuOptionsAndRemapsGlobalSectionIds(): void
    {
        // Menus.
        $GLOBALS['__nct_menus'][10] = ['name' => 'Main - EN'];

        // Source menu items include nectar_menu_options meta and legacy button style.
        $GLOBALS['__nct_menu_items'][10] = [
            101 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => 'https://example.test/',
                'title' => 'Home',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        // Post meta shims store per-post meta.
        $GLOBALS['__nct_post_meta'][101]['nectar_menu_options'] = [[
            'enable_mega_menu' => 'on',
            'mega_menu_global_section' => '500',
            'mega_menu_global_section_mobile' => '501',
            'menu_item_link_button_color' => '#ff00ff',
        ]];
        $GLOBALS['__nct_post_meta'][101]['menu-item-nectar-button-style'] = ['button_solid_color'];

        // Polylang post mapping for nectar_sections IDs.
        $GLOBALS['__nct_pll_post_map'] = [
            '500|nl' => 1500,
            '501|nl' => 1501,
        ];

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return ['success' => true, 'translations' => $texts, 'error' => null];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(1, $items);
        $newItemId = (int) $items[0]->ID;

        $copied = get_post_meta($newItemId, 'nectar_menu_options', true);
        $this->assertIsArray($copied);
        $this->assertSame('on', (string) ($copied['enable_mega_menu'] ?? ''));
        $this->assertSame('1500', (string) ($copied['mega_menu_global_section'] ?? ''));
        $this->assertSame('1501', (string) ($copied['mega_menu_global_section_mobile'] ?? ''));
        $this->assertSame('#ff00ff', (string) ($copied['menu_item_link_button_color'] ?? ''));

        $legacy = (string) get_post_meta($newItemId, 'menu-item-nectar-button-style', true);
        $this->assertSame('button_solid_color', $legacy);
    }

    public function testCopiesAcfOffCanvasNavItemStyleMeta(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - EN'];
        $GLOBALS['__nct_menu_items'][10] = [
            201 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => 'https://example.test/#about',
                'title' => 'About',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
            202 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Visit showroom',
                'menu_order' => 2,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        $GLOBALS['__nct_post_meta'][201]['off_canvas_nav_item_style'] = ['large'];
        $GLOBALS['__nct_post_meta'][201]['_off_canvas_nav_item_style'] = ['field_novi_off_canvas_nav_item_style'];
        $GLOBALS['__nct_post_meta'][202]['off_canvas_nav_item_style'] = ['button'];
        $GLOBALS['__nct_post_meta'][202]['_off_canvas_nav_item_style'] = ['field_novi_off_canvas_nav_item_style'];

        // Core WP meta must not be copied as custom (would confuse asserts if leaked).
        $GLOBALS['__nct_post_meta'][201]['_menu_item_url'] = ['https://example.test/#about'];

        $GLOBALS['__nct_home_url'] = 'https://example.test/';
        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[NL]' . $t, $texts),
                'error' => null,
            ];
        });

        $nlMenuId = MenuTranslator::translateMenu(10, 'en', 'nl', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($nlMenuId);
        $this->assertCount(2, $items);
        usort($items, static fn ($a, $b) => ((int) $a->menu_order) <=> ((int) $b->menu_order));

        $largeId = (int) $items[0]->ID;
        $buttonId = (int) $items[1]->ID;

        $this->assertSame('large', (string) get_post_meta($largeId, 'off_canvas_nav_item_style', true));
        $this->assertSame('field_novi_off_canvas_nav_item_style', (string) get_post_meta($largeId, '_off_canvas_nav_item_style', true));
        $this->assertSame('button', (string) get_post_meta($buttonId, 'off_canvas_nav_item_style', true));
        $this->assertSame('field_novi_off_canvas_nav_item_style', (string) get_post_meta($buttonId, '_off_canvas_nav_item_style', true));

        // Bare "#" CTA must keep URL and translate label — never "Not found- …".
        $this->assertSame('#', (string) $items[1]->url);
        $this->assertSame('[NL]Visit showroom', (string) $items[1]->title);
        $this->assertStringNotContainsString('Not found-', (string) $items[1]->title);
    }

    public function testRemapsPopupMakerNavItemPopupIdToPolylangTwin(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - NL'];
        $GLOBALS['__nct_menu_items'][10] = [
            301 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Showroom bezoeken',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];

        $GLOBALS['__nct_post_meta'][301]['off_canvas_nav_item_style'] = ['button'];
        $GLOBALS['__nct_post_meta'][301]['_pum_nav_item_options'] = [[
            'popup_id' => '703',
        ]];

        // NL popup 703 → EN twin 1703
        $GLOBALS['__nct_pll_post_map']['703|en'] = 1703;

        DeepLTranslator::setTestTranslator(function (array $texts, string $sourceLang, string $targetLang, array $options = []): array {
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => '[EN]' . $t, $texts),
                'error' => null,
            ];
        });

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $this->assertCount(1, $items);
        $newItemId = (int) $items[0]->ID;

        $pum = get_post_meta($newItemId, '_pum_nav_item_options', true);
        $this->assertIsArray($pum);
        $this->assertSame('1703', (string) ($pum['popup_id'] ?? ''));
        $this->assertSame('button', (string) get_post_meta($newItemId, 'off_canvas_nav_item_style', true));
    }
}
