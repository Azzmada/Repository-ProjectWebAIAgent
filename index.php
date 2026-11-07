<?php
/**
 * Front controller for public pages.
 *
 * Works with:
 *   index.php?page=home
 *   /home            (when APP_PRETTY=1 + .htaccess rewrite)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$routes = [
    'home'    => 'pages/home.php',
    'pesawat' => 'pages/flights.php',
    'promo'   => 'pages/promo.php',
    'bantuan' => 'pages/help.php',
];

$page = strtolower(trim((string) ($_GET['page'] ?? 'home')));
if ($page === '' || $page === 'index') {
    $page = 'home';
}

if (!isset($routes[$page])) {
    abort(404, 'Halaman yang Anda cari tidak tersedia.');
}

// The target page bootstraps itself (so each file is standalone-runnable)
// and renders header + content + footer.
require __DIR__ . '/' . $routes[$page];
