<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_login();

$pdo = pdo();
$meId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['toggle_fav'] ?? '');
    if ($action === 'clear') {
        $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$meId]);
    } elseif (($action === 'item' || $action === 'auction') && (int) ($_POST['target_id'] ?? 0) > 0) {
        toggle_favorite($pdo, $meId, $action, (int) $_POST['target_id']);
    }
    $next = (string) ($_POST['next'] ?? '');
    if ($next === '' || str_contains($next, '://') || $next[0] === '\\' || str_starts_with($next, '//')) {
        $next = 'account.php?tab=favorites';
    }
    header('Location: ' . $next);
    exit;
}

header('Location: account.php?tab=favorites');
exit;
