<?php
/**
 * Debug script to find Mautic installation
 */

echo "Current directory: " . getcwd() . "\n";
echo "Script directory: " . __DIR__ . "\n";

$searchPaths = [
    getcwd(),
    getcwd() . '/app',
    getcwd() . '/../app',
    getcwd() . '/../../app',
    __DIR__,
    __DIR__ . '/../app',
    __DIR__ . '/../../app',
    '/var/www/html',
    '/var/www/html/app',
    '/var/www/html/docroot',
    '/var/www/html/docroot/app',
];

echo "\nSearching for bootstrap.php in:\n";
foreach ($searchPaths as $path) {
    $bootstrapPath = $path . '/bootstrap.php';
    $exists = file_exists($bootstrapPath);
    echo "  $bootstrapPath - " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
}

echo "\nSearching for app directory in:\n";
foreach ($searchPaths as $path) {
    $appPath = $path . '/app';
    $exists = is_dir($appPath);
    echo "  $appPath - " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
}

echo "\nContents of current directory:\n";
$files = scandir(getcwd());
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $path = getcwd() . '/' . $file;
        $type = is_dir($path) ? 'DIR' : 'FILE';
        echo "  $file ($type)\n";
    }
}
