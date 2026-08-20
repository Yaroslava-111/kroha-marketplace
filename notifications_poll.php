<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = pdo();
finalize_due_auctions($pdo);
$userId = current_user_id();
echo json_encode([
    'ok' => true,
    'notifications' => unread_notifications_count($pdo, $userId),
    'messages' => unread_messages_count($pdo, $userId),
]);
