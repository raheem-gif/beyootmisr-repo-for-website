<?php

// Builds the logo and PWA icons from the master logo file given as argv[1].
// Usage: php tools/make-brand.php path/to/logo.jpg
$src = imagecreatefromjpeg($argv[1]);
[$x0, $y0, $x1, $y1] = [0, 281, 1319, 542];
$w = $x1 - $x0 + 1;
$h = $y1 - $y0 + 1;
$crop = imagecreatetruecolor($w, $h);
imagealphablending($crop, false);
imagesavealpha($crop, true);
imagecopy($crop, $src, 0, 0, $x0, $y0, $w, $h);
// Rounded corners (radius from the artwork, about 26 px at this size).
$r = 26;
$clear = imagecolorallocatealpha($crop, 0, 0, 0, 127);
for ($y = 0; $y < $r; $y++) {
    for ($x = 0; $x < $r; $x++) {
        $d = sqrt(($r - $x - 0.5) ** 2 + ($r - $y - 0.5) ** 2);
        if ($d > $r) {
            foreach ([[$x, $y], [$w - 1 - $x, $y], [$x, $h - 1 - $y], [$w - 1 - $x, $h - 1 - $y]] as [$px, $py]) {
                imagesetpixel($crop, $px, $py, $clear);
            }
        }
    }
}
$out = __DIR__.'/../public/brand';
@mkdir($out, 0755, true);
foreach ([480, 960] as $tw) {
    $th = (int) round($h * $tw / $w);
    $im = imagecreatetruecolor($tw, $th);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagecopyresampled($im, $crop, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagepng($im, "$out/esnad-logo-$tw.png", 9);
    imagewebp($im, "$out/esnad-logo-$tw.webp", 90);
}
// Square icons: slate canvas with the logo centred.
foreach ([192 => 0.86, 512 => 0.86, 180 => 0.86, 'maskable-512' => 0.66] as $name => $scale) {
    $s = is_int($name) ? $name : 512;
    $im = imagecreatetruecolor($s, $s);
    imagefill($im, 0, 0, imagecolorallocate($im, 0x33, 0x3C, 0x43));
    $tw = (int) round($s * $scale);
    $th = (int) round($h * $tw / $w);
    imagealphablending($im, true);
    imagecopyresampled($im, $crop, (int) (($s - $tw) / 2), (int) (($s - $th) / 2), 0, 0, $tw, $th, $w, $h);
    imagepng($im, "$out/icon-$name.png", 9);
}
echo "done\n";
