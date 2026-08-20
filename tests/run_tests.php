<?php
declare(strict_types=1);

error_reporting(E_ALL);

require __DIR__ . '/../config.php';

$tests = 0;
$failed = 0;
$skipped = 0;

function check(string $name, bool $cond, string $detail = ''): void
{
    global $tests, $failed;
    $tests++;
    if ($cond) {
        echo "  OK    $name\n";
    } else {
        $failed++;
        echo "  FAIL  $name" . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    }
}

function assert_eq(mixed $got, mixed $expected, string $name): void
{
    check($name, $got === $expected, 'получено ' . var_export($got, true) . ', ожидалось ' . var_export($expected, true));
}

function section(string $title): void
{
    echo "\n== $title\n";
}

function fresh_db(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sqlite.sql'));

    $users = [
        [1, 'Мария', 'masha@kroha.test'],
        [2, 'Светлана', 'sveta@kroha.test'],
        [3, 'Админ', 'admin@kroha.test'],
    ];
    $insUser = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, city, is_admin) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($users as $u) {
        $insUser->execute([$u[0], $u[1], $u[2], 'hash', 'Москва', $u[2] === 'admin@kroha.test' ? 1 : 0]);
    }

    $pdo->prepare('INSERT INTO items (id, user_id, title, category, price, city, status, buyer_id) VALUES (1, 1, \'Коляска\', \'Коляски\', 12000, \'Москва\', \'active\', NULL)')->execute();
    $pdo->prepare('INSERT INTO items (id, user_id, title, category, price, city, status, buyer_id) VALUES (2, 2, \'Комбинезон\', \'Одежда\', 800, \'Москва\', \'sold\', 1)')->execute();

    $pdo->prepare('INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status) VALUES (1, 1, \'Коляска-аукцион\', \'Коляски\', 100, 100, 50, 3, \'2099-01-01 00:00:00\', \'active\')')->execute();
    $pdo->prepare('INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status, winner_bid_id) VALUES (2, 2, \'Аукцион-завершён\', \'Одежда\', 50, 150, 25, 3, \'2000-01-01 00:00:00\', \'finished\', 1)')->execute();
    $pdo->prepare('INSERT INTO bids (id, auction_id, user_id, amount, created_at) VALUES (1, 2, 1, 150, \'2026-01-01 10:00:00\')')->execute();

    return $pdo;
}

// ------------------------------------------------------------------
section('Хелперы вывода');
assert_eq(e('<b>&"'), '&lt;b&gt;&amp;&quot;', 'e() экранирует спецсимволы');
assert_eq(money(12345), '12 345 ₽', 'money() форматирует тысячи');
assert_eq(money(0), '0 ₽', 'money(0)');
assert_eq(plural(1, ['товар', 'товара', 'товаров']), 'товар', 'plural 1');
assert_eq(plural(3, ['товар', 'товара', 'товаров']), 'товара', 'plural 3');
assert_eq(plural(5, ['товар', 'товара', 'товаров']), 'товаров', 'plural 5');
assert_eq(plural(21, ['товар', 'товара', 'товаров']), 'товар', 'plural 21');
assert_eq(report_reason_label('fraud'), 'Мошенничество', 'report_reason_label fraud');
assert_eq(report_reason_label('unknown'), 'Другое', 'report_reason_label default');
assert_eq(notification_type_label('report'), 'Новая жалоба', 'notification_type_label report');
assert_eq(notification_type_label('report_answered'), 'Ответ на жалобу', 'notification_type_label report_answered');
assert_eq(format_countdown(90061), '1 дн 1 ч', 'format_countdown дни');
assert_eq(format_countdown(3661), '1 ч 1 мин', 'format_countdown часы');

// ------------------------------------------------------------------
section('Жалобы на лоты');
$pdo = fresh_db();
assert_eq(report_listing($pdo, 2, 'item', 1, 'bad', ''), 'Выберите причину жалобы.', 'неверная причина отклонена');
assert_eq(report_listing($pdo, 2, 'item', 999, 'spam', ''), 'Лот не найден.', 'несуществующий лот');
assert_eq(report_listing($pdo, 1, 'item', 1, 'spam', ''), 'Нельзя пожаловаться на собственный лот.', 'жалоба на свой лот запрещена');
assert_eq(report_listing($pdo, 2, 'item', 1, 'fraud', 'Комментарий'), null, 'жалоба на объявление принята');
$cnt = (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
assert_eq($cnt, 1, 'в БД одна жалоба');
$rep = $pdo->query('SELECT * FROM reports')->fetch();
assert_eq($rep['reason'], 'fraud', 'причина сохранена');
assert_eq($rep['status'], 'new', 'статус жалобы new');
assert_eq($rep['item_id'], 1, 'привязка к item_id');
assert_eq($rep['auction_id'], null, 'auction_id пуст');
assert_eq(report_listing($pdo, 2, 'item', 1, 'spam', ''), 'Вы уже отправляли жалобу на этот лот.', 'дубликат жалобы отклонён');
assert_eq(report_listing($pdo, 2, 'auction', 1, 'rules', ''), null, 'жалоба на аукцион принята');
$rep2 = $pdo->query('SELECT * FROM reports WHERE auction_id IS NOT NULL')->fetch();
assert_eq($rep2['auction_id'], 1, 'привязка к auction_id');
$adminNotif = $pdo->query('SELECT * FROM notifications WHERE user_id = 3 AND type = \'report\'')->fetch();
assert_eq($adminNotif !== false, true, 'админ получил уведомление о жалобе');
$rep = $pdo->query('SELECT * FROM reports WHERE status = \'new\'')->fetch();
notify($pdo, (int) $rep['user_id'], 'report_answered', 'Ваша жалоба принята — лот скрыт и проверен модератором.', 'item.php?id=1');
$authorNotif = $pdo->query('SELECT * FROM notifications WHERE user_id = 2 AND type = \'report_answered\'')->fetch();
assert_eq($authorNotif !== false, true, 'автор получил уведомление о решении жалобы');
assert_eq($authorNotif['link'], 'item.php?id=1', 'ссылка в уведомлении на лот');
assert_eq($authorNotif['text'], 'Ваша жалоба принята — лот скрыт и проверен модератором.', 'текст уведомления о решении');

// ------------------------------------------------------------------
section('История просмотров');
$pdo = fresh_db();
record_view($pdo, 1, 'item', 1);
assert_eq((int) $pdo->query('SELECT COUNT(*) FROM view_history')->fetchColumn(), 0, 'владелец не пишется в историю');
record_view($pdo, 2, 'item', 1);
assert_eq((int) $pdo->query('SELECT COUNT(*) FROM view_history')->fetchColumn(), 1, 'просмотр записан');
record_view($pdo, 2, 'item', 1);
assert_eq(count(view_history($pdo, 2)), 1, 'дедупликация в истории');
record_view($pdo, 2, 'auction', 1);
assert_eq(count(view_history($pdo, 2)), 2, 'два уникальных лота');
$h = view_history($pdo, 2, 1);
assert_eq(count($h), 1, 'лимит 1');
assert_eq($h[0]['title'], 'Коляска-аукцион', 'свежий просмотр первый');
assert_eq($h[0]['photos'], null, 'photos из COALESCE');
clear_view_history($pdo, 2);
assert_eq((int) $pdo->query('SELECT COUNT(*) FROM view_history')->fetchColumn(), 0, 'история очищена');

// ------------------------------------------------------------------
section('Подтверждение сделок');
$pdo = fresh_db();
assert_eq(confirm_receipt($pdo, 2, 'item', 2), 'Подтвердить получение может только покупатель.', 'продавец не может подтвердить');
assert_eq(confirm_receipt($pdo, 1, 'item', 2), null, 'покупатель подтвердил получение');
assert_eq(confirm_receipt($pdo, 1, 'item', 2), 'Сделка уже подтверждена.', 'повторное подтверждение');
assert_eq(confirm_receipt($pdo, 2, 'item', 999), 'Лот не найден.', 'несуществующий лот');
assert_eq(confirm_receipt($pdo, 1, 'auction', 2), null, 'победитель аукциона подтвердил');
assert_eq(confirm_receipt($pdo, 1, 'auction', 2), 'Сделка уже подтверждена.', 'аукцион повторно подтверждён');
$itemSold = $pdo->query('SELECT confirmed_at FROM items WHERE id = 2')->fetchColumn();
assert_eq($itemSold !== null && $itemSold !== '', true, 'confirmed_at установлен у объявления');
$aucFin = $pdo->query('SELECT confirmed_at FROM auctions WHERE id = 2')->fetchColumn();
assert_eq($aucFin !== null && $aucFin !== '', true, 'confirmed_at установлен у аукциона');

// ------------------------------------------------------------------
section('Уведомления');
$pdo = fresh_db();
assert_eq(unread_notifications_count($pdo, 2), 0, 'нет непрочитанных');
notify($pdo, 2, 'outbid', 'Вашу ставку перебили.', 'auction.php?id=1');
assert_eq(unread_notifications_count($pdo, 2), 1, 'уведомление посчитано');
$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
$stmt->execute([2]);
assert_eq(unread_notifications_count($pdo, 2), 0, 'прочитанное не считается');

// ------------------------------------------------------------------
section('Продажи и счётчик «продано»');
$pdo = fresh_db();
$pdo->prepare('INSERT INTO conversations (buyer_id, seller_id, item_id, subject) VALUES (2, 1, 1, \'Про коляску\')')->execute();
assert_eq(mark_item_sold($pdo, 1, 999, 2), 'Объявление не найдено.', 'несуществующий лот');
assert_eq(mark_item_sold($pdo, 2, 1, 2), 'Объявление не найдено.', 'отметить может только владелец');
assert_eq(mark_item_sold($pdo, 1, 1, 555), null, 'лот продан');
assert_eq($pdo->query('SELECT buyer_id FROM items WHERE id = 1')->fetchColumn(), null, 'покупатель вне переписки не записан');
assert_eq((int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 0, 'посторонний покупатель не уведомлён');
assert_eq($pdo->query('SELECT status FROM items WHERE id = 1')->fetchColumn(), 'sold', 'статус объявления — sold');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 1')->fetchColumn(), 1, 'sold_count продавца вырос до 1');
assert_eq(mark_item_sold($pdo, 1, 1, 2), 'Отметить как «Продано» можно только активное объявление.', 'повторная продажа отклонена');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 1')->fetchColumn(), 1, 'sold_count не вырос повторно');

$pdo = fresh_db();
$pdo->prepare('INSERT INTO conversations (buyer_id, seller_id, item_id, subject) VALUES (2, 1, 1, \'Про коляску\')')->execute();
assert_eq(mark_item_sold($pdo, 1, 1, 2), null, 'лот продан покупателю из переписки');
$n = $pdo->query('SELECT * FROM notifications WHERE type = \'bought\'')->fetch();
assert_eq($n !== false, true, 'покупатель получил уведомление');
assert_eq((int) $n['user_id'], 2, 'уведомление уходит покупателю');
assert_eq($n['link'], 'item.php?id=1', 'ссылка ведёт на лот');
assert_eq(notification_type_label('bought'), 'Лот куплен', 'тип уведомления «Лот куплен»');

$pdo = fresh_db();
assert_eq(mark_item_sold($pdo, 1, 1, 0), null, 'продано без покупателя');
assert_eq($pdo->query('SELECT buyer_id FROM items WHERE id = 1')->fetchColumn(), null, 'без покупателя buyer_id пуст');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 1')->fetchColumn(), 1, 'счётчик растёт и без покупателя');
assert_eq((int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(), 0, 'без покупателя уведомлений нет');

// ------------------------------------------------------------------
section('Финализация аукционов');
$pdo = fresh_db();
assert_eq(finalize_auction($pdo, 1), null, 'активный незавершённый аукцион не трогаем');
assert_eq(finalize_auction($pdo, 999), null, 'несуществующий аукцион');
$pdo->prepare("INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status) VALUES (3, 2, 'Коляска-финал', 'Коляски', 100, 200, 50, 3, '2000-01-01 00:00:00', 'active')")->execute();
$pdo->prepare("INSERT INTO bids (id, auction_id, user_id, amount, created_at) VALUES (2, 3, 1, 200, '2026-01-01 10:00:00')")->execute();
$res = finalize_auction($pdo, 3);
assert_eq($res !== null, true, 'аукцион финализирован');
assert_eq($res['status'], 'finished', 'статус finished');
$win = $pdo->query('SELECT * FROM notifications WHERE type = \'win\' AND user_id = 1')->fetch();
assert_eq($win !== false, true, 'победитель уведомлён');
assert_eq($win['link'], 'auction.php?id=3', 'ссылка ведёт на аукцион');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 2')->fetchColumn(), 1, 'sold_count продавца вырос');
assert_eq(finalize_auction($pdo, 3), null, 'повторная финализация не проходит');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 2')->fetchColumn(), 1, 'sold_count не вырос повторно');

$pdo = fresh_db();
$pdo->prepare("INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status) VALUES (3, 2, 'Пустой аукцион', 'Коляски', 100, 100, 50, 3, '2000-01-01 00:00:00', 'active')")->execute();
$res = finalize_auction($pdo, 3);
assert_eq($res !== null && $res['winner_bid_id'] === null, true, 'финализация без победителя');
$un = $pdo->query('SELECT * FROM notifications WHERE type = \'unsold\' AND user_id = 2')->fetch();
assert_eq($un !== false, true, 'продавцу уведомление о непродаже');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 2')->fetchColumn(), 0, 'sold_count не растёт без победителя');

// ------------------------------------------------------------------
section('Фоновая финализация (поллер)');
$pdo = fresh_db();
$pdo->prepare("INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status) VALUES (3, 2, 'Просроченный', 'Коляски', 100, 100, 50, 3, '2000-01-01 00:00:00', 'active')")->execute();
$pdo->prepare("INSERT INTO bids (id, auction_id, user_id, amount, created_at) VALUES (2, 3, 1, 200, '2026-01-01 10:00:00')")->execute();
$pdo->prepare("INSERT INTO auctions (id, user_id, title, category, start_price, current_price, min_bid_step, duration_days, end_at, status) VALUES (4, 2, 'Время ещё есть', 'Коляски', 100, 100, 50, 3, '2099-01-01 00:00:00', 'active')")->execute();
assert_eq(finalize_due_auctions($pdo), 1, 'финализирован только просроченный');
assert_eq($pdo->query('SELECT status FROM auctions WHERE id = 3')->fetchColumn(), 'finished', 'просроченный завершён');
assert_eq($pdo->query('SELECT status FROM auctions WHERE id = 4')->fetchColumn(), 'active', 'будущий не тронут');
assert_eq(finalize_due_auctions($pdo), 0, 'повторный вызов ничего не делает');
$win = $pdo->query('SELECT * FROM notifications WHERE type = \'win\' AND user_id = 1')->fetch();
assert_eq($win !== false, true, 'победитель уведомлён');
assert_eq((int) $pdo->query('SELECT sold_count FROM users WHERE id = 2')->fetchColumn(), 1, 'sold_count продавца вырос');

// ------------------------------------------------------------------
section('Восстановление пароля');
$pdo = fresh_db();
$hashBefore = $pdo->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
$token1 = create_password_reset($pdo, 1);
assert_eq(strlen($token1) >= 32, true, 'токен создан');
$row = $pdo->query('SELECT * FROM password_resets WHERE token = \'' . $token1 . '\'')->fetch();
assert_eq($row !== false && (int) $row['user_id'] === 1, true, 'токен сохранён для нужного пользователя');
assert_eq(password_reset_by_token($pdo, $token1) !== null, true, 'действующий токен находится');
$token2 = create_password_reset($pdo, 1);
assert_eq($pdo->query('SELECT COUNT(*) FROM password_resets WHERE user_id = 1 AND used_at IS NULL')->fetchColumn(), 1, 'старый неиспользованный токен заменён');
assert_eq(password_reset_by_token($pdo, $token1), null, 'старый токен больше не действует');
assert_eq(complete_password_reset($pdo, $token2, 'sovershenno-novyj-parol'), true, 'сброс пароля прошёл');
$hashAfter = $pdo->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
assert_eq($hashBefore !== $hashAfter && password_verify('sovershenno-novyj-parol', $hashAfter), true, 'пароль изменён и проверяется');
assert_eq(complete_password_reset($pdo, $token2, 'eshche-odin'), false, 'токен одноразовый');
assert_eq(password_reset_by_token($pdo, 'no-such-token'), null, 'несуществующий токен отклонён');
$pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (1, \'expired-token\', \'2000-01-01 00:00:00\')')->execute();
assert_eq(password_reset_by_token($pdo, 'expired-token'), null, 'просроченный токен отклонён');
assert_eq(complete_password_reset($pdo, 'expired-token', 'xoroshiy-parol'), false, 'просроченный токен не срабатывает');

// ------------------------------------------------------------------
section('Смоук по HTTP (опционально)');
$host = 'http://127.0.0.1:8091';
$ctx = stream_context_create(['http' => ['timeout' => 2]]);
$page = @file_get_contents($host . '/', false, $ctx);
if ($page === false) {
    $skipped++;
    echo "  SKIP  сервер не запущен на $host\n";
} else {
    check('главная отдаёт HTML', str_contains($page, 'Кроха'));
    $poll = file_get_contents($host . '/notifications_poll.php', false, $ctx);
    $data = json_decode((string) $poll, true);
    assert_eq(($data['ok'] ?? null), false, 'poll без авторизации => ok=false');
}

echo "\n----------------------------------------\n";
echo "Тестов: $tests, пропущено: $skipped, упало: $failed\n";
exit($failed === 0 ? 0 : 1);
