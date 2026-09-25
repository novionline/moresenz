<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\PostDuplicator;
use PHPUnit\Framework\TestCase;

class PostDuplicatorAuthorTest extends TestCase
{
    private function callResolveTargetAuthorId(int $sourceAuthorId): int
    {
        $rm = new \ReflectionMethod(PostDuplicator::class, 'resolveTargetAuthorId');
        $rm->setAccessible(true);
        return (int) $rm->invoke(null, $sourceAuthorId);
    }

    public function testPreservesOriginalAuthorIdWhenPresent(): void
    {
        $this->assertSame(12, $this->callResolveTargetAuthorId(12));
    }

    public function testFallsBackToZeroWhenNoSourceAuthorAndNoWpCurrentUser(): void
    {
        $this->assertSame(0, $this->callResolveTargetAuthorId(0));
    }
}

