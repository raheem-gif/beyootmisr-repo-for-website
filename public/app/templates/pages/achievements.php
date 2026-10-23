<?php
/**
 * إنجازاتنا ومشروعاتنا — content document §08, and §12 «المشروع والإنجازات»:
 * «كل مشروع له حالة وتاريخ آخر تحديث وصور متدرجة، ولا يظهر رقم غير موثق أو
 * مشروع منجز وهو ما زال جاريًا».
 *
 * Sections: hero (with in-page links), أثرنا بالأرقام (verified numbers only),
 * مشروعات جارية ومنجزة (status filter), التقارير (PDF downloads), «شاركنا الخير».
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('achievements.meta_title');
BM::$page['description'] = (string) t('achievements.meta_description');

$stats = bm_stats_for('achievements');
$projects = bm_projects(true);
$reports = bm_reports(true);

/* Project counts per status (the filter only appears when both kinds exist). */
$counts = ['all' => count($projects), 'ongoing' => 0, 'done' => 0];
foreach ($projects as $p) {
    $counts[$p['status'] === 'done' ? 'done' : 'ongoing']++;
}
$showFilter = $counts['ongoing'] > 0 && $counts['done'] > 0;

/* Human file size for a report: stored bytes, else the file on disk. */
$fileSize = function (array $r): string {
    $bytes = (int) ($r['size'] ?? 0);
    $file = (string) ($r['file'] ?? '');
    if ($bytes <= 0 && $file !== '' && !preg_match('#^https?://#i', $file)) {
        $abs = BM_PUBLIC . '/' . ltrim($file, '/');
        if (strpos($file, '..') === false && is_file($abs)) {
            $bytes = (int) filesize($abs);
        }
    }
    if ($bytes <= 0) {
        return '';
    }
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
};

/* Decorative hero art: a dated report sheet beside arched windows that light up one by one. */
$art = '<div class="ach-art" aria-hidden="true">'
    . '<svg class="ach-art__svg" viewBox="0 0 360 300" focusable="false">'
    . '<defs><linearGradient id="ach-lit" x1="0" y1="0" x2="0" y2="1">'
    . '<stop offset="0" stop-color="#FFF3D6"/><stop offset=".45" stop-color="#F8D48A"/><stop offset="1" stop-color="#EFA927"/>'
    . '</linearGradient></defs>'
    . '<circle cx="292" cy="62" r="46" fill="#FDEBC8"/><circle cx="292" cy="62" r="28" fill="#F8D48A" opacity=".55"/>'
    . '<path d="M196 54q7-7 14 0q7-7 14 0" fill="none" stroke="#9FCBE8" stroke-width="3" stroke-linecap="round"/>'
    . '<rect x="6" y="266" width="348" height="14" rx="7" fill="#DCEDF8"/>'
    // report sheet
    . '<g class="ach-art__sheet">'
    . '<rect x="30" y="52" width="156" height="206" rx="18" fill="#fff" stroke="#CFDCE6" stroke-width="2"/>'
    . '<path d="M30 70a18 18 0 0 1 18-18h120a18 18 0 0 1 18 18v28H30z" fill="#1984C0"/>'
    . '<path d="M52 92V78a7 7 0 0 1 14 0v14z" fill="#fff" opacity=".9"/>'
    . '<path d="M74 92V78a7 7 0 0 1 14 0v14z" fill="#EFA927"/>'
    . '<path d="M96 92V78a7 7 0 0 1 14 0v14z" fill="#fff" opacity=".9"/>'
    . '<rect x="52" y="120" width="92" height="8" rx="4" fill="#B9DBF0"/><circle cx="160" cy="124" r="8" fill="#DCEDF8"/>'
    . '<path d="M156.5 124l2.5 2.5 4.5-5" fill="none" stroke="#1572A6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
    . '<rect x="72" y="146" width="72" height="8" rx="4" fill="#DCEDF8"/><circle cx="160" cy="150" r="8" fill="#DCEDF8"/>'
    . '<path d="M156.5 150l2.5 2.5 4.5-5" fill="none" stroke="#1572A6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
    . '<rect x="60" y="172" width="84" height="8" rx="4" fill="#DCEDF8"/><circle cx="160" cy="176" r="8" fill="#DCEDF8"/>'
    . '<path d="M156.5 176l2.5 2.5 4.5-5" fill="none" stroke="#1572A6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
    . '<rect x="52" y="206" width="56" height="26" rx="8" fill="#FEF7E8" stroke="#F8D48A" stroke-width="1.5"/>'
    . '<rect x="60" y="216" width="40" height="6" rx="3" fill="#EFA927" opacity=".75"/>'
    . '</g>'
    // arched windows rising — each lit window is a documented step
    . '<path d="M204 266v-52a15 15 0 0 1 30 0v52z" fill="#B9DBF0"/>'
    . '<path d="M244 266v-86a15 15 0 0 1 30 0v86z" fill="#5BA8D6"/>'
    . '<path d="M284 266v-120a15 15 0 0 1 30 0v120z" fill="#1984C0"/>'
    . '<path class="ach-art__lit" d="M324 266v-154a15 15 0 0 1 30 0v154z" fill="url(#ach-lit)"/>'
    . '<path d="M324 266v-154a15 15 0 0 1 30 0v154" fill="none" stroke="#E09A17" stroke-width="2"/>'
    // verification seal
    . '<circle cx="186" cy="238" r="30" fill="#1572A6"/><circle cx="186" cy="238" r="23" fill="none" stroke="#fff" stroke-width="2" stroke-dasharray="3 4" opacity=".55"/>'
    . '<path d="M175 238l7 7 14-15" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
    . '</svg></div>';

/* In-page links (the page has three distinct parts). */
$jump = '<nav class="ach-jump" aria-label="' . te('achievements.toc_label') . '"><ul role="list">';
foreach ([
    ['#stats', 'badge-check', t('achievements.stats_title')],
    ['#projects', 'building-2', t('achievements.projects_title')],
    ['#reports', 'file-text', t('achievements.reports_title')],
] as [$href, $ico, $label]) {
    $jump .= '<li><a class="ach-jump__link" href="' . e($href) . '">' . icon($ico) . '<span>' . e((string) $label) . '</span></a></li>';
}
$jump .= '</ul></nav>';

$statsCount = count($stats);
$projectsMod = $counts['all'] === 1 ? ' ach-projects__list--single' : '';
?>
<?= page_hero([
    'crumbs' => [[t('nav.achievements'), 'achievements.html']],
    'eyebrow' => t('achievements.hero.eyebrow'),
    'title' => t('achievements.hero.title'),
    'text' => t('achievements.hero.text'),
    'actions' => $jump,
    'aside' => $art,
    'class' => 'ach-hero',
]) ?>

<section class="section ach-stats" id="stats" aria-labelledby="stats-title">
  <div class="container">
    <div class="ach-head">
      <h2 class="section-title" id="stats-title"><?= te('achievements.stats_title') ?></h2>
      <p class="ach-policy"><?= icon('shield-check') ?><span><?= te('achievements.stats_note') ?></span></p>
    </div>
    <?php if ($stats): ?>
    <div class="ach-stats__grid ach-stats__grid--n<?= (int) $statsCount ?>">
      <?= stat_grid($stats, ['show_meta' => true]) ?>
    </div>
    <?php else: ?>
    <?= notice((string) t('achievements.stats_empty'), 'pending', 'badge-check') ?>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tint ach-projects" id="projects" aria-labelledby="projects-title">
  <div class="container">
    <div class="ach-projects__head">
      <?= section_head([
          'title' => t('achievements.projects_title'),
          'id' => 'projects-title',
          'text' => t('achievements.projects_text'),
      ]) ?>
      <?php if ($showFilter): ?>
      <div class="ach-filter">
        <div class="filters" data-filter-group="status" data-filter-target="#projects-list" role="group" aria-label="<?= te('ui.status') ?>" hidden>
          <?php foreach (['all' => 'ui.all', 'ongoing' => 'ui.status_ongoing', 'done' => 'ui.status_done'] as $value => $labelKey): ?>
          <button class="filters__btn filters__btn--<?= e($value) ?>" type="button" data-filter="<?= e($value) ?>" aria-pressed="<?= $value === 'all' ? 'true' : 'false' ?>">
            <span><?= te($labelKey) ?></span><span class="filters__count"><?= (int) $counts[$value] ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <p class="sr-only" data-filter-status data-template="<?= te('achievements.filter_status') ?>" aria-live="polite"></p>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($projects): ?>
    <ul class="ach-projects__list<?= $projectsMod ?>" id="projects-list" role="list">
      <?php foreach ($projects as $p): ?>
      <li><?= project_card($p) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <?= empty_state('clipboard-list', '', (string) t('ui.projects_empty')) ?>
    <?php endif; ?>
  </div>
</section>

<section class="section ach-reports" id="reports" aria-labelledby="reports-title">
  <div class="container ach-reports__grid">
    <div class="ach-reports__head">
      <span class="ach-reports__icon"><?= icon('file-text') ?></span>
      <?= section_head([
          'title' => t('achievements.reports_title'),
          'id' => 'reports-title',
          'text' => t('achievements.reports_text'),
      ]) ?>
    </div>
    <div class="ach-reports__body">
      <?php if ($reports): ?>
      <ul class="ach-reports__list" role="list">
        <?php foreach ($reports as $i => $r): ?>
        <?php
            $rid = 'report-' . slugify((string) $r['id']) . '-' . $i;
            $size = $fileSize($r);
            $year = trim((string) $r['year']);
        ?>
        <li>
          <article class="ach-report" aria-labelledby="<?= e($rid) ?>">
            <span class="ach-report__file" aria-hidden="true"><?= icon('file-text') ?><span>PDF</span></span>
            <div class="ach-report__body">
              <h3 class="ach-report__title" id="<?= e($rid) ?>"><?= e((string) $r['title']) ?></h3>
              <?php if ($year !== '' || $size !== ''): ?>
              <p class="ach-report__meta">
                <?php if ($year !== ''): ?><span><?= icon('calendar') ?><bdi><?= e($year) ?></bdi></span><?php endif; ?>
                <?php if ($size !== ''): ?><span><?= icon('download') ?><bdi dir="ltr">PDF · <?= e($size) ?></bdi></span><?php endif; ?>
              </p>
              <?php endif; ?>
              <?php if (trim((string) $r['description']) !== ''): ?>
              <div class="ach-report__desc"><?= p_html((string) $r['description']) ?></div>
              <?php endif; ?>
            </div>
            <?= btn(t('ui.download_pdf'), upload_url((string) $r['file']), 'outline', [
                'icon' => 'download',
                'size' => 'sm',
                'class' => 'ach-report__btn',
                'attrs' => ['download' => true, 'aria-describedby' => $rid],
            ]) ?>
          </article>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="ach-reports__empty">
        <?= notice((string) t('achievements.reports_empty'), 'privacy', 'file-text') ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?= cta_share() ?>
