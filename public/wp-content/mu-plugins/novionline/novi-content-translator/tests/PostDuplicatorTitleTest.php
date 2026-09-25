<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorTitleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_filters'] = [];
        $GLOBALS['__nct_pll_languages_list'] = ['nl', 'en', 'de'];
    }

    public function testNectarSectionsDoesNotTranslateTitleAndPrefixesUppercasedLangCode(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('nectar_sections', 'Bento Grid', 'nl');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('NL - Bento Grid', $result['targetTitle']);
    }

    public function testWpBlockDoesNotTranslateTitleAndPrefixesUppercasedLangCode(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('wp_block', 'Landing Block', 'nl');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('NL - Landing Block', $result['targetTitle']);
    }

    public function testOtherPostTypesTranslateTitle(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('page', 'About Us', 'nl');

        $this->assertSame(true, $result['shouldTranslateTitle']);
        $this->assertSame('About Us', $result['targetTitle']);
    }

    public function testTeamKeepsTitleAsIsWithoutTranslationOrPrefix(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('team', 'Daniek Brinkhof', 'en');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('Daniek Brinkhof', $result['targetTitle']);
    }

    public function testProjectKeepsTitleAsIsWithoutTranslationOrPrefix(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('project', 'KIEM', 'en');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('KIEM', $result['targetTitle']);
    }

    public function testKeepTitlePostTypesAreFilterable(): void
    {
        add_filter('nct_keep_title_post_types', static function (array $postTypes): array {
            $postTypes[] = 'vacancy';
            return $postTypes;
        });

        $result = PostDuplicator::buildTargetTitleForPostType('vacancy', 'Sustainability Lead', 'en');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('Sustainability Lead', $result['targetTitle']);
    }

    public function testIdempotencyAvoidsDoublePrefixing(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('nectar_sections', 'NL - Bento Grid', 'nl');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('NL - Bento Grid', $result['targetTitle']);
    }

    public function testStripsKnownLanguagePrefixBeforeApplyingNewPrefix(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('wp_block', 'EN - Process steps', 'nl');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('NL - Process steps', $result['targetTitle']);
    }

    public function testStripsStackedLanguagePrefixesBeforeApplyingNewPrefix(): void
    {
        $result = PostDuplicator::buildTargetTitleForPostType('wp_block', 'NL - EN - Process steps', 'nl');

        $this->assertSame(false, $result['shouldTranslateTitle']);
        $this->assertSame('NL - Process steps', $result['targetTitle']);
    }
}

