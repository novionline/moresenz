<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

class DeepLTranslatorStatusTest extends TestCase
{
    public function testValidateDeepLKeyAndGetUsageReturnsExpectedEmptyState(): void
    {
        $status = DeepLTranslator::validateDeepLKeyAndGetUsage('');

        $this->assertFalse($status['valid']);
        $this->assertSame('', $status['identifier']);
        $this->assertSame(0, (int) ($status['usage']['character_count'] ?? -1));
        $this->assertSame(0, (int) ($status['usage']['character_limit'] ?? -1));
        $this->assertFalse($status['quota_exhausted']);
        $this->assertNull($status['refresh_at']);
        $this->assertNull($status['period_start_at']);
        $this->assertNull($status['period_end_at']);
        $this->assertIsInt($status['last_checked']);
    }

    public function testGetDeepLKeyStatusReturnsInvalidWhenNoKeyConfigured(): void
    {
        $status = DeepLTranslator::getDeepLKeyStatus(true);

        $this->assertFalse($status['valid']);
        $this->assertSame('', $status['identifier']);
        $this->assertFalse($status['quota_exhausted']);
        $this->assertSame(0, (int) ($status['usage']['character_count'] ?? -1));
        $this->assertSame(0, (int) ($status['usage']['character_limit'] ?? -1));
    }
}

