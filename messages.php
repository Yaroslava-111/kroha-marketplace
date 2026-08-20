<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

require_login();

$params = [];
$convId = (int) ($_GET['id'] ?? 0);
if ($convId > 0) {
    $params['id'] = $convId;
}
if ((string) ($_GET['view'] ?? '') === 'archive') {
    $params['view'] = 'archive';
}
$url = 'account.php?tab=messages';
if ($params) {
    $url .= '&' . http_build_query($params);
}
header('Location: ' . $url);
exit;
