<?php
/**
 * «المشروعات» — content/projects.json (schema: bm_project_defaults() in app/lib/data.php).
 *
 *   ?p=projects                         list (f = ongoing | done | draft)
 *   ?p=projects&id=new                  new project
 *   ?p=projects&id=<id>                 edit: fields + images, stage timeline, delete
 *   ?p=projects&id=<id>&stage=new|<n>   add / edit one stage update (date, title, text, images)
 *
 * Content document §08/§12: «لا يوصف المشروع بأنه اكتمل إلا بعد إثبات الإنجاز الفعلي» →
 * changing the status to «منجز» requires the «تم إثبات الإنجاز الفعلي للمشروع» checkbox
 * (checked here, on the server). §12 «كل مشروع له حالة وتاريخ آخر تحديث» → publishing
 * requires «تاريخ آخر تحديث» (updated_at). Cost and donations appear only with show_financials.
 * Deleting a project, a stage or an image also deletes its files (when nothing else uses them).
 */
defined('BM_APP') || exit;

$MAX_IMAGES = 40;
$MAX_STAGE_IMAGES = 20;
$MAX_STAGES = 60;

$list = adm_list_load('projects');
$id = adm_req_id('id');
$stageParam = isset($_GET['stage']) && is_string($_GET['stage']) ? $_GET['stage'] : '';
$isPost = strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
$action = $isPost && isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$statusNames = ['ongoing' => (string) t('ui.status_ongoing'), 'done' => (string) t('ui.status_done')];
$activityOptions = adm_activity_options('— اختر النشاط —');

$idx = -1;
if ($id === '!' || ($id !== '' && $id !== 'new' && ($idx = adm_list_index($list, $id)) < 0)) {
    adm_flash('error', 'لم نجد هذا المشروع؛ ربما حُذف أو تغيّر رابطه.');
    adm_redirect('projects');
}
$record = $idx >= 0 ? $list[$idx] : null;
$p = array_replace(bm_project_defaults(), $record ?? []);
$p['images'] = bm_images($p['images']);
$stages = array_values(array_filter((array) $p['updates'], 'is_array'));
$rev = $record !== null ? adm_rec_rev($record) : '';
$stageDefaults = ['date' => '', 'title' => '', 'text' => '', 'images' => []];

/** Stage index from the query string: -1 = new, -2 = none/invalid. */
$sIdx = -2;
if ($record !== null && $stageParam !== '') {
    if ($stageParam === 'new') {
        $sIdx = -1;
    } elseif (ctype_digit($stageParam) && (int) $stageParam < count($stages)) {
        $sIdx = (int) $stageParam;
    } else {
        adm_flash('error', 'لم نجد هذه المرحلة؛ ربما حُذفت أو عُدّلت.');
        adm_redirect('projects', ['id' => $id], 'stages');
    }
}

$sortStages = function (array $ups): array {
    $i = 0;
    $keyed = [];
    foreach ($ups as $u) {
        $keyed[] = [$u, $i++];
    }
    usort($keyed, function ($a, $b) {
        $c = strcmp((string) ($b[0]['date'] ?? ''), (string) ($a[0]['date'] ?? ''));
        return $c !== 0 ? $c : $a[1] - $b[1];
    });
    return array_map(function ($x) {
        return $x[0];
    }, $keyed);
};

$describe = function (array $old, array $new) use ($statusNames): string {
    $o = array_replace(bm_project_defaults(), $old);
    $n = array_replace(bm_project_defaults(), $new);
    $parts = [];
    $labels = ['title' => 'الاسم', 'id' => 'الرابط', 'activity' => 'النشاط', 'location' => 'الموقع', 'goal' => 'الهدف', 'summary' => 'الملخص',
        'description' => 'الوصف', 'updated_at' => 'تاريخ آخر تحديث', 'cost' => 'التكلفة', 'donations' => 'التبرعات', 'show_financials' => 'إظهار التكلفة والتبرعات'];
    foreach ($labels as $k => $l) {
        if ($o[$k] !== $n[$k]) {
            $parts[] = $l;
        }
    }
    if ($o['status'] !== $n['status']) {
        $parts[] = 'الحالة ← ' . ($statusNames[$n['status']] ?? $n['status']) . ($n['status'] === 'done' ? ' (أُكّد إثبات الإنجاز الفعلي)' : '');
    }
    if ((bool) $o['published'] !== (bool) $n['published']) {
        $parts[] = $n['published'] ? 'نُشر على الموقع' : 'أُلغي النشر';
    }
    $os = array_column(bm_images($o['images']), 'src');
    $ns = array_column(bm_images($n['images']), 'src');
    $added = count(array_diff($ns, $os));
    $removed = count(array_diff($os, $ns));
    if ($added) {
        $parts[] = 'أُضيفت ' . adm_plural($added, 'صورة', 'صورتان', 'صور', 'صورة');
    }
    if ($removed) {
        $parts[] = 'حُذفت ' . adm_plural($removed, 'صورة', 'صورتان', 'صور', 'صورة');
    }
    if (!$added && !$removed && $o['images'] !== $n['images']) {
        $parts[] = array_values(array_intersect($os, $ns)) === array_values(array_intersect($ns, $os)) ? 'أوصاف الصور' : 'ترتيب الصور';
    }
    return '«' . $n['title'] . '» (' . $n['id'] . ')' . ($parts ? ': ' . implode('، ', $parts) : '');
};

$fv = [];          // typed values to show again after an error
$imagesView = null;

/* ======================================================================== */
/* POST: delete project                                                       */
/* ======================================================================== */
if ($action === 'delete' && $record !== null) {
    $conflict = false;
    $paths = [];
    try {
        $result = adm_content_save('projects', function ($data) use ($id, &$conflict, &$paths) {
            $data = array_values(is_array($data) ? $data : []);
            $i = adm_list_index($data, $id);
            if ($i < 0) {
                throw new AdmUserError('هذا المشروع لم يعد موجودًا؛ ربما حذفه مستخدم آخر.');
            }
            if (!hash_equals(adm_rec_rev($data[$i]), adm_post_str('rev'))) {
                $conflict = true;
                return $data;
            }
            $paths = adm_image_paths((array) ($data[$i]['images'] ?? []));
            foreach ((array) ($data[$i]['updates'] ?? []) as $u) {
                if (is_array($u)) {
                    $paths = array_merge($paths, adm_image_paths((array) ($u['images'] ?? [])));
                }
            }
            array_splice($data, $i, 1);
            return $data;
        }, 'projects.delete', '«' . $p['title'] . '» (' . $id . ')');
        if ($conflict) {
            adm_flash('warning', 'عدّل مستخدم آخر هذا المشروع قبل لحظات، فلم يُحذف. راجع النسخة الأحدث ثم احذفه إن أردت.');
            adm_redirect('projects', ['id' => $id]);
        }
        $n = adm_delete_unreferenced($paths);
        adm_flash('success', 'حُذف المشروع «' . $p['title'] . '»' . ($n > 0 ? ' وحُذفت صوره (' . adm_plural($n, 'ملف واحد', 'ملفان', 'ملفات', 'ملفًا') . ')' : '') . '. ' . adm_build_summary($result['report']));
        adm_redirect('projects');
    } catch (AdmUserError $e) {
        adm_flash('error', $e->getMessage(), $e->details);
        adm_redirect('projects', ['id' => $id]);
    }
}

/* ======================================================================== */
/* POST: save project (new or existing)                                       */
/* ======================================================================== */
if ($action === 'save' && $sIdx === -2 && ($id === 'new' || $record !== null)) {
    $isNew = $record === null;
    $in = new AdmInput($_POST);
    $postedRev = $in->raw('rev');
    $title = $in->text('title', 'اسم المشروع', ['max' => 140, 'required' => true]);
    $newId = adm_slug_from_post($in, 'slug', 'الرابط بالإنجليزية', $title, adm_list_ids($list, $isNew ? '' : $id), $isNew ? '' : $id);
    $fields = [
        'title' => $title,
        'activity' => $in->enum('activity', 'النشاط', array_keys(bm_activity_registry()), '', ['required' => true]),
        'location' => $in->text('location', 'الموقع العام', ['max' => 140]),
        'goal' => $in->text('goal', 'الهدف', ['max' => 1500, 'multiline' => true]),
        'status' => $in->enum('status', 'الحالة', bm_project_statuses(), 'ongoing', ['required' => true]),
        'summary' => $in->text('summary', 'الملخص', ['max' => 400, 'multiline' => true]),
        'description' => $in->text('description', 'الوصف التفصيلي', ['max' => 8000, 'multiline' => true]),
        'updated_at' => $in->date('updated_at', 'تاريخ آخر تحديث'),
        'cost' => adm_digits_latin($in->text('cost', 'تكلفة المشروع', ['max' => 60])),
        'donations' => adm_digits_latin($in->text('donations', 'التبرعات', ['max' => 60])),
        'show_financials' => $in->bool('show_financials'),
        'published' => $in->bool('published'),
    ];
    if ($fields['updated_at'] !== '' && $fields['updated_at'] > date('Y-m-d')) {
        $in->error('updated_at', '«تاريخ آخر تحديث» لا يمكن أن يكون في المستقبل.');
    }
    // §12: «كل مشروع له حالة وتاريخ آخر تحديث» — a published project always shows its update date.
    if ($fields['published'] && $fields['updated_at'] === '' && trim($in->raw('updated_at')) === '') {
        $in->error('updated_at', 'لا يُنشر المشروع دون «تاريخ آخر تحديث»: اكتب تاريخ آخر تحديث لبيانات المشروع أو صوره، أو ألغِ اختيار «نشر المشروع على الموقع» واحفظه مسودة.');
    }
    $wasDone = !$isNew && $p['status'] === 'done';
    if ($fields['status'] === 'done' && !$wasDone && !$in->bool('done_confirmed')) {
        $in->error('done_confirmed', 'لا يوصف المشروع بأنه «منجز» إلا بعد إثبات الإنجاز الفعلي: ضع علامة على «تم إثبات الإنجاز الفعلي للمشروع» أو أبقِ الحالة «جارٍ».');
    }
    if ($fields['show_financials'] && $fields['cost'] === '' && $fields['donations'] === '') {
        $in->error('show_financials', 'اكتب التكلفة أو التبرعات أولًا، أو ألغِ اختيار «إظهار التكلفة والتبرعات».');
    }
    $imgs = adm_images_from_post($p['images'], $in, 'imgs');
    $pending = adm_images_pending($in, 'imgs', $MAX_IMAGES - count($imgs['images']));
    adm_images_reselect($in, 'imgs');

    if ($in->ok()) {
        $up = adm_images_process($pending);
        $conflict = false;
        $finalId = $newId;
        try {
            $result = adm_content_save('projects', function ($data) use ($isNew, $id, $postedRev, $fields, $imgs, $up, &$finalId, &$conflict) {
                $data = array_values(is_array($data) ? $data : []);
                $images = array_merge($imgs['images'], $up['added']);
                if ($isNew) {
                    if (adm_list_index($data, $finalId) >= 0) {
                        $finalId = adm_unique_slug($finalId, adm_list_ids($data)); // created meanwhile by a colleague
                    }
                    $data[] = array_replace(bm_project_defaults(), $fields, ['id' => $finalId, 'images' => $images, 'updates' => [], 'created_at' => date('Y-m-d')]);
                    return $data;
                }
                $i = adm_list_index($data, $id);
                if ($i < 0) {
                    throw new AdmUserError('هذا المشروع لم يعد موجودًا؛ ربما حذفه مستخدم آخر.');
                }
                if (!hash_equals(adm_rec_rev($data[$i]), $postedRev)) {
                    $conflict = true;
                    return $data;
                }
                $other = adm_list_index($data, $finalId);
                if ($other >= 0 && $other !== $i) {
                    throw new AdmUserError('الرابط «' . $finalId . '» أصبح مستخدمًا لمشروع آخر؛ اختر رابطًا آخر.');
                }
                $data[$i] = array_replace(bm_project_defaults(), $data[$i], $fields, ['id' => $finalId, 'images' => $images]);
                return $data;
            }, $isNew ? 'projects.create' : 'projects.save', function ($old, $new) use ($describe, $isNew, $id, &$finalId) {
                $old = array_values((array) $old);
                $new = array_values((array) $new);
                $j = adm_list_index($new, $finalId);
                if ($j < 0) {
                    return '';
                }
                if ($isNew) {
                    return '«' . $new[$j]['title'] . '» (' . $finalId . ')';
                }
                $k = adm_list_index($old, $id);
                return $describe($k >= 0 ? $old[$k] : $new[$j], $new[$j]);
            });
            if ($conflict) {
                adm_delete_unreferenced(adm_image_paths($up['added']));
                adm_flash('warning', 'عدّل مستخدم آخر هذا المشروع أثناء عملك، فلم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول أدناه؛ راجعها ثم اضغط «حفظ المشروع» مرة أخرى'
                    . ($up['added'] ? ' (واختر الصور الجديدة مرة أخرى)' : '') . '.');
            } else {
                $deleted = adm_delete_unreferenced(adm_image_paths($imgs['removed']));
                if ($result['changed']) {
                    $msg = ($isNew ? 'أُضيف المشروع «' : 'حُفظ المشروع «') . $fields['title'] . '». ' . adm_build_summary($result['report']);
                    if ($up['added']) {
                        $msg .= ' رُفعت ' . adm_plural(count($up['added']), 'صورة واحدة', 'صورتان', 'صور', 'صورة') . '.';
                    }
                    if ($deleted > 0) {
                        $msg .= ' حُذفت الصور المحددة.';
                    }
                    if (!$fields['published']) {
                        $msg .= ' المشروع غير منشور (مسودة) ولا يظهر للزوار.';
                    }
                    adm_flash('success', $msg);
                } else {
                    adm_flash('info', 'لم يتغير شيء في المشروع.');
                }
                if ($up['failed']) {
                    adm_flash('warning', 'تعذّر رفع بعض الصور: ' . implode(' | ', $up['failed']));
                }
                adm_redirect('projects', ['id' => $finalId]);
            }
        } catch (AdmUserError $e) {
            adm_delete_unreferenced(adm_image_paths($up['added']));
            adm_flash('error', $e->getMessage(), $e->details);
        }
    }
    AdmForm::$errors = $in->errors();
    foreach (['title', 'slug', 'activity', 'location', 'goal', 'status', 'summary', 'description', 'updated_at', 'cost', 'donations'] as $k) {
        $fv[$k] = adm_clean($in->raw($k), true);
    }
    $fv['show_financials'] = $in->bool('show_financials');
    $fv['published'] = $in->bool('published');
    $fv['done_confirmed'] = $in->bool('done_confirmed');
    $imagesView = $imgs['view'];
}

/* ======================================================================== */
/* POST: save / delete a stage                                                */
/* ======================================================================== */
if (($action === 'stage_save' && $sIdx >= -1) || ($action === 'stage_delete' && $record !== null)) {
    $in = new AdmInput($_POST);
    $postedRev = $in->raw('rev');
    $deleting = $action === 'stage_delete';
    $target = $deleting ? (ctype_digit($in->raw('stage')) ? (int) $in->raw('stage') : -9) : $sIdx;
    if ($deleting && ($target < 0 || $target >= count($stages))) {
        adm_flash('error', 'لم نجد هذه المرحلة؛ ربما حُذفت أو عُدّلت.');
        adm_redirect('projects', ['id' => $id], 'stages');
    }
    $current = $target >= 0 ? array_replace($stageDefaults, $stages[$target]) : $stageDefaults;
    $stage = [];
    $imgs = ['images' => [], 'removed' => bm_images($current['images']), 'view' => []];
    $pending = [];
    if (!$deleting) {
        $stage = [
            'date' => $in->date('date', 'تاريخ المرحلة', ['required' => true]),
            'title' => $in->text('title', 'عنوان المرحلة', ['max' => 140, 'required' => true]),
            'text' => $in->text('text', 'وصف المرحلة', ['max' => 4000, 'multiline' => true]),
        ];
        if ($stage['date'] !== '' && $stage['date'] > date('Y-m-d')) {
            $in->error('date', '«تاريخ المرحلة» لا يمكن أن يكون في المستقبل؛ تُنشر المراحل بعد تنفيذها.');
        }
        if ($target === -1 && count($stages) >= $MAX_STAGES) {
            $in->error('title', 'وصلت إلى الحد الأقصى لعدد المراحل (' . $MAX_STAGES . ').');
        }
        $imgs = adm_images_from_post($current['images'], $in, 'imgs');
        $pending = adm_images_pending($in, 'imgs', $MAX_STAGE_IMAGES - count($imgs['images']));
        adm_images_reselect($in, 'imgs');
    }
    $bump = !$deleting && $in->bool('bump_updated');

    if ($in->ok()) {
        $up = adm_images_process($pending);
        $conflict = false;
        try {
            $result = adm_content_save('projects', function ($data) use ($id, $postedRev, $target, $deleting, $stage, $stageDefaults, $imgs, $up, $bump, $sortStages, &$conflict) {
                $data = array_values(is_array($data) ? $data : []);
                $i = adm_list_index($data, $id);
                if ($i < 0) {
                    throw new AdmUserError('هذا المشروع لم يعد موجودًا؛ ربما حذفه مستخدم آخر.');
                }
                if (!hash_equals(adm_rec_rev($data[$i]), $postedRev)) {
                    $conflict = true;
                    return $data;
                }
                $rec = array_replace(bm_project_defaults(), $data[$i]);
                $ups = array_values(array_filter((array) $rec['updates'], 'is_array'));
                if ($deleting) {
                    array_splice($ups, $target, 1);
                } else {
                    $old = $target >= 0 ? $ups[$target] : [];
                    $new = array_replace($stageDefaults, $old, $stage, ['images' => array_merge($imgs['images'], $up['added'])]);
                    if ($target >= 0) {
                        $ups[$target] = $new;
                    } else {
                        $ups[] = $new;
                    }
                    if ($bump && $stage['date'] > (string) $rec['updated_at']) {
                        $rec['updated_at'] = $stage['date'];
                    }
                }
                $rec['updates'] = $sortStages($ups);
                $data[$i] = $rec;
                return $data;
            }, $deleting ? 'projects.stage_delete' : 'projects.stage', '«' . $p['title'] . '» — ' . ($deleting ? 'حذف مرحلة «' . $current['title'] . '»'
                : ($target >= 0 ? 'تعديل مرحلة «' : 'إضافة مرحلة «') . ($stage['title'] ?? '') . '» (' . ($stage['date'] ?? '') . ')'));
            if ($conflict) {
                adm_delete_unreferenced(adm_image_paths($up['added']));
                adm_flash('warning', 'عدّل مستخدم آخر هذا المشروع أثناء عملك، فلم يُحفظ شيء بعد. راجع النسخة الأحدث ثم أعد المحاولة.');
                if ($deleting) {
                    adm_redirect('projects', ['id' => $id], 'stages');
                }
            } else {
                adm_delete_unreferenced(adm_image_paths($imgs['removed']));
                if ($deleting) {
                    adm_flash('success', 'حُذفت المرحلة «' . $current['title'] . '». ' . adm_build_summary($result['report'] ?? ['pages' => 0, 'written' => []]));
                } elseif ($result['changed']) {
                    adm_flash('success', ($target >= 0 ? 'حُفظت المرحلة «' : 'أُضيفت المرحلة «') . $stage['title'] . '». ' . adm_build_summary($result['report'])
                        . ($up['added'] ? ' رُفعت ' . adm_plural(count($up['added']), 'صورة واحدة', 'صورتان', 'صور', 'صورة') . '.' : ''));
                } else {
                    adm_flash('info', 'لم يتغير شيء في المرحلة.');
                }
                if ($up['failed']) {
                    adm_flash('warning', 'تعذّر رفع بعض الصور: ' . implode(' | ', $up['failed']));
                }
                adm_redirect('projects', ['id' => $id], 'stages');
            }
        } catch (AdmUserError $e) {
            adm_delete_unreferenced(adm_image_paths($up['added']));
            adm_flash('error', $e->getMessage(), $e->details);
            if ($deleting) {
                adm_redirect('projects', ['id' => $id], 'stages');
            }
        }
    }
    AdmForm::$errors = $in->errors();
    foreach (['date', 'title', 'text'] as $k) {
        $fv[$k] = adm_clean($in->raw($k), true);
    }
    $fv['bump_updated'] = $bump;
    $imagesView = $imgs['view'];
}

/* ======================================================================== */
/* Render                                                                     */
/* ======================================================================== */
$val = function (string $k) use (&$fv, $p) {
    if (array_key_exists($k, $fv)) {
        return $fv[$k];
    }
    if ($k === 'slug') {
        return (string) $p['id'];
    }
    return $p[$k] ?? '';
};
$s = function ($x): string {
    return is_scalar($x) ? (string) $x : '';
};
$cover = function (array $rec): ?array {
    $n = bm_normalize_project($rec);
    return bm_project_cover($n);
};
$thumbHtml = function (?array $img, string $iconName, string $class = 'adm-item__thumb'): string {
    if ($img !== null && !empty($img['src'])) {
        $src = (string) ($img['thumb'] !== '' ? $img['thumb'] : $img['src']);
        return '<img class="' . e($class) . '" src="' . e(adm_public_url($src)) . '" alt="" loading="lazy">';
    }
    return '<span class="' . e($class) . ' adm-item__ph" aria-hidden="true">' . adm_icon($iconName) . '</span>';
};
$statusBadge = function (string $status) use ($statusNames): string {
    return adm_badge($statusNames[$status] ?? $status, $status === 'done' ? 'green' : 'blue');
};

/* ---------------- List ---------------- */
if ($id === '') {
    AdmPage::$title = 'المشروعات';
    AdmPage::$lead = 'المشروعات الجارية والمنجزة. لكل مشروع حالته وتاريخ آخر تحديث وصور تنفيذه ومراحله. المشروع غير المنشور لا يظهر للزوار.';
    AdmPage::$actions = adm_link_button('إضافة مشروع', adm_url('projects', ['id' => 'new']), ['variant' => 'primary', 'icon' => 'plus']);
    $filter = isset($_GET['f']) && in_array($_GET['f'], ['ongoing', 'done', 'draft'], true) ? (string) $_GET['f'] : '';
    $counts = ['' => count($list), 'ongoing' => 0, 'done' => 0, 'draft' => 0];
    foreach ($list as $r) {
        $counts[($r['status'] ?? '') === 'done' ? 'done' : 'ongoing']++;
        if (empty($r['published'])) {
            $counts['draft']++;
        }
    }
    $rows = array_values(array_filter($list, function ($r) use ($filter) {
        if ($filter === 'draft') {
            return empty($r['published']);
        }
        return $filter === '' || (($r['status'] ?? '') === 'done' ? 'done' : 'ongoing') === $filter;
    }));
    usort($rows, function ($a, $b) {
        $sa = ($a['status'] ?? '') === 'done' ? 1 : 0;
        $sb = ($b['status'] ?? '') === 'done' ? 1 : 0;
        if ($sa !== $sb) {
            return $sa - $sb;
        }
        $c = strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        return $c !== 0 ? $c : strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $chips = ['' => 'الكل', 'ongoing' => $statusNames['ongoing'], 'done' => $statusNames['done'], 'draft' => 'غير منشورة'];
    echo '<nav class="adm-chips adm-chips--filter" aria-label="تصفية المشروعات"><ul role="list">';
    foreach ($chips as $k => $l) {
        echo '<li><a href="' . e(adm_url('projects', ['f' => $k])) . '"' . ($filter === $k ? ' aria-current="true" class="is-current"' : '') . '>'
            . e($l) . ' <span class="adm-chips__n">' . (int) $counts[$k] . '</span></a></li>';
    }
    echo '</ul></nav>';
    if (!$rows) {
        echo $list
            ? adm_empty('list-filter', 'لا توجد مشروعات بهذا التصنيف', 'اختر «الكل» لعرض كل المشروعات.')
            : adm_empty('building-2', 'لا توجد مشروعات بعد', 'أضف أول مشروع بزر «إضافة مشروع». يمكنك حفظه دون نشره حتى يكتمل.');
        return;
    }
    echo '<ul class="adm-items" role="list">';
    foreach ($rows as $r) {
        $r = array_replace(bm_project_defaults(), $r);
        $rid = (string) $r['id'];
        $edit = adm_url('projects', ['id' => $rid]);
        $act = bm_activity((string) $r['activity']);
        $nImages = count(bm_project_all_images(bm_normalize_project($r)));
        $nStages = count(array_filter((array) $r['updates'], 'is_array'));
        $meta = array_filter([$act ? (string) $act['name'] : '', trim((string) $r['location'])], 'strlen');
        echo '<li class="adm-item">'
            . '<a class="adm-item__media" href="' . e($edit) . '" tabindex="-1" aria-hidden="true">' . $thumbHtml($cover($r), $act ? (string) $act['icon'] : 'building-2') . '</a>'
            . '<div class="adm-item__body">'
            . '<h2 class="adm-item__title"><a href="' . e($edit) . '">' . e((string) $r['title']) . '</a></h2>'
            . ($meta ? '<p class="adm-item__meta">' . e(implode(' · ', $meta)) . '</p>' : '')
            . '<p class="adm-item__badges">' . $statusBadge((string) $r['status'])
            . (!empty($r['published']) ? adm_badge('منشور', 'green') : adm_badge('غير منشور', 'neutral'))
            . (trim((string) $r['updated_at']) !== '' ? adm_badge('آخر تحديث: ' . ar_date((string) $r['updated_at']), 'neutral') : adm_badge('بلا تاريخ تحديث', 'amber'))
            . '</p>'
            . '<p class="adm-item__counts">' . e(($nImages === 0 ? 'لا صور بعد' : adm_plural($nImages, 'صورة واحدة', 'صورتان', 'صور', 'صورة')) . ' · ' . ($nStages === 0 ? 'لا مراحل بعد' : adm_plural($nStages, 'مرحلة واحدة', 'مرحلتان', 'مراحل', 'مرحلة'))) . '</p>'
            . '</div>'
            . '<div class="adm-item__actions">'
            . adm_link_button('تعديل', $edit, ['icon' => 'pencil', 'small' => true])
            . (!empty($r['published']) ? adm_link_button('عرض', adm_public_url('projects/' . $rid . '.html'), ['icon' => 'external-link', 'small' => true, 'variant' => 'ghost', 'external' => true]) : '')
            . '</div></li>';
    }
    echo '</ul>';
    return;
}

/* ---------------- Stage form ---------------- */
if ($sIdx >= -1) {
    $st = $sIdx >= 0 ? array_replace($stageDefaults, $stages[$sIdx]) : $stageDefaults;
    $sv = function (string $k) use (&$fv, $st) {
        return array_key_exists($k, $fv) ? $fv[$k] : ($st[$k] ?? '');
    };
    AdmPage::$title = $sIdx >= 0 ? 'تعديل مرحلة' : 'مرحلة جديدة';
    AdmPage::$lead = 'مرحلة من مراحل تنفيذ «' . $p['title'] . '». تظهر المراحل في صفحة المشروع من الأحدث إلى الأقدم، مع صورها.';
    AdmPage::$actions = adm_link_button('العودة إلى المشروع', adm_url('projects', ['id' => $id], 'stages'), ['icon' => 'arrow-right']);
    $formUrl = adm_url('projects', ['id' => $id, 'stage' => $sIdx >= 0 ? (string) $sIdx : 'new']);
    echo adm_errors_summary(AdmForm::$errors);
    echo adm_form_open(['action' => $formUrl, 'files' => true, 'dirty' => true, 'class' => 'adm-edit']);
    echo adm_enter_submit() . '<input type="hidden" name="action" value="stage_save"><input type="hidden" name="rev" value="' . e($rev) . '">';
    echo adm_card('بيانات المرحلة', '<div class="adm-cols">'
        . adm_field(['path' => 'date', 'label' => 'تاريخ المرحلة', 'type' => 'date', 'required' => true, 'value' => $s($sv('date') !== '' ? $sv('date') : ($sIdx === -1 ? date('Y-m-d') : '')),
            'hint' => 'تاريخ تنفيذ هذه المرحلة أو توثيقها.'])
        . adm_field(['path' => 'title', 'label' => 'عنوان المرحلة', 'required' => true, 'value' => $s($sv('title')), 'max' => 140, 'placeholder' => 'مثل: الانتهاء من صب السقف'])
        . '</div>'
        . adm_field(['path' => 'text', 'label' => 'وصف المرحلة (اختياري)', 'type' => 'textarea', 'rows' => 4, 'value' => $s($sv('text')), 'max' => 4000,
            'hint' => 'ما الذي نُفّذ في هذه المرحلة، بدقة ودون مبالغة.'])
        . adm_field(['type' => 'checkbox', 'path' => 'bump_updated', 'label' => 'اجعل «آخر تحديث» للمشروع تاريخ هذه المرحلة (إن كان أحدث)',
            'value' => array_key_exists('bump_updated', $fv) ? $fv['bump_updated'] : $sIdx === -1]),
        ['icon' => 'list-checks', 'id' => 'stage-data']);
    echo adm_card('صور المرحلة', adm_images_editor([
        'items' => $imagesView ?? bm_images($st['images']),
        'max' => $MAX_STAGE_IMAGES,
        'empty' => 'لا توجد صور لهذه المرحلة بعد.',
        'upload_label' => 'إضافة صور للمرحلة',
    ]), ['icon' => 'camera', 'id' => 'stage-images']);
    echo '<div class="adm-savebar adm-savebar--sticky">' . adm_submit('حفظ المرحلة', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…'])
        . adm_link_button('إلغاء', adm_url('projects', ['id' => $id], 'stages'), ['variant' => 'ghost'])
        . '<span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span></div>';
    echo adm_form_close();
    if ($sIdx >= 0) {
        echo adm_card('حذف المرحلة', '<p>تُحذف المرحلة وصورها من الموقع نهائيًا.</p>'
            . adm_action_button(['label' => 'حذف هذه المرحلة', 'action' => adm_url('projects', ['id' => $id]), 'fields' => ['action' => 'stage_delete', 'stage' => (string) $sIdx, 'rev' => $rev],
                'variant' => 'danger', 'icon' => 'trash-2', 'confirm' => 'حذف المرحلة «' . $st['title'] . '» وصورها نهائيًا؟']),
            ['icon' => 'trash-2', 'class' => 'adm-card--danger', 'id' => 'stage-delete']);
    }
    return;
}

/* ---------------- Project form ---------------- */
$isNew = $record === null;
$wasDone = !$isNew && $p['status'] === 'done';
AdmPage::$title = $isNew ? 'مشروع جديد' : (string) $p['title'];
AdmPage::$lead = $isNew ? 'أدخل بيانات المشروع وصوره. يمكنك حفظه «غير منشور» ثم نشره حين يكتمل توثيقه.' : 'عدّل بيانات المشروع وصوره، وأضف مراحل التنفيذ أولًا بأول.';
AdmPage::$actions = adm_link_button('كل المشروعات', adm_url('projects'), ['icon' => 'arrow-right', 'variant' => 'ghost'])
    . (!$isNew && !empty($p['published']) ? adm_link_button('عرض على الموقع', adm_public_url('projects/' . $id . '.html'), ['icon' => 'external-link', 'external' => true]) : '');
$status = $s($val('status')) === 'done' ? 'done' : 'ongoing';
$formUrl = adm_url('projects', ['id' => $isNew ? 'new' : $id]);

$statusHtml = '<fieldset class="adm-choice" id="f-status" data-status-choice' . ($wasDone ? ' data-was-done' : '') . '><legend class="adm-label">الحالة <span class="adm-req" aria-hidden="true">*</span></legend><div class="adm-choice__opts">';
foreach (['ongoing' => 'العمل مستمر؛ تُنشر صور التنفيذ ومراحله تباعًا.', 'done' => 'اكتمل التنفيذ وأُثبت الإنجاز الفعلي للمشروع.'] as $k => $desc) {
    $statusHtml .= '<label class="adm-choice__opt"><input type="radio" name="status" value="' . e($k) . '"' . ($status === $k ? ' checked' : '') . '>'
        . '<span class="adm-choice__text"><strong>' . e($statusNames[$k]) . '</strong><small>' . e($desc) . '</small></span></label>';
}
$statusHtml .= '</div>' . (isset(AdmForm::$errors['status']) ? '<p class="adm-error">' . adm_icon('circle-alert') . '<span>' . e(AdmForm::$errors['status']) . '</span></p>' : '') . '</fieldset>';
if ($wasDone) {
    $statusHtml .= '<p class="adm-statline">' . adm_icon('circle-check') . '<span>حالة «' . e($statusNames['done']) . '» معتمدة بعد إثبات الإنجاز الفعلي.</span></p>';
} else {
    $statusHtml .= '<div class="adm-confirmbox" data-done-confirm>'
        . adm_field(['type' => 'checkbox', 'path' => 'done_confirmed', 'label' => 'تم إثبات الإنجاز الفعلي للمشروع', 'value' => !empty($fv['done_confirmed']), 'class' => 'adm-field--confirm',
            'hint' => 'مطلوب لتغيير الحالة إلى «' . $statusNames['done'] . '». لا يوصف المشروع بأنه اكتمل إلا بعد إثبات الإنجاز الفعلي (وثيقة المحتوى، القسم 08).'])
        . '</div>';
}

echo adm_errors_summary(AdmForm::$errors);
echo adm_form_open(['action' => $formUrl, 'files' => true, 'dirty' => true, 'class' => 'adm-edit', 'id' => 'project-form']);
echo adm_enter_submit() . '<input type="hidden" name="action" value="save"><input type="hidden" name="rev" value="' . e($rev) . '">';

echo adm_card('بيانات المشروع', ''
    . adm_field(['path' => 'title', 'label' => 'اسم المشروع', 'required' => true, 'value' => $s($val('title')), 'max' => 140, 'placeholder' => 'مثل: مسجد سيدنا عيسى ابن مريم'])
    . '<div class="adm-cols">'
    . adm_field(['path' => 'activity', 'label' => 'النشاط التابع له', 'type' => 'select', 'required' => true, 'value' => $s($val('activity')), 'options' => $activityOptions])
    . adm_field(['path' => 'location', 'label' => 'الموقع العام', 'value' => $s($val('location')), 'max' => 140, 'placeholder' => 'مثل: الطور، جنوب سيناء',
        'hint' => 'المدينة أو المحافظة فقط، دون عنوان تفصيلي يكشف أصحاب المكان.'])
    . '</div>'
    . adm_field(['path' => 'goal', 'label' => 'الهدف (اختياري)', 'type' => 'textarea', 'rows' => 2, 'value' => $s($val('goal')), 'max' => 1500])
    . adm_field(['path' => 'slug', 'label' => 'الرابط بالإنجليزية (اختياري)', 'value' => $s($val('slug')), 'max' => 60, 'dir' => 'ltr', 'placeholder' => $isNew ? 'يُنشأ تلقائيًا' : '',
        'hint' => 'يظهر في عنوان صفحة المشروع، مثل: ' . "\u{2066}projects/masjid-el-tor.html\u{2069}" . '. حروف إنجليزية صغيرة وأرقام وشرطة (-) فقط. '
            . ($isNew ? 'اتركه فارغًا ليُنشأ تلقائيًا.' : 'تغييره يغيّر عنوان الصفحة، فتتوقف الروابط القديمة التي شاركتها.')]),
    ['icon' => 'building-2', 'id' => 'project-data']);

echo adm_card('الحالة والنشر', $statusHtml
    . '<div class="adm-cols">'
    . adm_field(['path' => 'updated_at', 'label' => 'تاريخ آخر تحديث', 'type' => 'date', 'value' => $s($val('updated_at') !== '' ? $val('updated_at') : ($isNew && !$fv ? date('Y-m-d') : '')),
        'hint' => 'يظهر للزوار بجوار حالة المشروع، وهو مطلوب لنشر المشروع.'])
    . '</div>'
    . adm_field(['type' => 'checkbox', 'path' => 'published', 'label' => 'نشر المشروع على الموقع', 'value' => !empty($val('published')),
        'hint' => 'غير المنشور يبقى هنا فقط (مسودة) ولا تُنشأ له صفحة.']),
    ['icon' => 'list-checks', 'id' => 'project-status']);

echo adm_card('عن المشروع', ''
    . adm_field(['path' => 'summary', 'label' => 'ملخص قصير', 'type' => 'textarea', 'rows' => 2, 'value' => $s($val('summary')), 'max' => 400, 'counter' => true,
        'hint' => 'يظهر في بطاقة المشروع وأعلى صفحته. جملة أو جملتان.'])
    . adm_field(['path' => 'description', 'label' => 'الوصف التفصيلي (اختياري)', 'type' => 'textarea', 'rows' => 6, 'value' => $s($val('description')), 'max' => 8000,
        'hint' => 'اترك سطرًا فارغًا بين الفقرات.']),
    ['icon' => 'file-text', 'id' => 'project-about']);

echo adm_card('التكلفة والتبرعات', '<div class="adm-cols">'
    . adm_field(['path' => 'cost', 'label' => 'تكلفة المشروع (اختياري)', 'value' => $s($val('cost')), 'max' => 60, 'dir' => 'ltr', 'attrs' => ['inputmode' => 'numeric'],
        'hint' => 'بالجنيه، أرقام فقط مثل 250000.'])
    . adm_field(['path' => 'donations', 'label' => 'التبرعات المحصّلة (اختياري)', 'value' => $s($val('donations')), 'max' => 60, 'dir' => 'ltr', 'attrs' => ['inputmode' => 'numeric']])
    . '</div>'
    . adm_field(['type' => 'checkbox', 'path' => 'show_financials', 'label' => 'إظهار التكلفة والتبرعات على صفحة المشروع', 'value' => !empty($val('show_financials')),
        'hint' => 'فقط حين تكون بياناتها معتمدة ومناسبة للنشر (وثيقة المحتوى، القسم 08).']),
    ['icon' => 'hand-coins', 'id' => 'project-money']);

echo adm_card('صور التنفيذ', adm_images_editor([
    'items' => $imagesView ?? $p['images'],
    'max' => $MAX_IMAGES,
    'cover_note' => 'صورة الغلاف في بطاقة المشروع',
    'empty' => 'لا توجد صور للمشروع بعد. صور العمل الحقيقية تبني الثقة؛ تجنّب الصور النمطية وصور المستفيدين.',
    'upload_label' => 'إضافة صور للمشروع',
]), ['icon' => 'camera', 'id' => 'project-images']);

echo '<div class="adm-savebar adm-savebar--sticky">' . adm_submit($isNew ? 'إضافة المشروع' : 'حفظ المشروع', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…'])
    . '<span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span></div>';
echo adm_form_close();

if ($isNew) {
    echo adm_alert('بعد إضافة المشروع يمكنك إضافة مراحل التنفيذ (تاريخ وعنوان ووصف وصور لكل مرحلة).', 'info');
    return;
}

/* Stage timeline (newest first, as on the public page). */
$order = array_keys($stages);
usort($order, function ($a, $b) use ($stages) {
    $c = strcmp((string) ($stages[$b]['date'] ?? ''), (string) ($stages[$a]['date'] ?? ''));
    return $c !== 0 ? $c : $a - $b;
});
$stagesHtml = '';
if (!$stages) {
    $stagesHtml = '<p class="adm-imgs__empty">' . adm_icon('list-checks') . '<span>لا توجد مراحل بعد. تظهر في صفحة المشروع عبارة «' . e((string) t('ui.stages_empty')) . '».</span></p>';
} else {
    $stagesHtml = '<ol class="adm-stages" role="list">';
    foreach ($order as $i) {
        $st = array_replace($stageDefaults, $stages[$i]);
        $simgs = bm_images($st['images']);
        $thumbs = '';
        foreach (array_slice($simgs, 0, 4) as $img) {
            $thumbs .= '<li>' . $thumbHtml($img, 'camera', 'adm-stage__thumb') . '</li>';
        }
        $stagesHtml .= '<li class="adm-stage">'
            . '<p class="adm-stage__when">' . adm_icon('calendar') . '<span>' . e($st['date'] !== '' ? ar_date((string) $st['date']) : 'بلا تاريخ') . '</span></p>'
            . '<div class="adm-stage__body"><h3 class="adm-stage__title">' . e((string) $st['title']) . '</h3>'
            . (trim((string) $st['text']) !== '' ? '<p class="adm-stage__text">' . e(excerpt((string) $st['text'], 160)) . '</p>' : '')
            . ($thumbs !== '' ? '<ul class="adm-stage__thumbs" role="list">' . $thumbs . (count($simgs) > 4 ? '<li class="adm-stage__more">+' . (count($simgs) - 4) . '</li>' : '') . '</ul>' : '')
            . '</div>'
            . '<div class="adm-stage__actions">'
            . adm_link_button('تعديل', adm_url('projects', ['id' => $id, 'stage' => (string) $i]), ['icon' => 'pencil', 'small' => true])
            . adm_action_button(['label' => 'حذف', 'action' => adm_url('projects', ['id' => $id]), 'fields' => ['action' => 'stage_delete', 'stage' => (string) $i, 'rev' => $rev],
                'variant' => 'ghost', 'class' => 'adm-btn--danger-text', 'icon' => 'trash-2', 'small' => true, 'confirm' => 'حذف المرحلة «' . $st['title'] . '» وصورها نهائيًا؟'])
            . '</div></li>';
    }
    $stagesHtml .= '</ol>';
}
echo adm_card('مراحل التنفيذ', $stagesHtml, [
    'icon' => 'list-checks',
    'id' => 'stages',
    'actions' => adm_link_button('إضافة مرحلة', adm_url('projects', ['id' => $id, 'stage' => 'new']), ['icon' => 'plus', 'small' => true, 'variant' => 'primary']),
]);

echo adm_card('حذف المشروع', '<p>يُحذف المشروع وصفحته وكل صوره وصور مراحله نهائيًا. إن أردت إخفاءه مؤقتًا فألغِ «نشر المشروع على الموقع» بدلًا من الحذف.</p>'
    . adm_action_button(['label' => 'حذف المشروع نهائيًا', 'fields' => ['action' => 'delete', 'rev' => $rev], 'variant' => 'danger', 'icon' => 'trash-2',
        'confirm' => 'حذف المشروع «' . $p['title'] . '» وكل صوره نهائيًا؟ لا يمكن التراجع عن ذلك.']),
    ['icon' => 'trash-2', 'class' => 'adm-card--danger', 'id' => 'project-delete']);
