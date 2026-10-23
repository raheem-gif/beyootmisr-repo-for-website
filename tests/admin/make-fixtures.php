<?php
/**
 * Writes the photo/PDF fixtures used by shots-content.js (drawn scenes, no people).
 * Usage: php tests/admin/make-fixtures.php <out-dir>
 */
declare(strict_types=1);

$out = rtrim($argv[1] ?? '', '/');
if ($out === '') {
    fwrite(STDERR, "usage: php make-fixtures.php <out-dir>\n");
    exit(2);
}
@mkdir($out, 0755, true);
$scenes = [
    'roof' => [[186, 214, 236], [236, 226, 208], [150, 120, 90]],
    'walls' => [[200, 222, 240], [220, 200, 170], [190, 160, 120]],
    'boxes' => [[230, 236, 242], [210, 214, 220], [200, 150, 80]],
    'water' => [[170, 205, 235], [120, 170, 210], [90, 140, 190]],
];
$i = 0;
foreach ($scenes as $name => [$top, $bottom, $obj]) {
    $w = 1600;
    $h = 1100;
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im, (int) ($top[0] * (1 - $t) + $bottom[0] * $t), (int) ($top[1] * (1 - $t) + $bottom[1] * $t), (int) ($top[2] * (1 - $t) + $bottom[2] * $t));
        imageline($im, 0, $y, $w, $y, $c);
    }
    $groundY = (int) ($h * 0.72);
    imagefilledrectangle($im, 0, $groundY, $w, $h, imagecolorallocate($im, 196, 176, 140));
    mt_srand(++$i + 7);
    for ($k = 0; $k < 6; $k++) {
        $x = mt_rand(60, $w - 360);
        $bw = mt_rand(180, 320);
        $bh = mt_rand(160, 420);
        $y0 = $groundY - $bh;
        $c = imagecolorallocate($im, max(0, min(255, $obj[0] + mt_rand(-30, 30))), max(0, min(255, $obj[1] + mt_rand(-30, 30))), max(0, min(255, $obj[2] + mt_rand(-30, 30))));
        imagefilledrectangle($im, $x, $y0, $x + $bw, $groundY, $c);
        if ($k % 2 === 0) { // a lit arched window
            $win = imagecolorallocate($im, 239, 169, 39);
            $cx = $x + intdiv($bw, 2);
            imagefilledarc($im, $cx, $y0 + 70, 60, 60, 180, 360, $win, IMG_ARC_PIE);
            imagefilledrectangle($im, $cx - 30, $y0 + 70, $cx + 30, $y0 + 130, $win);
        }
    }
    imagejpeg($im, $out . '/' . $name . '.jpg', 85);
}
file_put_contents($out . '/report.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
echo "fixtures written to $out\n";
