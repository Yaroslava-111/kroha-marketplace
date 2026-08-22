<?php
declare(strict_types=1);

/* Генератор демо-фотографий для лотов без фото.
 * Рисует плоские иллюстрации в стиле сайта, кладёт PNG в uploads/,
 * делает миниатюры и заполняет items.photos / auctions.photos.
 * Запуск: php seed_photos.php */

require_once __DIR__ . '/config.php';

const W = 800;
const H = 600;
const BG = '#FBF4E8';

function pal(GdImage $im, string $hex): int
{
    return imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

/** Закруглённый прямоугольник */
function rr(GdImage $im, int $x, int $y, int $w, int $h, int $r, int $c): void
{
    $r = min($r, (int) floor($w / 2), (int) floor($h / 2));
    imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $c);
    imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $c);
    imagefilledarc($im, $x + $r, $y + $r, 2 * $r, 2 * $r, 180, 270, $c, IMG_ARC_PIE);
    imagefilledarc($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, 270, 360, $c, IMG_ARC_PIE);
    imagefilledarc($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, 0, 90, $c, IMG_ARC_PIE);
    imagefilledarc($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, 90, 180, $c, IMG_ARC_PIE);
}

function line(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $t, int $c): void
{
    imagesetthickness($im, $t);
    imageline($im, $x1, $y1, $x2, $y2, $c);
    imagesetthickness($im, 1);
}

function shadow(GdImage $im): void
{
    imagefilledellipse($im, 400, 512, 380, 44, pal($im, '#EBDCC3'));
}

function dots(GdImage $im): void
{
    $d = pal($im, '#F3E3CB');
    foreach ([[95, 95, 12], [706, 128, 16], [128, 508, 10], [688, 486, 12]] as [$x, $y, $r]) {
        imagefilledellipse($im, $x, $y, 2 * $r, 2 * $r, $d);
    }
}

/* --- Сюжеты --- */

function stroller_draw(GdImage $im, string $bodyHex, string $darkHex): void
{
    $ink = pal($im, '#3F3A34');
    $white = pal($im, '#FFFFFF');
    $body = pal($im, $bodyHex);
    $dark = pal($im, $darkHex);

    // колёса
    foreach ([315, 515] as $cx) {
        imagefilledellipse($im, $cx, 470, 92, 92, $ink);
        imagefilledellipse($im, $cx, 470, 30, 30, $white);
    }
    // рама
    line($im, 330, 305, 305, 450, 9, $ink);
    line($im, 520, 340, 502, 445, 9, $ink);
    line($im, 520, 245, 600, 175, 9, $ink);
    rr($im, 586, 158, 38, 20, 9, $dark);
    // люлька
    rr($im, 260, 252, 290, 112, 26, $body);
    // капор-купол
    imagefilledarc($im, 320, 260, 214, 214, 180, 360, $body, IMG_ARC_PIE);
    imagesetthickness($im, 6);
    imagearc($im, 320, 260, 214, 214, 180, 360, $dark);
    imagesetthickness($im, 1);
    // ремень
    line($im, 300, 300, 430, 300, 5, $dark);
}

function snowsuit_draw(GdImage $im, string $mainHex, string $darkHex, string $accentHex, bool $bow = false): void
{
    $ink = pal($im, '#3F3A34');
    $main = pal($im, $mainHex);
    $dark = pal($im, $darkHex);
    $accent = pal($im, $accentHex);

    // капюшон
    imagefilledellipse($im, 402, 208, 158, 130, $dark);
    imagefilledellipse($im, 402, 212, 92, 76, pal($im, BG));
    // туловище
    rr($im, 320, 238, 165, 172, 32, $main);
    // руки
    rr($im, 262, 248, 52, 142, 26, $main);
    rr($im, 486, 248, 52, 142, 26, $main);
    imagefilledellipse($im, 288, 398, 46, 46, $dark);
    imagefilledellipse($im, 512, 398, 46, 46, $dark);
    // ноги
    rr($im, 332, 398, 68, 82, 20, $main);
    rr($im, 402, 398, 68, 82, 20, $main);
    rr($im, 326, 458, 80, 30, 14, $dark);
    rr($im, 396, 458, 80, 30, 14, $dark);
    // молния и воротник
    line($im, 402, 272, 402, 392, 6, $accent);
    rr($im, 372, 234, 60, 16, 8, $accent);
    if ($bow) {
        imagefilledpolygon($im, [496, 174, 452, 146, 452, 202], $accent);
        imagefilledpolygon($im, [500, 174, 544, 146, 544, 202], $accent);
        imagefilledellipse($im, 498, 174, 30, 26, pal($im, '#C98B3E'));
        line($im, 498, 188, 498, 232, 5, pal($im, '#C98B3E'));
    }
}

function lego_draw(GdImage $im): void
{
    $m = pal($im, '#E0A458');
    $md = pal($im, '#C98B3E');
    $t = pal($im, '#C96F4A');
    $td = pal($im, '#A85438');
    $b = pal($im, '#7C9EB2');
    $bd = pal($im, '#5E7E93');
    $base = pal($im, '#6B886B');

    rr($im, 220, 432, 360, 54, 12, $base);
    // синий кирпич сверху
    rr($im, 300, 228, 120, 86, 12, $b);
    rr($im, 322, 210, 32, 20, 7, $bd);
    rr($im, 368, 210, 32, 20, 7, $bd);
    // горчичный
    rr($im, 280, 312, 160, 122, 14, $m);
    rr($im, 306, 294, 38, 20, 8, $md);
    rr($im, 378, 294, 38, 20, 8, $md);
    // терракотовый
    rr($im, 442, 338, 130, 96, 14, $t);
    rr($im, 464, 320, 34, 20, 8, $td);
    rr($im, 522, 320, 34, 20, 8, $td);
}

function bed_draw(GdImage $im): void
{
    $dsage = pal($im, '#6B886B');
    $lsage = pal($im, '#B8C9B0');
    $white = pal($im, '#FFFFFF');
    $terra = pal($im, '#C96F4A');
    $terraL = pal($im, '#D9A084');
    $ink = pal($im, '#3F3A34');

    rr($im, 185, 215, 58, 230, 16, $dsage);
    rr($im, 592, 320, 52, 125, 14, $dsage);
    rr($im, 196, 443, 30, 32, 6, $ink);
    rr($im, 596, 443, 30, 32, 6, $ink);
    rr($im, 228, 330, 380, 62, 18, $white);
    rr($im, 240, 306, 96, 48, 14, pal($im, '#FDF9F1'));
    rr($im, 350, 320, 256, 72, 14, $terra);
    rr($im, 350, 344, 256, 12, 6, $terraL);
    line($im, 240, 356, 350, 356, 4, pal($im, '#DCCDB6'));
}

function backpack_draw(GdImage $im): void
{
    $ink = pal($im, '#3F3A34');
    $terra = pal($im, '#C96F4A');
    $terraD = pal($im, '#A85438');
    $must = pal($im, '#E0A458');

    rr($im, 315, 178, 26, 92, 12, $ink);
    rr($im, 459, 178, 26, 92, 12, $ink);
    rr($im, 300, 216, 200, 254, 40, $terra);
    imagefilledarc($im, 400, 224, 204, 152, 180, 360, $terraD, IMG_ARC_PIE);
    rr($im, 390, 298, 20, 72, 8, $must);
    rr($im, 380, 350, 40, 24, 8, $ink);
    rr($im, 330, 370, 140, 82, 24, $must);
    line($im, 346, 388, 454, 388, 4, $terraD);
}

function bodystack_draw(GdImage $im): void
{
    $pink = pal($im, '#D9A5A5');
    $pinkD = pal($im, '#BC8888');
    $blue = pal($im, '#7C9EB2');
    $blueD = pal($im, '#5E7E93');
    $must = pal($im, '#E0A458');
    $mustD = pal($im, '#C98B3E');

    rr($im, 275, 402, 250, 56, 18, $pink);
    line($im, 295, 430, 505, 430, 4, $pinkD);
    rr($im, 263, 346, 270, 56, 18, $blue);
    line($im, 285, 374, 511, 374, 4, $blueD);
    rr($im, 287, 290, 230, 56, 18, $must);
    foreach ([370, 400, 430] as $bx) {
        imagefilledellipse($im, $bx, 318, 13, 13, $mustD);
    }
}

function schoolkit_draw(GdImage $im): void
{
    $ink = pal($im, '#3F3A34');
    $white = pal($im, '#FFFFFF');
    $blue = pal($im, '#7C9EB2');
    $lsage = pal($im, '#B8C9B0');
    $must = pal($im, '#E0A458');
    $wood = pal($im, '#E8CBA8');
    $pink = pal($im, '#D9A5A5');

    // тетрадь
    rr($im, 250, 298, 152, 194, 12, $white);
    rr($im, 250, 298, 24, 194, 12, $blue);
    for ($i = 0; $i < 3; $i++) {
        line($im, 296, 350 + $i * 40, 372, 350 + $i * 40, 3, pal($im, '#D8CFC0'));
    }
    // карандаш
    rr($im, 430, 356, 176, 28, 8, $must);
    rr($im, 414, 356, 22, 28, 8, $pink);
    imagefilledpolygon($im, [606, 356, 650, 370, 606, 384], $wood);
    imagefilledpolygon($im, [636, 366, 650, 370, 636, 375], $ink);
    // линейка
    rr($im, 420, 424, 222, 36, 8, $lsage);
    for ($i = 0; $i < 9; $i++) {
        line($im, 440 + $i * 23, 424, 440 + $i * 23, $i % 2 ? 438 : 444, 3, $ink);
    }
    // мелок
    rr($im, 466, 300, 116, 22, 11, $pink);
}

function jacket_draw(GdImage $im, string $mainHex, string $darkHex, string $accentHex): void
{
    $ink = pal($im, '#3F3A34');
    $main = pal($im, $mainHex);
    $dark = pal($im, $darkHex);
    $accent = pal($im, $accentHex);

    // капюшон
    imagefilledarc($im, 400, 238, 156, 124, 180, 360, $dark, IMG_ARC_PIE);
    imagefilledellipse($im, 400, 244, 84, 64, pal($im, BG));
    // корпус и рукава
    rr($im, 310, 244, 180, 198, 30, $main);
    rr($im, 255, 252, 55, 148, 26, $main);
    rr($im, 490, 252, 55, 148, 26, $main);
    rr($im, 259, 382, 47, 26, 12, $dark);
    rr($im, 494, 382, 47, 26, 12, $dark);
    rr($im, 318, 420, 164, 24, 10, $dark);
    // молния и карманы
    line($im, 400, 268, 400, 420, 6, $accent);
    line($im, 332, 362, 366, 396, 5, $ink);
    line($im, 468, 362, 434, 396, 5, $ink);
}

function make_photo(string $subject, array $p): GdImage
{
    $im = imagecreatetruecolor(W, H);
    imagefilledrectangle($im, 0, 0, W, H, pal($im, BG));
    dots($im);
    shadow($im);
    match ($subject) {
        'stroller' => stroller_draw($im, $p[0], $p[1]),
        'snowsuit' => snowsuit_draw($im, $p[0], $p[1], $p[2] ?? '#FFFFFF', $p[3] ?? false),
        'jacket' => jacket_draw($im, $p[0], $p[1], $p[2]),
        'lego' => lego_draw($im),
        'bed' => bed_draw($im),
        'backpack' => backpack_draw($im),
        'bodystack' => bodystack_draw($im),
        'schoolkit' => schoolkit_draw($im),
    };
    return $im;
}

/* --- Нормализация фото --- */

function flat_photos(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return [];
    }
    if (isset($d[0]) && is_array($d[0])) {
        $flat = [];
        array_walk_recursive($d, function ($v) use (&$flat) {
            if (is_string($v)) {
                $flat[] = $v;
            }
        });
        $d = $flat;
    }
    return array_values(array_filter($d, 'is_string'));
}

/* --- Основной проход --- */

$pdo = pdo();
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$targets = [
    'items' => [
        1 => ['stroller', ['#C96F4A', '#A85438']],
        2 => ['snowsuit', ['#7C9EB2', '#5E7E93', '#E0A458']],
        3 => ['lego', []],
        4 => ['bed', []],
        5 => ['backpack', []],
        6 => ['bodystack', []],
    ],
    'auctions' => [
        1 => ['stroller', ['#8FA98F', '#6B886B']],
        2 => ['snowsuit', ['#8FA98F', '#6B886B', '#C96F4A', true]],
        3 => ['snowsuit', ['#D9A5A5', '#B97F7F', '#A85438']],
        4 => ['schoolkit', []],
        5 => ['jacket', ['#E0A458', '#C98B3E', '#3F3A34']],
    ],
];

$done = 0;
foreach ($targets as $table => $map) {
    $stmt = $pdo->prepare("SELECT id, photos FROM {$table} WHERE id = ?");
    $upd = $pdo->prepare("UPDATE {$table} SET photos = ? WHERE id = ?");
    foreach ($map as $id => [$subject, $p]) {
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            continue;
        }
        $existing = flat_photos($row['photos']);
        if ($existing) {
            continue;
        }
        $file = "demo_{$table}_{$id}.png";
        $path = $uploadDir . '/' . $file;
        imagepng(make_photo($subject, $p), $path, 6);
        make_thumb($path, $uploadDir . '/t_' . $file);
        $upd->execute([json_encode(['uploads/' . $file]), $id]);
        $done++;
        echo "+ {$table} #{$id}: uploads/{$file}\n";
    }

    // чиним вложенные массивы у реальных фото
    $all = $pdo->query("SELECT id, photos FROM {$table}")->fetchAll();
    foreach ($all as $row) {
        $raw = (string) $row['photos'];
        if ($raw === '' || $raw === '[]') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            $upd->execute([json_encode(flat_photos($raw)), $row['id']]);
            echo "~ {$table} #{$row['id']}: нормализован JSON\n";
        }
    }
}

echo "Готово. Заполнено фото: {$done}\n";
