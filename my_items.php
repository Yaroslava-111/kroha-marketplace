<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$query = isset($_GET['ok']) ? '&ok=edit' : '';
header('Location: account.php?tab=items' . $query);
exit;
