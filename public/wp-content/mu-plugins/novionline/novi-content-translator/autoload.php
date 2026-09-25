<?php

//bail if accessed directly
if (!defined('ABSPATH')) exit;

//load Composer autoloader for third-party libraries (DeepL SDK, etc.)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

//simple autoload for this plugin's own classes
$directoriesToRequire = [
    realpath(__DIR__ . '/classes/components'),
    realpath(__DIR__ . '/classes/cli'),
    realpath(__DIR__ . '/classes/utils')
];

foreach ($directoriesToRequire as $directory) {
    if (!$directory) continue;
    
    $directoryFiles = scandir($directory);
    if ($directoryFiles) {
        foreach ($directoryFiles as $directoryFile) {
            $filePath = $directory . '/' . $directoryFile;
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($fileExtension === 'php') require_once($filePath);
        }
    }
}

