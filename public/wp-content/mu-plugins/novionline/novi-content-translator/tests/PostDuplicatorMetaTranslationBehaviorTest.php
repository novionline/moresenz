<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorMetaTranslationBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__nct_filters'] = [];
    }

    public function testGetTranslatableMetaKeysFallsBackToDefaultsWhenFilterReturnsNonArray(): void
    {
        $defaults = PostDuplicator::getTranslatableMetaKeys();

        add_filter('nct_translatable_meta_keys', static function () {
            return 'invalid';
        });

        $keys = PostDuplicator::getTranslatableMetaKeys();

        $this->assertSame($defaults, $keys);
    }

    public function testGetTranslatableMetaKeysNormalizesEmptyValuesOut(): void
    {
        add_filter('nct_translatable_meta_keys', static function (array $keys): array {
            $keys[] = '';
            $keys[] = 'custom_meta_a';
            $keys[] = '0';
            return $keys;
        });

        $keys = PostDuplicator::getTranslatableMetaKeys();

        $this->assertContains('custom_meta_a', $keys);
        $this->assertContains('0', $keys);
        $this->assertNotContains('', $keys);
    }
}

