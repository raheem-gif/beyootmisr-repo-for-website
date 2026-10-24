<?php
/**
 * سياسة الخصوصية — content document §12 («الخصوصية») and §13 (to be reviewed
 * with the foundation's legal adviser before launch).
 * Sections: hero (with the "last updated" date), a table of contents (sticky
 * on desktop), the numbered policy sections from texts.json, a short
 * contact row, and the charter band («اقرأ ميثاقنا»).
 * Closures only — no named functions in templates.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('privacy.meta_title');
BM::$page['description'] = (string) t('privacy.meta_description');

$contact = bm_settings()['contact'];
/* Numbers count only when they can be dialled, so no link gets an empty href. */
$hotline = tel_href((string) $contact['hotline']) !== '' ? trim((string) $contact['hotline']) : '';

$sections = [];
foreach (tl('privacy.sections') as $s) {
    if (is_array($s) && trim((string) ($s['title'] ?? '')) !== '') {
        $sections[] = ['title' => bm_fill(trim((string) $s['title'])), 'text' => (string) ($s['text'] ?? '')];
    }
}

/* "Last updated" line; datetime only for an ISO year-month(-day). */
$updated = trim((string) t('privacy.updated'));
$updatedHtml = '';
if ($updated !== '') {
    $dt = preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $updated) ? ' datetime="' . e($updated) . '"' : '';
    $updatedHtml = '<p class="pv-updated">' . icon('calendar') . '<span>' . te('privacy.updated_label') . ': '
        . '<time' . $dt . '>' . e(ar_date($updated)) . '</time></span></p>';
}

/* Official numbers mentioned in the text become tap-to-call / WhatsApp links. */
$numberLinks = [];
if ($hotline !== '') {
    $numberLinks[e($hotline)] = '<a class="pv-num" href="' . e(tel_href($hotline)) . '">' . phone_html($hotline) . '</a>';
}
$legal = trim((string) $contact['whatsapp_legal']);
$waLegal = wa_legal();
if ($legal !== '' && $waLegal !== '' && !isset($numberLinks[e($legal)])) {
    $numberLinks[e($legal)] = '<a class="pv-num" href="' . e($waLegal) . '" target="_blank" rel="noopener">' . phone_html($legal) . '</a>';
}
/* Strings inside lists are not filled by t(): fill {hotline} etc. here. */
$body = function (string $text) use ($numberLinks): string {
    $html = p_html(bm_fill($text));
    return $numberLinks ? strtr($html, $numberLinks) : $html;
};

$num = function (int $i): string {
    return sprintf('%02d', $i + 1);
};

/* Hero art: a lit arched window with its curtains drawn aside — the light is
   seen, the people are not («نحكي أثر الخير.. لا نكشف أصحاب الحاجة»). */
$curtain = '<path d="M50 40h78c-6 70-28 120-38 166 12 30 26 62 30 94H50z" fill="#DCEDF8"/>'
    . '<path d="M74 52c0 60-4 108-4 152M98 52c-2 60-12 108-18 152M72 214c6 30 16 58 20 84M84 214c10 30 20 58 24 84" fill="none" stroke="#B9DBF0" stroke-width="3" stroke-linecap="round"/>';
$heroArt = '<div class="pv-hero-art" aria-hidden="true"><svg viewBox="0 0 300 330" focusable="false">'
    . '<defs><linearGradient id="pv-light" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFE9B8"/><stop offset="1" stop-color="#EFA927"/></linearGradient>'
    . '<clipPath id="pv-glass"><path d="M62 296V150a88 88 0 0 1 176 0v146z"/></clipPath></defs>'
    . '<circle cx="150" cy="166" r="140" fill="#FDEBC8" opacity=".5"/>'
    . '<path d="M44 304V150a106 106 0 0 1 212 0v154z" fill="#1984C0"/>'
    . '<path d="M54 300V150a96 96 0 0 1 192 0v150" fill="none" stroke="#fff" stroke-opacity=".2" stroke-width="2"/>'
    . '<path class="pv-hero-art__glass" d="M62 296V150a88 88 0 0 1 176 0v146z" fill="url(#pv-light)"/>'
    . '<g clip-path="url(#pv-glass)">' . $curtain . '<g transform="matrix(-1 0 0 1 300 0)">' . $curtain . '</g></g>'
    . '<rect x="147.5" y="62" width="5" height="234" fill="#1572A6"/><rect x="62" y="178" width="176" height="5" fill="#1572A6"/>'
    . '<circle cx="90" cy="206" r="6" fill="#1572A6"/><circle cx="210" cy="206" r="6" fill="#1572A6"/>'
    . '<rect x="26" y="298" width="248" height="16" rx="6" fill="#0F5A85"/>'
    . '<g class="pv-hero-art__badge">'
    . '<circle cx="240" cy="270" r="34" fill="#122A3D" opacity=".08" transform="translate(0 5)"/>'
    . '<circle cx="240" cy="270" r="34" fill="#fff"/>'
    . '<path d="M240 248l17 6.5v12c0 11.5-7.5 19-17 22.5-9.5-3.5-17-11-17-22.5v-12z" fill="#1984C0"/>'
    . '<path d="M232 268l6 6 11-11" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>'
    . '</g></svg></div>';

echo page_hero([
    'crumbs' => [[t('nav.privacy'), 'privacy.html']],
    'eyebrow' => t('privacy.hero.eyebrow'),
    'title' => t('privacy.hero.title'),
    'text' => t('privacy.hero.text'),
    'actions' => $updatedHtml,
    'aside' => $heroArt,
    'class' => 'pv-hero',
]);
?>

<?php if ($sections): ?>
<div class="section pv-wrap">
  <div class="container pv-layout">
    <nav class="pv-toc" aria-labelledby="pv-toc-title">
      <p class="pv-toc__title" id="pv-toc-title"><?= icon('list-checks') ?><span><?= te('privacy.toc_title') ?></span></p>
      <ol class="pv-toc__list" role="list">
        <?php foreach ($sections as $i => $s): ?>
        <li><a class="pv-toc__link" href="#pv-<?= $i + 1 ?>"><span class="pv-toc__num" aria-hidden="true"><?= e($num($i)) ?></span><span><?= e((string) $s['title']) ?></span></a></li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <div class="pv-content">
      <?php foreach ($sections as $i => $s): ?>
      <section class="pv-sec" id="pv-<?= $i + 1 ?>" aria-labelledby="pv-<?= $i + 1 ?>-title">
        <h2 class="pv-sec__title" id="pv-<?= $i + 1 ?>-title"><span class="pv-sec__num" aria-hidden="true"><?= e($num($i)) ?></span><span><?= e((string) $s['title']) ?></span></h2>
        <div class="pv-sec__text prose"><?= $body($s['text']) ?></div>
      </section>
      <?php endforeach; ?>

      <div class="pv-end">
        <?php if ($hotline !== ''): ?>
        <a class="btn btn--primary" href="<?= e(tel_href($hotline)) ?>"><?= icon('phone') ?><span><?= te('buttons.call_hotline') ?></span></a>
        <?php endif; ?>
        <?= btn(t('nav.contact'), 'contact.html', 'outline', ['icon' => 'message-circle']) ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?= charter_band() ?>
