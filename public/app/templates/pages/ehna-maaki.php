<?php
/**
 * مبادرة «إحنا معاكي» — content document §06 (مبادراتنا الخاصة).
 * Free legal support and guidance for widows, divorced women and mothers who
 * support their families. Copy is addressed to women in Egyptian Arabic.
 *
 * Sections: hero (WhatsApp CTA), topics, what is free / assessed, steps,
 * tagline band, contact block (legal WhatsApp first, then the general line).
 * Closures only — no named functions in templates.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('ehna_maaki.meta_title');
BM::$page['description'] = (string) t('ehna_maaki.meta_description');

$contact = bm_settings()['contact'];
$legalNum = trim((string) $contact['whatsapp_legal']);
$hotline = trim((string) $contact['hotline']);
$waLegal = wa_legal();

$waButton = function (string $size = 'lg') use ($waLegal): string {
    if ($waLegal === '') {
        return '';
    }
    return btn(t('ehna_maaki.cta_button'), $waLegal, 'whatsapp', [
        'icon' => 'whatsapp',
        'size' => $size,
        'attrs' => ['aria-label' => t('ehna_maaki.cta_button') . ' (' . t('ui.opens_whatsapp') . ')'],
    ]);
};

/* The contact note with its two numbers as tappable, direction-isolated links. */
$contactNote = '';
if ($legalNum !== '' && $hotline !== '') {
    $contactNote = te('ehna_maaki.contact_note', ['whatsapp_legal' => '__BM_LEGAL__', 'hotline' => '__BM_HOTLINE__']);
    $contactNote = str_replace(
        ['__BM_LEGAL__', '__BM_HOTLINE__'],
        [
            '<a href="' . e($waLegal) . '" target="_blank" rel="noopener">' . phone_html($legalNum) . '</a>',
            '<a href="' . e(tel_href($hotline)) . '">' . phone_html($hotline) . '</a>',
        ],
        $contactNote
    );
}

/* Tagline: the reassurance ("إنتِ مش مطالبة تعرفي القانون..") then the promise, on two lines. */
$tagline = trim((string) t('ehna_maaki.tagline'));
$taglineParts = preg_split('/(?<=\.\.)\s+/u', $tagline, 2) ?: [$tagline];
$taglineHtml = count($taglineParts) === 2
    ? '<span class="em-band__lead">' . e($taglineParts[0]) . '</span> <span class="em-band__promise">' . e($taglineParts[1]) . '</span>'
    : '<span class="em-band__promise">' . e($tagline) . '</span>';

/* Steps in a horizontal row on wide screens (the shared steps() has no class option). */
$stepsHtml = str_replace('class="steps"', 'class="steps steps--row em-steps__list"', steps(tl('ehna_maaki.steps')));

/* Hero illustration: a lit arched window holding the scales of justice, and a message bubble. */
$poster = '<div class="em-poster" aria-hidden="true">'
    . '<svg class="em-poster__art" viewBox="0 0 320 300" focusable="false">'
    . '<defs><radialGradient id="em-window-light" cx="50%" cy="72%" r="78%">'
    . '<stop offset="0" stop-color="#FFF6E0"/><stop offset=".55" stop-color="#F8D48A"/><stop offset="1" stop-color="#EFA927"/>'
    . '</radialGradient></defs>'
    . '<circle cx="58" cy="70" r="44" fill="#FDEBC8"/><circle cx="58" cy="70" r="27" fill="#F8D48A" opacity=".55"/>'
    . '<path d="M30 140q7-7 14 0q7-7 14 0" fill="none" stroke="#9FCBE8" stroke-width="3" stroke-linecap="round"/>'
    . '<rect x="8" y="264" width="304" height="16" rx="8" fill="#DCEDF8"/>'
    . '<path d="M72 268V130a88 88 0 0 1 176 0v138z" fill="#1984C0"/>'
    . '<path d="M98 268V136a62 62 0 0 1 124 0v132z" fill="url(#em-window-light)"/>'
    . '<path d="M98 268V136a62 62 0 0 1 124 0v132" fill="none" stroke="#1572A6" stroke-width="6" stroke-linejoin="round"/>'
    . '<g fill="none" stroke="#0F5A85" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M160 112v112" stroke-width="6"/>'
    . '<path d="M120 130h80" stroke-width="6"/>'
    . '<path d="M120 130l-15 40M120 130l15 40M200 130l-15 40M200 130l15 40" stroke-width="3"/>'
    . '</g>'
    . '<circle cx="160" cy="108" r="7" fill="#0F5A85"/>'
    . '<path d="M101 170h38a19 13 0 0 1-38 0zM181 170h38a19 13 0 0 1-38 0z" fill="#0F5A85"/>'
    . '<path d="M138 236h44l-7-12h-30z" fill="#0F5A85"/>'
    . '<path d="M92 268h136l12 12H80z" fill="#F8D48A" opacity=".75"/>'
    . '<g class="em-poster__bubble">'
    . '<path d="M222 58a18 18 0 0 1 18-18h46a18 18 0 0 1 18 18v12a18 18 0 0 1-18 18h-34l-16 14V88a18 18 0 0 1-14-17z" fill="#fff" stroke="#B9DBF0" stroke-width="2.5" stroke-linejoin="round"/>'
    . '<circle cx="246" cy="64" r="5" fill="#EFA927"/><circle cx="263" cy="64" r="5" fill="#EFA927"/><circle cx="280" cy="64" r="5" fill="#EFA927"/>'
    . '</g>'
    . '</svg>'
    . '</div>';
?>
<?= page_hero([
    'crumbs' => [[t('nav.activities'), 'activities.html'], [t('ehna_maaki.name'), 'ehna-maaki.html']],
    'icon' => 'scale',
    'eyebrow' => t('ehna_maaki.hero.eyebrow'),
    'title' => t('ehna_maaki.hero.title'),
    'subtitle' => t('ehna_maaki.hero.subtitle'),
    'text' => t('ehna_maaki.intro'),
    'actions' => $waButton('lg'),
    'aside' => $poster,
    'class' => 'em-hero',
]) ?>

<section class="section em-topics" aria-labelledby="topics-title">
  <div class="container em-topics__grid">
    <?= section_head([
        'title' => t('ehna_maaki.topics_title'),
        'id' => 'topics-title',
        'text' => t('ehna_maaki.topics_text'),
    ]) ?>
    <div class="em-topics__card">
      <?= icon('scale', 'em-topics__mark') ?>
      <?= chips(tl('ehna_maaki.topics')) ?>
    </div>
  </div>
</section>

<section class="section section--tint em-terms" aria-labelledby="terms-title">
  <div class="container">
    <?= section_head(['title' => t('ehna_maaki.terms_title'), 'id' => 'terms-title', 'align' => 'center']) ?>
    <ul class="em-terms__grid" role="list">
      <li class="em-term em-term--free">
        <span class="em-term__icon"><?= icon('check') ?></span>
        <h3 class="em-term__label"><?= te('ehna_maaki.terms_free_label') ?></h3>
        <p class="em-term__text"><?= te('ehna_maaki.terms_free') ?></p>
      </li>
      <li class="em-term em-term--assessed">
        <span class="em-term__icon"><?= icon('badge-info') ?></span>
        <h3 class="em-term__label"><?= te('ehna_maaki.terms_assessed_label') ?></h3>
        <p class="em-term__text"><?= te('ehna_maaki.terms_assessed') ?></p>
      </li>
    </ul>
  </div>
</section>

<section class="section em-steps" aria-labelledby="steps-title">
  <div class="container">
    <?= section_head(['title' => t('ehna_maaki.steps_title'), 'id' => 'steps-title', 'align' => 'center']) ?>
    <?= $stepsHtml ?>
    <?php if ($waLegal !== ''): ?>
    <div class="actions em-steps__cta"><?= $waButton('lg') ?></div>
    <?php endif; ?>
  </div>
</section>

<?php if ($tagline !== ''): ?>
<div class="em-band">
  <div class="container em-band__inner">
    <span class="em-band__icon"><?= icon('heart-handshake') ?></span>
    <p class="em-band__text"><?= $taglineHtml ?></p>
  </div>
</div>
<?php endif; ?>

<section class="section em-contact" id="contact" aria-labelledby="contact-title">
  <div class="container">
    <div class="em-contact__panel">
      <div class="em-contact__head">
        <span class="em-contact__icon"><?= icon('message-circle') ?></span>
        <h2 class="section-title" id="contact-title"><?= te('ehna_maaki.contact_title') ?></h2>
        <?php if ($contactNote !== ''): ?>
        <p class="em-contact__note"><?= $contactNote ?></p>
        <?php endif; ?>
      </div>
      <?= help_lines() ?>
    </div>
  </div>
</section>
