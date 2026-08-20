<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$q = trim((string) ($_GET['q'] ?? ''));
$ql = mb_strtolower($q);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['q' => $q, 'categories' => [], 'items' => [], 'auctions' => [], 'all_url' => 'index.php?type=all'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = pdo();

$counts = [];
foreach ($pdo->query("SELECT category, COUNT(*) c FROM items WHERE status = 'active' GROUP BY category") as $r) {
    $counts[$r['category']] = (int) $r['c'];
}
foreach ($pdo->query("SELECT category, COUNT(*) c FROM auctions WHERE status = 'active' GROUP BY category") as $r) {
    $counts[$r['category']] = ($counts[$r['category']] ?? 0) + (int) $r['c'];
}

$categories = [];
foreach (categories() as $cat) {
    if (mb_stripos($cat, $q) !== false) {
        $categories[] = ['name' => $cat, 'count' => $counts[$cat] ?? 0];
    }
}

$like = '%' . $ql . '%';

$items = [];
$stmt = $pdo->prepare("SELECT id, title, price, city, is_giveaway FROM items WHERE status = 'active' AND search_lc LIKE ? ORDER BY created_at DESC, id DESC LIMIT 5");
$stmt->execute([$like]);
foreach ($stmt as $r) {
    $items[] = [
        'id' => (int) $r['id'],
        'title' => $r['title'],
        'price_label' => (int) $r['price'] > 0 ? money((int) $r['price']) : 'Отдам даром',
        'city' => $r['city'],
        'is_giveaway' => (int) $r['is_giveaway'] === 1,
        'url' => 'item.php?id=' . (int) $r['id'],
    ];
}

$auctions = [];
$stmt = $pdo->prepare("SELECT id, title, current_price FROM auctions WHERE status = 'active' AND search_lc LIKE ? ORDER BY created_at DESC, id DESC LIMIT 5");
$stmt->execute([$like]);
foreach ($stmt as $r) {
    $auctions[] = [
        'id' => (int) $r['id'],
        'title' => $r['title'],
        'price_label' => money((int) $r['current_price']),
        'url' => 'auction.php?id=' . (int) $r['id'],
    ];
}

echo json_encode([
    'q' => $q,
    'categories' => $categories,
    'items' => $items,
    'auctions' => $auctions,
    'all_url' => 'index.php?type=all&q=' . rawurlencode($q),
], JSON_UNESCAPED_UNICODE);
