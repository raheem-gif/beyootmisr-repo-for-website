<?php
/**
 * «حسابي»: every user can change their display name and their own password
 * (current password required). Changing the password ends other sessions.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'حسابي';
AdmPage::$lead = 'اسمك الظاهر وكلمة مرورك. اسم المستخدم لا يتغير.';
$name = adm_user_label($user);

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $in = new AdmInput($_POST);
    if ($action === 'name') {
        $name = $in->text('name', 'الاسم', ['max' => 60, 'required' => true]);
        if ($in->ok()) {
            adm_user_update($user['username'], ['name' => $name]);
            adm_audit('account.name', $name);
            adm_flash('success', 'حُفظ اسمك.');
            adm_redirect('account');
        }
    } elseif ($action === 'password') {
        $currentPw = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
        $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $pw2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
        $keys = adm_rl_keys($user['username']);
        if (adm_rl_locked($keys)) {
            $in->error('current', 'محاولات كثيرة غير صحيحة. انتظر 15 دقيقة ثم حاول مرة أخرى.');
        } elseif (!password_verify($currentPw, (string) $user['hash'])) {
            adm_rl_fail($keys);
            $in->error('current', 'كلمة المرور الحالية غير صحيحة.');
        } elseif (($p = adm_password_problem($pw, $user['username'])) !== '') {
            $in->error('password', $p);
        } elseif (!hash_equals($pw, $pw2)) {
            $in->error('password2', 'كلمتا المرور غير متطابقتين.');
        } elseif (hash_equals($currentPw, $pw)) {
            $in->error('password', 'اختر كلمة مرور مختلفة عن الحالية.');
        }
        if ($in->ok()) {
            adm_user_set_password($user['username'], $pw);
            $fresh = adm_user_get($user['username']);
            session_regenerate_id(true);
            $_SESSION['adm']['pwv'] = (int) ($fresh['pwv'] ?? 1);
            adm_audit('account.password');
            adm_flash('success', 'تغيّرت كلمة مرورك، وأُنهيت جلساتك المفتوحة على الأجهزة الأخرى.');
            adm_redirect('account');
        }
    }
    AdmForm::$errors = $in->errors();
}
$roles = adm_roles();
?>
<div class="adm-grid adm-grid--2">
<?= adm_card('البيانات', '<dl class="adm-dl"><div><dt>اسم المستخدم</dt><dd><bdi dir="ltr">' . e($user['username']) . '</bdi></dd></div>'
    . '<div><dt>الدور</dt><dd>' . e($roles[$user['role']] ?? '') . '</dd></div>'
    . '<div><dt>آخر دخول</dt><dd>' . e(adm_time(adm_ts((string) $user['last_login']))) . '</dd></div></dl>'
    . adm_form_open(['action' => adm_url('account')]) . '<input type="hidden" name="action" value="name">'
    . adm_field(['path' => 'name', 'label' => 'الاسم الظاهر', 'value' => $name, 'required' => true, 'max' => 60])
    . adm_submit('حفظ الاسم', ['variant' => 'secondary', 'icon' => 'save']) . adm_form_close(), ['icon' => 'user', 'id' => 'acc-info']) ?>

<?= adm_card('تغيير كلمة المرور', adm_form_open(['action' => adm_url('account')]) . '<input type="hidden" name="action" value="password">'
    . adm_field(['type' => 'password', 'path' => 'current', 'label' => 'كلمة المرور الحالية', 'required' => true, 'autocomplete' => 'current-password', 'attrs' => ['data-reveal' => '1']])
    . adm_field(['type' => 'password', 'path' => 'password', 'label' => 'كلمة المرور الجديدة', 'required' => true, 'autocomplete' => 'new-password',
        'hint' => 'لا تقل عن ' . ADM_PASSWORD_MIN . ' أحرف. جرّب عبارة من عدة كلمات يسهل تذكرها.', 'attrs' => ['minlength' => (string) ADM_PASSWORD_MIN, 'data-reveal' => '1']])
    . adm_field(['type' => 'password', 'path' => 'password2', 'label' => 'تأكيد كلمة المرور الجديدة', 'required' => true, 'autocomplete' => 'new-password'])
    . adm_submit('تغيير كلمة المرور', ['icon' => 'key-round']) . adm_form_close(), ['icon' => 'key-round', 'id' => 'acc-pw']) ?>
</div>
