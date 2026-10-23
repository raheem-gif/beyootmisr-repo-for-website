<?php
/**
 * أنشطتنا ومبادراتنا — content document §05 (+ §01: «الأنشطة العشرة مع صفحة لكل نشاط،
 * وصفحة مستقلة لمبادرة «إحنا معاكي»»).
 *
 * Sections: hero with a «ten doors» quick index, the ten activities (rich cards),
 * «مبادراتنا الخاصة» («إحنا معاكي»), «شاركنا الخير».
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('activities.meta_title');
BM::$page['description'] = (string) t('activities.meta_description');

$activities = bm_activities();

/*
 * Hero aside — «عشرة أبواب للعطاء» drawn literally: a house façade with ten
 * arched, lit doors. Each door jumps to its activity card below. On phones the
 * same links become one swipeable row of chips.
 */
$doors = '<nav class="acts-doors" aria-label="' . te('activities.jump_label') . '">'
    . '<span class="acts-doors__roof" aria-hidden="true"></span>'
    . '<ul class="acts-doors__list" role="list">';
foreach (array_values($activities) as $i => $a) {
    $doors .= '<li><a class="acts-door" href="#act-' . e($a['slug']) . '" style="--i:' . $i . '">'
        . '<span class="acts-door__arch">' . icon($a['icon']) . '</span>'
        . '<span class="acts-door__name">' . e($a['name']) . '</span></a></li>';
}
$doors .= '</ul><span class="acts-doors__ground" aria-hidden="true"></span></nav>';

/* Rich activity card: door with its house number, name, subtitle, excerpt, tagline, link. */
$card = function (array $a): string {
    return '<article class="act-card" id="act-' . e($a['slug']) . '">'
        . '<span class="act-card__door" aria-hidden="true">' . icon($a['icon'])
        . '<span class="act-card__plate">' . e($a['num']) . '</span></span>'
        . '<div class="act-card__head">'
        . '<h3 class="act-card__title"><a class="stretched" href="' . e(u($a['url'])) . '">' . e($a['name']) . '</a></h3>'
        . '<p class="act-card__subtitle">' . e($a['subtitle']) . '</p>'
        . '</div>'
        . '<div class="act-card__body">'
        . '<p class="act-card__excerpt">' . e(excerpt((string) $a['description'], 190)) . '</p>'
        . tagline((string) $a['tagline'], 'act-card__tagline')
        . '<span class="act-card__more" aria-hidden="true">' . te('ui.read_more') . icon('arrow-left') . '</span>'
        . '</div>'
        . '</article>';
};
?>
<?= page_hero([
    'crumbs' => [[t('nav.activities'), 'activities.html']],
    'eyebrow' => t('activities.hero.eyebrow'),
    'title' => t('activities.hero.title'),
    'text' => t('activities.hero.text'),
    'aside' => $doors,
    'class' => 'acts-hero',
]) ?>

<section class="section section--tint acts-list" aria-labelledby="list-title">
  <div class="container">
    <?= section_head(['title' => t('activities.list_title'), 'id' => 'list-title']) ?>
    <ul class="acts-grid" role="list">
      <?php foreach ($activities as $a): ?>
      <li><?= $card($a) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section acts-initiative" aria-labelledby="initiatives-title">
  <div class="container acts-initiative__grid">
    <div class="acts-initiative__head">
      <h2 class="section-title" id="initiatives-title"><?= te('activities.initiatives_title') ?></h2>
      <?= tagline(t('ehna_maaki.tagline'), 'acts-initiative__tagline') ?>
    </div>
    <div class="acts-initiative__card"><?= initiative_card() ?></div>
    <?php $topics = tl('ehna_maaki.topics'); if ($topics): ?>
    <div class="acts-initiative__topics">
      <p class="acts-initiative__label"><?= te('ehna_maaki.topics_title') ?></p>
      <?= chips($topics) ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?= cta_share() ?>
