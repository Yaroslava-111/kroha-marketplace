<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: account.php');
    exit;
}

$pdo = pdo();
$errors = ['email' => '', 'global' => ''];
$old = ['email' => ''];
$sent = false;
$devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['global'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $old['email'] = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        } else {
            $user = user_by_email($pdo, mb_strtolower($old['email']));
            if ($user) {
                $token = create_password_reset($pdo, (int) $user['id']);
                $devLink = 'reset_password.php?token=' . urlencode($token);
            }
            $sent = true;
        }
    }
}

$active = '';
$pageTitle = 'Восстановление пароля — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
    <section class="auth-wrap">
        <div class="auth-card">
            <div class="auth-card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M9 15.5v.5"/><path d="M12 15v1"/><path d="M15 15v1"/></svg>
            </div>
            <h2>Восстановление пароля</h2>

            <?php if ($errors['global'] !== ''): ?>
                <div class="alert alert-error"><?= e($errors['global']) ?></div>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="alert alert-ok">Если аккаунт с таким email существует, мы отправили ссылку для сброса пароля. Ссылка действует 1 час.</div>
                <?php if (APP_DEV && $devLink !== ''): ?>
                    <div class="alert alert-warn">Режим разработки: ссылка для сброса — <a href="<?= e($devLink) ?>">reset_password.php?token=…</a></div>
                <?php endif; ?>
            <?php else: ?>
                <p class="auth-card-sub">Укажите email, привязанный к аккаунту, — мы пришлём ссылку для смены пароля.</p>

                <form class="form-auth" method="post" action="forgot_password.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="form-row<?= $errors['email'] !== '' ? ' has-error' : '' ?>">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($old['email']) ?>" placeholder="you@example.ru" class="<?= $errors['email'] !== '' ? 'is-invalid' : '' ?>">
                        <?php if ($errors['email'] !== ''): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <button class="btn btn-primary btn-block" type="submit">Отправить ссылку</button>
                </form>
            <?php endif; ?>

            <p class="auth-alt"><a href="login.php">← Вернуться ко входу</a></p>
        </div>
    </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
