<?php
/**
 * «الإعدادات» (admins only): content/settings.json — site URL, logo, contact,
 * social links, legal identity, donations (enabled, allocation note, methods).
 * Unknown keys already in the file are preserved.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'الإعدادات';
AdmPage::$lead = 'بيانات التواصل والروابط الرسمية والهوية القانونية ووسائل التبرع. الحقول الفارغة لا تظهر على الموقع.';

$current = (array) adm_content_load('settings');
$s = array_replace_recursive(bm_settings_defaults(), $current);
$s['donations']['methods'] = array_values(array_filter((array) ($current['donations']['methods'] ?? []), 'is_array'));
$methodKeys = ['title', 'details', 'link', 'note'];

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $in = new AdmInput($_POST);
    $new = $s;

    $url = $in->url('site_url', 'عنوان الموقع');
    if ($url !== '') {
        $parts = parse_url($url);
        $url = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host'])
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . rtrim((string) ($parts['path'] ?? ''), '/');
    }
    $new['site_url'] = $url;

    $new['contact']['hotline'] = $in->phone('contact.hotline', 'الخط الساخن', ['required' => true]);
    $new['contact']['whatsapp_general'] = $in->phone('contact.whatsapp_general', 'واتساب المؤسسة العام');
    $new['contact']['whatsapp_legal'] = $in->phone('contact.whatsapp_legal', 'واتساب «إحنا معاكي»');
    $new['contact']['address'] = $in->text('contact.address', 'المركز الرئيسي', ['max' => 200]);
    $new['contact']['address_detail'] = $in->text('contact.address_detail', 'العنوان التفصيلي', ['max' => 300]);
    $new['contact']['email'] = $in->email('contact.email', 'البريد الإلكتروني الرسمي');
    $new['contact']['hours'] = $in->text('contact.hours', 'ساعات استقبال الرسائل', ['max' => 200]);
    $new['contact']['map_url'] = $in->url('contact.map_url', 'رابط الخريطة');

    $new['social']['facebook'] = $in->url('social.facebook', 'صفحة فيسبوك');
    $new['social']['instagram'] = $in->url('social.instagram', 'صفحة إنستجرام');

    $reg = adm_digits_latin($in->text('legal.registration_number', 'رقم الإشهار', ['max' => 40]));
    $new['legal']['registration_number'] = $reg;
    $new['legal']['registration_year'] = $in->year('legal.registration_year', 'سنة الإشهار');
    $new['legal']['registration_authority'] = $in->text('legal.registration_authority', 'جهة الإشهار', ['max' => 200]);
    $new['legal']['public_benefit'] = $in->text('legal.public_benefit', 'صفة النفع العام', ['max' => 400, 'multiline' => true]);
    $new['legal']['fundraising_permit'] = $in->text('legal.fundraising_permit', 'تصريح جمع التبرعات', ['max' => 400, 'multiline' => true]);

    $new['donations']['enabled'] = $in->bool('donations.enabled');
    $new['donations']['allocation_note'] = $in->text('donations.allocation_note', 'ملاحظة توجيه التبرعات', ['max' => 1000, 'multiline' => true]);
    $methods = [];
    $posted = adm_post('donations.methods', []);
    $n = 0;
    foreach (is_array($posted) ? $posted : [] as $key => $row) {
        if (!is_array($row) || !preg_match('/^[0-9]{1,6}$/', (string) $key)) {
            continue;
        }
        $n++;
        $p = 'donations.methods.' . $key;
        $m = [
            'title' => $in->text($p . '.title', 'اسم الوسيلة (' . $n . ')', ['max' => 120]),
            'details' => $in->text($p . '.details', 'بيانات الوسيلة (' . $n . ')', ['max' => 600, 'multiline' => true]),
            // The site shows official https:// links only (bm_donation_methods()), so nothing else is accepted.
            'link' => $in->url($p . '.link', 'رابط التبرع (' . $n . ')', ['https_only' => true,
                'https_error' => 'يُقبل رابط التبرع الآمن فقط، أي الذي يبدأ بـ https:// — الروابط التي تبدأ بـ http:// لا تظهر على الموقع. انسخ الرابط الرسمي الآمن من صفحة الجهة المصدرة.']),
            'note' => $in->text($p . '.note', 'ملاحظة (' . $n . ')', ['max' => 300]),
        ];
        $linkTyped = trim($in->raw($p . '.link')) !== '';
        if (implode('', $m) === '' && !$linkTyped) {
            continue; // empty row = removed
        }
        if ($m['title'] === '') {
            $in->error($p . '.title', 'اكتب اسم وسيلة التبرع رقم ' . $n . ' (مثل: حساب بنكي، محفظة إلكترونية).');
        }
        if ($m['details'] === '' && $m['link'] === '' && !$linkTyped) {
            $in->error($p . '.details', 'أدخل بيانات الوسيلة رقم ' . $n . ' أو رابطها.');
        }
        $methods[] = $m;
    }
    if (count($methods) > 12) {
        $in->error('donations.enabled', 'الحد الأقصى 12 وسيلة تبرع.');
    }
    $new['donations']['methods'] = $methods;
    if ($new['donations']['enabled']) {
        if (!$methods) {
            $in->error('donations.enabled', 'لا يمكن تفعيل التبرع قبل إضافة وسيلة تبرع رسمية واحدة على الأقل.');
        }
        $changed = !$s['donations']['enabled'] || $methods !== $s['donations']['methods'];
        if ($changed && !$in->bool('donations.verified')) {
            $in->error('donations.verified', 'أكّد أنك تحققت من ملكية الحسابات وتصريح جمع التبرعات قبل نشر وسائل التبرع.');
        }
    }

    // Logo (raster only; stored as PNG to keep transparency).
    $oldLogo = (string) $s['logo'];
    $logoFiles = adm_files('logo_file');
    $logoFile = $logoFiles ? reset($logoFiles) : null;
    if ($in->bool('logo_remove')) {
        $new['logo'] = '';
    }
    $uploaded = null;
    if ($in->ok() && $logoFile !== null) {
        try {
            $uploaded = adm_image_upload($logoFile, [
                'alt' => 'شعار مؤسسة بيوت مصر',
                'require_alt' => false,
                'max_w' => 800,
                'max_h' => 400,
                'thumb_w' => 0,
                'keep_alpha' => true,
                'prefix' => 'logo',
            ]);
            $new['logo'] = $uploaded['src'];
        } catch (AdmUserError $e) {
            $in->error('logo_file', $e->getMessage());
        }
    }

    if ($in->ok()) {
        try {
            $result = adm_content_save('settings', function ($cur) use ($new) {
                $merged = array_replace_recursive(is_array($cur) ? $cur : [], $new);
                $merged['donations']['methods'] = $new['donations']['methods'];
                return $merged;
            }, 'settings.save', function ($old, $newData) {
                $changed = [];
                foreach (['site_url', 'logo'] as $k) {
                    if (($old[$k] ?? '') !== ($newData[$k] ?? '')) {
                        $changed[] = $k;
                    }
                }
                foreach (['contact', 'social', 'legal', 'donations'] as $g) {
                    foreach ((array) ($newData[$g] ?? []) as $k => $v) {
                        if (($old[$g][$k] ?? null) !== $v) {
                            $changed[] = $g . '.' . $k;
                        }
                    }
                }
                return $changed ? implode('، ', $changed) : '';
            });
            if ($result['changed']) {
                if ($oldLogo !== '' && $oldLogo !== $new['logo']) {
                    adm_delete_upload($oldLogo);
                }
                adm_flash('success', 'حُفظت الإعدادات. ' . adm_build_summary($result['report']));
            } else {
                adm_flash('info', 'لم تتغير أي إعدادات.');
            }
            adm_redirect('settings');
        } catch (AdmUserError $e) {
            if ($uploaded !== null) {
                adm_delete_image($uploaded);
            }
            adm_flash('error', $e->getMessage(), $e->details);
        }
    } elseif ($uploaded !== null) {
        adm_delete_image($uploaded);
    }
    AdmForm::$errors = $in->errors();
    // Show the form again with what the editor typed.
    $s = $new;
    $s['logo'] = $oldLogo;
    $s['donations']['methods'] = array_filter(is_array($posted) ? $posted : [], 'is_array');
    $keepKeys = true;
    // Fields with errors show exactly what was typed, so it can be corrected.
    foreach (array_keys(AdmForm::$errors) as $errPath) {
        $raw = adm_post($errPath);
        if (is_string($raw) && strpos($errPath, 'donations.methods.') !== 0) {
            adm_path_set($s, $errPath, adm_clean($raw, true));
        }
    }
}

$v = function (string $path) use ($s): string {
    $x = adm_path_get($s, $path, '');
    return is_scalar($x) ? (string) $x : '';
};
$f = function (string $path, string $label, array $o = []) use ($v): string {
    return adm_field($o + ['path' => $path, 'label' => $label, 'value' => $v($path)]);
};
$logoRecord = $s['logo'] !== '' ? ['src' => $s['logo'], 'thumb' => $s['logo'], 'alt' => 'الشعار الحالي'] : null;
?>
<?= adm_errors_summary(AdmForm::$errors) ?>
<?= adm_form_open(['action' => adm_url('settings'), 'files' => true, 'dirty' => true, 'class' => 'adm-settings']) ?>

<nav class="adm-chips" aria-label="أقسام الإعدادات">
  <ul role="list">
    <li><a href="#set-site">الموقع والشعار</a></li>
    <li><a href="#set-contact">التواصل</a></li>
    <li><a href="#set-social">الصفحات الرسمية</a></li>
    <li><a href="#set-legal">الهوية القانونية</a></li>
    <li><a href="#set-donations">التبرعات</a></li>
  </ul>
</nav>

<?= adm_card('الموقع والشعار', ''
    . $f('site_url', 'عنوان الموقع (النطاق)', ['type' => 'url', 'placeholder' => 'https://www.example.org', 'max' => 200,
        'hint' => 'الرابط الكامل للموقع بعد ربط النطاق. يُستخدم لخريطة الموقع (sitemap) والروابط الأساسية ومشاركة الصفحات. اتركه فارغًا حتى يُربط النطاق.'])
    . adm_image_field([
        'name' => 'logo_file',
        'label' => 'شعار المؤسسة',
        'current' => $logoRecord,
        'no_alt' => true,
        'remove_path' => 'logo_remove',
        'remove_label' => 'إزالة الشعار المرفوع والعودة إلى الشعار المرسوم',
        'hint' => 'يُفضّل ملف PNG بخلفية شفافة وعرض 600 بكسل على الأقل. يظهر في رأس الصفحات. ملفات SVG غير مقبولة لأسباب أمنية.',
    ]), ['icon' => 'house', 'id' => 'set-site']) ?>

<?= adm_card('التواصل', '<div class="adm-cols">'
    . $f('contact.hotline', 'الخط الساخن', ['type' => 'tel', 'required' => true, 'max' => 24, 'hint' => 'يظهر في كل الصفحات ويُستخدم في النصوص مكان {hotline}.'])
    . $f('contact.whatsapp_general', 'واتساب المؤسسة العام', ['type' => 'tel', 'max' => 24, 'hint' => 'رقم زر واتساب العائم ونموذج الرسائل.'])
    . $f('contact.whatsapp_legal', 'واتساب مبادرة «إحنا معاكي»', ['type' => 'tel', 'max' => 24, 'hint' => 'للاستشارات القانونية فقط.'])
    . $f('contact.email', 'البريد الإلكتروني الرسمي', ['type' => 'email', 'max' => 120])
    . '</div>'
    . $f('contact.address', 'المركز الرئيسي', ['max' => 200, 'hint' => 'مثل: القاهرة — مدينة نصر — الحي العاشر'])
    . $f('contact.address_detail', 'العنوان التفصيلي', ['max' => 300, 'hint' => 'يُضاف بعد اعتماد العنوان الرسمي (قائمة المراجعة قبل الإطلاق).'])
    . $f('contact.hours', 'ساعات استقبال الرسائل', ['max' => 200, 'hint' => 'مثل: يوميًا عدا الجمعة من 10 صباحًا حتى 6 مساءً'])
    . $f('contact.map_url', 'رابط الموقع على الخريطة', ['type' => 'url', 'max' => 500, 'placeholder' => 'https://maps.google.com/…']),
    ['icon' => 'phone', 'id' => 'set-contact']) ?>

<?= adm_card('الصفحات الرسمية', '<div class="adm-cols">'
    . $f('social.facebook', 'صفحة فيسبوك', ['type' => 'url', 'max' => 500, 'placeholder' => 'https://www.facebook.com/…'])
    . $f('social.instagram', 'صفحة إنستجرام', ['type' => 'url', 'max' => 500, 'placeholder' => 'https://www.instagram.com/…'])
    . '</div><p class="adm-hint">أضف الصفحات الرسمية الموثقة فقط.</p>',
    ['icon' => 'share-2', 'id' => 'set-social']) ?>

<?= adm_card('الهوية القانونية', adm_alert('لا تُكتب صفة النفع العام وتصاريح جمع التبرعات إلا بعد إثباتها، وبالنص المطابق للمستندات المعتمدة بأرقامها ونطاقها وتواريخ صلاحيتها.', 'warning')
    . '<div class="adm-cols">'
    . $f('legal.registration_number', 'رقم الإشهار', ['max' => 40, 'dir' => 'ltr', 'hint' => 'يُستخدم في النصوص مكان {registration_number}.'])
    . $f('legal.registration_year', 'سنة الإشهار', ['max' => 4, 'dir' => 'ltr', 'attrs' => ['inputmode' => 'numeric'], 'hint' => 'يُستخدم مكان {registration_year}.'])
    . '</div>'
    . $f('legal.registration_authority', 'جهة الإشهار', ['max' => 200])
    . $f('legal.public_benefit', 'صفة النفع العام', ['type' => 'textarea', 'rows' => 2, 'max' => 400])
    . $f('legal.fundraising_permit', 'تصريح جمع التبرعات', ['type' => 'textarea', 'rows' => 2, 'max' => 400]),
    ['icon' => 'landmark', 'id' => 'set-legal']) ?>

<?php
$methodsHtml = adm_repeater([
    'path' => 'donations.methods',
    'items' => $s['donations']['methods'],
    'keep_keys' => !empty($keepKeys),
    'blank' => array_fill_keys($methodKeys, ''),
    'add_label' => 'إضافة وسيلة تبرع',
    'max' => 12,
    'empty' => 'لا توجد وسائل تبرع مضافة.',
    'legend_prefix' => 'وسيلة التبرع',
    'remove_confirm' => 'حذف وسيلة التبرع هذه؟ لن تُحذف نهائيًا حتى تضغط «حفظ الإعدادات».',
    'row' => function (string $path, array $item) {
        $val = function (string $k) use ($item): string {
            return isset($item[$k]) && is_string($item[$k]) ? $item[$k] : '';
        };
        return '<div class="adm-cols">'
            . adm_field(['path' => $path . '.title', 'label' => 'اسم الوسيلة', 'value' => $val('title'), 'max' => 120, 'placeholder' => 'مثل: حساب بنكي باسم المؤسسة'])
            . adm_field(['path' => $path . '.link', 'label' => 'رابط التبرع الرسمي (اختياري)', 'type' => 'url', 'value' => $val('link'), 'max' => 500,
                'placeholder' => 'https://…', 'dir' => 'ltr', 'hint' => 'رابط آمن يبدأ بـ https:// فقط.'])
            . '</div>'
            . adm_field(['path' => $path . '.details', 'label' => 'البيانات (رقم الحساب، اسم البنك، رقم المحفظة…)', 'type' => 'textarea', 'rows' => 3, 'value' => $val('details'), 'max' => 600])
            . adm_field(['path' => $path . '.note', 'label' => 'ملاحظة (اختياري)', 'value' => $val('note'), 'max' => 300]);
    },
]);
echo adm_card('التبرعات', adm_alert('لا تُنشر أي وسيلة تبرع إلا بعد التأكد من ملكية الحساب البنكي أو المحفظة باسم المؤسسة، ومن وجود تصريح جمع التبرعات ونطاقه. استخدم روابط التبرع الرسمية فقط.', 'warning', 'قبل النشر')
    . adm_field(['type' => 'checkbox', 'path' => 'donations.enabled', 'label' => 'إظهار وسائل التبرع على الموقع', 'value' => !empty($s['donations']['enabled']),
        'hint' => 'عند الإيقاف تعرض صفحة «شارك في الخير» أن وسائل التبرع تُعلن فور اعتمادها.'])
    . adm_field(['path' => 'donations.allocation_note', 'type' => 'textarea', 'rows' => 3, 'label' => 'ملاحظة توجيه التبرعات (اختياري)', 'value' => (string) $s['donations']['allocation_note'], 'max' => 1000,
        'hint' => 'مثل: توضيح كيف تُوجَّه التبرعات إلى النشاط الذي يختاره المتبرع.'])
    . '<h3 class="adm-subtitle">وسائل التبرع الرسمية</h3>' . $methodsHtml
    . adm_field(['type' => 'checkbox', 'path' => 'donations.verified', 'label' => 'أؤكد أنني تحققت من ملكية الحسابات ووسائل الدفع المذكورة باسم المؤسسة ومن تصريح جمع التبرعات.', 'value' => false, 'class' => 'adm-field--confirm',
        'hint' => 'مطلوب عند تفعيل التبرع أو تغيير وسائله.']),
    ['icon' => 'hand-coins', 'id' => 'set-donations']);
?>

<div class="adm-savebar adm-savebar--sticky">
  <?= adm_submit('حفظ الإعدادات', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…']) ?>
  <span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span>
</div>
<?= adm_form_close() ?>
