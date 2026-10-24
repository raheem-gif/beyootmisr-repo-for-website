<?php
/**
 * Site footer (content document §11 "تذييل الموقع").
 */
defined('BM_APP') || exit;

$s = bm_settings();
$c = $s['contact'];
$social = array_filter([
    'facebook' => safe_url((string) $s['social']['facebook']),
    'instagram' => safe_url((string) $s['social']['instagram']),
]);
$c['address'] = setting_text((string) $c['address']);
$c['address_detail'] = setting_text((string) $c['address_detail']);
$c['email'] = safe_email((string) $c['email']);
// Number rows only when the number can be dialled, so no link gets an empty href.
$telFooter = tel_href((string) $c['hotline']);
$waLegalFooter = wa_legal();
$hasLegal = $s['legal']['registration_number'] !== '' && $s['legal']['registration_year'] !== '';
?>
<footer class="site-footer">
  <?= footer_skyline_svg() ?>
  <div class="site-footer__main">
    <div class="container footer-grid">
      <div class="footer-about">
        <?= brand_html('light') ?>
        <p class="footer-about__name"><?= te('site.full_name') ?></p>
        <p class="footer-about__slogan"><?= te('site.slogan') ?></p>
        <?php if ($hasLegal): ?>
        <p class="footer-about__legal"><?= icon('badge-check') ?><span><?= te('site.legal_line') ?></span></p>
        <?php endif; ?>
        <p class="footer-about__motto"><?= te('site.motto') ?></p>
      </div>

      <nav class="footer-col" aria-labelledby="footer-links-title">
        <h2 class="footer-title" id="footer-links-title"><?= te('nav.quick_links') ?></h2>
        <ul role="list">
          <li><a href="<?= e(u('about.html')) ?>"><?= te('nav.about') ?></a></li>
          <li><a href="<?= e(u('activities.html')) ?>"><?= te('nav.footer_activities') ?></a></li>
          <li><a href="<?= e(u('charter.html')) ?>"><?= te('nav.charter') ?></a></li>
          <li><a href="<?= e(u('achievements.html')) ?>"><?= te('nav.achievements') ?></a></li>
          <li><a href="<?= e(u('get-involved.html')) ?>"><?= te('nav.get_involved') ?></a></li>
          <li><a href="<?= e(u('contact.html')) ?>"><?= te('nav.contact') ?></a></li>
          <li><a href="<?= e(u('privacy.html')) ?>"><?= te('nav.privacy') ?></a></li>
        </ul>
      </nav>

      <nav class="footer-col footer-col--activities" aria-labelledby="footer-activities-title">
        <h2 class="footer-title" id="footer-activities-title"><?= te('nav.footer_activities') ?></h2>
        <ul role="list" class="footer-activities">
          <?php foreach (bm_activities() as $a): ?>
          <li><a href="<?= e(u($a['url'])) ?>"><?= e($a['name']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= e(u('ehna-maaki.html')) ?>"><?= te('ehna_maaki.name') ?></a></li>
        </ul>
      </nav>

      <div class="footer-col footer-contact">
        <h2 class="footer-title"><?= te('nav.footer_contact') ?></h2>
        <?php if ($telFooter !== '' || $waLegalFooter !== '' || $c['address'] !== '' || $c['email'] !== ''): ?>
        <ul role="list">
          <?php if ($telFooter !== ''): ?>
          <li><?= icon('phone') ?><span><span class="footer-contact__label"><?= te('contact.hotline_label') ?></span><a href="<?= e($telFooter) ?>"><?= phone_html($c['hotline']) ?></a></span></li>
          <?php endif; ?>
          <?php if ($waLegalFooter !== ''): ?>
          <li><?= icon('scale') ?><span><span class="footer-contact__label"><?= te('contact.legal_label') ?></span><a href="<?= e($waLegalFooter) ?>" target="_blank" rel="noopener"><?= phone_html($c['whatsapp_legal']) ?></a></span></li>
          <?php endif; ?>
          <?php if ($c['address'] !== ''): ?>
          <li><?= icon('map-pin') ?><span><span class="footer-contact__label"><?= te('contact.address_label') ?></span><?= e($c['address']) ?><?= $c['address_detail'] !== '' ? '، ' . e($c['address_detail']) : '' ?></span></li>
          <?php endif; ?>
          <?php if ($c['email'] !== ''): ?>
          <li><?= icon('mail') ?><span><span class="footer-contact__label"><?= te('contact.email_label') ?></span><a href="mailto:<?= e($c['email']) ?>"><bdi dir="ltr"><?= e($c['email']) ?></bdi></a></span></li>
          <?php endif; ?>
        </ul>
        <?php endif; ?>
        <?php if ($social): ?>
        <ul class="social" role="list">
          <?php foreach ($social as $net => $url): ?>
          <li><a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= te('contact.' . $net) ?>"><?= icon($net) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="site-footer__bottom">
    <div class="container footer-bottom">
      <p><?= te('ui.copyright') ?></p>
      <p class="footer-bottom__note"><?= icon('shield-check') ?><span><?= te('ui.footer_privacy_note') ?></span></p>
    </div>
  </div>
</footer>
