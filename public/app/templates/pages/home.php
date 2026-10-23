<?php
/**
 * الصفحة الرئيسية — content document §02.
 * Sections: hero, short intro, «كل بيت.. له حكاية», activities, impact numbers,
 * stories, charter band, «شاركنا الخير».
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('home.meta_title');
BM::$page['description'] = (string) t('site.meta_description');

$activities = bm_activities();
$stories = array_slice(bm_stories(), 0, 3);
$homeIcons = ['heart-pulse', 'sprout', 'gem', 'house'];
?>
<section class="home-hero" aria-labelledby="hero-title">
  <div class="container home-hero__inner">
    <div class="home-hero__content">
      <?= eyebrow(t('home.hero.eyebrow')) ?>
      <h1 class="home-hero__title" id="hero-title"><?= te('home.hero.title') ?></h1>
      <p class="home-hero__text"><?= te('home.hero.text') ?></p>
      <div class="actions">
        <?= btn(t('buttons.know_us'), 'about.html', 'primary', ['size' => 'lg']) ?>
        <?= btn(t('buttons.see_activities'), 'activities.html', 'outline', ['size' => 'lg']) ?>
        <?= btn(t('buttons.share_good'), 'get-involved.html', 'donate', ['size' => 'lg', 'icon' => 'heart']) ?>
      </div>
    </div>
    <div class="home-hero__art">
      <?= skyline_svg() ?>
    </div>
  </div>
</section>

<section class="section section--tight" aria-labelledby="intro-title">
  <div class="container">
    <div class="intro-card">
      <div>
        <?= eyebrow(t('home.intro.eyebrow')) ?>
        <h2 class="section-title" id="intro-title"><?= te('home.intro.title') ?></h2>
        <div class="intro-card__text"><?= p_html(t('home.intro.text')) ?></div>
        <div class="actions" style="margin-block-start:1.5rem">
          <?= btn(t('buttons.know_our_story'), 'about.html', 'primary', ['icon_end' => 'arrow-left']) ?>
          <?= btn(t('buttons.read_charter'), 'charter.html', 'ghost') ?>
        </div>
      </div>
      <ul class="facts" role="list">
        <?php foreach (tl('home.intro.facts') as $i => $fact): ?>
        <li class="fact"><?= icon(['house', 'calendar', 'heart-handshake'][$i % 3]) ?><span><?= e($fact) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="homes-title">
  <div class="container">
    <?= section_head(['title' => t('home.every_home.title'), 'id' => 'homes-title', 'align' => 'center']) ?>
    <ul class="homes" role="list">
      <?php foreach (tl('home.every_home.lines') as $i => $line): ?>
      <li class="home-tile"><span class="home-tile__icon"><?= icon($homeIcons[$i % 4]) ?></span><p><?= e($line) ?></p></li>
      <?php endforeach; ?>
    </ul>
    <div class="homes-closing"><?= tagline(t('home.every_home.closing')) ?></div>
  </div>
</section>

<section class="section section--tint home-activities" aria-labelledby="activities-title">
  <div class="container">
    <?= section_head([
        'eyebrow' => t('home.activities.eyebrow'),
        'title' => t('home.activities.title'),
        'id' => 'activities-title',
        'text' => t('home.activities.text'),
        'align' => 'center',
    ]) ?>
    <ul class="activities-grid" role="list">
      <?php foreach ($activities as $a): ?>
      <li><?= activity_card($a) ?></li>
      <?php endforeach; ?>
    </ul>
    <?= initiative_card() ?>
    <div class="home-activities__more"><?= btn(t('buttons.discover_activities'), 'activities.html', 'outline', ['icon_end' => 'arrow-left']) ?></div>
  </div>
</section>

<section class="section" aria-labelledby="impact-title">
  <div class="container">
    <?= section_head([
        'eyebrow' => t('home.impact.eyebrow'),
        'title' => t('home.impact.title'),
        'id' => 'impact-title',
        'text' => t('home.impact.text'),
    ]) ?>
    <?= stat_grid(bm_stats_for('home'), ['show_meta' => true]) ?>
    <div class="actions" style="margin-block-start:1.75rem">
      <?= btn(t('buttons.see_impact'), 'achievements.html', 'primary', ['icon_end' => 'arrow-left']) ?>
    </div>
  </div>
</section>

<section class="section section--blue" aria-labelledby="stories-title">
  <div class="container">
    <div class="stories-teaser">
      <div>
        <?= section_head([
            'eyebrow' => t('home.stories.eyebrow'),
            'title' => t('home.stories.title'),
            'id' => 'stories-title',
            'text' => t('home.stories.text'),
            'actions' => btn(t('buttons.stories'), 'stories.html', 'primary', ['icon_end' => 'arrow-left']),
        ]) ?>
      </div>
      <?php if ($stories): ?>
      <ul class="grid grid--2" role="list">
        <?php foreach (array_slice($stories, 0, 2) as $s): ?>
        <li><?= story_card($s) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div>
        <ul class="stories-teaser__art" role="list">
          <?php foreach (bm_story_types() as $type => $info): ?>
          <li class="story-kind"><?= icon($info['icon']) ?><span><?= e(story_type_name($type)) ?></span></li>
          <?php endforeach; ?>
          <li class="story-kind"><?= icon('eye-off') ?><span><?= te('stories.privacy_badge') ?></span></li>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?= charter_band() ?>

<?= cta_share() ?>
