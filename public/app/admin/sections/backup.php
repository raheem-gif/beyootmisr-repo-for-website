<?php
/**
 * «نسخة احتياطية» (admins only):
 *  - download a ZIP of app/content + uploads (ZipArchive), or explain the manual way;
 *  - list the automatic backups taken before every save, download or restore one.
 * Accounts (app/storage) are never included in the ZIP.
 */
defined('BM_APP') || exit;

AdmPage::$title = 'نسخة احتياطية';
AdmPage::$lead = 'احتفظ بنسخة من المحتوى والصور على جهازك بانتظام، واسترجع أي ملف محتوى إلى حالته قبل تعديل سابق.';
$files = adm_content_files();
$hasZip = class_exists('ZipArchive');

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $name = is_string($_POST['file'] ?? null) ? $_POST['file'] : '';
    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';

    if ($action === 'zip') {
        if (!$hasZip) {
            adm_flash('error', 'إنشاء ملف ZIP غير متاح على هذه الاستضافة. اتبع طريقة التنزيل اليدوية الموضحة أدناه.');
            adm_redirect('backup');
        }
        @set_time_limit(300);
        $tmpDir = is_writable(sys_get_temp_dir()) ? sys_get_temp_dir() : adm_storage_dir('tmp');
        $tmp = @tempnam($tmpDir, 'bmz');
        $zip = new ZipArchive();
        if ($tmp === false || $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            adm_flash('error', 'تعذّر إنشاء ملف النسخة الاحتياطية المؤقت على الاستضافة.');
            adm_redirect('backup');
        }
        $count = 0;
        foreach (array_keys(adm_content_files()) as $contentName) {
            $f = adm_content_read_path($contentName);
            if (is_file($f)) {
                $zip->addFile($f, 'app/content/' . basename($f));
                $count++;
            }
        }
        foreach (['images', 'reports'] as $d) {
            foreach (glob(BM_PUBLIC . '/uploads/' . $d . '/*') ?: [] as $f) {
                if (!is_file($f) || basename($f) === 'index.html' || basename($f)[0] === '.') {
                    continue;
                }
                $entry = 'uploads/' . $d . '/' . basename($f);
                $zip->addFile($f, $entry);
                if (method_exists($zip, 'setCompressionName') && preg_match('/\.(jpe?g|png|webp|pdf)$/i', $f)) {
                    $zip->setCompressionName($entry, ZipArchive::CM_STORE);
                }
                $count++;
            }
        }
        $zip->addFromString('README.txt', "Beyoot Misr website backup — " . date('Y-m-d H:i') . " (Africa/Cairo)\n\n"
            . "app/content/  editable content (JSON). To restore, upload these files to app/content/ on the hosting,\n"
            . "              then open the admin panel and press «إعادة بناء الموقع» on the dashboard.\n"
            . "uploads/      uploaded photos and PDF reports. Upload them to the same folders.\n\n"
            . "Admin accounts are NOT included (they stay on the server in app/storage).\n");
        $zip->close();
        adm_audit('backup.download', $count . ' ملف');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="beyootmisr-backup-' . date('Ymd-Hi') . '.zip"');
        header('Content-Length: ' . (string) filesize($tmp));
        ignore_user_abort(true); // make sure the temporary file is removed
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    if (($action === 'restore' || $action === 'download') && isset($files[$name])) {
        $raw = adm_backup_read($name, $id);
        if ($raw === null) {
            adm_flash('error', 'لم نجد هذه النسخة. ربما حُذفت ضمن النسخ القديمة.');
            adm_redirect('backup');
        }
        if ($action === 'download') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $name . '-' . $id . '.json"');
            echo $raw;
            exit;
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            adm_flash('error', 'هذه النسخة تالفة ولا يمكن استرجاعها.');
            adm_redirect('backup');
        }
        $when = '';
        foreach (adm_backups($name) as $b) {
            if ($b['id'] === $id) {
                $when = adm_time($b['t']);
            }
        }
        try {
            $result = adm_content_save($name, function () use ($data) {
                return $data;
            }, 'backup.restore', '«' . $files[$name] . '» إلى نسخة ' . $when);
            adm_flash($result['changed'] ? 'success' : 'info', $result['changed']
                ? 'استُرجع «' . $files[$name] . '» إلى نسخة ' . $when . '. ' . adm_build_summary($result['report']) . ' (حُفظت النسخة السابقة أيضًا ويمكن الرجوع إليها.)'
                : 'هذه النسخة مطابقة للمحتوى الحالي؛ لم يتغير شيء.');
        } catch (AdmUserError $e) {
            adm_flash('error', $e->getMessage(), $e->details);
        }
        adm_redirect('backup');
    }
    adm_redirect('backup');
}

$uploadsSize = 0;
$uploadsCount = 0;
foreach (['images', 'reports'] as $d) {
    foreach (glob(BM_PUBLIC . '/uploads/' . $d . '/*') ?: [] as $f) {
        if (is_file($f) && basename($f) !== 'index.html') {
            $uploadsSize += (int) filesize($f);
            $uploadsCount++;
        }
    }
}

$zipBody = '<p>تحتوي النسخة على ملفات المحتوى (النصوص، والإعدادات، والأرقام، والمشروعات، والحكايات، والتقارير، ومعرض الصور) وكل الصور وملفات PDF المرفوعة'
    . ($uploadsCount > 0 ? ' (' . e(adm_plural($uploadsCount, 'ملف واحد', 'ملفان', 'ملفات', 'ملفًا')) . '، ' . e(adm_bytes($uploadsSize)) . ')' : ' (لا توجد ملفات مرفوعة بعد)')
    . '. لا تتضمن حسابات المستخدمين.</p>';
if ($hasZip) {
    $zipBody .= adm_action_button(['action' => adm_url('backup'), 'label' => 'تنزيل نسخة كاملة (ZIP)', 'icon' => 'hard-drive-download', 'variant' => 'primary', 'fields' => ['action' => 'zip'], 'busy' => 'جارٍ تجهيز الملف…']);
    $zipBody .= '<p class="adm-hint">احفظ الملف في مكان آمن (مثل حساب المؤسسة على Google Drive). يُنصح بتنزيل نسخة كل شهر وقبل أي تعديل كبير.</p>';
} else {
    $zipBody .= adm_alert('إنشاء ملفات ZIP غير متاح على هذه الاستضافة (إضافة ZipArchive غير مفعّلة).', 'warning')
        . '<p>للتنزيل يدويًا: افتح «مدير الملفات» في لوحة Turbify، وحدّد المجلدين <code dir="ltr">app/content</code> و<code dir="ltr">uploads</code>، ثم اختر «ضغط» (Compress) أو «تنزيل» (Download).</p>';
}
echo adm_card('تنزيل نسخة كاملة', $zipBody, ['icon' => 'archive', 'id' => 'bk-zip']);

$listBody = '<p class="adm-hint">قبل كل حفظ تُحفظ نسخة من الملف السابق تلقائيًا (آخر ' . ADM_BACKUPS_KEEP . ' نسخة لكل ملف). الاسترجاع يحفظ المحتوى الحالي أولًا، فيمكن التراجع عنه.</p>'
    . '<p class="adm-hint">تنبيه: الصور وملفات PDF التي حُذفت من لوحة الإدارة تُحذف من الاستضافة نهائيًا، فلا يعيدها استرجاع نسخة سابقة من ملف المحتوى. لاستعادتها استخدم نسخة ZIP كاملة نزّلتها قبل الحذف.</p>';
$any = false;
foreach ($files as $key => $label) {
    $list = adm_backups($key);
    if (!$list) {
        continue;
    }
    $any = true;
    $rows = '';
    foreach (array_slice($list, 0, 10) as $b) {
        $rows .= '<li class="adm-backup"><span class="adm-backup__when">' . e(adm_time($b['t'])) . '</span><span class="adm-backup__size">' . e(adm_bytes($b['size'])) . '</span>'
            . '<span class="adm-backup__actions">'
            . adm_action_button(['action' => adm_url('backup'), 'label' => 'تنزيل', 'icon' => 'download', 'variant' => 'ghost', 'small' => true, 'fields' => ['action' => 'download', 'file' => $key, 'id' => $b['id']], 'busy' => '…'])
            . adm_action_button(['action' => adm_url('backup'), 'label' => 'استرجاع', 'icon' => 'rotate-ccw', 'variant' => 'secondary', 'small' => true, 'fields' => ['action' => 'restore', 'file' => $key, 'id' => $b['id']],
                'confirm' => 'استرجاع «' . $label . '» إلى حالته في ' . adm_time($b['t']) . '؟ سيُستبدل المحتوى الحالي لهذا الملف (مع حفظ نسخة منه).', 'busy' => 'جارٍ الاسترجاع…'])
            . '</span></li>';
    }
    $listBody .= '<details class="adm-sub adm-sub--fold"><summary><span>' . e($label) . ' <span class="adm-muted">(' . e(adm_plural(count($list), 'نسخة واحدة', 'نسختان', 'نسخ', 'نسخة')) . ')</span></span>' . adm_icon('chevron-down', 'adm-fold__chev') . '</summary>'
        . '<div class="adm-sub__body"><ul class="adm-backups" role="list">' . $rows . '</ul>'
        . (count($list) > 10 ? '<p class="adm-hint">تظهر أحدث 10 نسخ.</p>' : '') . '</div></details>';
}
if (!$any) {
    $listBody .= adm_empty('database-backup', 'لا توجد نسخ تلقائية بعد', 'تظهر هنا بعد أول حفظ لأي محتوى.');
}
echo adm_card('النسخ التلقائية قبل كل حفظ', $listBody, ['icon' => 'history', 'id' => 'bk-auto']);
