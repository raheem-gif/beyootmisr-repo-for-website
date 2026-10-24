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
// Floating WhatsApp button: the «إحنا معاكي» page sends legal questions to the
// initiative's own WhatsApp line (§06/§10); every other page uses the general line.
// No number that can be dialled → no button (never an empty href).
$waLegalLink = $page['tpl'] === 'ehna-maaki' ? wa_legal() : '';
$waLegalPage = $waLegalLink !== '';
$wa = $waLegalPage ? $waLegalLink : wa_general();
$waLabel = $waLegalPage ? te('buttons.legal_whatsapp') : te('buttons.whatsapp');
?>
<!doctype html>
<html lang="ar" dir="rtl" data-l-close="<?= te('ui.close') ?>" data-l-prev="<?= te('ui.previous') ?>" data-l-next="<?= te('ui.next') ?>" data-l-gallery="<?= te('ui.gallery_title') ?>" data-l-image-of="<?= te('ui.image_of') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self' https://wa.me https://api.whatsapp.com">
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
<?php foreach (bm_page_styles((string) $page['tpl']) as $css): ?>
<link rel="stylesheet" href="<?= e(asset($css)) ?>">
<?php endforeach; ?>
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
<?php if ($wa !== ''): ?>
<aside class="wa-fab-region" aria-label="<?= $waLabel ?>">
  <a class="wa-fab" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="<?= $waLabel ?> (<?= te('ui.opens_whatsapp') ?>)">
    <span class="wa-fab__icon"><?= icon('whatsapp') ?></span><span class="wa-fab__label"><?= $waLabel ?></span>
  </a>
</aside>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
