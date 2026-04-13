<?php


//set up array of directories to scan for PHP files
$directoriesToRequire = [
    realpath(__DIR__ . '/classes/utils'),
    realpath(__DIR__ . '/classes/scripts'),
    realpath(__DIR__ . '/classes/settings'),
    realpath(__DIR__ . '/classes/components'),
    realpath(__DIR__ . '/classes/rest'),
    realpath(__DIR__ . '/classes/misc'),
    realpath(__DIR__ . '/classes/post-types'),
];

//scan "blocks" directory and add the PHP classes to the array
$blocksDirectory = realpath(__DIR__ . '/blocks');
if ($blocksDirectory) {
    $blockFolders = glob($blocksDirectory . '/*', GLOB_ONLYDIR);
    if ($blockFolders) {
        foreach ($blockFolders as $blockFolder) $directoriesToRequire[] = $blockFolder;
    }
}

//scan "post-types" directory and add the PHP classes to the array
$postTypeDirectory = realpath(__DIR__ . '/post-types');
if ($postTypeDirectory) {
    $postTypeFolders = glob($postTypeDirectory . '/*', GLOB_ONLYDIR);
    if ($postTypeFolders) {
        foreach ($postTypeFolders as $postTypeFolder) {
            $directoriesToRequire[] = $postTypeFolder;
        }
    }
}

//autoload the PHP files in the array
foreach ($directoriesToRequire as $directory) {
    if (!is_dir($directory)) continue;
    $directoryFiles = scandir($directory);
    if ($directoryFiles) {
        foreach ($directoryFiles as $directoryFile) {
            $filePath = $directory . '/' . $directoryFile;
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($fileExtension === 'php') {
                require_once($filePath);
            }
        }
    }
}