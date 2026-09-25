<?php

namespace NoviOnline\CodeSnippets;

/**
 * Exception carrying validation error message and optional line/column for display.
 * @package NoviOnline\CodeSnippets
 */
class SnippetValidationException extends \Exception
{
    /** @var int|null */
    private $errorLine;

    /** @var int|null */
    private $errorColumn;

    /**
     * @param string $message
     * @param int|null $line
     * @param int|null $column
     */
    public function __construct(string $message, ?int $line = null, ?int $column = null)
    {
        parent::__construct($message);
        $this->errorLine = $line;
        $this->errorColumn = $column;
    }

    /**
     * @return int|null
     */
    public function getErrorLine(): ?int
    {
        return $this->errorLine;
    }

    /**
     * @return int|null
     */
    public function getErrorColumn(): ?int
    {
        return $this->errorColumn;
    }
}
