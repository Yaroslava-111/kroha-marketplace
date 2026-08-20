<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    logout_user();
}

header('Location: index.php');
exit;
