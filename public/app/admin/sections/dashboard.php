<?php
/**
 * «لوحة التحكم»: counts, last build, rebuild button, pre-launch checklist
 * (content document §13), recent changes, hosting health (admins).
 */
defined('BM_APP') || exit;

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if ($action === 'rebuild' && adm_can($user, 'rebuild')) {
        try {
            $report = adm_rebuild('site.rebuild');
            adm_audit('site.rebuild', (int) $report['pages'] . ' صفحة');
            adm_flash('success', adm_build_summary($report));
        } catch (AdmUserError $e) {
            adm_flash('error', $e->getMessage(), $e->details);
        }
    }
    adm_redirect('dashboard');
}

$isAdmin = adm_can($user, 'settings');
AdmPage::$title = 'مرحبًا، ' . adm_user_label($user);
AdmPage::$lead = 'من هنا تدير محتوى موقع مؤسسة بيوت مصر. كل حفظ يُحدّث صفحات الموقع تلقائيًا.';

// Data (raw, including unpublished items).
$settings = array_replace_recursive(bm_settings_defaults(), (array) adm_content_load('settings'));
$statsRaw = (array) adm_content_load('stats');
$projects = array_values(array_filter((array) adm_content_load('projects'), 'is_array'));
$stories = array_values(array_filter((array) adm_content_load('stories'), 'is_array'));
$reports = array_values(array_filter((array) adm_content_load('reports'), 'is_array'));
$galleries = (array) adm_content_load('galleries');

$countPub = function (array $items): int {
    return count(array_filter($items, function ($x) {
        return !empty($x['published']);
    }));
};
$galleryCount = 0;
foreach ($galleries as $list) {
    $galleryCount += is_array($list) ? count($list) : 0;
}
$ongoing = count(array_filter($projects, function ($p) {
    return ($p['status'] ?? '') === 'ongoing';
}));

/* ---- Pre-launch checklist (content document §13) ---- */
$statItems = array_values(array_filter((array) ($statsRaw['items'] ?? []), 'is_array'));
$noValue = array_filter($statItems, function ($i) {
    return !isset($i['value']) || trim((string) $i['value']) === '';
});
$statsDate = trim((string) ($statsRaw['updated_at'] ?? ''));
$statDetail = [];
if ($statsDate === '') {
    $statDetail[] = 'لم يُسجَّل تاريخ مراجعة الأرقام';
}
if (trim((string) ($statsRaw['period'] ?? '')) === '') {
    $statDetail[] = 'لم تُحدَّد الفترة التي تغطيها الأرقام';
}
if ($noValue) {
    $statDetail[] = count($noValue) . ' من ' . count($statItems) . ' أرقام بلا قيمة (تظهر «يُعلن بعد التوثيق»)';
}
$legalMissing = [];
foreach (['registration_authority' => 'جهة الإشهار', 'public_benefit' => 'صفة النفع العام', 'fundraising_permit' => 'تصريح جمع التبرعات'] as $k => $l) {
    if (trim((string) $settings['legal'][$k]) === '') {
        $legalMissing[] = $l;
    }
}
$contactMissing = [];
foreach ([
    ['contact', 'email', 'البريد الإلكتروني'],
    ['social', 'facebook', 'صفحة فيسبوك'],
    ['social', 'instagram', 'صفحة إنستجرام'],
    ['contact', 'address_detail', 'العنوان التفصيلي'],
    ['contact', 'hours', 'مواعيد العمل'],
] as [$grp, $k, $l]) {
    if (trim((string) $settings[$grp][$k]) === '') {
        $contactMissing[] = $l;
    }
}
$donOn = !empty($settings['donations']['enabled']);
$donMethods = count(array_filter((array) $settings['donations']['methods'], 'is_array'));

$checks = [
    [
        'ok' => $statsDate !== '' && !$noValue,
        'title' => 'اعتماد أرقام الإنجازات والفترة الزمنية لكل رقم من إدارة المؤسسة.',
        'detail' => implode('، ', $statDetail),
        'link' => 'stats',
    ],
    [
        'ok' => !$legalMissing,
        'title' => 'إثبات الصفة الرسمية للنفع العام وتصريح جمع التبرعات بأرقامها ونطاقها وتواريخ صلاحيتها قبل ذكرها على الموقع.',
        'detail' => $legalMissing ? 'حقول فارغة في الإعدادات: ' . implode('، ', $legalMissing) . '. لا تُكتب إلا بالنص المطابق للمستندات المعتمدة.' : '',
        'link' => 'settings',
    ],
    [
        'ok' => $donOn && $donMethods > 0,
        'title' => 'التأكد من الحسابات البنكية ووسائل الدفع المسموح بها قبل نشرها.',
        'detail' => !$donOn ? 'التبرع عبر الموقع غير مفعّل بعد؛ يظهر للزوار أن وسائل التبرع تُعلن فور اعتمادها.' : ($donMethods === 0 ? 'التبرع مفعّل لكن لا توجد وسائل تبرع مضافة.' : ''),
        'link' => 'settings',
    ],
    [
        'ok' => !$contactMissing,
        'title' => 'اعتماد العنوان الرسمي ووسائل الاتصال ومواعيد العمل.',
        'detail' => $contactMissing ? 'غير مُدخل بعد: ' . implode('، ', $contactMissing) . '.' : '',
        'link' => 'settings',
    ],
    [
        'ok' => trim((string) $settings['site_url']) !== '',
        'title' => 'ربط عنوان الموقع (النطاق) في الإعدادات.',
        'detail' => trim((string) $settings['site_url']) === '' ? 'بدون عنوان الموقع لا تُنشأ خريطة الموقع (sitemap) ولا الروابط الأساسية لمحركات البحث.' : '',
        'link' => 'settings',
    ],
];
$manual = [
    'مطابقة رقم الإشهار والاسم القانوني مع المستندات الرسمية.',
    'اعتماد صور المشروعات والتأكد من حقوق استخدامها وعدم ظهور بيانات أو أشخاص يمكن تحديد هويتهم دون سند مناسب.',
    'مراجعة سياسة الخصوصية والنماذج الإلكترونية مع المختص القانوني.',
    'تدقيق محتوى الأنشطة والتأكد من أن المساعدات الموصوفة متاحة بالفعل في الفترة المعروضة.',
];
$pending = count(array_filter($checks, function ($c) {
    return !$c['ok'];
}));

$build = adm_build_status();
// Content changes only (sign-ins stay in the full log).
$audit = adm_audit_entries($isAdmin ? [] : ['user' => $user['username']], 200);
$audit['items'] = array_slice(array_values(array_filter($audit['items'], function ($r) {
    return !in_array((string) ($r['a'] ?? ''), ['login', 'logout', 'login.failed'], true);
})), 0, 6);
$health = $isAdmin ? adm_health(true) : [];

$stat = function (string $label, string $value, string $sub, string $iconName, string $section) use ($user): string {
    $inner = '<span class="adm-stat__icon">' . adm_icon($iconName) . '</span><span class="adm-stat__text"><span class="adm-stat__value">' . e($value) . '</span>'
        . '<span class="adm-stat__label">' . e($label) . '</span><span class="adm-stat__sub">' . e($sub) . '</span></span>';
    if (adm_can($user, $section)) {
        return '<li><a class="adm-stat" href="' . e(adm_url($section)) . '">' . $inner . '</a></li>';
    }
    return '<li><div class="adm-stat">' . $inner . '</div></li>';
};
?>
<?php if ($health): ?>
<div class="adm-alert adm-alert--warning"><?= adm_icon('triangle-alert') ?><div><strong>تنبيهات الاستضافة</strong><ul>
<?php foreach ($health as $c): ?>
  <li><?= e($c['label']) ?> — <?= e($c['fix']) ?></li>
<?php endforeach; ?>
</ul></div></div>
<?php endif; ?>

<section class="adm-section" aria-labelledby="dash-counts">
  <h2 class="sr-only" id="dash-counts">ملخص المحتوى</h2>
  <ul class="adm-stats" role="list">
    <?= $stat('المشروعات', (string) count($projects), $countPub($projects) . ' منشور · ' . $ongoing . ' جارٍ', 'building-2', 'projects') ?>
    <?= $stat('الحكايات', (string) count($stories), $countPub($stories) . ' منشورة', 'book-open', 'stories') ?>
    <?= $stat('التقارير', (string) count($reports), $countPub($reports) . ' منشور', 'scroll-text', 'reports') ?>
    <?= $stat('صور الأنشطة', (string) $galleryCount, 'في معرض صور الأنشطة', 'images', 'galleries') ?>
  </ul>
</section>

<div class="adm-grid adm-grid--dash">
  <?php
  $buildBody = '';
  if (!$build) {
      $buildBody .= '<p class="adm-muted">لم يُسجَّل تحديث للموقع من لوحة الإدارة بعد. تُحدَّث الصفحات تلقائيًا بعد كل حفظ.</p>';
  } else {
      $buildBody .= '<p class="adm-build ' . (!empty($build['ok']) ? 'is-ok' : 'is-error') . '">'
          . adm_icon(!empty($build['ok']) ? 'circle-check' : 'circle-alert')
          . '<span>' . (!empty($build['ok']) ? 'تم بنجاح: ' . (int) $build['pages'] . ' صفحة' . ((int) $build['written'] > 0 ? '، تحدّث ' . (int) $build['written'] . ' ملف' : '') : 'فشل آخر تحديث للموقع') . '</span></p>'
          . '<p class="adm-muted">' . e(adm_time((int) ($build['t'] ?? 0))) . (($build['u'] ?? '') !== '' ? ' — ' . e((string) $build['u']) : '')
          . (($build['reason'] ?? '') !== '' ? ' · ' . e(adm_audit_label((string) $build['reason'])) : '') . '</p>';
      if (empty($build['ok']) && !empty($build['error'])) {
          $buildBody .= '<details class="adm-tech"><summary>التفاصيل التقنية (للمطوّر)</summary><pre dir="ltr">' . e((string) $build['error']) . '</pre></details>';
      }
      if (!empty($build['warnings']) && $isAdmin) {
          $buildBody .= '<details class="adm-tech"><summary>ملاحظات البناء (' . count((array) $build['warnings']) . ')</summary><pre dir="ltr">' . e(implode("\n", (array) $build['warnings'])) . '</pre></details>';
      }
  }
  if (adm_can($user, 'rebuild')) {
      $buildBody .= '<p class="adm-hint">استخدم «إعادة بناء الموقع» بعد رفع ملفات يدويًا أو إن بدت صفحة غير محدّثة.</p>'
          . adm_action_button(['label' => 'إعادة بناء الموقع', 'fields' => ['action' => 'rebuild'], 'icon' => 'refresh-cw', 'variant' => 'secondary', 'busy' => 'جارٍ إعادة البناء…']);
  }
  echo adm_card('آخر تحديث للموقع', $buildBody, ['icon' => 'refresh-cw', 'id' => 'dash-build']);

  $auditBody = '';
  if (!$audit['items']) {
      $auditBody = '<p class="adm-muted">لا توجد تغييرات مسجلة بعد.</p>';
  } else {
      $auditBody = '<ul class="adm-feed" role="list">';
      foreach ($audit['items'] as $row) {
          $auditBody .= '<li><span class="adm-feed__what">' . e(adm_audit_label((string) ($row['a'] ?? ''))) . '</span>'
              . (($row['d'] ?? '') !== '' ? '<span class="adm-feed__detail">' . e((string) $row['d']) . '</span>' : '')
              . '<span class="adm-feed__meta">' . e(implode(' · ', array_filter([(string) (($row['n'] ?? '') !== '' ? $row['n'] : ($row['u'] ?? '')), adm_ago((int) ($row['t'] ?? 0))], 'strlen'))) . '</span></li>';
      }
      $auditBody .= '</ul>';
      if ($isAdmin) {
          $auditBody .= '<p><a href="' . e(adm_url('audit')) . '">عرض السجل كاملًا</a></p>';
      }
  }
  echo adm_card($isAdmin ? 'آخر التغييرات' : 'آخر تعديلاتك', $auditBody, ['icon' => 'history', 'id' => 'dash-audit']);
  ?>
</div>

<section class="adm-card adm-checklist" aria-labelledby="dash-check-title">
  <div class="adm-card__head">
    <h2 class="adm-card__title" id="dash-check-title"><?= adm_icon('list-checks') ?><span>قائمة المراجعة قبل إطلاق الموقع</span></h2>
    <?= $pending > 0 ? adm_badge($pending . ' بحاجة إلى متابعة', 'amber') : adm_badge('مكتملة', 'green') ?>
  </div>
  <div class="adm-card__body">
    <p class="adm-hint">من وثيقة المحتوى (القسم 13). البنود الآلية تُفحص من بيانات الموقع، والبنود اليدوية تراجعها إدارة المؤسسة.</p>
    <ul class="adm-checks" role="list">
<?php foreach ($checks as $c): ?>
      <li class="adm-check-item <?= $c['ok'] ? 'is-ok' : 'is-todo' ?>">
        <?= adm_icon($c['ok'] ? 'circle-check' : 'circle-alert') ?>
        <div>
          <p class="adm-check-item__title"><?= e($c['title']) ?></p>
          <?php if (!$c['ok'] && $c['detail'] !== ''): ?><p class="adm-check-item__detail"><?= e($c['detail']) ?></p><?php endif; ?>
          <?php if (!$c['ok'] && adm_can($user, $c['link'])): ?><p><a href="<?= e(adm_url($c['link'])) ?>">اذهب إلى «<?= e(adm_sections()[$c['link']]['label']) ?>»</a></p><?php endif; ?>
        </div>
        <span class="adm-check-item__state"><?= $c['ok'] ? adm_badge('تم', 'green') : adm_badge('بحاجة إلى متابعة', 'amber') ?></span>
      </li>
<?php endforeach; ?>
<?php foreach ($manual as $m): ?>
      <li class="adm-check-item is-manual">
        <?= adm_icon('eye') ?>
        <div><p class="adm-check-item__title"><?= e($m) ?></p></div>
        <span class="adm-check-item__state"><?= adm_badge('مراجعة يدوية', 'neutral') ?></span>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
