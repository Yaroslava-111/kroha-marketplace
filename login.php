<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = pdo();

$next = (string) ($_POST['next'] ?? $_GET['next'] ?? 'index.php');
if ($next === '' || str_contains($next, '://') || $next[0] === '\\' || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
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

$errors = ['email' => '', 'password' => '', 'global' => ''];
$old = ['email' => ''];

start_session();
$lockUntil = (int) ($_SESSION['login_lock'] ?? 0);
$fails = (int) ($_SESSION['login_fails'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($lockUntil > time()) {
        $mins = (int) ceil(($lockUntil - time()) / 60);
        $errors['global'] = 'Слишком много попыток входа. Вход временно заблокирован — повторите через ' . $mins . ' мин.';
    } elseif (!csrf_check()) {
        $errors['global'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $old['email'] = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        }
        if ($password === '') {
            $errors['password'] = 'Введите пароль.';
        }

        if ($errors['email'] === '' && $errors['password'] === '') {
            $user = user_by_email($pdo, mb_strtolower($old['email']));
            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                $fails++;
                $_SESSION['login_fails'] = $fails;
                if ($fails >= 5) {
                    $_SESSION['login_lock'] = time() + 900;
                    $_SESSION['login_fails'] = 0;
                    $fails = 0;
                    $lockUntil = (int) $_SESSION['login_lock'];
                    $errors['global'] = 'Слишком много неверных попыток. Вход заблокирован на 15 минут.';
                } else {
                    $errors['global'] = 'Неверный email или пароль.';
                }
            } elseif ((int) $user['is_banned'] === 1) {
                $errors['global'] = 'Этот аккаунт заблокирован. Свяжитесь с нами через раздел «Помощь».';
            } else {
                login_user((int) $user['id']);
                if ($isAjax) {
                    ajax_json(['ok' => true, 'redirect' => $next]);
                }
                header('Location: ' . $next);
                exit;
            }
        }
    }

    if ($isAjax) {
        $msg = $errors['global'];
        if ($msg === '' && $errors['email'] !== '') {
            $msg = $errors['email'];
        }
        if ($msg === '' && $errors['password'] !== '') {
            $msg = $errors['password'];
        }
        ajax_json(['ok' => false, 'error' => $msg !== '' ? $msg : 'Не удалось войти.']);
    }
}

$active = '';
$pageTitle = 'Вход — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="auth-wrap">
        <div class="auth-card">
            <div class="auth-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h2>Вход</h2>
            <p class="auth-card-sub">Размещайте объявления, участвуйте в аукционах и следите за своими лотами.</p>

            <?php if (($_GET['ok'] ?? '') === 'reset'): ?>
                <div class="alert alert-ok">Пароль изменён. Войдите с новым паролем.</div>
            <?php elseif ($errors['global'] !== ''): ?>
                <div class="alert alert-error"><?= e($errors['global']) ?></div>
            <?php elseif ($lockUntil > time()): ?>
                <div class="alert alert-error">Вход временно заблокирован из-за множества неудачных попыток.</div>
            <?php elseif ($fails >= 3): ?>
                <div class="alert alert-warn">Осталось попыток входа: <?= 5 - $fails ?>.</div>
            <?php endif; ?>

            <form class="form-auth" method="post" action="login.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-row<?= $errors['email'] !== '' ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus autocomplete="email" value="<?= e($old['email']) ?>" placeholder="you@example.ru" class="<?= $errors['email'] !== '' ? 'is-invalid' : '' ?>">
                    <?php if ($errors['email'] !== ''): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="form-row<?= $errors['password'] !== '' ? ' has-error' : '' ?>">
                    <label for="password">Пароль</label>
                    <div class="pass-wrap">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••" class="pass-input<?= $errors['password'] !== '' ? ' is-invalid' : '' ?>">
                        <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="password">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if ($errors['password'] !== ''): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>

                <div class="form-auth-meta">
                    <a class="forgot-link" href="forgot_password.php">Забыли пароль?</a>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Войти</button>
            </form>

            <p class="auth-alt">Нет аккаунта? <a href="register.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">Зарегистрируйтесь</a></p>
        </div>
    </section>

    <section class="catalog-block">
        <h2>Демо-аккаунты</h2>
        <p class="empty">Для быстрого знакомства: <strong>masha@kroha.test</strong> / <strong>demo</strong> (продавец), <strong>anna@kroha.test</strong> / <strong>demo</strong> (участник).</p>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
