<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$pdo = pdo();
$id = (int) ($_GET['id'] ?? 0);
$userId = current_user_id();

$stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    $active = 'manage';
    $pageTitle = 'Объявление не найдено — ' . APP_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<section class="not-found"><h1>Объявление не найдено</h1><p class="empty">Редактировать можно только свои объявления.</p><p><a class="btn btn-primary" href="account.php?tab=items">К моим объявлениям</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$cats = categories();
$seasons = ['всесезон', 'зима', 'весна', 'лето', 'осень'];
$photos = photos_of($item);

$errors = [];
$old = [
    'title' => $item['title'], 'category' => $item['category'],
    'price' => (string) $item['price'], 'condition_label' => (string) $item['condition_label'],
    'size' => (string) $item['size'], 'season' => (string) $item['season'],
    'age_min' => $item['age_min'] !== null ? (string) $item['age_min'] : '',
    'age_max' => $item['age_max'] !== null ? (string) $item['age_max'] : '',
    'description' => (string) $item['description'], 'city' => $item['city'],
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

        $keepPhotos = [];
        foreach (array_keys($photos) as $i) {
            if (isset($_POST['keep_photo'][$i])) {
                $keepPhotos[] = $photos[$i];
            }
        }
        [$savedPhotos, $photoErrors] = save_photos();
        foreach ($photoErrors as $pe) {
            $errors[] = $pe;
        }
        $allPhotos = array_merge($keepPhotos, $savedPhotos);

        if (!$errors) {
            $price = (int) $old['price'];
            $searchLc = mb_strtolower(trim($old['title'] . ' ' . $old['description'] . ' ' . $old['city']));
            $pdo->prepare(
                'UPDATE items SET
                    title = ?, category = ?, age_min = ?, age_max = ?, size = ?, season = ?,
                    condition_label = ?, price = ?, city = ?, description = ?, photos = ?, is_giveaway = ?,
                    search_lc = ?
                 WHERE id = ? AND user_id = ?'
            )->execute([
                $old['title'], $old['category'],
                $old['age_min'] !== '' ? (int) $old['age_min'] : null,
                $old['age_max'] !== '' ? (int) $old['age_max'] : null,
                $old['size'] !== '' ? $old['size'] : null,
                $old['season'] !== '' ? $old['season'] : null,
                $old['condition_label'] !== '' ? $old['condition_label'] : 'б/у',
                $price, $old['city'], $old['description'],
                $allPhotos ? json_encode($allPhotos, JSON_UNESCAPED_SLASHES) : null,
                $price === 0 ? 1 : 0,
                $searchLc,
                $id, $userId,
            ]);
            foreach ($photos as $ph) {
                if (!in_array($ph, $allPhotos, true)) {
                    $path = __DIR__ . '/' . $ph;
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
            header('Location: account.php?tab=items&ok=edit');
            exit;
        }
    }
}

$active = 'manage';
$pageTitle = 'Редактировать объявление — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="hero">
        <h1>Редактировать объявление</h1>
        <p>Обновите данные и нажмите «Сохранить». Изменения сразу появятся в каталоге.</p>
    </section>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $er): ?>
                <div><?= e($er) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="form-card" method="post" action="edit_item.php?id=<?= $id ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-row">
            <label for="title">Заголовок *</label>
            <input type="text" id="title" name="title" maxlength="200" required value="<?= e($old['title']) ?>">
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
                <input type="text" id="condition_label" name="condition_label" maxlength="100" value="<?= e($old['condition_label']) ?>">
            </div>
            <div class="form-row">
                <label for="size">Размер</label>
                <input type="text" id="size" name="size" maxlength="20" value="<?= e($old['size']) ?>">
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
                <input type="text" id="city" name="city" maxlength="100" required value="<?= e($old['city']) ?>">
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
            <textarea id="description" name="description" maxlength="5000" required><?= e($old['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?><div class="field-error"><?= e($errors['description']) ?></div><?php endif; ?>
        </div>

        <?php if ($photos): ?>
            <div class="form-row">
                <label>Текущие фото (снимите галочку, чтобы убрать)</label>
                <div class="photo-edits">
                    <?php foreach ($photos as $i => $ph): ?>
                        <label class="photo-edit">
                            <input type="checkbox" name="keep_photo[<?= $i ?>]" value="1" checked>
                            <img src="<?= e($ph) ?>" alt="">
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <label for="photos">Добавить фото</label>
            <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
            <span class="hint">JPG, PNG или WebP, до 5 МБ каждый.</span>
        </div>

        <div class="form-row">
            <button class="btn btn-primary" type="submit">Сохранить</button>
            <a class="btn btn-secondary" href="account.php?tab=items">Отмена</a>
        </div>
    </form>
<?php require __DIR__ . '/includes/footer.php'; ?>
