<?php
/**
 * First run: create the first admin account. Only possible while no account
 * exists AND app/storage/setup.allow exists (created by hand in the hosting
 * File Manager, which proves control of the hosting account). The file is
 * deleted after success.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'الإعداد الأول';
$allowFile = adm_storage('setup.allow');
$values = ['username' => '', 'name' => ''];

if (!is_file($allowFile)) {
    $problems = adm_health(true);
    ?>
<h1 class="adm-plain__title">مرحبًا بك في لوحة الإدارة</h1>
<p class="adm-plain__lead">لم يُنشأ أي حساب بعد. لحماية الموقع، يتطلب إنشاء أول حساب مدير خطوة من لوحة الاستضافة تثبت أنك صاحب الموقع:</p>
<ol class="adm-steps">
  <li>افتح «مدير الملفات» (File Manager) في لوحة تحكم Turbify.</li>
  <li>ادخل إلى مجلد الموقع ثم المجلد <code dir="ltr">app/storage</code>.</li>
  <li>أنشئ ملفًا جديدًا <strong>فارغًا</strong> باسم <code dir="ltr">setup.allow</code>.</li>
  <li>عد إلى هذه الصفحة واضغط «تحديث». سيظهر نموذج إنشاء حساب المدير.</li>
</ol>
<p class="adm-hint">يُحذف هذا الملف تلقائيًا بعد إنشاء الحساب.</p>
<?php if ($problems): ?>
<div class="adm-alert adm-alert--warning"><?= adm_icon('triangle-alert') ?><div><strong>قبل البدء، يلزم إصلاح ما يلي على الاستضافة:</strong><ul>
<?php foreach ($problems as $c): ?>
  <li><?= e($c['label']) ?>: <?= e($c['fix']) ?></li>
<?php endforeach; ?>
</ul></div></div>
<?php endif; ?>
<p><a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(adm_url('setup')) ?>"><?= adm_icon('refresh-cw') ?><span>تحديث</span></a></p>
<?php
    return;
}

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $in = new AdmInput($_POST);
    $values['username'] = strtolower(trim($in->raw('username')));
    $values['name'] = $in->text('name', 'الاسم', ['max' => 60, 'required' => true]);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
    if (($p = adm_username_problem($values['username'])) !== '') {
        $in->error('username', $p);
    }
    if (($p = adm_password_problem($password, $values['username'])) !== '') {
        $in->error('password', $p);
    } elseif (!hash_equals($password, $confirm)) {
        $in->error('password2', 'كلمتا المرور غير متطابقتين.');
    }
    if ($in->ok()) {
        try {
            if (adm_users_exist()) {
                throw new AdmUserError('يوجد حساب بالفعل. سجّل الدخول.');
            }
            $user = adm_user_create($values['username'], $values['name'], 'admin', $password);
            $deleted = @unlink($allowFile);
            adm_session_login($user);
            $GLOBALS['adm_user'] = $user;
            adm_audit('setup', 'حساب المدير: ' . $user['username'], $user);
            adm_flash('success', 'أُنشئ حساب المدير «' . $user['username'] . '» وسُجّل دخولك. احفظ كلمة المرور في مكان آمن.');
            if (!$deleted) {
                adm_flash('warning', 'تعذّر حذف الملف app/storage/setup.allow تلقائيًا. احذفه يدويًا من «مدير الملفات».');
            }
            adm_redirect('dashboard');
        } catch (AdmUserError $e) {
            $in->error('username', $e->getMessage());
        }
    }
    AdmForm::$errors = $in->errors();
}
?>
<h1 class="adm-plain__title">إنشاء حساب المدير الأول</h1>
<p class="adm-plain__lead">هذا الحساب يملك كل الصلاحيات، ويمكنه لاحقًا إضافة محررين للنصوص والمشروعات والحكايات.</p>
<?= adm_errors_summary(AdmForm::$errors) ?>
<?= adm_form_open(['action' => adm_url('setup')]) ?>
  <?= adm_field(['path' => 'name', 'label' => 'الاسم الظاهر', 'value' => $values['name'], 'required' => true, 'max' => 60, 'autocomplete' => 'name', 'hint' => 'مثل: رحيم — مسؤول الموقع']) ?>
  <?= adm_field(['path' => 'username', 'label' => 'اسم المستخدم (بالإنجليزية)', 'value' => $values['username'], 'required' => true, 'max' => 32, 'autocomplete' => 'username', 'dir' => 'ltr', 'hint' => 'حروف إنجليزية صغيرة وأرقام، مثل: raheem', 'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
  <?= adm_field(['path' => 'password', 'type' => 'password', 'label' => 'كلمة المرور', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'لا تقل عن ' . ADM_PASSWORD_MIN . ' أحرف. يُفضّل عبارة طويلة يسهل تذكرها ويصعب تخمينها.', 'attrs' => ['minlength' => (string) ADM_PASSWORD_MIN, 'data-reveal' => '1']]) ?>
  <?= adm_field(['path' => 'password2', 'type' => 'password', 'label' => 'تأكيد كلمة المرور', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <?= adm_submit('إنشاء الحساب والدخول', ['icon' => 'user-plus', 'class' => 'adm-btn--block']) ?>
<?= adm_form_close() ?>
