<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_admin();

$pdo = pdo();
$me = current_user();

$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'users', 'items', 'auctions', 'reports', 'stats'], true)) {
    $tab = 'overview';
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'ban' || $action === 'unban' || $action === 'verify' || $action === 'unverify' || $action === 'promote' || $action === 'demote') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) $me['id']) {
            $set = match ($action) {
                'ban' => 'is_banned = 1',
                'unban' => 'is_banned = 0',
                'verify' => 'verified = 1',
                'unverify' => 'verified = 0',
                'promote' => 'is_moderator = 1',
                'demote' => 'is_moderator = 0',
            };
            $stmt = $pdo->prepare('UPDATE users SET ' . $set . ' WHERE id = ? AND is_admin = 0');
            $stmt->execute([$userId]);
            $flash = 'Пользователь обновлён.';
        }
    } elseif ($action === 'hide_item' || $action === 'restore_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $from = $action === 'hide_item' ? 'active' : 'archived';
            $to = $action === 'hide_item' ? 'archived' : 'active';
            $stmt = $pdo->prepare("UPDATE items SET status = ? WHERE id = ? AND status = ?");
            $stmt->execute([$to, $itemId, $from]);
            $flash = $action === 'hide_item' ? 'Объявление скрыто.' : 'Объявление снова активно.';
        }
    } elseif ($action === 'hide_auction' || $action === 'restore_auction') {
        $auctionId = (int) ($_POST['auction_id'] ?? 0);
        if ($auctionId > 0) {
            $from = $action === 'hide_auction' ? 'active' : 'cancelled';
            $to = $action === 'hide_auction' ? 'cancelled' : 'active';
            $stmt = $pdo->prepare("UPDATE auctions SET status = ? WHERE id = ? AND status = ?");
            $stmt->execute([$to, $auctionId, $from]);
            $flash = $action === 'hide_auction' ? 'Аукцион скрыт.' : 'Аукцион снова активен.';
        }
    } elseif ($action === 'resolve_report' || $action === 'dismiss_report') {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        if ($reportId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM reports WHERE id = ? AND status = \'new\'');
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();
            if ($report) {
                $reportLink = $report['item_id'] !== null
                    ? 'item.php?id=' . (int) $report['item_id']
                    : 'auction.php?id=' . (int) $report['auction_id'];
                if ($action === 'resolve_report') {
                    if ($report['item_id'] !== null) {
                        $pdo->prepare("UPDATE items SET status = 'archived' WHERE id = ? AND status = 'active'")
                            ->execute([(int) $report['item_id']]);
                    } elseif ($report['auction_id'] !== null) {
                        $pdo->prepare("UPDATE auctions SET status = 'cancelled' WHERE id = ? AND status = 'active'")
                            ->execute([(int) $report['auction_id']]);
                    }
                    notify($pdo, (int) $report['user_id'], 'report_answered', 'Ваша жалоба принята — лот скрыт и проверен модератором.', $reportLink);
                    $flash = 'Лот скрыт, жалоба принята.';
                } else {
                    notify($pdo, (int) $report['user_id'], 'report_answered', 'Ваша жалоба рассмотрена, но нарушений не найдено.', $reportLink);
                    $flash = 'Жалоба отклонена.';
                }
                $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?")
                    ->execute([$reportId]);
            }
        }
    }
    header('Location: admin.php?tab=' . urlencode($tab) . ($flash !== '' ? '&flash=' . urlencode($flash) : ''));
    exit;
}
$flash = (string) ($_GET['flash'] ?? $flash);

if ($tab === 'overview') {
    $stats = [
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'banned' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn(),
        'moderators' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_moderator = 1')->fetchColumn(),
        'items' => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
        'items_active' => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn(),
        'items_sold' => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'sold'")->fetchColumn(),
        'auctions' => (int) $pdo->query('SELECT COUNT(*) FROM auctions')->fetchColumn(),
        'auctions_active' => (int) $pdo->query("SELECT COUNT(*) FROM auctions WHERE status = 'active'")->fetchColumn(),
        'auctions_finished' => (int) $pdo->query("SELECT COUNT(*) FROM auctions WHERE status = 'finished'")->fetchColumn(),
        'bids' => (int) $pdo->query('SELECT COUNT(*) FROM bids')->fetchColumn(),
        'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
        'reviews' => (int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        'deals' => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE confirmed_at IS NOT NULL AND confirmed_at != ''")->fetchColumn()
            + (int) $pdo->query("SELECT COUNT(*) FROM auctions WHERE confirmed_at IS NOT NULL AND confirmed_at != ''")->fetchColumn(),
    ];
} elseif ($tab === 'users') {
    $users = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
} elseif ($tab === 'items') {
    $adminItems = $pdo->query(
        'SELECT i.*, u.name AS seller_name FROM items i JOIN users u ON u.id = i.user_id ORDER BY i.id DESC'
    )->fetchAll();
    $itemStatusLabels = [
        'active' => 'Активно',
        'sold' => 'Продано',
        'archived' => 'Скрыто',
    ];
} elseif ($tab === 'reports') {
    $adminReports = $pdo->query(
        'SELECT r.*, u.name AS reporter_name,
                COALESCE(i.title, a.title) AS listing_title,
                COALESCE(i.status, a.status) AS listing_status
         FROM reports r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN items i ON i.id = r.item_id
         LEFT JOIN auctions a ON a.id = r.auction_id
         ORDER BY r.status = \'new\' DESC, r.created_at DESC'
    )->fetchAll();
} elseif ($tab === 'stats') {
    $metricQueries = [
        'users' => 'SELECT date(created_at) d, COUNT(*) c FROM users GROUP BY d',
        'items' => 'SELECT date(created_at) d, COUNT(*) c FROM items GROUP BY d',
        'auctions' => 'SELECT date(created_at) d, COUNT(*) c FROM auctions GROUP BY d',
        'bids' => 'SELECT date(created_at) d, COUNT(*) c FROM bids GROUP BY d',
        'messages' => 'SELECT date(created_at) d, COUNT(*) c FROM messages GROUP BY d',
    ];
    $byDay = [];
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $byDay[$day] = ['users' => 0, 'items' => 0, 'auctions' => 0, 'bids' => 0, 'messages' => 0, 'total' => 0];
    }
    foreach ($metricQueries as $key => $sql) {
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            if (isset($byDay[$row['d']])) {
                $byDay[$row['d']][$key] = (int) $row['c'];
                $byDay[$row['d']]['total'] += (int) $row['c'];
            }
        }
    }
    $chartMax = max(1, ...array_column($byDay, 'total'));

    $topCategories = $pdo->query(
        'SELECT category, COUNT(*) c FROM (SELECT category FROM items UNION ALL SELECT category FROM auctions) t
         GROUP BY category ORDER BY c DESC LIMIT 6'
    )->fetchAll();
    $catMax = max(1, ...array_map(static fn ($r) => (int) $r['c'], $topCategories));

    $topSellers = $pdo->query(
        'SELECT u.name,
                (SELECT COUNT(*) FROM items i WHERE i.user_id = u.id AND i.status = \'sold\')
                + (SELECT COUNT(*) FROM auctions a WHERE a.user_id = u.id AND a.status = \'finished\') AS deals
         FROM users u
         ORDER BY deals DESC, u.name LIMIT 5'
    )->fetchAll();

    $topViewed = $pdo->query(
        'SELECT COALESCE(i.title, a.title) AS title, COUNT(*) AS views
         FROM view_history vh
         LEFT JOIN items i ON i.id = vh.item_id
         LEFT JOIN auctions a ON a.id = vh.auction_id
         GROUP BY vh.item_id, vh.auction_id
         ORDER BY views DESC LIMIT 5'
    )->fetchAll();

    $statsExtras = [
        'reports_new' => (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'new'")->fetchColumn(),
        'reports_total' => (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn(),
        'views_total' => (int) $pdo->query('SELECT COUNT(*) FROM view_history')->fetchColumn(),
        'favorites' => (int) $pdo->query('SELECT COUNT(*) FROM favorites')->fetchColumn(),
    ];
} else {
    $adminAuctions = $pdo->query(
        'SELECT a.*, u.name AS seller_name FROM auctions a JOIN users u ON u.id = a.user_id ORDER BY a.id DESC'
    )->fetchAll();
    $auctionStatusLabels = [
        'active' => 'Активен',
        'finished' => 'Завершён',
        'cancelled' => 'Скрыт',
    ];
}

$active = 'admin';
$pageTitle = 'Админ-панель — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>Админ-панель</h1>
    <p>Пользователи, модерация объявлений и аукционов.</p>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert-ok"><?= e($flash) ?></div>
<?php endif; ?>

<nav class="admin-tabs">
    <a class="btn <?= $tab === 'overview' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=overview">Обзор</a>
    <a class="btn <?= $tab === 'users' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=users">Пользователи</a>
    <a class="btn <?= $tab === 'items' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=items">Объявления</a>
    <a class="btn <?= $tab === 'auctions' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=auctions">Аукционы</a>
    <a class="btn <?= $tab === 'reports' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=reports">Жалобы</a>
    <a class="btn <?= $tab === 'stats' ? 'btn-primary' : 'btn-secondary' ?>" href="admin.php?tab=stats">Статистика</a>
</nav>

<?php if ($tab === 'overview'): ?>
    <section class="stats-grid">
        <div class="stat-card"><strong><?= $stats['users'] ?></strong><span>Пользователей</span></div>
        <div class="stat-card stat-bad"><strong><?= $stats['banned'] ?></strong><span>Заблокировано</span></div>
        <div class="stat-card"><strong><?= $stats['moderators'] ?></strong><span>Модераторов</span></div>
        <div class="stat-card"><strong><?= $stats['items'] ?></strong><span>Объявлений</span></div>
        <div class="stat-card"><strong><?= $stats['items_active'] ?></strong><span>Активных</span></div>
        <div class="stat-card"><strong><?= $stats['items_sold'] ?></strong><span>Продано</span></div>
        <div class="stat-card"><strong><?= $stats['auctions'] ?></strong><span>Аукционов</span></div>
        <div class="stat-card"><strong><?= $stats['auctions_active'] ?></strong><span>Активных</span></div>
        <div class="stat-card"><strong><?= $stats['auctions_finished'] ?></strong><span>Завершено</span></div>
        <div class="stat-card"><strong><?= $stats['bids'] ?></strong><span>Ставок</span></div>
        <div class="stat-card"><strong><?= $stats['messages'] ?></strong><span>Сообщений</span></div>
        <div class="stat-card"><strong><?= $stats['reviews'] ?></strong><span>Отзывов</span></div>
        <div class="stat-card"><strong><?= $stats['deals'] ?></strong><span>Сделок подтверждено</span></div>
    </section>
<?php elseif ($tab === 'users'): ?>
    <section class="manage-list">
        <?php foreach ($users as $u): ?>
            <?php $isAdmin = (int) $u['is_admin'] === 1; ?>
            <article class="manage-row<?= (int) $u['is_banned'] === 1 ? ' is-closed' : '' ?>">
                <div class="manage-info">
                    <h3>
                        <a href="profile.php?id=<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a>
                        <?php if ($isAdmin): ?><span class="chip status-finished">Админ</span><?php endif; ?>
                        <?php if ((int) $u['is_moderator'] === 1): ?><span class="chip status-active">Модератор</span><?php endif; ?>
                        <?php if ((int) $u['is_banned'] === 1): ?><span class="chip status-cancelled">Заблокирован</span><?php endif; ?>
                    </h3>
                    <p class="seller-sub">
                        <?= e($u['email']) ?> · <?= e($u['city']) ?> ·
                        <?= e(rating_label(seller_rating($pdo, (int) $u['id']))) ?> · продано <?= (int) $u['sold_count'] ?>
                    </p>
                </div>
                <?php if (!$isAdmin): ?>
                    <div class="manage-actions">
                        <?php if ((int) $u['is_banned'] === 1): ?>
                            <form method="post" action="admin.php?tab=users">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-secondary" type="submit" name="action" value="unban">Разблокировать</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="admin.php?tab=users" onsubmit="return confirm('Заблокировать этого пользователя?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-danger" type="submit" name="action" value="ban">Заблокировать</button>
                            </form>
                        <?php endif; ?>
                        <?php if ((int) $u['is_moderator'] === 1): ?>
                            <form method="post" action="admin.php?tab=users">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-secondary" type="submit" name="action" value="demote">Снять модератора</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="admin.php?tab=users">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-secondary" type="submit" name="action" value="promote">Назначить модератора</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="admin.php?tab=users">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-secondary" type="submit" name="action" value="<?= (int) $u['verified'] === 1 ? 'unverify' : 'verify' ?>">
                                <?= (int) $u['verified'] === 1 ? 'Снять проверку' : 'Отметить проверенным' ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($tab === 'items'): ?>
    <section class="manage-list">
        <?php foreach ($adminItems as $it): ?>
            <article class="manage-row<?= $it['status'] !== 'active' ? ' is-closed' : '' ?>">
                <img class="manage-thumb" src="<?= e(first_photo($it)) ?>" alt="">
                <div class="manage-info">
                    <h3><a href="item.php?id=<?= (int) $it['id'] ?>"><?= e($it['title']) ?></a></h3>
                    <p class="seller-sub">
                        <?= e($it['seller_name']) ?> ·
                        <?= (int) $it['price'] > 0 ? money((int) $it['price']) : 'даром' ?> ·
                        <span class="chip manage-status status-<?= e($it['status']) ?>"><?= e($itemStatusLabels[$it['status']] ?? $it['status']) ?></span>
                        <?php if ($it['confirmed_at'] !== null && $it['confirmed_at'] !== ''): ?> · сделка подтверждена<?php endif; ?>
                    </p>
                </div>
                <?php if ($it['status'] === 'active' || $it['status'] === 'archived'): ?>
                    <div class="manage-actions">
                        <form method="post" action="admin.php?tab=items">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                            <button class="btn <?= $it['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>" type="submit" name="action" value="<?= $it['status'] === 'active' ? 'hide_item' : 'restore_item' ?>">
                                <?= $it['status'] === 'active' ? 'Скрыть' : 'Вернуть' ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($tab === 'reports'): ?>
    <?php if (!$adminReports): ?>
        <p class="empty">Жалоб пока нет.</p>
    <?php else: ?>
        <section class="manage-list">
            <?php foreach ($adminReports as $r): ?>
                <?php $isItem = $r['item_id'] !== null; ?>
                <?php $listingUrl = $isItem ? 'item.php?id=' . (int) $r['item_id'] : 'auction.php?id=' . (int) $r['auction_id']; ?>
                <article class="manage-row<?= $r['status'] === 'new' ? '' : ' is-closed' ?>">
                    <div class="manage-info">
                        <h3>
                            <a href="<?= e($listingUrl) ?>"><?= e((string) $r['listing_title']) ?></a>
                            <span class="chip manage-status status-<?= e((string) $r['listing_status']) ?>"><?= e((string) $r['listing_status']) ?></span>
                            <?php if ($r['status'] === 'new'): ?><span class="chip status-active">Новая</span><?php endif; ?>
                        </h3>
                        <p class="seller-sub">
                            От: <?= e((string) $r['reporter_name']) ?> ·
                            причина: <?= e(report_reason_label((string) $r['reason'])) ?> ·
                            <?= e(date('d.m.Y H:i', strtotime((string) $r['created_at']))) ?>
                        </p>
                        <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?>
                            <p class="seller-sub">«<?= e((string) $r['comment']) ?>»</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($r['status'] === 'new'): ?>
                        <div class="manage-actions">
                            <form method="post" action="admin.php?tab=reports" onsubmit="return confirm('Скрыть лот и закрыть жалобу?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-danger" type="submit" name="action" value="resolve_report">Скрыть лот</button>
                            </form>
                            <form method="post" action="admin.php?tab=reports">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-secondary" type="submit" name="action" value="dismiss_report">Отклонить</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php elseif ($tab === 'stats'): ?>
    <section class="stats-grid">
        <div class="stat-card<?= $statsExtras['reports_new'] > 0 ? ' stat-bad' : '' ?>"><strong><?= $statsExtras['reports_new'] ?></strong><span>Новых жалоб</span></div>
        <div class="stat-card"><strong><?= $statsExtras['reports_total'] ?></strong><span>Жалоб всего</span></div>
        <div class="stat-card"><strong><?= $statsExtras['views_total'] ?></strong><span>Просмотров лотов</span></div>
        <div class="stat-card"><strong><?= $statsExtras['favorites'] ?></strong><span>В избранном</span></div>
    </section>

    <section class="catalog-block">
        <h2>Активность за 14 дней</h2>
        <div class="chart">
            <?php foreach ($byDay as $day => $row): ?>
                <?php $h = $chartMax > 0 ? (int) round($row['total'] / $chartMax * 100) : 0; ?>
                <div class="chart-col" title="<?= e(date('d.m.Y', strtotime($day))) ?> · действий: <?= $row['total'] ?>">
                    <div class="chart-bar-wrap"><div class="chart-bar" style="height: <?= max(2, $h) ?>%"></div></div>
                    <span class="chart-value"><?= $row['total'] ?></span>
                    <span class="chart-day"><?= e(date('d.m', strtotime($day))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <table class="stats-table">
            <thead>
                <tr><th>День</th><th>Регистрации</th><th>Объявления</th><th>Аукционы</th><th>Ставки</th><th>Сообщения</th></tr>
            </thead>
            <tbody>
                <?php foreach ($byDay as $day => $row): ?>
                    <tr>
                        <td><?= e(date('d.m.Y', strtotime($day))) ?></td>
                        <td><?= $row['users'] ?></td>
                        <td><?= $row['items'] ?></td>
                        <td><?= $row['auctions'] ?></td>
                        <td><?= $row['bids'] ?></td>
                        <td><?= $row['messages'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="catalog-block">
        <h2>Топ-категории</h2>
        <?php if (!$topCategories): ?>
            <p class="empty">Лотов пока нет.</p>
        <?php else: ?>
            <ul class="stat-list">
                <?php foreach ($topCategories as $c): ?>
                    <li>
                        <span class="stat-label"><?= e((string) $c['category']) ?> · <?= (int) $c['c'] ?></span>
                        <span class="bar-track"><span class="bar-fill" style="width: <?= max(3, (int) round((int) $c['c'] / $catMax * 100)) ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="catalog-block">
        <h2>Топ продавцов по сделкам</h2>
        <ul class="stat-list">
            <?php foreach ($topSellers as $s): ?>
                <li><span class="stat-label"><?= e((string) $s['name']) ?></span><span class="stat-count"><?= (int) $s['deals'] ?> сделок</span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="catalog-block">
        <h2>Самые просматриваемые лоты</h2>
        <?php if (!$topViewed): ?>
            <p class="empty">Просмотров пока нет.</p>
        <?php else: ?>
            <ul class="stat-list">
                <?php foreach ($topViewed as $v): ?>
                    <li><span class="stat-label"><?= e((string) $v['title']) ?></span><span class="stat-count"><?= (int) $v['views'] ?> просмотров</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="manage-list">
        <?php foreach ($adminAuctions as $a): ?>
            <article class="manage-row<?= $a['status'] !== 'active' ? ' is-closed' : '' ?>">
                <img class="manage-thumb" src="<?= e(first_photo($a)) ?>" alt="">
                <div class="manage-info">
                    <h3><a href="auction.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a></h3>
                    <p class="seller-sub">
                        <?= e($a['seller_name']) ?> · <?= money((int) $a['current_price']) ?> ·
                        <span class="chip manage-status status-<?= e($a['status']) ?>"><?= e($auctionStatusLabels[$a['status']] ?? $a['status']) ?></span>
                        <?php if ($a['confirmed_at'] !== null && $a['confirmed_at'] !== ''): ?> · сделка подтверждена<?php endif; ?>
                    </p>
                </div>
                <?php if ($a['status'] === 'active' || $a['status'] === 'cancelled'): ?>
                    <div class="manage-actions">
                        <form method="post" action="admin.php?tab=auctions">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="auction_id" value="<?= (int) $a['id'] ?>">
                            <button class="btn <?= $a['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>" type="submit" name="action" value="<?= $a['status'] === 'active' ? 'hide_auction' : 'restore_auction' ?>">
                                <?= $a['status'] === 'active' ? 'Скрыть' : 'Вернуть' ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
