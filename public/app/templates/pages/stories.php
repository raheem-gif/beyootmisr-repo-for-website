<?php
/**
 * حكايات من بيوت مصر — content document §07.
 *
 * Sections: hero (with the privacy pledge), section intro, published stories
 * (type filters + grid) or an honest empty state that shows the five story
 * types, then «شاركنا الخير».
 *
 * The editorial «guide» text of each story type is for the admin panel only and
 * is never shown here.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('stories.meta_title');
BM::$page['description'] = (string) t('stories.meta_description');

$stories = bm_stories(true);
$types = bm_story_types();

/* Story types that have at least one published story, in the order of §07. */
$present = [];
foreach ($stories as $s) {
    $present[$s['type']] = true;
}
$filterTypes = array_values(array_filter(array_keys($types), function ($type) use ($present) {
    return isset($present[$type]);
}));

/* Privacy pledge (hero aside): «تُروى الحكايات دون كشف هوية أصحابها» + the charter line. */
$pledge = '<div class="stories-pledge">'
    . '<span class="stories-pledge__icon">' . icon('eye-off') . '</span>'
    . '<p class="stories-pledge__badge">' . te('stories.privacy_badge') . '</p>'
    . '<p class="stories-pledge__quote">' . icon('quote') . '<span>' . te('home.charter.quote') . '</span></p>'
    . '<a class="stories-pledge__link" href="' . e(u('charter.html')) . '">' . te('buttons.read_charter') . icon('arrow-left') . '</a>'
    . '</div>';
?>
<?= page_hero([
    'crumbs' => [[t('nav.stories'), 'stories.html']],
    'eyebrow' => t('stories.hero.eyebrow'),
    'title' => t('stories.hero.title'),
    'text' => t('stories.hero.text'),
    'aside' => $pledge,
    'class' => 'stories-hero',
]) ?>

<div class="section section--tight stories-intro-wrap">
  <div class="container">
    <figure class="stories-intro">
      <div class="stories-intro__art" aria-hidden="true"><?= hero_windows_svg('stories-intro__windows') ?></div>
      <blockquote class="stories-intro__quote">
        <span class="stories-intro__mark" aria-hidden="true"><?= icon('quote') ?></span>
        <?= p_html(t('stories.intro.text')) ?>
      </blockquote>
    </figure>
  </div>
</div>

<section class="section section--tint stories-list" id="stories" aria-labelledby="stories-list-title">
  <div class="container">
    <?= section_head([
        'eyebrow' => t('home.stories.eyebrow'),
        'title' => t('stories.list_title'),
        'id' => 'stories-list-title',
    ]) ?>

    <?php if ($stories): ?>
    <?php if (count($filterTypes) > 1): ?>
    <div class="stories-toolbar">
      <div class="filters" data-filter-group="type" data-filter-target="#stories-list" role="group" aria-label="<?= te('stories.filter.label') ?>" hidden>
        <button type="button" class="filter" data-filter="all" aria-pressed="true"><?= te('ui.all') ?></button>
        <?php foreach ($filterTypes as $type): ?>
        <button type="button" class="filter" data-filter="<?= e($type) ?>" aria-pressed="false"><?= icon($types[$type]['icon']) ?><span><?= e(story_type_name($type)) ?></span></button>
        <?php endforeach; ?>
      </div>
      <p class="stories-toolbar__status" data-filter-status data-template="<?= e((string) t('stories.filter.status')) ?>" aria-live="polite"><?= te('stories.filter.status', ['n' => count($stories)]) ?></p>
    </div>
    <?php endif; ?>
    <ul id="stories-list" class="stories-grid" role="list">
      <?php foreach ($stories as $s): ?>
      <li><?= story_card($s) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="stories-empty">
      <div class="stories-empty__head">
        <span class="stories-empty__icon"><?= icon('book-open') ?></span>
        <h3 class="stories-empty__title"><?= te('stories.empty_title') ?></h3>
        <p class="stories-empty__text"><?= te('stories.empty_text') ?></p>
      </div>
      <p class="stories-empty__label" id="story-types-label"><?= te('stories.types_title') ?></p>
      <ul class="story-types" role="list" aria-labelledby="story-types-label">
        <?php foreach ($types as $type => $info): ?>
        <li class="story-type">
          <span class="story-type__arch"><?= icon($info['icon']) ?></span>
          <span class="story-type__name"><?= e(story_type_name($type)) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>

<?= cta_share() ?>
