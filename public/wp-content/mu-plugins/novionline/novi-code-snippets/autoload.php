<?php

//bail if accessed directly
if (!defined('ABSPATH')) exit;

$directoriesToRequire = [
    realpath(__DIR__ . '/classes/utils'),
    realpath(__DIR__ . '/classes/components')
];

foreach ($directoriesToRequire as $directory) {
    if (!is_dir($directory)) {
        continue;
    }
    $directoryFiles = scandir($directory);
    if ($directoryFiles) {
        foreach ($directoryFiles as $directoryFile) {
            $filePath = $directory . '/' . $directoryFile;
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($fileExtension === 'php') {
                require_once $filePath;
            }
        }
    }
}
