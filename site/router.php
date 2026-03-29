<?php
// PHP built-in server router — mimics .htaccess rewrite
// Usage: php -S localhost:8080 -t site/ site/router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve real files (CSS, JS, images, fonts, etc.) directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let the built-in server serve the file
}

// Everything else → index.php
require __DIR__ . '/index.php';
