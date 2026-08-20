<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pdo = pdo();

$now = time();

// --- Пользователи (демо-пароль: demo; админ: admin123) ---
$users = [
    ['Мария', 'masha@kroha.test', password_hash('demo', PASSWORD_DEFAULT), 'Екатеринбург', 4.9, 12, 1, 0, 0],
    ['Светлана', 'sveta@kroha.test', password_hash('demo', PASSWORD_DEFAULT), 'Москва', 5.0, 8, 1, 0, 0],
    ['Анна', 'anna@kroha.test', password_hash('demo', PASSWORD_DEFAULT), 'Санкт-Петербург', 4.8, 3, 1, 0, 0],
    ['Администратор', 'admin@kroha.test', password_hash('admin123', PASSWORD_DEFAULT), 'Москва', 5.0, 0, 1, 1, 1],
];

$userId = [];
$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, city, rating, sold_count, verified, is_admin, is_moderator)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($users as $u) {
    $stmt->execute($u);
    $userId[] = (int) $pdo->lastInsertId();
}

// --- Объявления (фикс-цена) ---
$items = [
    [$userId[0], 'Коляска Bugaboo Cameleon, после одного ребёнка', 'Коляски', 0, 3, null, 'всесезон', 'как новая, после одного ребёнка', 12000, 'Екатеринбург', 'Отличное состояние, ремкомплект в комплекте, матрац в подарок. Новые такие стоят 60 000+.', 0],
    [$userId[1], 'Комбинезон REIMA 74, носил 1 сезон', 'Комбинезоны и верхняя одежда', 0, 2, '74', 'зима', 'как новое, носил 1 сезон', 2500, 'Москва', 'Постиран специальным средством, без потертостей. Оригинал.', 0],
    [$userId[2], 'Конструктор LEGO DUPLO, набор 80 деталей', 'Игрушки', 2, 6, null, null, 'в хорошем состоянии', 1800, 'Санкт-Петербург', 'Все детали на месте, в фирменной коробке.', 0],
    [$userId[1], 'Кровать детская с матрацем — отдам даром', 'Мебель', 2, 7, null, null, 'использовалась мало', 0, 'Москва', 'Отдам даром, самовывоз самовынос. Матрац в подарок.', 1],
    [$userId[0], 'Рюкзак школьный 4you, почти новый', 'Школьное', 6, 10, null, 'осень', 'почти новый', 900, 'Екатеринбург', 'Использовался один месяц, ортопедическая спинка.', 0],
    [$userId[2], 'Боди и комбинезоны 56–68, пакетом 15 шт', 'Одежда', 0, 1, '56–68', 'всесезон', 'хорошее состояние', 1500, 'Санкт-Петербург', 'Пакет для одного ребёнка: всё постирано, продаю вместе.', 0],
];

$itemId = [];
$stmt = $pdo->prepare(
    'INSERT INTO items (user_id, title, category, age_min, age_max, size, season, condition_label, price, city, description, is_giveaway, search_lc, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($items as $i) {
    $created = date('Y-m-d H:i:s', $now - random_int(86400, 86400 * 20));
    $searchLc = mb_strtolower(trim($i[1] . ' ' . $i[10] . ' ' . $i[9]));
    $stmt->execute([$i[0], $i[1], $i[2], $i[3], $i[4], $i[5], $i[6], $i[7], $i[8], $i[9], $i[10], $i[11], $searchLc, $created]);
    $itemId[] = (int) $pdo->lastInsertId();
}

// --- Аукционы ---
$auctions = [
    // старт, текущая, шаг, дней до конца, BIN, статус, bids
    ['Коляска Joie Versatrax, б/у', 'Коляски', 'после одного ребёнка', 8000, 11000, 5, 2, 16000, 'active', [
        [10000, 0, null],
        [10500, 1, 13000],
        [11000, 0, null],
    ]],
    ['Пакет комбинезонов 74–86, 4 шт', 'Комбинезоны и верхняя одежда', 'в хорошем состоянии', 3000, 3200, 5, 5, null, 'active', [
        [3000, 0, null],
        [3200, 1, 4000],
    ]],
    ['Комбинезон REIMA 92, почти как новый', 'Комбинезоны и верхняя одежда', 'как новый', 2000, 3800, 5, 0, 6000, 'active', [
        [2000, 0, null],
        [2600, 0, null],
        [3200, 0, null],
        [3800, 1, 5000],
    ]],
    ['Набор школьных принадлежностей (пакет)', 'Школьное', 'новое/б/у', 1000, 1000, 5, 3, null, 'active', []],
];

$auctionId = [];
$stmt = $pdo->prepare(
    'INSERT INTO auctions (user_id, title, category, condition_label, start_price, current_price, min_bid_step, end_at, bin_price, status, search_lc, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($auctions as $idx => $a) {
    $days = $a[6];
    if ($days === 0) {
        $endAt = date('Y-m-d H:i:s', $now + random_int(3600 * 3, 3600 * 8));
    } else {
        $endAt = date('Y-m-d H:i:s', $now + $days * 86400 + random_int(0, 3600));
    }
    $created = date('Y-m-d H:i:s', $now - random_int(86400, max(86400, 86400 * $days)));
    $seller = $userId[$idx % count($userId)];
    $searchLc = mb_strtolower(trim($a[0] . ' ' . $a[2]));
    $stmt->execute([$seller, $a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $endAt, $a[7], $a[8], $searchLc, $created]);
    $auctionId[] = (int) $pdo->lastInsertId();
}

// --- Ставки ---
$bidStmt = $pdo->prepare(
    'INSERT INTO bids (auction_id, user_id, amount, is_proxy, proxy_limit, created_at) VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($auctions as $idx => $a) {
    $bids = $a[9];
    $order = 0;
    foreach ($bids as $b) {
        $order++;
        $bidAt = date('Y-m-d H:i:s', strtotime('-1 day', $now) + $order * 3600);
        $bidder = $userId[($idx + $order) % count($userId)];
        $bidStmt->execute([$auctionId[$idx], $bidder, $b[0], $b[1], $b[2], $bidAt]);
    }
}

// --- Продажи: объявления и завершённый аукцион ---
$pdo->prepare('UPDATE items SET status = \'sold\', buyer_id = ? WHERE id = ?')->execute([$userId[1], $itemId[0]]);
$pdo->prepare('UPDATE items SET status = \'sold\', buyer_id = ? WHERE id = ?')->execute([$userId[0], $itemId[1]]);

$finish = date('Y-m-d H:i:s', $now - 3 * 86400);
$pdo->prepare(
    'INSERT INTO auctions (user_id, title, category, condition_label, start_price, current_price, min_bid_step, end_at, bin_price, status, winner_bid_id, search_lc, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $userId[0], 'Куртка зимняя 92, почти новая', 'Комбинезоны и верхняя одежда', 'как новая',
    2500, 3200, 100, $finish, null, 'finished', null,
    mb_strtolower('Куртка зимняя 92, почти новая как новая'),
    date('Y-m-d H:i:s', $now - 8 * 86400),
]);
$finAuctionId = (int) $pdo->lastInsertId();
$bidStmt->execute([$finAuctionId, $userId[2], 2800, 0, null, date('Y-m-d H:i:s', $now - 5 * 86400)]);
$bidStmt->execute([$finAuctionId, $userId[2], 3200, 0, null, date('Y-m-d H:i:s', $now - 4 * 86400)]);
$winBidId = (int) $pdo->lastInsertId();
$pdo->prepare('UPDATE auctions SET winner_bid_id = ? WHERE id = ?')->execute([$winBidId, $finAuctionId]);

// --- Отзывы ---
$reviewStmt = $pdo->prepare(
    'INSERT INTO reviews (user_id, author_id, item_id, auction_id, rating, text, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$reviewStmt->execute([
    $userId[1], $userId[0], $itemId[1], null, 4,
    'Хороший комбинезон, размер соответствует. Чуть дольше обычного доставка.',
    date('Y-m-d H:i:s', $now - 2 * 86400),
]);

// --- Диалоги ---
$convStmt = $pdo->prepare(
    'INSERT INTO conversations (buyer_id, seller_id, item_id, auction_id, subject, item_url, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$msgStmt = $pdo->prepare(
    'INSERT INTO messages (conversation_id, sender_id, text, is_read, created_at) VALUES (?, ?, ?, ?, ?)'
);

$convs = [
    [
        'buyer' => 2, 'seller' => 0, 'item' => $itemId[0], 'auction' => null,
        'subject' => $items[0][1], 'url' => 'item.php?id=' . $itemId[0],
        'created' => $now - 5 * 3600,
        'messages' => [
            ['sender' => 2, 'read' => 1, 'rel' => -5 * 3600, 'text' => 'Здравствуйте! Коляска ещё актуальна?'],
            ['sender' => 0, 'read' => 1, 'rel' => -4 * 3600, 'text' => 'Добрый день! Да, свободна. Можно посмотреть в выходные.'],
            ['sender' => 2, 'read' => 0, 'rel' => -30 * 60, 'text' => 'Отлично! А матрац правда в подарок?'],
        ],
    ],
    [
        'buyer' => 1, 'seller' => 0, 'item' => null, 'auction' => $auctionId[0],
        'subject' => $auctions[0][0], 'url' => 'auction.php?id=' . $auctionId[0],
        'created' => $now - 2 * 86400,
        'messages' => [
            ['sender' => 1, 'read' => 1, 'rel' => -2 * 86400, 'text' => 'Уступите, если заберу самовывозом?'],
            ['sender' => 0, 'read' => 1, 'rel' => -2 * 86400 + 2 * 3600, 'text' => 'При самовывозе отдам за 15 000 ₽.'],
        ],
    ],
    [
        'buyer' => 0, 'seller' => 1, 'item' => $itemId[1], 'auction' => null,
        'subject' => $items[1][1], 'url' => 'item.php?id=' . $itemId[1],
        'created' => $now - 86400,
        'messages' => [
            ['sender' => 0, 'read' => 1, 'rel' => -86400, 'text' => 'Здравствуйте! Размер правда 74? Сыну сейчас как раз 74.'],
            ['sender' => 1, 'read' => 1, 'rel' => -86400 + 3 * 3600, 'text' => 'Здравствуйте! Да, самый ходовой размер. Носили один сезон, состояние отличное.'],
        ],
    ],
];

foreach ($convs as $c) {
    $lastRel = end($c['messages'])['rel'];
    $convStmt->execute([
        $userId[$c['buyer']], $userId[$c['seller']], $c['item'], $c['auction'],
        $c['subject'], $c['url'],
        date('Y-m-d H:i:s', $c['created']), date('Y-m-d H:i:s', $now + $lastRel),
    ]);
    $cid = (int) $pdo->lastInsertId();
    foreach ($c['messages'] as $m) {
        $msgStmt->execute([
            $cid, $userId[$m['sender']], $m['text'], $m['read'],
            date('Y-m-d H:i:s', $now + $m['rel']),
        ]);
    }
}

echo "Сиды загружены: пользователей " . count($userId)
    . ", объявлений " . count($items)
    . ", аукционов " . (count($auctions) + 1) . "\n";
