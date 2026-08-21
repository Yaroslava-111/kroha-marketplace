<?php
/** Боковая панель кабинета.
 *  Требует авторизацию и pdo(). Активная вкладка — $accountActive (ключ меню или 'messages'/'notifications'/'admin'). */
$__uid = (int) current_user_id();
$__pdo = pdo();
$pendingCount = count(pending_reviews_of($__pdo, $__uid));
[$__favI, $__favA] = favorite_state($__pdo, $__uid);
$overviewFav = count($__favI) + count($__favA);
$unread = unread_notifications_count($__pdo, $__uid);
$unreadMsgs = unread_messages_count($__pdo, $__uid);
$__isAdmin = (int) ((current_user()['is_admin'] ?? 0)) === 1;
$accountActive = (string) ($accountActive ?? 'overview');

$__menu = [
    'overview' => ['Обзор', 'account.php?tab=overview'],
    'items' => ['Мои объявления', 'account.php?tab=items'],
    'auctions' => ['Мои аукционы', 'account.php?tab=auctions'],
    'bids' => ['Мои ставки', 'account.php?tab=bids'],
    'reviews' => ['Отзывы', 'account.php?tab=reviews'],
    'favorites' => ['Избранное', 'account.php?tab=favorites'],
    'searches' => ['Поиски', 'account.php?tab=searches'],
    'history' => ['История', 'account.php?tab=history'],
    'settings' => ['Настройки', 'account.php?tab=settings'],
];
?>
<aside class="account-aside">
    <nav class="account-menu" aria-label="Разделы кабинета">
        <?php foreach ($__menu as $__key => $__item): ?>
            <a class="account-menu-item<?= $accountActive === $__key ? ' is-active' : '' ?>" href="<?= e($__item[1]) ?>">
                <?= e($__item[0]) ?>
                <?php if ($__key === 'reviews' && $pendingCount > 0): ?>
                    <span class="account-menu-count"><?= $pendingCount ?></span>
                <?php elseif ($__key === 'favorites' && $overviewFav > 0): ?>
                    <span class="account-menu-count"><?= $overviewFav ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <div class="account-menu-sep"></div>
        <a class="account-menu-item<?= $accountActive === 'messages' ? ' is-active' : '' ?>" href="account.php?tab=messages">
            Сообщения<?php if ($unreadMsgs > 0): ?><span class="account-menu-count is-urgent"><?= $unreadMsgs ?></span><?php endif; ?>
        </a>
        <a class="account-menu-item<?= $accountActive === 'notifications' ? ' is-active' : '' ?>" href="account.php?tab=notifications">
            Уведомления<?php if ($unread > 0): ?><span class="account-menu-count is-urgent"><?= $unread ?></span><?php endif; ?>
        </a>
        <?php if ($__isAdmin): ?>
            <a class="account-menu-item<?= $accountActive === 'admin' ? ' is-active' : '' ?>" href="admin.php">Админ</a>
        <?php endif; ?>
    </nav>
</aside>