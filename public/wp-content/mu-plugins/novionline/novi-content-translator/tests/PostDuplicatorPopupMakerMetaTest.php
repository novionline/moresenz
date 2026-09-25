<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PostDuplicatorPopupMakerMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_post_meta'] = [];
    }

    public function testNormalizePopupMakerMetaRewritesCookieNamesAndResetsCounters(): void
    {
        $sourceId = 703;
        $targetId = 1703;

        $GLOBALS['__nct_post_meta'][$targetId] = [
            'popup_open_count' => [463],
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
        $this->assertSame('', (string) get_post_meta($targetId, 'popup_last_opened', true));

        $settings = get_post_meta($targetId, 'popup_settings', true);
        $this->assertIsArray($settings);
        $this->assertSame('pum-1703', (string) ($settings['cookies'][0]['settings']['name'] ?? ''));
        $this->assertSame('700', (string) ($settings['theme_id'] ?? ''));
    }
}
