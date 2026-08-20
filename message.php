<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$pdo = pdo();
$me = current_user();
$meId = (int) $me['id'];

if (!isset($_GET['to'])) {
    header('Location: account.php?tab=messages');
    exit;
}

$sellerId = (int) $_GET['to'];
$itemId = isset($_GET['item']) ? (int) $_GET['item'] : null;
$auctionId = isset($_GET['auction']) ? (int) $_GET['auction'] : null;

if ($sellerId === $meId) {
    header('Location: account.php?tab=messages');
    exit;
}

$sellerStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
$sellerStmt->execute([$sellerId]);
$seller = $sellerStmt->fetch();
if (!$seller) {
    header('Location: account.php?tab=messages');
    exit;
}

$subject = '';
$itemUrl = '';
if ($itemId !== null) {
    $itStmt = $pdo->prepare('SELECT id, title, user_id FROM items WHERE id = ?');
    $itStmt->execute([$itemId]);
    $it = $itStmt->fetch();
    if ($it && (int) $it['user_id'] === $sellerId) {
        $subject = $it['title'];
        $itemUrl = 'item.php?id=' . (int) $it['id'];
    }
} elseif ($auctionId !== null) {
    $auStmt = $pdo->prepare('SELECT id, title, user_id FROM auctions WHERE id = ?');
    $auStmt->execute([$auctionId]);
    $au = $auStmt->fetch();
    if ($au && (int) $au['user_id'] === $sellerId) {
        $subject = $au['title'];
        $itemUrl = 'auction.php?id=' . (int) $au['id'];
    }
}
if ($subject === '') {
    $subject = 'Диалог с ' . $seller['name'];
}

$conv = open_conversation($pdo, $meId, $sellerId, $itemId, $auctionId, $subject, $itemUrl);
header('Location: account.php?tab=messages&id=' . (int) $conv['id']);
exit;