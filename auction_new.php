<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$pdo = pdo();
$cats = categories();
$seasons = ['всесезон', 'зима', 'весна', 'лето', 'осень'];
$durations = [1, 3, 7];

$errors = [];
$old = [
    'title' => '', 'category' => '', 'condition_label' => 'б/у', 'size' => '',
    'season' => '', 'age_min' => '', 'age_max' => '', 'description' => '',
    'start_price' => '', 'duration' => '3', 'bin_price' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.';
    } else {
        $old = array_merge($old, [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category' => (string) ($_POST['category'] ?? ''),
            'condition_label' => trim((string) ($_POST['condition_label'] ?? '')),
            'size' => trim((string) ($_POST['size'] ?? '')),
            'season' => (string) ($_POST['season'] ?? ''),
            'age_min' => trim((string) ($_POST['age_min'] ?? '')),
            'age_max' => trim((string) ($_POST['age_max'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'start_price' => trim((string) ($_POST['start_price'] ?? '')),
            'duration' => (string) ($_POST['duration'] ?? '3'),
            'bin_price' => trim((string) ($_POST['bin_price'] ?? '')),
        ]);

        $titleLen = mb_strlen($old['title']);
        if ($titleLen < 5 || $titleLen > 200) {
            $errors['title'] = 'Заголовок — от 5 до 200 символов.';
        }
        if (!in_array($old['category'], $cats, true)) {
            $errors['category'] = 'Выберите категорию.';
        }
        if (!preg_match('/^\d+$/', $old['start_price']) || (int) $old['start_price'] < 100) {
            $errors['start_price'] = 'Стартовая цена — целое число от 100 ₽.';
        }
        if (!in_array((int) $old['duration'], $durations, true)) {
            $old['duration'] = '3';
        }
        foreach (['bin_price'] as $f) {
            if ($old[$f] !== '' && !preg_match('/^\d+$/', $old[$f])) {
                $errors[$f] = 'Цена — целое число в рублях.';
            }
        }
        $start = (int) $old['start_price'];
        if ($old['bin_price'] !== '' && (int) $old['bin_price'] < $start) {
            $errors['bin_price'] = '«Купить сейчас» не может быть ниже стартовой цены.';
        }
        $descLen = mb_strlen($old['description']);
        if ($descLen < 10 || $descLen > 5000) {
            $errors['description'] = 'Описание — от 10 до 5000 символов.';
        }
        foreach (['age_min', 'age_max'] as $ageField) {
            if ($old[$ageField] !== '' && !preg_match('/^\d{1,2}$/', $old[$ageField])) {
                $errors[$ageField] = 'Возраст — число.';
            }
        }
        if ($old['season'] !== '' && !in_array($old['season'], $seasons, true)) {
            $old['season'] = '';
        }

        [$savedPhotos, $photoErrors] = save_photos();
        foreach ($photoErrors as $pe) {
            $errors[] = $pe;
        }

        if (!$errors) {
            $step = max(50, (int) ceil($start * 0.05));
            $endAt = date('Y-m-d H:i:s', time() + (int) $old['duration'] * 86400);
            $searchLc = mb_strtolower(trim($old['title'] . ' ' . $old['description'] . ' ' . $old['condition_label']));
            $stmt = $pdo->prepare(
                'INSERT INTO auctions
                    (user_id, title, category, age_min, age_max, size, season, condition_label, description, photos,
                     start_price, current_price, min_bid_step, duration_days, end_at, bin_price, status, search_lc, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', ?, ?)'
            );
            $stmt->execute([
                current_user_id(),
                $old['title'],
                $old['category'],
                $old['age_min'] !== '' ? (int) $old['age_min'] : null,
                $old['age_max'] !== '' ? (int) $old['age_max'] : null,
                $old['size'] !== '' ? $old['size'] : null,
                $old['season'] !== '' ? $old['season'] : null,
                $old['condition_label'] !== '' ? $old['condition_label'] : 'б/у',
                $old['description'],
                $savedPhotos ? json_encode($savedPhotos, JSON_UNESCAPED_SLASHES) : null,
                $start,
                $start,
                $step,
                (int) $old['duration'],
                $endAt,
                $old['bin_price'] !== '' ? (int) $old['bin_price'] : null,
                $searchLc,
                date('Y-m-d H:i:s'),
            ]);
            notify_saved_searches($pdo, 'auction', (int) $pdo->lastInsertId());
            header('Location: auction.php?id=' . (int) $pdo->lastInsertId() . '&ok=create');
            exit;
        }
    }
}

$active = 'auctions';
$pageTitle = 'Создать аукцион — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="hero">
        <h1>Создать аукцион</h1>
        <p>Не знаете, сколько стоит? Пусть покупатели назовут цену. Для пакетов, сезонного и брендового — лучший способ продать.</p>
    </section>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $er): ?>
                <div><?= e($er) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="form-card" method="post" action="auction_new.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-row">
            <label for="title">Заголовок *</label>
            <input type="text" id="title" name="title" maxlength="200" required value="<?= e($old['title']) ?>" placeholder="Например: Пакет комбинезонов 74–86, 4 шт">
            <?php if (isset($errors['title'])): ?><div class="field-error"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>

        <div class="form-grid">
            <div class="form-row">
                <label for="category">Категория *</label>
                <select id="category" name="category" required>
                    <option value="">Выберите…</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= e($c) ?>" <?= $old['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?><div class="field-error"><?= e($errors['category']) ?></div><?php endif; ?>
            </div>
            <div class="form-row">
                <label for="condition_label">Состояние</label>
                <input type="text" id="condition_label" name="condition_label" maxlength="100" value="<?= e($old['condition_label']) ?>" placeholder="Например: как новое">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-row">
                <label for="start_price">Стартовая цена, ₽ *</label>
                <input type="number" id="start_price" name="start_price" min="100" step="1" value="<?= e($old['start_price']) ?>">
                <span class="hint">От 100 ₽. Шаг ставки посчитается сам: 5% от цены, минимум 50 ₽.</span>
                <?php if (isset($errors['start_price'])): ?><div class="field-error"><?= e($errors['start_price']) ?></div><?php endif; ?>
            </div>
            <div class="form-row">
                <label for="duration">Срок торга</label>
                <select id="duration" name="duration">
                    <option value="1" <?= $old['duration'] === '1' ? 'selected' : '' ?>>1 день</option>
                    <option value="3" <?= $old['duration'] === '3' ? 'selected' : '' ?>>3 дня</option>
                    <option value="7" <?= $old['duration'] === '7' ? 'selected' : '' ?>>7 дней</option>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-row">
                <label for="bin_price">«Купить сейчас», ₽</label>
                <input type="number" id="bin_price" name="bin_price" min="0" step="1" value="<?= e($old['bin_price']) ?>">
                <span class="hint">Покупатель может купить лот сразу за эту цену.</span>
                <?php if (isset($errors['bin_price'])): ?><div class="field-error"><?= e($errors['bin_price']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-row">
                <label for="size">Размер</label>
                <input type="text" id="size" name="size" maxlength="20" value="<?= e($old['size']) ?>" placeholder="Например: 92">
            </div>
            <div class="form-row">
                <label for="season">Сезон</label>
                <select id="season" name="season">
                    <option value="">Не важно</option>
                    <?php foreach ($seasons as $s): ?>
                        <option value="<?= e($s) ?>" <?= $old['season'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-row">
                <label for="age_min">Возраст, от</label>
                <input type="number" id="age_min" name="age_min" min="0" max="99" value="<?= e($old['age_min']) ?>">
            </div>
            <div class="form-row">
                <label for="age_max">Возраст, до</label>
                <input type="number" id="age_max" name="age_max" min="0" max="99" value="<?= e($old['age_max']) ?>">
            </div>
        </div>

        <div class="form-row">
            <label for="description">Описание *</label>
            <textarea id="description" name="description" maxlength="5000" required placeholder="Что входит в лот, состояние, как передать…"><?= e($old['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
            <label for="photos">Фотографии</label>
            <?php $photos_required = false; require __DIR__ . '/includes/photo_preview.php'; ?>
        </div>

        <button class="btn btn-primary" type="submit">Опубликовать лот</button>
    </form>

<?php require __DIR__ . '/includes/footer.php'; ?>
