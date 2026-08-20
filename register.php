<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = pdo();

$next = (string) ($_POST['next'] ?? $_GET['next'] ?? 'index.php');
if ($next === '' || str_contains($next, '://') || $next[0] === '\\') {
    $next = 'index.php';
}

$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function ajax_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_logged_in()) {
    if ($isAjax) {
        ajax_json(['ok' => true, 'redirect' => $next]);
    }
    header('Location: ' . $next);
    exit;
}

$errors = [];
$old = ['name' => '', 'email' => '', 'city' => '', 'agree' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['global'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $old = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'agree' => (($_POST['agree'] ?? '') === '1'),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');

        $nameLen = mb_strlen($old['name']);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors['name'] = 'Имя — от 2 до 100 символов.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        } elseif (user_by_email($pdo, mb_strtolower($old['email']))) {
            $errors['email'] = 'Этот email уже зарегистрирован.';
        }
        if (mb_strlen($old['city']) < 2 || mb_strlen($old['city']) > 100) {
            $errors['city'] = 'Укажите город.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Пароль — не короче 8 символов.';
        } elseif ($password !== $password2) {
            $errors['password2'] = 'Пароли не совпадают.';
        }
        if (!$old['agree']) {
            $errors['agree'] = 'Необходимо согласие на обработку персональных данных.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, city, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['name'],
                mb_strtolower($old['email']),
                password_hash($password, PASSWORD_DEFAULT),
                $old['city'],
                date('Y-m-d H:i:s'),
            ]);
            login_user((int) $pdo->lastInsertId());
            if ($isAjax) {
                ajax_json(['ok' => true, 'redirect' => $next]);
            }
            header('Location: ' . $next);
            exit;
        }
    }

    if ($isAjax) {
        $msg = $errors['global'] ?? '';
        foreach ($errors as $m) {
            if ($msg === '' && $m !== '') {
                $msg = $m;
            }
        }
        ajax_json(['ok' => false, 'error' => $msg !== '' ? $msg : 'Не удалось зарегистрироваться.']);
    }
}

$active = '';
$pageTitle = 'Регистрация — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="auth-wrap">
        <div class="auth-card">
            <div class="auth-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
            <h2>Регистрация</h2>
            <p class="auth-card-sub">Создайте аккаунт — и вы сможете размещать объявления, выставлять лоты на аукцион и делать ставки.</p>

            <?php if (!empty($errors['global'])): ?>
                <div class="alert alert-error"><?= e($errors['global']) ?></div>
            <?php endif; ?>

            <form class="form-auth" method="post" action="register.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-row<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Имя</label>
                    <input type="text" id="name" name="name" maxlength="100" required autocomplete="name" value="<?= e($old['name']) ?>" placeholder="Как вас представлять" class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="form-row<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($old['email']) ?>" placeholder="you@example.ru" class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="form-row<?= isset($errors['city']) ? ' has-error' : '' ?>">
                    <label for="city">Город</label>
                    <input type="text" id="city" name="city" maxlength="100" required autocomplete="address-level2" value="<?= e($old['city']) ?>" placeholder="Например: Москва" class="<?= isset($errors['city']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['city'])): ?><div class="field-error"><?= e($errors['city']) ?></div><?php endif; ?>
                </div>

                <div class="form-grid form-auth-grid">
                    <div class="form-row<?= isset($errors['password']) ? ' has-error' : '' ?>">
                        <label for="password">Пароль</label>
                        <div class="pass-wrap">
                            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Не короче 8 символов" class="pass-input<?= isset($errors['password']) ? ' is-invalid' : '' ?>">
                            <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="password">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-row<?= isset($errors['password2']) ? ' has-error' : '' ?>">
                        <label for="password2">Повторите пароль</label>
                        <div class="pass-wrap">
                            <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" placeholder="Ещё раз" class="pass-input<?= isset($errors['password2']) ? ' is-invalid' : '' ?>">
                            <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="password2">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <?php if (isset($errors['password2'])): ?><div class="field-error"><?= e($errors['password2']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="form-row form-row-check<?= isset($errors['agree']) ? ' has-error' : '' ?>">
                    <label class="agree-check">
                        <input type="checkbox" name="agree" value="1" <?= $old['agree'] ? 'checked' : '' ?>>
                        <span>Я согласен(на) на обработку моих персональных данных в соответствии с <a href="policy.php" target="_blank" rel="noopener">политикой обработки персональных данных</a></span>
                    </label>
                    <?php if (isset($errors['agree'])): ?><div class="field-error"><?= e($errors['agree']) ?></div><?php endif; ?>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Зарегистрироваться</button>
            </form>

            <p class="auth-alt">Уже есть аккаунт? <a href="login.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">Войдите</a></p>
        </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
