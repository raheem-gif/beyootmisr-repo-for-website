<?php
/**
 * Reusable UI components. Each returns an HTML string; templates echo them.
 * All dynamic values are escaped here, so templates stay simple.
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/* ------------------------------------------------------------------------ */
/* Brand                                                                     */
/* ------------------------------------------------------------------------ */

/** Logo mark: a house with a lit, arched door (brand blue + amber). */
function brand_mark(string $class = 'brand-mark'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 48 48" width="44" height="44" aria-hidden="true" focusable="false">'
        . '<path d="M22.1 4.6a3 3 0 0 1 3.8 0l16 13.1a3 3 0 0 1 1.1 2.3V41a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V20a3 3 0 0 1 1.1-2.3z" fill="#1984C0"/>'
        . '<path d="M17.5 44V32.5a6.5 6.5 0 0 1 13 0V44z" fill="#EFA927"/>'
        . '<rect x="20.5" y="16" width="7" height="7" rx="1.6" fill="#fff" opacity=".92"/>'
        . '</svg>';
}

/**
 * Site logo. Uses the official uploaded logo (settings.logo) when present;
 * otherwise the mark + wordmark.
 */
function brand_html(string $variant = ''): string
{
    $s = bm_settings();
    $cls = 'brand' . ($variant !== '' ? ' brand--' . $variant : '');
    $label = t('site.name') . ' — ' . t('nav.home');
    if ($s['logo'] !== '' && $variant !== 'light') {
        $size = @getimagesize(BM_PUBLIC . '/' . ltrim($s['logo'], '/'));
        $dims = $size ? ' width="' . (int) round($size[0] * 52 / max(1, $size[1])) . '" height="52"' : ' height="52"';
        return '<a class="' . $cls . ' brand--image" href="' . e(u('index.html')) . '" aria-label="' . e($label) . '">'
            . '<img src="' . e(upload_url($s['logo'])) . '" alt="' . e(t('site.full_name')) . '"' . $dims . '></a>';
    }
    return '<a class="' . $cls . '" href="' . e(u('index.html')) . '" aria-label="' . e($label) . '">'
        . brand_mark()
        . '<span class="brand-text"><strong>' . te('site.name') . '</strong><small>' . te('site.descriptor') . '</small></span>'
        . '</a>';
}

/* ------------------------------------------------------------------------ */
/* Buttons & small elements                                                  */
/* ------------------------------------------------------------------------ */

/**
 * Button link.
 * Variants: donate (amber), primary (blue), outline, ghost, light, whatsapp.
 * Options: icon, icon_end, size ('sm'|'lg'), external, class, attrs.
 */
function btn(string $label, string $href, string $variant = 'primary', array $o = []): string
{
    $cls = 'btn btn--' . $variant;
    if (!empty($o['size'])) {
        $cls .= ' btn--' . $o['size'];
    }
    if (!empty($o['class'])) {
        $cls .= ' ' . $o['class'];
    }
    $attrs = ['class' => $cls, 'href' => u($href)];
    if (!empty($o['external']) || preg_match('#^https?://#', $href)) {
        $attrs['target'] = '_blank';
        $attrs['rel'] = 'noopener';
    }
    $attrs = array_merge($attrs, $o['attrs'] ?? []);
    $inner = '';
    if (!empty($o['icon'])) {
        $inner .= icon($o['icon']);
    }
    $inner .= '<span>' . e($label) . '</span>';
    if (!empty($o['icon_end'])) {
        $inner .= icon($o['icon_end'], 'icon-end');
    }
    return '<a' . attrs($attrs) . '>' . $inner . '</a>';
}

function eyebrow(string $text, string $class = ''): string
{
    if (trim($text) === '') {
        return '';
    }
    return '<p class="eyebrow' . ($class !== '' ? ' ' . e($class) : '') . '">' . e($text) . '</p>';
}

/**
 * Section heading block.
 * Options: eyebrow, title, text, id (heading id), level (2), align ('center'|'start'), actions (html), class,
 * bar (true: the short amber bar under the title, `.section-title--bar`).
 */
function section_head(array $o): string
{
    $level = (int) ($o['level'] ?? 2);
    $align = $o['align'] ?? 'start';
    $out = '<div class="section-head section-head--' . e($align) . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '">';
    $out .= eyebrow((string) ($o['eyebrow'] ?? ''));
    if (!empty($o['title'])) {
        $out .= '<h' . $level . (!empty($o['id']) ? ' id="' . e($o['id']) . '"' : '') . ' class="section-title' . (!empty($o['bar']) ? ' section-title--bar' : '') . '">'
            . e($o['title']) . '</h' . $level . '>';
    }
    if (!empty($o['text'])) {
        $out .= '<div class="section-lead">' . p_html($o['text']) . '</div>';
    }
    if (!empty($o['actions'])) {
        $out .= '<div class="actions">' . $o['actions'] . '</div>';
    }
    return $out . '</div>';
}

/** Blue tagline with an amber window marker (the short closing lines of each activity). */
function tagline(string $text, string $class = ''): string
{
    if (trim($text) === '') {
        return '';
    }
    return '<p class="tagline' . ($class !== '' ? ' ' . e($class) : '') . '">' . e($text) . '</p>';
}

/** Informational notice (e.g. "announced after verification"). Variants: info, pending, privacy. */
function notice(string $text, string $variant = 'info', string $iconName = 'badge-info'): string
{
    if (trim($text) === '') {
        return '';
    }
    return '<div class="notice notice--' . e($variant) . '">' . icon($iconName) . '<div>' . p_html($text) . '</div></div>';
}

function empty_state(string $iconName, string $title, string $text): string
{
    return '<div class="empty-state">'
        . '<span class="empty-state__icon">' . icon($iconName) . '</span>'
        . ($title !== '' ? '<h3 class="empty-state__title">' . e($title) . '</h3>' : '')
        . p_html($text)
        . '</div>';
}

function status_badge(string $status): string
{
    $label = $status === 'done' ? t('ui.status_done') : t('ui.status_ongoing');
    $iconName = $status === 'done' ? 'circle-check' : 'refresh-cw';
    return '<span class="badge badge--' . e($status) . '">' . icon($iconName) . e($label) . '</span>';
}

function check_list(array $items, string $class = ''): string
{
    if (!$items) {
        return '';
    }
    $out = '<ul class="check-list' . ($class !== '' ? ' ' . e($class) : '') . '" role="list">';
    foreach ($items as $item) {
        $out .= '<li>' . icon('check') . '<span>' . e((string) $item) . '</span></li>';
    }
    return $out . '</ul>';
}

/** Numbered steps (e.g. «إحنا معاكي» steps). $class adds modifiers such as "steps--row". */
function steps(array $items, string $class = ''): string
{
    $out = '<ol class="steps' . ($class !== '' ? ' ' . e($class) : '') . '" role="list">';
    foreach (array_values($items) as $i => $item) {
        $out .= '<li class="step"><span class="step__num" aria-hidden="true">' . ($i + 1) . '</span><p>' . e((string) $item) . '</p></li>';
    }
    return $out . '</ol>';
}

/** FAQ accordion using native <details> (works without JavaScript). */
function faq(array $items): string
{
    $out = '<div class="faq">';
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['q'])) {
            continue;
        }
        $out .= '<details class="faq__item"><summary><span>' . e($item['q']) . '</span>' . icon('chevron-down', 'faq__chev') . '</summary>'
            . '<div class="faq__answer">' . p_html((string) ($item['a'] ?? '')) . '</div></details>';
    }
    return $out . '</div>';
}

/** Chips list (e.g. legal topics). */
function chips(array $items): string
{
    $out = '<ul class="chips" role="list">';
    foreach ($items as $item) {
        $out .= '<li class="chip">' . e((string) $item) . '</li>';
    }
    return $out . '</ul>';
}

/* ------------------------------------------------------------------------ */
/* Page structure                                                            */
/* ------------------------------------------------------------------------ */

/** @param array<int,array{0:string,1:string}> $items [label, href] — last item is the current page */
function breadcrumb(array $items): string
{
    $out = '<nav class="breadcrumb" aria-label="' . te('ui.breadcrumb') . '"><ol>';
    $out .= '<li><a href="' . e(u('index.html')) . '">' . te('nav.home') . '</a></li>';
    $last = count($items) - 1;
    foreach (array_values($items) as $i => $item) {
        [$label, $href] = $item;
        if ($i === $last) {
            $out .= '<li><span aria-current="page">' . e($label) . '</span></li>';
        } else {
            $out .= '<li><a href="' . e(u($href)) . '">' . e($label) . '</a></li>';
        }
    }
    return $out . '</ol></nav>';
}

/**
 * Inner-page hero.
 * Options: crumbs (breadcrumb items), eyebrow, title, subtitle, text, actions (html), icon, aside (html), class.
 */
function page_hero(array $o): string
{
    $out = '<header class="page-hero' . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '">';
    $out .= '<div class="page-hero__deco" aria-hidden="true">' . hero_windows_svg() . '</div>';
    $out .= '<div class="container page-hero__inner">';
    $out .= '<div class="page-hero__content">';
    if (!empty($o['crumbs'])) {
        $out .= breadcrumb($o['crumbs']);
    }
    if (!empty($o['icon'])) {
        $out .= '<span class="page-hero__icon">' . icon($o['icon']) . '</span>';
    }
    $out .= eyebrow((string) ($o['eyebrow'] ?? ''));
    $out .= '<h1 class="page-title">' . e((string) ($o['title'] ?? '')) . '</h1>';
    if (!empty($o['subtitle'])) {
        $out .= '<p class="page-subtitle">' . e($o['subtitle']) . '</p>';
    }
    if (!empty($o['text'])) {
        $out .= '<div class="page-lead">' . p_html($o['text']) . '</div>';
    }
    if (!empty($o['actions'])) {
        $out .= '<div class="actions">' . $o['actions'] . '</div>';
    }
    $out .= '</div>';
    if (!empty($o['aside'])) {
        $out .= '<div class="page-hero__aside">' . $o['aside'] . '</div>';
    }
    return $out . '</div></header>';
}

/* ------------------------------------------------------------------------ */
/* Cards                                                                     */
/* ------------------------------------------------------------------------ */

function activity_card(array $a, string $heading = 'h3'): string
{
    return '<article class="activity-card">'
        . '<div class="activity-card__top"><span class="activity-card__icon">' . icon($a['icon']) . '</span>'
        . '<span class="activity-card__num" aria-hidden="true">' . e($a['num']) . '</span></div>'
        . '<' . $heading . ' class="activity-card__title"><a class="stretched" href="' . e(u($a['url'])) . '">' . e($a['name']) . '</a></' . $heading . '>'
        . '<p class="activity-card__subtitle">' . e($a['subtitle']) . '</p>'
        . '<p class="activity-card__tagline">' . e($a['tagline']) . '</p>'
        . '<span class="activity-card__more" aria-hidden="true">' . te('ui.read_more') . icon('arrow-left') . '</span>'
        . '</article>';
}

/** Feature card for the «إحنا معاكي» initiative. */
function initiative_card(string $heading = 'h3'): string
{
    return '<article class="initiative-card">'
        . '<span class="initiative-card__icon">' . icon('scale') . '</span>'
        . '<div class="initiative-card__body">'
        . eyebrow(t('activities.initiatives_title'), 'eyebrow--light')
        . '<' . $heading . ' class="initiative-card__title"><a class="stretched" href="' . e(u('ehna-maaki.html')) . '">' . te('ehna_maaki.name') . '</a></' . $heading . '>'
        . '<p class="initiative-card__subtitle">' . te('ehna_maaki.hero.subtitle') . '</p>'
        . '<p class="initiative-card__text">' . te('ehna_maaki.card_text') . '</p>'
        . '</div>'
        . '<span class="initiative-card__more" aria-hidden="true">' . icon('arrow-left') . '</span>'
        . '</article>';
}

/**
 * Stat cards. Unverified numbers are never shown: such cards read
 * "يُعلن بعد التوثيق" instead (content document §08, §12).
 * Options: show_meta (bool, show period + last update line).
 */
function stat_grid(array $items, array $o = []): string
{
    if (!$items) {
        return '';
    }
    $stats = bm_stats();
    $out = '<div class="stats-grid">';
    foreach ($items as $item) {
        $published = bm_stat_published($item, $stats);
        $out .= '<div class="stat' . ($published ? '' : ' stat--pending') . '">'
            . '<span class="stat__icon">' . icon((string) $item['icon']) . '</span>';
        if ($published) {
            $num = fmt_number($item['value']);
            $count = is_numeric($item['value']) ? ' data-count="' . e((string) $item['value']) . '"' : '';
            $out .= '<p class="stat__value"><bdi' . $count . '>' . e($num) . '</bdi>' . ($item['suffix'] !== '' ? '<span class="stat__suffix">' . e($item['suffix']) . '</span>' : '') . '</p>';
        } else {
            $out .= '<p class="stat__value stat__value--pending">' . te('ui.pending_number') . '</p>';
        }
        $out .= '<p class="stat__label">' . e((string) $item['label']) . '</p></div>';
    }
    $out .= '</div>';
    if (!empty($o['show_meta'])) {
        $meta = [];
        if ($stats['period'] !== '') {
            $meta[] = '<span>' . icon('calendar') . te('ui.period') . ': ' . e($stats['period']) . '</span>';
        }
        if ($stats['updated_at'] !== '') {
            $meta[] = '<span>' . icon('refresh-cw') . te('ui.last_update') . ': <time datetime="' . e($stats['updated_at']) . '">' . e(ar_date($stats['updated_at'])) . '</time></span>';
        }
        if ($meta) {
            $out .= '<p class="stats-meta">' . implode('', $meta) . '</p>';
        }
    }
    return $out;
}

/**
 * Responsive image tag for an uploaded image record.
 * Options: class, sizes, loading ('lazy'|'eager'), link (wrap in lightbox link), group.
 */
function img_tag(array $img, array $o = []): string
{
    $alt = trim((string) $img['alt']) !== '' ? (string) $img['alt'] : (string) ($img['caption'] !== '' ? $img['caption'] : ($o['alt'] ?? ''));
    $a = [
        'src' => upload_url($img['thumb'] !== '' ? $img['thumb'] : $img['src']),
        'alt' => $alt,
        'loading' => $o['loading'] ?? 'lazy',
        'decoding' => 'async',
        'class' => $o['class'] ?? null,
    ];
    // srcset only when a real thumbnail exists; widths come from the stored record.
    if ($img['thumb'] !== '' && $img['thumb'] !== $img['src'] && (int) $img['w'] > 640) {
        $a['srcset'] = upload_url($img['thumb']) . ' 640w, ' . upload_url($img['src']) . ' ' . (int) $img['w'] . 'w';
        $a['sizes'] = $o['sizes'] ?? '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw';
    }
    if (!empty($img['w']) && !empty($img['h'])) {
        $a['width'] = (string) $img['w'];
        $a['height'] = (string) $img['h'];
    }
    return '<img' . attrs($a) . '>';
}

/** Image placeholder used on cards without photos (brand pattern, no stock images). */
function media_placeholder(string $iconName): string
{
    return '<div class="media-placeholder" aria-hidden="true">' . hero_windows_svg('media-placeholder__art') . '<span>' . icon($iconName) . '</span></div>';
}

function project_card(array $p, string $heading = 'h3'): string
{
    $a = bm_activity((string) $p['activity']);
    $cover = bm_project_cover($p);
    $media = $cover ? img_tag($cover, ['class' => 'card-media__img']) : media_placeholder($a['icon'] ?? 'building-2');
    $out = '<article class="project-card" data-status="' . e($p['status']) . '" data-activity="' . e($p['activity']) . '">'
        . '<div class="card-media">' . $media . status_badge($p['status']) . '</div>'
        . '<div class="project-card__body">';
    if ($a) {
        $out .= '<p class="project-card__activity">' . icon($a['icon']) . e($a['name']) . '</p>';
    }
    $out .= '<' . $heading . ' class="project-card__title"><a class="stretched" href="' . e(u($p['url'])) . '">' . e($p['title']) . '</a></' . $heading . '>';
    if ($p['location'] !== '') {
        $out .= '<p class="project-card__meta">' . icon('map-pin') . e($p['location']) . '</p>';
    }
    if ($p['summary'] !== '') {
        $out .= '<p class="project-card__summary">' . e(excerpt($p['summary'], 150)) . '</p>';
    }
    $out .= '<p class="project-card__foot"><span>' . te('ui.view_project') . icon('arrow-left') . '</span>';
    if ($p['updated_at'] !== '') {
        $out .= '<span class="muted">' . te('ui.last_update') . ': ' . e(ar_date($p['updated_at'])) . '</span>';
    }
    return $out . '</p></div></article>';
}

function story_type_name(string $type): string
{
    return (string) t('stories.types.' . $type . '.name');
}

function story_card(array $s, string $heading = 'h3'): string
{
    $types = bm_story_types();
    $iconName = $types[$s['type']]['icon'] ?? 'heart';
    $media = !empty($s['images']) ? img_tag($s['images'][0], ['class' => 'card-media__img']) : media_placeholder($iconName);
    $out = '<article class="story-card" data-type="' . e($s['type']) . '">'
        . '<div class="card-media">' . $media . '<span class="badge badge--type">' . icon($iconName) . e(story_type_name($s['type'])) . '</span></div>'
        . '<div class="story-card__body">'
        . '<' . $heading . ' class="story-card__title"><a class="stretched" href="' . e(u($s['url'])) . '">' . e($s['title']) . '</a></' . $heading . '>';
    if ($s['summary'] !== '') {
        $out .= '<p class="story-card__summary">' . e(excerpt($s['summary'], 170)) . '</p>';
    }
    $out .= '<p class="story-card__foot"><span>' . te('ui.read_story') . icon('arrow-left') . '</span>';
    if ($s['date'] !== '') {
        $out .= '<span class="muted">' . e(ar_date($s['date'])) . '</span>';
    }
    return $out . '</p></div></article>';
}

/**
 * Photo gallery with lightbox links. Shows an honest empty state when there
 * are no approved photos yet (no stock photography, per §12).
 */
function gallery(array $images, array $o = []): string
{
    if (!$images) {
        return empty_state('camera', '', (string) ($o['empty'] ?? t('ui.gallery_empty')));
    }
    $group = $o['group'] ?? 'gallery';
    $out = '<ul class="gallery" role="list" data-lightbox="' . e($group) . '">';
    foreach ($images as $img) {
        $out .= '<li><a class="gallery__link" href="' . e(upload_url($img['src'])) . '" data-caption="' . e($img['caption'] !== '' ? $img['caption'] : $img['alt']) . '">'
            . img_tag($img, ['sizes' => '(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw', 'alt' => (string) ($o['alt'] ?? '')])
            . '</a>' . ($img['caption'] !== '' ? '<p class="gallery__caption">' . e($img['caption']) . '</p>' : '') . '</li>';
    }
    return $out . '</ul>';
}

/* ------------------------------------------------------------------------ */
/* Contact                                                                   */
/* ------------------------------------------------------------------------ */

/** International digits of the general WhatsApp line: its own number, else the hotline ('' when neither is set). */
function wa_general_number(): string
{
    $c = bm_settings()['contact'];
    $n = intl_phone((string) $c['whatsapp_general']);
    return $n !== '' ? $n : intl_phone((string) $c['hotline']);
}

/** Default WhatsApp link to the general number with a greeting ('' when no number is set). */
function wa_general(string $message = ''): string
{
    return wa_href(wa_general_number(), $message !== '' ? $message : (string) t('ui.wa_greeting'));
}

/** WhatsApp link to the «إحنا معاكي» line ('' when no number is set). */
function wa_legal(): string
{
    return wa_href((string) bm_settings()['contact']['whatsapp_legal'], (string) t('ehna_maaki.wa_message'));
}

/** Big call / WhatsApp buttons for the two official numbers (only those that can be dialled). */
function help_lines(): string
{
    $c = bm_settings()['contact'];
    $tel = tel_href((string) $c['hotline']);
    $wa = wa_general();
    $waLegal = wa_legal();
    $out = '<div class="help-lines">';
    if ($tel !== '') {
        $out .= '<div class="help-line">'
            . '<p class="help-line__label">' . te('get_involved.help.general_label') . '</p>'
            . '<p class="help-line__num">' . phone_html($c['hotline']) . '</p>'
            . '<div class="actions">'
            . btn(t('buttons.call_hotline'), $tel, 'primary', ['icon' => 'phone', 'size' => 'sm'])
            . ($wa !== '' ? btn(t('buttons.whatsapp'), $wa, 'whatsapp', ['icon' => 'whatsapp', 'size' => 'sm']) : '')
            . '</div></div>';
    }
    if ($waLegal !== '') {
        $out .= '<div class="help-line help-line--legal">'
            . '<p class="help-line__label">' . te('get_involved.help.legal_label') . '</p>'
            . '<p class="help-line__num">' . phone_html($c['whatsapp_legal']) . '</p>'
            . '<div class="actions">'
            . btn(t('buttons.legal_whatsapp'), $waLegal, 'whatsapp', ['icon' => 'whatsapp', 'size' => 'sm'])
            . '</div></div>';
    }
    return $out . '</div>';
}

/**
 * Message composer that opens WhatsApp with a prepared message. Nothing is
 * sent to or stored on the website ("نماذج تواصل محدودة البيانات").
 * Options: id, topics (list), preset (topic), title, text, heading level.
 */
function wa_form(array $o = []): string
{
    $c = bm_settings()['contact'];
    $general = wa_general_number();
    $legal = intl_phone((string) $c['whatsapp_legal']);
    if ($general === '') {
        return '';
    }
    $id = $o['id'] ?? 'wa-form';
    $topics = $o['topics'] ?? tl('contact.form.topics');
    $preset = $o['preset'] ?? '';
    $level = (int) ($o['level'] ?? 3);
    $out = '<form class="wa-form" id="' . e($id) . '" data-wa-form data-wa-general="' . e($general) . '" data-wa-legal="' . e($legal) . '"'
        . ' data-greeting="' . e((string) t('ui.wa_greeting')) . '" data-name-prefix="' . e((string) t('contact.form.name_prefix')) . '" data-topic-prefix="' . e((string) t('contact.form.topic_prefix')) . '"'
        . ' action="https://wa.me/' . e($general) . '" method="get" target="_blank">';
    $out .= '<h' . $level . ' class="wa-form__title">' . icon('whatsapp') . e((string) ($o['title'] ?? t('contact.form.title'))) . '</h' . $level . '>';
    $out .= '<p class="wa-form__text">' . e((string) ($o['text'] ?? t('contact.form.text'))) . '</p>';
    $out .= '<div class="field"><label for="' . e($id) . '-name">' . te('contact.form.name') . '</label>'
        . '<input id="' . e($id) . '-name" name="name" type="text" maxlength="60" autocomplete="name"></div>';
    $out .= '<div class="field"><label for="' . e($id) . '-topic">' . te('contact.form.topic') . '</label><select id="' . e($id) . '-topic" name="topic">';
    foreach ($topics as $topic) {
        $out .= '<option' . ($topic === $preset ? ' selected' : '') . '>' . e((string) $topic) . '</option>';
    }
    if ($legal !== '' && empty($o['no_legal'])) {
        $out .= '<option value="legal" data-legal="1">' . te('contact.form.legal_topic') . '</option>';
    }
    $out .= '</select></div>';
    $out .= '<div class="field"><label for="' . e($id) . '-message">' . te('contact.form.message') . '</label>'
        . '<textarea id="' . e($id) . '-message" name="text" rows="4" maxlength="800" required aria-describedby="' . e($id) . '-hint"></textarea>'
        . '<p class="field__hint" id="' . e($id) . '-hint">' . te('contact.form.message_hint') . '</p></div>';
    $out .= '<p class="wa-form__privacy">' . icon('shield-check') . te('contact.form.privacy') . '</p>';
    $out .= '<button class="btn btn--whatsapp" type="submit">' . icon('whatsapp') . '<span>' . te('contact.form.submit') . '</span></button>';
    return $out . '</form>';
}

/* ------------------------------------------------------------------------ */
/* Shared bands                                                              */
/* ------------------------------------------------------------------------ */

/** "شاركنا الخير" call-to-action band reused across pages. */
function cta_share(string $headingId = 'share-title'): string
{
    $ways = [
        ['icon' => 'hand-heart', 'label' => t('buttons.donate_now'), 'href' => 'get-involved.html#donate'],
        ['icon' => 'users', 'label' => t('buttons.share_time'), 'href' => 'get-involved.html#volunteer'],
        ['icon' => 'package', 'label' => t('buttons.in_kind'), 'href' => 'get-involved.html#in-kind'],
        ['icon' => 'heart-handshake', 'label' => t('buttons.support_activity'), 'href' => 'activities.html'],
    ];
    $out = '<section class="cta-band" aria-labelledby="' . e($headingId) . '"><div class="container cta-band__inner">'
        . '<div class="cta-band__text">' . eyebrow(t('home.share.eyebrow'), 'eyebrow--light')
        . '<h2 id="' . e($headingId) . '" class="cta-band__title">' . te('home.share.title') . '</h2>'
        . p_html(t('home.share.text')) . '</div>'
        . '<ul class="cta-band__ways" role="list">';
    foreach ($ways as $w) {
        $out .= '<li><a class="way" href="' . e(u($w['href'])) . '">' . icon($w['icon']) . '<span>' . e($w['label']) . '</span>' . icon('arrow-left', 'way__arrow') . '</a></li>';
    }
    return $out . '</ul></div></section>';
}

/** Charter quote band ("نحكي أثر الخير.. لا نكشف أصحاب الحاجة."). */
function charter_band(): string
{
    return '<section class="quote-band"><div class="container quote-band__inner">'
        . '<span class="quote-band__icon">' . icon('shield-check') . '</span>'
        . '<div>' . eyebrow(t('home.charter.eyebrow')) . '<p class="quote-band__quote">' . te('home.charter.quote') . '</p></div>'
        . btn(t('buttons.read_charter'), 'charter.html', 'outline', ['icon_end' => 'arrow-left'])
        . '</div></section>';
}
