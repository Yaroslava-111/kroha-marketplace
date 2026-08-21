<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$pdo = pdo();
$me = current_user();
$meId = (int) $me['id'];

$validTabs = ['overview', 'items', 'auctions', 'bids', 'reviews', 'favorites', 'searches', 'messages', 'notifications', 'history', 'settings'];
$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, $validTabs, true)) {
    $tab = 'overview';
}

$sub = (string) ($_GET['sub'] ?? '');
if ($tab === 'reviews' && !in_array($sub, ['pending', 'given', 'about'], true)) {
    $sub = 'pending';
}
if ($tab === 'favorites' && !in_array($sub, ['items', 'auctions'], true)) {
    $sub = 'items';
}

define('MESSAGES_EMBEDDED', true);
require __DIR__ . '/includes/messages_section.php';

$actionOk = '';
$actionError = '';
$saveOk = false;
$editErrors = [];
$old = ['name' => $me['name'], 'city' => $me['city']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($tab === 'messages') {
        messages_post($pdo, $meId);
    }

    if (!csrf_check()) {
        $actionError = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } elseif ($action === 'sold' || $action === 'archive' || $action === 'activate' || $action === 'delete') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? AND user_id = ?');
        $stmt->execute([$itemId, $meId]);
        $item = $stmt->fetch();

        if (!$item) {
            $actionError = 'Объявление не найдено.';
        } elseif ($action === 'sold') {
            $err = mark_item_sold($pdo, $meId, $itemId, (int) ($_POST['buyer_id'] ?? 0));
            if ($err !== null) {
                $actionError = $err;
            } else {
                $actionOk = 'Объявление отмечено как «Продано».';
            }
        } elseif ($action === 'archive') {
            $pdo->prepare('UPDATE items SET status = \'archived\' WHERE id = ?')->execute([$itemId]);
            $actionOk = 'Объявление снято с публикации.';
        } elseif ($action === 'activate') {
            $pdo->prepare('UPDATE items SET status = \'active\' WHERE id = ?')->execute([$itemId]);
            $actionOk = 'Объявление снова в продаже.';
        } elseif ($action === 'delete') {
            foreach (photos_of($item) as $ph) {
                $path = __DIR__ . '/' . $ph;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?')->execute([$itemId, $meId]);
            $actionOk = 'Объявление удалено.';
        }
    } elseif ($action === 'update') {
        $old['name'] = trim((string) ($_POST['name'] ?? ''));
        $old['city'] = trim((string) ($_POST['city'] ?? ''));
        if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) {
            $editErrors['name'] = 'Имя — от 2 до 100 символов.';
        }
        if (mb_strlen($old['city']) < 2 || mb_strlen($old['city']) > 100) {
            $editErrors['city'] = 'Укажите город.';
        }
        if (!$editErrors) {
            $pdo->prepare('UPDATE users SET name = ?, city = ? WHERE id = ?')
                ->execute([$old['name'], $old['city'], $meId]);
            $me['name'] = $old['name'];
            $me['city'] = $old['city'];
            $saveOk = true;
        }
    } elseif ($action === 'password') {
        $current = (string) ($_POST['password_current'] ?? '');
        $newPass = (string) ($_POST['password_new'] ?? '');
        $newPass2 = (string) ($_POST['password_new2'] ?? '');
        if (!password_verify($current, (string) $me['password_hash'])) {
            $editErrors['password_current'] = 'Текущий пароль неверный.';
        } elseif (mb_strlen($newPass) < 8) {
            $editErrors['password_new'] = 'Новый пароль — не короче 8 символов.';
        } elseif ($newPass !== $newPass2) {
            $editErrors['password_new2'] = 'Новые пароли не совпадают.';
        }
        if (!$editErrors) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $meId]);
            $saveOk = true;
        }
    } elseif ($tab === 'favorites') {
        if ($action === 'clear') {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$meId]);
        } elseif (($action === 'item' || $action === 'auction') && (int) ($_POST['target_id'] ?? 0) > 0) {
            toggle_favorite($pdo, $meId, $action, (int) $_POST['target_id']);
        }
        header('Location: account.php?tab=favorites&sub=' . $sub);
        exit;
    } elseif ($tab === 'history' && $action === 'clear') {
        clear_view_history($pdo, $meId);
        header('Location: account.php?tab=history');
        exit;
    } elseif ($tab === 'reviews' && $action === 'review') {
        $type = ($_POST['type'] ?? '') === 'auction' ? 'auction' : 'item';
        $refId = (int) ($_POST['id'] ?? 0);
        $err = add_review($pdo, $meId, $type, $refId, (int) ($_POST['rating'] ?? 0), (string) ($_POST['text'] ?? ''));
        header('Location: account.php?tab=reviews' . ($err === null ? '&ok=review' : '&review_error=' . urlencode($err)));
        exit;
    } elseif ($tab === 'notifications' && $action === 'readall') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$meId]);
        header('Location: account.php?tab=notifications&ok=readall');
        exit;
    } elseif ($tab === 'notifications' && $action === 'read') {
        $nid = (int) ($_POST['id'] ?? 0);
        if ($nid > 0) {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$nid, $meId]);
        }
        http_response_code(204);
        exit;
    }
}

$passOpen = isset($editErrors['password_current'])
    || isset($editErrors['password_new'])
    || isset($editErrors['password_new2']);

$okMsg = '';
if (($_GET['ok'] ?? '') === 'review') {
    $okMsg = 'Спасибо! Отзыв опубликован.';
} elseif (($_GET['ok'] ?? '') === 'edit') {
    $okMsg = 'Изменения сохранены.';
} elseif (($_GET['ok'] ?? '') === 'readall') {
    $okMsg = 'Все уведомления прочитаны.';
}
$reviewErr = (string) ($_GET['review_error'] ?? '');

$unread = unread_notifications_count($pdo, $meId);
$unreadMsgs = unread_messages_count($pdo, $meId);

$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$notifStmt->execute([$meId]);
$notifications = $notifStmt->fetchAll();

// Обзор: счётчики
$stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = ? AND status = \'active\'');
$stmt->execute([$meId]);
$overviewItems = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM auctions WHERE user_id = ? AND status = \'active\'');
$stmt->execute([$meId]);
$overviewAuctions = (int) $stmt->fetchColumn();

$pendingReviews = pending_reviews_of($pdo, $meId);
$pendingCount = count($pendingReviews);

[$favItems, $favAuctions] = favorite_state($pdo, $meId);
$overviewFav = count($favItems) + count($favAuctions);

$myRating = seller_rating($pdo, $meId);

// Мои объявления
$myItems = [];
if ($tab === 'items') {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$meId]);
    $myItems = $stmt->fetchAll();
}

// Мои аукционы
$myAuctions = [];
if ($tab === 'auctions') {
    $stmt = $pdo->prepare(
        'SELECT a.*, COUNT(b.id) AS bid_count
         FROM auctions a
         LEFT JOIN bids b ON b.auction_id = a.id
         WHERE a.user_id = ?
         GROUP BY a.id
         ORDER BY a.created_at DESC, a.id DESC'
    );
    $stmt->execute([$meId]);
    $myAuctions = $stmt->fetchAll();
}

// Мои ставки
$profileBids = [];
if ($tab === 'bids') {
    $stmt = $pdo->prepare(
        'SELECT b.*, a.title AS auction_title, a.status AS auction_status,
                a.current_price, a.start_price, a.end_at, a.min_bid_step,
                u.name AS seller_name, u.city AS seller_city
         FROM bids b
         JOIN auctions a ON a.id = b.auction_id
         JOIN users u ON u.id = a.user_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC, b.id DESC'
    );
    $stmt->execute([$meId]);
    $rows = $stmt->fetchAll();
    $uniq = [];
    foreach ($rows as $r) {
        $aid = (int) $r['auction_id'];
        if (!isset($uniq[$aid])) {
            $uniq[$aid] = $r;
        }
    }
    $profileBids = array_values($uniq);
}

// Избранное
$favRows = ['items' => [], 'auctions' => []];
if ($tab === 'favorites') {
    if ($favItems) {
        $ph = implode(',', array_fill(0, count($favItems), '?'));
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id IN (' . $ph . ')');
        $stmt->execute($favItems);
        $favRows['items'] = $stmt->fetchAll();
        usort($favRows['items'], static fn(array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
    }
    if ($favAuctions) {
        $ph = implode(',', array_fill(0, count($favAuctions), '?'));
        $stmt = $pdo->prepare(
            'SELECT a.*, u.name AS seller_name, u.city AS seller_city, COUNT(b.id) AS bid_count
             FROM auctions a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN bids b ON b.auction_id = a.id
             WHERE a.id IN (' . $ph . ')
             GROUP BY a.id'
        );
        $stmt->execute($favAuctions);
        $favRows['auctions'] = $stmt->fetchAll();
    }
}

// История
$history = [];
if ($tab === 'history') {
    $history = view_history($pdo, $meId, 10);
}

// Сохранённые поиски
$savedSearches = [];
if ($tab === 'searches') {
    $savedSearches = saved_searches_of($pdo, $meId);
}

// Отзывы
$myGivenReviews = [];
$reviewsAboutMe = [];
if ($tab === 'reviews') {
    $myGivenReviews = reviews_by($pdo, $meId);
    $reviewsAboutMe = reviews_of($pdo, $meId);
}

$statusLabels = [
    'active' => 'Активно',
    'sold' => 'Продано',
    'archived' => 'Снято',
];
$statusLabelsAuction = [
    'active' => 'Активен',
    'finished' => 'Завершён',
    'cancelled' => 'Снят',
];

$active = 'account';
$pageTitle = 'Мой кабинет — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="account-head">
        <div class="account-head-user">
            <span class="avatar avatar-xl"><?= e(initials($me['name'])) ?></span>
            <div class="account-head-meta">
                <h1>Мой кабинет</h1>
                <p class="seller-sub">
                    <?= e($me['name']) ?> · <?= e($me['city']) ?> · <?= e(rating_label($myRating)) ?>
                    <?php if ((int) $me['sold_count'] > 0): ?> · продано <?= (int) $me['sold_count'] ?><?php endif; ?>
                </p>
            </div>
        </div>
        <div class="account-head-actions">
            <a class="btn btn-secondary" href="profile.php?id=<?= $meId ?>">Мой профиль</a>
        </div>
    </section>

    <div class="account-layout">
        <?php $accountActive = $tab; require __DIR__ . '/includes/account_aside.php'; ?>

        <div class="account-content">
            <?php if ($actionError !== ''): ?>
                <div class="alert alert-error"><?= e($actionError) ?></div>
            <?php endif; ?>
            <?php if ($actionOk !== ''): ?>
                <div class="alert alert-ok alert-flash"><?= e($actionOk) ?></div>
            <?php endif; ?>
            <?php if ($saveOk): ?>
                <div class="alert alert-ok alert-flash">Сохранено.</div>
            <?php endif; ?>
            <?php if ($editErrors): ?>
                <div class="alert alert-error">
                    <?php foreach ($editErrors as $er): ?>
                        <div><?= e($er) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($okMsg !== ''): ?>
                <div class="alert alert-ok alert-flash"><?= e($okMsg) ?></div>
            <?php endif; ?>
            <?php if ($reviewErr !== ''): ?>
                <div class="alert alert-error"><?= e($reviewErr) ?></div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                <section class="account-stats">
                    <div class="stat-card">
                        <span class="stat-num"><?= $overviewItems ?></span>
                        <span class="stat-label">Активных объявлений</span>
                        <a class="stat-link" href="account.php?tab=items">Открыть</a>
                    </div>
                    <div class="stat-card">
                        <span class="stat-num"><?= $overviewAuctions ?></span>
                        <span class="stat-label">Активных аукционов</span>
                        <a class="stat-link" href="account.php?tab=auctions">Открыть</a>
                    </div>
                    <div class="stat-card">
                        <span class="stat-num"><?= $overviewFav ?></span>
                        <span class="stat-label">В избранном</span>
                        <a class="stat-link" href="account.php?tab=favorites">Открыть</a>
                    </div>
                    <div class="stat-card">
                        <span class="stat-num"><?= $pendingCount ?></span>
                        <span class="stat-label">Ожидают отзыва</span>
                        <a class="stat-link" href="account.php?tab=reviews">Открыть</a>
                    </div>
                </section>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Быстрые действия</h2>
                    </div>
                    <div class="account-actions">
                        <a class="btn btn-primary" href="post.php" data-modal-post>Разместить объявление</a>
                        <a class="btn btn-secondary" href="auction_new.php" data-modal-auction>Создать аукцион</a>
                        <a class="btn btn-secondary" href="account.php?tab=messages">Сообщения</a>
                        <a class="btn btn-secondary" href="index.php?type=all">Смотреть каталог</a>
                    </div>
                </section>
            <?php elseif ($tab === 'items'): ?>
                <div class="section-head">
                    <h2>Мои объявления <span class="muted">(<?= count($myItems) ?>)</span></h2>
                    <a class="btn btn-primary" href="post.php" data-modal-item>Создать новое</a>
                </div>

                <?php if (!$myItems): ?>
                    <p class="empty">У вас пока нет объявлений. Создайте первое — вещи второй раз пригодятся!</p>
                <?php else: ?>
                    <section class="manage-grid">
                        <?php foreach ($myItems as $it): ?>
                            <?php $photoCount = count(photos_of($it)); ?>
                            <article class="mng-card<?= $it['status'] !== 'active' ? ' is-closed' : '' ?>">
                                <a class="mng-media" href="item.php?id=<?= (int) $it['id'] ?>">
                                    <img src="<?= e(first_photo($it)) ?>" alt="<?= e($it['title']) ?>">
                                    <?php if ($photoCount > 0): ?>
                                        <span class="mng-count"><?= $photoCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="mng-body">
                                    <div class="mng-top">
                                        <span class="chip status-<?= e($it['status']) ?>"><?= e($statusLabels[$it['status']] ?? $it['status']) ?></span>
                                        <span class="mng-date"><?= e(date('d.m.Y', strtotime($it['created_at']))) ?></span>
                                    </div>
                                    <h3 class="mng-title"><a href="item.php?id=<?= (int) $it['id'] ?>"><?= e($it['title']) ?></a></h3>
                                    <div class="mng-price<?= (int) $it['price'] > 0 ? '' : ' mng-price-give' ?>">
                                        <?= (int) $it['price'] > 0 ? money((int) $it['price']) : 'Отдам даром' ?>
                                    </div>
                                    <div class="mng-meta">
                                        <span><?= e($it['category']) ?></span>
                                        <span><?= e($it['city']) ?></span>
                                    </div>
                                    <?php if ($it['status'] === 'sold' && $it['confirmed_at'] !== null && $it['confirmed_at'] !== ''): ?>
                                        <div class="mng-confirm">Сделка подтверждена · <?= e(date('d.m.Y', strtotime($it['confirmed_at']))) ?></div>
                                    <?php endif; ?>
                                    <div class="mng-actions">
                                        <?php if ($it['status'] === 'active'): ?>
                                            <?php $buyers = item_buyers($pdo, (int) $it['id'], $meId); ?>
                                            <form method="post" action="account.php?tab=items">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                                <span class="sold-group">
                                                    <select name="buyer_id" class="sold-select" aria-label="Покупатель">
                                                        <option value="0">Без покупателя</option>
                                                        <?php foreach ($buyers as $b): ?>
                                                            <option value="<?= (int) $b['buyer_id'] ?>"><?= e($b['buyer_name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="action" value="sold">Продано</button>
                                                </span>
                                            </form>
                                        <?php elseif ($it['status'] === 'archived'): ?>
                                            <form method="post" action="account.php?tab=items">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                                <button class="mng-btn mng-btn-primary" type="submit" name="action" value="activate">Активировать</button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="mng-actions-aux">
                                            <a class="mng-icon" href="edit_item.php?id=<?= (int) $it['id'] ?>" title="Изменить" aria-label="Изменить">
                                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                                            </a>
                                            <?php if ($it['status'] === 'active'): ?>
                                                <form method="post" action="account.php?tab=items">
                                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                                    <button class="mng-icon" type="submit" name="action" value="archive" title="Снять с публикации" aria-label="Снять с публикации">
                                                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="account.php?tab=items" onsubmit="return confirm('Удалить объявление безвозвратно?');">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                                                <button class="mng-icon mng-icon-danger" type="submit" name="action" value="delete" title="Удалить" aria-label="Удалить">
                                                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($tab === 'auctions'): ?>
                <div class="section-head">
                    <h2>Мои аукционы <span class="muted">(<?= count($myAuctions) ?>)</span></h2>
                    <a class="btn btn-primary" href="auction_new.php" data-modal-auction>Создать аукцион</a>
                </div>

                <?php if (!$myAuctions): ?>
                    <p class="empty">Вы ещё не выставляли лотов. Аукцион — лучший способ продать пакет вещей или бренд.</p>
                <?php else: ?>
                    <section class="manage-grid">
                        <?php foreach ($myAuctions as $a): ?>
                            <?php
                            $ended = $a['status'] === 'finished' || $a['status'] === 'cancelled' || strtotime($a['end_at']) <= time();
                            $photoCount = count(photos_of($a));
                            ?>
                            <article class="mng-card<?= $ended ? ' is-closed' : '' ?>">
                                <a class="mng-media" href="auction.php?id=<?= (int) $a['id'] ?>">
                                    <img src="<?= e(first_photo($a)) ?>" alt="<?= e($a['title']) ?>">
                                    <?php if ($photoCount > 0): ?>
                                        <span class="mng-count"><?= $photoCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="mng-body">
                                    <div class="mng-top">
                                        <span class="chip status-<?= e($a['status']) ?>"><?= e($statusLabelsAuction[$a['status']] ?? $a['status']) ?></span>
                                        <span class="mng-date"><?= e(date('d.m.Y', strtotime($a['created_at']))) ?></span>
                                    </div>
                                    <h3 class="mng-title"><a href="auction.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a></h3>
                                    <div class="mng-price"><span class="mng-price-cap">Текущая</span><?= money((int) $a['current_price']) ?></div>
                                    <div class="mng-meta">
                                        <span>старт <?= money((int) $a['start_price']) ?></span>
                                        <span><?= (int) $a['bid_count'] ?> <?= e(plural((int) $a['bid_count'], ['ставка', 'ставки', 'ставок'])) ?></span>
                                    </div>
                                    <?php if (!$ended): ?>
                                        <div class="mng-meta mng-timer"><span class="mng-timer-label">Осталось</span><span class="chip timer mng-timer-chip<?= seconds_until($a['end_at']) <= 3600 ? ' is-urgent' : '' ?>" data-end="<?= (int) strtotime($a['end_at']) ?>"><?= e(format_countdown(seconds_until($a['end_at']))) ?></span></div>
                                    <?php endif; ?>
                                    <?php if ($a['status'] === 'finished' && $a['confirmed_at'] !== null && $a['confirmed_at'] !== ''): ?>
                                        <div class="mng-confirm">Сделка подтверждена · <?= e(date('d.m.Y', strtotime($a['confirmed_at']))) ?></div>
                                    <?php endif; ?>
                                    <div class="mng-actions">
                                        <a class="mng-btn mng-btn-primary" href="auction.php?id=<?= (int) $a['id'] ?>">Открыть</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($tab === 'bids'): ?>
                <div class="section-head">
                    <h2>Мои ставки <span class="muted">(<?= count($profileBids) ?>)</span></h2>
                </div>
                <?php if (!$profileBids): ?>
                    <p class="empty">Вы пока не делали ставок. Найдите лот в <a href="index.php?type=auctions">аукционах</a> и участвуйте!</p>
                <?php else: ?>
                    <div class="manage-list">
                        <?php foreach ($profileBids as $b): ?>
                            <?php
                            $ended = $b['auction_status'] === 'finished' || $b['auction_status'] === 'cancelled' || strtotime($b['end_at']) <= time();
                            $state = auction_state($pdo, $b);
                            $isLeader = $state['leader'] && (int) $state['leader']['user_id'] === $meId;
                            ?>
                            <article class="manage-row<?= $ended ? ' is-closed' : '' ?>">
                                <div class="manage-info">
                                    <h3><a href="auction.php?id=<?= (int) $b['auction_id'] ?>"><?= e($b['auction_title']) ?></a></h3>
                                    <p class="seller-sub">
                                        Ваша ставка: <?= money((int) $b['amount']) ?><?= (int) $b['is_proxy'] === 1 ? ' (авто)' : '' ?>
                                        · сделана <?= e(date('d.m.Y H:i', strtotime($b['created_at']))) ?>
                                    </p>
                                    <p class="seller-sub">
                                        Продавец: <?= e($b['seller_name']) ?> (<?= e($b['seller_city']) ?>)
                                        · текущая цена <?= money((int) $b['current_price']) ?>
                                    </p>
                                </div>
                                <?php if ($ended): ?>
                                    <span class="chip manage-status status-<?= e($b['auction_status']) ?>">Завершён</span>
                                <?php elseif ($isLeader): ?>
                                    <span class="chip manage-status status-active">Вы впереди</span>
                                <?php else: ?>
                                    <span class="chip manage-status status-overbid">Перебита</span>
                                <?php endif; ?>
                                <div class="manage-actions">
                                    <a class="btn btn-secondary" href="auction.php?id=<?= (int) $b['auction_id'] ?>">Открыть лот</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'reviews'): ?>
                <nav class="profile-tabs" aria-label="Журнал отзывов">
                    <a class="tab<?= $sub === 'pending' ? ' is-active' : '' ?>" href="account.php?tab=reviews&sub=pending">Ожидают отзыва (<?= $pendingCount ?>)</a>
                    <a class="tab<?= $sub === 'given' ? ' is-active' : '' ?>" href="account.php?tab=reviews&sub=given">Мои отзывы (<?= count($myGivenReviews) ?>)</a>
                    <a class="tab<?= $sub === 'about' ? ' is-active' : '' ?>" href="account.php?tab=reviews&sub=about">Обо мне (<?= count($reviewsAboutMe) ?>)</a>
                </nav>

                <?php if ($sub === 'pending'): ?>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Ожидают отзыва <span class="muted">(<?= $pendingCount ?>)</span></h2>
                    </div>
                    <?php if (!$pendingReviews): ?>
                        <p class="empty">Здесь появляются завершённые сделки, по которым вы ещё не оставили отзыв. Если не хотите писать сейчас — можно вернуться к этому разделу позже.</p>
                    <?php else: ?>
                        <div class="manage-list">
                            <?php foreach ($pendingReviews as $pr): ?>
                                <?php $url = $pr['type'] === 'item' ? 'item.php?id=' . $pr['id'] : 'auction.php?id=' . $pr['id']; ?>
                                <article class="manage-row" data-review-id="<?= $pr['type'] ?>:<?= $pr['id'] ?>">
                                    <div class="manage-info">
                                        <h3><a href="<?= e($url) ?>"><?= e($pr['title']) ?></a></h3>
                                        <p class="seller-sub">
                                            <?= $pr['type'] === 'item' ? 'Объявление' : 'Аукцион' ?> · продавец <?= e($pr['seller_name']) ?> (<?= e($pr['seller_city']) ?>)
                                        </p>
                                    </div>
                                    <details class="review-pending">
                                        <summary class="btn btn-secondary">Оставить отзыв</summary>
                                        <form class="form-card review-pending-form" method="post" action="account.php?tab=reviews">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="review">
                                            <input type="hidden" name="type" value="<?= e($pr['type']) ?>">
                                            <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                            <div class="review-stars">
                                                <?php for ($r = 5; $r >= 1; $r--): ?>
                                                    <label class="review-star-opt">
                                                        <input type="radio" name="rating" value="<?= $r ?>" required>
                                                        <span><?= str_repeat('★', $r) ?><?= str_repeat('☆', 5 - $r) ?></span>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="form-row">
                                                <textarea name="text" maxlength="500" rows="2" placeholder="Как прошла сделка? (необязательно)"></textarea>
                                            </div>
                                            <div class="review-pending-actions">
                                                <button class="btn btn-primary" type="submit">Отправить отзыв</button>
                                                <button class="btn btn-secondary review-dismiss" type="button">Не сейчас</button>
                                            </div>
                                        </form>
                                    </details>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php elseif ($sub === 'given'): ?>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Мои отзывы <span class="muted">(<?= count($myGivenReviews) ?>)</span></h2>
                    </div>
                    <?php if (!$myGivenReviews): ?>
                        <p class="empty">Вы пока не оставляли отзывов.</p>
                    <?php else: ?>
                        <div class="reviews-list">
                            <?php foreach ($myGivenReviews as $rv): ?>
                                <article class="review-row">
                                    <div class="review-head">
                                        <span class="avatar avatar-sm"><?= e(initials($rv['seller_name'])) ?></span>
                                        <span class="review-who">
                                            <strong><?= e($rv['seller_name']) ?></strong>
                                            <span class="review-stars-sm"><?= stars_html((int) $rv['rating']) ?></span>
                                        </span>
                                        <span class="msg-row-time"><?= e(date('d.m.Y', strtotime($rv['created_at']))) ?></span>
                                    </div>
                                    <div class="review-topic">
                                        <?php if ($rv['item_title'] !== null): ?>
                                            Объявление: <a href="item.php?id=<?= (int) $rv['item_id'] ?>"><?= e($rv['item_title']) ?></a>
                                        <?php elseif ($rv['auction_title'] !== null): ?>
                                            Аукцион: <a href="auction.php?id=<?= (int) $rv['auction_id'] ?>"><?= e($rv['auction_title']) ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($rv['text'] !== null && $rv['text'] !== ''): ?>
                                        <p class="review-text"><?= nl2br(e($rv['text'])) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php else: ?>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Отзывы обо мне <span class="muted">(<?= count($reviewsAboutMe) ?>)</span></h2>
                    </div>
                    <?php if (!$reviewsAboutMe): ?>
                        <p class="empty">Отзывов о вас пока нет. Они появятся после первых сделок.</p>
                    <?php else: ?>
                        <div class="reviews-list">
                            <?php foreach ($reviewsAboutMe as $rv): ?>
                                <article class="review-row">
                                    <div class="review-head">
                                        <span class="avatar avatar-sm"><?= e(initials($rv['author_name'])) ?></span>
                                        <span class="review-who">
                                            <strong><?= e($rv['author_name']) ?></strong>
                                            <span class="review-stars-sm"><?= stars_html((int) $rv['rating']) ?></span>
                                        </span>
                                        <span class="msg-row-time"><?= e(date('d.m.Y', strtotime($rv['created_at']))) ?></span>
                                    </div>
                                    <div class="review-topic">
                                        <?php if ($rv['item_title'] !== null): ?>
                                            Объявление: <a href="item.php?id=<?= (int) $rv['item_id'] ?>"><?= e($rv['item_title']) ?></a>
                                        <?php elseif ($rv['auction_title'] !== null): ?>
                                            Аукцион: <a href="auction.php?id=<?= (int) $rv['auction_id'] ?>"><?= e($rv['auction_title']) ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($rv['text'] !== null && $rv['text'] !== ''): ?>
                                        <p class="review-text"><?= nl2br(e($rv['text'])) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

            <?php elseif ($tab === 'favorites'): ?>
                <nav class="profile-tabs" aria-label="Избранное">
                    <a class="tab<?= $sub === 'items' ? ' is-active' : '' ?>" href="account.php?tab=favorites&sub=items">Объявления (<?= count($favRows['items']) ?>)</a>
                    <a class="tab<?= $sub === 'auctions' ? ' is-active' : '' ?>" href="account.php?tab=favorites&sub=auctions">Аукционы (<?= count($favRows['auctions']) ?>)</a>
                </nav>

                <?php if ($sub === 'items' || (!$sub && $favRows['items'])): ?>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Объявления <span class="muted">(<?= count($favRows['items']) ?>)</span></h2>
                        <?php if ($favItems || $favAuctions): ?>
                            <form class="section-head-action" method="post" action="account.php?tab=favorites&sub=<?= e($sub) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="clear">
                                <button class="btn btn-danger btn-sm" type="submit">Очистить избранное</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($favRows['items']): ?>
                        <div class="manage-list">
                            <?php foreach ($favRows['items'] as $it): ?>
                                <div class="manage-row">
                                    <img class="manage-thumb" src="<?= e(first_photo($it)) ?>" alt="">
                                    <div class="manage-info">
                                        <h3><a href="item.php?id=<?= (int) $it['id'] ?>"><?= e($it['title']) ?></a></h3>
                                        <span class="seller">
                                            <?= e($it['city']) ?> · <?= (int) $it['price'] > 0 ? money((int) $it['price']) : 'Отдам даром' ?>
                                        </span>
                                    </div>
                                    <div class="manage-actions">
                                        <a class="btn btn-secondary" href="item.php?id=<?= (int) $it['id'] ?>">Открыть</a>
                                        <form method="post" action="account.php?tab=favorites&sub=items">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="item">
                                            <input type="hidden" name="target_id" value="<?= (int) $it['id'] ?>">
                                            <button class="mng-icon mng-icon-danger" type="submit" aria-label="Убрать из избранного">
                                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty">В избранном пока нет объявлений. Нажмите на ♥ на карточке или странице объявления.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php if ($sub === 'auctions' || (!$sub && !$favRows['items'])): ?>
                <section class="catalog-block">
                    <div class="section-head">
                        <h2>Аукционы <span class="muted">(<?= count($favRows['auctions']) ?>)</span></h2>
                        <?php if ($favItems || $favAuctions): ?>
                            <form class="section-head-action" method="post" action="account.php?tab=favorites&sub=<?= e($sub) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="clear">
                                <button class="btn btn-danger btn-sm" type="submit">Очистить избранное</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($favRows['auctions']): ?>
                        <div class="manage-list">
                            <?php foreach ($favRows['auctions'] as $a): ?>
                                <div class="manage-row">
                                    <img class="manage-thumb" src="<?= e(first_photo($a)) ?>" alt="">
                                    <div class="manage-info">
                                        <h3><a href="auction.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a></h3>
                                        <span class="seller">
                                            <?= e($a['seller_city']) ?> · <?= money((int) $a['current_price']) ?>
                                            · <?= e(format_countdown(seconds_until($a['end_at']))) ?>
                                        </span>
                                    </div>
                                    <div class="manage-actions">
                                        <a class="btn btn-secondary" href="auction.php?id=<?= (int) $a['id'] ?>">Открыть</a>
                                        <form method="post" action="account.php?tab=favorites&sub=auctions">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="auction">
                                            <input type="hidden" name="target_id" value="<?= (int) $a['id'] ?>">
                                            <button class="mng-icon mng-icon-danger" type="submit" aria-label="Убрать из избранного">
                                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty">В избранном пока нет аукционов. Нажмите на ♥ на карточке или странице лота.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

            <?php elseif ($tab === 'searches'): ?>
                <div class="section-head">
                    <h2>Сохранённые поиски <span class="muted">(<?= count($savedSearches) ?>)</span></h2>
                    <a class="btn btn-primary" href="index.php?type=all">Новый поиск</a>
                </div>
                <?php if (($_GET['ok'] ?? '') === 'deleted'): ?>
                    <div class="alert alert-ok alert-flash">Поиск удалён.</div>
                <?php endif; ?>

                <?php if (!$savedSearches): ?>
                    <p class="empty">Пока нет сохранённых поисков. Задайте фильтры в каталоге и нажмите «☆ Сохранить поиск» — мы сообщим о новых подходящих лотах.</p>
                <?php else: ?>
                    <section class="notice-list">
                        <?php foreach ($savedSearches as $ss): ?>
                            <article class="manage-row">
                                <span class="notice-icon notice-icon--search" aria-hidden="true"><?= notification_icon('search') ?></span>
                                <div class="manage-info">
                                    <h3><a href="index.php?<?= e((string) $ss['params']) ?>"><?= e($ss['label']) ?></a></h3>
                                    <p class="seller-sub">сохранён <?= e(date('d.m.Y', strtotime($ss['created_at']))) ?></p>
                                </div>
                                <form method="post" action="saved_search.php" onsubmit="return confirm('Удалить этот поиск?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $ss['id'] ?>">
                                    <button class="btn-ghost" type="submit">Удалить</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($tab === 'history'): ?>
                <div class="section-head">
                    <h2>История <span class="muted">(<?= count($history) ?>)</span></h2>
                    <div class="section-head-actions">
                        <?php if ($history): ?>
                            <form method="post" action="account.php?tab=history" onsubmit="return confirm('Очистить историю просмотров?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="clear">
                                <button class="btn-ghost" type="submit">Очистить историю</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-primary" href="index.php?type=all">Смотреть каталог</a>
                    </div>
                </div>

                <?php if (!$history): ?>
                    <p class="empty">Вы пока не открывали лоты. <a href="index.php?type=all">Перейдите в каталог</a> — сюда попадут последние 10 просмотров.</p>
                <?php else: ?>
                    <section class="manage-list">
                        <?php foreach ($history as $v): ?>
                            <?php $isItem = $v['item_id'] !== null; ?>
                            <?php $url = $isItem ? 'item.php?id=' . (int) $v['item_id'] : 'auction.php?id=' . (int) $v['auction_id']; ?>
                            <article class="manage-row">
                                <img class="manage-thumb" src="<?= e(first_photo($v)) ?>" alt="">
                                <div class="manage-info">
                                    <h3><a href="<?= e($url) ?>"><?= e($v['title']) ?></a></h3>
                                    <p class="seller-sub">
                                        <?php if ($isItem): ?>
                                            <?= (int) $v['price'] > 0 ? money((int) $v['price']) : 'Отдам даром' ?>
                                        <?php else: ?>
                                            Текущая ставка: <?= money((int) $v['price']) ?>
                                        <?php endif; ?>
                                        · просмотрено <?= e(date('d.m.Y H:i', strtotime($v['viewed_at']))) ?>
                                    </p>
                                </div>
                                <div class="manage-actions">
                                    <a class="btn btn-secondary" href="<?= e($url) ?>">Открыть</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($tab === 'messages'): ?>
                <div class="section-head">
                    <h2>Сообщения</h2>
                </div>
                <?php render_messages_section($pdo, $meId); ?>

            <?php elseif ($tab === 'notifications'): ?>
                <div class="section-head">
                    <h2>Уведомления <span class="muted">(<?= $unread ?> <?= e(plural($unread, ['непрочитанное', 'непрочитанных', 'непрочитанных'])) ?>)</span></h2>
                    <form method="post" action="account.php?tab=notifications">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="readall">
                        <button class="btn-ghost" type="submit"<?= $unread === 0 ? ' disabled' : '' ?>>Отметить все прочитанными</button>
                    </form>
                </div>

                <?php if (!$notifications): ?>
                    <p class="empty">Пока нет уведомлений. Перебивайте ставки и ждите — они появятся сами.</p>
                <?php else: ?>
                    <section class="notice-list">
                        <?php foreach ($notifications as $n): ?>
                            <?php $isLink = $n['link'] !== ''; ?>
                            <<?= $isLink ? 'a href="' . e($n['link']) . '"' : 'div' ?> class="notice<?= (int) $n['is_read'] === 0 ? ' is-unread' : '' ?>" data-nid="<?= (int) $n['id'] ?>">
                                <span class="notice-icon notice-icon--<?= e($n['type']) ?>"><?= notification_icon($n['type']) ?></span>
                                <span class="notice-body">
                                    <span class="notice-text"><?= e($n['text']) ?></span>
                                    <span class="notice-meta">
                                        <span class="chip notice-type"><?= e(notification_type_label($n['type'])) ?></span>
                                        <span class="muted small" title="<?= e(date('d.m.Y H:i', strtotime($n['created_at']))) ?>"><?= e(msg_time($n['created_at'])) ?></span>
                                    </span>
                                </span>
                                <?php if ($isLink): ?><span class="notice-arrow" aria-hidden="true">→</span><?php endif; ?>
                            </<?= $isLink ? 'a' : 'div' ?>>
                        <?php endforeach; ?>
                    </section>
                    <script>
                    (function () {
                        var csrf = '<?= e(csrf_token()) ?>';
                        function bumpBadges(delta) {
                            ['.notif-badge', '.nav-badge-notif'].forEach(function (sel) {
                                var b = document.querySelector(sel);
                                if (!b) return;
                                var n = Math.max(0, (parseInt(b.dataset.count, 10) || 0) + delta);
                                b.dataset.count = String(n);
                                b.textContent = n;
                                b.classList.toggle('is-empty', n === 0);
                            });
                            var h2 = document.querySelector('.section-head h2 .muted');
                            if (h2 && delta !== 0) {
                                var m = h2.textContent.match(/\d+/);
                                if (m) {
                                    var left = Math.max(0, parseInt(m[0], 10) + delta);
                                    h2.textContent = '(' + left + ' ' + (left === 1 ? 'непрочитанное' : 'непрочитанных') + ')';
                                }
                            }
                        }
                        document.querySelectorAll('.notice.is-unread[data-nid]').forEach(function (el) {
                            el.addEventListener('click', function (ev) {
                                var href = el.getAttribute('href');
                                if (ev.defaultPrevented || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || (ev.button !== undefined && ev.button !== 0)) return;
                                ev.preventDefault();
                                el.classList.remove('is-unread');
                                bumpBadges(-1);
                                var fd = new FormData();
                                fd.append('csrf', csrf);
                                fd.append('action', 'read');
                                fd.append('id', el.getAttribute('data-nid'));
                                fetch('account.php?tab=notifications', {
                                    method: 'POST', credentials: 'same-origin', body: fd
                                }).catch(function () {}).then(function () {
                                    if (href) window.location.href = href;
                                });
                            });
                        });
                    })();
                    </script>
                <?php endif; ?>

            <?php elseif ($tab === 'settings'): ?>
                <section class="catalog-block profile-about">
                    <div class="section-head">
                        <h2>Редактировать профиль</h2>
                    </div>
                    <form class="form-card" method="post" action="account.php?tab=settings">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update">
                        <div class="form-grid">
                            <div class="form-row">
                                <label for="pname">Имя *</label>
                                <input type="text" id="pname" name="name" maxlength="100" required value="<?= e($old['name']) ?>">
                                <?php if (isset($editErrors['name'])): ?><div class="field-error"><?= e($editErrors['name']) ?></div><?php endif; ?>
                            </div>
                            <div class="form-row">
                                <label for="pcity">Город *</label>
                                <input type="text" id="pcity" name="city" maxlength="100" required value="<?= e($old['city']) ?>">
                                <?php if (isset($editErrors['city'])): ?><div class="field-error"><?= e($editErrors['city']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="form-row">
                            <button class="btn btn-primary" type="submit">Сохранить</button>
                        </div>
                    </form>

                    <button class="pass-toggle-card<?= $passOpen ? ' is-open' : '' ?>" type="button" aria-expanded="<?= $passOpen ? 'true' : 'false' ?>">
                        Сменить пароль
                        <span class="pass-toggle-arrow" aria-hidden="true"></span>
                    </button>
                    <form class="form-card pass-card<?= $passOpen ? ' is-open' : '' ?>" method="post" action="account.php?tab=settings">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="password">
                        <div class="form-grid profile-pass-grid">
                            <div class="form-row">
                                <label for="pw_current">Текущий пароль *</label>
                                <input type="password" id="pw_current" name="password_current" required autocomplete="current-password">
                                <?php if (isset($editErrors['password_current'])): ?><div class="field-error"><?= e($editErrors['password_current']) ?></div><?php endif; ?>
                            </div>
                            <div class="form-row">
                                <label for="pw_new">Новый пароль *</label>
                                <input type="password" id="pw_new" name="password_new" required minlength="8" autocomplete="new-password">
                                <?php if (isset($editErrors['password_new'])): ?><div class="field-error"><?= e($editErrors['password_new']) ?></div><?php endif; ?>
                            </div>
                            <div class="form-row">
                                <label for="pw_new2">Повторите новый пароль *</label>
                                <input type="password" id="pw_new2" name="password_new2" required minlength="8" autocomplete="new-password">
                                <?php if (isset($editErrors['password_new2'])): ?><div class="field-error"><?= e($editErrors['password_new2']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="form-row">
                            <button class="btn btn-secondary" type="submit">Сменить пароль</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>
<?php require __DIR__ . '/includes/footer.php'; ?>
