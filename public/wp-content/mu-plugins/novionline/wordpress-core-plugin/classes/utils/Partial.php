<?php

namespace NoviOnline\Core;

/**
 * Class Partial
 * @package NoviOnline\Core
 */
class Partial
{
    /**
     * Get or output partial template
     * @param string $file
     * @param array $args
     * @param bool $output
     * @param string $partialPath
     * @return string
     */
    public static function render(string $file, array $args = [], bool $output = true, string $partialPath = ''): string
    {
        $html = '';
        $isDev = getenv('DEV') === 'true';
        $partialsTemplate = $partialPath ?: get_stylesheet_directory() . '/partials/';

        // format file with extension
        $rawFile = $file;
        $file = !preg_match('#\.php$#', $file) ? $file . '.php' : $file;

        // check if file exist, if so include template with passed arguments
        if (!file_exists($partialsTemplate . $file)) {
            Log::log(sprintf('file %1s does not exist in given partials template directory %2s', $file, $partialsTemplate));
        } else {
            ob_start();
            extract($args);
            include($partialsTemplate . $file);
            $html = ob_get_clean();
        }

        //allow overruling partial templates
        $html = apply_filters('novi_partial_render', $html, $rawFile, $args, $output, $partialPath);

        //add comments for debugging purposes
        if ($isDev && !empty($html)) $html = '<!--- Start partial: ' . $partialsTemplate . $file . ' --->' . $html;
        if ($isDev && !empty($html)) $html = $html . '<!--- End partial: ' . $partialsTemplate . $file . ' --->';

        // echo the output when is enabled
        if ($output && !empty($html)) echo $html;

        return $html ?: '';
    }
}