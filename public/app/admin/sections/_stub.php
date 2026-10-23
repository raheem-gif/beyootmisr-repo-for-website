<?php
/**
 * Placeholder for sections that are registered in sections.php but whose file
 * (sections/<key>.php) does not exist yet. Stage 2 replaces these.
 */
defined('BM_APP') || exit;

$meta = adm_sections()[$section] ?? ['label' => '', 'about' => ''];
AdmPage::$title = (string) $meta['label'];
AdmPage::$lead = (string) ($meta['about'] ?? '');
?>
<?= adm_empty((string) ($meta['icon'] ?? 'info'), 'هذا القسم قيد الإعداد', 'ستتمكن قريبًا من إدارة «' . $meta['label'] . '» من هنا. حتى ذلك الحين يبقى المحتوى الحالي ظاهرًا على الموقع كما هو.') ?>
