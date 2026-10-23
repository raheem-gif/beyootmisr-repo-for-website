<?php
/**
 * «معرض صور الأنشطة» — content/galleries.json: { "<activity-slug>": [image records] }.
 *
 *   ?p=galleries              the ten activities with their photo counts
 *   ?p=galleries&a=<slug>     one activity: upload photos (Arabic description + caption),
 *                             edit descriptions, reorder, delete (files are deleted too)
 *
 * Each activity page shows its gallery («صور من العمل»); without photos it shows an honest
 * empty state (content document §12: real photos only, no stock images, no identifiable faces).
 */
defined('BM_APP') || exit;

$MAX_IMAGES = 80;

$all = adm_content_load('galleries');
$all = is_array($all) ? $all : [];
$registry = bm_activity_registry();
$slug = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
if ($slug !== '' && !isset($registry[$slug])) {
    adm_flash('error', 'هذا النشاط غير موجود.');
    adm_redirect('galleries');
}
$isPost = strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
$imagesView = null;

if ($isPost && $slug !== '') {
    $activityName = adm_activity_name($slug);
    $current = bm_images($all[$slug] ?? []);
    $in = new AdmInput($_POST);
    $postedRev = $in->raw('rev');
    $imgs = adm_images_from_post($current, $in, 'imgs');
    $pending = adm_images_pending($in, 'imgs', $MAX_IMAGES - count($imgs['images']));
    adm_images_reselect($in, 'imgs');
    if ($in->ok()) {
        $up = adm_images_process($pending);
        $conflict = false;
        $action = $up['added'] && !$imgs['removed'] ? 'galleries.upload' : ($imgs['removed'] && !$up['added'] ? 'galleries.delete' : 'galleries.save');
        try {
            $result = adm_content_save('galleries', function ($data) use ($slug, $postedRev, $imgs, $up, &$conflict) {
                $data = is_array($data) ? $data : [];
                $before = $data;
                if (!hash_equals(adm_rev($data[$slug] ?? []), $postedRev)) {
                    $conflict = true;
                    return $data;
                }
                $list = array_merge($imgs['images'], $up['added']);
                if ($list) {
                    $data[$slug] = $list;
                } else {
                    unset($data[$slug]);
                }
                if (!$data) {
                    // Keep the file a JSON object ({}) once the last photo is deleted.
                    return $before ? new stdClass() : $before;
                }
                return $data;
            }, $action, function ($old, $new) use ($slug, $activityName) {
                $o = array_column(bm_images(((array) $old)[$slug] ?? []), 'src');
                $n = array_column(bm_images(((array) $new)[$slug] ?? []), 'src');
                $parts = [];
                if ($a = count(array_diff($n, $o))) {
                    $parts[] = 'أُضيفت ' . adm_plural($a, 'صورة', 'صورتان', 'صور', 'صورة');
                }
                if ($d = count(array_diff($o, $n))) {
                    $parts[] = 'حُذفت ' . adm_plural($d, 'صورة', 'صورتان', 'صور', 'صورة');
                }
                if (!$parts) {
                    $parts[] = 'تعديل الأوصاف أو الترتيب';
                }
                return '«' . $activityName . '»: ' . implode('، ', $parts) . ' (المجموع ' . count($n) . ')';
            });
            if ($conflict) {
                adm_delete_unreferenced(adm_image_paths($up['added']));
                adm_flash('warning', 'عدّل مستخدم آخر صور هذا النشاط أثناء عملك، فلم يُحفظ شيء بعد. راجع الصور أدناه (النسخة الأحدث) ثم أعد تعديلك'
                    . ($up['added'] ? ' واختر الصور الجديدة مرة أخرى' : '') . '.');
                $all = adm_content_load('galleries');
                $all = is_array($all) ? $all : [];
            } else {
                adm_delete_unreferenced(adm_image_paths($imgs['removed']));
                if ($result['changed']) {
                    $parts = [];
                    if ($up['added']) {
                        $parts[] = 'رُفعت ' . adm_plural(count($up['added']), 'صورة واحدة', 'صورتان', 'صور', 'صورة');
                    }
                    if ($imgs['removed']) {
                        $parts[] = 'حُذفت ' . adm_plural(count($imgs['removed']), 'صورة واحدة', 'صورتان', 'صور', 'صورة');
                    }
                    adm_flash('success', 'حُفظ معرض صور «' . $activityName . '»' . ($parts ? ': ' . implode('، و', $parts) : '') . '. ' . adm_build_summary($result['report']));
                } else {
                    adm_flash('info', 'لم يتغير شيء في صور «' . $activityName . '».');
                }
                if ($up['failed']) {
                    adm_flash('warning', 'تعذّر رفع بعض الصور: ' . implode(' | ', $up['failed']));
                }
                adm_redirect('galleries', ['a' => $slug]);
            }
        } catch (AdmUserError $e) {
            adm_delete_unreferenced(adm_image_paths($up['added']));
            adm_flash('error', $e->getMessage(), $e->details);
            $imagesView = $imgs['view'];
        }
    } else {
        $imagesView = $imgs['view'];
    }
    AdmForm::$errors = $in->errors();
}

/* ---------------- Index: the ten activities ---------------- */
if ($slug === '') {
    AdmPage::$title = 'معرض صور الأنشطة';
    AdmPage::$lead = 'صور العمل الحقيقية لكل نشاط. تظهر في صفحة النشاط تحت «' . (string) t('ui.gallery_title') . '». النشاط بلا صور يعرض للزوار أن الصور تُضاف بعد اعتمادها.';
    $total = 0;
    foreach ($all as $list) {
        $total += is_array($list) ? count(bm_images($list)) : 0;
    }
    echo '<p class="adm-statline">' . adm_icon('images') . '<span>' . e($total > 0 ? 'في المعرض ' . adm_plural($total, 'صورة واحدة', 'صورتان', 'صور', 'صورة') . ' لكل الأنشطة.' : 'لا توجد صور في المعرض بعد.') . '</span></p>';
    echo '<ul class="adm-acts" role="list">';
    foreach (bm_activities() as $s => $a) {
        $imgs = bm_images($all[$s] ?? []);
        $thumbs = '';
        foreach (array_slice($imgs, 0, 4) as $img) {
            $thumbs .= '<li><img src="' . e(adm_public_url((string) ($img['thumb'] !== '' ? $img['thumb'] : $img['src']))) . '" alt="" loading="lazy"></li>';
        }
        $url = adm_url('galleries', ['a' => $s]);
        echo '<li class="adm-act">'
            . '<div class="adm-act__head"><span class="adm-act__icon">' . adm_icon((string) $a['icon']) . '</span>'
            . '<h2 class="adm-act__title"><a href="' . e($url) . '"><small>' . e((string) $a['num']) . '</small> ' . e((string) $a['name']) . '</a></h2></div>'
            . ($thumbs !== '' ? '<ul class="adm-act__thumbs" role="list">' . $thumbs . '</ul>' : '<p class="adm-act__none">' . adm_icon('camera') . '<span>لا توجد صور بعد</span></p>')
            . '<p class="adm-act__foot">' . ($imgs ? adm_badge(adm_plural(count($imgs), 'صورة واحدة', 'صورتان', 'صور', 'صورة'), 'blue') : '<span></span>')
            . '<span class="adm-act__go">إدارة الصور' . adm_icon('arrow-left') . '</span></p>'
            . '</li>';
    }
    echo '</ul>';
    return;
}

/* ---------------- One activity ---------------- */
$a = bm_activity($slug);
$current = bm_images($all[$slug] ?? []);
AdmPage::$title = 'صور نشاط «' . $a['name'] . '»';
AdmPage::$lead = 'تظهر هذه الصور في صفحة النشاط بالترتيب نفسه. اكتب لكل صورة وصفًا قصيرًا بالعربية، وأضف تعليقًا إن أردت.';
AdmPage::$actions = adm_link_button('كل الأنشطة', adm_url('galleries'), ['icon' => 'arrow-right', 'variant' => 'ghost'])
    . adm_link_button('صفحة النشاط', adm_public_url((string) $a['url']), ['icon' => 'external-link', 'external' => true]);

echo adm_errors_summary(AdmForm::$errors);
echo adm_form_open(['action' => adm_url('galleries', ['a' => $slug]), 'files' => true, 'dirty' => true, 'class' => 'adm-edit', 'id' => 'gallery-form']);
echo adm_enter_submit() . '<input type="hidden" name="rev" value="' . e(adm_rev($all[$slug] ?? [])) . '">';
echo adm_card('الصور (' . count($current) . ')', adm_images_editor([
    'items' => $imagesView ?? $current,
    'max' => $MAX_IMAGES,
    'empty' => 'لا توجد صور لهذا النشاط بعد. تعرض صفحته الآن: «' . (string) t('ui.gallery_empty') . '»',
    'upload_label' => 'إضافة صور لنشاط «' . $a['name'] . '»',
    'hint' => 'صور العمل والتنفيذ الحقيقية.',
]), ['icon' => 'images', 'id' => 'gallery-images']);
echo '<div class="adm-savebar adm-savebar--sticky">' . adm_submit('حفظ الصور', ['icon' => 'save', 'busy' => 'جارٍ الرفع وتحديث الموقع…'])
    . '<span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة</span></div>';
echo adm_form_close();
