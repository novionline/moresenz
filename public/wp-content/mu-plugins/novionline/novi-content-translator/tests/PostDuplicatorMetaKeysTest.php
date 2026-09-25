<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorMetaKeysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_filters'] = [];
    }

    public function testTranslatableMetaKeysDefaultIsNonEmpty(): void
    {
        $keys = PostDuplicator::getTranslatableMetaKeys();
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
        $this->assertContains('_yoast_wpseo_title', $keys);
        $this->assertContains('_yoast_wpseo_metadesc', $keys);
        $this->assertContains('_yoast_wpseo_focuskw', $keys);
        $this->assertContains('_yoast_wpseo_bctitle', $keys);
        $this->assertContains('_yoast_wpseo_opengraph-title', $keys);
        $this->assertContains('_yoast_wpseo_opengraph-description', $keys);
        $this->assertContains('_yoast_wpseo_twitter-title', $keys);
        $this->assertContains('_yoast_wpseo_twitter-description', $keys);
        $this->assertContains('_nectar_portfolio_description', $keys);
        $this->assertContains('_nectar_portfolio_client', $keys);
        $this->assertContains('_nectar_portfolio_extra_content', $keys);
        $this->assertContains('_nectar_quote', $keys);
        $this->assertContains('_nectar_quote_author', $keys);
        $this->assertContains('_nectar_header_title', $keys);
        $this->assertContains('_nectar_header_subtitle', $keys);
        $this->assertContains('team_function', $keys);
        $this->assertContains('team_quote', $keys);
        $this->assertContains('novi_page_service_type', $keys);
    }

    public function testRelationshipMetaKeysDefaultIncludesConnectedTeamMembers(): void
    {
        $keys = PostDuplicator::getRelationshipMetaKeys();
        $this->assertContains('connected_team_members', $keys);
    }

    public function testRelationshipMetaKeysAreFilterable(): void
    {
        add_filter('nct_relationship_meta_keys', function (array $keys) {
            $keys[] = 'custom_relationship';
            return $keys;
        });

        $keys = PostDuplicator::getRelationshipMetaKeys();
        $this->assertContains('connected_team_members', $keys);
        $this->assertContains('custom_relationship', $keys);
    }

    public function testTranslatableMetaKeysAreFilterable(): void
    {
        add_filter('nct_translatable_meta_keys', function (array $keys) {
            $keys[] = 'custom_meta_key_for_test';
            return $keys;
        });

        $keys = PostDuplicator::getTranslatableMetaKeys();
        $this->assertContains('custom_meta_key_for_test', $keys);
    }
}

