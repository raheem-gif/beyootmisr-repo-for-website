<?php
/**
 * تواصل معنا — content document §10.
 * Sections: hero, «قنواتنا الرسمية» (cards built from settings.contact and
 * settings.social), the WhatsApp message composer next to a short
 * explanation, «أسئلة شائعة», and «شاركنا الخير».
 *
 * A channel is shown only when its setting is filled in. Values that still
 * hold a "[…]" placeholder from the content document (e.g. «يُدرج بعد اعتماده»)
 * are treated as empty and never reach the page.
 * Closures only — no named functions in templates.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('contact.meta_title');
BM::$page['description'] = (string) t('contact.meta_description');

$settings = bm_settings();
$c = $settings['contact'];

/* A setting counts only when filled in and not a bracketed placeholder. */
$val = function ($v): string {
    $v = trim((string) $v);
    return ($v === '' || preg_match('/[\[\]]/u', $v)) ? '' : $v;
};
/* External links: plain http(s) URLs only. */
$url = function ($v) use ($val): string {
    $v = $val($v);
    return preg_match('#^https?://[^\s<>"]+$#i', $v) ? $v : '';
};

$hotline = $val($c['hotline']);
$waGeneral = $val($c['whatsapp_general']);
$legal = $val($c['whatsapp_legal']);
$address = $val($c['address']);
$addressDetail = $val($c['address_detail']);
$mapUrl = $url($c['map_url']);
$email = $val($c['email']);
$emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
$hours = $val($c['hours']);
$social = array_filter([
    'facebook' => $url($settings['social']['facebook']),
    'instagram' => $url($settings['social']['instagram']),
]);

/* The general line: the hotline, plus the general WhatsApp number when it differs. */
$mainNumber = $hotline !== '' ? $hotline : $waGeneral;
$extraWa = ($hotline !== '' && $waGeneral !== '' && intl_phone($waGeneral) !== intl_phone($hotline)) ? $waGeneral : '';
$waGeneralHref = $mainNumber !== '' ? wa_general() : '';
$waLegalHref = $legal !== '' ? wa_legal() : '';

/* Copy-number button: hidden until site.js confirms the clipboard is available. */
$copyBtn = function (string $targetId, string $number): string {
    return '<button class="ct-copy" type="button" data-copy="' . e($targetId) . '" data-copied="' . te('ui.copied') . '" hidden>'
        . icon('copy') . '<span aria-live="polite">' . te('ui.copy') . '</span><span class="sr-only"> ' . e($number) . '</span></button>';
};

/* Big number row (isolated LTR) with its copy button. */
$numberRow = function (string $id, string $number) use ($copyBtn): string {
    return '<div class="ct-line__num"><span class="ct-line__digits" id="' . e($id) . '">' . phone_html($number) . '</span>'
        . $copyBtn($id, $number) . '</div>';
};

/* Secondary channels (address, email, social pages, hours) — only those that are set. */
$cards = [];
if ($address !== '') {
    $body = '<p class="ct-card__value">' . e($address) . '</p>';
    if ($addressDetail !== '') {
        $body .= '<p class="ct-card__detail">' . nl2br(e($addressDetail), false) . '</p>';
    }
    if ($mapUrl !== '') {
        $body .= '<a class="ct-card__link" href="' . e($mapUrl) . '" target="_blank" rel="noopener">'
            . icon('external-link') . '<span>' . te('contact.map_link') . '</span></a>';
    }
    $cards[] = ['id' => 'address', 'icon' => 'map-pin', 'label' => t('contact.address_label'), 'body' => $body];
}
if ($email !== '') {
    $mail = '<bdi dir="ltr">' . e($email) . '</bdi>';
    $body = '<p class="ct-card__value ct-card__value--email">'
        . ($emailOk ? '<a href="mailto:' . e($email) . '">' . $mail . '</a>' : $mail) . '</p>';
    $cards[] = ['id' => 'email', 'icon' => 'mail', 'label' => t('contact.email_label'), 'body' => $body];
}
if ($social) {
    $body = '<ul class="ct-social" role="list">';
    foreach ($social as $net => $link) {
        $body .= '<li><a class="ct-social__link ct-social__link--' . e($net) . '" href="' . e($link) . '" target="_blank" rel="noopener">'
            . icon($net) . '<span>' . te('contact.' . $net) . '</span></a></li>';
    }
    $body .= '</ul>';
    $cards[] = ['id' => 'social', 'icon' => 'share-2', 'label' => t('contact.social_label'), 'body' => $body];
}
if ($hours !== '') {
    $cards[] = ['id' => 'hours', 'icon' => 'clock', 'label' => t('contact.hours_label'), 'body' => '<p class="ct-card__value">' . nl2br(e($hours), false) . '</p>'];
}

/* Hero art: the brand house with its lit, open door and a message on its way. */
$heroArt = '<div class="ct-hero-art" aria-hidden="true"><svg viewBox="0 0 420 340" focusable="false">'
    . '<circle cx="246" cy="146" r="124" fill="#FDEBC8" opacity=".55"/>'
    . '<circle cx="246" cy="146" r="80" fill="#F8D48A" opacity=".28"/>'
    . '<ellipse cx="210" cy="306" rx="206" ry="20" fill="#DCEDF8"/>'
    . '<ellipse cx="210" cy="302" rx="150" ry="9" fill="#B9DBF0" opacity=".7"/>'
    . '<path d="M197 64a20 20 0 0 1 26 0l104 86a12 12 0 0 1 4 9v133a8 8 0 0 1-8 8H97a8 8 0 0 1-8-8V159a12 12 0 0 1 4-9z" fill="#1984C0"/>'
    . '<path d="M197 64a20 20 0 0 1 26 0l104 86a12 12 0 0 1 4 9v10L216 76a10 10 0 0 0-12 0L89 169v-10a12 12 0 0 1 4-9z" fill="#1572A6"/>'
    . '<rect x="198" y="106" width="24" height="24" rx="5" fill="#fff" opacity=".92"/>'
    . '<path class="ct-hero-art__win" d="M112 222v-24a18 18 0 0 1 36 0v24z" fill="#EFA927"/>'
    . '<path d="M272 222v-24a18 18 0 0 1 36 0v24z" fill="#5BA8D6"/>'
    . '<rect x="107" y="221" width="46" height="6" rx="3" fill="#0F5A85"/><rect x="267" y="221" width="46" height="6" rx="3" fill="#0F5A85"/>'
    . '<path d="M174 300l-26 22h124l-26-22z" fill="#F8D48A" opacity=".6"/>'
    . '<path d="M178 300v-64a32 32 0 0 1 64 0v64z" fill="#EFA927"/>'
    . '<path d="M190 300v-60a20 20 0 0 1 40 0v60z" fill="#FBC765" opacity=".75"/>'
    . '<rect x="170" y="298" width="80" height="7" rx="3.5" fill="#0F5A85"/>'
    // message bubble (typing)
    . '<g class="ct-hero-art__bubble">'
    . '<path d="M42 40h88a22 22 0 0 1 22 22v26a22 22 0 0 1-22 22H86l-26 20 6-20H42a22 22 0 0 1-22-22V62a22 22 0 0 1 22-22z" fill="#122A3D" opacity=".07" transform="translate(0 6)"/>'
    . '<path d="M42 40h88a22 22 0 0 1 22 22v26a22 22 0 0 1-22 22H86l-26 20 6-20H42a22 22 0 0 1-22-22V62a22 22 0 0 1 22-22z" fill="#fff"/>'
    . '<circle cx="60" cy="75" r="8" fill="#1984C0"/><circle cx="86" cy="75" r="8" fill="#5BA8D6"/><circle cx="112" cy="75" r="8" fill="#9FCBE8"/>'
    . '</g>'
    // phone bubble
    . '<g class="ct-hero-art__phone">'
    . '<circle cx="364" cy="100" r="30" fill="#122A3D" opacity=".07" transform="translate(0 5)"/>'
    . '<circle cx="364" cy="100" r="30" fill="#fff"/>'
    . '<g transform="translate(350.5 86.5) scale(1.125)" fill="none" stroke="#1572A6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></g>'
    . '</g>'
    . '<circle cx="356" cy="196" r="5" fill="#EFA927"/><circle cx="58" cy="196" r="4" fill="#9FCBE8"/><circle cx="386" cy="250" r="3.5" fill="#5BA8D6"/>'
    . '</svg></div>';

/* WhatsApp composer (nothing is sent to or stored on the website). */
$form = wa_form(['id' => 'contact-form']);
$steps = tl('contact.compose.steps');

echo page_hero([
    'crumbs' => [[t('nav.contact'), 'contact.html']],
    'eyebrow' => t('contact.hero.eyebrow'),
    'title' => t('contact.hero.title'),
    'text' => t('contact.hero.text'),
    'aside' => $heroArt,
    'class' => 'ct-hero',
]);
?>

<section class="section ct-channels" aria-labelledby="channels-title">
  <div class="container">
    <?= section_head(['title' => t('contact.channels_title'), 'id' => 'channels-title']) ?>

    <?php if ($mainNumber !== '' || $legal !== ''): ?>
    <ul class="ct-lines<?= ($mainNumber !== '' && $legal !== '') ? ' ct-lines--two' : '' ?>" role="list">
      <?php if ($mainNumber !== ''): ?>
      <li class="ct-line ct-line--general">
        <div class="ct-line__head">
          <span class="ct-line__icon"><?= icon($hotline !== '' ? 'phone' : 'whatsapp') ?></span>
          <div>
            <h3 class="ct-line__label"><?= te('contact.hotline_label') ?></h3>
            <p class="ct-line__hint"><?= te('contact.hotline_hint') ?></p>
          </div>
        </div>
        <?= $numberRow('ct-num-general', $mainNumber) ?>
        <?php if ($extraWa !== ''): ?>
        <p class="ct-line__extra"><?= icon('whatsapp') ?><span><?= te('buttons.whatsapp') ?>:</span> <?= phone_html($extraWa) ?></p>
        <?php endif; ?>
        <div class="ct-line__actions">
          <?php if ($hotline !== ''): ?>
          <a class="btn btn--primary btn--lg" href="<?= e(tel_href($hotline)) ?>"><?= icon('phone') ?><span><?= te('buttons.call_hotline') ?></span></a>
          <?php endif; ?>
          <?php if ($waGeneralHref !== ''): ?>
          <a class="btn btn--whatsapp btn--lg" href="<?= e($waGeneralHref) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span><?= te('buttons.whatsapp') ?></span><span class="sr-only"> (<?= te('ui.opens_whatsapp') ?>)</span></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($legal !== ''): ?>
      <li class="ct-line ct-line--legal">
        <div class="ct-line__head">
          <span class="ct-line__icon"><?= icon('scale') ?></span>
          <div>
            <h3 class="ct-line__label"><?= te('contact.legal_label') ?></h3>
            <p class="ct-line__hint"><?= te('contact.legal_hint') ?></p>
          </div>
        </div>
        <?= $numberRow('ct-num-legal', $legal) ?>
        <div class="ct-line__actions">
          <a class="btn btn--whatsapp btn--lg" href="<?= e($waLegalHref) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span><?= te('buttons.legal_whatsapp') ?></span><span class="sr-only"> (<?= te('ui.opens_whatsapp') ?>)</span></a>
          <?= btn(t('contact.legal_more'), 'ehna-maaki.html', 'ghost', ['icon_end' => 'arrow-left', 'class' => 'ct-line__more']) ?>
        </div>
      </li>
      <?php endif; ?>
    </ul>
    <?php endif; ?>

    <?php if ($cards): ?>
    <ul class="ct-cards ct-cards--<?= count($cards) ?>" role="list">
      <?php foreach ($cards as $card): ?>
      <li class="ct-card ct-card--<?= e($card['id']) ?>">
        <span class="ct-card__icon"><?= icon($card['icon']) ?></span>
        <div class="ct-card__body">
          <h3 class="ct-card__label"><?= e($card['label']) ?></h3>
          <?= $card['body'] ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<?php if ($form !== ''): ?>
<section class="section section--tint ct-compose" aria-labelledby="compose-title">
  <div class="container ct-compose__grid">
    <div class="ct-compose__intro">
      <?= eyebrow(t('buttons.whatsapp')) ?>
      <h2 class="section-title" id="compose-title"><?= te('contact.form.title') ?></h2>
      <div class="section-lead"><?= p_html(t('contact.form.text')) ?></div>
      <?php if ($steps): ?>
      <ol class="ct-howto" role="list">
        <?php foreach ($steps as $i => $step): ?>
        <li class="ct-howto__step"><span class="ct-howto__num" aria-hidden="true"><?= $i + 1 ?></span><p><?= e(bm_fill((string) $step)) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
      <?php if ($legal !== ''): ?>
      <p class="ct-compose__note"><?= icon('scale') ?><span><?= te('contact.compose.legal_note') ?></span></p>
      <?php endif; ?>
      <p class="ct-compose__privacy"><?= icon('shield-check') ?><a href="<?= e(u('privacy.html')) ?>"><?= te('contact.compose.privacy_link') ?></a></p>
    </div>
    <div class="ct-compose__card">
      <div class="ct-compose__bar" aria-hidden="true">
        <?= brand_mark('ct-compose__avatar') ?>
        <span class="ct-compose__who"><strong><?= te('site.org_name') ?></strong><?= phone_html($waGeneral !== '' ? $waGeneral : $mainNumber) ?></span>
        <?= icon('whatsapp', 'ct-compose__wa') ?>
      </div>
      <?= $form ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* Strings inside lists are not filled by t(): fill {hotline} etc. here. */
$faq = [];
foreach (tl('contact.faq') as $item) {
    if (is_array($item) && trim((string) ($item['q'] ?? '')) !== '') {
        $faq[] = ['q' => bm_fill((string) $item['q']), 'a' => bm_fill((string) ($item['a'] ?? ''))];
    }
}
if ($faq): ?>
<section class="section ct-faq" aria-labelledby="faq-title">
  <div class="container container--narrow">
    <?= section_head(['title' => t('contact.faq_title'), 'id' => 'faq-title', 'align' => 'center']) ?>
    <?= faq($faq) ?>
  </div>
</section>
<?php endif; ?>

<?= cta_share() ?>
