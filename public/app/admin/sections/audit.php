<?php
/**
 * «سجل التغييرات» (admins only): who changed what and when, newest first,
 * filterable by user and by section.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'سجل التغييرات';
AdmPage::$lead = 'كل حفظ وتسجيل دخول وتغيير في الحسابات يُسجَّل هنا مع اسم صاحبه ووقته.';

$perPage = 50;
$filterUser = isset($_GET['u']) && is_string($_GET['u']) && preg_match('/^[a-z0-9._-]{1,32}$/', $_GET['u']) ? $_GET['u'] : '';
$sections = adm_sections();
$filterSection = isset($_GET['s']) && is_string($_GET['s']) && (isset($sections[$_GET['s']]) || in_array($_GET['s'], ['login', 'site'], true)) ? $_GET['s'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adm_audit_entries(['user' => $filterUser, 'section' => $filterSection], $perPage, ($page - 1) * $perPage);
$pages = max(1, (int) ceil($result['total'] / $perPage));

$userOptions = ['' => 'كل المستخدمين'];
foreach (adm_users() as $u) {
    $userOptions[$u['username']] = adm_user_label($u) . ' (' . $u['username'] . ')';
}
$sectionOptions = ['' => 'كل الأقسام', 'login' => 'الدخول والخروج', 'site' => 'إعادة بناء الموقع'];
foreach ($sections as $k => $s) {
    if ($k !== 'dashboard' && $k !== 'audit') {
        $sectionOptions[$k] = $s['label'];
    }
}
?>
<form method="get" action="index.php" class="adm-filters">
  <input type="hidden" name="p" value="audit">
  <?= adm_field(['type' => 'select', 'path' => 'u', 'label' => 'المستخدم', 'options' => $userOptions, 'value' => $filterUser]) ?>
  <?= adm_field(['type' => 'select', 'path' => 's', 'label' => 'القسم', 'options' => $sectionOptions, 'value' => $filterSection]) ?>
  <button type="submit" class="adm-btn adm-btn--secondary"><?= adm_icon('list-filter') ?><span>عرض</span></button>
</form>

<?php if (!$result['items']): ?>
<?= adm_empty('history', 'لا توجد تغييرات مسجلة', $filterUser !== '' || $filterSection !== '' ? 'جرّب إزالة عوامل التصفية.' : 'ستظهر هنا التعديلات فور حدوثها.') ?>
<?php else: ?>
<p class="adm-muted"><?= e(adm_plural($result['total'], 'سجل واحد', 'سجلان', 'سجلات', 'سجلًا')) ?></p>
<ol class="adm-log" role="list">
<?php foreach ($result['items'] as $row):
    $a = (string) ($row['a'] ?? '');
    $variant = strpos($a, 'delete') !== false || $a === 'login.failed' ? 'red' : (strpos($a, 'login') === 0 || $a === 'logout' ? 'neutral' : 'blue');
?>
  <li class="adm-log__row">
    <span class="adm-log__time"><time datetime="<?= e(date('c', (int) ($row['t'] ?? 0))) ?>"><?= e(adm_time((int) ($row['t'] ?? 0))) ?></time></span>
    <span class="adm-log__who"><?= e((string) (($row['n'] ?? '') !== '' ? $row['n'] : (($row['u'] ?? '') !== '' ? $row['u'] : 'زائر'))) ?></span>
    <span class="adm-log__what"><?= adm_badge(adm_audit_label($a), $variant) ?></span>
    <span class="adm-log__detail"><?= e((string) ($row['d'] ?? '')) ?></span>
  </li>
<?php endforeach; ?>
</ol>
<?php if ($pages > 1): ?>
<nav class="adm-pager" aria-label="صفحات السجل">
  <?php if ($page > 1): ?><a class="adm-btn adm-btn--secondary adm-btn--sm" href="<?= e(adm_url('audit', ['u' => $filterUser, 's' => $filterSection, 'page' => $page - 1])) ?>"><?= adm_icon('chevron-right') ?><span>الأحدث</span></a><?php endif; ?>
  <span class="adm-muted">صفحة <?= $page ?> من <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="adm-btn adm-btn--secondary adm-btn--sm" href="<?= e(adm_url('audit', ['u' => $filterUser, 's' => $filterSection, 'page' => $page + 1])) ?>"><span>الأقدم</span><?= adm_icon('chevron-left') ?></a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
