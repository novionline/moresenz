<?php

namespace NoviOnline\Core;

/**
 * class JsonFetchComponent
 * @package NoviOnline\Core
 */
class JsonFetchComponent extends Singleton
{
    /**
     * Set default value for files array
     * @var array ]
     */
    protected array $files = [];

    /**
     * Fetch contents of a JSON file
     * @param string $path
     * @return \stdClass|false
     */
    public function fetch(string $path): \stdClass|false
    {
        //set default value
        $content = false;

        //bail if no path given
        if (!$path) return $content;

        //prevent redundant JSON fetching
        if (array_key_exists($path, $this->files)) return $this->files[$path];

        try {
            $rawJson = @file_get_contents($path);
            if ($rawJson) {
                $parsedJson = json_decode($rawJson);
                if (is_a($parsedJson, '\StdClass')) {
                    $content = $parsedJson;
                    $this->files[$path] = $parsedJson;
                }
            }
        } catch (\Exception $exception) {
            Log::log(sprintf('Failed fetching JSON wil with path %1s. Error message: %2s', $path, $exception->getMessage()));
        }

        return $content;
    }
}