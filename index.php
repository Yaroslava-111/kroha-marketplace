<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$typeParam = (string) ($_GET['type'] ?? '');
$mode = match ($typeParam) {
    'items' => 'items',
    'auctions' => 'auctions',
    'all' => 'catalog',
    default => 'home',
};

$allowedSort = [
    'home' => ['newest', 'price_asc', 'price_desc'],
    'catalog' => ['newest', 'price_asc', 'price_desc'],
    'items' => ['newest', 'price_asc', 'price_desc'],
    'auctions' => ['newest', 'ending', 'price_asc', 'price_desc'],
];
$sort = (string) ($_GET['sort'] ?? 'newest');
if (!in_array($sort, $allowedSort[$mode], true)) {
    $sort = 'newest';
}
$sortLabels = [
    'newest' => 'Сначала новые',
    'ending' => 'Завершаются скоро',
    'price_asc' => 'Сначала дешевле',
    'price_desc' => 'Сначала дороже',
];
$sortOptions = [];
foreach ($allowedSort[$mode] as $val) {
    $sortOptions[$val] = $sortLabels[$val];
}

$f = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'age_min' => trim((string) ($_GET['age_min'] ?? '')),
    'age_max' => trim((string) ($_GET['age_max'] ?? '')),
    'size' => trim((string) ($_GET['size'] ?? '')),
    'season' => trim((string) ($_GET['season'] ?? '')),
    'city' => trim((string) ($_GET['city'] ?? '')),
    'price_min' => trim((string) ($_GET['price_min'] ?? '')),
    'price_max' => trim((string) ($_GET['price_max'] ?? '')),
    'used' => ($_GET['used'] ?? '') === '1',
];

function where_for(array $f, string $priceCol, ?string $cityExpr = null): array
{
    $p = [];
    $w = [];
    if ($f['q'] !== '') {
        $w[] = 'search_lc LIKE ?';
        $p[] = '%' . mb_strtolower($f['q']) . '%';
    }
    if ($f['category'] !== '') {
        $w[] = 'category = ?';
        $p[] = $f['category'];
    }
    if ($f['age_min'] !== '') {
        $w[] = 'age_max >= ?';
        $p[] = (int) $f['age_min'];
    }
    if ($f['age_max'] !== '') {
        $w[] = 'age_min <= ?';
        $p[] = (int) $f['age_max'];
    }
    if ($f['size'] !== '') {
        $w[] = 'size LIKE ?';
        $p[] = '%' . $f['size'] . '%';
    }
    if ($f['season'] !== '') {
        $w[] = 'season = ?';
        $p[] = $f['season'];
    }
    if ($f['price_min'] !== '') {
        $w[] = $priceCol . ' >= ?';
        $p[] = (int) $f['price_min'];
    }
    if ($f['price_max'] !== '') {
        $w[] = $priceCol . ' <= ?';
        $p[] = (int) $f['price_max'];
    }
    if ($f['city'] !== '' && $cityExpr !== null) {
        $w[] = $cityExpr . ' LIKE ?';
        $p[] = '%' . $f['city'] . '%';
    }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
}

function filter_chip_url(array $get, string $drop): string
{
    unset($get[$drop]);
    return 'index.php?' . http_build_query($get);
}

function catalog_url(string $type): string
{
    $g = $_GET;
    $g['type'] = $type;
    return 'index.php?' . http_build_query($g);
}

function page_url(int $p): string
{
    $g = $_GET;
    if ($p <= 1) {
        unset($g['page']);
    } else {
        $g['page'] = $p;
    }
    $qs = http_build_query($g);
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

$pdo = pdo();

$itemOrder = [
    'newest' => 'i.created_at DESC',
    'price_asc' => 'i.price ASC',
    'price_desc' => 'i.price DESC',
    'ending' => 'i.created_at DESC',
][$sort];
$aucOrder = [
    'newest' => 'a.created_at DESC',
    'price_asc' => 'a.current_price ASC',
    'price_desc' => 'a.current_price DESC',
    'ending' => 'a.end_at ASC',
][$sort];

$limit = $mode === 'home' ? 4 : 20;
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

[$whereItems, $paramsItems] = where_for($f, 'i.price', 'i.city');
$extraItems = [];
if ($f['used']) {
    $extraItems[] = 'i.is_giveaway = 0';
}
$extraItems[] = "i.status = 'active'";
$whereItems .= $whereItems === '' ? ' WHERE ' . implode(' AND ', $extraItems) : ' AND ' . implode(' AND ', $extraItems);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM items i' . $whereItems);
$countStmt->execute($paramsItems);
$totalItems = (int) $countStmt->fetchColumn();
$items = $pdo->prepare('SELECT i.* FROM items i' . $whereItems . ' ORDER BY ' . $itemOrder . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
$items->execute($paramsItems);
$items = $items->fetchAll();

[$whereAuctions, $paramsAuctions] = where_for($f, 'a.current_price', 'u.city');
$statusClause = $whereAuctions === '' ? ' WHERE a.status = \'active\'' : $whereAuctions . ' AND a.status = \'active\'';
$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM (SELECT a.id FROM auctions a JOIN users u ON u.id = a.user_id LEFT JOIN bids b ON b.auction_id = a.id'
    . $statusClause
    . ' GROUP BY a.id) t'
);
$countStmt->execute($paramsAuctions);
$totalAuctions = (int) $countStmt->fetchColumn();
$auctions = $pdo->prepare(
    'SELECT a.*, u.name AS seller_name, u.city AS seller_city, COUNT(b.id) AS bid_count
     FROM auctions a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN bids b ON b.auction_id = a.id'
    . $statusClause
    . ' GROUP BY a.id ORDER BY ' . $aucOrder . ' LIMIT ' . $limit . ' OFFSET ' . $offset
);
$auctions->execute($paramsAuctions);
$auctions = $auctions->fetchAll();

$cats = categories();
$seasons = ['всесезон', 'зима', 'весна', 'лето', 'осень'];

$filterCount = 0;
foreach (['category', 'age_min', 'age_max', 'size', 'season', 'city', 'price_min', 'price_max'] as $fk) {
    if ($f[$fk] !== '') {
        $filterCount++;
    }
}
if ($f['used']) {
    $filterCount++;
}
$panelOpen = $filterCount > 0;
$found = $mode === 'items' ? $totalItems : ($mode === 'auctions' ? $totalAuctions : $totalItems + $totalAuctions);

$showPills = $mode !== 'home';
$showItems = in_array($mode, ['home', 'catalog', 'items'], true);
$showAuctions = in_array($mode, ['home', 'catalog', 'auctions'], true);

$active = $mode === 'home' ? '' : $typeParam;
$pageTitles = [
    'home' => APP_NAME . ' — ' . APP_TAGLINE,
    'catalog' => 'Каталог — ' . APP_NAME,
    'items' => 'Объявления — ' . APP_NAME,
    'auctions' => 'Аукционы — ' . APP_NAME,
];
$pageTitle = $pageTitles[$mode];
$metaDescs = [
    'home' => 'Кроха — маркетплейс детских вещей: купите и продайте коляски, одежду и игрушки по фикс-цене или на аукционе.',
    'catalog' => 'Каталог детских товаров: все объявления и аукционы с фильтрами по возрасту, размеру, сезону и городу.',
    'items' => 'Объявления о продаже детских вещей по фикс-цене: коляски, одежда, игрушки, мебель. Есть «отдам даром».',
    'auctions' => 'Аукционы детских вещей: ставки, автоставки до лимита и «купить сейчас». Цену определяет рынок.',
];
$metaDesc = $metaDescs[$mode];
require __DIR__ . '/includes/header.php';
?>
<?php if ($mode === 'home'): ?>
    <section class="hero">
        <div class="hero-decor" aria-hidden="true">
            <span class="hero-balloon hb-1"></span>
            <span class="hero-balloon hb-2"></span>
            <span class="hero-balloon hb-3"></span>
            <span class="hero-star hs-1">✦</span>
            <span class="hero-star hs-2">✧</span>
            <span class="hero-star hs-3">✦</span>
        </div>
        <div class="hero-content">
            <h1>Детские вещи снова нужны</h1>
            <p class="hero-lead"><?= e(APP_TAGLINE) ?></p>
            <ul class="hero-perks" aria-label="Что даёт Кроха">
                <li>Экономим бюджет</li>
                <li>Продлеваем жизнь вещам</li>
                <li>Находим по возрасту и размеру</li>
            </ul>
        </div>
    </section>
<?php elseif ($mode === 'catalog'): ?>
    <section class="page-head">
        <h1>Каталог</h1>
        <p>Все детские товары и аукционы в одном месте — ищите, фильтруйте и выбирайте.</p>
    </section>
<?php elseif ($mode === 'items'): ?>
    <section class="page-head">
        <h1>Объявления</h1>
        <p>Детские вещи по вашей цене — новые и в отличном состоянии.</p>
    </section>
<?php else: ?>
    <section class="page-head">
        <h1>Аукционы</h1>
        <p>Ставьте, торгуйтесь и находите выгодные детские находки.</p>
    </section>
<?php endif; ?>

<form class="filters" method="get" id="filtersForm">
    <input type="hidden" name="type" value="<?= e($typeParam) ?>">
    <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
    <div class="catalog-toolbar">
        <?php if ($showPills): ?>
        <nav class="catalog-tabs" aria-label="Раздел каталога">
            <a class="tab<?= $mode === 'catalog' ? ' is-active' : '' ?>" href="<?= e(catalog_url('all')) ?>">Все</a>
            <a class="tab<?= $mode === 'items' ? ' is-active' : '' ?>" href="<?= e(catalog_url('items')) ?>">Объявления</a>
            <a class="tab<?= $mode === 'auctions' ? ' is-active' : '' ?>" href="<?= e(catalog_url('auctions')) ?>">Аукционы</a>
        </nav>
        <?php endif; ?>
        <div class="search-row">
            <?php if ($mode !== 'home'): ?><span class="filters-hint">Найдено: <?= $found ?></span><?php endif; ?>
            <select class="sort-select" id="f-sort" name="sort" aria-label="Сортировка">
                <?php foreach ($sortOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary filter-toggle<?= $panelOpen ? ' is-open' : '' ?>" type="button" id="filtersToggle" aria-expanded="<?= $panelOpen ? 'true' : 'false' ?>" aria-controls="filtersPanel">
                Фильтры<?php if ($filterCount): ?><span class="filter-count"><?= $filterCount ?></span><?php endif; ?>
            </button>
            <?php if ($filterCount): ?><a class="btn btn-secondary" href="index.php<?= $typeParam !== '' ? '?type=' . e($typeParam) : '' ?>">Сброс</a><?php endif; ?>
        </div>
    </div>
    <?php
    $chips = [];
    if ($f['q'] !== '') {
        $chips[] = ['Поиск: ' . $f['q'], filter_chip_url($_GET, 'q')];
    }
    if ($f['category'] !== '') {
        $chips[] = ['Категория: ' . $f['category'], filter_chip_url($_GET, 'category')];
    }
    if ($f['price_min'] !== '' || $f['price_max'] !== '') {
        $g = $_GET;
        unset($g['price_min'], $g['price_max']);
        $priceLabel = 'Цена: ' . ($f['price_min'] !== '' ? $f['price_min'] : '0') . ' – ' . ($f['price_max'] !== '' ? $f['price_max'] : '∞');
        $chips[] = [$priceLabel, 'index.php?' . http_build_query($g)];
    }
    if ($f['age_min'] !== '' || $f['age_max'] !== '') {
        $g = $_GET;
        unset($g['age_min'], $g['age_max']);
        $ageLabel = 'Возраст: ' . ($f['age_min'] !== '' ? $f['age_min'] : '0') . ' – ' . ($f['age_max'] !== '' ? $f['age_max'] : '∞') . ' лет';
        $chips[] = [$ageLabel, 'index.php?' . http_build_query($g)];
    }
    if ($f['size'] !== '') {
        $chips[] = ['Размер: ' . $f['size'], filter_chip_url($_GET, 'size')];
    }
    if ($f['season'] !== '') {
        $chips[] = ['Сезон: ' . $f['season'], filter_chip_url($_GET, 'season')];
    }
    if ($f['city'] !== '') {
        $chips[] = ['Город: ' . $f['city'], filter_chip_url($_GET, 'city')];
    }
    if ($f['used']) {
        $chips[] = ['Только б/у', filter_chip_url($_GET, 'used')];
    }
    ?>
    <?php if ($chips): ?>
        <div class="active-filters">
            <?php foreach ($chips as $chip): ?>
                <a class="filter-chip" href="<?= e($chip[1]) ?>"><?= e($chip[0]) ?> <span class="chip-x" aria-hidden="true">✕</span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="filter-grid" id="filtersPanel"<?= $panelOpen ? '' : ' hidden' ?>>
        <div class="filter-field">
            <label for="f-category">Категория</label>
            <select id="f-category" name="category">
                <option value="">Все категории</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= e($c) ?>" <?= $f['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="f-price-min">Цена от, ₽</label>
            <input type="number" id="f-price-min" name="price_min" min="0" placeholder="0" value="<?= e($f['price_min']) ?>">
        </div>
        <div class="filter-field">
            <label for="f-price-max">Цена до, ₽</label>
            <input type="number" id="f-price-max" name="price_max" min="0" placeholder="0" value="<?= e($f['price_max']) ?>">
        </div>
        <div class="filter-field">
            <label for="f-age-min">Возраст от, лет</label>
            <input type="number" id="f-age-min" name="age_min" min="0" placeholder="0" value="<?= e($f['age_min']) ?>">
        </div>
        <div class="filter-field">
            <label for="f-age-max">Возраст до, лет</label>
            <input type="number" id="f-age-max" name="age_max" min="0" placeholder="0" value="<?= e($f['age_max']) ?>">
        </div>
        <div class="filter-field">
            <label for="f-size">Размер</label>
            <input type="text" id="f-size" name="size" placeholder="Например, 92" value="<?= e($f['size']) ?>">
        </div>
        <div class="filter-field">
            <label for="f-season">Сезон</label>
            <select id="f-season" name="season">
                <option value="">Любой</option>
                <?php foreach ($seasons as $s): ?>
                    <option value="<?= e($s) ?>" <?= $f['season'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="f-city">Город</label>
            <input type="text" id="f-city" name="city" placeholder="Любой" value="<?= e($f['city']) ?>">
        </div>
        <div class="filter-field filter-field-check">
            <label class="used-check">
                <input type="checkbox" name="used" value="1" <?= $f['used'] ? 'checked' : '' ?>> только б/у
            </label>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Применить</button>
        </div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('filtersForm');
    var toggle = document.getElementById('filtersToggle');
    var panel = document.getElementById('filtersPanel');
    var sort = document.getElementById('f-sort');
    toggle.addEventListener('click', function () {
        var open = panel.hidden;
        panel.hidden = !open;
        toggle.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if (sort) {
        sort.addEventListener('change', function () { form.submit(); });
    }
})();
</script>

<?php if ($showItems): ?>
    <section class="catalog-block">
        <div class="section-head">
            <h2><?= $mode === 'home' ? 'Последние объявления' : 'Объявления' ?> <?php if ($mode !== 'home'): ?><span class="muted">(<?= $totalItems ?>)</span><?php endif; ?></h2>
            <?php if ($mode === 'home'): ?>
                <a class="view-all" href="index.php?type=items">Все объявления<svg class="view-all-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            <?php else: ?>
                <a class="btn btn-secondary" href="post.php" data-modal-item>Разместить</a>
            <?php endif; ?>
        </div>
        <?php if ($items): ?>
            <div class="grid">
                <?php foreach ($items as $it): ?>
                    <?php require __DIR__ . '/includes/card_item.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Объявлений пока нет. <?= $mode === 'home' ? 'Станьте первым — разместите своё.' : 'Попробуйте снять фильтры.' ?></p>
        <?php endif; ?>
        <?php if ($showItems && $mode !== 'home' && $totalItems > $perPage): ?>
            <nav class="pagination" aria-label="Страницы объявлений">
                <?php $pagesItems = (int) ceil($totalItems / $perPage); ?>
                <?php if ($page > 1): ?><a class="page-btn" href="<?= e(page_url($page - 1)) ?>">← Назад</a><?php endif; ?>
                <?php for ($p = 1; $p <= $pagesItems; $p++): ?>
                    <?php if ($pagesItems > 7 && $p > 2 && $p < $pagesItems - 1 && abs($p - $page) > 1): ?>
                        <?php if (($p === 3 && $page > 4) || ($p === $pagesItems - 2 && $page < $pagesItems - 3)): ?><span class="page-dots">…</span><?php endif; ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="page-btn<?= $p === $page ? ' is-active' : '' ?>" href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $pagesItems): ?><a class="page-btn" href="<?= e(page_url($page + 1)) ?>">Вперёд →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($showAuctions): ?>
    <section class="catalog-block">
        <div class="section-head">
            <h2><?= $mode === 'home' ? 'Свежие аукционы' : 'Аукционы' ?> <?php if ($mode !== 'home'): ?><span class="muted">(<?= $totalAuctions ?>)</span><?php endif; ?></h2>
            <?php if ($mode === 'home'): ?>
                <a class="view-all" href="index.php?type=auctions">Все аукционы<svg class="view-all-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            <?php else: ?>
                <a class="btn btn-secondary" href="auction_new.php" data-modal-auction>Создать аукцион</a>
            <?php endif; ?>
        </div>
        <?php if ($auctions): ?>
            <div class="grid">
                <?php foreach ($auctions as $a): ?>
                    <?php require __DIR__ . '/includes/card_auction.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Аукционов пока нет. <?= $mode === 'home' ? 'Загляните позже.' : 'Попробуйте снять фильтры.' ?></p>
        <?php endif; ?>
        <?php if ($showAuctions && $mode !== 'home' && $totalAuctions > $perPage): ?>
            <nav class="pagination" aria-label="Страницы аукционов">
                <?php $pagesAuctions = (int) ceil($totalAuctions / $perPage); ?>
                <?php if ($page > 1): ?><a class="page-btn" href="<?= e(page_url($page - 1)) ?>">← Назад</a><?php endif; ?>
                <?php for ($p = 1; $p <= $pagesAuctions; $p++): ?>
                    <?php if ($pagesAuctions > 7 && $p > 2 && $p < $pagesAuctions - 1 && abs($p - $page) > 1): ?>
                        <?php if (($p === 3 && $page > 4) || ($p === $pagesAuctions - 2 && $page < $pagesAuctions - 3)): ?><span class="page-dots">…</span><?php endif; ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="page-btn<?= $p === $page ? ' is-active' : '' ?>" href="<?= e(page_url($p)) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $pagesAuctions): ?><a class="page-btn" href="<?= e(page_url($page + 1)) ?>">Вперёд →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
