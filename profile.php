<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = pdo();
$me = current_user();

$id = (int) ($_GET['id'] ?? 0);
if ($id === 0) {
    require_login();
    $id = current_user_id();
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    $pageTitle = 'Профиль не найден — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="not-found"><h1>Профиль не найден</h1><p class="empty">Возможно, пользователь удалил аккаунт.</p><p><a class="btn btn-primary" href="index.php?type=all">Вернуться в каталог</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isMine = $me && (int) $me['id'] === $id;

$tab = (string) ($_GET['tab'] ?? 'items');
if (!in_array($tab, ['items', 'auctions', 'reviews'], true)) {
    $tab = 'items';
}

$profileRating = seller_rating($pdo, $id);
$profileReviews = reviews_of($pdo, $id);

$itemsStmt = $pdo->prepare(
    'SELECT * FROM items WHERE user_id = ? AND status = \'active\' ORDER BY created_at DESC LIMIT 12'
);
$itemsStmt->execute([$id]);
$profileItems = $itemsStmt->fetchAll();

$auctionsStmt = $pdo->prepare(
    'SELECT a.*, u.name AS seller_name, u.city AS seller_city, COUNT(b.id) AS bid_count
     FROM auctions a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN bids b ON b.auction_id = a.id
     WHERE a.user_id = ? AND a.status = \'active\'
     GROUP BY a.id
     ORDER BY a.created_at DESC LIMIT 12'
);
$auctionsStmt->execute([$id]);
$profileAuctions = $auctionsStmt->fetchAll();

$sellerRatings = seller_ratings_map($pdo, [(int) $id]);

$active = 'profile';
$pageTitle = $user['name'] . ' — профиль · ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="profile-head">
        <span class="avatar avatar-xl"><?= e(initials($user['name'])) ?></span>
        <div class="profile-meta">
            <h1><?= e($user['name']) ?><?php if ((int) $user['verified'] === 1): ?> <span class="badge-verified">Проверенный</span><?php endif; ?></h1>
            <p class="seller-sub">
                <?= e($user['city']) ?> · <?= e(rating_label($profileRating)) ?>
                <?php if ((int) $user['sold_count'] > 0): ?> · продано <?= (int) $user['sold_count'] ?><?php endif; ?>
                · на Крохе с <?= e(date('d.m.Y', strtotime($user['created_at']))) ?>
            </p>
        </div>
        <?php if ($isMine): ?>
            <div class="profile-actions">
                <a class="btn btn-secondary" href="account.php">Перейти в кабинет</a>
            </div>
        <?php else: ?>
            <div class="profile-actions">
                <a class="btn btn-secondary" href="message.php?to=<?= $id ?>"<?php if (!$me): ?> data-login-next="<?= e('message.php?to=' . $id) ?>"<?php endif; ?>>Написать сообщение</a>
            </div>
        <?php endif; ?>
    </section>

    <nav class="profile-tabs" aria-label="Разделы профиля">
        <a class="tab<?= $tab === 'items' ? ' is-active' : '' ?>" href="profile.php?id=<?= $id ?>&amp;tab=items">Объявления <span class="muted">(<?= count($profileItems) ?>)</span></a>
        <a class="tab<?= $tab === 'auctions' ? ' is-active' : '' ?>" href="profile.php?id=<?= $id ?>&amp;tab=auctions">Аукционы <span class="muted">(<?= count($profileAuctions) ?>)</span></a>
        <a class="tab<?= $tab === 'reviews' ? ' is-active' : '' ?>" href="profile.php?id=<?= $id ?>&amp;tab=reviews">Отзывы <span class="muted">(<?= count($profileReviews) ?>)</span></a>
    </nav>

    <?php if ($tab === 'items'): ?>
    <section class="catalog-block">
        <div class="section-head">
            <h2>Активные объявления <span class="muted">(<?= count($profileItems) ?>)</span></h2>
        </div>
        <?php if ($profileItems): ?>
            <div class="grid">
                <?php foreach ($profileItems as $it): ?>
                    <?php require __DIR__ . '/includes/card_item.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Активных объявлений пока нет.</p>
        <?php endif; ?>
    </section>
    <?php elseif ($tab === 'auctions'): ?>
    <section class="catalog-block">
        <div class="section-head">
            <h2>Активные аукционы <span class="muted">(<?= count($profileAuctions) ?>)</span></h2>
        </div>
        <?php if ($profileAuctions): ?>
            <div class="grid">
                <?php foreach ($profileAuctions as $a): ?>
                    <?php require __DIR__ . '/includes/card_auction.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Аукционов пока нет.</p>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="catalog-block">
        <div class="section-head">
            <h2>Отзывы <span class="muted">(<?= count($profileReviews) ?>)</span></h2>
        </div>
        <?php if ($profileReviews): ?>
            <div class="reviews-list">
                <?php foreach ($profileReviews as $rv): ?>
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
        <?php else: ?>
            <p class="empty">Отзывов пока нет.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
