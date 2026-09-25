<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\GravityFormsTranslator;
use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;
use NoviOnline\ContentTranslator\Core\MenuTranslator;
use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Full Popup Maker compatibility coverage across menus, block content, and popup meta.
 */
final class PopupMakerCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DeepLTranslator::setTestTranslator(null);
        InternalLinkTranslator::resetRuntimeCache();
        GravityFormsTranslator::setTestFormTitlesById(null);

        $GLOBALS['__nct_posts'] = [];
        $GLOBALS['__nct_permalink_map'] = [];
        $GLOBALS['__nct_terms'] = [];
        $GLOBALS['__nct_term_link_map'] = [];
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_pll_term_map'] = [];
        $GLOBALS['__nct_url_to_postid_map'] = [];
        $GLOBALS['__nct_home_url'] = 'https://example.test/';

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
        $GLOBALS['__nct_post_meta'] = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        GravityFormsTranslator::setTestFormTitlesById(null);
        if (isset($GLOBALS['__nct_default_test_translator']) && is_callable($GLOBALS['__nct_default_test_translator'])) {
            DeepLTranslator::setTestTranslator($GLOBALS['__nct_default_test_translator']);
        }
    }

    // --- menu ---

    public function testMenuRemapsPumNavItemPopupIdWhenTwinExists(): void
    {
        $this->seedOffCanvasPumMenuItem('703', 'button');
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;

        DeepLTranslator::setTestTranslator($this->identityTranslator('[EN]'));

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $this->assertCount(1, $items);
        $newItemId = (int) $items[0]->ID;

        $pum = get_post_meta($newItemId, '_pum_nav_item_options', true);
        $this->assertIsArray($pum);
        $this->assertSame('2974', (string) ($pum['popup_id'] ?? ''));
        $this->assertSame('button', (string) get_post_meta($newItemId, 'off_canvas_nav_item_style', true));
        $this->assertSame('#', (string) $items[0]->url);
        $this->assertSame('[EN]Bezoek showroom', (string) $items[0]->title);
        $this->assertStringNotContainsString('Not found-', (string) $items[0]->title);
    }

    public function testMenuKeepsSourcePumPopupIdWhenNoTwin(): void
    {
        $this->seedOffCanvasPumMenuItem('703', 'button');
        //no pll twin for 703

        DeepLTranslator::setTestTranslator($this->identityTranslator('[EN]'));

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $newItemId = (int) $items[0]->ID;

        $pum = get_post_meta($newItemId, '_pum_nav_item_options', true);
        $this->assertIsArray($pum);
        $this->assertSame('703', (string) ($pum['popup_id'] ?? ''));
        $this->assertSame('button', (string) get_post_meta($newItemId, 'off_canvas_nav_item_style', true));
    }

    public function testMenuCopiesOffCanvasStyleAlongsidePumOptions(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - NL'];
        $GLOBALS['__nct_menu_items'][10] = [
            301 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Showroom',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
            302 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Brochure',
                'menu_order' => 2,
                'parent' => 0,
                'classes' => [],
            ],
        ];
        $GLOBALS['__nct_post_meta'][301]['off_canvas_nav_item_style'] = ['button'];
        $GLOBALS['__nct_post_meta'][301]['_pum_nav_item_options'] = [['popup_id' => '703']];
        $GLOBALS['__nct_post_meta'][302]['off_canvas_nav_item_style'] = ['large'];
        $GLOBALS['__nct_post_meta'][302]['_pum_nav_item_options'] = [['popup_id' => '823']];
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;
        $GLOBALS['__nct_pll_post_map']['823|en'] = 2973;

        DeepLTranslator::setTestTranslator($this->identityTranslator('[EN]'));

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $this->assertCount(2, $items);
        usort($items, static fn ($a, $b) => ((int) $a->menu_order) <=> ((int) $b->menu_order));

        $showroom = get_post_meta((int) $items[0]->ID, '_pum_nav_item_options', true);
        $brochure = get_post_meta((int) $items[1]->ID, '_pum_nav_item_options', true);
        $this->assertSame('2974', (string) ($showroom['popup_id'] ?? ''));
        $this->assertSame('2973', (string) ($brochure['popup_id'] ?? ''));
        $this->assertSame('button', (string) get_post_meta((int) $items[0]->ID, 'off_canvas_nav_item_style', true));
        $this->assertSame('large', (string) get_post_meta((int) $items[1]->ID, 'off_canvas_nav_item_style', true));
        $this->assertSame('#', (string) $items[0]->url);
        $this->assertSame('#', (string) $items[1]->url);
    }

    public function testMenuRemapsInjectedPopmakeClassFromSourceItemClasses(): void
    {
        //simulate Popup Maker merge_item_data(): source classes already include popmake-{nlId}
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - NL'];
        $GLOBALS['__nct_menu_items'][10] = [
            301 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Bezoek showroom',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => ['novi-button', 'popmake-703'],
            ],
        ];
        $GLOBALS['__nct_post_meta'][301]['off_canvas_nav_item_style'] = ['button'];
        $GLOBALS['__nct_post_meta'][301]['_pum_nav_item_options'] = [['popup_id' => '703']];
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;

        DeepLTranslator::setTestTranslator($this->identityTranslator('[EN]'));

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $this->assertCount(1, $items);
        $newItemId = (int) $items[0]->ID;

        $storedClasses = get_post_meta($newItemId, '_menu_item_classes', true);
        if (!is_array($storedClasses)) {
            //shim may store via menu row classes only
            $storedClasses = is_array($items[0]->classes ?? null) ? $items[0]->classes : [];
        }
        $classList = array_values(array_filter(array_map('strval', (array) $storedClasses)));

        $this->assertContains('popmake-2974', $classList);
        $this->assertNotContains('popmake-703', $classList);
        $this->assertContains('novi-button', $classList);

        $pum = get_post_meta($newItemId, '_pum_nav_item_options', true);
        $this->assertSame('2974', (string) ($pum['popup_id'] ?? ''));
    }

    public function testMenuKeepsPopmakeClassWhenNoTwin(): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - NL'];
        $GLOBALS['__nct_menu_items'][10] = [
            301 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Bezoek showroom',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => ['popmake-703'],
            ],
        ];
        $GLOBALS['__nct_post_meta'][301]['_pum_nav_item_options'] = [['popup_id' => '703']];

        DeepLTranslator::setTestTranslator($this->identityTranslator('[EN]'));

        $enMenuId = MenuTranslator::translateMenu(10, 'nl', 'en', ['force' => true, 'strict_links' => true]);
        $items = wp_get_nav_menu_items($enMenuId);
        $newItemId = (int) $items[0]->ID;
        $storedClasses = get_post_meta($newItemId, '_menu_item_classes', true);
        if (!is_array($storedClasses)) {
            $storedClasses = is_array($items[0]->classes ?? null) ? $items[0]->classes : [];
        }
        $classList = array_values(array_filter(array_map('strval', (array) $storedClasses)));

        $this->assertContains('popmake-703', $classList);
        $this->assertSame('703', (string) (get_post_meta($newItemId, '_pum_nav_item_options', true)['popup_id'] ?? ''));
    }

    // --- blocks / content ---

    public function testTranslateRemapsOpenPopupIdAndPopmakeClass(): void
    {
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;
        $content = $this->buttonBlock('703', 'Showroom');

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"2974"', $out);
        $this->assertStringContainsString('popmake-2974', $out);
        $this->assertStringNotContainsString('"openPopupId":"703"', $out);
        $this->assertStringNotContainsString('popmake-703', $out);
    }

    public function testSyncLinksOnlyRemapsOpenPopupIdAndPopmakeClass(): void
    {
        $GLOBALS['__nct_pll_post_map']['823|en'] = 2973;
        $content = $this->buttonBlock('823', 'Brochure', true);

        $out = BlockContentTranslator::syncLinksOnly($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"2973"', $out);
        $this->assertStringContainsString('popmake-2973', $out);
        $this->assertStringNotContainsString('popmake-823', $out);
        $this->assertStringNotContainsString('"openPopupId":"823"', $out);
    }

    public function testNestedButtonInsideRowColumnRemapsPopupIds(): void
    {
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;

        $content = <<<'EOT'
<!-- wp:nectar-blocks/row {"blockId":"row-1"} -->
<!-- wp:nectar-blocks/column {"blockId":"col-1"} -->
<!-- wp:nectar-blocks/button {"blockId":"btn-1","openPopupId":"703","className":"novi-button"} -->
<div class="wp-block-nectar-blocks-button"><a class="nectar-button popmake-703" href="#">Showroom</a></div>
<!-- /wp:nectar-blocks/button -->
<!-- /wp:nectar-blocks/column -->
<!-- /wp:nectar-blocks/row -->
EOT;

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"2974"', $out);
        $this->assertStringContainsString('popmake-2974', $out);
        $this->assertStringNotContainsString('popmake-703', $out);
    }

    public function testPopmakeClassOnlyRemapsWhenOpenPopupIdMissing(): void
    {
        $GLOBALS['__nct_pll_post_map']['823|en'] = 2973;

        $content = <<<'EOT'
<!-- wp:nectar-blocks/button {"blockId":"block-test","className":"novi-button popmake-823"} -->
<a class="nectar-button popmake-823" href="#">Brochure</a>
<!-- /wp:nectar-blocks/button -->
EOT;

        $out = BlockContentTranslator::syncLinksOnly($content, 'nl', 'en');

        $this->assertStringContainsString('popmake-2973', $out);
        $this->assertStringNotContainsString('popmake-823', $out);
        $this->assertStringNotContainsString('openPopupId', $out);
    }

    public function testLeavesPopupIdsUnchangedWhenNoTwin(): void
    {
        $content = $this->buttonBlock('703', 'Showroom');

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"703"', $out);
        $this->assertStringContainsString('popmake-703', $out);
    }

    public function testMultipleDifferentPopupIdsInOneContentBothRemapped(): void
    {
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;
        $GLOBALS['__nct_pll_post_map']['823|en'] = 2973;

        $content = $this->buttonBlock('703', 'Showroom') . "\n" . $this->buttonBlock('823', 'Brochure', true);

        $out = BlockContentTranslator::syncLinksOnly($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"2974"', $out);
        $this->assertStringContainsString('"openPopupId":"2973"', $out);
        $this->assertStringContainsString('popmake-2974', $out);
        $this->assertStringContainsString('popmake-2973', $out);
        $this->assertStringNotContainsString('"openPopupId":"703"', $out);
        $this->assertStringNotContainsString('"openPopupId":"823"', $out);
        $this->assertStringNotContainsString('popmake-703', $out);
        $this->assertStringNotContainsString('popmake-823', $out);
    }

    public function testTranslateRemapsGravityFormsFormIdAndOpenPopupIdTogether(): void
    {
        GravityFormsTranslator::setTestFormTitlesById([
            1 => 'Brochure download - NL',
            3 => 'Brochure download - EN',
            2 => 'Visit showroom - NL',
            4 => 'Visit showroom - EN',
        ]);
        $GLOBALS['__nct_pll_post_map']['703|en'] = 2974;

        $content = <<<'EOT'
<!-- wp:nectar-blocks/row {"blockId":"row-popup"} -->
<!-- wp:nectar-blocks/column {"blockId":"col-popup"} -->
<!-- wp:nectar-blocks/button {"blockId":"btn-popup","openPopupId":"703","className":"novi-button"} -->
<a class="nectar-button popmake-703" href="#">Open</a>
<!-- /wp:nectar-blocks/button -->
<!-- wp:gravityforms/form {"formId":"2","title":false,"description":false,"ajax":true} /-->
<!-- /wp:nectar-blocks/column -->
<!-- /wp:nectar-blocks/row -->
EOT;

        $out = BlockContentTranslator::translatePostContent($content, 'nl', 'en');

        $this->assertStringContainsString('"openPopupId":"2974"', $out);
        $this->assertStringContainsString('popmake-2974', $out);
        $this->assertStringContainsString('"formId":"4"', $out);
        $this->assertStringNotContainsString('"formId":"2"', $out);
        $this->assertStringNotContainsString('popmake-703', $out);
    }

    // --- popup post meta ---

    public function testNormalizePopupMakerMetaRewritesCookieAndResetsAnalytics(): void
    {
        $sourceId = 703;
        $targetId = 2974;

        $GLOBALS['__nct_post_meta'][$targetId] = [
            'popup_open_count' => [463],
            'popup_open_count_total' => [900],
            'popup_last_opened' => [1790324316],
            'popup_settings' => [[
                'theme_id' => '700',
                'cookies' => [
                    [
                        'event' => 'on_popup_close',
                        'settings' => [
                            'name' => 'pum-703',
                            'key' => '',
                            'session' => false,
                        ],
                    ],
                ],
            ]],
        ];

        $method = new ReflectionMethod(PostDuplicator::class, 'normalizePopupMakerMeta');
        $method->setAccessible(true);
        $method->invoke(null, $targetId, $sourceId);

        $this->assertSame('', (string) get_post_meta($targetId, 'popup_open_count', true));
        $this->assertSame('', (string) get_post_meta($targetId, 'popup_open_count_total', true));
        $this->assertSame('', (string) get_post_meta($targetId, 'popup_last_opened', true));

        $settings = get_post_meta($targetId, 'popup_settings', true);
        $this->assertIsArray($settings);
        $this->assertSame('pum-2974', (string) ($settings['cookies'][0]['settings']['name'] ?? ''));
        $this->assertSame('700', (string) ($settings['theme_id'] ?? ''));
    }

    public function testNormalizePopupMakerMetaThemeIdUnchangedAndEmptyCookiesOk(): void
    {
        $sourceId = 823;
        $targetId = 2973;

        $GLOBALS['__nct_post_meta'][$targetId] = [
            'popup_settings' => [[
                'theme_id' => '700',
                'cookies' => [],
            ]],
        ];

        $method = new ReflectionMethod(PostDuplicator::class, 'normalizePopupMakerMeta');
        $method->setAccessible(true);
        $method->invoke(null, $targetId, $sourceId);

        $settings = get_post_meta($targetId, 'popup_settings', true);
        $this->assertIsArray($settings);
        $this->assertSame('700', (string) ($settings['theme_id'] ?? ''));
        $this->assertSame([], $settings['cookies'] ?? null);
    }

    public function testNormalizePopupMakerMetaMissingSettingsDoesNotCrash(): void
    {
        $sourceId = 703;
        $targetId = 2974;
        $GLOBALS['__nct_post_meta'][$targetId] = [
            'popup_open_count' => [1],
        ];

        $method = new ReflectionMethod(PostDuplicator::class, 'normalizePopupMakerMeta');
        $method->setAccessible(true);
        $method->invoke(null, $targetId, $sourceId);

        $this->assertSame('', (string) get_post_meta($targetId, 'popup_open_count', true));
        $this->assertSame('', (string) get_post_meta($targetId, 'popup_settings', true));
    }

    /**
     * @return callable
     */
    private function identityTranslator(string $prefix)
    {
        return static function (array $texts, string $sourceLang, string $targetLang, array $options = []) use ($prefix): array {
            return [
                'success' => true,
                'translations' => array_map(static fn ($t) => $prefix . $t, $texts),
                'error' => null,
            ];
        };
    }

    private function seedOffCanvasPumMenuItem(string $popupId, string $style): void
    {
        $GLOBALS['__nct_menus'][10] = ['name' => 'Side - NL'];
        $GLOBALS['__nct_menu_items'][10] = [
            301 => [
                'type' => 'custom',
                'object' => 'custom',
                'object_id' => 0,
                'url' => '#',
                'title' => 'Bezoek showroom',
                'menu_order' => 1,
                'parent' => 0,
                'classes' => [],
            ],
        ];
        $GLOBALS['__nct_post_meta'][301]['off_canvas_nav_item_style'] = [$style];
        $GLOBALS['__nct_post_meta'][301]['_pum_nav_item_options'] = [['popup_id' => $popupId]];
    }

    private function buttonBlock(string $popupId, string $label, bool $popmakeInClassName = false): string
    {
        $className = $popmakeInClassName
            ? 'novi-button popmake-' . $popupId
            : 'novi-button';

        return <<<EOT
<!-- wp:nectar-blocks/button {"blockId":"block-{$popupId}","openPopupId":"{$popupId}","className":"{$className}"} -->
<div class="wp-block-nectar-blocks-button"><a class="nectar-button popmake-{$popupId}" href="#">{$label}</a></div>
<!-- /wp:nectar-blocks/button -->
EOT;
    }
}
