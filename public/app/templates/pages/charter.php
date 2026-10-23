<?php
/**
 * ما نؤمن به — ميثاق بيوت مصر (content document §04).
 * Sections: hero (with an emblem of nine lit windows, one per principle),
 * the nine principles, the closing line «نحكي أثر الخير.. لا نكشف أصحاب الحاجة.»,
 * and «شاركنا الخير».
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('charter.meta_title');
BM::$page['description'] = (string) t('charter.meta_description');

$principles = array_values(array_filter(tl('charter.principles'), function ($p) {
    return is_array($p) && trim((string) ($p['title'] ?? '')) !== '';
}));
$icons = ['heart', 'eye-off', 'users', 'search', 'hand-heart', 'sprout', 'handshake', 'badge-check', 'sparkles'];

/* Hero emblem: an arched window holding one lit window per principle. */
$count = max(1, count($principles));
$cols = 3;
$rows = (int) ceil($count / $cols);
$winW = 44;
$winH = 56;
$gapX = 22;
$gapY = 18;
$top = 122;
$height = $top + $rows * $winH + ($rows - 1) * $gapY + 34;
$windows = '';
for ($n = 0; $n < $count; $n++) {
    $col = $n % $cols;
    $row = intdiv($n, $cols);
    $inRow = min($cols, $count - $row * $cols); // an incomplete last row is centred
    $x = (300 - ($inRow * $winW + ($inRow - 1) * $gapX)) / 2 + $col * ($winW + $gapX);
    $y = $top + $row * ($winH + $gapY);
    $r = $winW / 2;
    $windows .= '<path class="charter-emblem__win" style="--d:' . number_format(0.25 + $n * 0.16, 2, '.', '') . 's"'
        . ' d="M' . $x . ' ' . ($y + $winH) . 'V' . ($y + $r) . 'a' . $r . ' ' . $r . ' 0 0 1 ' . $winW . ' 0V' . ($y + $winH) . 'z"/>';
}
$sill = $height - 14;
$emblem = '<div class="charter-emblem" aria-hidden="true">'
    . '<svg viewBox="0 0 300 ' . $height . '" focusable="false">'
    . '<defs><linearGradient id="charter-emblem-bg" x1="0" y1="0" x2="0" y2="1">'
    . '<stop offset="0" stop-color="#1984C0"/><stop offset="1" stop-color="#0F5A85"/></linearGradient></defs>'
    . '<path d="M10 ' . $sill . 'V150A140 140 0 0 1 290 150V' . $sill . 'z" fill="url(#charter-emblem-bg)"/>'
    . '<path d="M24 ' . $sill . 'V150A126 126 0 0 1 276 150V' . $sill . '" fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="2"/>'
    . '<rect x="0" y="' . $sill . '" width="300" height="14" rx="5" fill="#0B4466"/>'
    . '<circle cx="150" cy="72" r="26" fill="#FDEBC8"/><circle cx="150" cy="72" r="15" fill="#EFA927"/>'
    . $windows
    . '</svg></div>';

/* Keep each half of the closing line together («نحكي أثر الخير..» / «لا نكشف أصحاب الحاجة.»). */
$closingParts = preg_split('/(?<=\.\.)\s+/u', trim((string) t('charter.closing'))) ?: [];
$closingHtml = implode(' ', array_map(function ($part) {
    return '<span class="charter-closing__seg">' . e($part) . '</span>';
}, array_filter($closingParts, 'strlen')));

echo page_hero([
    'crumbs' => [[t('nav.about'), 'about.html'], [t('nav.charter'), 'charter.html']],
    'eyebrow' => t('charter.hero.eyebrow'),
    'title' => t('charter.hero.title'),
    'text' => t('charter.hero.text'),
    'aside' => $emblem,
    'class' => 'charter-hero',
]);
?>

<section class="section section--tint charter-principles" aria-labelledby="principles-title">
  <div class="container">
    <h2 class="sr-only" id="principles-title"><?= te('charter.principles_title') ?></h2>
    <ol class="principles" role="list">
      <?php foreach ($principles as $i => $p): ?>
      <li class="principle">
        <div class="principle__top">
          <span class="principle__icon"><?= icon($icons[$i % count($icons)]) ?></span>
          <span class="principle__num" aria-hidden="true"><?= e(sprintf('%02d', $i + 1)) ?></span>
        </div>
        <h3 class="principle__title"><?= e((string) $p['title']) ?></h3>
        <?php if (trim((string) ($p['text'] ?? '')) !== ''): ?>
        <div class="principle__text"><?= p_html((string) $p['text']) ?></div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="charter-closing" aria-labelledby="charter-closing-title">
  <div class="container charter-closing__inner">
    <span class="charter-closing__icon"><?= icon('shield-check') ?></span>
    <h2 class="charter-closing__quote" id="charter-closing-title"><?= $closingHtml ?></h2>
    <div class="actions">
      <?= btn(t('buttons.share_good'), 'get-involved.html', 'donate', ['size' => 'lg', 'icon' => 'heart']) ?>
      <?= btn(t('nav.contact'), 'contact.html', 'outline', ['size' => 'lg', 'icon' => 'message-circle']) ?>
    </div>
  </div>
</section>

<?= cta_share() ?>
