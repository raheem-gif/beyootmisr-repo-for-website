<?php
/**
 * صفحة الحكاية — one page per published story (content document §07).
 * BM::$page['story'] holds the story id.
 *
 * Sections: hero (type, title, summary, date, related activity, cover photo in
 * an arched window), the story text, photos, privacy notice, related activity,
 * other stories, «شاركنا الخير».
 *
 * Rendered once per story, so this file must not declare named functions.
 */
defined('BM_APP') || exit;

$storyId = (string) (BM::$page['story'] ?? '');
$all = bm_stories(true);
$story = null;
foreach ($all as $s) {
    if ($s['id'] === $storyId) {
        $story = $s;
        break;
    }
}
if ($story === null) {
    throw new RuntimeException('Unknown story: ' . $storyId);
}

$types = bm_story_types();
$type = (string) $story['type'];
$typeIcon = $types[$type]['icon'] ?? 'heart';
$typeName = story_type_name($type);
$title = trim((string) $story['title']);
$summary = trim((string) $story['summary']);
$body = $story['body'];
$date = trim((string) $story['date']);
$activity = trim((string) $story['activity']) !== '' ? bm_activity((string) $story['activity']) : [];
$images = $story['images'];
$cover = $images ? $images[0] : null;
/* A single photo is shown as the hero cover (with lightbox); two or more also get a gallery. */
$hasBody = paragraphs($body) !== [];
$hasGallery = count($images) > 1;
$hasMain = $hasBody || $hasGallery;

BM::$page['title'] = $title;
BM::$page['description'] = excerpt($summary !== '' ? $summary : implode(' ', paragraphs($body)));
if ($cover) {
    BM::$page['image'] = (string) $cover['src'];
}

/* Hero meta line: date + related activity. */
$meta = [];
if ($date !== '') {
    $isIso = (bool) preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $date);
    $meta[] = '<li>' . icon('calendar') . '<time' . ($isIso ? ' datetime="' . e($date) . '"' : '') . '>' . e(ar_date($date)) . '</time></li>';
}
if ($activity) {
    $meta[] = '<li>' . icon((string) $activity['icon']) . '<span>' . te('ui.activity') . ': '
        . '<a href="' . e(u((string) $activity['url'])) . '">' . e((string) $activity['name']) . '</a></span></li>';
}
$metaHtml = $meta ? '<ul class="story-meta" role="list">' . implode('', $meta) . '</ul>' : '';

/* Hero visual: the lit arched window — the approved cover photo, or the story type icon. */
if ($cover) {
    $frame = '<a class="story-window__link" href="' . e(upload_url((string) $cover['src'])) . '" data-caption="' . e($cover['caption'] !== '' ? $cover['caption'] : $cover['alt']) . '">'
        . img_tag($cover, ['class' => 'story-window__img', 'loading' => 'eager', 'sizes' => '(min-width: 960px) 440px, 92vw'])
        . '</a>';
} else {
    $frame = '<span class="story-window__glow">' . icon($typeIcon) . '</span>';
}
$window = '<div class="story-window' . ($cover ? ' story-window--photo' : '') . '"' . ($cover ? ' data-lightbox="story-cover"' : '') . '>'
    . '<div class="story-window__frame">' . $frame . '</div>'
    . '<span class="badge badge--type">' . icon($typeIcon) . e($typeName) . '</span>'
    . '</div>';

/* Other stories: same type first, then the newest. */
$others = array_values(array_filter($all, function ($s) use ($storyId) {
    return $s['id'] !== $storyId;
}));
usort($others, function ($a, $b) use ($type) {
    $sa = $a['type'] === $type ? 0 : 1;
    $sb = $b['type'] === $type ? 0 : 1;
    if ($sa !== $sb) {
        return $sa - $sb;
    }
    return strcmp((string) $b['date'], (string) $a['date']);
});
$others = array_slice($others, 0, 3);
?>
<?= page_hero([
    'crumbs' => [[t('nav.stories'), 'stories.html'], [$title, $story['url']]],
    'eyebrow' => $typeName,
    'title' => $title,
    'text' => $summary,
    'actions' => $metaHtml,
    'aside' => $window,
    'class' => 'story-hero' . ($cover ? ' story-hero--photo' : ''),
]) ?>

<div class="section story-body">
  <div class="container story-layout<?= $hasMain ? '' : ' story-layout--compact' ?>">
    <?php if ($hasMain): ?>
    <article class="story-main" aria-labelledby="story-body-title">
      <h2 class="sr-only" id="story-body-title"><?= te('stories.story_page.body_title') ?></h2>
      <?php if ($hasBody): ?>
      <div class="prose story-prose"><?= p_html($body) ?></div>
      <?php endif; ?>

      <?php if ($hasGallery): ?>
      <section class="story-photos" aria-labelledby="story-photos-title">
        <h2 class="story-photos__title" id="story-photos-title"><?= icon('camera') ?><span><?= te('ui.gallery_title') ?></span></h2>
        <?= gallery($images, ['group' => 'story']) ?>
      </section>
      <?php endif; ?>
    </article>
    <?php endif; ?>

    <aside class="story-side">
      <div class="story-privacy">
        <?= notice((string) t('stories.privacy_badge'), 'privacy', 'eye-off') ?>
        <a class="story-privacy__link" href="<?= e(u('charter.html')) ?>"><?= te('buttons.read_charter') ?><?= icon('arrow-left') ?></a>
      </div>

      <?php if ($activity): ?>
      <section class="story-activity" aria-labelledby="story-activity-title">
        <h2 class="story-side__title" id="story-activity-title"><?= te('stories.story_page.activity_title') ?></h2>
        <?= activity_card($activity) ?>
      </section>
      <?php endif; ?>

      <div class="story-back">
        <?= btn(t('stories.story_page.back'), 'stories.html', 'outline', ['icon' => 'arrow-right']) ?>
      </div>
    </aside>
  </div>
</div>

<?php if ($others): ?>
<section class="section section--tint story-more" aria-labelledby="story-more-title">
  <div class="container">
    <?= section_head([
        'eyebrow' => t('nav.stories'),
        'title' => t('stories.story_page.more_title'),
        'id' => 'story-more-title',
    ]) ?>
    <ul class="stories-grid" role="list">
      <?php foreach ($others as $s): ?>
      <li><?= story_card($s) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?= cta_share() ?>
