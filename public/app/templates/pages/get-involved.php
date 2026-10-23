<?php
/**
 * شارك في الخير — content document §09 (and §01: «التبرع، التطوع، دعم المشروعات،
 * التبرعات العينية، طلب المساعدة»).
 *
 * The section ids are fixed: the header buttons and the «شاركنا الخير» band link
 * to #donate, #volunteer, #in-kind and #request-help.
 *
 * Donation methods appear only when settings.donations is enabled AND has
 * methods (verified accounts and permits, §09/§12); otherwise an honest
 * "announced once approved" notice with the hotline.
 * Closures only — no named functions in templates.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('get_involved.meta_title');
BM::$page['description'] = (string) t('get_involved.meta_description');

$settings = bm_settings();
$contact = $settings['contact'];
$donations = $settings['donations'];

$methods = [];
foreach ((array) $donations['methods'] as $m) {
    if (!is_array($m)) {
        continue;
    }
    $m = array_replace(['title' => '', 'details' => '', 'link' => '', 'note' => ''], $m);
    $m = array_map(function ($v) {
        return trim((string) $v);
    }, $m);
    // Official https links only.
    if ($m['link'] !== '' && !preg_match('#^https://[^\s<>"]+$#i', $m['link'])) {
        $m['link'] = '';
    }
    if ($m['title'] === '' || ($m['details'] === '' && $m['link'] === '')) {
        continue;
    }
    $methods[] = $m;
}
$canDonate = !empty($donations['enabled']) && $methods;
$allocationNote = trim((string) $donations['allocation_note']);

$hotline = trim((string) $contact['hotline']);
$telLink = $hotline !== '' ? tel_href($hotline) : '';
$waDonate = wa_general((string) t('get_involved.donate.wa_message'));
$waInKind = wa_general((string) t('get_involved.in_kind.wa_message'));

$ways = [
    ['id' => 'donate', 'icon' => 'hand-heart', 'label' => t('get_involved.donate.title')],
    ['id' => 'volunteer', 'icon' => 'users', 'label' => t('get_involved.volunteer.title')],
    ['id' => 'projects', 'icon' => 'building-2', 'label' => t('get_involved.projects.title')],
    ['id' => 'in-kind', 'icon' => 'package', 'label' => t('get_involved.in_kind.title')],
    ['id' => 'request-help', 'icon' => 'hand-helping', 'label' => t('get_involved.help.title')],
];
$wayIcon = [];
foreach ($ways as $w) {
    $wayIcon[$w['id']] = $w['icon'];
}

/* Section heading: arched icon badge (same as its tile above) + h2 + lead text. */
$head = function (string $id, string $title, string $text = '') use ($wayIcon): string {
    return '<div class="gi-head">'
        . '<span class="gi-head__icon">' . icon($wayIcon[$id] ?? 'heart') . '</span>'
        . '<h2 class="section-title gi-head__title" id="' . e($id) . '-title">' . e($title) . '</h2>'
        . ($text !== '' ? '<div class="section-lead gi-head__text">' . p_html($text) . '</div>' : '')
        . '</div>';
};

/* Call button that also shows the number (isolated LTR). */
$callBtn = function (string $variant = 'primary', string $size = '') use ($hotline, $telLink): string {
    if ($telLink === '') {
        return '';
    }
    return '<a class="btn btn--' . e($variant) . ($size !== '' ? ' btn--' . e($size) : '') . ' gi-call" href="' . e($telLink) . '">'
        . icon('phone') . '<span>' . te('buttons.call_hotline') . '</span>' . phone_html($hotline) . '</a>';
};

/* A rough icon per donation method, from its name (bank account, wallet, link). */
$methodIcon = function (array $m): string {
    $title = $m['title'];
    if (preg_match('/بنك|حساب|ايبان|آيبان|IBAN/ui', $title)) {
        return 'landmark';
    }
    if (preg_match('/محفظ|كاش|إنستا|انستا|فوري|wallet/ui', $title)) {
        return 'phone';
    }
    return $m['details'] === '' ? 'external-link' : 'hand-coins';
};

/* The activities a donor can choose from («اختر النشاط الأقرب إلى قلبك»). */
$activities = bm_activities();

/* Projects: up to three ongoing published projects. */
$projects = array_slice(array_values(array_filter(bm_projects(), function ($p) {
    return $p['status'] === 'ongoing';
})), 0, 3);

/* In-kind giving: the two activities built on it. */
$inKindActs = array_values(array_filter([bm_activity('kiswa'), bm_activity('mazad-al-khair')]));

/* Volunteer form (opens WhatsApp; nothing is stored on the site). */
$topics = tl('contact.form.topics');
$volunteerForm = wa_form([
    'id' => 'volunteer-form',
    'title' => t('get_involved.volunteer.form_title'),
    'preset' => in_array('التطوع', $topics, true) ? 'التطوع' : '',
    'no_legal' => true,
]);
?>
<?= page_hero([
    'crumbs' => [[t('nav.get_involved'), 'get-involved.html']],
    'eyebrow' => t('get_involved.hero.eyebrow'),
    'title' => t('get_involved.hero.title'),
    'text' => t('get_involved.hero.text'),
    'class' => 'gi-hero',
]) ?>

<nav class="gi-ways" aria-labelledby="ways-title">
  <div class="container">
    <div class="gi-ways__card">
      <h2 class="gi-ways__title" id="ways-title"><?= te('get_involved.ways_title') ?></h2>
      <ul class="gi-ways__list" role="list">
        <?php foreach ($ways as $w): ?>
        <li>
          <a class="gi-way gi-way--<?= e($w['id']) ?>" href="#<?= e($w['id']) ?>">
            <span class="gi-way__icon"><?= icon($w['icon']) ?></span>
            <span class="gi-way__label"><?= e($w['label']) ?></span>
            <?= icon('chevron-down', 'gi-way__arrow') ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>

<section class="section gi-section gi-donate" id="donate" aria-labelledby="donate-title">
  <div class="container">
    <div class="gi-donate__grid<?= $canDonate ? ' gi-donate__grid--open' : '' ?>">
      <div class="gi-donate__intro">
        <?= $head('donate', (string) t('get_involved.donate.title'), (string) t('get_involved.donate.text')) ?>
        <ul class="gi-acts" role="list" aria-label="<?= te('buttons.support_activity') ?>">
          <?php foreach ($activities as $a): ?>
          <li><a class="gi-act" href="<?= e(u($a['url'])) ?>"><span class="gi-act__icon"><?= icon($a['icon']) ?></span><span><?= e($a['name']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if (!$canDonate): ?>
      <div class="gi-donate__panel">
        <span class="gi-donate__panel-icon"><?= icon('hand-coins') ?></span>
        <?= p_html((string) t('get_involved.donate.pending'), 'gi-donate__pending') ?>
        <div class="actions gi-donate__actions">
          <?= $callBtn('primary') ?>
          <?php if ($waDonate !== ''): ?>
          <?= btn(t('buttons.whatsapp'), $waDonate, 'whatsapp', ['icon' => 'whatsapp']) ?>
          <?php endif; ?>
        </div>
        <div class="gi-donate__more">
          <?= btn(t('buttons.support_activity'), 'activities.html', 'outline', ['icon' => 'heart-handshake', 'icon_end' => 'arrow-left']) ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($canDonate): ?>
    <div class="gi-methods-wrap">
      <h3 class="gi-subtitle"><?= icon('shield-check') ?><span><?= te('get_involved.donate.methods_title') ?></span></h3>
      <ul class="gi-methods<?= count($methods) === 1 ? ' gi-methods--single' : '' ?>" role="list">
        <?php foreach ($methods as $i => $m): $mid = 'donation-method-' . ($i + 1); ?>
        <li class="gi-method">
          <div class="gi-method__head">
            <span class="gi-method__icon"><?= icon($methodIcon($m)) ?></span>
            <h4 class="gi-method__title" id="<?= e($mid) ?>-title"><?= e($m['title']) ?></h4>
          </div>
          <?php if ($m['details'] !== ''): ?>
          <div class="gi-method__details" id="<?= e($mid) ?>"><?= nl2br(e($m['details']), false) ?></div>
          <?php endif; ?>
          <?php if ($m['details'] !== '' || $m['link'] !== ''): ?>
          <div class="gi-method__actions">
            <?php if ($m['details'] !== ''): ?>
            <button type="button" class="btn btn--outline btn--sm" data-copy="<?= e($mid) ?>" data-copied="<?= te('ui.copied') ?>" aria-describedby="<?= e($mid) ?>-title" hidden><?= icon('copy') ?><span><?= te('ui.copy') ?></span></button>
            <?php endif; ?>
            <?php if ($m['link'] !== ''): ?>
            <?= btn(t('buttons.donate_now'), $m['link'], 'donate', ['size' => 'sm', 'icon_end' => 'external-link', 'attrs' => ['aria-describedby' => $mid . '-title']]) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($m['note'] !== ''): ?>
          <p class="gi-method__note"><?= icon('badge-info') ?><span><?= e($m['note']) ?></span></p>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="gi-donate__notes">
        <?php if ($allocationNote !== ''): ?>
        <?= notice($allocationNote, 'info', 'badge-info') ?>
        <?php endif; ?>
        <?= notice((string) t('get_involved.donate.official_only'), 'privacy', 'shield-check') ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tint gi-section gi-volunteer" id="volunteer" aria-labelledby="volunteer-title">
  <div class="container gi-split<?= $volunteerForm === '' ? ' gi-split--solo' : '' ?>">
    <div class="gi-volunteer__intro">
      <?= $head('volunteer', (string) t('get_involved.volunteer.title'), (string) t('get_involved.volunteer.text')) ?>
      <ul class="gi-skills" role="list" aria-hidden="true">
        <?php foreach (['heart-pulse', 'scale', 'hard-hat', 'clipboard-list', 'hammer', 'clock'] as $skill): ?>
        <li><?= icon($skill) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($volunteerForm !== ''): ?>
    <div class="gi-volunteer__form"><?= $volunteerForm ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="section gi-section gi-projects" id="projects" aria-labelledby="projects-title">
  <div class="container">
    <div class="gi-projects__top">
      <?= $head('projects', (string) t('get_involved.projects.title'), (string) t('get_involved.projects.text')) ?>
      <div class="actions gi-projects__more">
        <?= btn(t('get_involved.projects.more'), 'achievements.html#projects', 'outline', ['icon_end' => 'arrow-left']) ?>
        <?php if ($canDonate): ?>
        <?= btn(t('buttons.donate_now'), '#donate', 'donate', ['icon' => 'heart']) ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($projects): ?>
    <ul class="gi-projects__list gi-projects__list--<?= count($projects) ?>" role="list">
      <?php foreach ($projects as $p): ?>
      <li><?= project_card($p) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <?= empty_state('building-2', '', (string) t('ui.projects_empty')) ?>
    <?php endif; ?>
  </div>
</section>

<section class="section section--blue gi-section gi-inkind" id="in-kind" aria-labelledby="in-kind-title">
  <div class="container gi-split">
    <div class="gi-inkind__intro">
      <?= $head('in-kind', (string) t('get_involved.in_kind.title'), (string) t('get_involved.in_kind.text')) ?>
      <div class="actions gi-inkind__actions">
        <?php if ($waInKind !== ''): ?>
        <?= btn(t('buttons.whatsapp'), $waInKind, 'whatsapp', ['icon' => 'whatsapp', 'size' => 'lg']) ?>
        <?php endif; ?>
        <?= $callBtn('outline') ?>
      </div>
    </div>
    <?php if ($inKindActs): ?>
    <div class="gi-inkind__related">
      <h3 class="gi-subtitle" id="in-kind-related"><?= icon('package') ?><span><?= te('get_involved.in_kind.related_title') ?></span></h3>
      <ul class="gi-inkind__acts" role="list" aria-labelledby="in-kind-related">
        <?php foreach ($inKindActs as $a): ?>
        <li><?= activity_card($a, 'h4') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="section gi-section gi-help" id="request-help" aria-labelledby="request-help-title">
  <div class="container">
    <div class="gi-help__panel">
      <div class="gi-help__intro">
        <?= $head('request-help', (string) t('get_involved.help.title'), (string) t('get_involved.help.text')) ?>
      </div>
      <div class="gi-help__lines">
        <?= help_lines() ?>
        <?= notice((string) t('get_involved.help.privacy_note'), 'privacy', 'shield-check') ?>
        <a class="gi-legal-link" href="<?= e(u('ehna-maaki.html')) ?>">
          <span class="gi-legal-link__icon"><?= icon('scale') ?></span>
          <span class="gi-legal-link__text"><strong><?= te('ehna_maaki.name') ?></strong><small><?= te('ehna_maaki.hero.subtitle') ?></small></span>
          <?= icon('arrow-left', 'gi-legal-link__arrow') ?>
        </a>
      </div>
    </div>
  </div>
</section>
