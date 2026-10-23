<?php
/**
 * Page layout. Receives $content (rendered page body) and reads BM::$page.
 * Templates may set BM::$page['title'|'description'|'image'|'body_class'].
 */
defined('BM_APP') || exit;

$page = BM::$page;
$isHome = $page['tpl'] === 'home';
$siteName = (string) t('site.org_name');
$title = $page['title'] !== '' ? $page['title'] : (string) t('site.slogan');
$docTitle = $isHome ? $title . ' | ' . t('site.full_name') : $title . ' | ' . $siteName;
$description = $page['description'] !== '' ? $page['description'] : (string) t('site.meta_description');
$canonical = absolute_url($page['path']);
$ogImage = absolute_url($page['image'] !== '' ? $page['image'] : 'assets/img/og-image.png');
$settings = bm_settings();
?>
<!doctype html>
<html lang="ar" dir="rtl" data-l-close="<?= te('ui.close') ?>" data-l-prev="<?= te('ui.previous') ?>" data-l-next="<?= te('ui.next') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($page['noindex']): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($canonical !== '' && !$page['noindex']): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<meta name="theme-color" content="#1984C0">
<meta property="og:type" content="website">
<meta property="og:locale" content="ar_EG">
<meta property="og:site_name" content="<?= te('site.full_name') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php if ($canonical !== ''): ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="icon" href="<?= e(u('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(u('assets/img/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= e(u('assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(u('site.webmanifest')) ?>">
<link rel="preload" href="<?= e(u('assets/fonts/cairo-arabic.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php if ($isHome): ?>
<script type="application/ld+json"><?= json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'NGO',
    'name' => t('site.full_name'),
    'alternateName' => t('site.name'),
    'slogan' => t('site.slogan'),
    'foundingDate' => '2020',
    'url' => absolute_url() ?: null,
    'logo' => absolute_url('assets/img/apple-touch-icon.png') ?: null,
    'telephone' => $settings['contact']['hotline'] !== '' ? '+' . intl_phone($settings['contact']['hotline']) : null,
    'email' => safe_email((string) $settings['contact']['email']) ?: null,
    'address' => $settings['contact']['address'] !== '' ? ['@type' => 'PostalAddress', 'streetAddress' => $settings['contact']['address'], 'addressCountry' => 'EG'] : null,
    'sameAs' => array_values(array_filter([safe_url((string) $settings['social']['facebook']), safe_url((string) $settings['social']['instagram'])])) ?: null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="tpl-<?= e($page['tpl']) ?><?= $page['body_class'] !== '' ? ' ' . e($page['body_class']) : '' ?>">
<a class="skip-link" href="#main"><?= te('ui.skip') ?></a>
<?php include BM_APP_DIR . '/templates/partials/header.php'; ?>
<main id="main" tabindex="-1">
<?= $content ?>
</main>
<?php include BM_APP_DIR . '/templates/partials/footer.php'; ?>
<?php $wa = wa_general(); if ($wa !== ''): ?>
<a class="wa-fab" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="<?= te('buttons.whatsapp') ?> (<?= te('ui.opens_whatsapp') ?>)">
  <span class="wa-fab__icon"><?= icon('whatsapp') ?></span><span class="wa-fab__label"><?= te('buttons.whatsapp') ?></span>
</a>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
