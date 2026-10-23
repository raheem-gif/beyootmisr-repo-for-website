<?php
/**
 * Audit log («سجل التغييرات»): who changed what and when.
 * Stored as guarded JSON lines in app/storage/audit.php (never readable over HTTP).
 *
 *   adm_audit(string $action, string $detail = '', ?array $user = null): void
 *       $action is a dotted key such as "texts.save", "projects.delete", "login".
 *       Register an Arabic label for new actions in adm_audit_labels() (or the
 *       label falls back to the section name + the raw action).
 *   adm_audit_entries(array $filter = [], int $limit = 50, int $offset = 0): array
 *       ['total' => int, 'items' => [...newest first]]; filter keys: user, section
 *   adm_audit_label(string $action): string
 *
 * Entry: {t: unix time, u: username, n: display name, a: action, d: detail, ip: short HMAC}
 */
declare(strict_types=1);
defined('BM_APP') || exit;

const ADM_AUDIT_MAX_BYTES = 1500000;
const ADM_AUDIT_KEEP = 4000;

function adm_audit_file(): string
{
    return adm_storage('audit.php');
}

function adm_audit(string $action, string $detail = '', ?array $user = null): void
{
    $user = $user ?? ($GLOBALS['adm_user'] ?? null);
    $entry = [
        't' => time(),
        'u' => $user['username'] ?? '',
        'n' => $user !== null ? adm_user_label($user) : '',
        'a' => preg_replace('/[^a-z0-9._-]/', '', strtolower($action)),
        'd' => bm_substr(adm_clean($detail), 0, 400),
        'ip' => adm_client_hash(),
    ];
    try {
        adm_store_lines_append(adm_audit_file(), $entry);
        if (@filesize(adm_audit_file()) > ADM_AUDIT_MAX_BYTES) {
            adm_with_lock('audit', function () {
                $rows = adm_store_lines_read(adm_audit_file());
                $rows = array_slice($rows, -ADM_AUDIT_KEEP);
                $out = ADM_GUARD;
                foreach ($rows as $r) {
                    $out .= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                }
                adm_write_protected(adm_audit_file(), $out);
            });
        }
    } catch (Throwable $e) {
        // Never block a save because the log could not be written; the health check reports storage problems.
    }
}

function adm_audit_entries(array $filter = [], int $limit = 50, int $offset = 0): array
{
    $rows = array_reverse(adm_store_lines_read(adm_audit_file()));
    if (!empty($filter['user'])) {
        $rows = array_values(array_filter($rows, function ($r) use ($filter) {
            return ($r['u'] ?? '') === $filter['user'];
        }));
    }
    if (!empty($filter['section'])) {
        $rows = array_values(array_filter($rows, function ($r) use ($filter) {
            $a = (string) ($r['a'] ?? '');
            return strpos($a, $filter['section'] . '.') === 0 || $a === $filter['section']
                || ($filter['section'] === 'login' && $a === 'logout')
                || ($filter['section'] === 'users' && $a === 'setup');
        }));
    }
    return ['total' => count($rows), 'items' => array_slice($rows, $offset, $limit)];
}

function adm_audit_labels(): array
{
    return [
        'login' => 'تسجيل الدخول',
        'login.failed' => 'محاولة دخول غير ناجحة',
        'logout' => 'تسجيل الخروج',
        'setup' => 'إنشاء أول حساب مدير',
        'site.rebuild' => 'إعادة بناء الموقع',
        'texts.save' => 'تعديل النصوص',
        'settings.save' => 'تعديل الإعدادات',
        'settings.logo' => 'تغيير الشعار',
        'users.create' => 'إضافة مستخدم',
        'users.role' => 'تغيير دور مستخدم',
        'users.password' => 'إعادة تعيين كلمة مرور',
        'users.delete' => 'حذف مستخدم',
        'account.password' => 'تغيير كلمة المرور',
        'account.name' => 'تعديل الاسم',
        'backup.download' => 'تنزيل نسخة احتياطية',
        'backup.restore' => 'استرجاع نسخة سابقة',
        'stats.save' => 'تعديل أثرنا بالأرقام',
        'projects.create' => 'إضافة مشروع',
        'projects.save' => 'تعديل مشروع',
        'projects.delete' => 'حذف مشروع',
        'projects.stage' => 'تحديث مرحلة مشروع',
        'projects.stage_delete' => 'حذف مرحلة مشروع',
        'stories.create' => 'إضافة حكاية',
        'stories.save' => 'تعديل حكاية',
        'stories.delete' => 'حذف حكاية',
        'reports.create' => 'إضافة تقرير',
        'reports.save' => 'تعديل تقرير',
        'reports.delete' => 'حذف تقرير',
        'galleries.save' => 'تعديل معرض الصور',
        'galleries.upload' => 'رفع صور للمعرض',
        'galleries.delete' => 'حذف صورة من المعرض',
    ];
}

function adm_audit_label(string $action): string
{
    $labels = adm_audit_labels();
    if (isset($labels[$action])) {
        return $labels[$action];
    }
    $section = explode('.', $action)[0];
    $sections = adm_sections();
    return isset($sections[$section]) ? $sections[$section]['label'] . ' (' . $action . ')' : $action;
}
