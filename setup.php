<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$schemaFile = DB_DRIVER === 'sqlite'
    ? __DIR__ . '/database/schema.sqlite.sql'
    : __DIR__ . '/database/schema.mysql.sql';

$sql = file_get_contents($schemaFile);
if ($sql === false) {
    fwrite(STDERR, "Не удалось прочитать схему: {$schemaFile}\n");
    exit(1);
}

$pdo = pdo();
$pdo->exec($sql);

echo "Схема применена. Драйвер: {$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)}\n";
