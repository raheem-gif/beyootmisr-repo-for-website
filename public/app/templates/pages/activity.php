<?php
/**
 * صفحة النشاط — content document §05: «لكل نشاط صفحة فرعية تتضمن: تعريفًا مختصرًا،
 * أنواع المساعدات، أمثلة لمشروعاته، معرض صور حقيقية، إنجازات موثقة، وزر «ساهم في
 * هذا النشاط» حيث تتاح مساهمات رسمية».
 *
 * Rendered once per activity (BM::$page['activity'] holds the slug), so this file
 * must not declare named functions — closures only.
 */
defined('BM_APP') || exit;

$slug = (string) (BM::$page['activity'] ?? '');
$a = bm_activity($slug);
if (!$a) {
    throw new RuntimeException('Unknown activity: ' . $slug);
}

BM::$page['title'] = $a['name'] . ' | ' . $a['subtitle'];
BM::$page['description'] = excerpt((string) $a['description']);

$settings = bm_settings();
$contact = $settings['contact'];
$donations = $settings['donations'];
$canDonate = !empty($donations['enabled']) && !empty($donations['methods']);
$allocationNote = trim((string) ($donations['allocation_note'] ?? ''));
$waLink = wa_general((string) t('activities.wa_message', ['activity' => $a['name']]));
$hotline = trim((string) $contact['hotline']);
$telLink = $hotline !== '' ? tel_href($hotline) : '';

/* Neighbours (wrapping around) and the other nine activities. */
$all = bm_activities();
$slugs = array_keys($all);
$count = count($slugs);
$pos = (int) array_search($slug, $slugs, true);
$prev = $all[$slugs[($pos - 1 + $count) % $count]];
$next = $all[$slugs[($pos + 1) % $count]];
$others = array_filter($all, function ($x) use ($slug) {
    return $x['slug'] !== $slug;
});

/* Documentation: published projects, real photos (activity gallery + its projects), verified numbers. */
$projects = bm_projects(true, $slug);
$images = bm_gallery($slug);
foreach ($projects as $p) {
    foreach (bm_project_all_images($p) as $img) {
        $images[] = $img;
    }
}
$stats = bm_stats_for($slug);
$statsPublished = false;
foreach ($stats as $item) {
    if (bm_stat_published($item)) {
        $statsPublished = true;
        break;
    }
}

$assistTitle = trim((string) $a['assistance_title']) !== '' ? (string) $a['assistance_title'] : (string) t('activities.section_assistance');
$assistance = array_values(array_filter(array_map('strval', (array) $a['assistance']), function ($x) {
    return trim($x) !== '';
}));

/*
 * The three documentation sections. Each is "compact" while it only has an
 * honest empty/pending state; consecutive compact sections share one row on
 * wide screens so a young activity page never looks like a wall of placeholders.
 */
$docs = [];

if ($projects) {
    $list = '<ul class="act-projects' . (count($projects) === 1 ? ' act-projects--single' : '') . '" role="list">';
    foreach ($projects as $p) {
        $list .= '<li>' . project_card($p) . '</li>';
    }
    $docs[] = ['id' => 'projects', 'title' => t('activities.section_projects'), 'html' => $list . '</ul>', 'compact' => false];
} else {
    $docs[] = ['id' => 'projects', 'title' => t('activities.section_projects'), 'html' => empty_state('clipboard-list', '', (string) t('ui.projects_empty')), 'compact' => true];
}

$docs[] = [
    'id' => 'gallery',
    'title' => t('activities.section_gallery'),
    'html' => gallery($images, ['group' => 'activity-' . $slug]),
    'compact' => !$images,
];

$docs[] = [
    'id' => 'achievements',
    'title' => t('activities.section_achievements'),
    'html' => $stats
        ? stat_grid($stats, ['show_meta' => true])
        : notice((string) t('activities.achievements_empty'), 'pending', 'badge-check'),
    'compact' => !$statsPublished,
];

$docSection = function (array $d): string {
    return '<section class="act-doc act-doc--' . e($d['id']) . '" id="' . e($d['id']) . '" aria-labelledby="' . e($d['id']) . '-title">'
        . '<h2 class="act-doc__title" id="' . e($d['id']) . '-title">' . e((string) $d['title']) . '</h2>'
        . '<div class="act-doc__body">' . $d['html'] . '</div>'
        . '</section>';
};

/* Group consecutive compact sections into one row. */
$rows = [];
foreach ($docs as $d) {
    $last = count($rows) - 1;
    if ($d['compact'] && $last >= 0 && $rows[$last]['compact']) {
        $rows[$last]['items'][] = $d;
    } else {
        $rows[] = ['compact' => $d['compact'], 'items' => [$d]];
    }
}

/* Hero illustration: the activity as a house with a lit arched door and its house number. */
$poster = '<div class="act-poster" aria-hidden="true">'
    . '<svg class="act-poster__art" viewBox="0 0 320 300" focusable="false">'
    . '<defs><radialGradient id="act-door-light" cx="50%" cy="78%" r="75%">'
    . '<stop offset="0" stop-color="#FFF6E0"/><stop offset=".5" stop-color="#F8D48A"/><stop offset="1" stop-color="#EFA927"/>'
    . '</radialGradient></defs>'
    . '<circle cx="250" cy="70" r="52" fill="#FDEBC8"/><circle cx="250" cy="70" r="32" fill="#F8D48A" opacity=".55"/>'
    . '<path d="M54 92q7-7 14 0q7-7 14 0" fill="none" stroke="#9FCBE8" stroke-width="3" stroke-linecap="round"/>'
    . '<rect x="8" y="264" width="304" height="16" rx="8" fill="#DCEDF8"/>'
    . '<path d="M151 38.5a14 14 0 0 1 18 0l84 69a14 14 0 0 1 5 10.8V268H62V118.3a14 14 0 0 1 5-10.8z" fill="#1984C0"/>'
    . '<rect x="136" y="78" width="48" height="32" rx="8" fill="#fff"/>'
    . '<text class="act-poster__num" x="160" y="101" text-anchor="middle">' . e($a['num']) . '</text>'
    . '<path d="M76 206v-28a14 14 0 0 1 28 0v28z" fill="#0F5A85" opacity=".6"/>'
    . '<path d="M216 206v-28a14 14 0 0 1 28 0v28z" fill="#EFA927"/>'
    . '<path d="M118 268V178a42 42 0 0 1 84 0v90z" fill="url(#act-door-light)"/>'
    . '<path d="M118 268V178a42 42 0 0 1 84 0v90" fill="none" stroke="#1572A6" stroke-width="6" stroke-linejoin="round"/>'
    . '<path d="M114 268h92l12 12H102z" fill="#F8D48A" opacity=".7"/>'
    . '</svg>'
    . '<span class="act-poster__icon">' . icon($a['icon']) . '</span>'
    . '</div>';

$heroActions = $canDonate
    ? btn(t('buttons.contribute_activity'), 'get-involved.html#donate', 'donate', ['icon' => 'hand-heart', 'size' => 'lg'])
    : '';
?>
<?= page_hero([
    'crumbs' => [[t('nav.activities'), 'activities.html'], [$a['name'], $a['url']]],
    'icon' => $a['icon'],
    'eyebrow' => t('activities.number_label', ['num' => $a['num']]),
    'title' => $a['name'],
    'subtitle' => $a['subtitle'],
    'text' => $a['tagline'],
    'actions' => $heroActions,
    'aside' => $poster,
    'class' => 'act-hero',
]) ?>

<div class="section act-intro">
  <div class="container act-intro__grid">
    <section class="act-about" id="about" aria-labelledby="about-title">
      <h2 class="section-title" id="about-title"><?= te('activities.section_about') ?></h2>
      <div class="act-about__text"><?= p_html($a['description']) ?></div>
    </section>
    <?php if ($assistance): ?>
    <section class="act-assist" id="assistance" aria-labelledby="assistance-title">
      <h2 class="act-assist__title" id="assistance-title"><span class="act-assist__icon"><?= icon('list-checks') ?></span><?= e($assistTitle) ?></h2>
      <?= check_list($assistance, 'act-assist__list') ?>
    </section>
    <?php endif; ?>
  </div>
</div>

<div class="section section--tint act-docs">
  <div class="container act-docs__inner">
    <?php foreach ($rows as $row): ?>
    <?php if ($row['compact'] && count($row['items']) > 1): ?>
    <div class="act-docs__row act-docs__row--<?= count($row['items']) ?>">
      <?php foreach ($row['items'] as $d): ?>
      <?= $docSection($d) ?>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <?php foreach ($row['items'] as $d): ?>
    <?= $docSection($d) ?>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

<section class="section act-contribute-wrap" id="contribute" aria-labelledby="contribute-title">
  <div class="container">
    <div class="act-contribute<?= $canDonate ? ' act-contribute--open' : '' ?>">
      <span class="act-contribute__icon"><?= icon($canDonate ? 'hand-heart' : 'heart-handshake') ?></span>
      <div class="act-contribute__body">
        <?= eyebrow($a['name'], 'eyebrow--light') ?>
        <h2 class="act-contribute__title" id="contribute-title"><?= te('buttons.contribute_activity') ?></h2>
        <?= p_html((string) t($canDonate ? 'activities.contribute_text' : 'activities.contribute_pending'), 'act-contribute__text') ?>
        <?php if ($canDonate && $allocationNote !== ''): ?>
        <p class="act-contribute__note"><?= icon('badge-info') ?><span><?= e($allocationNote) ?></span></p>
        <?php endif; ?>
      </div>
      <div class="actions act-contribute__actions">
        <?php if ($canDonate): ?>
        <?= btn(t('buttons.contribute_activity'), 'get-involved.html#donate', 'donate', ['icon' => 'hand-heart', 'size' => 'lg']) ?>
        <?php if ($waLink !== ''): ?>
        <?= btn(t('buttons.whatsapp'), $waLink, 'outline-light', ['icon' => 'whatsapp']) ?>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($waLink !== ''): ?>
        <?= btn(t('buttons.whatsapp'), $waLink, 'whatsapp', ['icon' => 'whatsapp', 'size' => 'lg']) ?>
        <?php endif; ?>
        <?php if ($telLink !== ''): ?>
        <a class="btn btn--outline-light btn--lg act-call" href="<?= e($telLink) ?>"><?= icon('phone') ?><span><?= te('buttons.call_hotline') ?></span><?= phone_html($hotline) ?></a>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="section section--tint act-more">
  <div class="container">
    <nav class="act-pager" aria-label="<?= te('activities.pager_label') ?>">
      <a class="act-pager__link act-pager__link--prev" href="<?= e(u($prev['url'])) ?>">
        <span class="act-pager__dir"><?= icon('arrow-right') ?><?= te('ui.prev_activity') ?></span>
        <span class="act-pager__main"><span class="act-pager__icon"><?= icon($prev['icon']) ?></span><span class="act-pager__text"><strong class="act-pager__name"><?= e($prev['name']) ?></strong><small class="act-pager__sub"><?= e($prev['subtitle']) ?></small></span></span>
      </a>
      <a class="act-pager__link act-pager__link--next" href="<?= e(u($next['url'])) ?>">
        <span class="act-pager__dir"><?= te('ui.next_activity') ?><?= icon('arrow-left') ?></span>
        <span class="act-pager__main"><span class="act-pager__icon"><?= icon($next['icon']) ?></span><span class="act-pager__text"><strong class="act-pager__name"><?= e($next['name']) ?></strong><small class="act-pager__sub"><?= e($next['subtitle']) ?></small></span></span>
      </a>
    </nav>

    <section class="act-others" aria-labelledby="others-title">
      <div class="act-others__head">
        <h2 class="act-others__title" id="others-title"><?= te('ui.other_activities') ?></h2>
        <a class="act-others__all" href="<?= e(u('activities.html')) ?>"><?= te('nav.activities_all') ?><?= icon('arrow-left') ?></a>
      </div>
      <ul class="act-others__list" role="list">
        <?php foreach ($others as $o): ?>
        <li><a class="act-other" href="<?= e(u($o['url'])) ?>"><span class="act-other__icon"><?= icon($o['icon']) ?></span><span class="act-other__name"><?= e($o['name']) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
