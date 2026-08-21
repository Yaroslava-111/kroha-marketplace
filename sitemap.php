<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=UTF-8');

$pdo = pdo();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (sitemap_entries($pdo) as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . e(APP_BASE_URL . $entry['loc']) . "</loc>\n";
    echo '    <lastmod>' . e((string) $entry['lastmod']) . "</lastmod>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
