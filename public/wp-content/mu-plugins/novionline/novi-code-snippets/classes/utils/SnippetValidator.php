<?php

namespace NoviOnline\CodeSnippets;

use Peast\Peast;
use Peast\Syntax\Exception as PeastException;
use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\Parsing\SourceException as CssSourceException;
use Sabberworm\CSS\Rule\Rule;

/**
 * Class SnippetValidator
 * Validates CSS/JS using proper parsers (sabberworm/php-css-parser, mck89/peast).
 * CSS: syntax parse + property-name whitelist (rejects unknown properties like "backgroundrrr").
 * JS: full syntax parse via Peast. Invalid code is not output on the front-end.
 * @package NoviOnline\CodeSnippets
 */
class SnippetValidator
{
    const META_VALID = 'snippet_valid';

    const META_VALIDATION_ERROR = 'snippet_validation_error';

    const META_MINIFIED = 'snippet_code_minified';

    /**
     * Validate snippet code with CSS or JS parser; invalid code throws
     * @param string $code
     * @param string $type css|js
     * @return bool true if valid
     */
    public static function validate(string $code, string $type): bool
    {
        $result = self::validateWithDetails($code, $type);
        return $result['valid'];
    }

    /**
     * Validate and return details for display (message, line, column on error).
     * @param string $code
     * @param string $type css|js
     * @return array{valid: bool, message: string|null, line: int|null, column: int|null}
     */
    public static function validateWithDetails(string $code, string $type): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['valid' => true, 'message' => null, 'line' => null, 'column' => null];
        }
        try {
            if ($type === 'css') {
                self::validateCss($code);
            } else {
                self::validateJs($code);
            }
            return ['valid' => true, 'message' => null, 'line' => null, 'column' => null];
        } catch (\Throwable $e) {
            $line = null;
            $column = null;
            $message = $e->getMessage();
            if ($e instanceof SnippetValidationException) {
                $line = $e->getErrorLine();
                $column = $e->getErrorColumn();
            } elseif ($e instanceof CssSourceException) {
                $line = $e->getLineNumber();
                $column = $e->getColumnNumber();
            } elseif ($e instanceof PeastException && $e->getPosition()) {
                $pos = $e->getPosition();
                $line = $pos->getLine();
                $column = $pos->getColumn();
            }
            return ['valid' => false, 'message' => $message, 'line' => $line, 'column' => $column];
        }
    }

    /**
     * Validate CSS: full parse + property whitelist; on parse/selector failure fall back to property-name-only check.
     * @param string $code
     * @return void
     */
    protected static function validateCss(string $code): void
    {
        try {
            $parser = new CssParser($code);
            $document = $parser->parse();
            foreach ($document->getAllRuleSets() as $ruleSet) {
                foreach ($ruleSet->getRules() as $rule) {
                    if (!$rule instanceof Rule) {
                        continue;
                    }
                    $propertyName = $rule->getRule();
                    if (!CssPropertyWhitelist::isValidPropertyName($propertyName)) {
                        $line = $rule->getLineNumber();
                        $col = $rule->getColumnNumber();
                        throw new SnippetValidationException('Invalid CSS property: ' . $propertyName, $line, $col);
                    }
                }
            }
        } catch (\Throwable $e) {
            //allow modern selectors (:not, :has, etc.) when sabberworm fails; only check property names
            self::validateCssPropertyNamesOnly($code);
        }
    }

    /**
     * Fallback CSS validation: extract declaration blocks and validate property names only (no full parse).
     * @param string $code
     * @return void
     */
    protected static function validateCssPropertyNamesOnly(string $code): void
    {
        //find blocks { ... }; inside each block find property-name: patterns
        if (!preg_match_all('/\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s', $code, $blocks)) {
            return;
        }
        foreach ($blocks[1] as $block) {
            //match identifiers (property names) followed by colon; exclude pseudo/at-rules that use : in selectors
            if (!preg_match_all('/([a-zA-Z_\-][a-zA-Z0-9_\-]*)\s*:/', $block, $props)) {
                continue;
            }
            foreach ($props[1] as $propertyName) {
                if (!CssPropertyWhitelist::isValidPropertyName($propertyName)) {
                    throw new SnippetValidationException('Invalid CSS property: ' . $propertyName, null, null);
                }
            }
        }
    }

    /**
     * Validate JS using mck89/peast (throws on invalid syntax)
     * @param string $code
     * @return void
     */
    protected static function validateJs(string $code): void
    {
        Peast::latest($code)->parse();
    }
}
