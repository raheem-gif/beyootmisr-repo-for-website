<?php
/**
 * Admin UI: URLs, redirects, flash messages, page layout and small components.
 *
 *   adm_url(string $section = 'dashboard', array $query = [], string $hash = ''): string   "index.php?p=texts"
 *   adm_current_url(): string
 *   adm_redirect(string $section = 'dashboard', array $query = [], string $hash = ''): void  (exits)
 *   adm_public_url(string $rel): string        URL of a public file from the admin ("../uploads/…")
 *   adm_flash(string $type, string $message, string $details = ''): void    success|error|warning|info
 *   adm_flash_html(): string
 *   AdmPage::$title / ::$lead / ::$actions (HTML) / ::$wide (bool)        set by sections
 *   adm_layout(string $body, ?array $user, string $section): void         full page with navigation
 *   adm_layout_plain(string $body, string $title): void                    login / setup / errors
 *   adm_icon(string $name, string $class = ''): string                     admin icons + site icons
 *   adm_card(string $title, string $body, array $o = []): string          o: icon, id, class, actions, level
 *   adm_badge(string $text, string $variant = 'neutral'): string          neutral|blue|green|amber|red
 *   adm_alert(string $text, string $variant = 'info', string $title = ''): string
 *   adm_empty(string $icon, string $title, string $text = ''): string
 *   adm_time(int $ts): string / adm_date(int $ts): string / adm_ago(int $ts): string   Arabic dates
 *   adm_plural(int $n, string $one, string $two, string $few, string $many): string
 */
declare(strict_types=1);
defined('BM_APP') || exit;

final class AdmPage
{
    /** @var string */
    public static $title = '';
    /** @var string */
    public static $lead = '';
    /** @var string HTML */
    public static $actions = '';
    /** @var bool */
    public static $wide = false;
}

function adm_url(string $section = 'dashboard', array $query = [], string $hash = ''): string
{
    $q = $section !== 'dashboard' && $section !== '' ? ['p' => $section] : [];
    $q += array_filter($query, function ($v) {
        return $v !== null && $v !== '';
    });
    return 'index.php' . ($q ? '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '') . ($hash !== '' ? '#' . rawurlencode($hash) : '');
}

function adm_current_url(): string
{
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

function adm_redirect(string $section = 'dashboard', array $query = [], string $hash = ''): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . adm_url($section, $query, $hash), true, 303);
    exit;
}

function adm_public_url(string $rel): string
{
    if (preg_match('#^https?://#i', $rel)) {
        return $rel;
    }
    return '../' . ltrim($rel, '/');
}

function adm_flash(string $type, string $message, string $details = ''): void
{
    if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
        $type = 'info';
    }
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message, 'details' => $details];
}

function adm_flash_html(): string
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!is_array($items) || !$items) {
        return '';
    }
    $icons = ['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'triangle-alert', 'info' => 'info'];
    $html = '';
    foreach ($items as $f) {
        if (!is_array($f)) {
            continue;
        }
        $type = (string) ($f['type'] ?? 'info');
        $role = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="adm-alert adm-alert--' . e($type) . '" role="' . $role . '"' . ($type === 'error' ? ' tabindex="-1" data-focus' : '') . '>'
            . adm_icon($icons[$type] ?? 'info') . '<div><p>' . e((string) ($f['msg'] ?? '')) . '</p>';
        if (!empty($f['details'])) {
            $html .= '<details class="adm-tech"><summary>التفاصيل التقنية (للمطوّر)</summary><pre dir="ltr">' . e((string) $f['details']) . '</pre></details>';
        }
        $html .= '</div></div>';
    }
    return '<div class="adm-flashes">' . $html . '</div>';
}

function adm_icon(string $name, string $class = ''): string
{
    static $extra = null;
    if ($extra === null) {
        $extra = require BM_APP_DIR . '/admin/icons-data.php';
    }
    if (!isset($extra[$name])) {
        return icon($name, $class);
    }
    [, $body] = $extra[$name];
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

function adm_asset(string $file): string
{
    $path = ADM_WEB_DIR . '/' . $file;
    return $file . '?v=' . (is_file($path) ? substr((string) md5_file($path), 0, 8) : BM_VERSION);
}

function adm_head(string $title): string
{
    return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="same-origin">'
        . '<meta name="theme-color" content="#1572A6">'
        . '<title>' . e($title) . ' — لوحة إدارة بيوت مصر</title>'
        . '<link rel="icon" href="' . e(adm_public_url('assets/img/favicon.svg')) . '" type="image/svg+xml">'
        . '<link rel="preload" href="' . e(adm_public_url('assets/fonts/cairo-arabic.woff2')) . '" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="' . e(adm_asset('admin.css')) . '">'
        . '<script src="' . e(adm_asset('admin.js')) . '"></script>'
        . '</head>';
}

function adm_brand(): string
{
    return '<span class="adm-brand">' . brand_mark('adm-brand__mark') . '<span class="adm-brand__text"><strong>بيوت مصر</strong><small>لوحة إدارة الموقع</small></span></span>';
}

function adm_logout_form(string $class = ''): string
{
    return '<form method="post" action="' . e(adm_url('logout')) . '" class="adm-inline-form ' . e($class) . '">' . adm_csrf_input()
        . '<button type="submit" class="adm-btn adm-btn--ghost adm-btn--sm">' . adm_icon('log-out') . '<span>تسجيل الخروج</span></button></form>';
}

function adm_layout(string $body, ?array $user, string $section): void
{
    $sections = adm_sections();
    $title = AdmPage::$title !== '' ? AdmPage::$title : ($sections[$section]['label'] ?? '');
    $navHtml = '';
    foreach (adm_nav_groups() as $gKey => $gLabel) {
        $items = '';
        foreach ($sections as $key => $s) {
            if (($s['group'] ?? '') !== $gKey || (isset($s['nav']) && !$s['nav']) || !adm_can($user, $key)) {
                continue;
            }
            $current = $key === $section;
            $items .= '<li><a class="adm-nav__link' . ($current ? ' is-current' : '') . '" href="' . e(adm_url($key)) . '"' . ($current ? ' aria-current="page"' : '') . '>'
                . adm_icon((string) $s['icon']) . '<span>' . e((string) $s['label']) . '</span>'
                . (!adm_section_ready($key) ? '<small class="adm-nav__soon">قريبًا</small>' : '') . '</a></li>';
        }
        if ($items === '') {
            continue;
        }
        $navHtml .= '<div class="adm-nav__group">' . ($gLabel !== '' ? '<p class="adm-nav__title">' . e($gLabel) . '</p>' : '')
            . '<ul role="list">' . $items . '</ul></div>';
    }
    $roles = adm_roles();
    $userHtml = '';
    if ($user !== null) {
        $userHtml = '<div class="adm-user">'
            . '<span class="adm-user__avatar" aria-hidden="true">' . e(bm_substr(adm_user_label($user), 0, 1)) . '</span>'
            . '<span class="adm-user__text"><strong>' . e(adm_user_label($user)) . '</strong><small>' . e($roles[$user['role']] ?? '') . '</small></span>'
            . '</div>';
    }
    $siteLink = '<a class="adm-btn adm-btn--ghost adm-btn--sm adm-top__site" href="' . e(adm_public_url('index.html')) . '" target="_blank" rel="noopener">' . adm_icon('external-link') . '<span>عرض الموقع</span></a>';
    $accountLink = '<a class="adm-btn adm-btn--ghost adm-btn--sm' . ($section === 'account' ? ' is-current' : '') . '" href="' . e(adm_url('account')) . '">' . adm_icon('key-round') . '<span>حسابي</span></a>';

    echo adm_head($title);
    echo '<body class="adm adm-p-' . e($section) . (AdmPage::$wide ? ' adm-wide' : '') . '">';
    echo '<a class="adm-skip" href="#main">تخطَّ إلى المحتوى</a>';
    echo '<header class="adm-top"><a class="adm-top__home" href="' . e(adm_url('dashboard')) . '" aria-label="لوحة التحكم">' . adm_brand() . '</a>';
    echo '<div class="adm-top__end">' . $siteLink . '<span class="adm-top__desk">' . $userHtml . $accountLink . adm_logout_form() . '</span>'
        . '<button type="button" class="adm-menu-btn" aria-controls="adm-nav" aria-expanded="false" data-menu>' . adm_icon('menu') . '<span>القائمة</span></button></div>';
    echo '</header>';
    echo '<div class="adm-shell">';
    echo '<nav class="adm-nav" id="adm-nav" aria-label="أقسام لوحة الإدارة">' . $navHtml
        . '<div class="adm-nav__me">' . $userHtml . '<div class="adm-nav__me-actions">' . $accountLink . adm_logout_form() . '</div></div></nav>';
    echo '<main class="adm-main" id="main" tabindex="-1">';
    echo '<div class="adm-pagehead"><div class="adm-pagehead__text"><h1>' . e($title) . '</h1>'
        . (AdmPage::$lead !== '' ? '<p class="adm-lead">' . e(AdmPage::$lead) . '</p>' : '') . '</div>'
        . (AdmPage::$actions !== '' ? '<div class="adm-pagehead__actions">' . AdmPage::$actions . '</div>' : '') . '</div>';
    echo adm_flash_html();
    echo $body;
    echo '</main></div>';
    echo '<footer class="adm-foot"><p>لوحة إدارة موقع مؤسسة بيوت مصر لدعم الأرامل والأيتام · الإصدار ' . e(BM_VERSION) . '</p></footer>';
    echo '</body></html>';
}

function adm_layout_plain(string $body, string $title): void
{
    echo adm_head($title);
    echo '<body class="adm adm-plain"><main class="adm-plain__main" id="main">';
    echo '<div class="adm-plain__brand">' . adm_brand() . '</div>';
    echo '<div class="adm-plain__card">' . adm_flash_html() . $body . '</div>';
    echo '<p class="adm-plain__site"><a href="' . e(adm_public_url('index.html')) . '">' . adm_icon('arrow-right') . '<span>العودة إلى الموقع</span></a></p>';
    echo '</main></body></html>';
}

/* ------------------------------------------------------------------------ */
/* Components                                                                */
/* ------------------------------------------------------------------------ */

function adm_card(string $title, string $body, array $o = []): string
{
    $level = (int) ($o['level'] ?? 2);
    $id = (string) ($o['id'] ?? '');
    $head = '';
    if ($title !== '') {
        $head = '<div class="adm-card__head"><h' . $level . ' class="adm-card__title"' . ($id !== '' ? ' id="' . e($id) . '-title"' : '') . '>'
            . (!empty($o['icon']) ? adm_icon((string) $o['icon']) : '') . '<span>' . e($title) . '</span></h' . $level . '>'
            . (!empty($o['actions']) ? '<div class="adm-card__actions">' . $o['actions'] . '</div>' : '') . '</div>';
    }
    $tag = $title !== '' && $id !== '' ? 'section' : 'div';
    return '<' . $tag . ' class="adm-card ' . e((string) ($o['class'] ?? '')) . '"' . ($id !== '' ? ' id="' . e($id) . '"' : '')
        . ($tag === 'section' ? ' aria-labelledby="' . e($id) . '-title"' : '') . '>'
        . $head . '<div class="adm-card__body">' . $body . '</div></' . $tag . '>';
}

function adm_badge(string $text, string $variant = 'neutral'): string
{
    return '<span class="adm-badge adm-badge--' . e($variant) . '">' . e($text) . '</span>';
}

function adm_alert(string $text, string $variant = 'info', string $title = ''): string
{
    $icons = ['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'triangle-alert', 'info' => 'info'];
    return '<div class="adm-alert adm-alert--' . e($variant) . '"' . ($variant === 'error' ? ' role="alert"' : '') . '>' . adm_icon($icons[$variant] ?? 'info')
        . '<div>' . ($title !== '' ? '<strong>' . e($title) . '</strong>' : '') . '<p>' . e($text) . '</p></div></div>';
}

function adm_empty(string $iconName, string $title, string $text = ''): string
{
    return '<div class="adm-empty">' . adm_icon($iconName) . '<p class="adm-empty__title">' . e($title) . '</p>'
        . ($text !== '' ? '<p class="adm-empty__text">' . e($text) . '</p>' : '') . '</div>';
}

/* ------------------------------------------------------------------------ */
/* Dates                                                                     */
/* ------------------------------------------------------------------------ */

function adm_date(int $ts): string
{
    return $ts > 0 ? (int) date('j', $ts) . ' ' . ar_month((int) date('n', $ts)) . ' ' . date('Y', $ts) : '';
}

function adm_time(int $ts): string
{
    if ($ts <= 0) {
        return '';
    }
    return adm_date($ts) . '، ' . date('g:i', $ts) . ' ' . (date('A', $ts) === 'AM' ? 'ص' : 'م');
}

function adm_plural(int $n, string $one, string $two, string $few, string $many): string
{
    if ($n === 1) {
        return $one;
    }
    if ($n === 2) {
        return $two;
    }
    if ($n >= 3 && $n <= 10) {
        return $n . ' ' . $few;
    }
    return $n . ' ' . $many;
}

function adm_ago(int $ts): string
{
    if ($ts <= 0) {
        return '';
    }
    $d = time() - $ts;
    if ($d < 60) {
        return 'منذ لحظات';
    }
    if ($d < 3600) {
        return 'منذ ' . adm_plural(intdiv($d, 60), 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقة');
    }
    if ($d < 86400) {
        return 'منذ ' . adm_plural(intdiv($d, 3600), 'ساعة', 'ساعتين', 'ساعات', 'ساعة');
    }
    if ($d < 172800) {
        return 'أمس';
    }
    return adm_date($ts);
}

/** ISO date/time string → timestamp (0 when empty/invalid). */
function adm_ts(string $iso): int
{
    $t = $iso !== '' ? strtotime($iso) : false;
    return $t === false ? 0 : $t;
}
