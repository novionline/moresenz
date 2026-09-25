<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use PHPUnit\Framework\TestCase;

class DeepLTranslatorKeyValidationTest extends TestCase
{
    public function testMaskApiKeyIdentifierReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame('', DeepLTranslator::maskApiKeyIdentifier(''));
        $this->assertSame('', DeepLTranslator::maskApiKeyIdentifier('   '));
    }

    public function testMaskApiKeyIdentifierUsesPrefixAndLastFour(): void
    {
        $this->assertSame('DE****BEEF', DeepLTranslator::maskApiKeyIdentifier('DEADBEEF'));
    }

    public function testBuildUsageValidationCacheKeyUsesSha256(): void
    {
        $apiKey = 'key123';
        $expected = 'nct_deepl_usage_valid_' . hash('sha256', $apiKey);

        $this->assertSame($expected, DeepLTranslator::buildUsageValidationCacheKey($apiKey));
    }

    public function testEncryptAndDecryptApiKeyRoundTrip(): void
    {
        $apiKey = 'my-super-secret-key';
        $encrypted = DeepLTranslator::encryptApiKeyForStorage($apiKey);

        $this->assertNotSame($apiKey, $encrypted);
        $this->assertSame($apiKey, DeepLTranslator::decryptApiKeyFromStorage($encrypted));
    }

    public function testIsQuotaExhaustedReturnsTrueWhenUsageAtOrAboveLimit(): void
    {
        $status = [
            'usage' => [
                'character_count' => 500000,
                'character_limit' => 500000,
            ],
        ];

        $this->assertTrue(DeepLTranslator::isQuotaExhausted($status));
    }

    public function testIsQuotaExhaustedReturnsFalseWhenBelowLimitOrNoLimit(): void
    {
        $belowLimit = [
            'usage' => [
                'character_count' => 1000,
                'character_limit' => 500000,
            ],
        ];
        $noLimit = [
            'usage' => [
                'character_count' => 1000,
                'character_limit' => 0,
            ],
        ];

        $this->assertFalse(DeepLTranslator::isQuotaExhausted($belowLimit));
        $this->assertFalse(DeepLTranslator::isQuotaExhausted($noLimit));
    }
}

