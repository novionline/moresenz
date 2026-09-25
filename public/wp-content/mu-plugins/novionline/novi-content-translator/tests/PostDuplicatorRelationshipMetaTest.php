<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorRelationshipMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_filters'] = [];
        $GLOBALS['__nct_pll_post_map'] = [];
        $GLOBALS['__nct_pll_post_translations_groups'] = [];
    }

    public function testRemapRelationshipMetaMapsIdsWhenTranslationExists(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '101|en' => 201,
            '102|en' => 202,
        ];

        $meta = [
            'connected_team_members' => [[101, 102]],
            '_connected_team_members' => ['field_novi_connected_team_members'],
            'other_field' => ['leave-me'],
        ];

        $out = PostDuplicator::remapRelationshipMeta($meta, 'en');

        $this->assertSame([[201, 202]], $out['connected_team_members']);
        $this->assertSame(['field_novi_connected_team_members'], $out['_connected_team_members']);
        $this->assertSame(['leave-me'], $out['other_field']);
    }

    public function testRemapRelationshipMetaKeepsSourceIdWhenTranslationMissing(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '101|en' => 201,
        ];

        $meta = [
            'connected_team_members' => [[101, 999]],
        ];

        $out = PostDuplicator::remapRelationshipMeta($meta, 'en');

        $this->assertSame([[201, 999]], $out['connected_team_members']);
    }

    public function testRemapRelationshipMetaHandlesSingleNumericId(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '55|en' => 155,
        ];

        $meta = [
            'connected_team_members' => [55],
        ];

        $out = PostDuplicator::remapRelationshipMeta($meta, 'en');

        $this->assertSame([155], $out['connected_team_members']);
    }

    public function testRemapRelationshipMetaLeavesNonListedKeysAlone(): void
    {
        $GLOBALS['__nct_pll_post_map'] = [
            '10|en' => 20,
        ];

        $meta = [
            'unrelated_ids' => [[10, 11]],
        ];

        $out = PostDuplicator::remapRelationshipMeta($meta, 'en');

        $this->assertSame([[10, 11]], $out['unrelated_ids']);
    }
}
