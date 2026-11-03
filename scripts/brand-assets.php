<?php

/*
 * Logo variations from the master logo (resources/brand/lankaguide360-logo-source.png, white
 * background). Run after replacing the master:  php scripts/brand-assets.php
 *
 * Writes public/images/brand/:
 *   logo-full.png / .webp     whole logo (emblem, name, tagline) on a transparent background
 *   logo-stacked.png / .webp  emblem + name, without the tagline (smaller places, PDFs)
 *   mark.png (512), mark-128.png  emblem only, transparent (navbar, light backgrounds)
 *   mark-badge.png (512)      emblem on a white circle (dark backgrounds, social avatars)
 *   icon-512.png, apple-touch-icon.png (180), favicon-32.png, favicon.ico  app/browser icons
 *   (apple-touch-icon.png and favicon.ico are also written to public/, where browsers look first)
 *
 * The white page around the artwork is removed with a flood fill from the edges, so white
 * shapes inside the emblem (the island, the clouds) stay white; edge pixels are un-blended
 * from white to keep the anti-aliasing smooth on any background.
 */

ini_set('memory_limit', '1024M');

$root = dirname(__DIR__);
$source = $root.'/resources/brand/lankaguide360-logo-source.png';
$out = $root.'/public/images/brand';
@mkdir($out, 0775, true);

$img = imagecreatefrompng($source) ?: exit("Cannot read {$source}\n");
imagepalettetotruecolor($img);
$w = imagesx($img);
$h = imagesy($img);

// Areas of the master (measured from the artwork; rows between them are empty).
$emblem = ['y0' => 100, 'y1' => 770];
$name = ['y0' => 770, 'y1' => 1075];

// ---------------------------------------------------------------- 1. remove the white page

$rgb = static fn (int $p): array => [($p >> 16) & 255, ($p >> 8) & 255, $p & 255];
$isPage = static fn (array $c): bool => min($c) >= 238;

$background = str_repeat("\0", $w * $h);
$stack = [];
for ($x = 0; $x < $w; $x++) {
    $stack[] = $x;
    $stack[] = ($h - 1) * $w + $x;
}
for ($y = 0; $y < $h; $y++) {
    $stack[] = $y * $w;
    $stack[] = $y * $w + $w - 1;
}
while ($stack) {
    $i = array_pop($stack);
    if ($background[$i] !== "\0") {
        continue;
    }
    $x = $i % $w;
    $y = intdiv($i, $w);
    if (! $isPage($rgb(imagecolorat($img, $x, $y)))) {
        continue;
    }
    $background[$i] = "\1";
    if ($x > 0) {
        $stack[] = $i - 1;
    }
    if ($x < $w - 1) {
        $stack[] = $i + 1;
    }
    if ($y > 0) {
        $stack[] = $i - $w;
    }
    if ($y < $h - 1) {
        $stack[] = $i + $w;
    }
}

$clear = imagecreatetruecolor($w, $h);
imagealphablending($clear, false);
imagesavealpha($clear, true);
imagefill($clear, 0, 0, imagecolorallocatealpha($clear, 255, 255, 255, 127));

$nearPage = static function (int $x, int $y) use ($background, $w, $h): bool {
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            if ($nx >= 0 && $ny >= 0 && $nx < $w && $ny < $h && $background[$ny * $w + $nx] === "\1") {
                return true;
            }
        }
    }

    return false;
};

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if ($background[$y * $w + $x] === "\1") {
            continue;
        }
        [$r, $g, $b] = $rgb(imagecolorat($img, $x, $y));
        $a = 1.0;
        if ($nearPage($x, $y)) {
            // "Colour to alpha" against white for the anti-aliased rim.
            $a = max(255 - $r, 255 - $g, 255 - $b) / 255;
            if ($a <= 0.02) {
                continue;
            }
            [$r, $g, $b] = array_map(fn ($c) => (int) round(max(0, min(255, ($c - 255 * (1 - $a)) / $a))), [$r, $g, $b]);
        }
        imagesetpixel($clear, $x, $y, imagecolorallocatealpha($clear, $r, $g, $b, (int) round(127 * (1 - $a))));
    }
}

// ---------------------------------------------------------------- helpers

/** Bounding box of visible pixels inside [y0, y1). */
$bbox = static function (GdImage $im, int $y0, int $y1): array {
    $minX = imagesx($im);
    $minY = $y1;
    $maxX = 0;
    $maxY = $y0;
    for ($y = $y0; $y < $y1; $y++) {
        for ($x = 0; $x < imagesx($im); $x++) {
            if (((imagecolorat($im, $x, $y) >> 24) & 127) < 120) {
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
    }

    return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
};

$canvas = static function (int $cw, int $ch): GdImage {
    $c = imagecreatetruecolor($cw, $ch);
    imagealphablending($c, false);
    imagesavealpha($c, true);
    imagefill($c, 0, 0, imagecolorallocatealpha($c, 255, 255, 255, 127));
    imagealphablending($c, true);

    return $c;
};

/** Copy a region into a new transparent canvas of $cw x $ch, centred, with $pad around it. */
$fit = static function (GdImage $src, array $box, int $cw, int $ch, float $pad = 0) use ($canvas): GdImage {
    [$sx, $sy, $sw, $sh] = $box;
    $c = $canvas($cw, $ch);
    $scale = min(($cw * (1 - 2 * $pad)) / $sw, ($ch * (1 - 2 * $pad)) / $sh);
    $dw = (int) round($sw * $scale);
    $dh = (int) round($sh * $scale);
    imagecopyresampled($c, $src, intdiv($cw - $dw, 2), intdiv($ch - $dh, 2), $sx, $sy, $dw, $dh, $sw, $sh);

    return $c;
};

$save = static function (GdImage $im, string $file, bool $webp = false) use ($out): void {
    imagesavealpha($im, true);
    imagepng($im, "{$out}/{$file}.png", 9);
    if ($webp) {
        imagewebp($im, "{$out}/{$file}.webp", 90);
    }
    echo "  {$file}.png".($webp ? ' + .webp' : '')."\n";
};

/** Flatten onto a white disc (badge) or white square (icons). */
$onWhite = static function (GdImage $mark, string $shape, float $inner) use ($canvas): GdImage {
    $size = imagesx($mark);
    $c = $canvas($size, $size);
    $white = imagecolorallocate($c, 255, 255, 255);
    if ($shape === 'circle') {
        imagefilledellipse($c, intdiv($size, 2), intdiv($size, 2), $size - 2, $size - 2, $white);
    } else {
        imagefilledrectangle($c, 0, 0, $size, $size, $white);
    }
    $d = (int) round($size * $inner);
    $o = intdiv($size - $d, 2);
    imagecopyresampled($c, $mark, $o, $o, 0, 0, $d, $d, $size, $size);

    return $c;
};

// ---------------------------------------------------------------- 2. variations

echo "Writing public/images/brand:\n";

$all = $bbox($clear, 0, $h);
$full = $fit($clear, $all, 1000, (int) round(1000 * $all[3] / $all[2]), 0.02);
$save($full, 'logo-full', true);

$stackedBox = $bbox($clear, 0, $name['y1']);
$stacked = $fit($clear, $stackedBox, 800, (int) round(800 * $stackedBox[3] / $stackedBox[2]), 0.02);
$save($stacked, 'logo-stacked', true);

$markBox = $bbox($clear, $emblem['y0'], $emblem['y1']);
$mark = $fit($clear, $markBox, 512, 512, 0.01);
$save($mark, 'mark', true);
$save($fit($mark, [0, 0, 512, 512], 128, 128), 'mark-128');

$save($onWhite($mark, 'circle', 0.80), 'mark-badge', true);
$icon = $onWhite($mark, 'square', 0.86);
$save($icon, 'icon-512');
$save($fit($icon, [0, 0, 512, 512], 180, 180), 'apple-touch-icon');
copy("{$out}/apple-touch-icon.png", $root.'/public/apple-touch-icon.png'); // iOS asks for it at the site root
$favicon = $fit($mark, [0, 0, 512, 512], 32, 32);
$save($favicon, 'favicon-32');

// favicon.ico with one 32x32 PNG inside (supported by every current browser).
ob_start();
imagepng($favicon);
$png = ob_get_clean();
file_put_contents($root.'/public/favicon.ico', pack('vvv', 0, 1, 1).pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22).$png);
echo "  ../../favicon.ico\n";
