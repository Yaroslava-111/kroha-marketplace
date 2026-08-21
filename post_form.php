<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$isFragment = ($_GET['fragment'] ?? '') === '1';

$me = current_user();
if (!$me) {
    if ($isFragment) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'login' => true]);
        exit;
    }
    require_login();
}
$userId = (int) $me['id'];

$pdo = pdo();
$cats = categories();
$seasons = ['всесезон', 'зима', 'весна', 'лето', 'осень'];

$errors = [];
$old = [
    'title' => '', 'category' => '', 'price' => '', 'condition_label' => 'б/у',
    'size' => '', 'season' => '', 'age_min' => '', 'age_max' => '',
    'description' => '', 'city' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.';
    } else {
        $old = array_merge($old, [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category' => (string) ($_POST['category'] ?? ''),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'condition_label' => trim((string) ($_POST['condition_label'] ?? '')),
            'size' => trim((string) ($_POST['size'] ?? '')),
            'season' => (string) ($_POST['season'] ?? ''),
            'age_min' => trim((string) ($_POST['age_min'] ?? '')),
            'age_max' => trim((string) ($_POST['age_max'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
        ]);

        $titleLen = mb_strlen($old['title']);
        if ($titleLen < 5 || $titleLen > 200) {
            $errors['title'] = 'Заголовок — от 5 до 200 символов.';
        }
        if (!in_array($old['category'], $cats, true)) {
            $errors['category'] = 'Выберите категорию.';
        }
        if ($old['price'] === '') {
            $old['price'] = '0';
        }
        if (!preg_match('/^\d+$/', $old['price'])) {
            $errors['price'] = 'Цена — целое число в рублях, или 0, если отдаёте даром.';
        }
        $descLen = mb_strlen($old['description']);
        if ($descLen < 10 || $descLen > 5000) {
            $errors['description'] = 'Описание — от 10 до 5000 символов.';
        }
        if (mb_strlen($old['city']) < 2 || mb_strlen($old['city']) > 100) {
            $errors['city'] = 'Укажите город.';
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
        if (!$savedPhotos) {
            $errors['photos'] = 'Добавьте хотя бы одну фотографию.';
        }

        if (!$errors) {
            $price = (int) $old['price'];
            $searchLc = mb_strtolower(trim($old['title'] . ' ' . $old['description'] . ' ' . $old['city']));
            $stmt = $pdo->prepare(
                'INSERT INTO items
                    (user_id, title, category, age_min, age_max, size, season, condition_label, price, city, description, photos, is_giveaway, status, search_lc, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', ?, ?)'
            );
            $stmt->execute([
                $userId,
                $old['title'],
                $old['category'],
                $old['age_min'] !== '' ? (int) $old['age_min'] : null,
                $old['age_max'] !== '' ? (int) $old['age_max'] : null,
                $old['size'] !== '' ? $old['size'] : null,
                $old['season'] !== '' ? $old['season'] : null,
                $old['condition_label'] !== '' ? $old['condition_label'] : 'б/у',
                $price,
                $old['city'],
                $old['description'],
                $savedPhotos ? json_encode($savedPhotos, JSON_UNESCAPED_SLASHES) : null,
                $price === 0 ? 1 : 0,
                $searchLc,
                date('Y-m-d H:i:s'),
            ]);
            notify_saved_searches($pdo, 'item', (int) $pdo->lastInsertId());
            if ($isFragment) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'url' => 'item.php?id=' . (int) $pdo->lastInsertId()]);
                exit;
            }
            header('Location: item.php?id=' . (int) $pdo->lastInsertId());
            exit;
        }
    }
    if ($isFragment) {
        http_response_code(422);
    }
}
?>
<?php if ($errors): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $er): ?>
            <div><?= e($er) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="form-card post-modal-form" method="post" action="post.php" enctype="multipart/form-data" data-endpoint="post_form.php?fragment=1">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="form-row">
        <label for="title">Заголовок *</label>
        <input type="text" id="title" name="title" maxlength="200" required value="<?= e($old['title']) ?>" placeholder="Например: Коляска Peg Perego, 2 сезона">
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
            <label for="price">Цена, ₽ *</label>
            <input type="number" id="price" name="price" min="0" step="1" inputmode="numeric" value="<?= e($old['price']) ?>">
            <span class="hint">0 — значит «Отдам даром»</span>
            <?php if (isset($errors['price'])): ?><div class="field-error"><?= e($errors['price']) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-row">
            <label for="condition_label">Состояние</label>
            <input type="text" id="condition_label" name="condition_label" maxlength="100" value="<?= e($old['condition_label']) ?>" placeholder="Например: как новое">
        </div>
        <div class="form-row">
            <label for="size">Размер</label>
            <input type="text" id="size" name="size" maxlength="20" value="<?= e($old['size']) ?>" placeholder="Например: 92">
        </div>
    </div>

    <div class="form-grid">
        <div class="form-row">
            <label for="season">Сезон</label>
            <select id="season" name="season">
                <option value="">Не важно</option>
                <?php foreach ($seasons as $s): ?>
                    <option value="<?= e($s) ?>" <?= $old['season'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="city">Город *</label>
            <input type="text" id="city" name="city" maxlength="100" required value="<?= e($old['city']) ?>" placeholder="Например: Москва">
            <?php if (isset($errors['city'])): ?><div class="field-error"><?= e($errors['city']) ?></div><?php endif; ?>
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
        <textarea id="description" name="description" maxlength="5000" required placeholder="Состояние, износ, комплектность, как передать (самовывоз/доставка)…"><?= e($old['description']) ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
    </div>

    <div class="form-row">
        <label for="photos">Фотографии *</label>
        <?php $photos_required = true; require __DIR__ . '/includes/photo_preview.php'; ?>
        <?php if (isset($errors['photos'])): ?><div class="field-error"><?= e($errors['photos']) ?></div><?php endif; ?>
    </div>

    <button class="btn btn-primary post-submit" type="submit">Опубликовать</button>
</form>
