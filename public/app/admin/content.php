<?php
/**
 * Content saving: validate → backup → write → rebuild the static site.
 *
 *   adm_content_files(): array                      name => Arabic label (texts, settings, stats, …)
 *   adm_content_load(string $name)                  fresh decoded JSON (bypasses the build cache)
 *   adm_content_save(string $name, callable $mutate, string $action, string $detail = ''): array
 *       Runs under a lock: re-reads the file, calls $mutate($current) which returns the new data
 *       (throw AdmUserError for validation problems), backs the old file up, writes the new JSON,
 *       rebuilds the site. If the build throws, the previous file is restored, the site is rebuilt
 *       and an AdmUserError explains what happened. Writes an audit entry on success.
 *       Returns ['changed' => bool, 'report' => ?array]. Unchanged data → nothing written or built.
 *       $detail may also be a callable($old, $new): string to describe the change for the audit log.
 *   adm_rebuild(string $reason = ''): array         bm_build() + build status (throws AdmUserError)
 *   adm_build_status(): array                       last build {t,u,ok,pages,written,removed,warnings,error,reason}
 *   adm_build_summary(array $report): string        Arabic one-liner ("أُعيد بناء 22 صفحة…")
 *   adm_backups(string $name): array                automatic backups, newest first [{id,t,size}]
 *   adm_backup_read(string $name, string $id): ?string
 *   adm_require_writable(string $path): void        AdmUserError naming the folder when not writable
 *   adm_health(): array                             environment checks for the dashboard
 */
declare(strict_types=1);
defined('BM_APP') || exit;

const ADM_BACKUPS_KEEP = 30;

/** Message safe to show to editors (Arabic). */
class AdmUserError extends RuntimeException
{
    /** @var string technical details shown in a collapsible block */
    public $details = '';

    public function __construct(string $message, string $details = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->details = $details;
    }
}

function adm_content_files(): array
{
    return [
        'texts' => 'النصوص',
        'settings' => 'الإعدادات',
        'stats' => 'أثرنا بالأرقام',
        'projects' => 'المشروعات',
        'stories' => 'الحكايات',
        'reports' => 'التقارير',
        'galleries' => 'معرض صور الأنشطة',
    ];
}

/** File that saves go to: the guarded app/content/<name>.json.php. */
function adm_content_path(string $name): string
{
    if (!isset(adm_content_files()[$name])) {
        throw new RuntimeException('Unknown content file: ' . $name);
    }
    return bm_content_file($name);
}

/** File to read: the guarded file, or a legacy plain <name>.json left from an older upload. */
function adm_content_read_path(string $name): string
{
    adm_content_path($name); // validates the name
    return bm_content_source($name);
}

/** @return mixed decoded JSON ([] when the file is missing or empty) */
function adm_content_decode(string $name, ?string $raw)
{
    if ($raw !== null) {
        $raw = bm_json_strip_guard($raw);
    }
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new AdmUserError(
            'ملف المحتوى «' . adm_content_files()[$name] . '» تالف ولا يمكن قراءته. استرجع نسخة سابقة من صفحة «نسخة احتياطية».',
            $name . '.json: ' . json_last_error_msg()
        );
    }
    return $data;
}

/** @return mixed */
function adm_content_load(string $name)
{
    $file = adm_content_read_path($name);
    $raw = is_file($file) ? @file_get_contents($file) : null;
    if ($raw === false) {
        throw new AdmUserError('تعذّرت قراءة الملف «' . adm_rel($file) . '». تحقق من صلاحياته في «مدير الملفات».');
    }
    return adm_content_decode($name, $raw);
}

/** Throws an AdmUserError naming the folder (or file) that the hosting does not let PHP write. */
function adm_require_writable(string $path): void
{
    if (file_exists($path)) {
        if (!is_writable($path)) {
            throw new AdmUserError(adm_msg_unwritable($path));
        }
        return;
    }
    if (!is_writable(dirname($path))) {
        throw new AdmUserError(adm_msg_unwritable(dirname($path)));
    }
}

/**
 * @param callable        $mutate  function ($current) { return $new; }
 * @param string|callable $detail  audit text, or function ($old, $new): string
 */
function adm_content_save(string $name, callable $mutate, string $action, $detail = ''): array
{
    $file = adm_content_path($name);
    adm_require_writable(BM_CONTENT);
    if (is_file($file)) {
        adm_require_writable($file);
    }
    return adm_with_lock('content', function () use ($name, $file, $mutate, $action, $detail) {
        $source = adm_content_read_path($name);
        $legacy = $source !== $file;
        $raw = is_file($source) ? @file_get_contents($source) : null;
        if ($raw === false) {
            throw new AdmUserError('تعذّرت قراءة الملف «' . adm_rel($source) . '». تحقق من صلاحياته في «مدير الملفات».');
        }
        $current = adm_content_decode($name, $raw);
        $new = $mutate($current);
        if ($new === $current) {
            return ['changed' => false, 'report' => null];
        }
        if ($raw !== null) {
            adm_backup_write($name, bm_json_strip_guard($raw));
        }
        bm_json_write($file, $new);
        try {
            $report = adm_rebuild($action);
        } catch (AdmUserError $e) {
            // Put the previous content back and rebuild so the public site stays consistent.
            if ($raw === null || $legacy) {
                @unlink($file);
            } else {
                bm_write_file($file, $raw);
            }
            try {
                bm_content_reset();
                bm_build();
            } catch (Throwable $e2) {
                // The original error is the useful one.
            }
            bm_content_reset();
            throw new AdmUserError(
                'لم يُحفظ التعديل: تعذّر تحديث صفحات الموقع بالمحتوى الجديد، فأُعيد المحتوى السابق كما كان. '
                . 'إن تكرر ذلك فأرسل «التفاصيل التقنية» إلى المطوّر.',
                $e->details
            );
        }
        if ($legacy) {
            // The plain .json could be downloadable on hosts that ignore .htaccess.
            @unlink($source);
        }
        $text = is_callable($detail) ? (string) $detail($current, $new) : (string) $detail;
        adm_audit($action, $text);
        return ['changed' => true, 'report' => $report];
    });
}

/** Rebuild the public site. Records the result for the dashboard. */
function adm_rebuild(string $reason = ''): array
{
    $user = $GLOBALS['adm_user'] ?? null;
    $status = ['t' => time(), 'u' => $user !== null ? adm_user_label($user) : '', 'reason' => $reason];
    @set_time_limit(120);
    try {
        bm_content_reset();
        $report = bm_build();
        bm_content_reset();
    } catch (Throwable $e) {
        bm_content_reset();
        $details = adm_error_details($e);
        adm_build_status_write($status + ['ok' => false, 'pages' => 0, 'written' => 0, 'removed' => 0, 'warnings' => [], 'error' => $details]);
        adm_log_error($e);
        // bm_write_file() reports permission problems as "Cannot … : /path".
        if (preg_match('/^Cannot (?:write|replace|create)[^:]*: (.+)$/', $e->getMessage(), $m)) {
            $target = $m[1];
            throw new AdmUserError(adm_msg_unwritable(is_dir($target) ? $target : dirname($target)), $details, $e);
        }
        throw new AdmUserError('حدث خطأ أثناء بناء صفحات الموقع.', $details, $e);
    }
    adm_build_status_write($status + [
        'ok' => true,
        'pages' => (int) $report['pages'],
        'written' => count($report['written']),
        'removed' => count($report['removed']),
        'warnings' => array_slice(array_values($report['warnings']), 0, 20),
        'error' => '',
    ]);
    return $report;
}

function adm_build_status_write(array $status): void
{
    try {
        adm_store_json_write(adm_storage('build-status.php'), $status);
    } catch (Throwable $e) {
        // Reported by the health check.
    }
}

function adm_build_status(): array
{
    return (array) adm_store_json_read(adm_storage('build-status.php'), []);
}

function adm_build_summary(array $report): string
{
    $msg = 'أُعيد بناء الموقع: ' . (int) $report['pages'] . ' صفحة';
    $w = count($report['written'] ?? []);
    $msg .= $w > 0 ? '، وتحدّث ' . $w . ' ملف.' : '، ولم تتغير أي صفحة منشورة.';
    return $msg;
}

/** "Error rendering about.html: …" plus file:line of the root cause, for developers. */
function adm_error_details(Throwable $e): string
{
    $out = get_class($e) . ': ' . $e->getMessage();
    $p = $e->getPrevious();
    while ($p !== null) {
        $out .= "\n← " . get_class($p) . ': ' . $p->getMessage() . ' (' . adm_rel($p->getFile()) . ':' . $p->getLine() . ')';
        $p = $p->getPrevious();
    }
    if ($e->getPrevious() === null) {
        $out .= ' (' . adm_rel($e->getFile()) . ':' . $e->getLine() . ')';
    }
    return $out;
}

function adm_log_error(Throwable $e): void
{
    try {
        adm_store_lines_append(adm_storage('errors.php'), [
            't' => date('c'),
            'e' => bm_substr(adm_error_details($e), 0, 2000),
            'p' => (string) ($_GET['p'] ?? ''),
        ]);
    } catch (Throwable $e2) {
        // Nothing more we can do.
    }
}

/* ------------------------------------------------------------------------ */
/* Automatic backups (before each save)                                      */
/* ------------------------------------------------------------------------ */

function adm_backup_dir(string $name): string
{
    adm_content_path($name); // validates the name
    return adm_storage_dir('backups/' . $name);
}

function adm_backup_write(string $name, string $raw): void
{
    $dir = adm_backup_dir($name);
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    adm_write_protected($dir . '/' . $id . '.json.php', ADM_GUARD . $raw);
    $all = adm_backups($name);
    foreach (array_slice($all, ADM_BACKUPS_KEEP) as $old) {
        @unlink($dir . '/' . $old['id'] . '.json.php');
    }
}

/** @return array[] newest first: {id, t, size} */
function adm_backups(string $name): array
{
    $dir = adm_storage('backups/' . $name);
    if (!isset(adm_content_files()[$name]) || !is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (glob($dir . '/*.json.php') ?: [] as $f) {
        $id = basename($f, '.json.php');
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-[a-f0-9]{6}$/', $id, $m)) {
            continue;
        }
        $t = mktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
        $out[] = ['id' => $id, 't' => $t ?: 0, 'size' => max(0, (int) @filesize($f) - strlen(ADM_GUARD))];
    }
    usort($out, function ($a, $b) {
        return strcmp($b['id'], $a['id']);
    });
    return $out;
}

/** Raw JSON of one backup (or null). The id is validated, so no path traversal is possible. */
function adm_backup_read(string $name, string $id): ?string
{
    if (!isset(adm_content_files()[$name]) || !preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}$/', $id)) {
        return null;
    }
    $f = adm_storage('backups/' . $name . '/' . $id . '.json.php');
    if (!is_file($f)) {
        return null;
    }
    $raw = file_get_contents($f);
    return $raw === false ? null : adm_strip_guard($raw);
}

/* ------------------------------------------------------------------------ */
/* Environment health (dashboard + setup)                                    */
/* ------------------------------------------------------------------------ */

/** @return array[] {ok: bool, label: string, fix: string} — only problems when $problemsOnly */
function adm_health(bool $problemsOnly = false): array
{
    $checks = [];
    $checks[] = [
        'ok' => PHP_VERSION_ID >= 70400,
        'label' => 'إصدار PHP ' . PHP_VERSION,
        'fix' => 'اختر PHP 7.4 أو أحدث من إعدادات الاستضافة.',
    ];
    $checks[] = [
        'ok' => function_exists('imagecreatetruecolor') && function_exists('imagejpeg'),
        'label' => 'مكتبة معالجة الصور (GD)',
        'fix' => 'رفع الصور غير متاح حتى تُفعَّل إضافة GD في إعدادات PHP على الاستضافة.',
    ];
    $checks[] = [
        'ok' => function_exists('finfo_open'),
        'label' => 'التحقق من نوع الملفات (fileinfo)',
        'fix' => 'فعّل إضافة fileinfo في إعدادات PHP لرفع الصور والتقارير.',
    ];
    $checks[] = [
        'ok' => class_exists('ZipArchive'),
        'label' => 'إنشاء ملفات ZIP (ZipArchive)',
        'fix' => 'تنزيل النسخة الاحتياطية بصيغة ZIP غير متاح؛ نزّل المجلدات يدويًا من «مدير الملفات».',
    ];
    $dirs = [
        BM_CONTENT => 'ملفات المحتوى',
        BM_STORAGE => 'بيانات لوحة الإدارة',
        BM_PUBLIC . '/uploads/images' => 'الصور المرفوعة',
        BM_PUBLIC . '/uploads/reports' => 'التقارير المرفوعة',
        BM_PUBLIC => 'صفحات الموقع',
        BM_PUBLIC . '/assets/css' => 'ملفات التنسيق',
        BM_PUBLIC . '/activities' => 'صفحات الأنشطة',
    ];
    foreach (['projects', 'stories'] as $d) {
        if (is_dir(BM_PUBLIC . '/' . $d)) {
            $dirs[BM_PUBLIC . '/' . $d] = $d === 'projects' ? 'صفحات المشروعات' : 'صفحات الحكايات';
        }
    }
    foreach ($dirs as $dir => $label) {
        $ok = is_dir($dir) && is_writable($dir);
        $checks[] = [
            'ok' => $ok,
            'label' => $label . ' — «' . adm_rel($dir) . '» قابل للكتابة',
            'fix' => 'من «مدير الملفات» اضبط صلاحيات المجلد «' . adm_rel($dir) . '» على 755 (أو 775).',
        ];
    }
    if (!empty($GLOBALS['adm_session_fallback'])) {
        $checks[] = [
            'ok' => false,
            'label' => 'حفظ الجلسات داخل app/storage/sessions',
            'fix' => 'المجلد غير قابل للكتابة؛ تُستخدم إعدادات الاستضافة الافتراضية للجلسات مؤقتًا.',
        ];
    }
    $checks[] = [
        'ok' => adm_is_https() || adm_is_local(),
        'label' => 'اتصال مشفّر (HTTPS)',
        'fix' => 'ادخل إلى لوحة الإدارة عبر https:// وفعّل شهادة SSL من لوحة Turbify.',
    ];
    if ($problemsOnly) {
        return array_values(array_filter($checks, function ($c) {
            return !$c['ok'];
        }));
    }
    return $checks;
}
