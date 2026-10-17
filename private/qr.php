<?php
declare(strict_types=1);

function qr_gf_multiply(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
}

function qr_reed_solomon_divisor(int $degree): array
{
    $result = array_fill(0, $degree, 0); $result[$degree - 1] = 1; $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $result[$j] = qr_gf_multiply($result[$j], $root);
            if ($j + 1 < $degree) $result[$j] ^= $result[$j + 1];
        }
        $root = qr_gf_multiply($root, 2);
    }
    return $result;
}

function qr_reed_solomon_remainder(array $data, array $divisor): array
{
    $result = array_fill(0, count($divisor), 0);
    foreach ($data as $byte) {
        $factor = $byte ^ array_shift($result); $result[] = 0;
        foreach ($divisor as $i => $coefficient) $result[$i] ^= qr_gf_multiply($coefficient, $factor);
    }
    return $result;
}

function eventqr_svg(string $text): string
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    if (count($bytes) > 134) throw new RuntimeException('El enlace es demasiado largo para generar el QR.');
    $bits = [0, 1, 0, 0];
    $length = count($bytes);
    for ($i = 7; $i >= 0; $i--) $bits[] = ($length >> $i) & 1;
    foreach ($bytes as $byte) for ($i = 7; $i >= 0; $i--) $bits[] = ($byte >> $i) & 1;
    $capacity = 136 * 8;
    for ($i = 0; $i < min(4, $capacity - count($bits)); $i++) $bits[] = 0;
    while (count($bits) % 8) $bits[] = 0;
    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0; for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | $bits[$i + $j]; $data[] = $byte;
    }
    for ($pad = 0; count($data) < 136; $pad++) $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
    $divisor = qr_reed_solomon_divisor(18); $blocks = [array_slice($data, 0, 68), array_slice($data, 68, 68)]; $ecc = [];
    foreach ($blocks as $block) $ecc[] = qr_reed_solomon_remainder($block, $divisor);
    $codewords = [];
    for ($i = 0; $i < 68; $i++) foreach ($blocks as $block) $codewords[] = $block[$i];
    for ($i = 0; $i < 18; $i++) foreach ($ecc as $block) $codewords[] = $block[$i];
    $matrix = array_fill(0, 41, array_fill(0, 41, null)); $set = static function(int $x, int $y, bool $dark) use (&$matrix): void { if ($x >= 0 && $x < 41 && $y >= 0 && $y < 41) $matrix[$y][$x] = $dark; };
    foreach ([[3,3],[37,3],[3,37]] as [$cx,$cy]) {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $distance = max(abs($dx), abs($dy)); $set($cx + $dx, $cy + $dy, $distance !== 2 && $distance !== 4);
        }
    }
    for ($i = 8; $i < 33; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set(34 + $dx, 34 + $dy, max(abs($dx), abs($dy)) !== 1);
    $format = (1 << 3); $remainder = $format;
    for ($i = 0; $i < 10; $i++) $remainder = ($remainder << 1) ^ ((($remainder >> 9) & 1) * 0x537);
    $formatBits = (($format << 10) | $remainder) ^ 0x5412;
    for ($i = 0; $i <= 5; $i++) $set(8, $i, (($formatBits >> $i) & 1) !== 0);
    $set(8, 7, (($formatBits >> 6) & 1) !== 0); $set(8, 8, (($formatBits >> 7) & 1) !== 0); $set(7, 8, (($formatBits >> 8) & 1) !== 0);
    for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, (($formatBits >> $i) & 1) !== 0);
    for ($i = 0; $i < 8; $i++) $set(40 - $i, 8, (($formatBits >> $i) & 1) !== 0);
    for ($i = 8; $i < 15; $i++) $set(8, 41 - 15 + $i, (($formatBits >> $i) & 1) !== 0);
    $set(8, 33, true);
    $bitIndex = 0; $upward = true;
    for ($right = 40; $right >= 1; $right -= 2) {
        if ($right === 6) $right--;
        for ($vert = 0; $vert < 41; $vert++) {
            $y = $upward ? 40 - $vert : $vert;
            for ($offset = 0; $offset < 2; $offset++) {
                $x = $right - $offset;
                if ($matrix[$y][$x] !== null) continue;
                $bit = $bitIndex < count($codewords) * 8 ? (($codewords[intdiv($bitIndex, 8)] >> (7 - ($bitIndex % 8))) & 1) : 0; $bitIndex++;
                $set($x, $y, ($bit === 1) !== (($x + $y) % 2 === 0));
            }
        }
        $upward = !$upward;
    }
    $path = [];
    for ($y = 0; $y < 41; $y++) for ($x = 0; $x < 41; $x++) if ($matrix[$y][$x]) $path[] = 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="490" height="490" viewBox="0 0 49 49" shape-rendering="crispEdges"><rect width="49" height="49" fill="#fff"/><path d="' . implode('', $path) . '" fill="#000"/></svg>';
}
