<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorReusableBlocksTest extends TestCase
{
    public function testEnsureReusableBlockTranslationsMarksRefsAsFailedWhenPolylangFunctionsMissing(): void
    {
        $summary = PostDuplicator::ensureReusableBlockTranslations([10, 'invalid', -1, 22], 'nl');

        $this->assertSame([], $summary['map']);
        $this->assertSame([], $summary['created']);
        $this->assertSame([], $summary['existing']);
        $this->assertCount(2, $summary['failed']);
        $this->assertSame(10, (int) $summary['failed'][0]['source_ref']);
        $this->assertSame(22, (int) $summary['failed'][1]['source_ref']);
        //bootstrap always shims Polylang; missing posts are reported as not found
        $this->assertStringContainsString('Reusable block not found.', (string) $summary['failed'][0]['error']);
    }
}

