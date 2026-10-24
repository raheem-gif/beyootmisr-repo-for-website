<?php
/**
 * Uploads: images (JPEG/PNG/WebP → re-encoded JPEG, metadata stripped) and PDF reports.
 *
 * Privacy (content document §12/§13): re-encoding with GD removes EXIF/GPS and
 * any other metadata from photos. SVG is never accepted (it can carry scripts).
 * Client file names are never used: files get random hex names.
 *
 *   adm_files(string $field): array
 *       Normalised list of uploaded files for an <input type=file name="field"> or name="field[]"
 *       (skips empty slots). Each item: {name, type, tmp_name, error, size}.
 *   adm_file_present(?array $file): bool          a file was chosen (even if it failed)
 *
 *   adm_image_upload(array $file, array $o = []): array
 *       Validates (≤10 MB, getimagesize + finfo, jpg/png/webp only, ≤40 MP), applies EXIF
 *       orientation, resizes to max 1600 px wide (+ a 640 px thumbnail), saves JPEG q82 into
 *       uploads/images/<random>.jpg (+ <random>-640.jpg). Returns an image record
 *       {src, thumb, w, h, alt, caption}. Options:
 *         alt (string, required unless 'require_alt' => false), caption,
 *         max_w (1600), max_h (2400), thumb_w (640, 0 = no thumbnail), quality (82),
 *         keep_alpha (false: PNG output with transparency — used for the logo), prefix ('').
 *       Throws AdmUserError with an Arabic message.
 *   adm_image_process(string $path, array $o = []): array   same, for a file already on disk
 *   adm_pdf_upload(array $file, array $o = []): array
 *       finfo application/pdf + "%PDF-" magic, ≤15 MB → uploads/reports/<random>.pdf.
 *       Returns {file, size}.
 *   adm_delete_upload(string $rel): bool          delete a file under uploads/images or uploads/reports
 *                                                 (realpath prefix check — no traversal)
 *   adm_delete_image(array $img): void            delete src + thumb of an image record
 *   adm_image_ready(): bool                        GD can decode/encode what we accept
 *   adm_upload_limits_text(): string               "حتى 10 ميجابايت…" for hints (respects php.ini limits)
 *   adm_privacy_reminder(): string                 HTML reminder shown next to every upload field
 */
declare(strict_types=1);
defined('BM_APP') || exit;

const ADM_IMAGE_MAX_BYTES = 10485760;  // 10 MB
const ADM_PDF_MAX_BYTES = 15728640;    // 15 MB
const ADM_IMAGE_MAX_PIXELS = 40000000; // 40 MP (memory safety on shared hosting)

function adm_files(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || !isset($f['error'])) {
        return [];
    }
    $out = [];
    if (is_array($f['error'])) {
        foreach (array_keys($f['error']) as $i) {
            if (is_array($f['error'][$i])) {
                continue; // deeper nesting is not used by the admin forms
            }
            $item = [
                'name' => (string) ($f['name'][$i] ?? ''),
                'type' => (string) ($f['type'][$i] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
                'error' => (int) $f['error'][$i],
                'size' => (int) ($f['size'][$i] ?? 0),
            ];
            if ($item['error'] !== UPLOAD_ERR_NO_FILE) {
                $out[$i] = $item;
            }
        }
        return $out;
    }
    if ((int) $f['error'] === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    return [[
        'name' => (string) $f['name'],
        'type' => (string) $f['type'],
        'tmp_name' => (string) $f['tmp_name'],
        'error' => (int) $f['error'],
        'size' => (int) $f['size'],
    ]];
}

function adm_file_present(?array $file): bool
{
    return $file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function adm_upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'حجم الملف أكبر من الحد الذي تسمح به الاستضافة (' . adm_bytes(adm_ini_bytes((string) ini_get('upload_max_filesize'))) . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'لم يكتمل رفع الملف. تحقق من الاتصال وأعد المحاولة.';
        case UPLOAD_ERR_NO_FILE:
            return 'لم يتم اختيار ملف.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'تعذّر حفظ الملف المؤقت على الاستضافة. تواصل مع الدعم الفني للاستضافة.';
        case UPLOAD_ERR_EXTENSION:
            return 'رفضت إعدادات الاستضافة هذا الملف.';
    }
    return 'تعذّر رفع الملف.';
}

function adm_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' ميجابايت';
    }
    if ($bytes >= 1024) {
        return (int) round($bytes / 1024) . ' كيلوبايت';
    }
    return $bytes . ' بايت';
}

/** Effective maximum upload size: our limit capped by php.ini. */
function adm_upload_max(int $ours): int
{
    $limits = [$ours];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $v = adm_ini_bytes((string) ini_get($k));
        if ($v > 0) {
            $limits[] = $v;
        }
    }
    return min($limits);
}

function adm_upload_limits_text(string $kind = 'image'): string
{
    if ($kind === 'pdf') {
        return 'ملف PDF فقط، حتى ' . adm_bytes(adm_upload_max(ADM_PDF_MAX_BYTES)) . '.';
    }
    return 'صور JPG أو PNG أو WebP، حتى ' . adm_bytes(adm_upload_max(ADM_IMAGE_MAX_BYTES)) . ' للصورة.';
}

function adm_image_ready(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagejpeg')
        && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng')
        && function_exists('getimagesize');
}

function adm_finfo_mime(string $path): string
{
    if (!function_exists('finfo_open')) {
        return '';
    }
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if (!$fi) {
        return '';
    }
    $mime = (string) finfo_file($fi, $path);
    finfo_close($fi);
    return strtolower($mime);
}

/** Basic checks shared by images and PDFs. */
function adm_upload_check(array $file, int $maxBytes): string
{
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new AdmUserError(adm_upload_error_message((int) $file['error']));
    }
    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp) || !is_file($tmp)) {
        throw new AdmUserError('تعذّر رفع الملف.');
    }
    $size = (int) filesize($tmp);
    if ($size <= 0) {
        throw new AdmUserError('الملف فارغ.');
    }
    if ($size > $maxBytes) {
        throw new AdmUserError('حجم الملف (' . adm_bytes($size) . ') أكبر من الحد المسموح (' . adm_bytes($maxBytes) . ').');
    }
    return $tmp;
}

function adm_random_name(): string
{
    return bin2hex(random_bytes(8));
}

function adm_upload_dir(string $kind): string
{
    $dir = BM_PUBLIC . '/uploads/' . ($kind === 'pdf' ? 'reports' : 'images');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new AdmUserError(adm_msg_unwritable(dirname($dir)));
    }
    if (!is_writable($dir)) {
        throw new AdmUserError(adm_msg_unwritable($dir));
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', "<!doctype html><title>403</title>\n");
    }
    return $dir;
}

/* ------------------------------------------------------------------------ */
/* Images                                                                    */
/* ------------------------------------------------------------------------ */

function adm_image_upload(array $file, array $o = []): array
{
    if (!adm_image_ready()) {
        throw new AdmUserError('رفع الصور غير متاح حاليًا لأن مكتبة معالجة الصور (GD) غير مفعّلة على الاستضافة. اطلب من الدعم الفني تفعيل إضافة GD لـ PHP.');
    }
    if (!empty($o['require_alt'] ?? true) && adm_clean((string) ($o['alt'] ?? '')) === '') {
        throw new AdmUserError('اكتب وصفًا قصيرًا للصورة (النص البديل) قبل رفعها؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.');
    }
    return adm_image_process(adm_upload_check($file, ADM_IMAGE_MAX_BYTES), $o);
}

/**
 * Validate + re-encode an image file already on disk (used by adm_image_upload; also
 * usable for files that did not come through an HTTP upload, e.g. in tests).
 */
function adm_image_process(string $tmp, array $o = []): array
{
    $o += [
        'alt' => '',
        'caption' => '',
        'require_alt' => true,
        'max_w' => 1600,
        'max_h' => 2400,
        'thumb_w' => 640,
        'quality' => 82,
        'keep_alpha' => false,
        'prefix' => '',
    ];
    if (!adm_image_ready()) {
        throw new AdmUserError('رفع الصور غير متاح حاليًا لأن مكتبة معالجة الصور (GD) غير مفعّلة على الاستضافة. اطلب من الدعم الفني تفعيل إضافة GD لـ PHP.');
    }
    $alt = adm_clean((string) $o['alt']);
    if ($o['require_alt'] && $alt === '') {
        throw new AdmUserError('اكتب وصفًا قصيرًا للصورة (النص البديل) قبل رفعها؛ يقرؤه قارئ الشاشة لمن لا يرى الصورة.');
    }
    if (!is_file($tmp) || (int) filesize($tmp) > ADM_IMAGE_MAX_BYTES) {
        throw new AdmUserError('حجم الملف أكبر من الحد المسموح (' . adm_bytes(ADM_IMAGE_MAX_BYTES) . ').');
    }

    $info = @getimagesize($tmp);
    $mime = adm_finfo_mime($tmp);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png'];
    if (defined('IMAGETYPE_WEBP')) {
        $types[IMAGETYPE_WEBP] = 'image/webp';
    }
    if (!is_array($info) || !isset($types[$info[2]]) || ($mime !== '' && $mime !== $types[$info[2]])) {
        throw new AdmUserError('نوع الملف غير مسموح. ارفع صورة بصيغة JPG أو PNG أو WebP فقط (ملفات SVG وPDF والمستندات غير مقبولة هنا).');
    }
    [$w, $h, $type] = $info;
    if ($w < 1 || $h < 1) {
        throw new AdmUserError('تعذّرت قراءة أبعاد الصورة.');
    }
    if ($w * $h > ADM_IMAGE_MAX_PIXELS) {
        throw new AdmUserError('أبعاد الصورة كبيرة جدًا (' . $w . '×' . $h . '). صغّرها إلى أقل من 6000 بكسل في العرض ثم أعد رفعها.');
    }
    adm_raise_memory((int) ($w * $h * 5 + 64 * 1048576));

    $src = adm_gd_open($tmp, (int) $type);
    if ($type === IMAGETYPE_JPEG) {
        $src = adm_gd_orient($src, $tmp);
    }
    $dir = adm_upload_dir('image');
    $base = ($o['prefix'] !== '' ? adm_clean_slug((string) $o['prefix'], 20) . '-' : '') . adm_random_name();
    $ext = $o['keep_alpha'] ? 'png' : 'jpg';

    $written = [];
    try {
        $main = adm_gd_fit($src, (int) $o['max_w'], (int) $o['max_h'], !$o['keep_alpha']);
        $mainW = imagesx($main);
        $mainH = imagesy($main);
        $written[] = $mainFile = $dir . '/' . $base . '.' . $ext;
        adm_gd_save($main, $mainFile, $ext, (int) $o['quality']);

        $thumbRel = 'uploads/images/' . $base . '.' . $ext;
        if ((int) $o['thumb_w'] > 0 && $mainW > (int) $o['thumb_w']) {
            $thumb = adm_gd_fit($main, (int) $o['thumb_w'], 100000, !$o['keep_alpha']);
            $written[] = $thumbFile = $dir . '/' . $base . '-' . (int) $o['thumb_w'] . '.' . $ext;
            adm_gd_save($thumb, $thumbFile, $ext, (int) $o['quality']);
            if ($thumb !== $main) {
                adm_gd_free($thumb);
            }
            $thumbRel = 'uploads/images/' . basename($thumbFile);
        }
        if ($main !== $src) {
            adm_gd_free($main);
        }
    } catch (Throwable $e) {
        foreach ($written as $f) {
            @unlink($f);
        }
        throw $e instanceof AdmUserError ? $e : new AdmUserError('تعذّرت معالجة الصورة. جرّب صورة أخرى أو احفظها بصيغة JPG ثم أعد رفعها.', $e->getMessage());
    } finally {
        adm_gd_free($src);
    }

    return [
        'src' => 'uploads/images/' . $base . '.' . $ext,
        'thumb' => $thumbRel,
        'w' => $mainW,
        'h' => $mainH,
        'alt' => bm_substr($alt, 0, 250),
        'caption' => bm_substr(adm_clean((string) $o['caption']), 0, 300),
    ];
}

/** imagedestroy() is a no-op since PHP 8 (and deprecated in 8.5). @param resource|\GdImage $im */
function adm_gd_free($im): void
{
    if (PHP_VERSION_ID < 80000 && is_resource($im)) {
        imagedestroy($im);
    }
}

function adm_raise_memory(int $needed): void
{
    $limit = adm_ini_bytes((string) ini_get('memory_limit'));
    if ($limit > 0 && $limit < $needed && function_exists('ini_set')) {
        @ini_set('memory_limit', (string) min(max($needed, 256 * 1048576), 512 * 1048576));
    }
}

/** @return resource|\GdImage */
function adm_gd_open(string $path, int $type)
{
    $im = false;
    if ($type === IMAGETYPE_JPEG) {
        $im = @imagecreatefromjpeg($path);
    } elseif ($type === IMAGETYPE_PNG) {
        $im = @imagecreatefrompng($path);
    } elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) {
        if (!function_exists('imagecreatefromwebp')) {
            throw new AdmUserError('الاستضافة لا تدعم صور WebP. احفظ الصورة بصيغة JPG أو PNG ثم أعد رفعها.');
        }
        $im = @imagecreatefromwebp($path);
    }
    if (!$im) {
        throw new AdmUserError('الملف ليس صورة سليمة أو أنه تالف. جرّب صورة أخرى.');
    }
    if (function_exists('imageistruecolor') && !imageistruecolor($im) && function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($im);
    }
    return $im;
}

/**
 * Apply the EXIF orientation of phone photos (the pixels are rotated; the tag
 * itself disappears with re-encoding).
 * @param resource|\GdImage $im
 * @return resource|\GdImage
 */
function adm_gd_orient($im, string $path)
{
    if (!function_exists('exif_read_data')) {
        return $im;
    }
    $exif = @exif_read_data($path);
    $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    if ($o < 2 || $o > 8) {
        return $im;
    }
    $rotate = function ($img, int $deg) {
        $r = imagerotate($img, $deg, 0);
        if ($r === false) {
            return $img;
        }
        adm_gd_free($img);
        return $r;
    };
    switch ($o) {
        case 2:
            imageflip($im, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $im = $rotate($im, 180);
            break;
        case 4:
            imageflip($im, IMG_FLIP_VERTICAL);
            break;
        case 5:
            $im = $rotate($im, -90);
            imageflip($im, IMG_FLIP_HORIZONTAL);
            break;
        case 6:
            $im = $rotate($im, -90);
            break;
        case 7:
            $im = $rotate($im, 90);
            imageflip($im, IMG_FLIP_HORIZONTAL);
            break;
        case 8:
            $im = $rotate($im, 90);
            break;
    }
    return $im;
}

/**
 * Scale down to fit max width/height (never up). Always returns a new image
 * when $flatten (white background for JPEG) or when resizing.
 * @param resource|\GdImage $src
 * @return resource|\GdImage
 */
function adm_gd_fit($src, int $maxW, int $maxH, bool $flatten)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) {
        throw new RuntimeException('imagecreatetruecolor failed');
    }
    if ($flatten) {
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    } else {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $clear);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** @param resource|\GdImage $im */
function adm_gd_save($im, string $file, string $ext, int $quality): void
{
    $tmp = dirname($file) . '/.up-' . adm_random_name() . '.' . $ext;
    if ($ext === 'png') {
        $ok = imagepng($im, $tmp, 8);
    } else {
        // Progressive JPEG. (Signature differs: int in PHP 7, ?bool in PHP 8 — strict_types is on.)
        if (PHP_VERSION_ID >= 80000) {
            imageinterlace($im, true);
        } else {
            imageinterlace($im, 1);
        }
        $ok = imagejpeg($im, $tmp, max(40, min(95, $quality)));
    }
    if (!$ok || !@rename($tmp, $file)) {
        @unlink($tmp);
        throw new AdmUserError(adm_msg_unwritable(dirname($file)));
    }
    if (function_exists('chmod')) {
        @chmod($file, 0644);
    }
}

/* ------------------------------------------------------------------------ */
/* PDF reports                                                               */
/* ------------------------------------------------------------------------ */

function adm_pdf_upload(array $file, array $o = []): array
{
    $tmp = adm_upload_check($file, ADM_PDF_MAX_BYTES);
    $mime = adm_finfo_mime($tmp);
    $h = @fopen($tmp, 'rb');
    $magic = $h ? (string) fread($h, 5) : '';
    if ($h) {
        fclose($h);
    }
    if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true) || $magic !== '%PDF-') {
        throw new AdmUserError('الملف ليس بصيغة PDF. صدّر التقرير بصيغة PDF ثم أعد رفعه.');
    }
    $dir = adm_upload_dir('pdf');
    $name = adm_random_name() . '.pdf';
    $dest = $dir . '/' . $name;
    if (!@move_uploaded_file($tmp, $dest)) {
        throw new AdmUserError(adm_msg_unwritable($dir));
    }
    if (function_exists('chmod')) {
        @chmod($dest, 0644);
    }
    return ['file' => 'uploads/reports/' . $name, 'size' => (int) filesize($dest)];
}

/* ------------------------------------------------------------------------ */
/* Deleting                                                                  */
/* ------------------------------------------------------------------------ */

/** Delete an uploaded file given as "uploads/images/…" or "uploads/reports/…". */
function adm_delete_upload(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if (!preg_match('#^uploads/(images|reports)/[A-Za-z0-9._-]+$#', $rel) || strpos($rel, '..') !== false) {
        return false;
    }
    $path = realpath(BM_PUBLIC . '/' . $rel);
    if ($path === false || !is_file($path) || basename($path) === 'index.html') {
        return false;
    }
    $ok = false;
    foreach (['images', 'reports'] as $d) {
        $root = realpath(BM_PUBLIC . '/uploads/' . $d);
        if ($root !== false && strpos($path, $root . DIRECTORY_SEPARATOR) === 0) {
            $ok = true;
        }
    }
    return $ok && @unlink($path);
}

function adm_delete_image(array $img): void
{
    foreach (['src', 'thumb'] as $k) {
        if (!empty($img[$k]) && is_string($img[$k])) {
            adm_delete_upload($img[$k]);
        }
    }
}

/** Privacy reminder shown next to every upload field (content document §12, §13). */
function adm_privacy_reminder(): string
{
    return '<div class="adm-privacy" role="note">' . adm_icon('shield-alert')
        . '<div><strong>تذكير الخصوصية قبل الرفع</strong>'
        . '<p>لا ترفع صور بطاقات الرقم القومي أو المستندات أو التقارير الطبية، ولا صورًا تظهر فيها وجوه أو أسماء أو عناوين '
        . 'يمكن أن تكشف هوية المستفيدين، إلا بموافقة واضحة ومسوّغ مناسب. تُزال بيانات الموقع الجغرافي (GPS) وبيانات '
        . 'الكاميرا من الصور تلقائيًا عند الرفع.</p></div></div>';
}
