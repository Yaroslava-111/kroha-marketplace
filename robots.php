<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /account.php\n";
echo "Disallow: /admin.php\n";
echo "Disallow: /messages.php\n";
echo "Disallow: /message.php\n";
echo "Disallow: /saved_search.php\n";
echo 'Sitemap: ' . APP_BASE_URL . "/sitemap.xml\n";
