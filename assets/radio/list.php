<?php
header('Content-Type: application/json');

$dir = __DIR__;
$files = array_merge(
    glob($dir . '/*.mp3') ?: [],
    glob($dir . '/*.wav') ?: [],
    glob($dir . '/*.ogg') ?: []
);

$urls = array_map(function ($path) {
    return 'assets/radio/' . rawurlencode(basename($path));
}, $files);

echo json_encode($urls);

