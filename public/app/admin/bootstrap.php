<?php
/**
 * Admin panel bootstrap + router. Loaded by admin/index.php (the only entry point).
 *
 *   index.php?p=<section>   GET renders a section, POST runs its actions (CSRF + Origin checked here).
 *   Special routes: login, logout (POST only), setup (first run), ping (keeps an active editor's session alive).
 *
 * Library map (each file documents its API at the top):
 *   store.php     protected storage (users/logs/backups as .php files)     security.php  headers, sessions, CSRF, rate limit
 *   users.php     accounts, roles, permissions                            audit.php     change log
 *   input.php     cleaning + AdmInput validator                           forms.php     form/field HTML helpers
 *   content.php   save flow (backup → write → build → restore), backups   uploads.php   images + PDFs
 *   ui.php        layout, flash, components, dates                        sections.php  navigation registry
 *   collections.php  records, slugs, options, image-list editor (stage 2)
 *   texts-labels.php  Arabic names for texts.json keys                    sections/*.php  one file per section
 */
declare(strict_types=1);
defined('BM_APP') || exit;

if (!defined('ADM_WEB_DIR')) {
    define('ADM_WEB_DIR', dirname(__DIR__, 2) . '/admin'); // folder of admin/index.php, admin.css, admin.js
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/input.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/collections.php';
require_once __DIR__ . '/sections.php';

function adm_run(): void
{
    if (function_exists('ini_set')) { // hosts may disable it (a fatal error on PHP 8)
        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');
    }
    error_reporting(E_ALL);
    set_exception_handler('adm_fatal');
    ob_start();
    adm_send_headers();
    header('Content-Type: text/html; charset=utf-8');

    adm_session_start();

    $p = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : 'dashboard';
    if (!preg_match('/^[a-z][a-z0-9-]{0,30}$/', $p)) {
        $p = 'dashboard';
    }
    $isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

    if ($isPost) {
        if (adm_post_too_large()) {
            adm_flash('error', 'حجم ما أرسلته أكبر من الحد الذي تسمح به الاستضافة ('
                . adm_bytes(adm_ini_bytes((string) ini_get('post_max_size'))) . '). ارفع ملفات أصغر أو عددًا أقل في كل مرة.');
            adm_redirect_to(adm_current_url());
        }
        if (!adm_origin_ok() || !adm_csrf_valid()) {
            adm_bad_request();
            return;
        }
    }

    // First run: no accounts yet.
    if (!adm_users_exist()) {
        adm_run_plain('setup');
        return;
    }
    if (is_file(adm_storage('setup.allow'))) {
        @unlink(adm_storage('setup.allow')); // accounts exist: the setup switch must not linger
    }
    if ($p === 'setup') {
        adm_redirect('dashboard');
    }

    $user = adm_session_user();
    $GLOBALS['adm_user'] = $user;

    if ($p === 'ping') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($user !== null ? 204 : 401);
        return;
    }

    if ($p === 'logout') {
        if ($isPost && $user !== null) {
            adm_audit('logout', '', $user);
            adm_session_end();
            adm_redirect('login', ['m' => 'out']);
        }
        adm_redirect($user !== null ? 'dashboard' : 'login');
    }

    if ($user === null) {
        if ($p !== 'login') {
            if (!$isPost && isset(adm_sections()[$p])) {
                $_SESSION['adm_next'] = $p;
            }
            adm_redirect('login', !empty($GLOBALS['adm_session_expired']) ? ['m' => 'timeout'] : []);
        }
        adm_run_plain('login');
        return;
    }
    if ($p === 'login') {
        adm_redirect('dashboard');
    }

    $sections = adm_sections();
    if (!isset($sections[$p])) {
        http_response_code(404);
        AdmPage::$title = 'الصفحة غير موجودة';
        adm_layout(adm_empty('circle-help', 'لم نجد هذه الصفحة', 'اختر قسمًا من القائمة.'), $user, '');
        return;
    }
    if (!adm_can($user, $p)) {
        http_response_code(403);
        AdmPage::$title = 'غير مسموح';
        adm_layout(adm_empty('lock', 'ليست لديك صلاحية لفتح هذا القسم', 'هذا القسم متاح لحساب «مدير» فقط. تواصل مع مدير الموقع إن احتجت إليه.'), $user, $p);
        return;
    }
    adm_run_section($p, $user);
}

/** Run a section file inside the full layout. */
function adm_run_section(string $key, array $user): void
{
    $file = adm_section_ready($key) ? adm_section_file($key) : __DIR__ . '/sections/_stub.php';
    $isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    try {
        $body = adm_capture($file, ['user' => $user, 'section' => $key]);
    } catch (AdmUserError $e) {
        if ($isPost) {
            adm_flash('error', $e->getMessage(), $e->details);
            adm_redirect_to(adm_current_url());
        }
        $body = adm_alert($e->getMessage(), 'error');
    }
    adm_layout($body, $user, $key);
}

/** Login / setup: no navigation. */
function adm_run_plain(string $key): void
{
    $body = adm_capture(__DIR__ . '/sections/' . $key . '.php', ['user' => null, 'section' => $key]);
    adm_layout_plain($body, AdmPage::$title);
}

/** Include a section file in its own scope and return what it printed. */
function adm_capture(string $file, array $vars): string
{
    $level = ob_get_level();
    ob_start();
    try {
        (static function (string $__file, array $__vars) {
            extract($__vars, EXTR_SKIP);
            include $__file;
        })($file, $vars);
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw $e;
    }
    return (string) ob_get_clean();
}

function adm_redirect_to(string $url): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!preg_match('#^index\.php(\?[^\r\n]*)?$#', $url)) {
        $url = 'index.php';
    }
    header('Location: ' . $url, true, 303);
    exit;
}

function adm_bad_request(): void
{
    http_response_code(400);
    AdmPage::$title = 'انتهت صلاحية الصفحة';
    $body = '<h1 class="adm-plain__title">انتهت صلاحية الصفحة</h1>'
        . '<p>لم نتمكن من التحقق من هذا الطلب (ربما انتهت الجلسة أو فُتحت الصفحة منذ وقت طويل). لم يُحفظ أي تغيير.</p>'
        . '<p><a class="adm-btn adm-btn--primary" href="' . e(adm_current_url()) . '">' . adm_icon('refresh-cw') . '<span>أعد تحميل الصفحة وحاول مرة أخرى</span></a></p>';
    adm_layout_plain($body, AdmPage::$title);
}

/** Last-resort handler: log, then show a calm Arabic page (details only on localhost). */
function adm_fatal(Throwable $e): void
{
    adm_log_error($e);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $details = adm_is_local() ? '<details class="adm-tech"><summary>التفاصيل التقنية</summary><pre dir="ltr">' . e(adm_error_details($e)) . '</pre></details>' : '';
    $body = '<h1 class="adm-plain__title">حدث خطأ غير متوقع</h1>'
        . '<p>لم يكتمل الطلب. إن كنت تحفظ تعديلًا فتأكد منه بعد إعادة تحميل الصفحة. سُجّل الخطأ في «app/storage/errors.php» ليطّلع عليه المطوّر.</p>'
        . $details
        . '<p><a class="adm-btn adm-btn--primary" href="index.php">العودة إلى لوحة التحكم</a></p>';
    try {
        adm_layout_plain($body, 'خطأ');
    } catch (Throwable $e2) {
        echo 'حدث خطأ غير متوقع.';
    }
}
