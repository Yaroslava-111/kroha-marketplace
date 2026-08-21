<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$pdo = pdo();
$me = current_user();
if (!$me) {
    header('Location: login.php?next=' . urlencode($_POST['next'] ?? 'index.php?type=all'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    header('Location: index.php?type=all');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$next = (string) ($_POST['next'] ?? 'index.php?type=all');
if (!str_starts_with($next, '/') && !str_starts_with($next, 'index.php')) {
    $next = 'index.php?type=all';
}
$sep = str_contains($next, '?') ? '&' : '?';

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM saved_searches WHERE id = ? AND user_id = ?')
        ->execute([(int) ($_POST['id'] ?? 0), (int) $me['id']]);
    header('Location: account.php?tab=searches&ok=deleted');
    exit;
}

$params = trim((string) ($_POST['params'] ?? ''));
if ($params === '') {
    $params = normalize_saved_search_params($_POST);
}
if ($params === '') {
    header('Location: ' . $next . $sep . 'ss_err=empty');
    exit;
}

$count = $pdo->prepare('SELECT COUNT(*) FROM saved_searches WHERE user_id = ?');
$count->execute([(int) $me['id']]);
if ((int) $count->fetchColumn() >= 20) {
    header('Location: ' . $next . $sep . 'ss_err=limit');
    exit;
}

parse_str($params, $f);
$label = describe_search($f);
$stmt = $pdo->prepare('SELECT id FROM saved_searches WHERE user_id = ? AND params = ?');
$stmt->execute([(int) $me['id'], $params]);
if ($stmt->fetch()) {
    header('Location: ' . $next . $sep . 'ss_err=exists');
    exit;
}

$pdo->prepare('INSERT INTO saved_searches (user_id, params, label) VALUES (?, ?, ?)')
    ->execute([(int) $me['id'], $params, $label]);
header('Location: ' . $next . $sep . 'ss_ok=1');
exit;
