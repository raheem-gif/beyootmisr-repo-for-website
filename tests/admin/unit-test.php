<?php
/**
 * Unit tests for the admin libraries (no HTTP): image processing (EXIF orientation,
 * metadata stripping, resizing, rejection of non-images), upload deletion safety,
 * backups, protected storage, input cleaning.
 *
 * Usage: php tests/admin/unit-test.php <sandbox-site-dir>
 * The sandbox is a disposable copy of public/ (see run.sh). Never point it at the real site.
 */
declare(strict_types=1);

$site = rtrim($argv[1] ?? '', '/');
if ($site === '' || !is_file($site . '/app/admin/bootstrap.php')) {
    fwrite(STDERR, "usage: php unit-test.php <sandbox-site-dir>\n");
    exit(2);
}
if (realpath($site) === realpath(__DIR__ . '/../../public')) {
    fwrite(STDERR, "Refusing to run against the real site folder.\n");
    exit(2);
}
define('BM_APP', true);
define('ADM_WEB_DIR', $site . '/admin');
require $site . '/app/admin/bootstrap.php';
if (realpath(BM_PUBLIC) !== realpath($site)) {
    fwrite(STDERR, 'Sandbox resolves to ' . BM_PUBLIC . " — copy the files instead of symlinking them.\n");
    exit(2);
}

$pass = 0;
$fail = 0;
function check(bool $cond, string $name, string $info = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ok   $name\n";
    } else {
        $fail++;
        echo "  FAIL $name" . ($info !== '' ? " — $info" : '') . "\n";
    }
}
function expect_error(callable $fn, string $name, string $needle = ''): void
{
    try {
        $fn();
        check(false, $name, 'no error thrown');
    } catch (AdmUserError $e) {
        check($needle === '' || strpos($e->getMessage(), $needle) !== false, $name, $e->getMessage());
    } catch (Throwable $e) {
        check(false, $name, get_class($e) . ': ' . $e->getMessage());
    }
}

$tmp = dirname($site) . '/unit-tmp-' . bin2hex(random_bytes(4)); // next to the sandbox, never in the repo
mkdir($tmp);

/* ---------------------------------------------------------------- */
echo "Images\n";

/** JPEG with an EXIF APP1 segment: Orientation + a GPS IFD (latitude). */
function jpeg_with_exif(string $path, int $w, int $h, int $orientation): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 30, 120, 200));
    imagefilledrectangle($im, 0, 0, (int) ($w / 8), (int) ($h / 8), imagecolorallocate($im, 255, 0, 0)); // red top-left marker
    ob_start();
    imagejpeg($im, null, 90);
    $jpeg = (string) ob_get_clean();
    $le16 = function (int $v): string { return pack('v', $v); };
    $le32 = function (int $v): string { return pack('V', $v); };
    $gpsOffset = 8 + 2 + 2 * 12 + 4;          // 38
    $ratOffset = $gpsOffset + 2 + 2 * 12 + 4;  // 68
    $tiff = "II*\0" . $le32(8)
        . $le16(2)
        . $le16(0x0112) . $le16(3) . $le32(1) . $le16($orientation) . $le16(0)
        . $le16(0x8825) . $le16(4) . $le32(1) . $le32($gpsOffset)
        . $le32(0)
        . $le16(2)
        . $le16(0x0001) . $le16(2) . $le32(2) . "N\0\0\0"
        . $le16(0x0002) . $le16(5) . $le32(3) . $le32($ratOffset)
        . $le32(0)
        . $le32(30) . $le32(1) . $le32(2) . $le32(1) . $le32(0) . $le32(1);
    $app1 = "Exif\0\0" . $tiff;
    $seg = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
    file_put_contents($path, substr($jpeg, 0, 2) . $seg . substr($jpeg, 2));
}

$src = $tmp . '/phone.jpg';
jpeg_with_exif($src, 2400, 1200, 6);
$exif = @exif_read_data($src);
check(is_array($exif) && (int) ($exif['Orientation'] ?? 0) === 6 && isset($exif['GPSLatitudeRef']), 'fixture has EXIF orientation 6 + GPS');

$rec = adm_image_process($src, ['alt' => 'صورة اختبار', 'caption' => "تعليق\x07"]);
$out = $site . '/' . $rec['src'];
$thumb = $site . '/' . $rec['thumb'];
check(preg_match('#^uploads/images/[a-f0-9]{16}\.jpg$#', $rec['src']) === 1, 'random hex file name', $rec['src']);
check(preg_match('#^uploads/images/[a-f0-9]{16}-640\.jpg$#', $rec['thumb']) === 1, 'thumbnail name ends with -640', $rec['thumb']);
[$ow, $oh] = getimagesize($out);
check($ow === 1200 && $oh === 2400 && $rec['w'] === 1200 && $rec['h'] === 2400, 'rotated to portrait (2400×1200 → 1200×2400)', "{$ow}x{$oh}");
[$tw, $th] = getimagesize($thumb);
check($tw === 640 && $th === 1280, 'thumbnail 640 wide', "{$tw}x{$th}");
$raw = (string) file_get_contents($out);
check(strpos($raw, 'Exif') === false, 'EXIF block removed from output');
$e2 = @exif_read_data($out);
check(!is_array($e2) || (!isset($e2['GPSLatitudeRef']) && !isset($e2['Orientation'])), 'no GPS / orientation tags after re-encode');
$g = imagecreatefromjpeg($out);
$c = imagecolorsforindex($g, imagecolorat($g, $ow - 10, 10));
check($c['red'] > 200 && $c['green'] < 60, 'red marker moved to top-right (90° clockwise)', json_encode($c));
check($rec['alt'] === 'صورة اختبار' && $rec['caption'] === 'تعليق', 'alt kept, control chars stripped from caption');

// Orientation 3 (180°) on a small image: no thumbnail when narrower than 640.
$src3 = $tmp . '/small.jpg';
jpeg_with_exif($src3, 500, 300, 3);
$rec3 = adm_image_process($src3, ['alt' => 'x']);
[$w3, $h3] = getimagesize($site . '/' . $rec3['src']);
$g3 = imagecreatefromjpeg($site . '/' . $rec3['src']);
$c3 = imagecolorsforindex($g3, imagecolorat($g3, $w3 - 5, $h3 - 5));
check($w3 === 500 && $h3 === 300 && $rec3['thumb'] === $rec3['src'], 'small image: no upscaling, thumb = src');
check($c3['red'] > 200 && $c3['green'] < 60, 'orientation 3 rotates 180°');

// Wide landscape → 1600 wide.
$src4 = $tmp . '/wide.jpg';
jpeg_with_exif($src4, 3200, 1800, 1);
$rec4 = adm_image_process($src4, ['alt' => 'x']);
check($rec4['w'] === 1600 && $rec4['h'] === 900, 'landscape resized to 1600 wide', $rec4['w'] . 'x' . $rec4['h']);

// PNG with transparency → JPEG on white.
$png = $tmp . '/alpha.png';
$im = imagecreatetruecolor(300, 200);
imagesavealpha($im, true);
imagealphablending($im, false);
imagefilledrectangle($im, 0, 0, 300, 200, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagepng($im, $png);
$rec5 = adm_image_process($png, ['alt' => 'x']);
$g5 = imagecreatefromjpeg($site . '/' . $rec5['src']);
$c5 = imagecolorsforindex($g5, imagecolorat($g5, 10, 10));
check(substr($rec5['src'], -4) === '.jpg' && $c5['red'] > 245 && $c5['green'] > 245 && $c5['blue'] > 245, 'transparent PNG flattened on white JPEG');

// keep_alpha (logo) → PNG with transparency.
$rec6 = adm_image_process($png, ['alt' => '', 'require_alt' => false, 'keep_alpha' => true, 'thumb_w' => 0, 'prefix' => 'logo']);
$g6 = imagecreatefrompng($site . '/' . $rec6['src']);
$c6 = imagecolorsforindex($g6, imagecolorat($g6, 10, 10));
check(preg_match('#^uploads/images/logo-[a-f0-9]{16}\.png$#', $rec6['src']) === 1 && $c6['alpha'] === 127, 'logo keeps transparency as PNG', $rec6['src'] . ' alpha=' . $c6['alpha']);

// Rejections.
file_put_contents($tmp . '/evil.jpg', "<?php echo 'x'; ?>");
expect_error(function () use ($tmp) { adm_image_process($tmp . '/evil.jpg', ['alt' => 'x']); }, 'PHP disguised as .jpg rejected', 'نوع الملف');
file_put_contents($tmp . '/x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>');
expect_error(function () use ($tmp) { adm_image_process($tmp . '/x.svg', ['alt' => 'x']); }, 'SVG rejected', 'نوع الملف');
$gif = imagecreatetruecolor(10, 10);
imagegif($gif, $tmp . '/a.gif');
expect_error(function () use ($tmp) { adm_image_process($tmp . '/a.gif', ['alt' => 'x']); }, 'GIF rejected', 'نوع الملف');
expect_error(function () use ($src) { adm_image_process($src, ['alt' => '  ']); }, 'missing alt text rejected', 'وصفًا');
// PNG header claiming 9000×9000 (81 MP): rejected before decoding.
$ihdr = pack('NNCCCCC', 9000, 9000, 8, 2, 0, 0, 0);
$chunk = function (string $type, string $data): string {
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
};
file_put_contents($tmp . '/huge.png', "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IDAT', gzcompress('')) . $chunk('IEND', ''));
expect_error(function () use ($tmp) { adm_image_process($tmp . '/huge.png', ['alt' => 'x']); }, 'oversized dimensions rejected', 'أبعاد');
// Corrupt JPEG body with a valid header.
file_put_contents($tmp . '/broken.jpg', substr((string) file_get_contents($src4), 0, 400));
expect_error(function () use ($tmp) { adm_image_process($tmp . '/broken.jpg', ['alt' => 'x']); }, 'truncated JPEG rejected');

/* ---------------------------------------------------------------- */
echo "Deleting uploads safely\n";
check(adm_delete_upload('uploads/images/../../app/content/texts.json.php') === false, 'traversal with ../ refused');
check(adm_delete_upload('/etc/passwd') === false, 'absolute path refused');
check(adm_delete_upload('uploads/images/index.html') === false, 'index.html guard kept');
check(adm_delete_upload('app/content/texts.json.php') === false, 'outside uploads refused');
check(is_file($site . '/app/content/texts.json.php'), 'texts.json still exists');
check(adm_delete_upload($rec4['src']) === true && !is_file($site . '/' . $rec4['src']), 'real upload deleted');
adm_delete_upload($rec4['thumb']);
adm_delete_image($rec);
check(!is_file($out) && !is_file($thumb), 'adm_delete_image removes src + thumb');

/* ---------------------------------------------------------------- */
echo "Backups & storage\n";
check(adm_backup_read('texts', '../../users') === null, 'backup id traversal refused');
check(adm_backup_read('../storage/users', '20260101-000000-abcdef') === null, 'backup name whitelist');
$f = adm_storage('unit-test-store.php');
adm_store_php_write($f, ['a' => "it's \"quoted\" \\ <?php", 'n' => 1]);
$back = adm_store_php_read($f);
check(($back['a'] ?? '') === "it's \"quoted\" \\ <?php" && ($back['n'] ?? 0) === 1, 'PHP-array storage round trip');
$printed = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f));
check($printed === '' || $printed === null, 'storage file prints nothing when executed directly');
adm_store_json_write(adm_storage('unit-test.php'), ['x' => 'y']);
check(strpos((string) file_get_contents(adm_storage('unit-test.php')), '<?php exit; ?>') === 0, 'guarded JSON starts with exit guard');
check(adm_store_json_read(adm_storage('unit-test.php')) === ['x' => 'y'], 'guarded JSON round trip');
@unlink($f);
@unlink(adm_storage('unit-test.php'));
check(strpos(adm_msg_unwritable($site . '/app/content'), '«app/content»') !== false, 'unwritable message names the folder relative to the site');

/* ---------------------------------------------------------------- */
echo "Input cleaning\n";
check(adm_clean("  سطر\x00 أول\r\nثاني\t\x1b ") === 'سطر أول ثاني', 'single-line clean', adm_clean("  سطر\x00 أول\r\nثاني\t\x1b "));
check(adm_clean("أ\r\n\r\n\r\n\r\nب", true) === "أ\n\nب", 'multiline clean keeps paragraphs');
check(adm_clean_url('javascript:alert(1)') === '', 'javascript: URL rejected');
check(adm_clean_url('https://www.facebook.com/بيوت-مصر') !== '' && strpos(adm_clean_url('https://www.facebook.com/بيوت-مصر'), '%') !== false, 'Arabic URL path percent-encoded');
check(adm_clean_url('http://') === '' && adm_clean_url('ftp://x.org') === '', 'incomplete / non-http URLs rejected');
check(adm_clean_email('info@beyootmisr.org') === 'info@beyootmisr.org' && adm_clean_email('x@') === '', 'email validation');
check(adm_clean_phone('٠١٢١١١١٤٦٦٢') === '01211114662', 'Arabic-Indic digits normalised in phones');
check(adm_clean_phone('<script>') === '', 'phone rejects letters');
check(adm_clean_date('2026-02-30') === '' && adm_clean_date('2026-10', true) === '2026-10', 'date validation');
$in = new AdmInput(['n' => '١٢٬٥٠٠', 's' => 'x', 'e' => 'bad']);
check($in->number('n', 'n') === 12500, 'number with Arabic digits and separator');
$in->enum('e', 'e', ['a', 'b'], 'a');
check(!$in->ok(), 'enum outside whitelist is an error');
check(adm_password_problem('short') !== '' && adm_password_problem('1234567890') !== '' && adm_password_problem('بيوت مصر حكاية 2026') === '', 'password policy');

/* ---------------------------------------------------------------- */
foreach ([$rec3, $rec5, $rec6] as $leftover) {
    adm_delete_image($leftover);
}
foreach (glob($tmp . '/*') ?: [] as $x) {
    @unlink($x);
}
@rmdir($tmp);
echo "\nunit: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
