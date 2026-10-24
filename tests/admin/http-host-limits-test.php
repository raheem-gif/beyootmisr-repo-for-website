<?php
/**
 * The admin panel on a restrictive shared host. run.sh restarts `php -S` with
 *   -d disable_functions=set_time_limit,ignore_user_abort,ini_set,chmod,…
 * Since PHP 8 a disabled function is undefined: calling it is a fatal Error even with "@",
 * so every optional call must be guarded. This drives login, a content save (write → rebuild →
 * audit), a manual rebuild, a photo upload and the ZIP backup on such a server.
 *
 * Usage: php tests/admin/http-host-limits-test.php <base-url> <sandbox-site-dir>
 * Creates its own admin account (limits-admin) in the sandbox.
 */
declare(strict_types=1);
date_default_timezone_set('Africa/Cairo');

$BASE = rtrim($argv[1] ?? '', '/');
$SITE = rtrim($argv[2] ?? '', '/');
if ($BASE === '' || !is_dir($SITE . '/app/content')) {
    fwrite(STDERR, "usage: php http-host-limits-test.php <base-url> <sandbox-site-dir>\n");
    exit(2);
}
if (realpath($SITE) === realpath(__DIR__ . '/../../public')) {
    fwrite(STDERR, "Refusing to run against the real site folder.\n");
    exit(2);
}
$WORK = dirname($SITE) . '/http-limits-tmp';
require __DIR__ . '/http-lib.php';

$A = '/admin/index.php';
$content = $SITE . '/app/content';
$storage = $SITE . '/app/storage';
$USER = 'limits-admin';
$PW = 'limits admin pass 2026';

// The account is created by the CLI (not affected by the server's disable_functions).
$code = 'define("BM_APP", true); define("ADM_WEB_DIR", $argv[1] . "/admin"); require $argv[1] . "/app/admin/bootstrap.php";'
    . ' if (adm_user_get($argv[2]) === null) { adm_user_create($argv[2], "مدير الاستضافة المقيدة", "admin", $argv[3]); } echo "ok";';
$out = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg($SITE) . ' ' . escapeshellarg($USER) . ' ' . escapeshellarg($PW)));
check($out === 'ok', 'test account created', $out);

$errorsFile = $storage . '/errors.php';
$errorsBefore = is_file($errorsFile) ? (string) file_get_contents($errorsFile) : '';
$auditLines = function () use ($storage): array {
    return array_values(array_filter(explode("\n", (string) @file_get_contents($storage . '/audit.php')), function ($l) {
        return $l !== '' && strpos($l, '<?php') !== 0;
    }));
};

/* ====================================================================== */
section('Sessions without ini_set / chmod');
$b = new Browser($BASE);
$r = $b->get($A . '?p=login');
check($r['status'] === 200 && strpos($r['body'], 'name="password"') !== false, 'login page opens (no fatal error on session start)', (string) $r['status']);
$cookieHdr = implode("\n", $r['headers']['set-cookie'] ?? []);
check(stripos($cookieHdr, 'HttpOnly') !== false && stripos($cookieHdr, 'SameSite=Strict') !== false && stripos($cookieHdr, 'path=/admin/') !== false, 'session cookie still HttpOnly, SameSite=Strict, path=/admin/', $cookieHdr);
$fixed = str_repeat('a1b2', 8);
$x = (new Browser($BASE))->request('GET', $A . '?p=login', null, ['Cookie: bm_admin=' . $fixed]);
$xCookie = implode("\n", $x['headers']['set-cookie'] ?? []);
check(preg_match('/bm_admin=([a-zA-Z0-9,-]+)/', $xCookie, $m) === 1 && $m[1] !== $fixed, 'strict mode still on: an unknown session id from the client is replaced', $xCookie);
$r = $b->post($A . '?p=login', ['username' => $USER, 'password' => $PW]);
check($r['status'] === 303, 'login', (string) $r['status']);
$r = $b->get($A);
check($r['status'] === 200 && strpos($r['body'], 'قائمة المراجعة قبل إطلاق الموقع') !== false, 'logged in: the session was stored', (string) $r['status']);

/* ====================================================================== */
section('Saving content without set_time_limit');
$settings = json_file($content . '/settings.json.php');
$hours = 'يوميًا عدا الجمعة (اختبار استضافة مقيدة ' . random_int(1000, 9999) . ')';
$methods = [];
foreach ((array) ($settings['donations']['methods'] ?? []) as $i => $m) {
    if (is_array($m)) {
        $methods[$i] = array_intersect_key($m + ['title' => '', 'details' => '', 'link' => '', 'note' => ''], array_flip(['title', 'details', 'link', 'note']));
    }
}
$form = [
    'site_url' => (string) $settings['site_url'],
    'contact' => array_replace($settings['contact'], ['hours' => $hours]),
    'social' => $settings['social'],
    'legal' => $settings['legal'],
    'donations' => ['allocation_note' => (string) ($settings['donations']['allocation_note'] ?? ''), 'methods' => $methods],
];
if (!empty($settings['donations']['enabled'])) {
    $form['donations']['enabled'] = '1';
}
$audit0 = count($auditLines());
$b->get($A . '?p=settings');
$r = $b->post($A . '?p=settings', $form);
check($r['status'] === 303, 'settings saved (no HTTP 500)', $r['status'] . ' ' . flash_text($r['body']));
$r = $b->follow();
check(strpos(flash_text($r['body']), 'success') === 0, 'success message', flash_text($r['body']));
check((json_file($content . '/settings.json.php')['contact']['hours'] ?? '') === $hours, 'settings.json updated');
check(strpos((string) file_get_contents($SITE . '/contact.html'), $hours) !== false, 'contact page rebuilt with the new value');
$audit = $auditLines();
check(count($audit) === $audit0 + 1 && strpos((string) end($audit), '"a":"settings.save"') !== false, 'audit entry written', (string) end($audit));
$b->get($A);
$r = $b->post($A, ['action' => 'rebuild']);
check($r['status'] === 303, 'manual rebuild from the dashboard', (string) $r['status']);
$r = $b->follow();
check(strpos($r['body'], 'أُعيد بناء الموقع') !== false, 'rebuild flash', flash_text($r['body']));

/* ====================================================================== */
section('Photo upload without chmod / ini_set');
if (function_exists('imagecreatetruecolor')) {
    $jpg = $WORK . '/limits.jpg';
    $im = imagecreatetruecolor(320, 200);
    imagefilledrectangle($im, 0, 0, 319, 199, (int) imagecolorallocate($im, 25, 132, 192));
    imagejpeg($im, $jpg, 85);
    $r = $b->get($A . '?p=galleries&a=najah');
    preg_match('/name="rev" value="([a-f0-9]*)"/', $r['body'], $m);
    $r = $b->post($A . '?p=galleries&a=najah', [
        'rev' => $m[1] ?? '',
        'imgs_new' => [new CURLFile($jpg, 'image/jpeg', 'limits.jpg')],
        'imgs_alt_each' => ['صورة اختبار الاستضافة المقيدة'],
    ]);
    check($r['status'] === 303, 'photo uploaded', $r['status'] . ' ' . flash_text($r['body']));
    $gal = json_file($content . '/galleries.json.php');
    $img = $gal['najah'][0] ?? null;
    check(is_array($img) && is_file($SITE . '/' . $img['src']) && is_file($SITE . '/' . $img['thumb']), 'photo and thumbnail written');
    // Clean up so the sandbox ends as it started.
    $r = $b->get($A . '?p=galleries&a=najah');
    preg_match('/name="rev" value="([a-f0-9]*)"/', $r['body'], $m);
    $rows = [];
    foreach ((array) ($gal['najah'] ?? []) as $g) {
        $rows[] = ['k' => substr(sha1((string) $g['src']), 0, 12), 'alt' => $g['alt'], 'caption' => $g['caption'] ?? '', 'del' => '1'];
    }
    $r = $b->post($A . '?p=galleries&a=najah', ['rev' => $m[1] ?? '', 'imgs' => $rows]);
    check($r['status'] === 303 && empty(json_file($content . '/galleries.json.php')['najah']), 'photo deleted again', $r['status'] . ' ' . flash_text($r['body']));
} else {
    echo "  skip GD not available in the CLI\n";
}

/* ====================================================================== */
section('ZIP backup without set_time_limit / ignore_user_abort');
if (class_exists('ZipArchive')) {
    $b->get($A . '?p=backup');
    $r = $b->post($A . '?p=backup', ['action' => 'zip']);
    check($r['status'] === 200 && ($r['headers']['content-type'][0] ?? '') === 'application/zip' && strncmp($r['body'], "PK", 2) === 0, 'ZIP download', $r['status'] . ' ' . ($r['headers']['content-type'][0] ?? ''));
} else {
    echo "  skip ZipArchive not available\n";
}

/* ====================================================================== */
section('No errors');
$errorsNow = is_file($errorsFile) ? (string) file_get_contents($errorsFile) : '';
$new = trim((string) substr($errorsNow, strlen($errorsBefore)));
check($new === '', 'nothing written to app/storage/errors.php', $new);

echo "\nhttp-host-limits: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
