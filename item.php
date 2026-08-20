<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = pdo();

$stmt = $pdo->prepare(
    'SELECT i.*, u.name AS seller_name, u.city AS seller_city, u.rating, u.sold_count, u.verified
     FROM items i JOIN users u ON u.id = i.user_id
     WHERE i.id = ?'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    $active = 'items';
    $pageTitle = 'Объявление не найдено — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="not-found"><h1>Объявление не найдено</h1><p class="empty">Возможно, его уже удалили.</p><p><a class="btn btn-primary" href="index.php?type=all">Вернуться в каталог</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$currentUser = current_user();
$isOwner = $currentUser && (int) $currentUser['id'] === (int) $item['user_id'];
$sellerRating = seller_rating($pdo, (int) $item['user_id']);
$canReview = $currentUser && can_review($pdo, (int) $currentUser['id'], 'item', $id);

$favItems = [];
if ($currentUser) {
    [$favItems, $favAuctions] = favorite_state($pdo, (int) $currentUser['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
        if (($_POST['toggle_fav'] ?? '') === 'item') {
            toggle_favorite($pdo, (int) $currentUser['id'], 'item', $id);
            header('Location: item.php?id=' . $id);
            exit;
        }
        if (($_POST['action'] ?? '') === 'review') {
            $err = add_review($pdo, (int) $currentUser['id'], 'item', $id, (int) ($_POST['rating'] ?? 0), (string) ($_POST['text'] ?? ''));
            header('Location: item.php?id=' . $id . ($err === null ? '&ok=review' : '&review_error=' . urlencode($err)));
            exit;
        }
        if (($_POST['action'] ?? '') === 'confirm') {
            $err = confirm_receipt($pdo, (int) $currentUser['id'], 'item', $id);
            header('Location: item.php?id=' . $id . ($err === null ? '&ok=confirm' : '&confirm_error=' . urlencode($err)));
            exit;
        }
        if (($_POST['action'] ?? '') === 'report') {
            $err = report_listing($pdo, (int) $currentUser['id'], 'item', $id, (string) ($_POST['reason'] ?? ''), (string) ($_POST['comment'] ?? ''));
            header('Location: item.php?id=' . $id . ($err === null ? '&ok=report' : '&report_error=' . urlencode($err)));
            exit;
        }
    }
}
$isBuyer = $currentUser && (int) $currentUser['id'] === (int) $item['buyer_id'];
if ($item['status'] !== 'active' && !$isOwner && !($item['status'] === 'sold' && $isBuyer)) {
    http_response_code(404);
    $active = 'items';
    $pageTitle = 'Объявление не найдено — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="not-found"><h1>Объявление не найдено</h1><p class="empty">Оно снято с публикации или продано.</p><p><a class="btn btn-primary" href="index.php?type=all">Вернуться в каталог</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
if ($currentUser && $_SERVER['REQUEST_METHOD'] === 'GET') {
    record_view($pdo, (int) $currentUser['id'], 'item', $id);
}

$similar = $pdo->prepare(
    'SELECT * FROM items WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 4'
);
$similar->execute([$item['category'], $id]);
$similar = $similar->fetchAll();

$photos = photos_of($item);
$isGive = (int) $item['is_giveaway'] === 1;

$active = 'items';
$pageTitle = $item['title'] . ' — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <?php if (($ok = $_GET['ok'] ?? '') === 'review'): ?>
        <div class="alert alert-ok">Спасибо! Отзыв опубликован.</div>
    <?php elseif ($ok === 'confirm'): ?>
        <div class="alert alert-ok">Спасибо! Сделка подтверждена.</div>
    <?php elseif ($ok === 'report'): ?>
        <div class="alert alert-ok">Спасибо! Жалоба отправлена, мы рассмотрим её.</div>
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
        <a href="index.php?type=items">Объявления</a>
        <span class="bc-sep" aria-hidden="true">›</span>
        <span class="bc-current"><?= e(mb_strimwidth($item['title'], 0, 48, '…')) ?></span>
    </nav>
    <section class="item-layout">
        <div class="gallery">
            <?php if ($photos): ?>
                <img id="galleryMain" class="gallery-main" src="<?= e($photos[0]) ?>" alt="<?= e($item['title']) ?>">
                <?php if (count($photos) > 1): ?>
                    <div class="gallery-thumbs">
                        <?php foreach ($photos as $i => $ph): ?>
                            <button type="button" class="gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= e($ph) ?>" aria-label="Фото <?= $i + 1 ?>">
                                <img src="<?= e($ph) ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <img class="gallery-main" src="<?= e(placeholder_photo('Кроха')) ?>" alt="<?= e($item['title']) ?>">
            <?php endif; ?>
        </div>

        <div class="item-info">
            <div class="item-tags">
                <span class="chip"><?= e($item['category']) ?></span>
                <?php if ($isGive): ?>
                    <span class="badge badge-give static">Отдам даром</span>
                <?php else: ?>
                    <span class="chip"><?= e($item['condition_label'] ?: 'б/у') ?></span>
                <?php endif; ?>
                <?php if ($isOwner && $item['status'] !== 'active'): ?>
                    <span class="chip status-<?= e($item['status']) ?>"><?= $item['status'] === 'sold' ? 'Продано' : 'Снято с публикации' ?></span>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <form class="item-tags-fav" method="post" action="item.php?id=<?= $id ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="toggle_fav" value="item">
                        <button class="card-fav<?= in_array($id, $favItems, true) ? ' is-fav' : '' ?>" type="submit" aria-label="<?= in_array($id, $favItems, true) ? 'Убрать из избранного' : 'В избранное' ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <h1 class="item-title"><?= e($item['title']) ?></h1>
            <div class="item-price"><?= $isGive ? 'Отдам даром' : money((int) $item['price']) ?></div>

            <dl class="specs">
                <?php if (!$isGive): ?>
                    <div><dt>Состояние</dt><dd><?= e($item['condition_label'] ?: 'б/у') ?></dd></div>
                <?php endif; ?>
                <?php if ($item['size'] !== null && $item['size'] !== ''): ?>
                    <div><dt>Размер</dt><dd><?= e($item['size']) ?></dd></div>
                <?php endif; ?>
                <?php if ($item['season'] !== null && $item['season'] !== ''): ?>
                    <div><dt>Сезон</dt><dd><?= e($item['season']) ?></dd></div>
                <?php endif; ?>
                <?php if (($age = age_range($item)) !== null): ?>
                    <div><dt>Возраст</dt><dd><?= e($age) ?></dd></div>
                <?php endif; ?>
                <div><dt>Город</dt><dd><?= e($item['city']) ?></dd></div>
                <div><dt>Размещено</dt><dd><?= e(date('d.m.Y', strtotime($item['created_at']))) ?></dd></div>
            </dl>

            <?php if ($item['description'] !== null && $item['description'] !== ''): ?>
                <div class="item-desc">
                    <h2>Описание</h2>
                    <p><?= nl2br(e($item['description'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="seller-card">
                <span class="avatar"><?= e(initials($item['seller_name'])) ?></span>
                <div class="seller-meta">
                    <strong class="seller-name">
                        <a href="profile.php?id=<?= (int) $item['user_id'] ?>"><?= e($item['seller_name']) ?></a>
                        <?php if ((int) $item['verified'] === 1): ?><span class="badge-verified">Проверенный</span><?php endif; ?>
                    </strong>
                    <span class="seller-sub">
                        <?= e($item['seller_city']) ?> · <?= e(rating_label($sellerRating)) ?>
                        <?php if ((int) $item['sold_count'] > 0): ?>· продано <?= (int) $item['sold_count'] ?><?php endif; ?>
                    </span>
                </div>
                <div class="seller-actions">
                <?php if (!$isOwner): ?>
                    <a class="btn btn-secondary" href="message.php?to=<?= (int) $item['user_id'] ?>&amp;item=<?= $id ?>"<?php if (!$currentUser): ?> data-login-next="<?= e('message.php?to=' . (int) $item['user_id'] . '&item=' . $id) ?>"<?php endif; ?>>Написать продавцу</a>
                <?php endif; ?>
                <?php if ($currentUser && !$isOwner): ?>
                    <button class="btn btn-secondary btn-report" type="button" data-report-trigger>Пожаловаться</button>
                    <dialog class="post-modal report-modal" id="reportModal" aria-labelledby="reportModalTitle">
                        <div class="post-modal-head">
                            <h2 id="reportModalTitle">Пожаловаться</h2>
                            <button class="post-modal-close" type="button" aria-label="Закрыть">×</button>
                        </div>
                        <div class="post-modal-body">
                            <form class="report-form" method="post" action="item.php?id=<?= $id ?>">
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

            <?php if ($isBuyer && $item['status'] === 'sold'): ?>
                <div class="deal-box">
                    <?php if ($item['confirmed_at'] !== null && $item['confirmed_at'] !== ''): ?>
                        <div class="alert alert-ok">Сделка подтверждена · <?= e(date('d.m.Y', strtotime($item['confirmed_at']))) ?></div>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="item.php?id=<?= $id ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button class="btn btn-primary" type="submit">Подтвердить получение</button>
                            <span class="hint">Товар получен — сообщите продавцу.</span>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($canReview): ?>
                <div class="review-box" data-review-id="item:<?= $id ?>">
                    <h2>Оставьте отзыв продавцу</h2>
                    <p class="muted">Сделка завершена — поделитесь впечатлениями о покупателе-продавце.</p>
                    <form class="form-card" method="post" action="item.php?id=<?= $id ?>">
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
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($similar): ?>
    <section class="catalog-block">
        <h2>Похожие объявления</h2>
        <div class="grid">
            <?php foreach ($similar as $it): ?>
                <?php require __DIR__ . '/includes/card_item.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <script>
    document.querySelectorAll('.gallery-thumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('galleryMain').src = btn.dataset.src;
            document.querySelectorAll('.gallery-thumb').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
        });
    });
    </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
