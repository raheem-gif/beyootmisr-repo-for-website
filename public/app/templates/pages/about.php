<?php
/**
 * من نحن — content document §03 (+ §01: حكاية المؤسسة، الرؤية، الرسالة،
 * الأهداف، الوضع المؤسسي، ما نؤمن به).
 *
 * Sections: hero + in-page links, story (with facts card), vision & mission,
 * goals, «لماذا بيوت مصر؟», legal identity, charter teaser, «شاركنا الخير».
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('about.meta_title');
BM::$page['description'] = (string) t('about.meta_description');

$settings = bm_settings();
$legal = $settings['legal'];
$regNumber = trim((string) $legal['registration_number']);
$regYear = trim((string) $legal['registration_year']);
$hasReg = $regNumber !== '' && $regYear !== '';
$homeFacts = tl('home.intro.facts');
$address = trim((string) $settings['contact']['address']);

/* Facts card beside the story. Each line is shown only when its data exists. */
$facts = [];
if (isset($homeFacts[1])) {
    $facts[] = ['calendar', '', (string) $homeFacts[1]];
}
if ($hasReg) {
    $facts[] = ['badge-check', '', (string) t('site.legal_line')];
} elseif (isset($homeFacts[0])) {
    $facts[] = ['badge-check', '', (string) $homeFacts[0]];
}
if ($address !== '') {
    $facts[] = ['map-pin', (string) t('contact.address_label'), $address];
}
if (isset($homeFacts[2])) {
    $facts[] = ['heart-handshake', '', (string) $homeFacts[2]];
}

/* Legal identity: optional rows appear only once the approved wording is entered in settings. */
$legalRows = [];
foreach ([
    'registration_authority' => 'about.legal.authority_label',
    'public_benefit' => 'about.legal.public_benefit_label',
    'fundraising_permit' => 'about.legal.fundraising_label',
] as $field => $labelKey) {
    $value = trim((string) ($legal[$field] ?? ''));
    if ($value !== '') {
        $legalRows[] = [(string) t($labelKey), $value];
    }
}
$legalText = $hasReg
    ? (string) t('about.legal.text')
    : (string) t('site.full_name') . (isset($homeFacts[0]) ? ' — ' . $homeFacts[0] : '');

$goalIcons = ['hand-heart', 'house-plus', 'sprout', 'users', 'shield-check'];
$charterIcons = ['heart', 'eye-off', 'users', 'search', 'hand-heart', 'sprout', 'handshake', 'badge-check', 'sparkles'];

/* In-page links (the page sections listed in §01). */
$toc = [
    ['story', t('about.story.title')],
    ['vision', t('about.vision.title')],
    ['mission', t('about.mission.title')],
    ['goals', t('about.goals.title')],
    ['why', t('about.why.title')],
    ['legal', t('about.legal.title')],
    ['charter', t('about.charter.eyebrow')],
];
$tocHtml = '<nav class="about-toc" aria-label="' . te('about.toc_label') . '"><ul role="list">';
foreach ($toc as $item) {
    $tocHtml .= '<li><a href="#' . e($item[0]) . '">' . e((string) $item[1]) . '</a></li>';
}
$tocHtml .= '</ul></nav>';

/* «وراء كل باب حكاية»: an Egyptian house whose arched door is open and lit. */
$doorArt = '<svg class="about-door" viewBox="0 0 320 284" aria-hidden="true" focusable="false">'
    . '<defs><radialGradient id="about-door-light" cx="50%" cy="72%" r="70%">'
    . '<stop offset="0" stop-color="#FFF3D6"/><stop offset=".55" stop-color="#F8D48A"/><stop offset="1" stop-color="#EFA927"/>'
    . '</radialGradient></defs>'
    . '<circle cx="236" cy="74" r="56" fill="#FDEBC8"/>'
    . '<circle cx="236" cy="74" r="34" fill="#F8D48A" opacity=".55"/>'
    . '<path d="M30 46q7-7 14 0q7-7 14 0" fill="none" stroke="#9FCBE8" stroke-width="3" stroke-linecap="round"/>'
    . '<path d="M64 24q5-5 10 0q5-5 10 0" fill="none" stroke="#B9DBF0" stroke-width="3" stroke-linecap="round"/>'
    . '<path d="M0 246h320v38H0z" fill="#DCEDF8"/>'
    . '<path d="M0 246c80-5 160-6 320 0v6H0z" fill="#B9DBF0"/>'
    . '<rect x="58" y="78" width="204" height="170" rx="5" fill="#1984C0"/>'
    . '<path d="M50 68h220v14H50z" fill="#1572A6"/>'
    . '<path d="M62 56h26v12H62zM232 56h26v12h-26z" fill="#1572A6"/>'
    . '<path d="M116 248v-82a44 44 0 0 1 88 0v82z" fill="#1572A6"/>'
    . '<path class="about-door__light" d="M126 248v-80a34 34 0 0 1 68 0v80z" fill="url(#about-door-light)"/>'
    . '<path d="M126 248V170l26 9v61z" fill="#0F5A85"/>'
    . '<circle cx="146" cy="214" r="2.6" fill="#EFA927"/>'
    . '<path class="about-door__spill" d="M126 248h68l46 34H96z" fill="#FDEBC8"/>'
    . '<path class="about-door__win" d="M150 112V96a10 10 0 0 1 20 0v16z" fill="#EFA927"/>'
    . '<path d="M78 196v-30a14 14 0 0 1 28 0v30z" fill="#2B6E98"/>'
    . '<path class="about-door__win" d="M214 196v-30a14 14 0 0 1 28 0v30z" fill="#EFA927"/>'
    . '<path d="M74 200h36v5H74zM210 200h36v5h-36z" fill="#1572A6"/>'
    . '<path d="M230 248l4-20h20l4 20z" fill="#1572A6"/>'
    . '<path d="M244 228c-2-12 2-22 10-28M244 228c0-12-6-20-14-24M244 228c4-8 10-12 18-12" fill="none" stroke="#5BA8D6" stroke-width="4" stroke-linecap="round"/>'
    . '</svg>';

echo page_hero([
    'crumbs' => [[t('nav.about'), 'about.html']],
    'eyebrow' => t('about.hero.eyebrow'),
    'title' => t('about.hero.title'),
    'actions' => $tocHtml,
    'class' => 'about-hero',
]);
?>

<section class="section about-story" id="story" aria-labelledby="story-title">
  <div class="container about-story__grid">
    <div class="about-story__text">
      <h2 class="section-title" id="story-title"><?= te('about.story.title') ?></h2>
      <div class="about-story__body"><?= p_html(tl('about.story.paragraphs')) ?></div>
      <div class="actions about-story__actions">
        <?= btn(t('buttons.discover_activities'), 'activities.html', 'primary', ['icon_end' => 'arrow-left']) ?>
      </div>
    </div>
    <div class="about-facts-card">
      <div class="about-facts-card__art"><?= $doorArt ?></div>
      <?php if ($facts): ?>
      <ul class="about-facts" role="list">
        <?php foreach ($facts as $fact): ?>
        <li class="about-fact">
          <span class="about-fact__icon"><?= icon($fact[0]) ?></span>
          <span class="about-fact__text"><?php if ($fact[1] !== ''): ?><small><?= e($fact[1]) ?></small><?php endif; ?><?= e($fact[2]) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section section--tint about-vm" aria-labelledby="vision-title mission-title">
  <div class="container about-vm__grid">
    <div class="about-vision" id="vision">
      <div class="about-vm__head">
        <span class="about-vm__icon"><?= icon('sparkles') ?></span>
        <h2 class="about-vm__title" id="vision-title"><?= te('about.vision.title') ?></h2>
      </div>
      <p class="about-vision__statement"><?= icon('quote', 'about-vision__mark') ?><span><?= te('about.vision.statement') ?></span></p>
      <div class="about-vision__text"><?= p_html(t('about.vision.text')) ?></div>
      <div class="about-vision__art" aria-hidden="true"><?= hero_windows_svg('about-vision__windows') ?></div>
    </div>
    <div class="about-mission" id="mission">
      <div class="about-vm__head">
        <span class="about-vm__icon about-vm__icon--amber"><?= icon('heart-handshake') ?></span>
        <h2 class="about-vm__title" id="mission-title"><?= te('about.mission.title') ?></h2>
      </div>
      <div class="about-mission__text"><?= p_html(t('about.mission.text')) ?></div>
    </div>
  </div>
</section>

<?php $goals = tl('about.goals.items'); ?>
<section class="section about-goals" id="goals" aria-labelledby="goals-title">
  <div class="container">
    <?= section_head(['title' => t('about.goals.title'), 'id' => 'goals-title', 'align' => 'center']) ?>
    <ol class="goals" role="list">
      <?php foreach ($goals as $i => $goal): ?>
      <li class="goal">
        <div class="goal__top">
          <span class="goal__icon"><?= icon($goalIcons[$i % count($goalIcons)]) ?></span>
          <span class="goal__num" aria-hidden="true"><?= e(sprintf('%02d', $i + 1)) ?></span>
        </div>
        <p class="goal__text"><?= e((string) $goal) ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="about-why" id="why" aria-labelledby="why-title">
  <div class="about-why__art" aria-hidden="true"><?= hero_windows_svg('about-why__windows') ?></div>
  <div class="container about-why__inner">
    <div class="about-why__head">
      <span class="about-why__icon"><?= icon('house') ?></span>
      <h2 class="about-why__title" id="why-title"><?= te('about.why.title') ?></h2>
    </div>
    <div class="about-why__text"><?= p_html(t('about.why.text')) ?></div>
  </div>
</section>

<section class="section about-legal" id="legal" aria-labelledby="legal-title">
  <div class="container">
    <div class="legal-card<?= $hasReg ? '' : ' legal-card--plain' ?>">
      <div class="legal-card__main">
        <div class="legal-card__head">
          <span class="legal-card__icon"><?= icon('landmark') ?></span>
          <h2 class="legal-card__title" id="legal-title"><?= te('about.legal.title') ?></h2>
        </div>
        <p class="legal-card__text"><?= e($legalText) ?></p>
        <?php if ($legalRows): ?>
        <dl class="legal-card__rows">
          <?php foreach ($legalRows as $row): ?>
          <div class="legal-row">
            <dt><?= e($row[0]) ?></dt>
            <dd><?= nl2br(e($row[1]), false) ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
        <?php endif; ?>
      </div>
      <?php if ($hasReg): ?>
      <div class="legal-seal">
        <span class="legal-seal__badge" aria-hidden="true"><?= icon('badge-check') ?></span>
        <dl class="legal-seal__list">
          <div class="legal-seal__item">
            <dt><?= te('about.legal.number_label') ?></dt>
            <dd><bdi class="num"><?= e($regNumber) ?></bdi></dd>
          </div>
          <div class="legal-seal__item">
            <dt><?= te('about.legal.year_label') ?></dt>
            <dd><bdi class="num"><?= e($regYear) ?></bdi></dd>
          </div>
        </dl>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php $principles = tl('charter.principles'); ?>
<section class="section about-charter" id="charter" aria-labelledby="charter-title">
  <div class="container about-charter__inner">
    <div class="about-charter__intro">
      <?= section_head([
          'eyebrow' => t('about.charter.eyebrow'),
          'title' => t('about.charter.title'),
          'id' => 'charter-title',
          'text' => t('about.charter.text'),
          'actions' => btn(t('buttons.read_charter'), 'charter.html', 'primary', ['icon_end' => 'arrow-left']),
      ]) ?>
    </div>
    <?php if ($principles): ?>
    <ol class="about-charter__list" role="list">
      <?php foreach ($principles as $i => $p): ?>
      <?php if (!is_array($p) || trim((string) ($p['title'] ?? '')) === '') { continue; } ?>
      <li class="about-principle">
        <span class="about-principle__icon"><?= icon($charterIcons[$i % count($charterIcons)]) ?></span>
        <span><?= e((string) $p['title']) ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </div>
</section>

<?= cta_share() ?>
