<?php
/**
 * Login. Rate limited per IP and per username (5 failures → 15-minute lock),
 * with one generic error message whatever the reason.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'تسجيل الدخول';
$generic = 'تعذّر تسجيل الدخول. تحقّق من اسم المستخدم وكلمة المرور. بعد 5 محاولات غير صحيحة يتوقف الدخول مؤقتًا لمدة 15 دقيقة.';
$error = '';
$username = '';

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $username = strtolower(adm_clean(is_string($_POST['username'] ?? null) ? $_POST['username'] : ''));
    $username = substr((string) preg_replace('/[^a-z0-9._@-]/', '', $username), 0, 64);
    $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 1024) : '';
    $keys = adm_rl_keys($username);

    if (adm_rl_locked($keys)) {
        password_verify($password, ADM_DUMMY_HASH); // same cost as a real attempt
        $error = $generic;
    } else {
        $found = $username !== '' && $password !== '' ? adm_authenticate($username, $password) : null;
        if ($found !== null) {
            $next = isset($_SESSION['adm_next']) && is_string($_SESSION['adm_next']) ? $_SESSION['adm_next'] : 'dashboard';
            adm_rl_clear($keys);
            adm_session_login($found);
            $GLOBALS['adm_user'] = $found;
            adm_audit('login', '', $found);
            adm_redirect(isset(adm_sections()[$next]) && adm_can($found, $next) ? $next : 'dashboard');
        }
        if ($username === '' || $password === '') {
            password_verify($password, ADM_DUMMY_HASH);
        }
        adm_rl_fail($keys);
        adm_audit('login.failed', $username !== '' ? 'اسم المستخدم: ' . $username : '');
        usleep(random_int(150000, 400000));
        $error = $generic;
    }
}

$m = isset($_GET['m']) && is_string($_GET['m']) ? $_GET['m'] : '';
?>
<h1 class="adm-plain__title">تسجيل الدخول</h1>
<p class="adm-plain__lead">لوحة إدارة محتوى موقع مؤسسة بيوت مصر لدعم الأرامل والأيتام.</p>
<?php if ($m === 'timeout'): ?>
<?= adm_alert('انتهت الجلسة بعد 30 دقيقة دون نشاط (أو 8 ساعات متواصلة) حفاظًا على الأمان. سجّل الدخول مرة أخرى.', 'info') ?>
<?php elseif ($m === 'out'): ?>
<?= adm_alert('تم تسجيل الخروج بنجاح.', 'success') ?>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="adm-alert adm-alert--error" role="alert" tabindex="-1" data-focus><?= adm_icon('circle-alert') ?><div><p><?= e($error) ?></p></div></div>
<?php endif; ?>
<?php if (!adm_is_https() && !adm_is_local()): ?>
<?= adm_alert('هذا الاتصال غير مشفّر. استخدم عنوانًا يبدأ بـ https:// قبل إدخال كلمة المرور.', 'warning') ?>
<?php endif; ?>
<?= adm_form_open(['action' => adm_url('login'), 'class' => 'adm-login']) ?>
  <?= adm_field(['path' => 'username', 'label' => 'اسم المستخدم', 'value' => $username, 'required' => true, 'autocomplete' => 'username', 'dir' => 'ltr', 'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false', 'autofocus' => $username === '' ? true : null]]) ?>
  <?= adm_field(['path' => 'password', 'type' => 'password', 'label' => 'كلمة المرور', 'required' => true, 'autocomplete' => 'current-password', 'attrs' => ['autofocus' => $username !== '' ? true : null, 'data-reveal' => '1']]) ?>
  <?= adm_submit('دخول', ['icon' => 'lock-open', 'class' => 'adm-btn--block', 'busy' => 'جارٍ التحقق…']) ?>
<?= adm_form_close() ?>
<p class="adm-plain__note">نسيت كلمة المرور؟ اطلب من مدير الموقع إعادة تعيينها من صفحة «المستخدمون».</p>
