<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$tab = ($_GET['tab'] ?? '') === 'auctions' ? 'auctions' : 'items';
header('Location: account.php?tab=' . $tab);
exit;
