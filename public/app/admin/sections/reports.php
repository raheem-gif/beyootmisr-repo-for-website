<?php
/**
 * «التقارير» — content/reports.json: {id, title, year, description, file, size, published}.
 *
 *   ?p=reports                 list
 *   ?p=reports&id=new|<id>     upload / edit (title, year, description, published, replace the PDF) + delete
 *
 * PDFs only (uploads.php: finfo application/pdf + "%PDF-" magic, ≤15 MB, random name in
 * uploads/reports). Content document §08: reports are published «بعد مراجعتها واعتماد نشرها» →
 * publishing a report (or a new file of a published report) requires the review confirmation.
 * Deleting a report, or replacing its file, deletes the old PDF.
 */
defined('BM_APP') || exit;

$list = adm_list_load('reports');
$id = adm_req_id('id');
$isPost = strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
$action = $isPost && isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$defaults = ['id' => '', 'title' => '', 'year' => '', 'description' => '', 'file' => '', 'size' => 0, 'published' => false];

$idx = -1;
if ($id === '!' || ($id !== '' && $id !== 'new' && ($idx = adm_list_index($list, $id)) < 0)) {
    adm_flash('error', 'لم نجد هذا التقرير؛ ربما حُذف.');
    adm_redirect('reports');
}
$record = $idx >= 0 ? $list[$idx] : null;
$r = array_replace($defaults, $record ?? []);
$rev = $record !== null ? adm_rec_rev($record) : '';
$fv = [];

$fileLink = function (string $file): string {
    return adm_public_url($file);
};
$sizeText = function (array $rec): string {
    $bytes = (int) ($rec['size'] ?? 0);
    $file = (string) ($rec['file'] ?? '');
    if ($bytes <= 0 && $file !== '' && strpos($file, '..') === false && is_file(BM_PUBLIC . '/' . ltrim($file, '/'))) {
        $bytes = (int) filesize(BM_PUBLIC . '/' . ltrim($file, '/'));
    }
    return $bytes > 0 ? adm_bytes($bytes) : '';
};

/* ---------------- POST: delete ---------------- */
if ($action === 'delete' && $record !== null) {
    $conflict = false;
    $file = '';
    try {
        $result = adm_content_save('reports', function ($data) use ($id, &$conflict, &$file) {
            $data = array_values(is_array($data) ? $data : []);
            $i = adm_list_index($data, $id);
            if ($i < 0) {
                throw new AdmUserError('هذا التقرير لم يعد موجودًا؛ ربما حذفه مستخدم آخر.');
            }
            if (!hash_equals(adm_rec_rev($data[$i]), adm_post_str('rev'))) {
                $conflict = true;
                return $data;
            }
            $file = (string) ($data[$i]['file'] ?? '');
            array_splice($data, $i, 1);
            return $data;
        }, 'reports.delete', '«' . $r['title'] . '»' . ($r['year'] !== '' ? ' (' . $r['year'] . ')' : ''));
        if ($conflict) {
            adm_flash('warning', 'عدّل مستخدم آخر هذا التقرير قبل لحظات، فلم يُحذف. راجع النسخة الأحدث ثم احذفه إن أردت.');
            adm_redirect('reports', ['id' => $id]);
        }
        $n = adm_delete_unreferenced([$file]);
        adm_flash('success', 'حُذف التقرير «' . $r['title'] . '»' . ($n > 0 ? ' وملفه' : '') . '. ' . adm_build_summary($result['report']));
        adm_redirect('reports');
    } catch (AdmUserError $e) {
        adm_flash('error', $e->getMessage(), $e->details);
        adm_redirect('reports', ['id' => $id]);
    }
}

/* ---------------- POST: save ---------------- */
if ($action === 'save' && ($id === 'new' || $record !== null)) {
    $isNew = $record === null;
    $in = new AdmInput($_POST);
    $postedRev = $in->raw('rev');
    $fields = [
        'title' => $in->text('title', 'عنوان التقرير', ['max' => 160, 'required' => true]),
        'year' => $in->year('year', 'السنة'),
        'description' => $in->text('description', 'وصف قصير', ['max' => 1500, 'multiline' => true]),
        'published' => $in->bool('published'),
    ];
    if ($fields['year'] !== '' && (int) $fields['year'] > (int) date('Y')) {
        $in->error('year', '«السنة» لا يمكن أن تكون في المستقبل.');
    }
    $files = adm_files('pdf');
    $pdfFile = $files ? reset($files) : null;
    if ($isNew && $pdfFile === null) {
        $in->error('pdf', 'اختر ملف التقرير بصيغة PDF.');
    }
    $wasPublished = !$isNew && !empty($r['published']);
    if ($fields['published'] && (!$wasPublished || $pdfFile !== null) && !$in->bool('review_confirmed')) {
        $in->error('review_confirmed', 'أكّد أن إدارة المؤسسة راجعت التقرير واعتمدت نشره، وأنه لا يتضمن بيانات شخصية للمستفيدين، قبل نشره.');
    }
    if ($pdfFile !== null && !$in->ok() && !isset($in->errors()['pdf'])) {
        $in->error('pdf', 'لم يُرفع الملف بعد. صحّح الأخطاء ثم اختر الملف مرة أخرى.');
    }

    $uploaded = null;
    if ($in->ok() && $pdfFile !== null) {
        try {
            $uploaded = adm_pdf_upload($pdfFile);
        } catch (AdmUserError $e) {
            $in->error('pdf', $e->getMessage());
        }
    }
    if ($in->ok()) {
        $conflict = false;
        $oldFile = '';
        $newId = $isNew ? bin2hex(random_bytes(5)) : $id;
        try {
            $result = adm_content_save('reports', function ($data) use ($isNew, $id, $newId, $postedRev, $fields, $uploaded, $defaults, &$conflict, &$oldFile) {
                $data = array_values(is_array($data) ? $data : []);
                if ($isNew) {
                    $data[] = array_replace($defaults, ['id' => $newId], $fields, ['file' => $uploaded['file'], 'size' => $uploaded['size']]);
                    return $data;
                }
                $i = adm_list_index($data, $id);
                if ($i < 0) {
                    throw new AdmUserError('هذا التقرير لم يعد موجودًا؛ ربما حذفه مستخدم آخر.');
                }
                if (!hash_equals(adm_rec_rev($data[$i]), $postedRev)) {
                    $conflict = true;
                    return $data;
                }
                $rec = array_replace($defaults, $data[$i], $fields);
                if ($uploaded !== null) {
                    $oldFile = (string) ($data[$i]['file'] ?? '');
                    $rec['file'] = $uploaded['file'];
                    $rec['size'] = $uploaded['size'];
                }
                $data[$i] = $rec;
                return $data;
            }, $isNew ? 'reports.create' : 'reports.save', '«' . $fields['title'] . '»' . ($fields['year'] !== '' ? ' (' . $fields['year'] . ')' : '')
                . ($uploaded !== null && !$isNew ? '، ملف جديد' : '') . ($fields['published'] ? '، منشور (أُكّدت المراجعة)' : '، غير منشور'));
            if ($conflict) {
                if ($uploaded !== null) {
                    adm_delete_unreferenced([$uploaded['file']]);
                }
                adm_flash('warning', 'عدّل مستخدم آخر هذا التقرير أثناء عملك، فلم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول أدناه؛ راجعها ثم احفظ مرة أخرى'
                    . ($uploaded !== null ? ' (واختر الملف مرة أخرى)' : '') . '.');
            } else {
                if ($oldFile !== '') {
                    adm_delete_unreferenced([$oldFile]);
                }
                if ($result['changed']) {
                    adm_flash('success', ($isNew ? 'أُضيف التقرير «' : 'حُفظ التقرير «') . $fields['title'] . '». ' . adm_build_summary($result['report'])
                        . (!$fields['published'] ? ' التقرير غير منشور ولا يظهر للزوار.' : ''));
                } else {
                    adm_flash('info', 'لم يتغير شيء في التقرير.');
                }
                adm_redirect('reports', ['id' => $newId]);
            }
        } catch (AdmUserError $e) {
            if ($uploaded !== null) {
                adm_delete_unreferenced([$uploaded['file']]);
            }
            adm_flash('error', $e->getMessage(), $e->details);
        }
    } elseif ($uploaded !== null) {
        adm_delete_unreferenced([$uploaded['file']]);
    }
    AdmForm::$errors = $in->errors();
    foreach (['title', 'year', 'description'] as $k) {
        $fv[$k] = adm_clean($in->raw($k), true);
    }
    $fv['published'] = $in->bool('published');
    $fv['review_confirmed'] = $in->bool('review_confirmed');
}

/* ======================================================================== */
/* Render                                                                     */
/* ======================================================================== */
$s = function ($x): string {
    return is_scalar($x) ? (string) $x : '';
};
$val = function (string $k) use (&$fv, $r) {
    return array_key_exists($k, $fv) ? $fv[$k] : ($r[$k] ?? '');
};

if ($id === '') {
    AdmPage::$title = 'التقارير';
    AdmPage::$lead = 'تقارير النشاط السنوية وتقارير المشروعات بصيغة PDF. تظهر في صفحة «إنجازاتنا» بعد نشرها، من الأحدث إلى الأقدم.';
    AdmPage::$actions = adm_link_button('إضافة تقرير', adm_url('reports', ['id' => 'new']), ['variant' => 'primary', 'icon' => 'plus']);
    if (!$list) {
        echo adm_empty('scroll-text', 'لا توجد تقارير بعد', 'ارفع أول تقرير بزر «إضافة تقرير». حتى يُنشر تقرير تعرض صفحة الإنجازات عبارة «' . (string) t('achievements.reports_empty') . '»');
        return;
    }
    $rows = $list;
    usort($rows, function ($a, $b) {
        return strcmp((string) ($b['year'] ?? ''), (string) ($a['year'] ?? ''));
    });
    echo '<ul class="adm-items" role="list">';
    foreach ($rows as $row) {
        $row = array_replace($defaults, $row);
        $edit = adm_url('reports', ['id' => (string) $row['id']]);
        $size = $sizeText($row);
        echo '<li class="adm-item">'
            . '<a class="adm-item__media" href="' . e($edit) . '" tabindex="-1" aria-hidden="true"><span class="adm-item__thumb adm-item__ph adm-item__ph--pdf">' . adm_icon('file-text') . '<small>PDF</small></span></a>'
            . '<div class="adm-item__body">'
            . '<h2 class="adm-item__title"><a href="' . e($edit) . '">' . e((string) $row['title']) . '</a></h2>'
            . (trim((string) $row['description']) !== '' ? '<p class="adm-item__meta">' . e(excerpt((string) $row['description'], 120)) . '</p>' : '')
            . '<p class="adm-item__badges">' . (!empty($row['published']) ? adm_badge('منشور', 'green') : adm_badge('غير منشور', 'neutral'))
            . ($row['year'] !== '' ? adm_badge((string) $row['year'], 'blue') : '')
            . ($size !== '' ? adm_badge($size, 'neutral') : '')
            . ((string) $row['file'] === '' ? adm_badge('بلا ملف', 'red') : '')
            . '</p></div>'
            . '<div class="adm-item__actions">'
            . adm_link_button('تعديل', $edit, ['icon' => 'pencil', 'small' => true])
            . ((string) $row['file'] !== '' ? adm_link_button('فتح الملف', $fileLink((string) $row['file']), ['icon' => 'external-link', 'small' => true, 'variant' => 'ghost', 'external' => true]) : '')
            . '</div></li>';
    }
    echo '</ul>';
    return;
}

$isNew = $record === null;
AdmPage::$title = $isNew ? 'تقرير جديد' : (string) $r['title'];
AdmPage::$lead = 'ارفع التقرير بصيغة PDF بعد مراجعته واعتماد نشره من إدارة المؤسسة.';
AdmPage::$actions = adm_link_button('كل التقارير', adm_url('reports'), ['icon' => 'arrow-right', 'variant' => 'ghost']);

$current = '';
if (!$isNew && (string) $r['file'] !== '') {
    $size = $sizeText($r);
    $current = '<p class="adm-upload__file">' . adm_icon('file-text') . '<a href="' . e($fileLink((string) $r['file'])) . '" target="_blank" rel="noopener">الملف الحالي'
        . ($size !== '' ? ' (' . e($size) . ')' : '') . '</a></p>';
}

echo adm_errors_summary(AdmForm::$errors);
echo adm_form_open(['action' => adm_url('reports', ['id' => $isNew ? 'new' : $id]), 'files' => true, 'dirty' => true, 'class' => 'adm-edit', 'id' => 'report-form']);
echo '<input type="hidden" name="action" value="save"><input type="hidden" name="rev" value="' . e($rev) . '">';

echo adm_card('بيانات التقرير', ''
    . adm_field(['path' => 'title', 'label' => 'عنوان التقرير', 'required' => true, 'value' => $s($val('title')), 'max' => 160, 'placeholder' => 'مثل: تقرير النشاط السنوي'])
    . '<div class="adm-cols">'
    . adm_field(['path' => 'year', 'label' => 'السنة', 'value' => $s($val('year')), 'max' => 4, 'dir' => 'ltr', 'attrs' => ['inputmode' => 'numeric'], 'placeholder' => date('Y'),
        'hint' => 'تُرتَّب التقارير على الموقع من الأحدث إلى الأقدم.'])
    . '</div>'
    . adm_field(['path' => 'description', 'label' => 'وصف قصير (اختياري)', 'type' => 'textarea', 'rows' => 3, 'value' => $s($val('description')), 'max' => 1500]),
    ['icon' => 'scroll-text', 'id' => 'report-data']);

echo adm_card('ملف التقرير', $current . adm_pdf_field([
    'name' => 'pdf',
    'label' => $isNew ? 'اختر ملف التقرير (PDF)' : 'استبدال الملف (اختياري)',
    'hint' => $isNew ? 'مطلوب.' : 'اختر ملفًا جديدًا فقط إن أردت استبدال الحالي؛ يُحذف الملف القديم بعد الحفظ.',
]) . '<p class="adm-hint">راجع التقرير قبل رفعه: لا يتضمن صور بطاقات أو مستندات طبية أو بيانات شخصية للمستفيدين، ويُفضّل حذف اسم كاتبه من خصائص الملف قبل تصديره.</p>',
    ['icon' => 'file-text', 'id' => 'report-file']);

echo adm_card('النشر', adm_field(['type' => 'checkbox', 'path' => 'published', 'label' => 'نشر التقرير في صفحة «إنجازاتنا»', 'value' => !empty($val('published'))])
    . adm_field(['type' => 'checkbox', 'path' => 'review_confirmed', 'label' => 'أؤكد أن إدارة المؤسسة راجعت التقرير واعتمدت نشره، وأنه لا يتضمن بيانات شخصية للمستفيدين.',
        'value' => !empty($fv['review_confirmed']), 'class' => 'adm-field--confirm', 'hint' => 'مطلوب عند نشر التقرير وعند استبدال ملف تقرير منشور.']),
    ['icon' => 'shield-check', 'id' => 'report-publish']);

echo '<div class="adm-savebar adm-savebar--sticky">' . adm_submit($isNew ? 'إضافة التقرير' : 'حفظ التقرير', ['icon' => 'save', 'busy' => 'جارٍ الرفع والحفظ…'])
    . '<span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span></div>';
echo adm_form_close();

if (!$isNew) {
    echo adm_card('حذف التقرير', '<p>يُحذف التقرير وملفه نهائيًا من الموقع. لإخفائه مؤقتًا ألغِ «نشر التقرير» بدلًا من الحذف.</p>'
        . adm_action_button(['label' => 'حذف التقرير نهائيًا', 'fields' => ['action' => 'delete', 'rev' => $rev], 'variant' => 'danger', 'icon' => 'trash-2',
            'confirm' => 'حذف التقرير «' . $r['title'] . '» وملفه نهائيًا؟ لا يمكن التراجع عن ذلك.']),
        ['icon' => 'trash-2', 'class' => 'adm-card--danger', 'id' => 'report-delete']);
}
