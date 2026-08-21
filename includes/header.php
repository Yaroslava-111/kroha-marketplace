<?php
declare(strict_types=1);

if (!isset($active)) {
    $active = '';
}
$pageTitle = isset($pageTitle) ? $pageTitle : (APP_NAME . ' — ' . APP_TAGLINE);
$metaDesc = isset($metaDesc) ? $metaDesc : 'Кроха — маркетплейс детских вещей: объявления по фикс-цене, «отдам даром» и аукционы с автоставками. Коляски, одежда, игрушки по возрасту и размеру.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$metaBase = $scheme . '://' . $host;
$metaUrl = $metaBase . ($_SERVER['REQUEST_URI'] ?? '/');
$metaImg = isset($metaImg) ? $metaImg : 'assets/favicon.svg';
if (!str_starts_with($metaImg, 'http')) {
    $metaImg = $metaBase . '/' . ltrim($metaImg, '/');
}

$currentUser = current_user();
$unread = $currentUser ? unread_notifications_count($pdo, (int) $currentUser['id']) : 0;
$unreadMsgs = $currentUser ? unread_messages_count($pdo, (int) $currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:image" content="<?= e($metaImg) ?>">
    <meta property="og:url" content="<?= e($metaUrl) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;700;800&family=Nunito:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body<?= $currentUser ? ' data-logged="1"' : '' ?>>
<?php $catalogType = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' && in_array($active, ['all', 'items', 'auctions'], true)) ? $active : 'all'; ?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">К</span>
            <span class="brand-name"><?= e(APP_NAME) ?></span>
        </a>
        <div class="header-search-wrap">
            <form class="header-search" method="get" action="index.php" role="search">
                <input type="hidden" name="type" value="<?= e($catalogType) ?>">
                <input type="search" id="header-q" name="q" placeholder="Поиск товаров…" autocomplete="off" aria-label="Поиск по каталогу">
                <button class="btn btn-primary header-search-btn" type="submit">Найти</button>
            </form>
            <div class="search-suggest" id="searchSuggest" hidden></div>
        </div>
        <button class="nav-burger" type="button" id="navBurger" aria-expanded="false" aria-controls="mainNav" aria-label="Открыть меню">
            <span></span><span></span><span></span>
        </button>
        <nav class="main-nav" id="mainNav">
            <div class="nav-tabs" role="group" aria-label="Разделы каталога">
                <div class="nav-dropdown">
                    <a class="nav-dropdown-link<?= $active === 'all' || $active === '' ? ' is-active' : '' ?>" href="index.php?type=all" aria-haspopup="true" aria-expanded="false">
                        <svg class="nav-dropdown-grid" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
                        Каталог
                        <svg class="nav-dropdown-arrow" viewBox="0 0 12 8" width="10" height="7" aria-hidden="true"><path d="M1 1l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div class="nav-dropdown-menu">
                        <a class="nav-dropdown-item<?= $active === 'items' ? ' is-active' : '' ?>" href="index.php?type=items">
                            <span class="nav-dropdown-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            </span>
                            <span class="nav-dropdown-txt">
                                <span class="nav-dropdown-name">Объявления</span>
                                <span class="nav-dropdown-desc">Фикс-цена и даром</span>
                            </span>
                        </a>
                        <a class="nav-dropdown-item<?= $active === 'auctions' ? ' is-active' : '' ?>" href="index.php?type=auctions">
                            <span class="nav-dropdown-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.5 12.5-8 8a2.119 2.119 0 1 1-3-3l8-8"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/></svg>
                            </span>
                            <span class="nav-dropdown-txt">
                                <span class="nav-dropdown-name">Аукционы</span>
                                <span class="nav-dropdown-desc">Ставки и торги</span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            <?php if ($currentUser): ?>
                <a class="nav-fav" href="account.php?tab=favorites" aria-label="Избранное">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </a>
                <a class="nav-notif<?= $active === 'account' && ($_GET['tab'] ?? '') === 'notifications' ? ' is-active' : '' ?>" href="account.php?tab=notifications" aria-label="Уведомления">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span class="notif-badge<?= $unread === 0 ? ' is-empty' : '' ?>" data-count="<?= $unread ?>"><?= $unread ?></span>
                </a>
                <div class="nav-user">
                    <button class="user-toggle" type="button" aria-expanded="false" aria-haspopup="true" aria-label="Меню пользователя: <?= e($currentUser['name']) ?>">
                        <span class="user-avatar"><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span>
                        <span class="avatar-dot<?= ($unreadMsgs + $unread) === 0 ? ' is-empty' : '' ?>"></span>
                        <span class="user-name"><?= e($currentUser['name']) ?></span>
                    </button>
                    <div class="nav-user-menu">
                        <div class="nav-user-head">
                            <span class="user-avatar user-avatar-lg"><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span>
                            <span class="nav-user-head-txt">
                                <span class="nav-user-name"><?= e($currentUser['name']) ?></span>
                                <span class="nav-user-email"><?= e($currentUser['email']) ?></span>
                            </span>
                        </div>
                        <div class="nav-user-sep"></div>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'overview' ? ' is-active' : '' ?>" href="account.php">Мой кабинет</a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'items' ? ' is-active' : '' ?>" href="account.php?tab=items">Мои объявления</a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'auctions' ? ' is-active' : '' ?>" href="account.php?tab=auctions">Мои аукционы</a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'bids' ? ' is-active' : '' ?>" href="account.php?tab=bids">Мои ставки</a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'reviews' ? ' is-active' : '' ?>" href="account.php?tab=reviews">Отзывы</a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'history' ? ' is-active' : '' ?>" href="account.php?tab=history">История</a>
                        <div class="nav-user-sep"></div>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'messages' ? ' is-active' : '' ?>" href="account.php?tab=messages">Сообщения<span class="nav-badge nav-badge-msg<?= $unreadMsgs === 0 ? ' is-empty' : '' ?>" data-count="<?= $unreadMsgs ?>"><?= $unreadMsgs ?></span></a>
                        <a class="nav-link<?= $active === 'account' && ($_GET['tab'] ?? '') === 'notifications' ? ' is-active' : '' ?>" href="account.php?tab=notifications">Уведомления<span class="nav-badge nav-badge-notif<?= $unread === 0 ? ' is-empty' : '' ?>" data-count="<?= $unread ?>"><?= $unread ?></span></a>
                        <?php if ((int) $currentUser['is_admin'] === 1): ?>
                            <a class="nav-link<?= $active === 'admin' ? ' is-active' : '' ?>" href="admin.php">Админ</a>
                        <?php endif; ?>
                        <div class="nav-user-sep"></div>
                        <a class="nav-link" href="profile.php?id=<?= (int) $currentUser['id'] ?>">Мой профиль</a>
                        <form class="logout-form" method="post" action="logout.php">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <button class="nav-logout" type="submit">Выйти</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-secondary nav-auth<?= $active === 'login' ? ' is-active' : '' ?>" href="login.php" data-login-modal>Войти</a>
            <?php endif; ?>

            <a class="btn btn-primary nav-cta" href="post.php" data-modal-post>Разместить</a>
        </nav>
    </div>
</header>
<main class="container">
