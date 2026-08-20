<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: account.php');
    exit;
}

$pdo = pdo();
$errors = ['password' => '', 'password2' => '', 'global' => ''];
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['global'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } elseif (password_reset_by_token($pdo, $token) === null) {
        $errors['global'] = 'Ссылка недействительна или устарела. Запросите восстановление пароля заново.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Пароль — не короче 8 символов.';
        } elseif ($password !== $password2) {
            $errors['password2'] = 'Пароли не совпадают.';
        }
        if ($errors['password'] === '' && $errors['password2'] === '') {
            complete_password_reset($pdo, $token, $password);
            header('Location: login.php?ok=reset');
            exit;
        }
    }
}

if ($errors['global'] === '' && password_reset_by_token($pdo, $token) === null) {
    $errors['global'] = 'Ссылка недействительна или устарела. Запросите восстановление пароля заново.';
}

$active = '';
$pageTitle = 'Новый пароль — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="auth-wrap">
        <div class="auth-card">
            <div class="auth-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h2>Новый пароль</h2>

            <?php if ($errors['global'] !== ''): ?>
                <div class="alert alert-error"><?= e($errors['global']) ?></div>
                <p class="auth-alt"><a href="forgot_password.php">Запросить новую ссылку</a></p>
            <?php else: ?>
                <p class="auth-card-sub">Придумайте новый пароль для своей учётной записи.</p>

                <form class="form-auth" method="post" action="reset_password.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-grid form-auth-grid">
                        <div class="form-row<?= $errors['password'] !== '' ? ' has-error' : '' ?>">
                            <label for="password">Пароль</label>
                            <div class="pass-wrap">
                                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Не короче 8 символов" class="pass-input<?= $errors['password'] !== '' ? ' is-invalid' : '' ?>">
                                <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="password">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <?php if ($errors['password'] !== ''): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <div class="form-row<?= $errors['password2'] !== '' ? ' has-error' : '' ?>">
                            <label for="password2">Повторите пароль</label>
                            <div class="pass-wrap">
                                <input type="password" id="password2" name="password2" required minlength="8" autocomplete="new-password" placeholder="Ещё раз" class="pass-input<?= $errors['password2'] !== '' ? ' is-invalid' : '' ?>">
                                <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="password2">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <?php if ($errors['password2'] !== ''): ?><div class="field-error"><?= e($errors['password2']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Сохранить пароль</button>
                </form>
            <?php endif; ?>

            <p class="auth-alt"><a href="login.php">← Вернуться ко входу</a></p>
        </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
