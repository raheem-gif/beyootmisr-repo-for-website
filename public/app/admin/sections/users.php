<?php
/**
 * «المستخدمون» (admins only): add users, change roles, reset passwords, delete
 * other users. The last admin can be neither demoted nor deleted.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'المستخدمون';
AdmPage::$lead = 'حسابات فريق العمل. امنح كل شخص الدور الذي يحتاجه فقط.';
$roles = adm_roles();
$values = ['name' => '', 'username' => '', 'role' => 'editor'];

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $target = is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '';
    $self = $target === $user['username'];
    try {
        if ($action === 'create') {
            $in = new AdmInput($_POST);
            $values['name'] = $in->text('name', 'الاسم', ['max' => 60, 'required' => true]);
            $values['username'] = $target;
            $values['role'] = $in->enum('role', 'الدور', array_keys($roles), 'editor', ['required' => true]);
            $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $pw2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
            if (($p = adm_username_problem($target)) !== '') {
                $in->error('username', $p);
            } elseif (adm_user_get($target) !== null) {
                $in->error('username', 'اسم المستخدم مستخدم بالفعل، اختر اسمًا آخر.');
            }
            if (($p = adm_password_problem($pw, $target)) !== '') {
                $in->error('password', $p);
            } elseif (!hash_equals($pw, $pw2)) {
                $in->error('password2', 'كلمتا المرور غير متطابقتين.');
            }
            if ($in->ok()) {
                $u = adm_user_create($target, $values['name'], $values['role'], $pw);
                adm_audit('users.create', $u['username'] . ' (' . $roles[$u['role']] . ')');
                adm_flash('success', 'أُضيف المستخدم «' . $u['username'] . '». أبلغه باسم المستخدم وكلمة المرور بطريقة آمنة، واطلب منه تغييرها من «حسابي».');
                adm_redirect('users');
            }
            AdmForm::$errors = $in->errors();
        } elseif ($action === 'role') {
            if ($self) {
                throw new AdmUserError('لا يمكنك تغيير دورك بنفسك. اطلب ذلك من مدير آخر.');
            }
            $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
            if (!isset($roles[$role])) {
                throw new AdmUserError('اختر دورًا صحيحًا.');
            }
            $u = adm_user_update($target, ['role' => $role]);
            adm_audit('users.role', $u['username'] . ' ← ' . $roles[$role]);
            adm_flash('success', 'أصبح دور «' . $u['username'] . '»: ' . $roles[$role] . '.');
            adm_redirect('users');
        } elseif ($action === 'password') {
            $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if ($self) {
                throw new AdmUserError('لتغيير كلمة مرورك استخدم صفحة «حسابي».');
            }
            if (adm_user_get($target) === null) {
                throw new AdmUserError('المستخدم غير موجود.');
            }
            adm_user_set_password($target, $pw);
            adm_audit('users.password', $target);
            adm_flash('success', 'عُيّنت كلمة مرور جديدة لـ «' . $target . '»، وأُنهيت جلساته المفتوحة.');
            adm_redirect('users');
        } elseif ($action === 'delete') {
            if ($self) {
                throw new AdmUserError('لا يمكنك حذف حسابك.');
            }
            adm_user_delete($target);
            adm_audit('users.delete', $target);
            adm_flash('success', 'حُذف المستخدم «' . $target . '».');
            adm_redirect('users');
        } else {
            adm_redirect('users');
        }
    } catch (AdmUserError $e) {
        adm_flash('error', $e->getMessage());
        if ($action !== 'create') {
            adm_redirect('users');
        }
        AdmForm::$errors['username'] = AdmForm::$errors['username'] ?? $e->getMessage();
    }
}

$users = adm_users();
?>
<div class="adm-alert adm-alert--info"><?= adm_icon('info') ?><div>
  <p><strong>مدير:</strong> كل الأقسام، بما فيها الإعدادات والمستخدمون وسجل التغييرات والنسخ الاحتياطية.</p>
  <p><strong>محرر:</strong> النصوص، وأثرنا بالأرقام، والمشروعات، والحكايات، والتقارير، ومعرض صور الأنشطة.</p>
</div></div>

<section class="adm-card" aria-labelledby="users-list-title">
  <div class="adm-card__head"><h2 class="adm-card__title" id="users-list-title"><?= adm_icon('users') ?><span>الحسابات (<?= count($users) ?>)</span></h2></div>
  <div class="adm-card__body">
    <ul class="adm-users" role="list">
<?php foreach ($users as $u):
    $isSelf = $u['username'] === $user['username'];
    $uid = adm_id('u-' . $u['username']);
?>
      <li class="adm-users__item">
        <div class="adm-users__who">
          <span class="adm-user__avatar" aria-hidden="true"><?= e(bm_substr(adm_user_label($u), 0, 1)) ?></span>
          <div>
            <p class="adm-users__name"><?= e(adm_user_label($u)) ?> <?= $isSelf ? adm_badge('أنت', 'blue') : '' ?></p>
            <p class="adm-users__meta"><bdi dir="ltr"><?= e($u['username']) ?></bdi> · <?= adm_badge($roles[$u['role']] ?? $u['role'], $u['role'] === 'admin' ? 'amber' : 'neutral') ?></p>
            <p class="adm-users__meta">آخر دخول: <?= $u['last_login'] !== '' ? e(adm_time(adm_ts((string) $u['last_login']))) : 'لم يسجّل الدخول بعد' ?></p>
          </div>
        </div>
<?php if ($isSelf): ?>
        <p class="adm-users__self"><a class="adm-btn adm-btn--ghost adm-btn--sm" href="<?= e(adm_url('account')) ?>"><?= adm_icon('key-round') ?><span>تغيير اسمي أو كلمة مروري</span></a></p>
<?php else: ?>
        <details class="adm-users__manage">
          <summary class="adm-btn adm-btn--secondary adm-btn--sm"><?= adm_icon('user-cog') ?><span>إدارة الحساب</span></summary>
          <div class="adm-users__panel">
            <?= adm_form_open(['action' => adm_url('users'), 'class' => 'adm-users__form']) ?>
              <input type="hidden" name="action" value="role"><input type="hidden" name="username" value="<?= e($u['username']) ?>">
              <?= adm_field(['type' => 'select', 'path' => 'role', 'id' => $uid . '-role', 'label' => 'الدور', 'options' => $roles, 'value' => $u['role'], 'error' => '']) ?>
              <?= adm_submit('تغيير الدور', ['variant' => 'secondary', 'small' => true, 'busy' => 'جارٍ الحفظ…']) ?>
            <?= adm_form_close() ?>
            <?= adm_form_open(['action' => adm_url('users'), 'class' => 'adm-users__form']) ?>
              <input type="hidden" name="action" value="password"><input type="hidden" name="username" value="<?= e($u['username']) ?>">
              <?= adm_field(['type' => 'password', 'path' => 'password', 'id' => $uid . '-pw', 'label' => 'كلمة مرور جديدة', 'autocomplete' => 'new-password', 'error' => '', 'attrs' => ['minlength' => (string) ADM_PASSWORD_MIN, 'required' => true, 'data-reveal' => '1'], 'hint' => 'لا تقل عن ' . ADM_PASSWORD_MIN . ' أحرف. سيُطلب منه الدخول من جديد.']) ?>
              <?= adm_submit('تعيين كلمة المرور', ['variant' => 'secondary', 'small' => true, 'icon' => 'key-round', 'busy' => 'جارٍ الحفظ…']) ?>
            <?= adm_form_close() ?>
            <?= adm_action_button(['action' => adm_url('users'), 'label' => 'حذف الحساب', 'icon' => 'trash-2', 'variant' => 'danger', 'small' => true,
                'fields' => ['action' => 'delete', 'username' => $u['username']],
                'confirm' => 'حذف حساب «' . $u['username'] . '» نهائيًا؟ لن يتمكن من الدخول بعد ذلك.']) ?>
          </div>
        </details>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="adm-card" id="new-user" aria-labelledby="users-new-title">
  <div class="adm-card__head"><h2 class="adm-card__title" id="users-new-title"><?= adm_icon('user-plus') ?><span>إضافة مستخدم</span></h2></div>
  <div class="adm-card__body">
    <?= adm_errors_summary(AdmForm::$errors) ?>
    <?= adm_form_open(['action' => adm_url('users', [], 'new-user')]) ?>
      <input type="hidden" name="action" value="create">
      <div class="adm-cols">
        <?= adm_field(['path' => 'name', 'label' => 'الاسم الظاهر', 'value' => $values['name'], 'required' => true, 'max' => 60]) ?>
        <?= adm_field(['path' => 'username', 'label' => 'اسم المستخدم (بالإنجليزية)', 'value' => $values['username'], 'required' => true, 'max' => 32, 'dir' => 'ltr', 'hint' => 'حروف إنجليزية صغيرة وأرقام، مثل: sara.ali', 'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false', 'autocomplete' => 'off']]) ?>
      </div>
      <?= adm_field(['type' => 'select', 'path' => 'role', 'label' => 'الدور', 'options' => $roles, 'value' => $values['role'], 'hint' => 'اختر «محرر» لمن يحدّث المحتوى فقط.']) ?>
      <div class="adm-cols">
        <?= adm_field(['type' => 'password', 'path' => 'password', 'label' => 'كلمة المرور', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'لا تقل عن ' . ADM_PASSWORD_MIN . ' أحرف.', 'attrs' => ['minlength' => (string) ADM_PASSWORD_MIN, 'data-reveal' => '1']]) ?>
        <?= adm_field(['type' => 'password', 'path' => 'password2', 'label' => 'تأكيد كلمة المرور', 'required' => true, 'autocomplete' => 'new-password']) ?>
      </div>
      <?= adm_submit('إضافة المستخدم', ['icon' => 'user-plus']) ?>
    <?= adm_form_close() ?>
  </div>
</section>
