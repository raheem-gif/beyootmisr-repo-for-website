<?php
/**
 * «أثرنا بالأرقام» — content/stats.json: review date, period, and the number cards
 * (label, value, suffix, icon, activity, home/achievements flags). Cards can be
 * added and removed. A number is shown on the site only when it has a value AND
 * the review date is set (content document §02/§08: «لا تُنشر أرقام غير موثقة»).
 * Publishing new or changed numbers requires the «موثقة ومعتمدة» confirmation.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'أثرنا بالأرقام';
AdmPage::$lead = 'أرقام الإنجازات الموثقة التي تظهر في الصفحة الرئيسية وصفحة الإنجازات وصفحات الأنشطة.';

$raw = adm_content_load('stats');
$raw = is_array($raw) ? $raw : [];
$itemDefaults = ['id' => '', 'label' => '', 'value' => null, 'suffix' => '', 'icon' => 'badge-check', 'activity' => '', 'home' => false, 'achievements' => true];
$normalize = function (array $data) use ($itemDefaults): array {
    $items = [];
    foreach ((array) ($data['items'] ?? []) as $it) {
        if (is_array($it) && !empty($it['id'])) {
            $items[] = array_replace($itemDefaults, $it);
        }
    }
    return ['updated_at' => (string) ($data['updated_at'] ?? ''), 'period' => (string) ($data['period'] ?? ''), 'items' => $items];
};
$stored = $normalize($raw);
$icons = adm_stat_icons();
$activities = adm_activity_options('— لا يرتبط بنشاط —');
$hasValue = function ($v): bool {
    return $v !== null && trim((string) $v) !== '';
};
/* What visitors see: numbers with a value, only when the review date is set. */
$visible = function (array $data) use ($hasValue): array {
    if (trim($data['updated_at']) === '') {
        return [];
    }
    $out = [];
    foreach ($data['items'] as $it) {
        if ($hasValue($it['value'])) {
            $out[(string) $it['id']] = [(string) $it['label'], (string) $it['value'], (string) $it['suffix']];
        }
    }
    return $out ? ['date' => $data['updated_at'], 'period' => $data['period'], 'items' => $out] : [];
};

$form = $stored;      // values shown in the form
$keepKeys = false;

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $in = new AdmInput($_POST);
    $rev = $in->raw('rev');
    $date = $in->date('updated_at', 'تاريخ مراجعة الأرقام');
    if ($date !== '' && $date > date('Y-m-d')) {
        $in->error('updated_at', '«تاريخ مراجعة الأرقام» لا يمكن أن يكون في المستقبل.');
    }
    $period = $in->text('period', 'الفترة التي تغطيها الأرقام', ['max' => 120]);

    $allowedIcons = array_keys($icons);
    foreach ($stored['items'] as $it) {
        $allowedIcons[] = (string) $it['icon']; // icons set earlier by the developer stay valid
    }
    $posted = adm_post('items', []);
    $posted = is_array($posted) ? $posted : [];
    $items = [];
    $ids = [];
    $n = 0;
    foreach ($posted as $key => $row) {
        if (!is_array($row) || !preg_match('/^[0-9]{1,6}$/', (string) $key)) {
            continue;
        }
        $n++;
        $p = 'items.' . $key;
        $label = $in->text($p . '.label', 'عنوان البطاقة ' . $n, ['max' => 120]);
        $value = $in->number($p . '.value', 'الرقم في البطاقة ' . $n, ['min' => 0, 'max' => 999999999999]);
        $suffix = $in->text($p . '.suffix', 'اللاحقة في البطاقة ' . $n, ['max' => 12]);
        if ($label === '' && $value === null && $suffix === '') {
            continue; // empty row = removed
        }
        if ($label === '') {
            $in->error($p . '.label', 'اكتب عنوان البطاقة ' . $n . ' (مثل: الأسر المستفيدة).');
        }
        $id = $in->raw($p . '.id');
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $id) || isset($ids[$id])) {
            $id = 'n-' . bin2hex(random_bytes(3));
        }
        $ids[$id] = true;
        $items[] = [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'suffix' => $suffix,
            'icon' => $in->enum($p . '.icon', 'أيقونة البطاقة ' . $n, $allowedIcons, 'badge-check'),
            'activity' => $in->enum($p . '.activity', 'النشاط المرتبط بالبطاقة ' . $n, array_keys($activities), ''),
            'home' => $in->bool($p . '.home'),
            'achievements' => $in->bool($p . '.achievements'),
        ];
    }
    if (count($items) > 24) {
        $in->error('items', 'الحد الأقصى 24 بطاقة.');
    }
    $new = ['updated_at' => $date, 'period' => $period, 'items' => $items];
    $nowVisible = $visible($new);
    if ($nowVisible && $nowVisible !== $visible($stored) && !$in->bool('verified')) {
        $in->error('verified', 'أكّد أن الأرقام التي ستظهر على الموقع موثقة ومعتمدة من إدارة المؤسسة قبل نشرها.');
    }

    if ($in->ok()) {
        $conflict = false;
        try {
            $result = adm_content_save('stats', function ($cur) use ($new, $rev, $itemDefaults, &$conflict) {
                $cur = is_array($cur) ? $cur : [];
                if (!hash_equals(adm_rev($cur), $rev)) {
                    $conflict = true;
                    return $cur;
                }
                $old = [];
                foreach ((array) ($cur['items'] ?? []) as $it) {
                    if (is_array($it) && !empty($it['id'])) {
                        $old[(string) $it['id']] = $it;
                    }
                }
                $items = [];
                foreach ($new['items'] as $it) {
                    // Keys the developer added to a card are kept.
                    $items[] = array_replace($itemDefaults, $old[$it['id']] ?? [], $it);
                }
                return array_replace($cur, ['updated_at' => $new['updated_at'], 'period' => $new['period'], 'items' => $items]);
            }, 'stats.save', function ($old, $newData) use ($visible, $normalize) {
                $o = $normalize(is_array($old) ? $old : []);
                $nn = $normalize($newData);
                $parts = [];
                if ($o['updated_at'] !== $nn['updated_at']) {
                    $parts[] = 'تاريخ المراجعة: ' . ($nn['updated_at'] !== '' ? $nn['updated_at'] : '—');
                }
                if ($o['period'] !== $nn['period']) {
                    $parts[] = 'الفترة';
                }
                if (count($o['items']) !== count($nn['items'])) {
                    $parts[] = 'عدد البطاقات: ' . count($nn['items']);
                }
                $v = $visible($nn);
                $parts[] = 'الأرقام الظاهرة: ' . count($v['items'] ?? []);
                return implode('، ', $parts);
            });
            if ($conflict) {
                adm_flash('warning', 'عدّل مستخدم آخر «أثرنا بالأرقام» أثناء عملك، فلم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول أدناه؛ راجعها (أو أعد تحميل الصفحة لترى النسخة الأحدث) ثم اضغط «حفظ الأرقام» مرة أخرى.');
            } else {
                adm_flash($result['changed'] ? 'success' : 'info', $result['changed'] ? 'حُفظت الأرقام. ' . adm_build_summary($result['report']) : 'لم يتغير شيء في الأرقام.');
                adm_redirect('stats');
            }
        } catch (AdmUserError $e) {
            adm_flash('error', $e->getMessage(), $e->details);
        }
    }
    AdmForm::$errors = $in->errors();
    // Show the form again with what was typed.
    $form = [
        'updated_at' => adm_clean($in->raw('updated_at')),
        'period' => adm_clean($in->raw('period')),
        'items' => array_filter($posted, 'is_array'),
    ];
    $keepKeys = true;
}

$storedById = [];
foreach ($stored['items'] as $it) {
    $storedById[(string) $it['id']] = $it;
}
$visibleNow = $visible($stored);
$shownCount = count($visibleNow['items'] ?? []);
$iconSvg = function (string $name): string {
    return adm_icon($name !== '' ? $name : 'badge-check');
};
$str = function ($x): string {
    if (is_bool($x)) {
        return $x ? '1' : '';
    }
    return is_scalar($x) ? (string) $x : '';
};

$rows = adm_repeater([
    'path' => 'items',
    'items' => $form['items'],
    'keep_keys' => $keepKeys,
    'blank' => ['id' => '', 'label' => '', 'value' => '', 'suffix' => '', 'icon' => 'badge-check', 'activity' => '', 'home' => false, 'achievements' => true],
    'add_label' => 'إضافة بطاقة رقم',
    'max' => 24,
    'empty' => 'لا توجد بطاقات أرقام. أضف بطاقة لكل إنجاز موثق.',
    'legend_prefix' => 'البطاقة',
    'remove_confirm' => 'حذف هذه البطاقة؟ لن تُحذف نهائيًا حتى تضغط «حفظ الأرقام».',
    'row' => function (string $path, array $item) use ($icons, $activities, $iconSvg, $str, $storedById, $stored, $hasValue) {
        $id = $str($item['id'] ?? '');
        $icon = $str($item['icon'] ?? '') !== '' ? $str($item['icon']) : 'badge-check';
        $iconOptions = $icons;
        if (!isset($iconOptions[$icon])) {
            $iconOptions = [$icon => $icon] + $iconOptions;
        }
        $state = '';
        if ($id !== '' && isset($storedById[$id])) {
            $s = $storedById[$id];
            if ($hasValue($s['value']) && trim($stored['updated_at']) !== '') {
                $state = adm_badge('يظهر على الموقع', 'green');
            } else {
                $state = adm_badge(t('ui.pending_number'), 'amber');
            }
        }
        return '<input type="hidden" name="' . e(adm_name($path . '.id')) . '" value="' . e($id) . '">'
            . '<div class="adm-statrow">'
            . '<span class="adm-statrow__icon" data-icon-preview>' . $iconSvg($icon) . '</span>'
            . '<div class="adm-statrow__main">'
            . ($state !== '' ? '<p class="adm-statrow__state">' . $state . '</p>' : '')
            . '<div class="adm-statrow__grid">'
            . adm_field(['path' => $path . '.label', 'label' => 'عنوان البطاقة', 'value' => $str($item['label'] ?? ''), 'max' => 120, 'placeholder' => 'مثل: الأسر المستفيدة', 'class' => 'adm-statrow__label'])
            . adm_field(['path' => $path . '.value', 'label' => 'الرقم', 'type' => 'number', 'value' => $str($item['value'] ?? ''), 'placeholder' => 'فارغ حتى يُعتمد'])
            . adm_field(['path' => $path . '.suffix', 'label' => 'لاحقة (اختياري)', 'value' => $str($item['suffix'] ?? ''), 'max' => 12, 'placeholder' => 'مثل: +'])
            . adm_field(['path' => $path . '.icon', 'label' => 'الأيقونة', 'type' => 'select', 'value' => $icon, 'options' => $iconOptions, 'attrs' => ['data-icon-select' => '1']])
            . adm_field(['path' => $path . '.activity', 'label' => 'يظهر أيضًا في صفحة النشاط', 'type' => 'select', 'value' => $str($item['activity'] ?? ''), 'options' => $activities, 'class' => 'adm-statrow__activity'])
            . '</div>'
            . '<div class="adm-statrow__checks">'
            . adm_field(['type' => 'checkbox', 'path' => $path . '.home', 'label' => 'يظهر في الصفحة الرئيسية', 'value' => !empty($item['home'])])
            . adm_field(['type' => 'checkbox', 'path' => $path . '.achievements', 'label' => 'يظهر في صفحة «إنجازاتنا»', 'value' => !empty($item['achievements'])])
            . '</div>'
            . '</div></div>';
    },
]);

$iconSet = '';
foreach (array_keys($icons) as $name) {
    $iconSet .= '<span data-icon="' . e($name) . '">' . $iconSvg($name) . '</span>';
}
?>
<div class="adm-rule">
  <span class="adm-rule__icon"><?= adm_icon('shield-check') ?></span>
  <div>
    <p class="adm-rule__title">لا تُنشر أرقام غير موثقة</p>
    <p>يظهر الرقم على الموقع <strong>فقط</strong> إذا كانت له قيمة <strong>و</strong>أُدخل «تاريخ مراجعة الأرقام». البطاقة التي لا قيمة لها تظهر للزوار بعبارة «<?= te('ui.pending_number') ?>». أدخل الأرقام بعد اعتمادها من إدارة المؤسسة، مع الفترة الزمنية التي تغطيها.</p>
  </div>
</div>

<?= adm_errors_summary(AdmForm::$errors) ?>
<?= adm_form_open(['action' => adm_url('stats'), 'dirty' => true, 'class' => 'adm-stats-form']) ?>
<input type="hidden" name="rev" value="<?= e(adm_rev($raw)) ?>">

<?= adm_card('المراجعة والفترة', '<div class="adm-cols">'
    . adm_field(['path' => 'updated_at', 'label' => 'تاريخ مراجعة الأرقام', 'type' => 'date', 'value' => $form['updated_at'], 'dir' => 'ltr',
        'hint' => 'تاريخ آخر مراجعة واعتماد للأرقام. يظهر للزوار بجوار الأرقام. بدونه لا يظهر أي رقم.'])
    . adm_field(['path' => 'period', 'label' => 'الفترة التي تغطيها الأرقام', 'value' => $form['period'], 'max' => 120, 'placeholder' => 'مثل: منذ التأسيس حتى سبتمبر 2026'])
    . '</div>'
    . '<p class="adm-statline">' . adm_icon($shownCount > 0 ? 'circle-check' : 'info')
    . '<span>' . ($shownCount > 0
        ? 'يظهر الآن على الموقع ' . e(adm_plural($shownCount, 'رقم واحد', 'رقمان', 'أرقام', 'رقمًا')) . ' من ' . count($stored['items']) . '.'
        : 'لا يظهر أي رقم على الموقع حاليًا' . (trim($stored['updated_at']) === '' ? ' لأن تاريخ المراجعة غير مُدخل.' : '.')) . '</span></p>',
    ['icon' => 'calendar', 'id' => 'stats-meta']) ?>

<?= adm_card('بطاقات الأرقام', '<ul class="adm-hintlist" role="list">'
    . '<li><strong>الرقم:</strong> أرقام فقط مثل 1250 (تُقبل الأرقام العربية ٠١٢٣). اتركه فارغًا حتى يُعتمد؛ فتظهر البطاقة للزوار بعبارة «' . te('ui.pending_number') . '».</li>'
    . '<li><strong>اللاحقة:</strong> تظهر بعد الرقم، مثل «+» أو «أسرة».</li>'
    . '<li><strong>أين تظهر:</strong> اختر لكل بطاقة الصفحة الرئيسية و/أو صفحة «إنجازاتنا»، وصفحة النشاط المرتبط بها إن وُجد. يظهر الترتيب على الموقع كما هو هنا.</li>'
    . '</ul>' . $rows,
    ['icon' => 'chart-column', 'id' => 'stats-items']) ?>

<?= adm_field(['type' => 'checkbox', 'path' => 'verified', 'label' => 'أؤكد أن الأرقام التي ستظهر على الموقع موثقة ومعتمدة من إدارة المؤسسة وتخص الفترة المذكورة.', 'value' => false, 'class' => 'adm-field--confirm',
    'hint' => 'مطلوب عند نشر أرقام جديدة أو تغيير رقم ظاهر أو تاريخ مراجعته.']) ?>

<div class="adm-savebar adm-savebar--sticky">
  <?= adm_submit('حفظ الأرقام', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…']) ?>
  <span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span>
</div>
<?= adm_form_close() ?>
<div class="adm-iconset" data-icon-set hidden><?= $iconSet ?></div>
