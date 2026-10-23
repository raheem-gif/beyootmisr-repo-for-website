<?php
/**
 * Admin sections (navigation order, labels, icons, permission keys).
 *
 * A section "key" is also its permission key (see adm_role_permissions() in users.php)
 * and its file: app/admin/sections/<key>.php. When that file does not exist yet the
 * section shows a "coming soon" page (sections/_stub.php) built from 'about'.
 *
 * Section file contract (included inside a function, once per request):
 *   - $user (array) is the logged-in user; AdmPage::$title / ::$lead / ::$actions set the page header.
 *   - Handle POST first (CSRF is already verified by the router), then adm_redirect() (PRG).
 *     On validation errors set AdmForm::$errors and fall through to render the form again.
 *   - Echo the page body (escape with e()); the router wraps it in the layout.
 *
 * To add a section: add it here, create sections/<key>.php, and (for editors) list
 * the key in adm_role_permissions().
 */
declare(strict_types=1);
defined('BM_APP') || exit;

function adm_sections(): array
{
    return [
        'dashboard' => [
            'label' => 'لوحة التحكم',
            'icon' => 'layout-dashboard',
            'group' => '',
        ],
        'texts' => [
            'label' => 'النصوص',
            'icon' => 'file-text',
            'group' => 'content',
            'about' => 'كل نصوص صفحات الموقع كما وردت في وثيقة المحتوى، مقسّمة حسب الصفحات.',
        ],
        'stats' => [
            'label' => 'أثرنا بالأرقام',
            'icon' => 'chart-column',
            'group' => 'content',
            'about' => 'أرقام الإنجازات الموثقة وتاريخ مراجعتها والفترة التي تغطيها. لا يظهر أي رقم على الموقع إلا بعد إدخال قيمته وتاريخ المراجعة.',
        ],
        'projects' => [
            'label' => 'المشروعات',
            'icon' => 'building-2',
            'group' => 'content',
            'about' => 'المشروعات الجارية والمنجزة: الحالة، والموقع، وتاريخ آخر تحديث، وصور التنفيذ ومراحله.',
        ],
        'stories' => [
            'label' => 'الحكايات',
            'icon' => 'book-open',
            'group' => 'content',
            'about' => 'حكايات الأثر (علاج، رزق، سقف، نجاح، مسجد) تُكتب دون كشف هوية أصحابها.',
        ],
        'reports' => [
            'label' => 'التقارير',
            'icon' => 'scroll-text',
            'group' => 'content',
            'about' => 'تقارير النشاط السنوية وتقارير المشروعات بصيغة PDF بعد مراجعتها واعتماد نشرها.',
        ],
        'galleries' => [
            'label' => 'معرض صور الأنشطة',
            'icon' => 'images',
            'group' => 'content',
            'about' => 'صور العمل الحقيقية لكل نشاط من الأنشطة العشرة، مع وصف لكل صورة.',
        ],
        'settings' => [
            'label' => 'الإعدادات',
            'icon' => 'settings',
            'group' => 'admin',
            'about' => 'بيانات التواصل والروابط الرسمية والهوية القانونية ووسائل التبرع وعنوان الموقع والشعار.',
        ],
        'users' => [
            'label' => 'المستخدمون',
            'icon' => 'users',
            'group' => 'admin',
            'about' => 'حسابات فريق العمل وأدوارهم.',
        ],
        'audit' => [
            'label' => 'سجل التغييرات',
            'icon' => 'history',
            'group' => 'admin',
            'about' => 'من غيّر ماذا ومتى.',
        ],
        'backup' => [
            'label' => 'نسخة احتياطية',
            'icon' => 'database-backup',
            'group' => 'admin',
            'about' => 'تنزيل نسخة كاملة من المحتوى والصور، واسترجاع نسخة سابقة من أي ملف محتوى.',
        ],
        'account' => [
            'label' => 'حسابي',
            'icon' => 'user',
            'group' => '',
            'nav' => false,
            'about' => 'اسمك وكلمة مرورك.',
        ],
    ];
}

function adm_nav_groups(): array
{
    return [
        '' => '',
        'content' => 'المحتوى',
        'admin' => 'الإدارة',
    ];
}

function adm_section_file(string $key): string
{
    return BM_APP_DIR . '/admin/sections/' . $key . '.php';
}

function adm_section_ready(string $key): bool
{
    return preg_match('/^[a-z][a-z0-9-]*$/', $key) === 1 && is_file(adm_section_file($key));
}
