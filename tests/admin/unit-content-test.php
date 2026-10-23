<?php
/**
 * Unit tests for app/admin/collections.php (stage 2): slugs, record lookup, the image-list
 * editor's POST handling (order, reorder, deletion, descriptions, unknown keys) and the
 * "delete only unreferenced uploads" rule.
 *
 * Usage: php tests/admin/unit-content-test.php <sandbox-site-dir>   (a disposable copy of public/, see run.sh)
 */
declare(strict_types=1);

$site = rtrim($argv[1] ?? '', '/');
if ($site === '' || !is_file($site . '/app/admin/bootstrap.php')) {
    fwrite(STDERR, "usage: php unit-content-test.php <sandbox-site-dir>\n");
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

echo "Slugs and records\n";
check(adm_unique_slug('roof', ['roof', 'roof-2']) === 'roof-3', 'unique slug gets a numeric suffix');
check(adm_unique_slug('', []) === 'item', 'empty base falls back');
$taken = ['masjid-el-tor', 'roof'];
$in = new AdmInput(['slug' => '']);
check(adm_slug_from_post($in, 'slug', 'الرابط', 'Roof', $taken, '') === 'roof-2' && $in->ok(), 'empty slug on a new record: from the title, made unique');
$in = new AdmInput(['slug' => '']);
check(adm_slug_from_post($in, 'slug', 'الرابط', 'أي عنوان', $taken, 'roof') === 'roof' && $in->ok(), 'empty slug on an existing record keeps the current one');
$in = new AdmInput(['slug' => '']);
check(preg_match('/^[a-f0-9]{10}$/', adm_slug_from_post($in, 'slug', 'الرابط', 'مسجد القرية', $taken, '')) === 1, 'Arabic-only title → short hash slug');
$in = new AdmInput(['slug' => '  Roof  Test_2 ']);
check(adm_slug_from_post($in, 'slug', 'الرابط', '', $taken, '') === 'roof-test-2' && $in->ok(), 'spaces/underscores become dashes, lower case');
foreach (['مسجد', 'roof!', '../etc', 'a', str_repeat('a', 61)] as $bad) {
    $in = new AdmInput(['slug' => $bad]);
    adm_slug_from_post($in, 'slug', 'الرابط', '', $taken, '');
    check(!$in->ok(), 'slug rejected: ' . substr($bad, 0, 12));
}
$in = new AdmInput(['slug' => 'masjid-el-tor']);
adm_slug_from_post($in, 'slug', 'الرابط', '', $taken, 'roof');
$errs = $in->errors();
check(!$in->ok() && strpos((string) current($errs), 'مستخدم بالفعل') !== false, 'slug used by another record rejected');
$in = new AdmInput(['slug' => 'roof']);
check(adm_slug_from_post($in, 'slug', 'الرابط', '', $taken, 'roof') === 'roof' && $in->ok(), 'keeping your own slug is fine');
$list = [['id' => 'Roof Test'], 'junk', ['id' => 'b']];
check(adm_list_index($list, 'roof-test') === 0 && adm_list_index($list, 'b') === 2 && adm_list_index($list, 'x') === -1, 'list index compares normalised ids, keeps positions');
check(adm_list_ids($list, 'b') === ['roof-test'], 'ids except one');
check(adm_rev(['a' => 'ب']) === adm_rev(['a' => 'ب']) && adm_rev(['a' => 1]) !== adm_rev(['a' => '1']), 'rev is stable and type-sensitive');
check(adm_rec_rev(['id' => 'Masjid_Tor', 'x' => 1]) === adm_rec_rev(['id' => 'masjid-tor', 'x' => 1]), 'record rev ignores how the id was written');
$_GET = ['id' => '../x'];
check(adm_req_id() === '!', 'malformed id from the query string');
$_GET = ['id' => 'new'];
check(adm_req_id() === 'new', '"new" id');

echo "Image list POST handling\n";
$imgs = [];
foreach (['a', 'b', 'c'] as $n) {
    $imgs[] = ['src' => 'uploads/images/' . str_repeat($n, 16) . '.jpg', 'thumb' => 'uploads/images/' . str_repeat($n, 16) . '-640.jpg', 'w' => 1600, 'h' => 900, 'alt' => 'صورة ' . $n, 'caption' => ''];
}
$k = array_map('adm_img_key', $imgs);
$row = function (int $i, array $extra = []) use ($imgs, $k): array {
    return array_replace(['k' => $k[$i], 'alt' => $imgs[$i]['alt'], 'caption' => ''], $extra);
};
$in = new AdmInput(['imgs' => [5 => $row(2), 1 => $row(0), 9 => $row(1)]]);
$r = adm_images_from_post($imgs, $in, 'imgs');
check($in->ok() && array_column($r['images'], 'src') === [$imgs[2]['src'], $imgs[0]['src'], $imgs[1]['src']], 'posted row order becomes the saved order');
$in = new AdmInput(['imgs' => [$row(0), $row(1), $row(2)], 'imgs_move' => $k[2] . ':up']);
$r = adm_images_from_post($imgs, $in, 'imgs');
check(array_column($r['images'], 'src') === [$imgs[0]['src'], $imgs[2]['src'], $imgs[1]['src']], 'no-JS «تقديم» swaps with the previous photo');
$in = new AdmInput(['imgs' => [$row(0), $row(1), $row(2)], 'imgs_move' => $k[0] . ':up']);
$r = adm_images_from_post($imgs, $in, 'imgs');
check(array_column($r['images'], 'src') === array_column($imgs, 'src'), 'moving the first photo up does nothing');
$in = new AdmInput(['imgs' => [$row(0), $row(1, ['del' => '1', 'alt' => '']), $row(2)]]);
$r = adm_images_from_post($imgs, $in, 'imgs');
check($in->ok() && count($r['images']) === 2 && $r['removed'][0]['src'] === $imgs[1]['src'] && !empty($r['view'][1]['_del']), 'delete flag: removed list, no description needed, kept in the view');
$in = new AdmInput(['imgs' => [$row(0), $row(1)]]);
$r = adm_images_from_post($imgs, $in, 'imgs');
check(count($r['images']) === 3 && $r['images'][2]['src'] === $imgs[2]['src'], 'photos missing from the form (added meanwhile) are kept at the end');
$in = new AdmInput(['imgs' => [['k' => 'ffffffffffff', 'alt' => 'x', 'del' => '1'], ['k' => $k[0], 'alt' => 'x'], ['k' => $k[0], 'alt' => 'y', 'del' => '1']]]);
$r = adm_images_from_post($imgs, $in, 'imgs');
check(!$r['removed'] && count($r['images']) === 3, 'unknown and duplicate keys are ignored (nothing deleted)');
$in = new AdmInput(['imgs' => [$row(0, ['alt' => '']), $row(1, ['alt' => 'photo']), $row(2, ['alt' => "وصف\x07 جديد", 'caption' => str_repeat('ت', 301)])]]);
$r = adm_images_from_post($imgs, $in, 'imgs');
$e = $in->errors();
check(isset($e['imgs.' . $k[0] . '.alt'], $e['imgs.' . $k[1] . '.alt'], $e['imgs.' . $k[2] . '.caption']), 'empty / non-Arabic description and long caption are errors keyed by image', json_encode(array_keys($e)));
check($r['view'][2]['alt'] === 'وصف جديد', 'descriptions are cleaned of control characters');
check(adm_alt_problem('السقف بعد الصب') === '' && adm_alt_problem('roof') !== '' && adm_alt_problem('') !== '', 'description rules');
$_FILES = [];
$in = new AdmInput([]);
check(adm_images_pending($in, 'imgs', 5) === [] && $in->ok(), 'no files chosen → nothing pending');

echo "Deleting only unreferenced uploads\n";
$dir = $site . '/uploads/images';
$keep = 'uploads/images/' . bin2hex(random_bytes(8)) . '.jpg';
$drop = 'uploads/images/' . bin2hex(random_bytes(8)) . '.jpg';
file_put_contents($site . '/' . $keep, 'x');
file_put_contents($site . '/' . $drop, 'x');
$galFile = BM_CONTENT . '/galleries.json.php';
$galOrig = (string) file_get_contents($galFile);
bm_json_write($galFile, ['salama' => [['src' => $keep, 'thumb' => $keep, 'alt' => 'م']]]);
$refs = adm_uploads_referenced();
check(isset($refs[$keep]) && !isset($refs[$drop]), 'references collected from content JSON');
check(adm_delete_unreferenced([$keep, $drop, 'uploads/images/../../app/content/texts.json.php']) === 1, 'one file deleted');
check(is_file($site . '/' . $keep) && !is_file($site . '/' . $drop) && is_file(BM_CONTENT . '/texts.json.php'), 'referenced file kept, unreferenced deleted, traversal ignored');
file_put_contents($galFile, $galOrig);
@unlink($site . '/' . $keep);
check(adm_image_paths([['src' => 'a', 'thumb' => 'a'], 'b', ['src' => 'c', 'thumb' => 'd']]) === ['a', 'b', 'c', 'd'], 'image paths (src + thumb, legacy string records)');

echo "Options\n";
$opts = adm_activity_options('— اختر —');
check(count($opts) === 11 && $opts[''] === '— اختر —' && strpos($opts['salama'], 'سلامة') !== false, 'activity options: the ten activities + none');
check(array_keys(adm_story_type_options()) === array_keys(bm_story_types()), 'story types from the registry');
$icons = require $site . '/app/lib/icons-data.php';
check(!array_diff(array_keys(adm_stat_icons()), array_keys($icons)), 'every selectable stats icon exists in the site icon set');

echo "\nunit-content: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
