<?php
/**
 * End-to-end HTTP tests for the admin panel (real requests, cookie jars).
 * Usage: php tests/admin/http-test.php <base-url> <sandbox-site-dir>   (see run.sh)
 */
declare(strict_types=1);

$BASE = rtrim($argv[1] ?? '', '/');
$SITE = rtrim($argv[2] ?? '', '/');
if ($BASE === '' || !is_dir($SITE . '/app/content')) {
    fwrite(STDERR, "usage: php http-test.php <base-url> <sandbox-site-dir>\n");
    exit(2);
}
if (realpath($SITE) === realpath(__DIR__ . '/../../public')) {
    fwrite(STDERR, "Refusing to run against the real site folder.\n");
    exit(2);
}

// Scratch files (cookie jars, fixtures) live next to the sandbox site, never in the real repo.
$WORK = dirname($SITE) . '/http-tmp';
require __DIR__ . '/http-lib.php';


$A = '/admin/index.php';
$storage = $SITE . '/app/storage';
$content = $SITE . '/app/content';
$ADMIN_PW = 'نخل وشمس 2026 بيوت';
$EDITOR_PW = 'editor-pass-2026!';

/* ====================================================================== */
section('Headers & first run');
$b = new Browser($BASE);
$r = $b->get('/admin/');
check($r['status'] === 200, 'admin opens', (string) $r['status']);
$csp = $r['headers']['content-security-policy'][0] ?? '';
check($csp === "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'", 'exact CSP header', $csp);
check(($r['headers']['x-frame-options'][0] ?? '') === 'DENY', 'X-Frame-Options DENY');
check(($r['headers']['x-content-type-options'][0] ?? '') === 'nosniff', 'nosniff');
check(($r['headers']['referrer-policy'][0] ?? '') === 'same-origin', 'Referrer-Policy same-origin');
check(strpos($r['headers']['cache-control'][0] ?? '', 'no-store') !== false, 'Cache-Control no-store');
check(strpos($r['body'], '<meta name="robots" content="noindex,nofollow">') !== false, 'robots noindex meta');
$cookieHdr = implode("\n", $r['headers']['set-cookie'] ?? []);
check(stripos($cookieHdr, 'bm_admin=') !== false && stripos($cookieHdr, 'HttpOnly') !== false && stripos($cookieHdr, 'SameSite=Strict') !== false && stripos($cookieHdr, 'path=/admin/') !== false, 'session cookie: custom name, HttpOnly, SameSite=Strict, path=/admin/', $cookieHdr);
check(strpos($r['body'], 'setup.allow') !== false && strpos($r['body'], 'name="password"') === false, 'no accounts: instructions, no form without setup.allow');
check(!preg_match('/\sstyle="/', $r['body']) && !preg_match('/<script(?![^>]*\ssrc=)[^>]*>/', $r['body']), 'no inline styles or inline scripts (CSP-safe markup)');

$b->post($A . '?p=setup', ['username' => 'hacker', 'name' => 'x', 'password' => 'aaaaaaaaaaaa1', 'password2' => 'aaaaaaaaaaaa1']);
check(!is_file($storage . '/users.php'), 'setup POST refused while setup.allow is missing');

touch($storage . '/setup.allow');
$r = $b->get($A . '?p=setup');
check(strpos($r['body'], 'name="password2"') !== false, 'setup form appears once setup.allow exists');
$token = $b->csrf();
$r = $b->request('POST', $A . '?p=setup', ['username' => 'raheem', 'name' => 'رحيم', 'password' => $ADMIN_PW, 'password2' => $ADMIN_PW]);
check($r['status'] === 400 && !is_file($storage . '/users.php'), 'setup without CSRF token → 400', (string) $r['status']);
$b->get($A . '?p=setup');
$r = $b->post($A . '?p=setup', ['username' => 'raheem', 'name' => 'رحيم', 'password' => 'short', 'password2' => 'short']);
check($r['status'] === 200 && strpos($r['body'], 'لا تقل عن 10') !== false && !is_file($storage . '/users.php'), 'weak password rejected');
$r = $b->post($A . '?p=setup', ['username' => 'raheem', 'name' => 'رحيم', 'password' => $ADMIN_PW, 'password2' => $ADMIN_PW . 'x']);
check(strpos($r['body'], 'غير متطابقتين') !== false, 'password confirmation must match');
$before = $b->cookie();
$r = $b->post($A . '?p=setup', ['username' => 'raheem', 'name' => 'رحيم', 'password' => $ADMIN_PW, 'password2' => $ADMIN_PW]);
check($r['status'] === 303 && strpos($r['location'], 'index.php') === 0, 'first admin created → redirect', $r['status'] . ' ' . $r['location']);
check(is_file($storage . '/users.php') && !is_file($storage . '/setup.allow'), 'users.php written, setup.allow deleted');
check($b->cookie() !== $before && $b->cookie() !== '', 'session id regenerated at login');
$r = $b->follow();
check($r['status'] === 200 && strpos($r['body'], 'قائمة المراجعة قبل إطلاق الموقع') !== false, 'dashboard shows the pre-launch checklist');
touch($storage . '/setup.allow');
$b->get($A . '?p=setup');
check(!is_file($storage . '/setup.allow') && $b->last['status'] === 303, 'setup.allow is removed again once accounts exist; setup redirects');
check(strpos($r['body'], 'أُنشئ حساب المدير') !== false, 'success flash after setup');

/* ====================================================================== */
section('Private storage is never readable over HTTP');
foreach (['users.php', 'audit.php', 'secret.php', 'build-status.php'] as $f) {
    if (is_file($storage . '/' . $f)) {
        $x = (new Browser($BASE))->get('/app/storage/' . $f);
        check(trim($x['body']) === '', "/app/storage/$f prints nothing", substr($x['body'], 0, 80));
    }
}
$sessFiles = glob($storage . '/sessions/*.php') ?: [];
check(count($sessFiles) > 0, 'session files are .php files named by hash');
if ($sessFiles) {
    $x = (new Browser($BASE))->get('/app/storage/sessions/' . basename($sessFiles[0]));
    check(trim($x['body']) === '', 'session file prints nothing over HTTP');
    check(strpos(basename($sessFiles[0]), $b->cookie()) === false, 'session file name does not reveal the session id');
}
$users = (string) file_get_contents($storage . '/users.php');
check(strpos($users, "defined('BM_APP') || exit;") !== false && strpos($users, '$2y$') !== false && strpos($users, $ADMIN_PW) === false, 'users.php: guarded PHP array with bcrypt hash only');

/* ====================================================================== */
section('Routing & permissions basics');
$anon = new Browser($BASE);
$r = $anon->get($A . '?p=texts');
check($r['status'] === 303 && strpos($r['location'], 'p=login') !== false, 'anonymous → login');
$r = $anon->get($A . '?p=ping');
check($r['status'] === 401, 'ping without session → 401');
$r = $b->get($A . '?p=ping');
check($r['status'] === 204, 'ping with session → 204');
$r = $b->get($A . '?p=../../etc/passwd');
check($r['status'] === 200 && strpos($r['body'], 'قائمة المراجعة') !== false, 'malformed section name falls back to dashboard');
$r = $b->get($A . '?p=nope');
check($r['status'] === 404, 'unknown section → 404');
$r = $b->get($A . '?p=logout');
check($r['status'] === 303 && strpos($r['location'], 'p=login') === false, 'logout via GET does not log out');
$r = $b->get($A . '?p=stats');
check($r['status'] === 200 && strpos($r['body'], 'قيد الإعداد') === false && strpos($r['body'], 'حفظ الأرقام') !== false, 'stage-2 sections are live (stats editor renders)');

/* ====================================================================== */
section('CSRF & origin');
$r = $b->get($A);
$tok = $b->csrf();
$r = $b->request('POST', $A, ['action' => 'rebuild', '_csrf' => 'deadbeef']);
check($r['status'] === 400, 'wrong CSRF token → 400');
$r = $b->request('POST', $A, ['action' => 'rebuild', '_csrf' => $tok], ['Origin: https://evil.example']);
check($r['status'] === 400, 'cross-site Origin → 400');
$r = $b->request('POST', $A, ['action' => 'rebuild', '_csrf' => $tok], ['Origin: ' . $BASE]);
check($r['status'] === 303, 'same Origin + token → accepted');
$r = $b->follow();
check(strpos($r['body'], 'أُعيد بناء الموقع') !== false, 'rebuild flash with page count', flash_text($r['body']));
check(is_file($storage . '/build-status.php'), 'build status recorded');

/* ====================================================================== */
section('Texts editor');
$texts = json_file($content . '/texts.json.php');
$hash = function (array $g): string {
    return sha1((string) json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
$r = $b->get($A . '?p=texts');
check($r['status'] === 200 && substr_count($r['body'], 'data-group') >= 10, 'texts page lists groups', (string) substr_count($r['body'], 'data-group'));
check(strpos($r['body'], 'data-text-search') !== false, 'search box present');
check(strpos($r['body'], '<textarea') !== false && strpos($r['body'], 'data-lines') !== false, 'textareas and list fields rendered');
check(strpos($r['body'], 'name="t[_label]"') === false && strpos($r['body'], '[_label]') === false, '_label metadata is never an editable field');
check(strpos($r['body'], 'data-row-add') !== false && strpos($r['body'], 't[faq][__i__][q]') !== false, 'FAQ repeater with template row');

$newTitle = 'بيوت مصر.. حكاية من مليون حكاية (اختبار)';
$r = $b->post($A . '?p=texts&open=home', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => $newTitle]]]);
check($r['status'] === 303, 'save home group → redirect', (string) $r['status'] . flash_text($r['body']));
$r = $b->follow();
check(strpos($r['body'], 'حُفظت نصوص') !== false, 'success flash', flash_text($r['body']));
$after = json_file($content . '/texts.json.php');
check($after['home']['hero']['title'] === $newTitle, 'texts.json updated');
check($after['home']['hero']['_label'] === $texts['home']['hero']['_label'] && $after['_label'] === $texts['_label'], '_label metadata preserved');
check($after['home']['hero']['text'] === $texts['home']['hero']['text'], 'fields not posted are kept');
$copy = $after;
$copy['home']['hero']['title'] = $texts['home']['hero']['title'];
check($copy === $texts, 'nothing else in texts.json changed (structure preserved)');
check(strpos((string) file_get_contents($SITE . '/index.html'), 'حكاية من مليون حكاية (اختبار)') !== false, 'home page rebuilt with the new text');
check(count(glob($storage . '/backups/texts/*.json.php') ?: []) === 1, 'backup of previous texts.json written');
$audit = (string) file_get_contents($storage . '/audit.php');
check(strpos($audit, '"texts.save"') !== false && strpos($audit, 'home.hero.title') !== false, 'audit entry names the changed field');

$texts = $after;
$r = $b->get($A . '?p=texts');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => 'x', 'evil' => 'y']]]);
check($r['status'] === 200 && json_file($content . '/texts.json.php') === $texts, 'unknown key rejected, nothing saved');
check(strpos($r['body'], 'حقل غير معروف') !== false, 'unknown key error shown');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['_label' => 'hacked']]]);
check(json_file($content . '/texts.json.php') === $texts, '_label cannot be overwritten');
$r = $b->post($A . '?p=texts', ['group' => '_label', 'h' => '', 't' => []]);
check(json_file($content . '/texts.json.php') === $texts, 'meta group refused');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => '']]]);
check(strpos($r['body'], 'لا يمكن تركه فارغًا') !== false && json_file($content . '/texts.json.php') === $texts, 'required text cannot be emptied');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => 'اتصل {phone}']]]);
check(strpos($r['body'], 'غير معروف') !== false && json_file($content . '/texts.json.php') === $texts, 'unknown placeholder rejected');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => 'اتصل على {hotline}']]]);
$texts = json_file($content . '/texts.json.php');
check($texts['home']['hero']['title'] === 'اتصل على {hotline}', 'known placeholder accepted');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => 'stale', 't' => ['hero' => ['title' => 'نسخة قديمة']]]);
check($r['status'] === 200 && strpos($r['body'], 'عدّل مستخدم آخر') !== false && json_file($content . '/texts.json.php') === $texts, 'edit conflict detected, nothing saved');
check(strpos($r['body'], 'نسخة قديمة') !== false, 'editor values shown again after conflict');
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => "سطر\x00مخفي\x1b"]]]);
$texts = json_file($content . '/texts.json.php');
check($texts['home']['hero']['title'] === 'سطرمخفي', 'control characters stripped', $texts['home']['hero']['title']);
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['hero' => ['title' => '<script>alert(1)</script>']]]);
$texts = json_file($content . '/texts.json.php');
$home = (string) file_get_contents($SITE . '/index.html');
check(strpos($home, '<script>alert(1)</script>') === false && strpos($home, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'HTML in texts is escaped on the public page');
$r = $b->get($A . '?p=texts');
check(strpos($r['body'], '<script>alert(1)</script>') === false, 'HTML in texts is escaped in the admin');

// Lists (one item per line).
$facts = $texts['home']['intro']['facts'];
$r = $b->post($A . '?p=texts', ['group' => 'home', 'h' => $hash($texts['home']), 't' => ['intro' => ['facts' => implode("\r\n", $facts) . "\r\n\r\nمعلومة جديدة\r\n"]]]);
$texts = json_file($content . '/texts.json.php');
check($texts['home']['intro']['facts'] === array_merge($facts, ['معلومة جديدة']), 'list textarea: one item per line, blanks dropped');

// FAQ add / remove.
$faq = $texts['contact']['faq'];
$rows = [];
foreach ($faq as $i => $row) {
    $rows[$i] = $row;
}
$rows[1003] = ['q' => 'سؤال جديد؟', 'a' => 'إجابة جديدة.'];
$r = $b->post($A . '?p=texts', ['group' => 'contact', 'h' => $hash($texts['contact']), 't' => ['faq' => $rows]]);
$texts = json_file($content . '/texts.json.php');
check(count($texts['contact']['faq']) === count($faq) + 1 && end($texts['contact']['faq'])['q'] === 'سؤال جديد؟', 'FAQ row added');
unset($rows[0]);
$r = $b->post($A . '?p=texts', ['group' => 'contact', 'h' => $hash($texts['contact']), 't' => ['faq' => $rows]]);
$texts = json_file($content . '/texts.json.php');
check(count($texts['contact']['faq']) === count($faq) && $texts['contact']['faq'][0]['q'] === $faq[1]['q'], 'FAQ row removed');
$r = $b->post($A . '?p=texts', ['group' => 'contact', 'h' => $hash($texts['contact']), 't' => ['faq' => [0 => ['q' => 'سؤال بلا إجابة', 'a' => '']]]]);
check(strpos($r['body'], 'أكمل كل الحقول') !== false && json_file($content . '/texts.json.php') === $texts, 'incomplete FAQ row rejected');
$r = $b->post($A . '?p=texts', ['group' => 'contact', 'h' => $hash($texts['contact']), 't' => ['faq' => [0 => ['q' => 'س', 'a' => 'ج', 'x' => 'evil']]]]);
check(json_file($content . '/texts.json.php') === $texts, 'unknown FAQ field rejected');
// Fixed-size list of objects keeps its count.
$r = $b->post($A . '?p=texts', ['group' => 'charter', 'h' => $hash($texts['charter']), 't' => ['principles' => [99 => ['title' => 'x', 'text' => 'y']]]]);
check(json_file($content . '/texts.json.php') === $texts, 'charter principles: rows cannot be added');

// Build failure → previous content restored.
$tpl = $SITE . '/app/templates/pages/about.php';
$orig = (string) file_get_contents($tpl);
file_put_contents($tpl, "<?php\ndefined('BM_APP') || exit;\nif (t('about.hero.title') === 'BREAK-BUILD') { throw new RuntimeException('test failure'); }\n?>" . $orig);
sleep(3); // let OPcache in the test server notice the changed template (revalidate_freq = 2 s)
$r = $b->post($A . '?p=texts', ['group' => 'about', 'h' => $hash($texts['about']), 't' => ['hero' => ['title' => 'BREAK-BUILD']]]);
check(strpos($r['body'], 'أُعيد المحتوى السابق') !== false, 'build error reported to the editor', flash_text($r['body']));
check(json_file($content . '/texts.json.php') === $texts, 'texts.json restored after failed build');
check(strpos($r['body'], 'BREAK-BUILD') !== false, 'editor keeps the typed value after a failed build');
check(strpos((string) file_get_contents($SITE . '/about.html'), 'BREAK-BUILD') === false, 'public page not changed by the failed save');
file_put_contents($tpl, $orig);
sleep(3);
$status = (string) file_get_contents($storage . '/build-status.php');
check(strpos($status, '"ok": false') !== false && strpos($status, 'test failure') !== false, 'failed build recorded for the dashboard');
$r = $b->get($A);
check(strpos($r['body'], 'فشل آخر تحديث للموقع') !== false, 'dashboard shows the failed build');
$b->post($A, ['action' => 'rebuild']);

/* ====================================================================== */
section('Settings');
$settings = json_file($content . '/settings.json.php');
$settings['extra_key_from_developer'] = ['keep' => 'me'];
file_put_contents($content . '/settings.json.php', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$form = function (array $over = []) use ($settings): array {
    $f = [
        'site_url' => 'https://www.beyootmisr.org/',
        'contact' => $settings['contact'],
        'social' => ['facebook' => '', 'instagram' => ''],
        'legal' => $settings['legal'],
        'donations' => ['allocation_note' => '', 'methods' => []],
    ];
    return array_replace_recursive($f, $over);
};
$r = $b->get($A . '?p=settings');
check($r['status'] === 200 && strpos($r['body'], 'name="donations[enabled]"') !== false && strpos($r['body'], 'enctype="multipart/form-data"') !== false, 'settings form renders');
$r = $b->post($A . '?p=settings', $form(['contact' => ['email' => 'not-an-email'], 'social' => ['facebook' => 'javascript:alert(1)']]));
check($r['status'] === 200 && strpos($r['body'], 'البريد الإلكتروني غير صحيح') !== false && strpos($r['body'], 'https://') !== false, 'invalid email and URL rejected');
check(json_file($content . '/settings.json.php') === $settings, 'nothing saved on validation errors');
check(strpos($r['body'], 'value="not-an-email"') !== false, 'invalid value is shown again so it can be corrected');
$r = $b->post($A . '?p=settings', $form(['contact' => ['email' => 'info@beyootmisr.org', 'hotline' => '٠١٢١١١١٤٦٦٢'], 'social' => ['facebook' => 'https://www.facebook.com/بيوت-مصر']]));
check($r['status'] === 303, 'valid settings saved', flash_text($r['body']));
$s2 = json_file($content . '/settings.json.php');
check($s2['site_url'] === 'https://www.beyootmisr.org' && $s2['contact']['email'] === 'info@beyootmisr.org' && $s2['contact']['hotline'] === '01211114662', 'values normalised (trailing slash, Arabic digits)');
check(($s2['extra_key_from_developer']['keep'] ?? '') === 'me', 'unknown settings keys preserved');
check(strpos((string) file_get_contents($SITE . '/sitemap.xml'), 'https://www.beyootmisr.org/') !== false, 'site_url enables sitemap on rebuild');
$method = ['title' => 'حساب بنكي باسم المؤسسة', 'details' => 'بنك مصر — رقم الحساب 123', 'link' => '', 'note' => ''];
$r = $b->post($A . '?p=settings', $form(['donations' => ['enabled' => '1']]));
check(strpos($r['body'], 'لا يمكن تفعيل التبرع') !== false, 'donations cannot be enabled without methods');
$r = $b->post($A . '?p=settings', $form(['donations' => ['enabled' => '1', 'methods' => [5 => $method]]]));
check(strpos($r['body'], 'أكّد أنك تحققت') !== false && empty(json_file($content . '/settings.json.php')['donations']['enabled']), 'verification checkbox required to publish methods');
check(strpos($r['body'], 'name="donations[methods][5][title]"') !== false, 'posted method row shown again with its key');
$r = $b->post($A . '?p=settings', $form(['donations' => ['enabled' => '1', 'verified' => '1', 'methods' => [5 => $method, 9 => ['title' => '', 'details' => '', 'link' => '', 'note' => '']]]]));
$s3 = json_file($content . '/settings.json.php');
check(!empty($s3['donations']['enabled']) && $s3['donations']['methods'] === [$method], 'donation method saved; empty rows dropped');
check(!isset($s3['donations']['verified']), 'confirmation checkbox is not stored');
$r = $b->post($A . '?p=settings', $form(['donations' => ['methods' => [0 => ['title' => 'رابط', 'details' => '', 'link' => 'data:text/html,x', 'note' => '']]]]));
check(strpos($r['body'], 'https://') !== false && json_file($content . '/settings.json.php') === $s3, 'non-http donation link rejected');

// Logo upload.
$tmpdir = $WORK;
$png = $tmpdir . '/bm-logo.png';
$im = imagecreatetruecolor(1200, 400);
imagesavealpha($im, true);
imagealphablending($im, false);
imagefilledrectangle($im, 0, 0, 1200, 400, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagefilledrectangle($im, 100, 100, 300, 300, imagecolorallocatealpha($im, 25, 132, 192, 0));
imagepng($im, $png);
$r = $b->get($A . '?p=settings');
$f = $form(['donations' => ['enabled' => '1', 'methods' => [0 => $method]]]);
$f['logo_file'] = new CURLFile($png, 'image/png', '../../evil name.png');
$r = $b->post($A . '?p=settings', $f);
$s4 = json_file($content . '/settings.json.php');
check($r['status'] === 303 && preg_match('#^uploads/images/logo-[a-f0-9]{16}\.png$#', $s4['logo']) === 1, 'logo uploaded with a random name (client name ignored)', $s4['logo'] . ' ' . flash_text($r['body']));
$logo1 = $s4['logo'];
[$lw] = getimagesize($SITE . '/' . $logo1);
check($lw === 800, 'logo resized to ≤800 px', (string) $lw);
check(strpos((string) file_get_contents($SITE . '/index.html'), $logo1) !== false, 'public header uses the uploaded logo');
$svg = $tmpdir . '/bm-logo.svg';
file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$r = $b->get($A . '?p=settings');
$f['logo_file'] = new CURLFile($svg, 'image/svg+xml', 'logo.svg');
$r = $b->post($A . '?p=settings', $f);
check(strpos($r['body'], 'نوع الملف غير مسموح') !== false && json_file($content . '/settings.json.php')['logo'] === $logo1, 'SVG logo rejected');
$fake = $tmpdir . '/bm-fake.png';
file_put_contents($fake, "<?php system('id'); ?>");
$r = $b->get($A . '?p=settings');
$f['logo_file'] = new CURLFile($fake, 'image/png', 'shell.php.png');
$r = $b->post($A . '?p=settings', $f);
check(strpos($r['body'], 'نوع الملف غير مسموح') !== false && count(glob($SITE . '/uploads/images/*.php*') ?: []) === 0, 'PHP disguised as PNG rejected, nothing stored');
$r = $b->get($A . '?p=settings');
$f['logo_file'] = new CURLFile($png, 'image/png', 'logo2.png');
$r = $b->post($A . '?p=settings', $f);
$logo2 = json_file($content . '/settings.json.php')['logo'];
check($logo2 !== $logo1 && is_file($SITE . '/' . $logo2) && !is_file($SITE . '/' . $logo1), 'replacing the logo deletes the old file');
$r = $b->get($A . '?p=settings');
$f2 = $form(['donations' => ['enabled' => '1', 'methods' => [0 => $method]]]);
$f2['logo_remove'] = '1';
$r = $b->post($A . '?p=settings', $f2);
check(json_file($content . '/settings.json.php')['logo'] === '' && !is_file($SITE . '/' . $logo2), 'logo removed');

/* ====================================================================== */
section('Users & roles');
$r = $b->get($A . '?p=users');
check($r['status'] === 200 && strpos($r['body'], 'إضافة مستخدم') !== false, 'users page');
$r = $b->post($A . '?p=users', ['action' => 'create', 'name' => 'سارة', 'username' => 'Sara.Ali', 'role' => 'editor', 'password' => $EDITOR_PW, 'password2' => $EDITOR_PW]);
check($r['status'] === 303, 'editor created', flash_text($r['body']));
$r = $b->get($A . '?p=users');
$r = $b->post($A . '?p=users', ['action' => 'create', 'name' => 'x', 'username' => 'sara.ali', 'role' => 'editor', 'password' => $EDITOR_PW, 'password2' => $EDITOR_PW]);
check(strpos($r['body'], 'مستخدم بالفعل') !== false, 'duplicate username refused');
$r = $b->post($A . '?p=users', ['action' => 'create', 'name' => 'x', 'username' => 'root', 'role' => 'superuser', 'password' => $EDITOR_PW, 'password2' => $EDITOR_PW]);
check(strpos($r['body'], 'اختر قيمة من القائمة') !== false, 'unknown role refused');
$r = $b->post($A . '?p=users', ['action' => 'role', 'username' => 'raheem', 'role' => 'editor']);
$b->follow();
check(strpos(flash_text($b->last['body']), 'لا يمكنك تغيير دورك') !== false, 'admin cannot demote themself');
$r = $b->post($A . '?p=users', ['action' => 'delete', 'username' => 'raheem']);
$b->follow();
check(strpos(flash_text($b->last['body']), 'لا يمكنك حذف حسابك') !== false, 'admin cannot delete themself');

$ed = new Browser($BASE);
$ed->get($A . '?p=login');
$r = $ed->post($A . '?p=login', ['username' => 'SARA.ALI', 'password' => $EDITOR_PW]);
check($r['status'] === 303, 'editor logs in (username case-insensitive)');
$r = $ed->follow();
check(strpos($r['body'], 'p=settings') === false && strpos($r['body'], 'p=users') === false && strpos($r['body'], 'p=backup') === false && strpos($r['body'], 'p=texts') !== false, 'editor navigation hides admin-only sections');
foreach (['settings', 'users', 'backup', 'audit'] as $sec) {
    $r = $ed->get($A . '?p=' . $sec);
    check($r['status'] === 403, "editor GET $sec → 403");
}
$before = (string) file_get_contents($content . '/settings.json.php');
$ed->get($A . '?p=texts');
$r = $ed->post($A . '?p=settings', $form(['site_url' => 'https://evil.example']));
check($r['status'] === 403 && file_get_contents($content . '/settings.json.php') === $before, 'editor POST settings → 403, unchanged');
$r = $ed->post($A . '?p=users', ['action' => 'create', 'name' => 'x', 'username' => 'eviladmin', 'role' => 'admin', 'password' => $EDITOR_PW, 'password2' => $EDITOR_PW]);
check($r['status'] === 403 && strpos((string) file_get_contents($storage . '/users.php'), 'eviladmin') === false, 'editor cannot create users');
$r = $ed->post($A . '?p=backup', ['action' => 'zip']);
check($r['status'] === 403, 'editor cannot download backups');
$ed->get($A . '?p=texts');
$texts = json_file($content . '/texts.json.php');
$r = $ed->post($A . '?p=texts', ['group' => 'nav', 'h' => $hash($texts['nav']), 't' => ['home' => 'الصفحة الرئيسية']]);
check($r['status'] === 303 && json_file($content . '/texts.json.php')['nav']['home'] === 'الصفحة الرئيسية', 'editor can edit texts');
$ed->get($A);
$r = $ed->post($A, ['action' => 'rebuild']);
check($r['status'] === 303, 'editor can rebuild');

// Reset the editor's password → their open session ends.
$b->get($A . '?p=users');
$r = $b->post($A . '?p=users', ['action' => 'password', 'username' => 'sara.ali', 'password' => 'short']);
$b->follow();
check(strpos(flash_text($b->last['body']), 'لا تقل عن') !== false, 'reset with weak password refused');
$r = $b->post($A . '?p=users', ['action' => 'password', 'username' => 'sara.ali', 'password' => 'new editor pass 99']);
check($r['status'] === 303, 'admin resets editor password');
$r = $ed->get($A . '?p=texts');
check($r['status'] === 303 && strpos($r['location'], 'p=login') !== false, "editor's old session ended after reset");

// Promote then demote; last-admin protection.
$b->get($A . '?p=users');
$b->post($A . '?p=users', ['action' => 'role', 'username' => 'sara.ali', 'role' => 'admin']);
$usersArr = (static function ($f) { define('BM_APP', true); return include $f; })($storage . '/users.php');
check(($usersArr['sara.ali']['role'] ?? '') === 'admin', 'role changed to admin');
$b->get($A . '?p=users');
$b->post($A . '?p=users', ['action' => 'role', 'username' => 'sara.ali', 'role' => 'editor']);
$b->get($A . '?p=users');
$r = $b->post($A . '?p=users', ['action' => 'delete', 'username' => 'sara.ali']);
check($r['status'] === 303 && strpos((string) file_get_contents($storage . '/users.php'), 'sara.ali') === false, 'user deleted');

/* ====================================================================== */
section('Own account');
$r = $b->get($A . '?p=account');
$r = $b->post($A . '?p=account', ['action' => 'password', 'current' => 'wrong password here', 'password' => 'another long pass 1', 'password2' => 'another long pass 1']);
check(strpos($r['body'], 'كلمة المرور الحالية غير صحيحة') !== false, 'wrong current password refused');
$NEW_PW = 'another long pass 1';
$r = $b->post($A . '?p=account', ['action' => 'password', 'current' => $ADMIN_PW, 'password' => $NEW_PW, 'password2' => $NEW_PW]);
check($r['status'] === 303, 'own password changed');
$r = $b->follow();
check($r['status'] === 200 && strpos($r['body'], 'تغيّرت كلمة مرورك') !== false, 'still logged in after changing own password');
$ADMIN_PW = $NEW_PW;
$r = $b->post($A . '?p=account', ['action' => 'name', 'name' => 'رحيم الإمام']);
check($r['status'] === 303, 'display name changed');

/* ====================================================================== */
section('Session timeouts');
$sessFile = function (Browser $br) use ($storage): string {
    return $storage . '/sessions/' . hash('sha256', $br->cookie()) . '.php';
};
$f = $sessFile($b);
check(is_file($f), 'session file found by hash of the cookie');
$data = (string) file_get_contents($f);
file_put_contents($f, preg_replace('/s:4:"last";i:\d+;/', 's:4:"last";i:' . (time() - 1900) . ';', $data));
$r = $b->get($A . '?p=texts');
check($r['status'] === 303 && strpos($r['location'], 'm=timeout') !== false, 'idle timeout (30 min) → login with message');
$b->get($A . '?p=login');
$r = $b->post($A . '?p=login', ['username' => 'raheem', 'password' => $ADMIN_PW]);
check($r['status'] === 303, 'log back in');
$f = $sessFile($b);
$data = (string) file_get_contents($f);
file_put_contents($f, preg_replace('/s:7:"created";i:\d+;/', 's:7:"created";i:' . (time() - 8 * 3600 - 60) . ';', $data));
$r = $b->get($A);
check($r['status'] === 303 && strpos($r['location'], 'm=timeout') !== false, 'absolute timeout (8 h)');
$b->get($A . '?p=login');
$b->post($A . '?p=login', ['username' => 'raheem', 'password' => $ADMIN_PW]);

/* ====================================================================== */
section('Backups');
$r = $b->get($A . '?p=backup');
check($r['status'] === 200 && strpos($r['body'], 'تنزيل نسخة كاملة') !== false, 'backup page');
$r = $b->post($A . '?p=backup', ['action' => 'zip']);
check($r['status'] === 200 && ($r['headers']['content-type'][0] ?? '') === 'application/zip', 'ZIP download', ($r['headers']['content-type'][0] ?? ''));
$zipFile = $tmpdir . '/bm-test.zip';
file_put_contents($zipFile, $r['body']);
$zip = new ZipArchive();
$names = [];
if ($zip->open($zipFile) === true) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();
}
check(in_array('app/content/texts.json.php', $names, true) && in_array('app/content/settings.json.php', $names, true) && in_array('README.txt', $names, true), 'ZIP has content JSON + README', implode(',', $names));
check(!preg_grep('#storage|users#', $names), 'ZIP never includes accounts or storage');
$b->get($A . '?p=backup');
$list = glob($storage . '/backups/texts/*.json.php') ?: [];
sort($list);
$oldest = basename($list[0], '.json.php');
$oldestJson = json_decode(substr((string) file_get_contents($list[0]), strlen("<?php exit; ?>\n")), true);
$r = $b->post($A . '?p=backup', ['action' => 'restore', 'file' => 'texts', 'id' => $oldest]);
check($r['status'] === 303 && json_file($content . '/texts.json.php') === $oldestJson, 'restore a backup');
$b->get($A . '?p=backup');
$r = $b->post($A . '?p=backup', ['action' => 'restore', 'file' => 'texts', 'id' => '../../users']);
$b->follow();
check(strpos(flash_text($b->last['body']), 'لم نجد هذه النسخة') !== false, 'restore with a bad id refused');
$r = $b->post($A . '?p=backup', ['action' => 'download', 'file' => 'texts', 'id' => $oldest]);
check(($r['headers']['content-type'][0] ?? '') === 'application/json; charset=utf-8' && json_decode($r['body'], true) === $oldestJson, 'download one backup as JSON');
foreach (glob($storage . '/backups/*/*.json.php') ?: [] as $bf) {
    $x = (new Browser($BASE))->get('/app/storage/backups/' . basename(dirname($bf)) . '/' . basename($bf));
    if (trim($x['body']) !== '') {
        check(false, 'backup files print nothing over HTTP', $bf);
        break;
    }
}
check(true, 'backup files print nothing over HTTP');

/* ====================================================================== */
section('Audit log');
$r = $b->get($A . '?p=audit');
check($r['status'] === 200 && strpos($r['body'], 'تعديل النصوص') !== false && strpos($r['body'], 'تعديل الإعدادات') !== false && strpos($r['body'], 'إضافة مستخدم') !== false, 'audit page lists actions');
$r = $b->get($A . '?p=audit&u=raheem&s=texts');
check($r['status'] === 200 && strpos($r['body'], 'تعديل الإعدادات') === false, 'audit filters by section');

/* ====================================================================== */
section('CSP-safe markup on every page');
foreach (['dashboard', 'texts', 'settings', 'users', 'account', 'audit', 'backup', 'stats', 'projects', 'stories', 'reports', 'galleries'] as $sec) {
    $r = $b->get($A . ($sec === 'dashboard' ? '' : '?p=' . $sec));
    $bad = preg_match('/\sstyle\s*=/i', $r['body']) || preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $r['body']) || preg_match('/\son[a-z]+\s*=\s*["\']/i', $r['body']);
    check($r['status'] === 200 && !$bad, "$sec: no inline style/script/event handlers", (string) $r['status']);
}

/* ====================================================================== */
section('Logout & login rate limiting');
$b->get($A);
$r = $b->post($A . '?p=logout', []);
check($r['status'] === 303 && strpos($r['location'], 'm=out') !== false, 'logout via POST');
$r = $b->get($A . '?p=texts');
check($r['status'] === 303 && strpos($r['location'], 'p=login') !== false, 'logged out');
$atk = new Browser($BASE);
$atk->get($A . '?p=login');
$msgs = [];
for ($i = 0; $i < 5; $i++) {
    $r = $atk->post($A . '?p=login', ['username' => 'raheem', 'password' => 'wrong-' . $i]);
    $msgs[] = flash_text($r['body']);
}
$r = $atk->post($A . '?p=login', ['username' => 'raheem', 'password' => $ADMIN_PW]);
check($r['status'] === 200 && strpos($r['body'], 'تعذّر تسجيل الدخول') !== false, 'after 5 failures even the right password is refused (locked)');
$r2 = $atk->post($A . '?p=login', ['username' => 'nobody-here', 'password' => 'whatever']);
check(flash_text($r['body']) === flash_text($r2['body']) && count(array_unique($msgs)) === 1, 'one generic error message for every failure');
$rl = (string) file_get_contents($storage . '/ratelimit.php');
check(strpos($rl, '<?php exit; ?>') === 0 && strpos($rl, '127.0.0.1') === false && strpos($rl, 'raheem') === false, 'rate-limit file is guarded and stores only hashes');
check(strpos((string) file_get_contents($storage . '/audit.php'), '"login.failed"') !== false, 'failed logins are audited');
unlink($storage . '/ratelimit.php'); // simulate the 15 minutes passing
$atk->get($A . '?p=login');
$r = $atk->post($A . '?p=login', ['username' => 'raheem', 'password' => $ADMIN_PW]);
check($r['status'] === 303, 'login works again after the lock expires');

echo "\nhttp: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
