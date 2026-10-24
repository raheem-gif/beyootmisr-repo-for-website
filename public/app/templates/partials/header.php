<?php
/**
 * Site header: logo, main navigation, and the two fixed buttons required by
 * the content document (§01): «تبرع الآن» and «اطلب مساعدة».
 */
defined('BM_APP') || exit;

$current = (string) BM::$page['nav'];
$here = (string) BM::$page['path'];
$activities = bm_activities();

/** aria-current for exact page links. */
$cur = function (string $path) use ($here): string {
    return $path === $here ? ' aria-current="page"' : '';
};
/** Marks the top-level item of the current section. */
$sec = function (string $key) use ($current): string {
    return $key === $current ? ' is-current' : '';
};
?>
<header class="site-header" data-header>
  <div class="container site-header__inner">
    <?= brand_html() ?>

    <nav class="main-nav" id="main-nav" aria-label="<?= te('ui.main_nav') ?>" data-nav>
      <ul class="nav-list" role="list">
        <li class="nav-item nav-item--home<?= $sec('home') ?>"><a class="nav-link" href="<?= e(u('index.html')) ?>"<?= $cur('index.html') ?>><?= te('nav.home') ?></a></li>

        <li class="nav-item has-sub<?= $sec('about') ?>">
          <a class="nav-link" href="<?= e(u('about.html')) ?>"<?= $cur('about.html') ?>><?= te('nav.about') ?></a>
          <button class="sub-toggle" type="button" aria-expanded="false" aria-controls="sub-about" data-sub-toggle>
            <span class="sr-only"><?= te('ui.show_submenu') ?>: <?= te('nav.about') ?></span><?= icon('chevron-down') ?>
          </button>
          <div class="subnav subnav--small" id="sub-about">
            <ul role="list">
              <li><a class="subnav__link" href="<?= e(u('about.html')) ?>"<?= $cur('about.html') ?>><span class="subnav__icon"><?= icon('house') ?></span><span><strong><?= te('nav.about_story') ?></strong><small><?= te('nav.about_story_hint') ?></small></span></a></li>
              <li><a class="subnav__link" href="<?= e(u('charter.html')) ?>"<?= $cur('charter.html') ?>><span class="subnav__icon"><?= icon('shield-check') ?></span><span><strong><?= te('nav.charter') ?></strong><small><?= te('nav.charter_hint') ?></small></span></a></li>
            </ul>
          </div>
        </li>

        <li class="nav-item has-sub<?= $sec('activities') ?>">
          <a class="nav-link" href="<?= e(u('activities.html')) ?>"<?= $cur('activities.html') ?>><?= te('nav.activities') ?></a>
          <button class="sub-toggle" type="button" aria-expanded="false" aria-controls="sub-activities" data-sub-toggle>
            <span class="sr-only"><?= te('ui.show_submenu') ?>: <?= te('nav.activities') ?></span><?= icon('chevron-down') ?>
          </button>
          <div class="subnav subnav--mega" id="sub-activities">
            <ul class="subnav__grid" role="list">
              <?php foreach ($activities as $a): ?>
              <li><a class="subnav__link" href="<?= e(u($a['url'])) ?>"<?= $cur($a['url']) ?>><span class="subnav__icon"><?= icon($a['icon']) ?></span><span><strong><?= e($a['name']) ?></strong><small><?= e($a['subtitle']) ?></small></span></a></li>
              <?php endforeach; ?>
            </ul>
            <div class="subnav__feature">
              <a class="subnav__link subnav__link--feature" href="<?= e(u('ehna-maaki.html')) ?>"<?= $cur('ehna-maaki.html') ?>><span class="subnav__icon"><?= icon('scale') ?></span><span><strong><?= te('ehna_maaki.name') ?></strong><small><?= te('ehna_maaki.hero.subtitle') ?></small></span></a>
              <a class="subnav__all" href="<?= e(u('activities.html')) ?>"><?= te('nav.activities_all') ?><?= icon('arrow-left') ?></a>
            </div>
          </div>
        </li>

        <li class="nav-item<?= $sec('stories') ?>"><a class="nav-link" href="<?= e(u('stories.html')) ?>"<?= $cur('stories.html') ?>><?= te('nav.stories') ?></a></li>
        <li class="nav-item<?= $sec('achievements') ?>"><a class="nav-link" href="<?= e(u('achievements.html')) ?>"<?= $cur('achievements.html') ?>><?= te('nav.achievements') ?></a></li>
        <li class="nav-item<?= $sec('get_involved') ?>"><a class="nav-link" href="<?= e(u('get-involved.html')) ?>"<?= $cur('get-involved.html') ?>><?= te('nav.get_involved') ?></a></li>
        <li class="nav-item<?= $sec('contact') ?>"><a class="nav-link" href="<?= e(u('contact.html')) ?>"<?= $cur('contact.html') ?>><?= te('nav.contact') ?></a></li>
      </ul>

      <?php
      // Drawer contact buttons only for numbers that can be dialled (never an empty href).
      $hotlineDrawer = (string) bm_settings()['contact']['hotline'];
      $telDrawer = tel_href($hotlineDrawer);
      $waDrawer = wa_general();
      if ($telDrawer !== '' || $waDrawer !== ''): ?>
      <div class="nav-drawer-extra">
        <?php if ($telDrawer !== ''): ?>
        <a class="drawer-contact" href="<?= e($telDrawer) ?>"><?= icon('phone') ?><span><?= te('buttons.call_hotline') ?></span><?= phone_html($hotlineDrawer) ?></a>
        <?php endif; ?>
        <?php if ($waDrawer !== ''): ?>
        <a class="drawer-contact drawer-contact--wa" href="<?= e($waDrawer) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span><?= te('buttons.whatsapp') ?></span></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </nav>

    <div class="header-actions">
      <a class="btn btn--outline btn--sm header-btn header-btn--help" href="<?= e(u('get-involved.html#request-help')) ?>"><?= icon('hand-helping') ?><span><?= te('buttons.request_help') ?></span></a>
      <a class="btn btn--donate btn--sm header-btn header-btn--donate" href="<?= e(u('get-involved.html#donate')) ?>"><?= icon('heart') ?><span><?= te('buttons.donate_now') ?></span></a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" data-nav-toggle data-label-open="<?= te('ui.menu') ?>" data-label-close="<?= te('ui.close_menu') ?>">
        <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="sr-only" data-nav-toggle-label><?= te('ui.menu') ?></span>
      </button>
    </div>
  </div>
</header>
