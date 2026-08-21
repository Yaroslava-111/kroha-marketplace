<?php
declare(strict_types=1);

// Маршрутизатор для встроенного сервера PHP: `php -S 127.0.0.1:8091 router.php`.
// Для Apache/хостинга аналогичные правила есть в .htaccess.

require_once __DIR__ . '/includes/functions.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}
if ($path === '/robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}

$lot = parse_pretty_lot_path($path);
if ($lot !== null) {
    $_GET['id'] = $lot['id'];
    $_GET['pretty'] = '1';
    require __DIR__ . '/' . $lot['kind'] . '.php';
    return true;
}

return false;
