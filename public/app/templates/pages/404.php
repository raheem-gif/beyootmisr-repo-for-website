<?php
/**
 * صفحة غير موجودة (404). Rendered with BM::$page['absolute'] = true, so u()
 * returns root-absolute links ("/about.html") and the page works when the
 * server shows it for any missing URL, at any folder depth (.htaccess:
 * ErrorDocument 404 /404.html).
 * Layout: the brand skyline inside a large arched window, «404» with a lit
 * arched window as its zero, the message, the main ways back, quick links.
 */
defined('BM_APP') || exit;

BM::$page['title'] = (string) t('not_found.meta_title');
BM::$page['description'] = (string) t('not_found.text');

$links = [
    ['nav.about', 'about.html'],
    ['nav.charter', 'charter.html'],
    ['nav.stories', 'stories.html'],
    ['nav.achievements', 'achievements.html'],
    ['nav.get_involved', 'get-involved.html'],
];
?>
<section class="nf" aria-labelledby="nf-title">
  <div class="container nf__inner">
    <div class="nf__art">
      <div class="nf__window"><?= skyline_svg() ?></div>
    </div>

    <div class="nf__content">
      <p class="nf__code" aria-hidden="true"><span>4</span><span class="nf__zero"></span><span>4</span></p>
      <h1 class="nf__title" id="nf-title"><?= te('not_found.title') ?></h1>
      <div class="nf__text"><?= p_html(t('not_found.text')) ?></div>
      <div class="actions nf__actions">
        <?= btn(t('ui.back_home'), 'index.html', 'primary', ['icon' => 'house']) ?>
        <?= btn(t('nav.activities'), 'activities.html', 'outline', ['icon' => 'heart-handshake']) ?>
        <?= btn(t('nav.contact'), 'contact.html', 'ghost', ['icon' => 'message-circle']) ?>
      </div>
      <nav class="nf__links" aria-labelledby="nf-links-title">
        <p class="nf__links-title" id="nf-links-title"><?= te('nav.quick_links') ?></p>
        <ul role="list">
          <?php foreach ($links as [$key, $href]): ?>
          <li><a href="<?= e(u($href)) ?>"><?= te($key) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </div>
</section>
