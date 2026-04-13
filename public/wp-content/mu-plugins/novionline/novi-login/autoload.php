<?php

//bail if accessed directly
if (!defined('ABSPATH')) exit;

$directoriesToRequire = [
    realpath(__DIR__ . '/classes'),
];

foreach ($directoriesToRequire as $directory) {
    if (!$directory) {
        continue;
    }
    $directoryFiles = scandir($directory);
    if ($directoryFiles) {
        foreach ($directoryFiles as $directoryFile) {
            if ($directoryFile === '.' || $directoryFile === '..') {
                continue;
            }
            $filePath = $directory . '/' . $directoryFile;
            if (!is_file($filePath)) {
                continue;
            }
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($fileExtension === 'php') {
                require_once $filePath;
            }
        }
    }
}
