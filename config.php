<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');

define('APP_NAME', 'Кроха');
define('APP_TAGLINE', 'Объявления и аукционы для детских вещей');

// Режим разработки: показывать ссылки восстановления пароля прямо на странице.
// На боевом хостинге поставьте false и подключите реальную отправку писем.
define('APP_DEV', true);

// Драйвер БД: 'sqlite' — локальная разработка, 'mysql' — хостинг
define('DB_DRIVER', 'sqlite');

// Публичный адрес сайта (для sitemap.xml, robots.txt и canonical-ссылок).
// На боевом хостинге замените на реальный домен, например https://kroha.ru
define('APP_BASE_URL', 'http://127.0.0.1:8091');

// --- SQLite (локально) ---
define('DB_PATH', __DIR__ . '/database/kroha.db');

// --- MySQL (хостинг) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'kroha');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
