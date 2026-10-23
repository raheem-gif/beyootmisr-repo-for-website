<?php
/**
 * «الحكايات» — content/stories.json (schema: bm_story_defaults() in app/lib/data.php).
 *
 *   ?p=stories                 list (f = published | draft)
 *   ?p=stories&id=new|<id>     create / edit (type with its editorial guide, title, slug, summary,
 *                              body, activity, date, images, published) + delete
 *
 * Content document §07/§12: stories never reveal names, faces or identifying details except
 * with clear rules and consent. Publishing a story — or changing a published one — requires the
 * privacy confirmation checkbox (checked here, on the server).
 */
defined('BM_APP') || exit;

$MAX_IMAGES = 12;

$list = adm_list_load('stories');
$id = adm_req_id('id');
$isPost = strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
$action = $isPost && isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$types = adm_story_type_options();
$typeIcons = bm_story_types();
$activityOptions = adm_activity_options('— لا يرتبط بنشاط —');

$idx = -1;
if ($id === '!' || ($id !== '' && $id !== 'new' && ($idx = adm_list_index($list, $id)) < 0)) {
    adm_flash('error', 'لم نجد هذه الحكاية؛ ربما حُذفت أو تغيّر رابطها.');
    adm_redirect('stories');
}
$record = $idx >= 0 ? $list[$idx] : null;
$st = array_replace(bm_story_defaults(), $record ?? []);
$st['images'] = bm_images($st['images']);
$rev = $record !== null ? adm_rec_rev($record) : '';
$fv = [];
$imagesView = null;

/* ---------------- POST: delete ---------------- */
if ($action === 'delete' && $record !== null) {
    $conflict = false;
    $paths = [];
    try {
        $result = adm_content_save('stories', function ($data) use ($id, &$conflict, &$paths) {
            $data = array_values(is_array($data) ? $data : []);
            $i = adm_list_index($data, $id);
            if ($i < 0) {
                throw new AdmUserError('هذه الحكاية لم تعد موجودة؛ ربما حذفها مستخدم آخر.');
            }
            if (!hash_equals(adm_rec_rev($data[$i]), adm_post_str('rev'))) {
                $conflict = true;
                return $data;
            }
            $paths = adm_image_paths((array) ($data[$i]['images'] ?? []));
            array_splice($data, $i, 1);
            return $data;
        }, 'stories.delete', '«' . $st['title'] . '» (' . $id . ')');
        if ($conflict) {
            adm_flash('warning', 'عدّل مستخدم آخر هذه الحكاية قبل لحظات، فلم تُحذف. راجع النسخة الأحدث ثم احذفها إن أردت.');
            adm_redirect('stories', ['id' => $id]);
        }
        $n = adm_delete_unreferenced($paths);
        adm_flash('success', 'حُذفت الحكاية «' . $st['title'] . '»' . ($n > 0 ? ' وصورها' : '') . '. ' . adm_build_summary($result['report']));
        adm_redirect('stories');
    } catch (AdmUserError $e) {
        adm_flash('error', $e->getMessage(), $e->details);
        adm_redirect('stories', ['id' => $id]);
    }
}

/* ---------------- POST: save ---------------- */
if ($action === 'save' && ($id === 'new' || $record !== null)) {
    $isNew = $record === null;
    $in = new AdmInput($_POST);
    $postedRev = $in->raw('rev');
    $title = $in->text('title', 'عنوان الحكاية', ['max' => 140, 'required' => true]);
    $newId = adm_slug_from_post($in, 'slug', 'الرابط بالإنجليزية', $title, adm_list_ids($list, $isNew ? '' : $id), $isNew ? '' : $id);
    $fields = [
        'type' => $in->enum('type', 'نوع الحكاية', array_keys($types), 'treatment', ['required' => true]),
        'title' => $title,
        'summary' => $in->text('summary', 'الملخص', ['max' => 400, 'multiline' => true, 'required' => true]),
        'body' => $in->text('body', 'نص الحكاية', ['max' => 8000, 'multiline' => true]),
        'activity' => $in->enum('activity', 'النشاط المرتبط', array_keys($activityOptions), ''),
        'date' => $in->date('date', 'تاريخ الحكاية', ['month' => true]),
        'published' => $in->bool('published'),
    ];
    if ($fields['date'] !== '' && $fields['date'] > date('Y-m-d')) {
        $in->error('date', '«تاريخ الحكاية» لا يمكن أن يكون في المستقبل.');
    }
    $imgs = adm_images_from_post($st['images'], $in, 'imgs');
    $pending = adm_images_pending($in, 'imgs', $MAX_IMAGES - count($imgs['images']));
    // Publishing, or changing a published story, needs the privacy confirmation.
    $candidate = array_replace(bm_story_defaults(), $record ?? [], $fields, ['id' => $newId, 'images' => $imgs['images']]);
    $changes = $isNew || $pending || $candidate !== array_replace(bm_story_defaults(), $record ?? [], ['images' => $st['images']]);
    if ($fields['published'] && $changes && !$in->bool('privacy_confirmed')) {
        $in->error('privacy_confirmed', 'قبل نشر الحكاية أكّد مراجعة الخصوصية: لا أسماء ولا وجوه ولا تفاصيل تكشف هوية أصحابها، مع توفر الموافقات اللازمة.');
    }
    adm_images_reselect($in, 'imgs');

    if ($in->ok()) {
        $up = adm_images_process($pending);
        $conflict = false;
        $finalId = $newId;
        try {
            $result = adm_content_save('stories', function ($data) use ($isNew, $id, $postedRev, $fields, $imgs, $up, &$finalId, &$conflict) {
                $data = array_values(is_array($data) ? $data : []);
                $images = array_merge($imgs['images'], $up['added']);
                if ($isNew) {
                    if (adm_list_index($data, $finalId) >= 0) {
                        $finalId = adm_unique_slug($finalId, adm_list_ids($data));
                    }
                    $data[] = array_replace(bm_story_defaults(), ['id' => $finalId], $fields, ['images' => $images]);
                    return $data;
                }
                $i = adm_list_index($data, $id);
                if ($i < 0) {
                    throw new AdmUserError('هذه الحكاية لم تعد موجودة؛ ربما حذفها مستخدم آخر.');
                }
                if (!hash_equals(adm_rec_rev($data[$i]), $postedRev)) {
                    $conflict = true;
                    return $data;
                }
                $other = adm_list_index($data, $finalId);
                if ($other >= 0 && $other !== $i) {
                    throw new AdmUserError('الرابط «' . $finalId . '» أصبح مستخدمًا لحكاية أخرى؛ اختر رابطًا آخر.');
                }
                $data[$i] = array_replace(bm_story_defaults(), $data[$i], $fields, ['id' => $finalId, 'images' => $images]);
                return $data;
            }, $isNew ? 'stories.create' : 'stories.save', function ($old, $new) use ($isNew, $id, $types, &$finalId) {
                $new = array_values((array) $new);
                $old = array_values((array) $old);
                $j = adm_list_index($new, $finalId);
                if ($j < 0) {
                    return '';
                }
                $n = array_replace(bm_story_defaults(), $new[$j]);
                $k = $isNew ? -1 : adm_list_index($old, $id);
                $o = $k >= 0 ? array_replace(bm_story_defaults(), $old[$k]) : null;
                $parts = [$types[$n['type']] ?? $n['type']];
                if ($o === null || (bool) $o['published'] !== (bool) $n['published']) {
                    $parts[] = $n['published'] ? 'منشورة (أُكّدت مراجعة الخصوصية)' : 'غير منشورة';
                } elseif ($n['published']) {
                    $parts[] = 'تعديل حكاية منشورة (أُكّدت مراجعة الخصوصية)';
                }
                $ni = count(bm_images($n['images']));
                $parts[] = adm_plural($ni, 'صورة واحدة', 'صورتان', 'صور', 'صورة');
                return '«' . $n['title'] . '» (' . $finalId . '): ' . implode('، ', $parts);
            });
            if ($conflict) {
                adm_delete_unreferenced(adm_image_paths($up['added']));
                adm_flash('warning', 'عدّل مستخدم آخر هذه الحكاية أثناء عملك، فلم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول أدناه؛ راجعها ثم اضغط «حفظ الحكاية» مرة أخرى'
                    . ($up['added'] ? ' (واختر الصور الجديدة مرة أخرى)' : '') . '.');
            } else {
                adm_delete_unreferenced(adm_image_paths($imgs['removed']));
                if ($result['changed']) {
                    $msg = ($isNew ? 'أُضيفت الحكاية «' : 'حُفظت الحكاية «') . $fields['title'] . '». ' . adm_build_summary($result['report']);
                    if ($up['added']) {
                        $msg .= ' رُفعت ' . adm_plural(count($up['added']), 'صورة واحدة', 'صورتان', 'صور', 'صورة') . '.';
                    }
                    if (!$fields['published']) {
                        $msg .= ' الحكاية غير منشورة ولا تظهر للزوار.';
                    }
                    adm_flash('success', $msg);
                } else {
                    adm_flash('info', 'لم يتغير شيء في الحكاية.');
                }
                if ($up['failed']) {
                    adm_flash('warning', 'تعذّر رفع بعض الصور: ' . implode(' | ', $up['failed']));
                }
                adm_redirect('stories', ['id' => $finalId]);
            }
        } catch (AdmUserError $e) {
            adm_delete_unreferenced(adm_image_paths($up['added']));
            adm_flash('error', $e->getMessage(), $e->details);
        }
    }
    AdmForm::$errors = $in->errors();
    foreach (['type', 'title', 'slug', 'summary', 'body', 'activity', 'date'] as $k) {
        $fv[$k] = adm_clean($in->raw($k), true);
    }
    $fv['published'] = $in->bool('published');
    $fv['privacy_confirmed'] = $in->bool('privacy_confirmed');
    $imagesView = $imgs['view'];
}

/* ======================================================================== */
/* Render                                                                     */
/* ======================================================================== */
$s = function ($x): string {
    return is_scalar($x) ? (string) $x : '';
};
$val = function (string $k) use (&$fv, $st) {
    if (array_key_exists($k, $fv)) {
        return $fv[$k];
    }
    return $k === 'slug' ? (string) $st['id'] : ($st[$k] ?? '');
};
$typeBadge = function (string $type) use ($types): string {
    return adm_badge($types[$type] ?? $type, 'blue');
};

/* ---------------- List ---------------- */
if ($id === '') {
    AdmPage::$title = 'الحكايات';
    AdmPage::$lead = 'حكايات الأثر (علاج، رزق، سقف، نجاح، مسجد). تُروى دون كشف هوية أصحابها، ولا تظهر للزوار إلا بعد نشرها.';
    AdmPage::$actions = adm_link_button('إضافة حكاية', adm_url('stories', ['id' => 'new']), ['variant' => 'primary', 'icon' => 'plus']);
    $filter = isset($_GET['f']) && in_array($_GET['f'], ['published', 'draft'], true) ? (string) $_GET['f'] : '';
    $counts = ['' => count($list), 'published' => 0, 'draft' => 0];
    foreach ($list as $r) {
        $counts[!empty($r['published']) ? 'published' : 'draft']++;
    }
    $rows = array_values(array_filter($list, function ($r) use ($filter) {
        return $filter === '' || ($filter === 'published') === !empty($r['published']);
    }));
    usort($rows, function ($a, $b) {
        return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
    });
    echo '<nav class="adm-chips adm-chips--filter" aria-label="تصفية الحكايات"><ul role="list">';
    foreach (['' => 'الكل', 'published' => 'منشورة', 'draft' => 'غير منشورة'] as $k => $l) {
        echo '<li><a href="' . e(adm_url('stories', ['f' => $k])) . '"' . ($filter === $k ? ' aria-current="true" class="is-current"' : '') . '>'
            . e($l) . ' <span class="adm-chips__n">' . (int) $counts[$k] . '</span></a></li>';
    }
    echo '</ul></nav>';
    if (!$rows) {
        echo $list
            ? adm_empty('list-filter', 'لا توجد حكايات بهذا التصنيف', 'اختر «الكل» لعرض كل الحكايات.')
            : adm_empty('book-open', 'لا توجد حكايات بعد', 'أضف أول حكاية بزر «إضافة حكاية». حتى تُنشر حكاية يعرض الموقع عبارة «' . (string) t('stories.empty_title') . '».');
        return;
    }
    echo '<ul class="adm-items" role="list">';
    foreach ($rows as $r) {
        $r = array_replace(bm_story_defaults(), $r);
        $rid = (string) $r['id'];
        $edit = adm_url('stories', ['id' => $rid]);
        $imgs = bm_images($r['images']);
        $icon = $typeIcons[$r['type']]['icon'] ?? 'book-open';
        $thumb = $imgs
            ? '<img class="adm-item__thumb" src="' . e(adm_public_url((string) ($imgs[0]['thumb'] !== '' ? $imgs[0]['thumb'] : $imgs[0]['src']))) . '" alt="" loading="lazy">'
            : '<span class="adm-item__thumb adm-item__ph" aria-hidden="true">' . adm_icon($icon) . '</span>';
        $act = adm_activity_name((string) $r['activity']);
        echo '<li class="adm-item">'
            . '<a class="adm-item__media" href="' . e($edit) . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>'
            . '<div class="adm-item__body">'
            . '<h2 class="adm-item__title"><a href="' . e($edit) . '">' . e((string) $r['title']) . '</a></h2>'
            . (trim((string) $r['summary']) !== '' ? '<p class="adm-item__meta">' . e(excerpt((string) $r['summary'], 120)) . '</p>' : '')
            . '<p class="adm-item__badges">' . $typeBadge((string) $r['type'])
            . (!empty($r['published']) ? adm_badge('منشورة', 'green') : adm_badge('غير منشورة', 'neutral'))
            . ($r['date'] !== '' ? adm_badge(ar_date((string) $r['date']), 'neutral') : '')
            . ($act !== '' ? adm_badge('نشاط: ' . $act, 'neutral') : '')
            . '</p>'
            . '<p class="adm-item__counts">' . e($imgs ? adm_plural(count($imgs), 'صورة واحدة', 'صورتان', 'صور', 'صورة') : 'بلا صور') . '</p>'
            . '</div>'
            . '<div class="adm-item__actions">'
            . adm_link_button('تعديل', $edit, ['icon' => 'pencil', 'small' => true])
            . (!empty($r['published']) ? adm_link_button('عرض', adm_public_url('stories/' . $rid . '.html'), ['icon' => 'external-link', 'small' => true, 'variant' => 'ghost', 'external' => true]) : '')
            . '</div></li>';
    }
    echo '</ul>';
    return;
}

/* ---------------- Form ---------------- */
$isNew = $record === null;
AdmPage::$title = $isNew ? 'حكاية جديدة' : (string) $st['title'];
AdmPage::$lead = 'اختر نوع الحكاية واتبع إرشادات كتابته. احكِ كيف تحوّل الدعم إلى أثر، دون كشف هوية أصحاب الحكاية.';
AdmPage::$actions = adm_link_button('كل الحكايات', adm_url('stories'), ['icon' => 'arrow-right', 'variant' => 'ghost'])
    . (!$isNew && !empty($st['published']) ? adm_link_button('عرض على الموقع', adm_public_url('stories/' . $id . '.html'), ['icon' => 'external-link', 'external' => true]) : '');
$type = isset($types[$s($val('type'))]) ? $s($val('type')) : 'treatment';

$guides = '<div class="adm-guide" data-guides aria-live="polite">';
foreach ($types as $k => $name) {
    $guide = t('stories.types.' . $k . '.guide');
    $guides .= '<div class="adm-guide__item" data-guide="' . e($k) . '"' . ($k !== $type ? ' hidden' : '') . '>'
        . '<p class="adm-guide__title">' . adm_icon((string) ($typeIcons[$k]['icon'] ?? 'book-open')) . '<span>ماذا تتضمن «' . e($name) . '»؟</span></p>'
        . '<p>' . e(is_string($guide) ? $guide : '') . '</p></div>';
}
$guides .= '</div>';

echo adm_errors_summary(AdmForm::$errors);
echo adm_form_open(['action' => adm_url('stories', ['id' => $isNew ? 'new' : $id]), 'files' => true, 'dirty' => true, 'class' => 'adm-edit', 'id' => 'story-form']);
echo adm_enter_submit() . '<input type="hidden" name="action" value="save"><input type="hidden" name="rev" value="' . e($rev) . '">';

echo '<div class="adm-rule adm-rule--privacy"><span class="adm-rule__icon">' . adm_icon('eye-off') . '</span><div>'
    . '<p class="adm-rule__title">' . te('stories.privacy_badge') . '</p>'
    . '<p>لا تكتب أسماء المستفيدين أو عناوينهم أو مدارسهم أو أي تفاصيل تمكّن من التعرف عليهم، ولا ترفع صورًا تظهر فيها وجوههم، إلا وفق ضوابط واضحة وموافقات مناسبة. احكِ عن الأثر، لا عن الحاجة.</p>'
    . '</div></div>';

echo adm_card('نوع الحكاية', adm_field(['path' => 'type', 'label' => 'نوع الحكاية', 'type' => 'select', 'required' => true, 'value' => $type, 'options' => $types, 'attrs' => ['data-guide-select' => '1']])
    . $guides, ['icon' => 'book-open', 'id' => 'story-type']);

echo adm_card('الحكاية', ''
    . adm_field(['path' => 'title', 'label' => 'عنوان الحكاية', 'required' => true, 'value' => $s($val('title')), 'max' => 140, 'placeholder' => 'مثل: رحلة علاج اكتملت'])
    . adm_field(['path' => 'summary', 'label' => 'ملخص قصير', 'type' => 'textarea', 'rows' => 2, 'required' => true, 'value' => $s($val('summary')), 'max' => 400, 'counter' => true,
        'hint' => 'يظهر في بطاقة الحكاية وأعلى صفحتها.'])
    . adm_field(['path' => 'body', 'label' => 'نص الحكاية', 'type' => 'textarea', 'rows' => 8, 'value' => $s($val('body')), 'max' => 8000,
        'hint' => 'اترك سطرًا فارغًا بين الفقرات.'])
    . '<div class="adm-cols">'
    . adm_field(['path' => 'activity', 'label' => 'النشاط المرتبط (اختياري)', 'type' => 'select', 'value' => $s($val('activity')), 'options' => $activityOptions])
    . (preg_match('/^\d{4}-\d{2}$/', $s($val('date')))
        // A month-only date (set by hand in the JSON) would look empty in a date picker and be lost on save.
        ? adm_field(['path' => 'date', 'label' => 'تاريخ الحكاية', 'value' => $s($val('date')), 'dir' => 'ltr', 'max' => 10, 'hint' => 'سنة-شهر (مثل 2026-10) أو تاريخ كامل (2026-10-23).'])
        : adm_field(['path' => 'date', 'label' => 'تاريخ الحكاية', 'type' => 'date', 'value' => $s($val('date') !== '' ? $val('date') : ($isNew && !$fv ? date('Y-m-d') : ''))]))
    . '</div>'
    . adm_field(['path' => 'slug', 'label' => 'الرابط بالإنجليزية (اختياري)', 'value' => $s($val('slug')), 'max' => 60, 'dir' => 'ltr', 'placeholder' => $isNew ? 'يُنشأ تلقائيًا' : '',
        'hint' => 'يظهر في عنوان صفحة الحكاية، مثل: ' . "\u{2066}stories/safe-roof.html\u{2069}" . '. حروف إنجليزية صغيرة وأرقام وشرطة (-) فقط. '
            . ($isNew ? 'اتركه فارغًا ليُنشأ تلقائيًا.' : 'تغييره يغيّر عنوان الصفحة.')]),
    ['icon' => 'file-text', 'id' => 'story-text']);

echo adm_card('صور الحكاية', adm_images_editor([
    'items' => $imagesView ?? $st['images'],
    'max' => $MAX_IMAGES,
    'cover_note' => 'صورة الغلاف',
    'empty' => 'لا توجد صور. الحكاية بلا صور مقبولة تمامًا؛ تظهر بأيقونة نوعها.',
    'upload_label' => 'إضافة صور للحكاية',
    'hint' => 'صور العمل أو المكان أو الأداة، لا وجوه المستفيدين.',
]), ['icon' => 'camera', 'id' => 'story-images']);

echo adm_card('النشر', adm_field(['type' => 'checkbox', 'path' => 'published', 'label' => 'نشر الحكاية على الموقع', 'value' => !empty($val('published')),
        'hint' => 'غير المنشورة تبقى هنا فقط ولا تُنشأ لها صفحة.'])
    . adm_field(['type' => 'checkbox', 'path' => 'privacy_confirmed', 'label' => 'راجعت الحكاية وصورها: لا تكشف أسماء أصحابها أو وجوههم أو أي تفاصيل تحدد هويتهم، والموافقات اللازمة متوفرة.',
        'value' => !empty($fv['privacy_confirmed']), 'class' => 'adm-field--confirm', 'hint' => 'مطلوب عند نشر الحكاية وعند أي تعديل على حكاية منشورة.']),
    ['icon' => 'shield-check', 'id' => 'story-publish']);

echo '<div class="adm-savebar adm-savebar--sticky">' . adm_submit($isNew ? 'إضافة الحكاية' : 'حفظ الحكاية', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…'])
    . '<span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span></div>';
echo adm_form_close();

if (!$isNew) {
    echo adm_card('حذف الحكاية', '<p>تُحذف الحكاية وصفحتها وصورها نهائيًا. لإخفائها مؤقتًا ألغِ «نشر الحكاية على الموقع» بدلًا من الحذف.</p>'
        . adm_action_button(['label' => 'حذف الحكاية نهائيًا', 'fields' => ['action' => 'delete', 'rev' => $rev], 'variant' => 'danger', 'icon' => 'trash-2',
            'confirm' => 'حذف الحكاية «' . $st['title'] . '» وصورها نهائيًا؟ لا يمكن التراجع عن ذلك.']),
        ['icon' => 'trash-2', 'class' => 'adm-card--danger', 'id' => 'story-delete']);
}
