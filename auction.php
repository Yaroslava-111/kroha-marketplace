<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = pdo();

$stmt = $pdo->prepare(
    'SELECT a.*, u.name AS seller_name, u.city AS seller_city, u.rating, u.sold_count, u.verified
     FROM auctions a JOIN users u ON u.id = a.user_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$auction = $stmt->fetch();

if (!$auction) {
    http_response_code(404);
    $active = 'auctions';
    $pageTitle = 'Аукцион не найден — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="not-found"><h1>Аукцион не найден</h1><p class="empty">Возможно, он завершён или удалён.</p><p><a class="btn btn-primary" href="index.php?type=auctions">К аукционам</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$state = auction_state($pdo, $auction);
$ended = $auction['status'] === 'finished' || strtotime($auction['end_at']) <= time();

if ($auction['status'] === 'active' && !$ended) {
    if ((int) $state['price'] !== (int) $auction['current_price']) {
        $pdo->prepare('UPDATE auctions SET current_price = ? WHERE id = ?')
            ->execute([$state['price'], $id]);
        $auction['current_price'] = $state['price'];
    }
}

if ($auction['status'] === 'active' && $ended) {
    $auction = finalize_auction($pdo, $id) ?? $auction;
}

$bidsStmt = $pdo->prepare(
    'SELECT b.*, u.name AS bidder_name, u.city AS bidder_city
     FROM bids b JOIN users u ON u.id = b.user_id
     WHERE b.auction_id = ?
     ORDER BY b.created_at DESC, b.id DESC'
);
$bidsStmt->execute([$id]);
$bids = $bidsStmt->fetchAll();

$winner = null;
if ($auction['status'] === 'finished') {
    if ((int) $auction['winner_bid_id'] > 0) {
        $w = $pdo->prepare('SELECT b.amount, u.name, b.user_id FROM bids b JOIN users u ON u.id = b.user_id WHERE b.id = ?');
        $w->execute([(int) $auction['winner_bid_id']]);
        $winner = $w->fetch();
    }
    if (!$winner && $state['leader']) {
        $winner = ['amount' => $state['leader']['eff'], 'name' => $state['leader']['bidder_name'], 'user_id' => $state['leader']['user_id']];
    }
}

$currentUser = current_user();
$winnerUserId = 0;
if ($auction['status'] === 'finished' && (int) $auction['winner_bid_id'] > 0) {
    $wb = $pdo->prepare('SELECT user_id FROM bids WHERE id = ?');
    $wb->execute([(int) $auction['winner_bid_id']]);
    $winnerUserId = (int) $wb->fetchColumn();
}
$canReview = $currentUser && $auction['status'] === 'finished' && can_review($pdo, (int) $currentUser['id'], 'auction', $id);

if ($currentUser && $_SERVER['REQUEST_METHOD'] === 'GET') {
    record_view($pdo, (int) $currentUser['id'], 'auction', $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (!is_logged_in()) {
        header('Location: login.php?next=' . urlencode('auction.php?id=' . $id));
        exit;
    }
    $err = add_review($pdo, current_user_id(), 'auction', $id, (int) ($_POST['rating'] ?? 0), (string) ($_POST['text'] ?? ''));
    header('Location: auction.php?id=' . $id . ($err === null ? '&ok=review' : '&review_error=' . urlencode($err)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!is_logged_in()) {
        header('Location: login.php?next=' . urlencode('auction.php?id=' . $id));
        exit;
    }
    $err = confirm_receipt($pdo, current_user_id(), 'auction', $id);
    header('Location: auction.php?id=' . $id . ($err === null ? '&ok=confirm' : '&confirm_error=' . urlencode($err)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report') {
    if (!is_logged_in() || !csrf_check()) {
        header('Location: login.php?next=' . urlencode('auction.php?id=' . $id));
        exit;
    }
    $err = report_listing($pdo, current_user_id(), 'auction', $id, (string) ($_POST['reason'] ?? ''), (string) ($_POST['comment'] ?? ''));
    header('Location: auction.php?id=' . $id . ($err === null ? '&ok=report' : '&report_error=' . urlencode($err)));
    exit;
}

$bidError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['toggle_fav'] ?? '') === 'auction') {
    if (is_logged_in()) {
        if (csrf_check()) {
            toggle_favorite($pdo, current_user_id(), 'auction', $id);
        }
        header('Location: auction.php?id=' . $id);
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ended) {
    if (!is_logged_in()) {
        header('Location: login.php?next=' . urlencode('auction.php?id=' . $id));
        exit;
    }
    if (!csrf_check()) {
        $bidError = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $action = (string) ($_POST['action'] ?? 'bid');
        $bidderId = current_user_id();

        if ($bidderId === 0 || $bidderId === (int) $auction['user_id']) {
            $bidError = 'Вы не можете участвовать в собственном аукционе.';
        } elseif ($action === 'buy' && (int) $auction['bin_price'] > 0) {
            $amount = (int) $auction['bin_price'];
            $pdo->prepare('INSERT INTO bids (auction_id, user_id, amount, is_proxy, created_at) VALUES (?, ?, ?, 0, ?)')
                ->execute([$id, $bidderId, $amount, date('Y-m-d H:i:s')]);
            $bidId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE auctions SET current_price = ?, status = \'finished\', winner_bid_id = ? WHERE id = ?')
                ->execute([$amount, $bidId, $id]);
            notify(
                $pdo,
                (int) $auction['user_id'],
                'bought',
                'Ваш лот «' . $auction['title'] . '» куплен по «Купить сейчас» за ' . money($amount) . '.',
                'auction.php?id=' . $id
            );
            bump_sold_count($pdo, (int) $auction['user_id']);
            header('Location: auction.php?id=' . $id . '&ok=buy');
            exit;
        } elseif ($action === 'bid') {
            $isProxy = !empty($_POST['is_proxy']);
            $amount = (int) ($_POST['amount'] ?? 0);
            $limit = (int) ($_POST['proxy_limit'] ?? 0);
            $current = (int) $auction['current_price'];
            $min = $current + (int) $auction['min_bid_step'];
            $oldLeader = auction_state($pdo, $auction)['leader'];

            if ($isProxy) {
                if ($limit < $min) {
                    $bidError = 'Лимит автоставки должен быть не меньше ' . money($min) . '.';
                } else {
                    $pdo->prepare('INSERT INTO bids (auction_id, user_id, amount, is_proxy, proxy_limit, created_at) VALUES (?, ?, ?, 1, ?, ?)')
                        ->execute([$id, $bidderId, $limit, $limit, date('Y-m-d H:i:s')]);
                    $placed = true;
                }
            } else {
                if ($amount < $min) {
                    $bidError = 'Минимальная ставка — ' . money($min) . '.';
                } else {
                    $pdo->prepare('INSERT INTO bids (auction_id, user_id, amount, is_proxy, created_at) VALUES (?, ?, ?, 0, ?)')
                        ->execute([$id, $bidderId, $amount, date('Y-m-d H:i:s')]);
                    $placed = true;
                }
            }

            if (!empty($placed)) {
                $state = auction_state($pdo, $auction);
                $auction['current_price'] = $state['price'];

                $newLeader = $state['leader'];
                if ($oldLeader && $newLeader && (int) $newLeader['user_id'] !== (int) $oldLeader['user_id']) {
                    notify(
                        $pdo,
                        (int) $oldLeader['user_id'],
                        'outbid',
                        'Вашу ставку на лот «' . $auction['title'] . '» перебили — сейчас впереди ' . $newLeader['bidder_name'] . '.',
                        'auction.php?id=' . $id
                    );
                }

                $extended = false;
                $endTs = strtotime($auction['end_at']);
                if ($endTs - time() < 180) {
                    $newEnd = date('Y-m-d H:i:s', time() + 180);
                    $pdo->prepare('UPDATE auctions SET end_at = ? WHERE id = ?')
                        ->execute([$newEnd, $id]);
                    $auction['end_at'] = $newEnd;
                    $extended = true;
                }

                $pdo->prepare('UPDATE auctions SET current_price = ? WHERE id = ?')
                    ->execute([$state['price'], $id]);

                header('Location: auction.php?id=' . $id . '&ok=bid' . ($extended ? '&ext=1' : ''));
                exit;
            }
        } else {
            $bidError = 'Неизвестное действие.';
        }
    }
}

$minBid = (int) $auction['current_price'] + (int) $auction['min_bid_step'];
$photos = photos_of($auction);

$favAuctions = [];
$sellerRating = seller_rating($pdo, (int) $auction['user_id']);
if ($currentUser) {
    [$favItems, $favAuctions] = favorite_state($pdo, (int) $currentUser['id']);
}

$active = 'auctions';
$pageTitle = $auction['title'] . ' — аукцион · ' . APP_NAME;
$metaDesc = mb_strimwidth('Аукцион: текущая цена ' . money((int) $auction['current_price']) . '. Завершение ' . date('d.m.Y H:i', strtotime($auction['end_at'])) . '. ' . $auction['title'], 0, 160, '…');
$metaImg = first_photo($auction);
require __DIR__ . '/includes/header.php';
?>
    <?php if (($ok = $_GET['ok'] ?? '') !== ''): ?>
        <div class="alert alert-ok">
            <?php if ($ok === 'bid'): ?>Ставка принята.<?php endif; ?>
            <?php if ($ok === 'buy'): ?>Лот куплен по цене «Купить сейчас».<?php endif; ?>
            <?php if ($ok === 'create'): ?>Лот создан и опубликован.<?php endif; ?>
            <?php if ($ok === 'review'): ?>Спасибо! Отзыв опубликован.<?php endif; ?>
            <?php if ($ok === 'confirm'): ?>Спасибо! Сделка подтверждена.<?php endif; ?>
            <?php if ($ok === 'report'): ?>Спасибо! Жалоба отправлена, мы рассмотрим её.<?php endif; ?>
            <?php if ($ok === 'bid' && !empty($_GET['ext'])): ?><br>Время продлено на 3 минуты (анти-снайпинг).<?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (($re = $_GET['review_error'] ?? '') !== ''): ?>
        <div class="alert alert-error"><?= e($re) ?></div>
    <?php endif; ?>
    <?php if (($ce = $_GET['confirm_error'] ?? '') !== ''): ?>
        <div class="alert alert-error"><?= e($ce) ?></div>
    <?php endif; ?>
    <?php if (($rpe = $_GET['report_error'] ?? '') !== ''): ?>
        <div class="alert alert-error"><?= e($rpe) ?></div>
    <?php endif; ?>

    <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php?type=all">Каталог</a>
        <span class="bc-sep" aria-hidden="true">›</span>
        <a href="index.php?type=auctions">Аукционы</a>
        <span class="bc-sep" aria-hidden="true">›</span>
        <span class="bc-current"><?= e(mb_strimwidth($auction['title'], 0, 48, '…')) ?></span>
    </nav>

    <section class="item-layout">
        <div class="gallery" data-lightbox>
            <img class="gallery-main" src="<?= e(first_photo($auction)) ?>" alt="<?= e($auction['title']) ?>">
            <?php if (count($photos) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($photos as $i => $ph): ?>
                        <button type="button" class="gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= e($ph) ?>">
                            <img src="<?= e(thumb_of($ph)) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="item-info">
            <div class="item-tags">
                <span class="chip"><?= e($auction['category']) ?></span>
                <span class="chip"><?= e($auction['condition_label'] ?: 'б/у') ?></span>
                <?php if ($currentUser): ?>
                    <form class="item-tags-fav" method="post" action="auction.php?id=<?= $id ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="toggle_fav" value="auction">
                        <button class="card-fav<?= in_array($id, $favAuctions, true) ? ' is-fav' : '' ?>" type="submit" aria-label="<?= in_array($id, $favAuctions, true) ? 'Убрать из избранного' : 'В избранное' ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <h1 class="item-title"><?= e($auction['title']) ?></h1>

            <?php if ($ended): ?>
                <div class="winner-banner<?= $winner ? '' : ' is-empty' ?>">
                    <div class="winner-banner-head">
                        <span class="winner-banner-check">✓</span>
                        Аукцион завершён
                    </div>
                    <?php if ($winner): ?>
                        <div class="winner-banner-body">
                            <span class="avatar"><?= e(initials($winner['name'])) ?></span>
                            <div class="winner-banner-info">
                                <span class="winner-banner-label">Победитель</span>
                                <strong class="winner-banner-name"><?= e($winner['name']) ?></strong>
                            </div>
                            <div class="winner-banner-price">
                                <span class="winner-banner-label">Цена</span>
                                <span class="amount"><?= money((int) $winner['amount']) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="winner-banner-body">
                            <div class="winner-banner-info">
                                <span class="winner-banner-label">Итог</span>
                                <strong class="winner-banner-name">Ставок не было — лот не продан</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="price-hero">
                <span class="label"><?= $ended ? 'Цена продажи' : 'Текущая цена' ?></span>
                <span class="amount"><?= money((int) $auction['current_price']) ?></span>
                <span class="hint">старт <?= money((int) $auction['start_price']) ?> · шаг <?= money((int) $auction['min_bid_step']) ?>
                    <?php if ($ended): ?>
                        · завершён
                    <?php else: ?>
                        · <span class="timer" data-end="<?= (int) strtotime($auction['end_at']) ?>"><?= e(format_countdown(seconds_until($auction['end_at']))) ?></span>
                    <?php endif; ?>
                </span>
                <?php if (!$ended && $state['leader']): ?>
                    <span class="hint">Впереди: <strong><?= e($state['leader']['bidder_name']) ?></strong> (<?= e($state['leader']['bidder_city']) ?>)</span>
                <?php endif; ?>
            </div>

            <?php if (!$ended): ?>
                <?php if ($bidError): ?>
                    <div class="alert alert-error"><?= e($bidError) ?></div>
                <?php endif; ?>

                <?php if ($currentUser): ?>
                <form class="bid-form" method="post" action="auction.php?id=<?= $id ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <span class="hint">Участвуете как: <strong><?= e($currentUser['name']) ?></strong> (<?= e($currentUser['city']) ?>)</span>
                    <div class="field" id="manualField">
                        <label for="amount">Ставка, ₽</label>
                        <input type="number" id="amount" name="amount" min="<?= $minBid ?>" step="<?= (int) $auction['min_bid_step'] ?>" value="<?= $minBid ?>">
                    </div>
                    <div class="field" id="proxyField" style="display:none">
                        <label for="proxy_limit">Лимит автоставки, ₽</label>
                        <input type="number" id="proxy_limit" name="proxy_limit" min="<?= $minBid ?>" step="<?= (int) $auction['min_bid_step'] ?>" value="<?= $minBid ?>">
                    </div>
                    <label class="proxy-toggle">
                        <input type="checkbox" id="is_proxy" name="is_proxy" value="1"> автоставка до лимита
                    </label>
                    <button class="btn btn-primary" type="submit" name="action" value="bid">Сделать ставку</button>
                    <?php if ((int) $auction['bin_price'] > 0): ?>
                        <button class="btn btn-secondary" type="submit" name="action" value="buy">Купить сейчас за <?= money((int) $auction['bin_price']) ?></button>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                    <p class="empty">Чтобы делать ставки, <a class="btn btn-secondary" href="login.php?next=<?= urlencode('auction.php?id=' . $id) ?>">Войдите</a> или <a href="register.php">зарегистрируйтесь</a>.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($auction['description'] !== null && $auction['description'] !== ''): ?>
                <div class="item-desc">
                    <h2>Описание</h2>
                    <p><?= nl2br(e($auction['description'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="seller-card">
                <span class="avatar"><?= e(initials($auction['seller_name'])) ?></span>
                <div class="seller-meta">
                    <strong class="seller-name">
                        <a href="profile.php?id=<?= (int) $auction['user_id'] ?>"><?= e($auction['seller_name']) ?></a>
                        <?php if ((int) $auction['verified'] === 1): ?><span class="badge-verified">Проверенный</span><?php endif; ?>
                    </strong>
                    <span class="seller-sub">
                        <?= e($auction['seller_city']) ?> · <?= e(rating_label($sellerRating)) ?>
                        <?php if ((int) $auction['sold_count'] > 0): ?>· продано <?= (int) $auction['sold_count'] ?><?php endif; ?>
                    </span>
                </div>
                <div class="seller-actions">
                <?php if (!$currentUser || (int) $currentUser['id'] !== (int) $auction['user_id']): ?>
                    <a class="btn btn-secondary" href="message.php?to=<?= (int) $auction['user_id'] ?>&amp;auction=<?= $id ?>"<?php if (!$currentUser): ?> data-login-next="<?= e('message.php?to=' . (int) $auction['user_id'] . '&auction=' . $id) ?>"<?php endif; ?>>Написать продавцу</a>
                <?php endif; ?>
                <?php if ($currentUser && (int) $currentUser['id'] !== (int) $auction['user_id']): ?>
                    <button class="btn btn-secondary btn-report" type="button" data-report-trigger>Пожаловаться</button>
                    <dialog class="post-modal report-modal" id="reportModal" aria-labelledby="reportModalTitle">
                        <div class="post-modal-head">
                            <h2 id="reportModalTitle">Пожаловаться</h2>
                            <button class="post-modal-close" type="button" aria-label="Закрыть">×</button>
                        </div>
                        <div class="post-modal-body">
                            <form class="report-form" method="post" action="auction.php?id=<?= $id ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="report">
                                <label class="report-label">Причина</label>
                                <select name="reason" required>
                                    <option value="spam">Спам</option>
                                    <option value="fraud">Мошенничество</option>
                                    <option value="rules">Нарушение правил</option>
                                    <option value="wrong">Неверные данные</option>
                                    <option value="other">Другое</option>
                                </select>
                                <textarea name="comment" rows="3" maxlength="1000" placeholder="Комментарий (необязательно)"></textarea>
                                <button class="btn btn-danger" type="submit">Отправить жалобу</button>
                            </form>
                        </div>
                    </dialog>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($currentUser && $auction['status'] === 'finished' && $winnerUserId === (int) $currentUser['id']): ?>
        <section class="catalog-block deal-box">
            <?php if ($auction['confirmed_at'] !== null && $auction['confirmed_at'] !== ''): ?>
                <div class="alert alert-ok">Сделка подтверждена · <?= e(date('d.m.Y', strtotime($auction['confirmed_at']))) ?></div>
            <?php else: ?>
                <form class="inline-form" method="post" action="auction.php?id=<?= $id ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button class="btn btn-primary" type="submit">Подтвердить получение</button>
                    <span class="hint">Вы победили в аукционе — подтвердите, что получили лот.</span>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($canReview): ?>
        <section class="catalog-block review-box" data-review-id="auction:<?= $id ?>">
            <h2>Лот завершён — оставьте отзыв продавцу</h2>
            <p class="muted">Вы победили в этом аукционе. Поделитесь впечатлениями о сделке.</p>
            <form class="form-card" method="post" action="auction.php?id=<?= $id ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="review">
                <div class="review-stars">
                    <?php for ($r = 5; $r >= 1; $r--): ?>
                        <label class="review-star-opt">
                            <input type="radio" name="rating" value="<?= $r ?>" required>
                            <span><?= str_repeat('★', $r) ?><?= str_repeat('☆', 5 - $r) ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <div class="form-row">
                    <textarea name="text" maxlength="500" rows="3" placeholder="Как прошла сделка? (необязательно)"></textarea>
                </div>
                <div class="review-pending-actions">
                    <button class="btn btn-primary" type="submit">Отправить отзыв</button>
                    <button class="btn btn-secondary review-dismiss" type="button">Не сейчас</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="catalog-block">
        <h2>История ставок <span class="muted">(<?= count($bids) ?>)</span></h2>
        <?php if ($bids): ?>
            <table class="bids-table">
                <thead>
                    <tr><th>Кто</th><th>Ставка</th><th>Когда</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bids as $b): ?>
                        <tr>
                            <td><?= e($b['bidder_name']) ?> <span class="muted small">(<?= e($b['bidder_city']) ?>)</span></td>
                            <td><?= money((int) $b['amount']) ?><?= (int) $b['is_proxy'] === 1 ? ' <span class="small">(авто)</span>' : '' ?></td>
                            <td class="muted small"><?= e(date('d.m.Y H:i', strtotime($b['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty">Ставок пока нет. Станьте первым!</p>
        <?php endif; ?>
    </section>

    <script>
    var isProxy = document.getElementById('is_proxy');
    var manualField = document.getElementById('manualField');
    var proxyField = document.getElementById('proxyField');
    if (isProxy) {
        function syncProxy() {
            manualField.style.display = isProxy.checked ? 'none' : '';
            proxyField.style.display = isProxy.checked ? '' : 'none';
            if (isProxy.checked) proxyField.querySelector('input').focus();
        }
        isProxy.addEventListener('change', syncProxy);
        syncProxy();
    }

    document.querySelectorAll('.gallery-thumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var main = document.querySelector('.gallery-main');
            if (main) main.src = btn.dataset.src;
            document.querySelectorAll('.gallery-thumb').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
        });
    });
    </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
