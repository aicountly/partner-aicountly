<?php

/**
 * Router for PHP's built-in server during local development:
 *
 *   php -S 127.0.0.1:8081 dev-server.php
 *
 * It mirrors the production .htaccess rule — real files (assets/app.css and
 * friends) are served directly, everything else is routed to index.php.
 * Apache handles this on cPanel, so this file plays no part in production.
 */

$path = __DIR__ . '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if (is_file($path) && realpath($path) !== __FILE__) {
    return false;
}

require __DIR__ . '/index.php';
