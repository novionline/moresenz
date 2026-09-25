<?php

namespace NoviOnline\ContentTranslator\Tests;

use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use PHPUnit\Framework\TestCase;

class BlockContentTranslatorUnicodeEscapeNormalizationTest extends TestCase
{
    public function testNormalizeBlocksForSerializationDecodesLiteralU00EscapesInAttrsStrings(): void
    {
        $blocks = [
            [
                'blockName' => 'nectar-blocks/text',
                'attrs' => [
                    'blockId' => 'block-x',
                    'content' => '© 2025 Datalyzer. Gebouwd door u003ca href=u0022https://bonjourney.nl/u0022 target=u0022_blanku0022u003eBonjourneyu003c/au003e',
                ],
                'innerHTML' => '',
                'innerContent' => [],
                'innerBlocks' => [],
            ],
        ];

        $rm = new \ReflectionMethod(BlockContentTranslator::class, 'normalizeBlocksForSerialization');
        $rm->setAccessible(true);
        $rm->invokeArgs(null, [&$blocks]);

        $content = (string) ($blocks[0]['attrs']['content'] ?? '');
        $this->assertStringContainsString('<a href="https://bonjourney.nl/"', $content);
        $this->assertStringNotContainsString('u003ca', $content);
        $this->assertStringNotContainsString('u0022', $content);
    }

    public function testNormalizeBlocksForSerializationDecodesLiteralU0022AndU0026ampSequences(): void
    {
        $blocks = [
            [
                'blockName' => 'nectar-blocks/text',
                'attrs' => [
                    'blockId' => 'block-y',
                    'content' => 'ISO 27001 u0026amp; SOC2 and u0022quotedu0022',
                ],
                'innerBlocks' => [],
            ],
        ];

        $rm = new \ReflectionMethod(BlockContentTranslator::class, 'normalizeBlocksForSerialization');
        $rm->setAccessible(true);
        $rm->invokeArgs(null, [&$blocks]);

        $content = (string) ($blocks[0]['attrs']['content'] ?? '');
        $this->assertSame('ISO 27001 &amp; SOC2 and "quoted"', $content);
    }

    public function testNormalizeBlocksForSerializationDoesNotMutateBlockIdsWithUHex(): void
    {
        $blocks = [
            [
                'blockName' => 'nectar-blocks/icon-list-item',
                'attrs' => [
                    'blockId' => 'block-u605bmft2ht1',
                    'title' => 'houdbaar',
                ],
                'innerBlocks' => [],
            ],
        ];

        $rm = new \ReflectionMethod(BlockContentTranslator::class, 'normalizeBlocksForSerialization');
        $rm->setAccessible(true);
        $rm->invokeArgs(null, [&$blocks]);

        $this->assertSame('block-u605bmft2ht1', $blocks[0]['attrs']['blockId']);
        $this->assertSame('houdbaar', $blocks[0]['attrs']['title']);
    }
}

