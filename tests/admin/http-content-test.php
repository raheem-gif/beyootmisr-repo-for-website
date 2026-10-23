<?php
/**
 * End-to-end HTTP tests for the stage-2 content sections: «أثرنا بالأرقام», «المشروعات»
 * (with stages), «الحكايات», «التقارير», «معرض صور الأنشطة».
 * Real requests with cookie jars against a disposable sandbox copy of public/ (see run.sh).
 *
 * Usage: php tests/admin/http-content-test.php <base-url> <sandbox-site-dir>
 * Creates its own accounts (content-admin / content-editor) in the sandbox.
 */
declare(strict_types=1);
date_default_timezone_set('Africa/Cairo'); // same calendar day as the site (app/bootstrap.php)

$BASE = rtrim($argv[1] ?? '', '/');
$SITE = rtrim($argv[2] ?? '', '/');
if ($BASE === '' || !is_dir($SITE . '/app/content')) {
    fwrite(STDERR, "usage: php http-content-test.php <base-url> <sandbox-site-dir>\n");
    exit(2);
}
if (realpath($SITE) === realpath(__DIR__ . '/../../public')) {
    fwrite(STDERR, "Refusing to run against the real site folder.\n");
    exit(2);
}
$WORK = dirname($SITE) . '/http-content-tmp';
require __DIR__ . '/http-lib.php';

$A = '/admin/index.php';
$content = $SITE . '/app/content';
$storage = $SITE . '/app/storage';
$uploads = $SITE . '/uploads';
$ADMIN = ['content-admin', 'مدير المحتوى', 'admin', 'admin content pass 2026'];
$EDITOR = ['content-editor', 'محررة المحتوى', 'editor', 'editor content pass 2026'];

/* ---------------------------------------------------------------- */
/* Helpers                                                           */
/* ---------------------------------------------------------------- */
function mk_user(string $site, array $u): string
{
    $code = 'define("BM_APP", true); define("ADM_WEB_DIR", $argv[1] . "/admin"); require $argv[1] . "/app/admin/bootstrap.php";'
        . ' if (adm_user_get($argv[2]) === null) { adm_user_create($argv[2], $argv[3], $argv[4], $argv[5]); } echo "ok";';
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);
    foreach (array_merge([$site], $u) as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    return trim((string) shell_exec($cmd));
}
function login(string $base, string $user, string $pw): Browser
{
    $b = new Browser($base);
    $b->get('/admin/index.php?p=login');
    $r = $b->post('/admin/index.php?p=login', ['username' => $user, 'password' => $pw]);
    check($r['status'] === 303, "login as $user", (string) $r['status']);
    $b->get('/admin/index.php'); // fresh CSRF token for the new session
    return $b;
}
function rev_of(string $html): string
{
    return preg_match('/name="rev" value="([a-f0-9]*)"/', $html, $m) ? $m[1] : '';
}
function img_key(string $src): string
{
    return substr(sha1($src), 0, 12);
}
function find_by(array $list, string $key, $value): ?array
{
    foreach ($list as $r) {
        if (is_array($r) && ($r[$key] ?? null) === $value) {
            return $r;
        }
    }
    return null;
}
function upload_files(string $dir): array
{
    $out = [];
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (basename($f) !== 'index.html') {
            $out[] = basename($f);
        }
    }
    sort($out);
    return $out;
}
/** Form fields for the image list of a record: imgs[n][k|alt|caption]. */
function img_rows(array $images): array
{
    $rows = [];
    foreach (array_values($images) as $n => $img) {
        $rows[$n] = ['k' => img_key($img['src']), 'alt' => (string) $img['alt'], 'caption' => (string) ($img['caption'] ?? '')];
    }
    return $rows;
}
function project_form(array $rec, string $rev, array $over = []): array
{
    $f = ['action' => 'save', 'rev' => $rev];
    foreach (['title', 'activity', 'location', 'goal', 'status', 'summary', 'description', 'updated_at', 'cost', 'donations'] as $k) {
        $f[$k] = (string) ($rec[$k] ?? '');
    }
    $f['slug'] = (string) ($rec['id'] ?? '');
    if (!empty($rec['show_financials'])) {
        $f['show_financials'] = '1';
    }
    if (!empty($rec['published'])) {
        $f['published'] = '1';
    }
    $f['imgs'] = img_rows($rec['images'] ?? []);
    foreach ($over as $k => $v) {
        if ($v === null) {
            unset($f[$k]);
        } else {
            $f[$k] = $v;
        }
    }
    return $f;
}
function story_form(array $rec, string $rev, array $over = []): array
{
    $f = ['action' => 'save', 'rev' => $rev];
    foreach (['type', 'title', 'summary', 'body', 'activity', 'date'] as $k) {
        $f[$k] = (string) ($rec[$k] ?? '');
    }
    $f['slug'] = (string) ($rec['id'] ?? '');
    if (!empty($rec['published'])) {
        $f['published'] = '1';
    }
    $f['imgs'] = img_rows($rec['images'] ?? []);
    foreach ($over as $k => $v) {
        if ($v === null) {
            unset($f[$k]);
        } else {
            $f[$k] = $v;
        }
    }
    return $f;
}
function stats_form(array $stats, string $rev, array $over = []): array
{
    $f = ['rev' => $rev, 'updated_at' => (string) $stats['updated_at'], 'period' => (string) $stats['period'], 'items' => []];
    foreach ($stats['items'] as $i => $it) {
        $row = ['id' => $it['id'], 'label' => $it['label'], 'value' => $it['value'] === null ? '' : (string) $it['value'], 'suffix' => $it['suffix'], 'icon' => $it['icon'], 'activity' => $it['activity']];
        if (!empty($it['home'])) {
            $row['home'] = '1';
        }
        if (!empty($it['achievements'])) {
            $row['achievements'] = '1';
        }
        $f['items'][$i] = $row;
    }
    return array_replace($f, $over);
}

/** JPEG with an EXIF APP1 segment holding a GPS IFD (latitude "N 30°") and Orientation 1. */
function jpeg_with_gps(string $path, int $w, int $h, array $rgb = [30, 120, 200]): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]));
    ob_start();
    imagejpeg($im, null, 90);
    $jpeg = (string) ob_get_clean();
    $le16 = function (int $v): string {
        return pack('v', $v);
    };
    $le32 = function (int $v): string {
        return pack('V', $v);
    };
    $gpsOffset = 38;
    $ratOffset = 68;
    $tiff = "II*\0" . $le32(8)
        . $le16(2)
        . $le16(0x0112) . $le16(3) . $le32(1) . $le16(1) . $le16(0)
        . $le16(0x8825) . $le16(4) . $le32(1) . $le32($gpsOffset)
        . $le32(0)
        . $le16(2)
        . $le16(0x0001) . $le16(2) . $le32(2) . "N\0\0\0"
        . $le16(0x0002) . $le16(5) . $le32(3) . $le32($ratOffset)
        . $le32(0)
        . $le32(30) . $le32(1) . $le32(2) . $le32(1) . $le32(0) . $le32(1);
    $app1 = "Exif\0\0" . $tiff;
    file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2));
}

/* ---------------------------------------------------------------- */
/* Fixtures                                                          */
/* ---------------------------------------------------------------- */
$gpsJpg = $WORK . '/phone-gps.jpg';
jpeg_with_gps($gpsJpg, 1800, 1200);
$jpg2 = $WORK . '/two.jpg';
jpeg_with_gps($jpg2, 900, 600, [200, 120, 30]);
$jpg3 = $WORK . '/three.jpg';
jpeg_with_gps($jpg3, 700, 700, [40, 160, 90]);
$pdf = $WORK . '/report.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
$pdf2 = $WORK . '/report2.pdf';
file_put_contents($pdf2, "%PDF-1.5\n% second file\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
$fakePdf = $WORK . '/fake.pdf';
file_put_contents($fakePdf, "<html><script>alert(1)</script></html>");
$phpJpg = $WORK . '/shell.jpg';
file_put_contents($phpJpg, "<?php system('id'); ?>");
$svg = $WORK . '/x.svg';
file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="9" height="9"/></svg>');
$exif = @exif_read_data($gpsJpg);
check(is_array($exif) && isset($exif['GPSLatitudeRef']), 'fixture JPEG carries GPS EXIF');

check(mk_user($SITE, $ADMIN) === 'ok' && mk_user($SITE, $EDITOR) === 'ok', 'test accounts created in the sandbox');
$adm = login($BASE, $ADMIN[0], $ADMIN[3]);
$ed = login($BASE, $EDITOR[0], $EDITOR[3]);

/* ====================================================================== */
section('Permissions');
foreach (['stats', 'projects', 'stories', 'reports', 'galleries'] as $sec) {
    $r = $ed->get($A . '?p=' . $sec);
    check($r['status'] === 200 && strpos($r['body'], 'قيد الإعداد') === false, "editor opens $sec");
}
foreach (['settings', 'users', 'backup', 'audit'] as $sec) {
    $r = $ed->get($A . '?p=' . $sec);
    check($r['status'] === 403, "editor GET $sec → 403");
}
$settingsBefore = (string) file_get_contents($content . '/settings.json.php');
$r = $ed->post($A . '?p=settings', ['site_url' => 'https://evil.example', 'contact' => ['hotline' => '01000000000']]);
check($r['status'] === 403 && file_get_contents($content . '/settings.json.php') === $settingsBefore, 'editor POST settings → 403, unchanged');
$r = $ed->post($A . '?p=users', ['action' => 'create', 'username' => 'sneaky', 'name' => 'x', 'role' => 'admin', 'password' => 'aaaa bbbb cccc', 'password2' => 'aaaa bbbb cccc']);
check($r['status'] === 403, 'editor POST users → 403');
$anon = new Browser($BASE);
$r = $anon->get($A . '?p=projects&id=new');
check($r['status'] === 303 && strpos($r['location'], 'p=login') !== false, 'anonymous → login');
$r = $adm->request('POST', $A . '?p=projects&id=new', ['action' => 'save', 'title' => 'x', '_csrf' => 'nope']);
check($r['status'] === 400 && count(json_file($content . '/projects.json.php')) === 1, 'project POST without a valid CSRF token → 400');
$r = $adm->get($A . '?p=projects&id=..%2F..%2Fetc');
check($r['status'] === 303, 'malformed project id → back to the list');

/* ====================================================================== */
section('أثرنا بالأرقام');
$stats = json_file($content . '/stats.json.php');
$r = $adm->get($A . '?p=stats');
check($r['status'] === 200 && strpos($r['body'], 'لا تُنشر أرقام غير موثقة') !== false && substr_count($r['body'], 'data-row-key="') === count($stats['items']) + 1, 'stats editor lists every card (+ template row)');
check(strpos($r['body'], 'يظهر الرقم على الموقع') !== false, 'explains: value AND review date are needed');
$rev = rev_of($r['body']);
$f = stats_form($stats, $rev);
$f['items'][0]['value'] = '1250';
$r = $adm->post($A . '?p=stats', $f);
check($r['status'] === 303, 'value without review date saved', flash_text($r['body']));
$stats = json_file($content . '/stats.json.php');
check($stats['items'][0]['value'] === 1250 && $stats['updated_at'] === '', 'value stored as a number');
check(strpos((string) file_get_contents($SITE . '/index.html'), '1,250') === false, 'no review date → number NOT shown on the home page');

$r = $adm->get($A . '?p=stats');
$f = stats_form($stats, rev_of($r['body']), ['updated_at' => date('Y-m-d'), 'period' => 'منذ التأسيس حتى اليوم']);
$r = $adm->post($A . '?p=stats', $f);
check($r['status'] === 200 && strpos($r['body'], 'موثقة ومعتمدة') !== false && json_file($content . '/stats.json.php') === $stats, 'publishing numbers requires the «موثقة ومعتمدة» confirmation');
$f['verified'] = '1';
$r = $adm->post($A . '?p=stats', $f);
check($r['status'] === 303, 'numbers published with confirmation', flash_text($r['body']));
$home = (string) file_get_contents($SITE . '/index.html');
check(strpos($home, '1,250') !== false && strpos($home, 'منذ التأسيس حتى اليوم') !== false, 'value + review date → number and period shown on the home page');
$stats = json_file($content . '/stats.json.php');

$r = $adm->get($A . '?p=stats');
$rev = rev_of($r['body']);
$bad = stats_form($stats, $rev, ['updated_at' => date('Y-m-d', time() + 86400 * 10)]);
$bad['items'][1]['value'] = 'كثير';
$bad['items'][2]['icon'] = 'evil-icon';
$r = $adm->post($A . '?p=stats', $bad);
check($r['status'] === 200 && strpos($r['body'], 'في المستقبل') !== false && strpos($r['body'], 'اكتب رقمًا صحيحًا') !== false && strpos($r['body'], 'اختر قيمة من القائمة') !== false,
    'future date, non-numeric value and unknown icon rejected', flash_text($r['body']));
check(json_file($content . '/stats.json.php') === $stats, 'nothing saved on validation errors');
check(strpos($r['body'], 'value="كثير"') !== false, 'typed value shown again');

// Add a custom card, remove another, HTML is escaped.
$f = stats_form($stats, $rev, ['verified' => '1']);
$removedId = $f['items'][7]['id'];
unset($f['items'][7]);
$f['items'][57] = ['id' => '', 'label' => 'قطع كسوة <b>موزعة</b>', 'value' => '٣٠٠', 'suffix' => '+', 'icon' => 'shirt', 'activity' => 'kiswa', 'achievements' => '1'];
$r = $adm->post($A . '?p=stats', $f);
check($r['status'] === 303, 'custom card added', flash_text($r['body']));
$stats = json_file($content . '/stats.json.php');
$custom = end($stats['items']);
check(count($stats['items']) === 8 && find_by($stats['items'], 'id', $removedId) === null, 'card removed by leaving it out of the form');
check(preg_match('/^n-[a-f0-9]{6}$/', $custom['id']) === 1 && $custom['value'] === 300 && $custom['activity'] === 'kiswa' && $custom['home'] === false && $custom['achievements'] === true, 'new card: generated id, Arabic digits → 300, flags', json_encode($custom, JSON_UNESCAPED_UNICODE));
$kiswa = (string) file_get_contents($SITE . '/activities/kiswa.html');
check(strpos($kiswa, '300') !== false && strpos($kiswa, 'قطع كسوة &lt;b&gt;موزعة&lt;/b&gt;') !== false, 'activity page shows the card, label HTML-escaped');
$r = $adm->post($A . '?p=stats', stats_form($stats, 'stale-rev', ['period' => 'نسخة قديمة', 'verified' => '1']));
check(strpos($r['body'], 'عدّل مستخدم آخر') !== false && json_file($content . '/stats.json.php') === $stats, 'stale form → conflict warning, nothing saved');
$r = $ed->get($A . '?p=stats');
$f = stats_form($stats, rev_of($r['body']), ['period' => 'حتى أكتوبر 2026', 'verified' => '1']);
$r = $ed->post($A . '?p=stats', $f);
check($r['status'] === 303 && json_file($content . '/stats.json.php')['period'] === 'حتى أكتوبر 2026', 'editor can save the numbers');
check(count(glob($storage . '/backups/stats/*.json.php') ?: []) >= 3, 'stats.json backed up before each save');

/* ====================================================================== */
section('المشروعات — create, images, EXIF');
$r = $adm->get($A . '?p=projects');
check($r['status'] === 200 && strpos($r['body'], 'مسجد سيدنا عيسى ابن مريم') !== false && strpos($r['body'], 'بلا تاريخ تحديث') !== false, 'list shows the project with badges');
$r = $adm->get($A . '?p=projects&id=new');
check(strpos($r['body'], 'name="imgs_new[]"') !== false && strpos($r['body'], 'تذكير الخصوصية') !== false, 'new-project form: multi-upload with privacy reminder');
$imagesBefore = upload_files($uploads . '/images');
$new = ['title' => 'سقف بيت آمن (اختبار)', 'activity' => 'maa-wa-namaa', 'location' => 'المنيا', 'status' => 'ongoing', 'summary' => 'تركيب سقف جديد.', 'updated_at' => date('Y-m-d'), 'published' => true];
$f = project_form($new, '', ['slug' => '']);
$f['imgs_new'] = [new CURLFile($gpsJpg, 'image/jpeg', 'IMG_2026 بيت أم أحمد.jpg')];
$r = $adm->post($A . '?p=projects&id=new', $f);
check($r['status'] === 200 && strpos($r['body'], 'وصفًا قصيرًا للصورة') !== false && count(json_file($content . '/projects.json.php')) === 1, 'upload without a description refused, nothing saved');
check(upload_files($uploads . '/images') === $imagesBefore, 'no file stored when the form is refused');
check(strpos($r['body'], 'اختر الصور مرة أخرى') !== false, 'editor told to choose the photos again');
$f['imgs_alt'] = 'photo of roof';
$r = $adm->post($A . '?p=projects&id=new', $f);
check(strpos($r['body'], 'باللغة العربية') !== false && count(json_file($content . '/projects.json.php')) === 1, 'description must be in Arabic');
$f['imgs_alt'] = 'أعمال تركيب السقف';
$f['imgs_caption'] = 'المرحلة الأولى';
$r = $adm->post($A . '?p=projects&id=new', $f);
check($r['status'] === 303 && strpos($r['location'], 'p=projects&id=') !== false, 'project created → edit page', $r['status'] . ' ' . flash_text($r['body']));
$projects = json_file($content . '/projects.json.php');
$proj = find_by($projects, 'title', $new['title']);
check($proj !== null && preg_match('/^[a-z0-9-]{2,60}$/', $proj['id']) === 1 && $proj['created_at'] === date('Y-m-d'), 'record stored with an automatic slug and created_at', json_encode($proj, JSON_UNESCAPED_UNICODE));
$pid = $proj['id'];
$img = $proj['images'][0] ?? [];
$file = $SITE . '/' . ($img['src'] ?? 'missing');
check(preg_match('#^uploads/images/[a-f0-9]{16}\.jpg$#', $img['src'] ?? '') === 1 && is_file($file) && is_file($SITE . '/' . $img['thumb']), 'image stored under a random name with a thumbnail');
check(strpos((string) json_encode($proj, JSON_UNESCAPED_UNICODE), 'أم أحمد') === false, 'client file name never stored');
check($img['w'] === 1600 && $img['h'] === 1067 && $img['alt'] === 'أعمال تركيب السقف' && $img['caption'] === 'المرحلة الأولى', 'resized to 1600 px, w/h, alt and caption recorded', json_encode($img, JSON_UNESCAPED_UNICODE));
$raw = (string) file_get_contents($file);
$ex = @exif_read_data($file);
check(strpos($raw, 'Exif') === false && (!is_array($ex) || !isset($ex['GPSLatitudeRef'])), 'uploaded JPEG with GPS EXIF comes out without EXIF/GPS');
$page = (string) @file_get_contents($SITE . '/projects/' . $pid . '.html');
check($page !== '' && strpos($page, 'سقف بيت آمن (اختبار)') !== false && strpos($page, $img['thumb']) !== false, 'published project page appears in the build with its photo');
check(strpos((string) file_get_contents($SITE . '/achievements.html'), 'سقف بيت آمن (اختبار)') !== false, 'achievements page lists the project');
$audit = (string) file_get_contents($storage . '/audit.php');
check(strpos($audit, '"projects.create"') !== false, 'creation audited');

// More photos with per-file descriptions; bad files fail alone.
$r = $adm->get($A . '?p=projects&id=' . $pid);
check(strpos($r['body'], 'data-img-row') !== false && strpos($r['body'], 'value="' . img_key($img['src']) . '"') !== false, 'edit page lists the photo with its key');
$proj = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
$f = project_form($proj, rev_of($r['body']));
$f['imgs_new'] = [new CURLFile($jpg2, 'image/jpeg', 'a.jpg'), new CURLFile($phpJpg, 'image/jpeg', 'shell.php.jpg'), new CURLFile($jpg3, 'image/jpeg', 'b.jpg'), new CURLFile($svg, 'image/svg+xml', 'x.svg')];
$f['imgs_alt_each'] = [0 => 'صب الأعمدة', 2 => 'تجهيز الخامات'];
$f['imgs_caption_each'] = [2 => 'قبل البدء'];
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
check($r['status'] === 303, 'save with 2 good + 2 bad files', flash_text($r['body']));
$r = $adm->follow();
$ft = flash_text($r['body']);
check(strpos($ft, 'رُفعت صورتان') !== false && strpos($ft, 'تعذّر رفع بعض الصور') !== false && substr_count($ft, 'نوع الملف غير مسموح') === 2, 'good photos saved, bad ones reported', $ft);
$proj = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check(count($proj['images']) === 3 && $proj['images'][1]['alt'] === 'صب الأعمدة' && $proj['images'][2]['alt'] === 'تجهيز الخامات' && $proj['images'][2]['caption'] === 'قبل البدء', 'per-file descriptions and captions', json_encode(array_column($proj['images'], 'alt'), JSON_UNESCAPED_UNICODE));
check(!preg_grep('/\.(php|svg|phtml)/i', upload_files($uploads . '/images')), 'no PHP or SVG file ever stored');

// Reorder (no-JS buttons), edit a description, ignore unknown keys.
$k2 = img_key($proj['images'][2]['src']);
$f = project_form($proj, rev_of($r['body']), ['imgs_move' => $k2 . ':up']);
$f['imgs'][1]['alt'] = 'صب الأعمدة الخرسانية';
$f['imgs'][9] = ['k' => 'deadbeef0000', 'alt' => 'دخيل', 'del' => '1'];
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
$proj2 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check($r['status'] === 303 && array_column($proj2['images'], 'src') === [$proj['images'][0]['src'], $proj['images'][2]['src'], $proj['images'][1]['src']], '«تقديم» moves a photo up');
check($proj2['images'][2]['alt'] === 'صب الأعمدة الخرسانية' && count($proj2['images']) === 3, 'description edited; unknown image key ignored');
// The order of the posted rows is the order saved (what admin.js produces).
$r = $adm->get($A . '?p=projects&id=' . $pid);
$f = project_form($proj2, rev_of($r['body']));
$f['imgs'] = [2 => $f['imgs'][2], 0 => $f['imgs'][0], 1 => $f['imgs'][1]];
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
$proj3 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check(array_column($proj3['images'], 'src') === [$proj2['images'][2]['src'], $proj2['images'][0]['src'], $proj2['images'][1]['src']], 'posted row order is saved');
// Missing / non-Arabic description on an existing photo.
$r = $adm->get($A . '?p=projects&id=' . $pid);
$f = project_form($proj3, rev_of($r['body']));
$f['imgs'][0]['alt'] = '';
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
check($r['status'] === 200 && strpos($r['body'], 'الصورة 1: اكتب وصفًا') !== false && find_by(json_file($content . '/projects.json.php'), 'id', $pid) === $proj3, 'an existing photo cannot lose its description');
// Delete one photo → its files are removed.
$gone = $proj3['images'][1];
$f = project_form($proj3, rev_of($r['body']));
$f['imgs'][1]['del'] = '1';
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
$proj4 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check($r['status'] === 303 && count($proj4['images']) === 2 && !in_array($gone['src'], array_column($proj4['images'], 'src'), true), 'photo removed from the project');
check(!is_file($SITE . '/' . $gone['src']) && !is_file($SITE . '/' . $gone['thumb']), 'deleted photo: file and thumbnail removed from uploads');

/* ====================================================================== */
section('المشروعات — status, slug, financials, publishing');
$r = $adm->get($A . '?p=projects&id=' . $pid);
$rev = rev_of($r['body']);
check(strpos($r['body'], 'تم إثبات الإنجاز الفعلي للمشروع') !== false, 'done-confirmation checkbox shown');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['status' => 'done']));
check($r['status'] === 200 && strpos($r['body'], 'لا يوصف المشروع بأنه «منجز»') !== false, 'done status without confirmation rejected', flash_text($r['body']));
check(find_by(json_file($content . '/projects.json.php'), 'id', $pid)['status'] === 'ongoing', 'status unchanged (still ongoing)');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['status' => 'evil']));
check(strpos($r['body'], 'اختر قيمة من القائمة') !== false, 'unknown status rejected');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['slug' => 'مسجد']));
check(strpos($r['body'], 'حروفًا إنجليزية صغيرة') !== false, 'Arabic slug rejected');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['slug' => 'masjid-sayedna-isa-el-tor']));
check(strpos($r['body'], 'مستخدم بالفعل') !== false, 'duplicate slug rejected');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['show_financials' => '1']));
check(strpos($r['body'], 'اكتب التكلفة أو التبرعات') !== false, 'show financials needs a value');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['updated_at' => '2999-01-01', 'activity' => 'nope']));
check(strpos($r['body'], 'في المستقبل') !== false && strpos($r['body'], '«النشاط»') !== false, 'future date and unknown activity rejected');
check(find_by(json_file($content . '/projects.json.php'), 'id', $pid) === $proj4, 'nothing saved by the rejected attempts');

$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj4, $rev, ['status' => 'done', 'done_confirmed' => '1', 'slug' => 'Roof Test', 'cost' => '٢٥٠٠٠٠', 'show_financials' => '1']));
check($r['status'] === 303 && strpos($r['location'], 'id=roof-test') !== false, 'done with confirmation + slug «Roof Test» → roof-test', $r['location'] . ' ' . flash_text($r['body']));
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', 'roof-test');
check($proj5 !== null && $proj5['status'] === 'done' && $proj5['cost'] === '250000' && $proj5['show_financials'] === true, 'status done, cost normalised');
check(is_file($SITE . '/projects/roof-test.html') && !is_file($SITE . '/projects/' . $pid . '.html'), 'page moved to the new slug, old page removed');
$page = (string) file_get_contents($SITE . '/projects/roof-test.html');
check(strpos($page, '250,000') !== false, 'approved cost shown on the project page');
check(strpos((string) file_get_contents($storage . '/audit.php'), 'أُكّد إثبات الإنجاز الفعلي') !== false, 'audit notes the completion confirmation');
$pid = 'roof-test';
$r = $adm->get($A . '?p=projects&id=' . $pid);
check(strpos($r['body'], 'معتمدة بعد إثبات الإنجاز') !== false, 'already done: no new confirmation needed');
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, rev_of($r['body']), ['summary' => 'تركيب سقف جديد آمن.', 'updated_at' => '2025-12-01']));
check($r['status'] === 303 && find_by(json_file($content . '/projects.json.php'), 'id', $pid)['summary'] === 'تركيب سقف جديد آمن.', 'editing a done project needs no new confirmation');
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);

// Unpublish → the page disappears from the build; republish → it comes back.
$r = $adm->get($A . '?p=projects&id=' . $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, rev_of($r['body']), ['published' => null]));
check($r['status'] === 303 && !is_file($SITE . '/projects/' . $pid . '.html'), 'unpublished project page disappears from the build');
check(strpos((string) file_get_contents($SITE . '/achievements.html'), 'سقف بيت آمن') === false, 'unpublished project no longer listed');
$r = $adm->follow();
check(strpos($r['body'], 'غير منشور') !== false, 'flash says the project is a draft');
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, rev_of($r['body']), ['published' => '1']));
check(is_file($SITE . '/projects/' . $pid . '.html'), 'republished → page back');
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);

// Stale form, HTML escaping.
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, 'stalestalestale1', ['title' => 'عنوان من نسخة قديمة']));
check($r['status'] === 200 && strpos($r['body'], 'عدّل مستخدم آخر') !== false && strpos($r['body'], 'عنوان من نسخة قديمة') !== false, 'stale form → conflict, typed values kept');
check(find_by(json_file($content . '/projects.json.php'), 'id', $pid) === $proj5, 'conflict: nothing saved');
$r = $adm->get($A . '?p=projects&id=' . $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, rev_of($r['body']), ['title' => 'سقف <script>alert(1)</script>']));
$page = (string) file_get_contents($SITE . '/projects/' . $pid . '.html');
check(strpos($page, '<script>alert(1)</script>') === false && strpos($page, '&lt;script&gt;') !== false, 'HTML in a project title is escaped on the public page');
$r = $adm->get($A . '?p=projects');
check(strpos($r['body'], '<script>alert(1)</script>') === false, 'and in the admin list');
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
$r = $adm->get($A . '?p=projects&id=' . $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, project_form($proj5, rev_of($r['body']), ['title' => 'سقف بيت آمن (اختبار)']));
$proj5 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);

/* ====================================================================== */
section('المشروعات — stages');
$r = $adm->get($A . '?p=projects&id=' . $pid . '&stage=new');
check($r['status'] === 200 && strpos($r['body'], 'name="action" value="stage_save"') !== false, 'new stage form');
$rev = rev_of($r['body']);
$r = $adm->post($A . '?p=projects&id=' . $pid . '&stage=new', ['action' => 'stage_save', 'rev' => $rev, 'date' => '2999-01-01', 'title' => 'مرحلة', 'text' => '']);
check(strpos($r['body'], 'في المستقبل') !== false, 'future stage date rejected');
$r = $adm->post($A . '?p=projects&id=' . $pid . '&stage=new', ['action' => 'stage_save', 'rev' => $rev, 'date' => '', 'title' => '', 'text' => '']);
check(strpos($r['body'], '«تاريخ المرحلة» مطلوب') !== false && strpos($r['body'], '«عنوان المرحلة» مطلوب') !== false, 'stage date and title required');
$stageDate = date('Y-m-d');
$r = $adm->post($A . '?p=projects&id=' . $pid . '&stage=new', [
    'action' => 'stage_save', 'rev' => $rev, 'date' => '2026-01-15', 'title' => 'تجهيز الموقع', 'text' => "إزالة السقف القديم.\n\nتجهيز الخامات.",
    'imgs_new' => [new CURLFile($jpg2, 'image/jpeg', 's1.jpg')], 'imgs_alt' => 'إزالة السقف القديم',
]);
check($r['status'] === 303 && strpos($r['location'], '#stages') !== false, 'stage added', flash_text($r['body']));
$proj6 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check(count($proj6['updates']) === 1 && $proj6['updates'][0]['title'] === 'تجهيز الموقع' && count($proj6['updates'][0]['images']) === 1, 'stage stored with its photo');
check($proj6['updated_at'] === '2025-12-01', '«آخر تحديث» unchanged when not asked', $proj6['updated_at']);
$r = $adm->get($A . '?p=projects&id=' . $pid . '&stage=new');
$r = $adm->post($A . '?p=projects&id=' . $pid . '&stage=new', ['action' => 'stage_save', 'rev' => rev_of($r['body']), 'date' => $stageDate, 'title' => 'تركيب السقف', 'text' => 'اكتمل التركيب.', 'bump_updated' => '1']);
$proj6 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check(count($proj6['updates']) === 2 && $proj6['updates'][0]['title'] === 'تركيب السقف' && $proj6['updated_at'] === $stageDate, 'stages kept newest first; «آخر تحديث» follows the newest stage');
$page = (string) file_get_contents($SITE . '/projects/' . $pid . '.html');
check(strpos($page, 'تجهيز الموقع') !== false && strpos($page, 'تركيب السقف') !== false && strpos($page, 'إزالة السقف القديم') !== false, 'public page shows the stage timeline and stage photo');
$stagePhoto = $proj6['updates'][1]['images'][0];
$r = $adm->get($A . '?p=projects&id=' . $pid . '&stage=1');
check(strpos($r['body'], 'تجهيز الموقع') !== false && strpos($r['body'], img_key($stagePhoto['src'])) !== false, 'stage edit form shows the stage and its photo');
$r = $adm->post($A . '?p=projects&id=' . $pid . '&stage=1', ['action' => 'stage_save', 'rev' => rev_of($r['body']), 'date' => '2026-01-15', 'title' => 'تجهيز الموقع والخامات', 'text' => 'نص',
    'imgs' => [0 => ['k' => img_key($stagePhoto['src']), 'alt' => 'إزالة السقف القديم بالكامل', 'caption' => '']]]);
$proj6 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check($proj6['updates'][1]['title'] === 'تجهيز الموقع والخامات' && $proj6['updates'][1]['images'][0]['alt'] === 'إزالة السقف القديم بالكامل', 'stage edited');
$r = $adm->get($A . '?p=projects&id=' . $pid . '&stage=7');
check($r['status'] === 303, 'unknown stage index → back to the project');
$r = $adm->get($A . '?p=projects&id=' . $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, ['action' => 'stage_delete', 'stage' => '1', 'rev' => rev_of($r['body'])]);
$proj7 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
check($r['status'] === 303 && count($proj7['updates']) === 1 && $proj7['updates'][0]['title'] === 'تركيب السقف', 'stage deleted');
check(!is_file($SITE . '/' . $stagePhoto['src']), 'deleted stage photo file removed');
$r = $adm->post($A . '?p=projects&id=' . $pid, ['action' => 'stage_delete', 'stage' => '0', 'rev' => 'stale']);
check(count(find_by(json_file($content . '/projects.json.php'), 'id', $pid)['updates']) === 1, 'stage delete with a stale form refused');

/* ====================================================================== */
section('المشروعات — failed build rolls back');
$tpl = $SITE . '/app/templates/pages/project.php';
$orig = (string) file_get_contents($tpl);
file_put_contents($tpl, "<?php\ndefined('BM_APP') || exit;\nforeach (bm_projects(true) as \$__x) { if (\$__x['title'] === 'BREAK-BUILD') { throw new RuntimeException('test failure'); } }\n?>" . $orig);
sleep(3); // OPcache revalidation in the test server
$before = (string) file_get_contents($content . '/projects.json.php');
$filesBefore = upload_files($uploads . '/images');
$r = $adm->get($A . '?p=projects&id=' . $pid);
$f = project_form($proj7, rev_of($r['body']), ['title' => 'BREAK-BUILD']);
$f['imgs_new'] = [new CURLFile($jpg3, 'image/jpeg', 'c.jpg')];
$f['imgs_alt'] = 'صورة لن تُحفظ';
$r = $adm->post($A . '?p=projects&id=' . $pid, $f);
check(strpos($r['body'], 'أُعيد المحتوى السابق') !== false, 'build failure reported', flash_text($r['body']));
check((string) file_get_contents($content . '/projects.json.php') === $before, 'projects.json restored');
check(upload_files($uploads . '/images') === $filesBefore, 'photo uploaded with the failed save was cleaned up');
check(strpos($r['body'], 'BREAK-BUILD') !== false, 'typed values kept after the failed build');
file_put_contents($tpl, $orig);
sleep(3);
$adm->get($A);
$adm->post($A, ['action' => 'rebuild']);

/* ====================================================================== */
section('الحكايات');
$r = $adm->get($A . '?p=stories&id=new');
$texts = json_file($content . '/texts.json.php');
check(strpos($r['body'], htmlspecialchars($texts['stories']['types']['roof']['guide'], ENT_QUOTES)) !== false && strpos($r['body'], 'data-guide="treatment"') !== false, "each type's editorial guide from texts.json is on the form");
$story = ['type' => 'roof', 'title' => 'بيت صار أكثر أمانًا', 'summary' => 'سقف جديد لأسرة.', 'body' => "كان السقف متهالكًا.\n\nاليوم صار البيت آمنًا.", 'activity' => 'maa-wa-namaa', 'date' => '2026-09-01', 'published' => true];
$f = story_form($story, '', ['slug' => 'safe-roof']);
$f['imgs_new'] = [new CURLFile($gpsJpg, 'image/jpeg', 'roof.jpg')];
$f['imgs_alt'] = 'السقف بعد التركيب';
$r = $adm->post($A . '?p=stories&id=new', $f);
check($r['status'] === 200 && strpos($r['body'], 'مراجعة الخصوصية') !== false && json_file($content . '/stories.json.php') === [], 'publishing a story without the privacy confirmation is refused');
$f['privacy_confirmed'] = '1';
$f['imgs_new'] = [new CURLFile($gpsJpg, 'image/jpeg', 'roof.jpg')];
$r = $adm->post($A . '?p=stories&id=new', $f);
check($r['status'] === 303, 'story published with the privacy confirmation', flash_text($r['body']));
$stories = json_file($content . '/stories.json.php');
$s1 = $stories[0] ?? [];
check(($s1['id'] ?? '') === 'safe-roof' && $s1['type'] === 'roof' && $s1['published'] === true && count($s1['images']) === 1, 'story stored', json_encode($s1, JSON_UNESCAPED_UNICODE));
$sfile = $SITE . '/' . $s1['images'][0]['src'];
check(strpos((string) file_get_contents($sfile), 'Exif') === false, 'story photo stripped of EXIF');
check(is_file($SITE . '/stories/safe-roof.html') && strpos((string) file_get_contents($SITE . '/stories.html'), 'بيت صار أكثر أمانًا') !== false, 'story page built and listed');
$r = $adm->get($A . '?p=stories&id=safe-roof');
$rev = rev_of($r['body']);
$r = $adm->post($A . '?p=stories&id=safe-roof', story_form($s1, $rev, ['summary' => 'سقف جديد لأسرة في المنيا.']));
check($r['status'] === 200 && strpos($r['body'], 'مراجعة الخصوصية') !== false, 'changing a published story needs the confirmation again');
$r = $adm->post($A . '?p=stories&id=safe-roof', story_form($s1, $rev, ['type' => 'evil']));
check(strpos($r['body'], 'اختر قيمة من القائمة') !== false, 'unknown story type rejected');
$r = $adm->post($A . '?p=stories&id=safe-roof', story_form($s1, $rev, ['summary' => '']));
check(strpos($r['body'], '«الملخص» مطلوب') !== false, 'summary required');
$r = $adm->post($A . '?p=stories&id=safe-roof', story_form($s1, $rev, ['published' => null]));
check($r['status'] === 303 && !is_file($SITE . '/stories/safe-roof.html'), 'unpublishing needs no confirmation; page removed');
$s1 = json_file($content . '/stories.json.php')[0];
$r = $ed->get($A . '?p=stories&id=safe-roof');
$r = $ed->post($A . '?p=stories&id=safe-roof', story_form($s1, rev_of($r['body']), ['published' => '1', 'privacy_confirmed' => '1', 'type' => 'success']));
check($r['status'] === 303 && is_file($SITE . '/stories/safe-roof.html') && json_file($content . '/stories.json.php')[0]['type'] === 'success', 'editor republishes and changes the type');
$s1 = json_file($content . '/stories.json.php')[0];
$r = $adm->get($A . '?p=stories');
check(strpos($r['body'], 'بيت صار أكثر أمانًا') !== false && strpos($r['body'], 'منشورة') !== false, 'stories list');
$r = $adm->get($A . '?p=stories&id=safe-roof');
$r = $adm->post($A . '?p=stories&id=safe-roof', ['action' => 'delete', 'rev' => rev_of($r['body'])]);
check($r['status'] === 303 && json_file($content . '/stories.json.php') === [] && !is_file($sfile) && !is_file($SITE . '/stories/safe-roof.html'), 'story deleted with its photo and page');

/* ====================================================================== */
section('التقارير');
$reportsBefore = upload_files($uploads . '/reports');
$r = $adm->get($A . '?p=reports&id=new');
check(strpos($r['body'], 'accept="application/pdf,.pdf"') !== false && strpos($r['body'], 'تذكير الخصوصية') !== false, 'PDF upload field with privacy reminder');
$base = ['action' => 'save', 'rev' => '', 'title' => 'تقرير النشاط السنوي 2025', 'year' => '٢٠٢٥', 'description' => 'ملخص أعمال العام.', 'published' => '1'];
$r = $adm->post($A . '?p=reports&id=new', $base);
check(strpos($r['body'], 'اختر ملف التقرير') !== false, 'a new report needs a PDF');
$r = $adm->post($A . '?p=reports&id=new', $base + ['pdf' => new CURLFile($fakePdf, 'application/pdf', 'report.pdf'), 'review_confirmed' => '1']);
check(strpos($r['body'], 'ليس بصيغة PDF') !== false && json_file($content . '/reports.json.php') === [] && upload_files($uploads . '/reports') === $reportsBefore, 'fake PDF rejected, nothing stored');
$r = $adm->post($A . '?p=reports&id=new', $base + ['pdf' => new CURLFile($pdf, 'application/pdf', 'تقرير.pdf')]);
check(strpos($r['body'], 'راجعت التقرير') !== false && upload_files($uploads . '/reports') === $reportsBefore, 'publishing needs the review confirmation; file not kept');
$r = $adm->post($A . '?p=reports&id=new', array_replace($base, ['year' => '3000']) + ['pdf' => new CURLFile($pdf, 'application/pdf', 'r.pdf'), 'review_confirmed' => '1']);
check(strpos($r['body'], 'السنة') !== false && json_file($content . '/reports.json.php') === [], 'year validated');
$r = $adm->post($A . '?p=reports&id=new', $base + ['pdf' => new CURLFile($pdf, 'application/pdf', 'تقرير.pdf'), 'review_confirmed' => '1']);
check($r['status'] === 303, 'report uploaded', flash_text($r['body']));
$reports = json_file($content . '/reports.json.php');
$rep = $reports[0] ?? [];
check(preg_match('/^[a-f0-9]{10}$/', $rep['id'] ?? '') === 1 && $rep['year'] === '2025' && preg_match('#^uploads/reports/[a-f0-9]{16}\.pdf$#', $rep['file']) === 1 && $rep['size'] === filesize($pdf), 'record: id, year (Arabic digits), random file name, size');
$ach = (string) file_get_contents($SITE . '/achievements.html');
check(strpos($ach, 'تقرير النشاط السنوي 2025') !== false && strpos($ach, $rep['file']) !== false, 'achievements page links the report');
$r = $adm->get($A . '?p=reports&id=' . $rep['id']);
$rev = rev_of($r['body']);
$r = $adm->post($A . '?p=reports&id=' . $rep['id'], array_replace($base, ['rev' => $rev, 'title' => 'التقرير السنوي 2025']));
check($r['status'] === 303 && json_file($content . '/reports.json.php')[0]['title'] === 'التقرير السنوي 2025', 'editing the title of a published report needs no new confirmation');
$r = $adm->get($A . '?p=reports&id=' . $rep['id']);
$rev = rev_of($r['body']);
$r = $adm->post($A . '?p=reports&id=' . $rep['id'], array_replace($base, ['rev' => $rev]) + ['pdf' => new CURLFile($pdf2, 'application/pdf', 'v2.pdf')]);
check(strpos($r['body'], 'راجعت التقرير') !== false, 'replacing the file of a published report needs the confirmation');
$r = $adm->post($A . '?p=reports&id=' . $rep['id'], array_replace($base, ['rev' => $rev]) + ['pdf' => new CURLFile($pdf2, 'application/pdf', 'v2.pdf'), 'review_confirmed' => '1']);
$rep2 = json_file($content . '/reports.json.php')[0];
check($r['status'] === 303 && $rep2['file'] !== $rep['file'] && is_file($SITE . '/' . $rep2['file']) && !is_file($SITE . '/' . $rep['file']), 'file replaced, old PDF deleted');
$r = $ed->get($A . '?p=reports&id=' . $rep['id']);
$r = $ed->post($A . '?p=reports&id=' . $rep['id'], ['action' => 'delete', 'rev' => rev_of($r['body'])]);
check($r['status'] === 303 && json_file($content . '/reports.json.php') === [] && !is_file($SITE . '/' . $rep2['file']), 'editor deletes the report; PDF removed');
check(strpos((string) file_get_contents($SITE . '/achievements.html'), 'التقرير السنوي 2025') === false, 'report gone from the achievements page');

/* ====================================================================== */
section('معرض صور الأنشطة');
$r = $adm->get($A . '?p=galleries');
check($r['status'] === 200 && substr_count($r['body'], 'class="adm-act"') === 10, 'index lists the ten activities');
$r = $adm->get($A . '?p=galleries&a=evil');
check($r['status'] === 303, 'unknown activity → back to the index');
$r = $ed->get($A . '?p=galleries&a=salama');
$r = $ed->post($A . '?p=galleries&a=salama', [
    'rev' => rev_of($r['body']),
    'imgs_new' => [new CURLFile($gpsJpg, 'image/jpeg', 'g1.jpg'), new CURLFile($jpg2, 'image/jpeg', 'g2.jpg'), new CURLFile($jpg3, 'image/jpeg', 'g3.jpg')],
    'imgs_alt_each' => ['تجهيز مستلزمات علاجية', 'فريق المتطوعين أثناء التجهيز', 'حقائب الأدوية'],
    'imgs_caption_each' => [1 => 'مارس 2026'],
]);
check($r['status'] === 303, 'editor uploads three photos to «سلامة»', flash_text($r['body']));
$gal = json_file($content . '/galleries.json.php');
check(count($gal['salama'] ?? []) === 3 && $gal['salama'][1]['caption'] === 'مارس 2026', 'galleries.json updated');
$act = (string) file_get_contents($SITE . '/activities/salama.html');
check(strpos($act, 'تجهيز مستلزمات علاجية') !== false && strpos($act, $gal['salama'][0]['thumb']) !== false, 'activity page shows the gallery');
check(strpos((string) file_get_contents($SITE . '/' . $gal['salama'][0]['src']), 'Exif') === false, 'gallery photo stripped of EXIF');
$r = $ed->get($A . '?p=galleries&a=salama');
$rows = img_rows($gal['salama']);
$r = $ed->post($A . '?p=galleries&a=salama', ['rev' => rev_of($r['body']), 'imgs' => $rows, 'imgs_move' => $rows[0]['k'] . ':down']);
$gal2 = json_file($content . '/galleries.json.php');
check(array_column($gal2['salama'], 'src') === [$gal['salama'][1]['src'], $gal['salama'][0]['src'], $gal['salama'][2]['src']], '«تأخير» moves a photo down');
$r = $ed->get($A . '?p=galleries&a=salama');
$rows = img_rows($gal2['salama']);
$rows[2]['del'] = '1';
$r = $ed->post($A . '?p=galleries&a=salama', ['rev' => rev_of($r['body']), 'imgs' => $rows]);
$gal3 = json_file($content . '/galleries.json.php');
check(count($gal3['salama']) === 2 && !is_file($SITE . '/' . $gal2['salama'][2]['src']), 'photo deleted with its file');
$r = $ed->post($A . '?p=galleries&a=salama', ['rev' => 'stale', 'imgs' => img_rows($gal3['salama'])]);
check(strpos($r['body'], 'عدّل مستخدم آخر') !== false, 'stale gallery form → conflict warning');
// Another project's photo key cannot be used to delete it from a gallery form.
$projPhoto = find_by(json_file($content . '/projects.json.php'), 'id', $pid)['images'][0];
$r = $ed->get($A . '?p=galleries&a=salama');
$rows = img_rows($gal3['salama']);
$rows[5] = ['k' => img_key($projPhoto['src']), 'alt' => 'x', 'del' => '1'];
$r = $ed->post($A . '?p=galleries&a=salama', ['rev' => rev_of($r['body']), 'imgs' => $rows]);
check(is_file($SITE . '/' . $projPhoto['src']), "a gallery form cannot delete another record's photo");
$r = $ed->get($A . '?p=galleries&a=salama');
$rows = img_rows($gal3['salama']);
$rows[0]['del'] = '1';
$rows[1]['del'] = '1';
$r = $ed->post($A . '?p=galleries&a=salama', ['rev' => rev_of($r['body']), 'imgs' => $rows]);
check(trim(strip_guard((string) file_get_contents($content . '/galleries.json.php'))) === '{}', 'last photo deleted → galleries.json is {} again');
check(strpos((string) file_get_contents($SITE . '/activities/salama.html'), 'تجهيز مستلزمات علاجية') === false, 'activity page back to its empty state');

/* ====================================================================== */
section('Delete a project with its files');
$proj8 = find_by(json_file($content . '/projects.json.php'), 'id', $pid);
$paths = [];
foreach ($proj8['images'] as $im) {
    $paths[] = $im['src'];
    $paths[] = $im['thumb'];
}
foreach ($proj8['updates'] as $u) {
    foreach ($u['images'] as $im) {
        $paths[] = $im['src'];
    }
}
$r = $adm->get($A . '?p=projects&id=' . $pid);
check(strpos($r['body'], 'حذف المشروع نهائيًا') !== false && strpos($r['body'], 'data-confirm=') !== false, 'delete button asks for confirmation');
$r = $adm->post($A . '?p=projects&id=' . $pid, ['action' => 'delete', 'rev' => 'stale']);
check(find_by(json_file($content . '/projects.json.php'), 'id', $pid) !== null, 'delete with a stale form refused');
$r = $adm->get($A . '?p=projects&id=' . $pid);
$r = $adm->post($A . '?p=projects&id=' . $pid, ['action' => 'delete', 'rev' => rev_of($r['body'])]);
check($r['status'] === 303 && find_by(json_file($content . '/projects.json.php'), 'id', $pid) === null, 'project deleted');
$left = array_filter($paths, function ($p) use ($SITE) {
    return is_file($SITE . '/' . $p);
});
check(!$left && !is_file($SITE . '/projects/' . $pid . '.html'), 'its photos, thumbnails and page are removed', implode(', ', $left));
check(is_file($SITE . '/projects/masjid-sayedna-isa-el-tor.html'), 'other projects untouched');
$audit = (string) file_get_contents($storage . '/audit.php');
foreach (['stats.save', 'projects.save', 'projects.stage', 'projects.stage_delete', 'projects.delete', 'stories.create', 'stories.delete', 'reports.create', 'reports.delete', 'galleries.upload', 'galleries.delete'] as $act) {
    check(strpos($audit, '"' . $act . '"') !== false, "audit has $act");
}
$r = $adm->get($A . '?p=audit&s=projects');
$r2 = $adm->get($A . '?p=audit&s=galleries');
check(strpos($r['body'], 'تحديث مرحلة مشروع') !== false && strpos($r2['body'], 'رفع صور للمعرض') !== false, 'audit page shows Arabic labels for the new actions');

/* ====================================================================== */
section('Hand-written records (legacy id, string image)');
$plist = json_file($content . '/projects.json.php');
$plist[] = ['id' => 'Legacy_Project', 'title' => 'مشروع مكتوب يدويًا', 'activity' => 'najah', 'status' => 'ongoing', 'images' => ['uploads/images/legacy-photo.jpg'], 'published' => false];
file_put_contents($content . '/projects.json.php', json_encode($plist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
copy($jpg2, $uploads . '/images/legacy-photo.jpg');
$r = $adm->get($A . '?p=projects&id=legacy-project');
check($r['status'] === 200 && strpos($r['body'], img_key('uploads/images/legacy-photo.jpg')) !== false, 'record with a hand-written id and a string image opens');
$legacy = ['id' => 'legacy-project', 'title' => 'مشروع مكتوب يدويًا', 'activity' => 'najah', 'status' => 'ongoing', 'images' => [['src' => 'uploads/images/legacy-photo.jpg', 'alt' => 'صورة من الأرشيف', 'caption' => '']]];
$r = $adm->post($A . '?p=projects&id=legacy-project', project_form($legacy, rev_of($r['body']), ['summary' => 'ملخص مضاف']));
$saved = find_by(json_file($content . '/projects.json.php'), 'title', 'مشروع مكتوب يدويًا');
check($r['status'] === 303 && $saved !== null && $saved['id'] === 'legacy-project' && $saved['summary'] === 'ملخص مضاف', 'saved without a false conflict; id normalised', flash_text($r['body']));
check(($saved['images'][0]['src'] ?? '') === 'uploads/images/legacy-photo.jpg' && $saved['images'][0]['alt'] === 'صورة من الأرشيف' && $saved['images'][0]['thumb'] === 'uploads/images/legacy-photo.jpg', 'string image upgraded to a full record');

/* ====================================================================== */
section('CSP-safe markup and escaping on every new page');
// Seed one record of each kind so the edit pages render with data.
$r = $adm->get($A . '?p=projects&id=new');
$adm->post($A . '?p=projects&id=new', project_form(['title' => 'مشروع للعرض', 'activity' => 'najah', 'status' => 'ongoing', 'summary' => 'x'], '', ['slug' => 'show-me']));
$r = $adm->get($A . '?p=stories&id=new');
$adm->post($A . '?p=stories&id=new', story_form(['type' => 'success', 'title' => 'حكاية للعرض', 'summary' => 'x'], '', ['slug' => 'story-x']));
$pages = ['stats', 'projects', 'projects&id=new', 'projects&id=show-me', 'projects&id=show-me&stage=new', 'stories', 'stories&id=new', 'stories&id=story-x', 'reports', 'reports&id=new', 'galleries', 'galleries&a=najah', 'dashboard'];
foreach ($pages as $pg) {
    $r = $adm->get($A . '?p=' . $pg);
    $bad = preg_match('/\sstyle\s*=/i', $r['body']) || preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $r['body']) || preg_match('/\son[a-z]+\s*=\s*["\']/i', $r['body']);
    check($r['status'] === 200 && !$bad && strpos($r['body'], 'حدث خطأ غير متوقع') === false, "$pg: renders, no inline style/script/handlers", (string) $r['status']);
}
$logged = is_file($storage . '/errors.php') ? array_filter(explode("\n", (string) file_get_contents($storage . '/errors.php')), function ($l) {
    return $l !== '' && strpos($l, '<?php') !== 0 && strpos($l, 'test failure') === false;
}) : [];
check(!$logged, 'no unexpected PHP errors logged (only the injected build failure)', implode(' | ', $logged));

echo "\nhttp-content: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
