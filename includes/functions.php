<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(int $rub): string
{
    return number_format($rub, 0, ',', ' ') . ' ₽';
}

function placeholder_photo(string $label, string $color = '#C96F4A'): string
{
    $label = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="450">'
        . '<rect width="600" height="450" fill="#FBF4E8"/>'
        . '<text x="50%" y="50%" font-family="Nunito, sans-serif" font-size="110" fill="' . $color . '" text-anchor="middle" dominant-baseline="central">'
        . $label
        . '</text></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function photos_of(array $row): array
{
    $raw = (string) ($row['photos'] ?? '');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function first_photo(array $row): string
{
    $photos = photos_of($row);
    return $photos[0] ?? placeholder_photo('Кроха');
}

function make_thumb(string $file, string $thumb, int $max = 640): bool
{
    if (!is_file($file) || !function_exists('imagecreatetruecolor')) {
        return false;
    }
    $info = @getimagesize($file);
    if ($info === false) {
        return false;
    }
    [$w, $h] = $info;
    if ($w <= $max && $h <= $max) {
        return @copy($file, $thumb);
    }
    $scale = min($max / $w, $max / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $src = match ($info['mime']) {
        'image/jpeg' => @imagecreatefromjpeg($file),
        'image/png' => @imagecreatefrompng($file),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
        default => false,
    };
    if ($src === false) {
        return false;
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if ($info['mime'] === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = match ($info['mime']) {
        'image/jpeg' => imagejpeg($dst, $thumb, 82),
        'image/png' => imagepng($dst, $thumb, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($dst, $thumb, 82) : false,
        default => false,
    };
    imagedestroy($src);
    imagedestroy($dst);
    return (bool) $ok;
}

function thumb_of(string $photo): string
{
    if (!str_starts_with($photo, 'uploads/')) {
        return $photo;
    }
    $t = 'uploads/t_' . basename($photo);
    return is_file(__DIR__ . '/../' . $t) ? $t : $photo;
}

function card_photo(array $row): string
{
    $photos = photos_of($row);
    if (!$photos) {
        return placeholder_photo('Кроха');
    }
    return thumb_of($photos[0]);
}

function condition_is_new(?string $label): bool
{
    if ($label === null) {
        return false;
    }
    $l = mb_strtolower(trim($label));
    return in_array($l, ['новое', 'новый', 'новая', 'как новое', 'как новый', 'как новая'], true);
}

function seconds_until(?string $endAt): int
{
    if ($endAt === null || $endAt === '') {
        return 0;
    }
    $end = strtotime($endAt);
    return max(0, $end - time());
}

function format_countdown(int $seconds): string
{
    if ($seconds <= 0) {
        return 'завершён';
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $mins = intdiv($seconds % 3600, 60);
    if ($days > 0) {
        return $days . ' дн ' . $hours . ' ч';
    }
    if ($hours > 0) {
        return $hours . ' ч ' . $mins . ' мин';
    }
    return $mins . ' мин';
}

function categories(): array
{
    return ['Коляски', 'Комбинезоны и верхняя одежда', 'Игрушки', 'Школьное', 'Мебель', 'Одежда'];
}

function plural(int $n, array $forms): string
{
    $n10 = $n % 10;
    $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) {
        return $forms[0];
    }
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
        return $forms[1];
    }
    return $forms[2];
}

function age_range(array $row): ?string
{
    $min = $row['age_min'] ?? null;
    $max = $row['age_max'] ?? null;
    if ($min === null && $max === null) {
        return null;
    }
    if ($min !== null && $max !== null) {
        return $min . '–' . $max . ' лет';
    }
    return $min !== null ? 'от ' . $min . ' лет' : 'до ' . $max . ' лет';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name));
    $res = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') {
            $res .= mb_strtoupper(mb_substr($p, 0, 1));
        }
    }
    return $res !== '' ? $res : '?';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $savePath = session_save_path();
    if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
        $fallback = sys_get_temp_dir() . '/kroha-sessions';
        if (!is_dir($fallback)) {
            mkdir($fallback, 0777, true);
        }
        session_save_path($fallback);
    }
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    $token = (string) ($_POST['csrf'] ?? '');
    start_session();
    if ($token === '') {
        return false;
    }
    return hash_equals((string) ($_SESSION['csrf'] ?? ''), $token);
}

function user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function current_user(): ?array
{
    start_session();
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id === 0) {
        return null;
    }
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    if ((int) $user['is_banned'] === 1) {
        logout_user();
        return null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(int $userId): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['login_fails'], $_SESSION['login_lock']);
}

function logout_user(): void
{
    start_session();
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function require_login(): void
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        $next = isset($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
        header('Location: login.php' . ($next !== '' ? '?next=' . $next : ''));
        exit;
    }
}

function require_admin(): void
{
    require_login();
    $user = current_user();
    if (!$user || (int) $user['is_admin'] !== 1) {
        http_response_code(403);
        $pageTitle = 'Доступ запрещён — ' . APP_NAME;
        $pdo = pdo();
        require __DIR__ . '/header.php';
        echo '<section class="not-found"><h1>Доступ запрещён</h1><p class="empty">Эта страница только для администраторов.</p><p><a class="btn btn-primary" href="index.php?type=all">Вернуться в каталог</a></p></section>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function current_user_id(): int
{
    $user = current_user();
    return $user ? (int) $user['id'] : 0;
}

function create_password_reset(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
        ->execute([$userId]);
    $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
        ->execute([$userId, $token, date('Y-m-d H:i:s', time() + 3600)]);
    return $token;
}

function password_reset_by_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row === false || $row['used_at'] !== null || strtotime((string) $row['expires_at']) <= time()) {
        return null;
    }
    return $row;
}

function complete_password_reset(PDO $pdo, string $token, string $password): bool
{
    $row = password_reset_by_token($pdo, $token);
    if ($row === null) {
        return false;
    }
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
    $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), (int) $row['id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id != ?')
        ->execute([(int) $row['user_id'], (int) $row['id']]);
    return true;
}

function save_photos(): array
{
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $saved = [];
    $errors = [];
    if (empty($_FILES['photos']['name'][0])) {
        return [$saved, $errors];
    }
    if (count($_FILES['photos']['name']) > 8) {
        return [[], ['Можно загрузить не больше 8 фотографий.']];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    foreach ($_FILES['photos']['error'] as $i => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Ошибка загрузки файла №' . ($i + 1) . '.';
            continue;
        }
        if ($_FILES['photos']['size'][$i] > 5 * 1024 * 1024) {
            $errors[] = 'Файл №' . ($i + 1) . ' больше 5 МБ.';
            continue;
        }
        $info = @getimagesize($_FILES['photos']['tmp_name'][$i]);
        if ($info === false || !isset($allowed[$info['mime']])) {
            $errors[] = 'Файл №' . ($i + 1) . ' — не картинка. Нужен JPG, PNG или WebP.';
            continue;
        }
        $name = bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
        if (!move_uploaded_file($_FILES['photos']['tmp_name'][$i], $uploadDir . '/' . $name)) {
            $errors[] = 'Не удалось сохранить файл №' . ($i + 1) . '.';
            continue;
        }
        make_thumb($uploadDir . '/' . $name, $uploadDir . '/t_' . $name);
        $saved[] = 'uploads/' . $name;
    }

    return [$saved, $errors];
}

function auction_offers(PDO $pdo, int $auctionId): array
{
    $stmt = $pdo->prepare(
        'SELECT user_id, bidder_name, bidder_city, MAX(eff) AS eff
         FROM (
            SELECT b.user_id, u.name AS bidder_name, u.city AS bidder_city,
                   (CASE WHEN b.proxy_limit IS NOT NULL AND b.proxy_limit > b.amount THEN b.proxy_limit ELSE b.amount END) AS eff
            FROM bids b JOIN users u ON u.id = b.user_id
            WHERE b.auction_id = ?
         ) t
         GROUP BY user_id
         ORDER BY eff DESC, user_id ASC'
    );
    $stmt->execute([$auctionId]);
    return $stmt->fetchAll();
}

function auction_state(PDO $pdo, array $auction): array
{
    $offers = auction_offers($pdo, (int) $auction['id']);
    $step = max(1, (int) $auction['min_bid_step']);
    $start = (int) $auction['start_price'];
    $price = $start;
    $leader = null;

    if ($offers) {
        $leader = $offers[0];
        if (count($offers) === 1) {
            $price = max($start, (int) $offers[0]['eff']);
        } else {
            $price = min((int) $offers[0]['eff'], (int) $offers[1]['eff'] + $step);
            $price = max($price, $start);
        }
    }

    $price = max($price, (int) $auction['current_price']);

    return [
        'offers' => $offers,
        'price' => $price,
        'leader' => $leader,
    ];
}

function auction_leader_bid(PDO $pdo, int $auctionId, int $userId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM bids WHERE auction_id = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1'
    );
    $stmt->execute([$auctionId, $userId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function finalize_auction(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM auctions WHERE id = ?');
    $stmt->execute([$id]);
    $auction = $stmt->fetch();
    if ($auction === false) {
        return null;
    }
    if ($auction['status'] !== 'active' || strtotime($auction['end_at']) > time()) {
        return null;
    }

    $state = auction_state($pdo, $auction);
    $winnerId = null;
    if ($state['leader']) {
        $winnerId = auction_leader_bid($pdo, $id, (int) $state['leader']['user_id']);
    }
    $pdo->prepare('UPDATE auctions SET status = \'finished\', winner_bid_id = ?, current_price = ? WHERE id = ?')
        ->execute([$winnerId, $state['price'], $id]);
    $auction['status'] = 'finished';
    $auction['winner_bid_id'] = $winnerId;
    $auction['current_price'] = $state['price'];

    if ($winnerId) {
        $win = $pdo->prepare('SELECT user_id FROM bids WHERE id = ?');
        $win->execute([$winnerId]);
        $winnerUserId = (int) $win->fetch()['user_id'];
        notify(
            $pdo,
            $winnerUserId,
            'win',
            'Вы выиграли лот «' . $auction['title'] . '» за ' . money((int) $state['price']) . '!',
            'auction.php?id=' . $id
        );
        bump_sold_count($pdo, (int) $auction['user_id']);
    } elseif (!$state['leader']) {
        notify(
            $pdo,
            (int) $auction['user_id'],
            'unsold',
            'Лот «' . $auction['title'] . '» завершился без ставок — лот не продан.',
            'auction.php?id=' . $id
        );
    }
    return $auction;
}

function finalize_due_auctions(PDO $pdo, int $limit = 20): int
{    $stmt = $pdo->prepare(
        'SELECT id FROM auctions WHERE status = \'active\' AND end_at <= ? ORDER BY end_at ASC LIMIT ?'
    );
    $stmt->bindValue(1, date('Y-m-d H:i:s'));
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (finalize_auction($pdo, (int) $row['id']) !== null) {
            $count++;
        }
    }
    return $count;
}

function notify(PDO $pdo, int $userId, string $type, string $text, string $link = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, text, link, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $text, $link, date('Y-m-d H:i:s')]);
}

function notify_admins(PDO $pdo, string $type, string $text, string $link = ''): void
{
    $stmt = $pdo->query('SELECT id FROM users WHERE is_admin = 1');
    foreach ($stmt->fetchAll() as $admin) {
        notify($pdo, (int) $admin['id'], $type, $text, $link);
    }
}

function bump_sold_count(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE users SET sold_count = sold_count + 1 WHERE id = ?')->execute([$userId]);
}

function unread_notifications_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function notification_type_label(string $type): string
{
    return match ($type) {
        'outbid' => 'Перебита ставка',
        'win' => 'Выигрыш',
        'bought' => 'Лот куплен',
        'unsold' => 'Лот не продан',
        'review' => 'Новый отзыв',
        'confirmed' => 'Сделка подтверждена',
        'report' => 'Новая жалоба',
        'report_answered' => 'Ответ на жалобу',
        'search' => 'Сохранённый поиск',
        default => $type,
    };
}

function notification_icon(string $type): string
{
    $svg = static fn(string $inner): string =>
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';

    return match ($type) {
        'outbid' => $svg('<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>'),
        'win' => $svg('<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>'),
        'bought' => $svg('<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'),
        'unsold' => $svg('<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'),
        'review' => $svg('<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'),
        'confirmed' => $svg('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
        'report' => $svg('<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>'),
        'report_answered' => $svg('<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>'),
        'search' => $svg('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'),
        default => $svg('<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'),
    };
}

function find_conversation(PDO $pdo, int $buyerId, int $sellerId, ?int $itemId, ?int $auctionId): ?array
{
    if ($itemId !== null) {
        $sql = 'SELECT * FROM conversations WHERE buyer_id = ? AND seller_id = ? AND item_id = ?';
        $params = [$buyerId, $sellerId, $itemId];
    } elseif ($auctionId !== null) {
        $sql = 'SELECT * FROM conversations WHERE buyer_id = ? AND seller_id = ? AND auction_id = ?';
        $params = [$buyerId, $sellerId, $auctionId];
    } else {
        $sql = 'SELECT * FROM conversations WHERE buyer_id = ? AND seller_id = ? AND item_id IS NULL AND auction_id IS NULL';
        $params = [$buyerId, $sellerId];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function open_conversation(PDO $pdo, int $buyerId, int $sellerId, ?int $itemId, ?int $auctionId, string $subject, string $itemUrl = ''): array
{
    $existing = find_conversation($pdo, $buyerId, $sellerId, $itemId, $auctionId);
    if ($existing) {
        return $existing;
    }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO conversations (buyer_id, seller_id, item_id, auction_id, subject, item_url, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$buyerId, $sellerId, $itemId, $auctionId, $subject, $itemUrl, $now, $now]);
    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function unread_messages_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE m.sender_id != ? AND m.is_read = 0 AND (c.buyer_id = ? OR c.seller_id = ?)'
    );
    $stmt->execute([$userId, $userId, $userId]);
    return (int) $stmt->fetchColumn();
}

function msg_time(?string $at): string
{
    if ($at === null || $at === '') {
        return '';
    }
    $ts = strtotime($at);
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return date('H:i', $ts);
    }
    if (date('Y', $ts) === date('Y')) {
        return date('d.m H:i', $ts);
    }
    return date('d.m.Y', $ts);
}

function favorite_state(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT item_id, auction_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    $itemIds = [];
    $auctionIds = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['item_id'] !== null) {
            $itemIds[] = (int) $row['item_id'];
        }
        if ($row['auction_id'] !== null) {
            $auctionIds[] = (int) $row['auction_id'];
        }
    }
    return [$itemIds, $auctionIds];
}

function toggle_favorite(PDO $pdo, int $userId, string $type, int $id): bool
{
    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND item_id = ?');
        $stmt->execute([$userId, $id]);
        if ($stmt->fetch()) {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND item_id = ?')->execute([$userId, $id]);
            return false;
        }
        $pdo->prepare('INSERT INTO favorites (user_id, item_id) VALUES (?, ?)')->execute([$userId, $id]);
        return true;
    }
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND auction_id = ?');
    $stmt->execute([$userId, $id]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND auction_id = ?')->execute([$userId, $id]);
        return false;
    }
    $pdo->prepare('INSERT INTO favorites (user_id, auction_id) VALUES (?, ?)')->execute([$userId, $id]);
    return true;
}

function item_buyers(PDO $pdo, int $itemId, int $sellerId): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT c.buyer_id, u.name AS buyer_name
         FROM conversations c JOIN users u ON u.id = c.buyer_id
         WHERE c.item_id = ? AND c.buyer_id != ?
         ORDER BY u.name'
    );
    $stmt->execute([$itemId, $sellerId]);
    return $stmt->fetchAll();
}

function mark_item_sold(PDO $pdo, int $sellerId, int $itemId, int $buyerId): ?string
{
    $stmt = $pdo->prepare('SELECT id, user_id, status, title FROM items WHERE id = ? AND user_id = ?');
    $stmt->execute([$itemId, $sellerId]);
    $item = $stmt->fetch();

    if ($item === false) {
        return 'Объявление не найдено.';
    }
    if ($item['status'] !== 'active') {
        return 'Отметить как «Продано» можно только активное объявление.';
    }

    $buyerId = $buyerId > 0 ? $buyerId : null;
    $validBuyer = false;
    foreach (item_buyers($pdo, $itemId, $sellerId) as $b) {
        if ((int) $b['buyer_id'] === $buyerId) {
            $validBuyer = true;
            break;
        }
    }
    $buyerId = $validBuyer ? $buyerId : null;

    $pdo->prepare('UPDATE items SET status = \'sold\', buyer_id = ? WHERE id = ?')
        ->execute([$buyerId, $itemId]);
    if ($buyerId !== null) {
        notify(
            $pdo,
            $buyerId,
            'bought',
            'Продавец отметил лот «' . $item['title'] . '» как проданный. Подтвердите получение и оставьте отзыв.',
            'item.php?id=' . $itemId
        );
    }
    bump_sold_count($pdo, $sellerId);
    return null;
}

function stars_html(int $rating): string
{
    $rating = max(1, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function seller_rating(PDO $pdo, int $userId): ?float
{
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM reviews WHERE user_id = ?');
    $stmt->execute([$userId]);
    $avg = $stmt->fetchColumn();
    return $avg !== null && $avg !== false ? round((float) $avg, 1) : null;
}

function rating_label(?float $rating): string
{
    return $rating === null ? 'рейтинг —' : 'рейтинг ' . (string) $rating . '/5';
}

function reviews_of(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, u.name AS author_name, u.city AS author_city,
                i.title AS item_title, a.title AS auction_title
         FROM reviews r
         JOIN users u ON u.id = r.author_id
         LEFT JOIN items i ON i.id = r.item_id
         LEFT JOIN auctions a ON a.id = r.auction_id
         WHERE r.user_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function reviews_by(PDO $pdo, int $authorId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, u.name AS seller_name, u.city AS seller_city,
                i.title AS item_title, a.title AS auction_title
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN items i ON i.id = r.item_id
         LEFT JOIN auctions a ON a.id = r.auction_id
         WHERE r.author_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $stmt->execute([$authorId]);
    return $stmt->fetchAll();
}

function pending_reviews_of(PDO $pdo, int $userId): array
{
    $res = [];

    $items = $pdo->prepare(
        'SELECT i.id, i.title, i.created_at, u.name AS seller_name, u.city AS seller_city
         FROM items i
         JOIN users u ON u.id = i.user_id
         WHERE i.status = \'sold\' AND i.buyer_id = ? AND i.user_id != ?
           AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.author_id = ? AND r.item_id = i.id)
         ORDER BY i.created_at DESC, i.id DESC'
    );
    $items->execute([$userId, $userId, $userId]);
    foreach ($items->fetchAll() as $row) {
        $res[] = [
            'type' => 'item',
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'seller_name' => $row['seller_name'],
            'seller_city' => $row['seller_city'],
            'created_at' => $row['created_at'],
        ];
    }

    $auctions = $pdo->prepare(
        'SELECT a.id, a.title, a.created_at, u.name AS seller_name, u.city AS seller_city
         FROM auctions a
         JOIN bids b ON b.id = a.winner_bid_id
         JOIN users u ON u.id = a.user_id
         WHERE a.status = \'finished\' AND b.user_id = ? AND a.user_id != ?
           AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.author_id = ? AND r.auction_id = a.id)
         ORDER BY a.created_at DESC, a.id DESC'
    );
    $auctions->execute([$userId, $userId, $userId]);
    foreach ($auctions->fetchAll() as $row) {
        $res[] = [
            'type' => 'auction',
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'seller_name' => $row['seller_name'],
            'seller_city' => $row['seller_city'],
            'created_at' => $row['created_at'],
        ];
    }

    usort($res, static fn(array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
    return $res;
}

function can_review(PDO $pdo, int $authorId, string $type, int $refId): bool
{
    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT id FROM reviews WHERE author_id = ? AND item_id = ?');
        $stmt->execute([$authorId, $refId]);
        if ($stmt->fetch()) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT user_id, status, buyer_id FROM items WHERE id = ?');
        $stmt->execute([$refId]);
        $item = $stmt->fetch();
        return $item !== false
            && $item['status'] === 'sold'
            && (int) $item['buyer_id'] === $authorId
            && (int) $item['user_id'] !== $authorId;
    }
    $stmt = $pdo->prepare('SELECT id FROM reviews WHERE author_id = ? AND auction_id = ?');
    $stmt->execute([$authorId, $refId]);
    if ($stmt->fetch()) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT user_id, status, winner_bid_id FROM auctions WHERE id = ?');
    $stmt->execute([$refId]);
    $auction = $stmt->fetch();
    if ($auction === false || $auction['status'] !== 'finished' || (int) $auction['winner_bid_id'] <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT user_id FROM bids WHERE id = ?');
    $stmt->execute([(int) $auction['winner_bid_id']]);
    $winnerUserId = (int) $stmt->fetch()['user_id'];
    return $winnerUserId === $authorId && (int) $auction['user_id'] !== $authorId;
}

function add_review(PDO $pdo, int $authorId, string $type, int $refId, int $rating, string $text): ?string
{
    if ($rating < 1 || $rating > 5) {
        return 'Оценка должна быть от 1 до 5.';
    }
    $text = trim($text);
    if (mb_strlen($text) > 500) {
        return 'Текст отзыва — не длиннее 500 символов.';
    }
    if (!can_review($pdo, $authorId, $type, $refId)) {
        return 'Отзыв можно оставить только после завершённой сделки.';
    }

    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT user_id FROM items WHERE id = ?');
        $stmt->execute([$refId]);
        $sellerId = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM auctions WHERE id = ?');
        $stmt->execute([$refId]);
        $sellerId = (int) $stmt->fetchColumn();
    }

    $sql = $type === 'item'
        ? 'INSERT INTO reviews (user_id, author_id, item_id, rating, text, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        : 'INSERT INTO reviews (user_id, author_id, auction_id, rating, text, created_at) VALUES (?, ?, ?, ?, ?, ?)';
    $col = $type === 'item' ? 'item_id' : 'auction_id';
    $pdo->prepare($sql)->execute([$sellerId, $authorId, $refId, $rating, $text !== '' ? $text : null, date('Y-m-d H:i:s')]);

    $stmt = $pdo->prepare('SELECT title FROM ' . ($type === 'item' ? 'items' : 'auctions') . ' WHERE id = ?');
    $stmt->execute([$refId]);
    $title = (string) $stmt->fetchColumn();

    notify(
        $pdo,
        $sellerId,
        'review',
        'Покупатель оставил вам отзыв: ' . $rating . '/5 по лоту «' . $title . '».',
        $type === 'item' ? 'item.php?id=' . $refId : 'auction.php?id=' . $refId
    );
    return null;
}

function confirm_receipt(PDO $pdo, int $userId, string $type, int $refId): ?string
{
    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT user_id, status, buyer_id, title, confirmed_at FROM items WHERE id = ?');
        $stmt->execute([$refId]);
        $row = $stmt->fetch();
        $table = 'items';
        $link = 'item.php?id=' . $refId;
    } else {
        $stmt = $pdo->prepare('SELECT a.user_id, a.status, a.title, a.confirmed_at, b.user_id AS winner_id
                               FROM auctions a LEFT JOIN bids b ON b.id = a.winner_bid_id
                               WHERE a.id = ?');
        $stmt->execute([$refId]);
        $row = $stmt->fetch();
        $table = 'auctions';
        $link = 'auction.php?id=' . $refId;
    }

    if ($row === false) {
        return 'Лот не найден.';
    }
    if ($row['confirmed_at'] !== null && $row['confirmed_at'] !== '') {
        return 'Сделка уже подтверждена.';
    }
    if ($type === 'item') {
        $isBuyer = $row['status'] === 'sold' && (int) $row['buyer_id'] === $userId && (int) $row['user_id'] !== $userId;
    } else {
        $isBuyer = $row['status'] === 'finished' && (int) $row['winner_id'] === $userId && (int) $row['user_id'] !== $userId;
    }
    if (!$isBuyer) {
        return 'Подтвердить получение может только покупатель.';
    }

    $pdo->prepare('UPDATE ' . $table . ' SET confirmed_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $refId]);
    notify(
        $pdo,
        (int) $row['user_id'],
        'confirmed',
        'Покупатель подтвердил получение лота «' . $row['title'] . '».',
        $link
    );
    return null;
}

function report_listing(PDO $pdo, int $userId, string $type, int $refId, string $reason, string $comment): ?string
{
    $allowedReasons = ['spam', 'fraud', 'rules', 'wrong', 'other'];
    if (!in_array($reason, $allowedReasons, true)) {
        return 'Выберите причину жалобы.';
    }
    $comment = trim($comment);

    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT user_id, title FROM items WHERE id = ?');
        $stmt->execute([$refId]);
        $row = $stmt->fetch();
        $title = $row['title'] ?? '';
        $link = 'item.php?id=' . $refId;
        $check = 'item_id';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, title FROM auctions WHERE id = ?');
        $stmt->execute([$refId]);
        $row = $stmt->fetch();
        $title = $row['title'] ?? '';
        $link = 'auction.php?id=' . $refId;
        $check = 'auction_id';
    }

    if ($row === false) {
        return 'Лот не найден.';
    }
    if ((int) $row['user_id'] === $userId) {
        return 'Нельзя пожаловаться на собственный лот.';
    }

    $dup = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND {$check} = ?");
    $dup->execute([$userId, $refId]);
    if ((int) $dup->fetchColumn() > 0) {
        return 'Вы уже отправляли жалобу на этот лот.';
    }

    $ins = $pdo->prepare('INSERT INTO reports (user_id, item_id, auction_id, reason, comment, status, created_at)
                          VALUES (?, ?, ?, ?, ?, \'new\', ?)');
    if ($type === 'item') {
        $ins->execute([$userId, $refId, null, $reason, $comment !== '' ? $comment : null, date('Y-m-d H:i:s')]);
    } else {
        $ins->execute([$userId, null, $refId, $reason, $comment !== '' ? $comment : null, date('Y-m-d H:i:s')]);
    }
    notify_admins(
        $pdo,
        'report',
        'Жалоба на лот «' . $title . '»: ' . report_reason_label($reason) . '.',
        'admin.php?tab=reports'
    );
    return null;
}

function report_reason_label(string $reason): string
{
    return match ($reason) {
        'spam' => 'Спам',
        'fraud' => 'Мошенничество',
        'rules' => 'Нарушение правил',
        'wrong' => 'Неверные данные',
        default => 'Другое',
    };
}

function record_view(PDO $pdo, int $userId, string $type, int $refId): void
{
    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT user_id FROM items WHERE id = ?');
        $stmt->execute([$refId]);
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM auctions WHERE id = ?');
        $stmt->execute([$refId]);
    }
    $ownerId = (int) $stmt->fetchColumn();
    if ($ownerId === $userId) {
        return;
    }
    $ins = $pdo->prepare('INSERT INTO view_history (user_id, item_id, auction_id, viewed_at)
                          VALUES (?, ?, ?, ?)');
    if ($type === 'item') {
        $ins->execute([$userId, $refId, null, date('Y-m-d H:i:s')]);
    } else {
        $ins->execute([$userId, null, $refId, date('Y-m-d H:i:s')]);
    }
}

function view_history(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT vh.*,
                COALESCE(i.title, a.title) AS title,
                COALESCE(i.price, a.current_price) AS price,
                COALESCE(i.status, a.status) AS status,
                COALESCE(i.created_at, a.created_at) AS created_at,
                COALESCE(i.photos, a.photos) AS photos
         FROM view_history vh
         LEFT JOIN items i ON i.id = vh.item_id
         LEFT JOIN auctions a ON a.id = vh.auction_id
         WHERE vh.user_id = ?
         ORDER BY vh.id DESC'
    );
    $stmt->execute([$userId]);
    $seen = [];
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['item_id'] !== null ? 'i' . $row['item_id'] : 'a' . $row['auction_id'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = $row;
        if (count($result) >= $limit) {
            break;
        }
    }
    return $result;
}

function clear_view_history(PDO $pdo, int $userId): void
{
    $pdo->prepare('DELETE FROM view_history WHERE user_id = ?')->execute([$userId]);
}

function slugify(string $title): string
{
    $translit = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $t = strtr(mb_strtolower(trim($title)), $translit);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim($t, '-');
}

function lot_url(string $kind, int $id, string $title): string
{
    $base = $kind === 'item' ? 'item' : 'auction';
    $slug = mb_substr(slugify($title), 0, 60);
    return '/' . $base . '/' . $id . ($slug !== '' ? '-' . $slug : '');
}

function parse_pretty_lot_path(string $path): ?array
{
    if (!preg_match('#^/(item|auction)/(\d+)(?:-[^/]*)?$#', $path, $m)) {
        return null;
    }
    return ['kind' => $m[1], 'id' => (int) $m[2]];
}

function maybe_redirect_pretty(string $kind, int $id, string $title): void
{
    if (($_GET['pretty'] ?? '') === '1') {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== $kind . '.php') {
        return;
    }
    $params = $_GET;
    unset($params['id']);
    $qs = http_build_query($params);
    header('Location: ' . lot_url($kind, $id, $title) . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}

function seller_ratings_map(PDO $pdo, array $userIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $i): bool => $i > 0)));
    if ($ids === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT user_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS cnt
         FROM reviews WHERE user_id IN ($in) GROUP BY user_id"
    );
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['user_id']] = ['avg' => (float) $row['avg_rating'], 'cnt' => (int) $row['cnt']];
    }
    return $map;
}

function saved_search_filter_keys(): array
{
    return ['q', 'category', 'age_min', 'age_max', 'size', 'season', 'city', 'price_min', 'price_max', 'used'];
}

function describe_search(array $f): string
{
    $parts = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $parts[] = '«' . $q . '»';
    }
    if (($f['category'] ?? '') !== '') {
        $parts[] = (string) $f['category'];
    }
    if (($f['size'] ?? '') !== '') {
        $parts[] = 'размер ' . $f['size'];
    }
    if (($f['season'] ?? '') !== '') {
        $parts[] = (string) $f['season'];
    }
    if (($f['city'] ?? '') !== '') {
        $parts[] = (string) $f['city'];
    }
    $ageMin = (string) ($f['age_min'] ?? '');
    $ageMax = (string) ($f['age_max'] ?? '');
    if ($ageMin !== '' || $ageMax !== '') {
        $parts[] = 'возраст ' . ($ageMin !== '' ? $ageMin : '0') . '–' . ($ageMax !== '' ? $ageMax : '∞');
    }
    $priceMin = (string) ($f['price_min'] ?? '');
    $priceMax = (string) ($f['price_max'] ?? '');
    if ($priceMin !== '' || $priceMax !== '') {
        $parts[] = 'цена ' . ($priceMin !== '' ? $priceMin : '0') . '–' . ($priceMax !== '' ? $priceMax : '∞') . ' ₽';
    }
    if (($f['used'] ?? '') === '1') {
        $parts[] = 'только б/у';
    }
    return $parts !== [] ? implode(' · ', $parts) : 'Все лоты';
}

function saved_search_chips(string $params): array
{
    parse_str($params, $f);
    $chips = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $chips[] = '«' . mb_strimwidth($q, 0, 24, '…') . '»';
    }
    foreach (['category', 'season', 'city'] as $key) {
        if (($f[$key] ?? '') !== '') {
            $chips[] = (string) $f[$key];
        }
    }
    if (($f['size'] ?? '') !== '') {
        $chips[] = 'размер ' . $f['size'];
    }
    $ageMin = (string) ($f['age_min'] ?? '');
    $ageMax = (string) ($f['age_max'] ?? '');
    if ($ageMin !== '' && $ageMax !== '') {
        $chips[] = $ageMin . '–' . $ageMax . ' лет';
    } elseif ($ageMin !== '') {
        $chips[] = $ageMin . '+ лет';
    } elseif ($ageMax !== '') {
        $chips[] = 'до ' . $ageMax . ' лет';
    }
    $priceMin = (string) ($f['price_min'] ?? '');
    $priceMax = (string) ($f['price_max'] ?? '');
    if ($priceMin !== '' && $priceMax !== '') {
        $chips[] = money((int) $priceMin) . ' – ' . money((int) $priceMax);
    } elseif ($priceMin !== '') {
        $chips[] = 'от ' . money((int) $priceMin);
    } elseif ($priceMax !== '') {
        $chips[] = 'до ' . money((int) $priceMax);
    }
    if (($f['used'] ?? '') === '1') {
        $chips[] = 'только б/у';
    }
    return $chips;
}

function normalize_saved_search_params(array $get): string
{
    $clean = [];
    foreach (saved_search_filter_keys() as $key) {
        $val = trim((string) ($get[$key] ?? ''));
        if ($val !== '') {
            $clean[$key] = $val;
        }
    }
    ksort($clean);
    return http_build_query($clean);
}

function search_matches_lot(array $f, array $lot): bool
{
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '' && !str_contains(mb_strtolower((string) ($lot['search_lc'] ?? '')), mb_strtolower($q))) {
        return false;
    }
    if (($f['category'] ?? '') !== '' && (string) ($lot['category'] ?? '') !== (string) $f['category']) {
        return false;
    }
    $ageMin = (string) ($f['age_min'] ?? '');
    $ageMax = (string) ($f['age_max'] ?? '');
    if ($ageMin !== '' && ($lot['age_max'] === null || (int) $lot['age_max'] < (int) $ageMin)) {
        return false;
    }
    if ($ageMax !== '' && ($lot['age_min'] === null || (int) $lot['age_min'] > (int) $ageMax)) {
        return false;
    }
    $size = trim((string) ($f['size'] ?? ''));
    if ($size !== '' && !str_contains(mb_strtolower((string) ($lot['size'] ?? '')), mb_strtolower($size))) {
        return false;
    }
    if (($f['season'] ?? '') !== '' && (string) ($lot['season'] ?? '') !== (string) $f['season']) {
        return false;
    }
    $city = trim((string) ($f['city'] ?? ''));
    if ($city !== '' && !str_contains(mb_strtolower((string) ($lot['city'] ?? '')), mb_strtolower($city))) {
        return false;
    }
    $price = (int) ($lot['price'] ?? $lot['current_price'] ?? 0);
    $priceMin = (string) ($f['price_min'] ?? '');
    $priceMax = (string) ($f['price_max'] ?? '');
    if ($priceMin !== '' && $price < (int) $priceMin) {
        return false;
    }
    if ($priceMax !== '' && $price > (int) $priceMax) {
        return false;
    }
    if (($f['used'] ?? '') === '1' && (int) ($lot['is_giveaway'] ?? 0) === 1) {
        return false;
    }
    return true;
}

function notify_saved_searches(PDO $pdo, string $kind, int $lotId): void
{
    $table = $kind === 'item' ? 'items' : 'auctions';
    $stmt = $pdo->prepare(
        "SELECT l.*, u.city AS city FROM {$table} l JOIN users u ON u.id = l.user_id WHERE l.id = ?"
    );
    $stmt->execute([$lotId]);
    $lot = $stmt->fetch();
    if ($lot === false || (string) $lot['status'] !== 'active') {
        return;
    }
    $rows = $pdo->query('SELECT id, user_id, params, label FROM saved_searches')->fetchAll();
    foreach ($rows as $ss) {
        if ((int) $ss['user_id'] === (int) $lot['user_id']) {
            continue;
        }
        parse_str((string) $ss['params'], $f);
        if (!search_matches_lot($f, $lot)) {
            continue;
        }
        notify(
            $pdo,
            (int) $ss['user_id'],
            'search',
            'Новый лот по вашему поиску «' . $ss['label'] . '»: ' . $lot['title'],
            lot_url($kind, (int) $lot['id'], (string) $lot['title'])
        );
    }
}

function saved_searches_of(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function sitemap_entries(PDO $pdo): array
{
    $entries = [
        ['loc' => '/', 'lastmod' => date('Y-m-d')],
        ['loc' => '/index.php?type=all', 'lastmod' => date('Y-m-d')],
        ['loc' => '/index.php?type=items', 'lastmod' => date('Y-m-d')],
        ['loc' => '/index.php?type=auctions', 'lastmod' => date('Y-m-d')],
        ['loc' => '/help.php', 'lastmod' => '2026-01-01'],
        ['loc' => '/policy.php', 'lastmod' => '2026-01-01'],
    ];
    $rows = $pdo->query("SELECT id, title, created_at FROM items WHERE status = 'active' ORDER BY id")->fetchAll();
    foreach ($rows as $r) {
        $entries[] = ['loc' => lot_url('item', (int) $r['id'], (string) $r['title']), 'lastmod' => substr((string) $r['created_at'], 0, 10)];
    }
    $rows = $pdo->query("SELECT id, title, created_at FROM auctions WHERE status = 'active' ORDER BY id")->fetchAll();
    foreach ($rows as $r) {
        $entries[] = ['loc' => lot_url('auction', (int) $r['id'], (string) $r['title']), 'lastmod' => substr((string) $r['created_at'], 0, 10)];
    }
    return $entries;
}
