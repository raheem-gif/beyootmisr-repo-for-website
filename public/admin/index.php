<?php
/**
 * Beyoot Misr — admin panel entry point (/admin/).
 * Everything lives in app/admin; see docs/ADMIN.md.
 */
if (PHP_VERSION_ID < 70400) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "لوحة الإدارة تحتاج PHP 7.4 أو أحدث. اختر إصدارًا أحدث من إعدادات الاستضافة.\n"
        . 'The admin panel needs PHP 7.4 or newer (this server runs ' . PHP_VERSION . ").\n";
    exit;
}

define('BM_APP', true);
define('ADM_WEB_DIR', __DIR__);

require dirname(__DIR__) . '/app/admin/bootstrap.php';

adm_run();
