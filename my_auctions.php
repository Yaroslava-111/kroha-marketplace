<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

header('Location: account.php?tab=auctions');
exit;
