<?php
/**
 * صفحة المشروع — content document §08: «اسم المشروع، النشاط التابع له، الموقع العام،
 * الهدف، الحالة الحالية (جارٍ / منجز)، صور التنفيذ، وآخر تحديث؛ وتظهر تكلفة المشروع
 * والتبرعات فقط حين تكون بياناتها معتمدة ومناسبة للنشر», and §12 «كل مشروع له حالة
 * وتاريخ آخر تحديث وصور متدرجة».
 *
 * Rendered once per published project (BM::$page['project'] holds the id), so this
 * file must not declare named functions — closures only.
 */
defined('BM_APP') || exit;

$id = (string) (BM::$page['project'] ?? '');
$all = bm_projects(true);
$p = null;
foreach ($all as $candidate) {
    if ($candidate['id'] === $id) {
        $p = $candidate;
        break;
    }
}
if ($p === null) {
    throw new RuntimeException('Unknown project: ' . $id);
}

$a = bm_activity((string) $p['activity']);
$summary = trim((string) $p['summary']);
$description = trim((string) $p['description']);
$goal = trim((string) $p['goal']);
$location = trim((string) $p['location']);
$updated = trim((string) $p['updated_at']);
$isDone = $p['status'] === 'done';
$cover = bm_project_cover($p);

BM::$page['title'] = (string) $p['title'];
BM::$page['description'] = excerpt($summary !== '' ? $summary : $description);
if ($cover) {
    BM::$page['image'] = (string) $cover['src'];
}

$settings = bm_settings();
$canDonate = !empty($settings['donations']['enabled']) && !empty($settings['donations']['methods']);
$allocationNote = trim((string) ($settings['donations']['allocation_note'] ?? ''));
$hotline = trim((string) $settings['contact']['hotline']);
$telLink = $hotline !== '' ? tel_href($hotline) : '';
$waLink = wa_general((string) t('project.wa_message', ['project' => $p['title']]));

/* Cost & donations: only when approved for publication (show_financials) and filled in. */
$money = [];
if (!empty($p['show_financials'])) {
    foreach (['cost' => 'ui.cost', 'donations' => 'ui.donations'] as $field => $labelKey) {
        $value = trim((string) $p[$field]);
        if ($value !== '') {
            $money[] = ['field' => $field, 'label' => t($labelKey), 'value' => $value];
        }
    }
}
$moneyValue = function (string $value): string {
    $plain = str_replace([',', '٬', ' '], '', $value);
    if (preg_match('/^\d+(\.\d+)?$/', $plain)) {
        return '<bdi class="num">' . e(fmt_number($plain)) . '</bdi> <span class="proj-money__unit">' . te('project.currency') . '</span>';
    }
    return e($value);
};

/* Stage photos exist even when the main gallery is still empty. */
$stagePhotos = 0;
foreach ($p['updates'] as $u) {
    $stagePhotos += count($u['images']);
}
$showGallery = $p['images'] || $stagePhotos === 0;

/* Every photo gets a text alternative, even when the editor left «alt» empty. */
$withAlt = function (array $img, string $fallback): array {
    if (trim((string) $img['alt']) === '') {
        $img['alt'] = trim((string) $img['caption']) !== '' ? (string) $img['caption'] : $fallback;
    }
    return $img;
};
$galleryImages = [];
foreach ($p['images'] as $img) {
    $galleryImages[] = $withAlt($img, (string) $p['title']);
}

$dateTag = function (string $iso): string {
    return '<time datetime="' . e($iso) . '">' . e(ar_date($iso)) . '</time>';
};

/* ---------- Hero ---------- */
$meta = '<ul class="proj-meta" role="list"><li>' . status_badge($p['status']) . '</li>';
if ($location !== '') {
    $meta .= '<li class="proj-meta__item">' . icon('map-pin') . '<span>' . e($location) . '</span></li>';
}
if ($updated !== '') {
    $meta .= '<li class="proj-meta__item">' . icon('refresh-cw') . '<span>' . te('ui.last_update') . ': ' . $dateTag($updated) . '</span></li>';
}
$meta .= '</ul>';

/* The two project buttons of §11. Without official donation methods, «ساهم» leads to the contact options below. */
$heroActions = '';
if (!$isDone) {
    $heroActions .= btn(t('buttons.contribute_project'), $canDonate ? 'get-involved.html#donate' : '#contribute', 'donate', ['icon' => 'hand-heart', 'size' => 'lg']);
}
$heroActions .= btn(t('buttons.see_stages'), '#stages', $isDone ? 'primary' : 'outline', ['icon' => 'list-checks', 'size' => 'lg']);

if ($cover) {
    $cover = $withAlt($cover, (string) $p['title']);
    $aside = '<figure class="proj-cover">'
        . '<span class="proj-cover__frame">' . img_tag($cover, ['class' => 'proj-cover__img', 'loading' => 'eager', 'sizes' => '(min-width: 960px) 420px, 100vw']) . '</span>'
        . '</figure>';
} else {
    $aside = '<div class="proj-cover proj-cover--empty" aria-hidden="true">'
        . '<span class="proj-cover__frame">' . hero_windows_svg('proj-cover__art')
        . '<span class="proj-cover__icon">' . icon($a ? $a['icon'] : 'building-2') . '</span></span>'
        . '</div>';
}

$hero = page_hero([
    'crumbs' => [[t('nav.achievements'), 'achievements.html'], [$p['title'], $p['url']]],
    'eyebrow' => $a ? $a['name'] : '',
    'title' => $p['title'],
    'actions' => $heroActions,
    'aside' => $aside,
    'class' => 'proj-hero' . ($cover ? ' proj-hero--photo' : ''),
]);
/* The eyebrow names the activity and links to its page; the meta row follows the title. */
if ($a) {
    $plainEyebrow = eyebrow($a['name']);
    $linkedEyebrow = '<p class="eyebrow proj-hero__activity"><a href="' . e(u($a['url'])) . '">' . icon($a['icon']) . '<span>' . e($a['name']) . '</span></a></p>';
    $hero = str_replace($plainEyebrow, $linkedEyebrow, $hero);
}
$h1End = strpos($hero, '</h1>');
if ($h1End !== false) {
    $hero = substr_replace($hero, $meta, $h1End + 5, 0);
}

/* ---------- Facts ---------- */
$facts = [];
if ($a) {
    $facts[] = ['icon' => $a['icon'], 'label' => t('ui.activity'), 'html' => '<a href="' . e(u($a['url'])) . '">' . e($a['name']) . '</a>'];
}
if ($location !== '') {
    $facts[] = ['icon' => 'map-pin', 'label' => t('ui.location'), 'html' => e($location)];
}
$facts[] = ['icon' => $isDone ? 'circle-check' : 'refresh-cw', 'label' => t('ui.status'), 'html' => status_badge($p['status'])];
if ($updated !== '') {
    $facts[] = ['icon' => 'calendar', 'label' => t('ui.last_update'), 'html' => $dateTag($updated)];
}

/* ---------- Stages timeline ---------- */
$stages = '';
if ($p['updates']) {
    $stages = '<ol class="proj-stages' . (count($p['updates']) === 1 ? ' proj-stages--single' : '') . '" role="list">';
    foreach ($p['updates'] as $i => $u) {
        $sid = 'stage-' . ($i + 1);
        $title = trim((string) $u['title']);
        $date = trim((string) $u['date']);
        $latestChip = $i === 0 && count($p['updates']) > 1;
        $stages .= '<li class="proj-stage' . ($i === 0 ? ' proj-stage--latest' : '') . '">'
            . '<span class="proj-stage__marker" aria-hidden="true"></span>'
            . '<p class="proj-stage__when">'
            . ($date !== '' ? icon('calendar') . $dateTag($date) : '')
            . ($latestChip ? '<span class="proj-stage__latest">' . te('ui.last_update') . '</span>' : '')
            . '</p>'
            . '<article class="proj-stage__card"' . ($title !== '' ? ' aria-labelledby="' . e($sid) . '-title"' : '') . '>';
        if ($title !== '') {
            $stages .= '<h3 class="proj-stage__title" id="' . e($sid) . '-title">' . e($title) . '</h3>';
        }
        if (trim((string) $u['text']) !== '') {
            $stages .= '<div class="proj-stage__text">' . p_html((string) $u['text']) . '</div>';
        }
        if ($u['images']) {
            $stages .= '<ul class="proj-stage__photos" role="list" data-lightbox="' . e($sid) . '">';
            foreach ($u['images'] as $img) {
                $img = $withAlt($img, $title !== '' ? $title : (string) $p['title']);
                $stages .= '<li><a class="proj-stage__photo" href="' . e(upload_url($img['src'])) . '" data-caption="' . e($img['caption'] !== '' ? $img['caption'] : $img['alt']) . '">'
                    . img_tag($img, ['sizes' => '(min-width: 1024px) 220px, 33vw']) . '</a></li>';
            }
            $stages .= '</ul>';
        }
        $stages .= '</article></li>';
    }
    $stages .= '</ol>';
}
$docsCompact = $showGallery && !$p['images'] && !$p['updates'];

/* ---------- Contribute band: an ongoing project asks for itself; a completed one points to its activity ---------- */
if (!$isDone) {
    $band = ['eyebrow' => (string) $p['title'], 'title' => t('buttons.contribute_project'), 'text' => t($canDonate ? 'project.contribute_text' : 'project.contribute_pending'), 'link' => null];
} elseif ($a) {
    $band = ['eyebrow' => $a['name'], 'title' => t('buttons.contribute_activity'), 'text' => t($canDonate ? 'activities.contribute_text' : 'activities.contribute_pending'), 'link' => [$a['name'], $a['url']]];
} else {
    $band = ['eyebrow' => (string) $p['title'], 'title' => t('buttons.support_activity'), 'text' => t('get_involved.projects.text'), 'link' => [t('nav.activities_all'), 'activities.html']];
}

/* ---------- Other published projects ---------- */
$others = array_slice(array_values(array_filter($all, function ($x) use ($p) {
    return $x['id'] !== $p['id'];
})), 0, 3);
?>
<?= $hero ?>

<div class="section proj-main">
  <div class="container proj-main__grid">
    <div class="proj-main__content">
      <section class="proj-overview" id="overview" aria-labelledby="overview-title">
        <h2 class="section-title" id="overview-title"><?= te('project.overview') ?></h2>
        <?php if ($summary !== ''): ?>
        <p class="proj-lead"><?= nl2br(e($summary), false) ?></p>
        <?php endif; ?>
        <?php if ($description !== ''): ?>
        <div class="proj-text"><?= p_html($description) ?></div>
        <?php endif; ?>
        <?php if ($goal !== ''): ?>
        <div class="proj-goal">
          <span class="proj-goal__icon"><?= icon('heart-handshake') ?></span>
          <div>
            <h3 class="proj-goal__title"><?= te('ui.goal') ?></h3>
            <?= p_html($goal, 'proj-goal__text') ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!$isDone): ?>
        <?= notice((string) t('project.completion_note'), 'pending', 'badge-info') ?>
        <?php endif; ?>
      </section>

      <?php if ($money): ?>
      <section class="proj-money" id="financials" aria-labelledby="financials-title">
        <h2 class="proj-money__title" id="financials-title"><span class="proj-money__icon"><?= icon('hand-coins') ?></span><?= te('project.financials_title') ?></h2>
        <dl class="proj-money__list proj-money__list--n<?= count($money) ?>">
          <?php foreach ($money as $m): ?>
          <div class="proj-money__item proj-money__item--<?= e($m['field']) ?>">
            <dt><?= e((string) $m['label']) ?></dt>
            <dd><?= $moneyValue($m['value']) ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
      </section>
      <?php endif; ?>
    </div>

    <aside class="proj-facts" aria-labelledby="facts-title">
      <h2 class="proj-facts__title" id="facts-title"><?= te('ui.details') ?></h2>
      <dl class="proj-facts__list">
        <?php foreach ($facts as $f): ?>
        <div class="proj-fact">
          <dt><span class="proj-fact__icon"><?= icon($f['icon']) ?></span><?= e((string) $f['label']) ?></dt>
          <dd><?= $f['html'] ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
    </aside>
  </div>
</div>

<div class="section section--tint proj-docs">
  <div class="container proj-docs__inner<?= $docsCompact ? ' proj-docs__inner--compact' : '' ?>">
    <?php if ($showGallery): ?>
    <section class="proj-doc proj-doc--gallery" id="gallery" aria-labelledby="gallery-title">
      <h2 class="proj-doc__title" id="gallery-title"><?= te('ui.gallery_title') ?></h2>
      <?= gallery($galleryImages, ['group' => 'project']) ?>
    </section>
    <?php endif; ?>
    <section class="proj-doc proj-doc--stages" id="stages" aria-labelledby="stages-title">
      <h2 class="proj-doc__title" id="stages-title"><?= te('ui.stages_title') ?></h2>
      <?= $stages !== '' ? $stages : empty_state('list-checks', '', (string) t('ui.stages_empty')) ?>
    </section>
  </div>
</div>

<section class="section proj-contribute-wrap" id="contribute" aria-labelledby="contribute-title">
  <div class="container">
    <div class="proj-contribute<?= $canDonate ? ' proj-contribute--open' : '' ?>">
      <span class="proj-contribute__icon"><?= icon($canDonate ? 'hand-heart' : 'heart-handshake') ?></span>
      <div class="proj-contribute__body">
        <?= eyebrow($band['eyebrow'], 'eyebrow--light') ?>
        <h2 class="proj-contribute__title" id="contribute-title"><?= e((string) $band['title']) ?></h2>
        <?= p_html((string) $band['text'], 'proj-contribute__text') ?>
        <?php if ($canDonate && $allocationNote !== ''): ?>
        <p class="proj-contribute__note"><?= icon('badge-info') ?><span><?= e($allocationNote) ?></span></p>
        <?php endif; ?>
      </div>
      <div class="actions proj-contribute__actions">
        <?php if ($canDonate): ?>
        <?= btn((string) $band['title'], 'get-involved.html#donate', 'donate', ['icon' => 'hand-heart', 'size' => 'lg']) ?>
        <?php if ($waLink !== ''): ?>
        <?= btn(t('buttons.whatsapp'), $waLink, 'outline-light', ['icon' => 'whatsapp']) ?>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($waLink !== ''): ?>
        <?= btn(t('buttons.whatsapp'), $waLink, 'whatsapp', ['icon' => 'whatsapp', 'size' => 'lg']) ?>
        <?php endif; ?>
        <?php if ($telLink !== ''): ?>
        <a class="btn btn--outline-light btn--lg proj-call" href="<?= e($telLink) ?>"><?= icon('phone') ?><span><?= te('buttons.call_hotline') ?></span><?= phone_html($hotline) ?></a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($band['link']): ?>
        <?= btn((string) $band['link'][0], (string) $band['link'][1], 'outline-light', ['icon_end' => 'arrow-left']) ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$others): ?>
    <p class="proj-back"><a href="<?= e(u('achievements.html#projects')) ?>"><?= icon('arrow-right') ?><span><?= te('project.back') ?></span></a></p>
    <?php endif; ?>
  </div>
</section>

<?php if ($others): ?>
<section class="section section--tint proj-others" aria-labelledby="others-title">
  <div class="container">
    <div class="proj-others__head">
      <h2 class="section-title" id="others-title"><?= te('project.others_title') ?></h2>
      <a class="proj-others__all" href="<?= e(u('achievements.html#projects')) ?>"><span><?= te('project.back') ?></span><?= icon('arrow-left') ?></a>
    </div>
    <ul class="proj-others__list proj-others__list--n<?= count($others) ?>" role="list">
      <?php foreach ($others as $o): ?>
      <li><?= project_card($o) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
