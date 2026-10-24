<?php
/**
 * Stage-2 content helpers: lists of records (projects, stories, reports), slugs,
 * activity/story-type options, and the image-list editor shared by projects,
 * project stages, stories and activity galleries.
 *
 * Records
 *   adm_list_load(string $name): array               stored list (only arrays with an id), ids normalised
 *   adm_list_index(array $list, string $id): int     position of a record, -1 when missing
 *   adm_list_ids(array $list, string $except = ''): array
 *   adm_rev($data): string                           short hash for edit-conflict detection (hidden "rev" field)
 *   adm_req_id(string $key = 'id'): string           validated id from the query string ('' / 'new' / slug)
 *   adm_slug_from_post(AdmInput $in, string $path, string $label, string $title, array $taken, string $current): string
 *       Optional English slug: '' → keep $current, or slugify($title) (made unique). Otherwise must be
 *       [a-z0-9-] (spaces/underscores become "-"), 2–60 characters, and not used by another record.
 *   adm_unique_slug(string $base, array $taken): string
 *
 * Options
 *   adm_activity_options(string $noneLabel = ''): array   slug => Arabic name (+ '' => $noneLabel when given)
 *   adm_activity_name(string $slug): string
 *   adm_story_type_options(): array                        type => Arabic name
 *   adm_stat_icons(): array                                 safe icon list for «أثرنا بالأرقام»: name => Arabic label
 *
 * Images (every image needs an Arabic description; files are re-encoded by uploads.php)
 *   adm_img_key(array $img): string                 stable key of an image record (from its src)
 *   adm_images_editor(array $o): string             HTML: current images (alt, caption, reorder, delete) + upload.
 *       o: prefix ('imgs'), items (records; may carry '_del' => true after a failed save), max (40),
 *          label, empty (text when there are no images), upload_label, hint
 *       Posts: prefix[n][k|alt|caption|del], prefix_move ("<key>:up|down", no-JS reorder),
 *              prefix_new[] (files), prefix_alt / prefix_caption (shared), prefix_alt_each[i] / prefix_caption_each[i] (per file, admin.js)
 *   adm_images_from_post(array $current, AdmInput $in, string $prefix): array
 *       ['images' => kept records in the new order, 'removed' => records marked for deletion,
 *        'view' => rows to show again after an error (with '_del')]. Validates alt/caption.
 *   adm_images_pending(AdmInput $in, string $prefix, int $room): array
 *       Validates the chosen files' descriptions before anything is processed: [{file, alt, caption, n, name}]
 *   adm_images_process(array $pending): array      ['added' => records, 'failed' => Arabic messages]
 *
 * Files
 *   adm_uploads_referenced(): array                 every "uploads/…" path that content JSON still references
 *   adm_delete_unreferenced(array $paths): int      delete those of $paths no content references any more
 *   adm_image_paths(array $images): array           src + thumb paths of image records
 *   adm_max_file_uploads(): int
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/* ------------------------------------------------------------------------ */
/* Records                                                                   */
/* ------------------------------------------------------------------------ */

function adm_list_load(string $name): array
{
    $out = [];
    foreach ((array) adm_content_load($name) as $r) {
        if (is_array($r) && isset($r['id']) && is_scalar($r['id']) && (string) $r['id'] !== '') {
            $r['id'] = slugify((string) $r['id']);
            $out[] = $r;
        }
    }
    return $out;
}

function adm_list_index(array $list, string $id): int
{
    foreach (array_values($list) as $i => $r) {
        if (is_array($r) && isset($r['id']) && slugify((string) $r['id']) === $id) {
            return $i;
        }
    }
    return -1;
}

function adm_list_ids(array $list, string $except = ''): array
{
    $ids = [];
    foreach ($list as $r) {
        if (is_array($r) && isset($r['id'])) {
            $id = slugify((string) $r['id']);
            if ($id !== $except) {
                $ids[] = $id;
            }
        }
    }
    return $ids;
}

/** @param mixed $data */
function adm_rev($data): string
{
    return substr(sha1((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
}

/**
 * Edit-conflict hash of one stored record (projects, stories, reports). The id is normalised
 * first, so a hand-written id such as "Masjid_Tor" hashes the same in the form (adm_list_load)
 * and under the save lock (raw JSON).
 */
function adm_rec_rev(array $record): string
{
    if (isset($record['id']) && is_scalar($record['id'])) {
        $record['id'] = slugify((string) $record['id']);
    }
    return adm_rev($record);
}

/** A posted string field ('' when missing or not a string). */
function adm_post_str(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

/** Id from the query string: '' (none), 'new', or a slug; anything else becomes '!' (not found). */
function adm_req_id(string $key = 'id'): string
{
    $v = isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : '';
    if ($v === '' || $v === 'new') {
        return $v;
    }
    return preg_match('/^[a-z0-9-]{1,60}$/', $v) ? $v : '!';
}

function adm_unique_slug(string $base, array $taken): string
{
    $base = trim(substr($base, 0, 54), '-');
    if ($base === '') {
        $base = 'item';
    }
    $slug = $base;
    $n = 2;
    while (in_array($slug, $taken, true)) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

function adm_slug_from_post(AdmInput $in, string $path, string $label, string $title, array $taken, string $current): string
{
    $raw = strtolower(trim(adm_clean($in->raw($path))));
    if ($raw === '') {
        if ($current !== '') {
            return $current;
        }
        return adm_unique_slug(slugify($title !== '' ? $title : bin2hex(random_bytes(4))), $taken);
    }
    $slug = trim((string) preg_replace('/[\s_]+/', '-', $raw), '-');
    $slug = (string) preg_replace('/-{2,}/', '-', $slug);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $in->error($path, '«' . $label . '»: استخدم حروفًا إنجليزية صغيرة (a-z) وأرقامًا وشرطة (-) فقط، مثل: ' . "\u{2066}masjid-el-tor\u{2069}" . '.');
        return $current;
    }
    if (strlen($slug) < 2 || strlen($slug) > 60) {
        $in->error($path, '«' . $label . '»: من 2 إلى 60 حرفًا.');
        return $current;
    }
    if ($slug !== $current && in_array($slug, $taken, true)) {
        $in->error($path, '«' . $label . '»: هذا الرابط مستخدم بالفعل؛ اختر رابطًا آخر.');
        return $current;
    }
    return $slug;
}

/* ------------------------------------------------------------------------ */
/* Options                                                                   */
/* ------------------------------------------------------------------------ */

function adm_activity_options(string $noneLabel = ''): array
{
    $out = $noneLabel !== '' ? ['' => $noneLabel] : [];
    foreach (bm_activities() as $slug => $a) {
        $out[$slug] = $a['num'] . ' · ' . ($a['name'] !== '' ? $a['name'] : $slug);
    }
    return $out;
}

function adm_activity_name(string $slug): string
{
    if ($slug === '' || !isset(bm_activity_registry()[$slug])) {
        return '';
    }
    $a = bm_activity($slug);
    return (string) ($a['name'] !== '' ? $a['name'] : $slug);
}

function adm_story_type_options(): array
{
    $out = [];
    foreach (array_keys(bm_story_types()) as $type) {
        $name = t('stories.types.' . $type . '.name');
        $out[$type] = is_string($name) && $name !== '' ? $name : $type;
    }
    return $out;
}

/** Icons editors may pick for a number card (all exist in app/lib/icons-data.php). */
function adm_stat_icons(): array
{
    return [
        'house' => 'بيت',
        'house-plus' => 'بيت جديد',
        'users' => 'أسر وأشخاص',
        'heart-pulse' => 'علاج',
        'mosque' => 'مسجد',
        'building-2' => 'مشروع / مبنى',
        'graduation-cap' => 'تعليم',
        'sprout' => 'تمكين اقتصادي',
        'droplets' => 'مياه',
        'soup' => 'طعام',
        'utensils' => 'وجبات',
        'shirt' => 'كسوة',
        'gem' => 'أفراح',
        'gift' => 'هدايا',
        'link-2-off' => 'فك كرب',
        'gavel' => 'مزاد',
        'hand-heart' => 'عطاء',
        'heart-handshake' => 'تكافل',
        'hand-coins' => 'دعم مالي',
        'hand-helping' => 'مساندة',
        'scale' => 'دعم قانوني',
        'hammer' => 'ترميم',
        'hard-hat' => 'إنشاءات',
        'store' => 'مشروع صغير',
        'package' => 'مساعدات عينية',
        'baby' => 'أطفال',
        'book-open' => 'كتب',
        'sun-snow' => 'مواسم',
        'badge-check' => 'إنجاز موثق',
    ];
}

/* ------------------------------------------------------------------------ */
/* Image lists                                                               */
/* ------------------------------------------------------------------------ */

function adm_img_key(array $img): string
{
    return substr(sha1((string) ($img['src'] ?? '')), 0, 12);
}

function adm_max_file_uploads(): int
{
    $n = (int) ini_get('max_file_uploads');
    return $n > 0 ? $n : 20;
}

function adm_has_arabic(string $s): bool
{
    return preg_match('/\p{Arabic}/u', $s) === 1;
}

/** Problem with an image description, or ''. */
function adm_alt_problem(string $alt): string
{
    if ($alt === '') {
        return 'اكتب وصفًا قصيرًا للصورة (النص البديل)؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.';
    }
    if (!adm_has_arabic($alt)) {
        return 'اكتب وصف الصورة باللغة العربية.';
    }
    if (bm_strlen($alt) > 250) {
        return 'وصف الصورة طويل جدًا (الحد الأقصى 250 حرفًا).';
    }
    return '';
}

function adm_images_editor(array $o): string
{
    $prefix = (string) ($o['prefix'] ?? 'imgs');
    $items = array_values((array) ($o['items'] ?? []));
    $max = (int) ($o['max'] ?? 40);
    $count = count($items);
    $room = max(0, $max - $count);
    $errors = AdmForm::$errors;
    $html = '<div class="adm-imgs" data-imgs>';

    if ($items) {
        $html .= '<p class="adm-hint">الترتيب هنا هو ترتيب الظهور على الموقع' . (!empty($o['cover_note']) ? '، والصورة الأولى هي ' . e((string) $o['cover_note']) : '') . '. استخدم «تقديم» و«تأخير» لتغيير الترتيب، ثم اضغط «حفظ».</p>';
        $html .= '<ol class="adm-imgs__list" role="list" data-img-list>';
        foreach ($items as $n => $img) {
            $img = array_replace(bm_image_defaults(), (array) $img);
            $key = adm_img_key($img);
            $base = $prefix . '.' . $key;
            $name = $prefix . '[' . $n . ']';
            $del = !empty($img['_del']);
            $thumb = (string) ($img['thumb'] !== '' ? $img['thumb'] : $img['src']);
            $pos = $n + 1;
            $html .= '<li class="adm-img' . ($del ? ' is-deleted' : '') . '" data-img-row>'
                . '<div class="adm-img__media">'
                . '<img src="' . e(adm_public_url($thumb)) . '" alt="" loading="lazy"'
                . ((int) $img['w'] > 0 && (int) $img['h'] > 0 ? ' width="' . (int) $img['w'] . '" height="' . (int) $img['h'] . '"' : '') . '>'
                . '<span class="adm-img__num" data-img-num aria-hidden="true">' . $pos . '</span>'
                . '<span class="adm-img__gone" aria-hidden="true">' . adm_icon('trash-2') . '<span>ستُحذف عند الحفظ</span></span>'
                . '</div>'
                . '<div class="adm-img__fields">'
                . '<input type="hidden" name="' . e($name) . '[k]" value="' . e($key) . '">'
                . adm_field([
                    'path' => $base . '.alt',
                    'name' => $name . '[alt]',
                    'label' => 'وصف الصورة ' . $pos,
                    'value' => (string) $img['alt'],
                    'max' => 250,
                    'error' => $errors[$base . '.alt'] ?? '',
                    'attrs' => ['data-img-label' => 'وصف الصورة'],
                ])
                . adm_field([
                    'path' => $base . '.caption',
                    'name' => $name . '[caption]',
                    'label' => 'تعليق تحت الصورة (اختياري)',
                    'value' => (string) $img['caption'],
                    'max' => 300,
                    'error' => $errors[$base . '.caption'] ?? '',
                ])
                . '</div>'
                . '<div class="adm-img__tools">'
                . '<button type="submit" class="adm-btn adm-btn--ghost adm-btn--sm" name="' . e($prefix) . '_move" value="' . e($key) . ':up" data-img-move="up" formnovalidate'
                . ' aria-label="تقديم الصورة ' . $pos . '"' . ($n === 0 ? ' disabled' : '') . '>' . adm_icon('arrow-right') . '<span>تقديم</span></button>'
                . '<button type="submit" class="adm-btn adm-btn--ghost adm-btn--sm" name="' . e($prefix) . '_move" value="' . e($key) . ':down" data-img-move="down" formnovalidate'
                . ' aria-label="تأخير الصورة ' . $pos . '"' . ($n === $count - 1 ? ' disabled' : '') . '>' . adm_icon('arrow-left') . '<span>تأخير</span></button>'
                . '<label class="adm-img__del"><input type="checkbox" name="' . e($name) . '[del]" value="1" data-img-delete' . ($del ? ' checked' : '') . '>'
                . '<span>حذف الصورة</span></label>'
                . '</div>'
                . '</li>';
        }
        $html .= '</ol><p class="sr-only" aria-live="polite" data-img-status></p>';
    } else {
        $html .= '<p class="adm-imgs__empty">' . adm_icon('camera') . '<span>' . e((string) ($o['empty'] ?? 'لا توجد صور بعد.')) . '</span></p>';
    }

    // New uploads.
    $fileName = $prefix . '_new';
    $id = adm_id($fileName);
    $html .= '<div class="adm-upload adm-upload--multi">';
    $html .= '<div class="adm-upload__label">' . adm_icon('upload') . '<span>' . e((string) ($o['upload_label'] ?? 'إضافة صور')) . '</span></div>';
    if (!adm_image_ready()) {
        $html .= adm_alert('رفع الصور غير متاح حاليًا لأن مكتبة معالجة الصور (GD) غير مفعّلة على الاستضافة. اطلب من الدعم الفني تفعيل إضافة GD لـ PHP.', 'warning');
    } elseif ($room === 0) {
        $html .= adm_alert('وصلت إلى الحد الأقصى (' . $max . ' صورة). احذف صورة لإضافة أخرى.', 'info');
    } else {
        $perRequest = min($room, adm_max_file_uploads());
        $hint = trim((string) ($o['hint'] ?? '') . ' ' . adm_upload_limits_text('image') . ' يمكنك اختيار حتى ' . adm_plural($perRequest, 'صورة واحدة', 'صورتين', 'صور', 'صورة') . ' في المرة الواحدة.');
        $html .= adm_field([
            'type' => 'file',
            'path' => $fileName,
            'name' => $fileName,
            'id' => $id,
            'label' => 'اختر صورة أو أكثر',
            'accept' => 'image/jpeg,image/png,image/webp',
            'multiple' => true,
            'hint' => $hint,
            'error' => $errors[$fileName] ?? '',
            'attrs' => [
                'data-preview' => $id . '-preview',
                'data-each' => $prefix,
                'data-max-files' => (string) $perRequest,
            ],
        ]);
        $html .= '<div class="adm-upload__preview adm-newimgs" id="' . e($id) . '-preview" hidden></div>';
        $html .= '<div class="adm-upload__shared" data-shared-alt>'
            . adm_field([
                'path' => $prefix . '_alt',
                'label' => 'وصف الصور الجديدة (نص بديل)',
                'value' => '',
                'max' => 250,
                'hint' => 'جملة قصيرة بالعربية تصف ما في الصورة، مثل: «أعمال صب سقف المسجد». مطلوب لكل صورة؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.',
                'error' => $errors[$prefix . '_alt'] ?? '',
            ])
            . adm_field([
                'path' => $prefix . '_caption',
                'label' => 'تعليق تحت الصور الجديدة (اختياري)',
                'value' => '',
                'max' => 300,
            ])
            . '</div>';
    }
    $html .= adm_privacy_reminder();
    $html .= '</div>';
    return $html . '</div>';
}

function adm_images_from_post(array $current, AdmInput $in, string $prefix): array
{
    $current = bm_images($current);
    $byKey = [];
    foreach ($current as $img) {
        $byKey[adm_img_key($img)] = $img;
    }
    $posted = $in->get($prefix, []);
    $seen = [];
    $view = [];
    $n = 0;
    foreach (is_array($posted) ? $posted : [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $k = isset($row['k']) && is_string($row['k']) ? $row['k'] : '';
        if (!isset($byKey[$k]) || isset($seen[$k])) {
            continue; // unknown key (stale form or tampering): never trusted
        }
        $seen[$k] = true;
        $n++;
        $img = $byKey[$k];
        $alt = isset($row['alt']) && is_string($row['alt']) ? adm_clean($row['alt']) : (string) $img['alt'];
        $cap = isset($row['caption']) && is_string($row['caption']) ? adm_clean($row['caption']) : (string) $img['caption'];
        $del = isset($row['del']) && $row['del'] === '1';
        if (!$del) {
            if (($p = adm_alt_problem($alt)) !== '') {
                $in->error($prefix . '.' . $k . '.alt', 'الصورة ' . $n . ': ' . $p);
            }
            if (bm_strlen($cap) > 300) {
                $in->error($prefix . '.' . $k . '.caption', 'الصورة ' . $n . ': التعليق طويل جدًا (الحد الأقصى 300 حرف).');
            }
        }
        $img['alt'] = bm_substr($alt, 0, 250);
        $img['caption'] = bm_substr($cap, 0, 300);
        $view[] = $img + ['_del' => $del];
    }
    // Images that were not in the form (e.g. added by a colleague meanwhile) are kept, at the end.
    foreach ($byKey as $k => $img) {
        if (!isset($seen[$k])) {
            $view[] = $img + ['_del' => false];
        }
    }
    // Reorder without JavaScript: «تقديم» / «تأخير» are submit buttons.
    $move = $in->raw($prefix . '_move');
    if (preg_match('/^([a-f0-9]{12}):(up|down)$/', $move, $m)) {
        foreach ($view as $i => $v) {
            if (adm_img_key($v) === $m[1]) {
                $j = $m[2] === 'up' ? $i - 1 : $i + 1;
                if ($j >= 0 && $j < count($view)) {
                    $tmp = $view[$j];
                    $view[$j] = $view[$i];
                    $view[$i] = $tmp;
                }
                break;
            }
        }
    }
    $images = [];
    $removed = [];
    foreach ($view as $v) {
        $rec = $v;
        unset($rec['_del']);
        if (!empty($v['_del'])) {
            $removed[] = $rec;
        } else {
            $images[] = $rec;
        }
    }
    return ['images' => $images, 'removed' => $removed, 'view' => $view];
}

function adm_images_pending(AdmInput $in, string $prefix, int $room): array
{
    $files = adm_files($prefix . '_new');
    if (!$files) {
        return [];
    }
    if (!adm_image_ready()) {
        $in->error($prefix . '_new', 'رفع الصور غير متاح حاليًا لأن مكتبة معالجة الصور (GD) غير مفعّلة على الاستضافة.');
        return [];
    }
    if (count($files) > $room) {
        $in->error($prefix . '_new', $room > 0
            ? 'اخترت ' . adm_plural(count($files), 'صورة واحدة', 'صورتين', 'صور', 'صورة') . '، ويمكن إضافة '
                . adm_plural($room, 'صورة واحدة', 'صورتين', 'صور', 'صورة') . ' فقط قبل الوصول إلى الحد الأقصى.'
            : 'وصلت إلى الحد الأقصى لعدد الصور. احذف صورة لإضافة أخرى.');
        return [];
    }
    $sharedAlt = adm_clean($in->raw($prefix . '_alt'));
    $sharedCap = adm_clean($in->raw($prefix . '_caption'));
    $out = [];
    $n = 0;
    foreach ($files as $i => $file) {
        $n++;
        $alt = adm_clean($in->raw($prefix . '_alt_each.' . $i));
        $cap = adm_clean($in->raw($prefix . '_caption_each.' . $i));
        $alt = $alt !== '' ? $alt : $sharedAlt;
        $cap = $cap !== '' ? $cap : $sharedCap;
        // Files that are not images fail later with the precise reason (type, size…), without a description being demanded.
        if (!adm_upload_looks_like_image($file)) {
            $alt = $alt !== '' ? $alt : '—';
        } elseif (($p = adm_alt_problem($alt)) !== '') {
            $in->error($prefix . '_alt', 'الصورة الجديدة ' . $n . ': ' . $p);
        }
        if (bm_strlen($cap) > 300) {
            $in->error($prefix . '_caption', 'الصورة الجديدة ' . $n . ': التعليق طويل جدًا (الحد الأقصى 300 حرف).');
        }
        $out[] = [
            'file' => $file,
            'alt' => $alt,
            'caption' => bm_substr($cap, 0, 300),
            'n' => $n,
            'name' => bm_substr(adm_clean((string) ($file['name'] ?? '')), 0, 60),
        ];
    }
    return $out;
}

/** Quick check before validation: an uploaded JPEG/PNG/WebP (the real checks run in adm_image_upload). */
function adm_upload_looks_like_image(array $file): bool
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return false;
    }
    $info = @getimagesize((string) $file['tmp_name']);
    $ok = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
    if (defined('IMAGETYPE_WEBP')) {
        $ok[] = IMAGETYPE_WEBP;
    }
    return is_array($info) && in_array($info[2], $ok, true);
}

/**
 * Invisible first submit button of a form that also contains «تقديم/تأخير» submit buttons:
 * pressing Enter in a text field then saves the form instead of moving an image.
 */
function adm_enter_submit(): string
{
    return '<button type="submit" class="adm-enter-submit" tabindex="-1" aria-hidden="true">حفظ</button>';
}

function adm_images_process(array $pending): array
{
    $added = [];
    $failed = [];
    foreach ($pending as $p) {
        try {
            $added[] = adm_image_upload($p['file'], ['alt' => $p['alt'], 'caption' => $p['caption']]);
        } catch (AdmUserError $e) {
            $failed[] = 'الصورة ' . $p['n'] . ($p['name'] !== '' ? ' («' . $p['name'] . '»)' : '') . ': ' . $e->getMessage();
        }
    }
    return ['added' => $added, 'failed' => $failed];
}

/** Arabic note for the editor when new files were chosen but the form had errors. */
function adm_images_reselect(AdmInput $in, string $prefix): void
{
    if (adm_files($prefix . '_new') && !$in->ok()) {
        $in->error($prefix . '_new', 'لم تُرفع الصور التي اخترتها بعد. صحّح الأخطاء ثم اختر الصور مرة أخرى.');
    }
}

/* ------------------------------------------------------------------------ */
/* Files                                                                     */
/* ------------------------------------------------------------------------ */

function adm_image_paths(array $images): array
{
    $out = [];
    foreach ($images as $img) {
        if (is_string($img)) {
            $img = ['src' => $img];
        }
        foreach (['src', 'thumb'] as $k) {
            if (is_array($img) && !empty($img[$k]) && is_string($img[$k])) {
                $out[] = $img[$k];
            }
        }
    }
    return array_values(array_unique($out));
}

/** Every "uploads/…" string in the content files (projects, stories, galleries, reports, settings…). */
function adm_uploads_referenced(): array
{
    $refs = [];
    $walk = function ($node) use (&$walk, &$refs) {
        if (is_string($node)) {
            $s = ltrim(str_replace('\\', '/', $node), '/');
            if (strpos($s, 'uploads/') === 0) {
                $refs[$s] = true;
            }
        } elseif (is_array($node)) {
            foreach ($node as $v) {
                $walk($v);
            }
        }
    };
    foreach (array_keys(adm_content_files()) as $name) {
        if ($name === 'texts') {
            continue;
        }
        $walk(adm_content_load($name));
    }
    return $refs;
}

/** Delete uploaded files that no content references any more (call after a successful save). */
function adm_delete_unreferenced(array $paths): int
{
    if (!$paths) {
        return 0;
    }
    try {
        $refs = adm_uploads_referenced();
    } catch (Throwable $e) {
        return 0; // when in doubt, keep the files
    }
    $n = 0;
    foreach (array_unique($paths) as $p) {
        $p = ltrim(str_replace('\\', '/', (string) $p), '/');
        if ($p !== '' && !isset($refs[$p]) && adm_delete_upload($p)) {
            $n++;
        }
    }
    return $n;
}
