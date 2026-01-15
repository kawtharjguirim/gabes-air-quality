<?php

// Router script for PHP built-in server

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Don't serve API routes as static files - always route through Symfony
if (str_starts_with($uri, '/api/')) {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    return;
}

// Check if it's a real file (like CSS, JS, images)
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false; // Serve the file as-is
}

// All other requests go through Symfony
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/index.php';
