<?php
/**
 * Core helpers: escaping, URLs, texts, JSON storage, icons, formatting.
 * Written for PHP 7.4+ (shared hosting may not run PHP 8).
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/** Current page being rendered (set by the build). */
final class BM
{
    /** @var array<string,mixed> */
    public static $page = ['path' => 'index.html'];
    /** @var string[] keys requested from texts.json that do not exist */
    public static $missingTexts = [];
}

/** Escape for HTML text and attribute context. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Length of a UTF-8 string, with a fallback when mbstring is missing. */
function bm_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

/** Substring of a UTF-8 string, with a fallback when mbstring is missing. */
function bm_substr(string $s, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, $start, $length, 'UTF-8');
    }
    preg_match_all('/./us', $s, $m);
    return implode('', array_slice($m[0], $start, $length));
}

/**
 * URL from the current page to a site path given relative to the site root
 * ("about.html", "assets/css/site.css"). Relative links keep the site working
 * from any folder and when opened straight from disk.
 */
function u(string $target = 'index.html'): string
{
    if (preg_match('#^(https?:|mailto:|tel:|\#)#i', $target)) {
        return $target;
    }
    $target = ltrim($target, '/');
    if (!empty(BM::$page['absolute'])) {
        return '/' . $target;
    }
    $depth = substr_count((string) BM::$page['path'], '/');
    return str_repeat('../', $depth) . $target;
}

/** Asset URL with a content hash so browsers pick up changes after a rebuild. */
function asset(string $file): string
{
    $rel = 'assets/' . ltrim($file, '/');
    $abs = BM_PUBLIC . '/' . $rel;
    $v = is_file($abs) ? substr(md5_file($abs), 0, 8) : BM_VERSION;
    return u($rel) . '?v=' . $v;
}

/** URL for an uploaded file stored in content data as "uploads/…". */
function upload_url(string $path): string
{
    return u($path);
}

/** Absolute URL using settings.site_url, or '' when the domain is not configured. */
function absolute_url(string $path = ''): string
{
    $base = rtrim((string) (bm_settings()['site_url'] ?? ''), '/');
    if ($base === '') {
        return '';
    }
    $path = ltrim($path, '/');
    if ($path === 'index.html') {
        $path = '';
    }
    return $base . '/' . $path;
}

/* ------------------------------------------------------------------------ */
/* JSON storage                                                              */
/* ------------------------------------------------------------------------ */

/** @return mixed */
function bm_json_read(string $file, $default = [])
{
    if (!is_file($file)) {
        return $default;
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON in ' . basename($file) . ': ' . json_last_error_msg());
    }
    return $data;
}

/** Write JSON atomically (temp file + rename) so a failed save never truncates data. */
function bm_json_write(string $file, $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Could not encode JSON for ' . basename($file));
    }
    bm_write_file($file, $json . "\n");
}

/** Atomic file write. */
function bm_write_file(string $file, string $contents): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create folder: ' . $dir);
    }
    $tmp = $dir . '/.' . basename($file) . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write file (check folder permissions): ' . $file);
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot replace file (check permissions): ' . $file);
    }
}

/** Load a content collection from app/content/<name>.json (cached per request). */
function bm_content(string $name)
{
    if (!array_key_exists($name, $GLOBALS['bm_content_cache'] ?? [])) {
        $GLOBALS['bm_content_cache'][$name] = bm_json_read(BM_CONTENT . '/' . $name . '.json', []);
    }
    return $GLOBALS['bm_content_cache'][$name];
}

function bm_content_reset(): void
{
    $GLOBALS['bm_content_cache'] = [];
}

/* ------------------------------------------------------------------------ */
/* Texts                                                                     */
/* ------------------------------------------------------------------------ */

/**
 * Look up an editable text by dotted key in content/texts.json.
 * Strings get {placeholders} filled from settings (phone numbers, registration…).
 *
 * @return mixed string or array
 */
function t(string $key, array $vars = [])
{
    $node = bm_content('texts');
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            BM::$missingTexts[$key] = true;
            return '';
        }
        $node = $node[$part];
    }
    if (is_string($node)) {
        return bm_fill($node, $vars);
    }
    return $node;
}

/** Escaped text, for direct output. */
function te(string $key, array $vars = []): string
{
    $v = t($key, $vars);
    return e(is_string($v) ? $v : '');
}

/** Text as a list (arrays stay arrays; a string becomes a one-item list). */
function tl(string $key): array
{
    $v = t($key);
    if (is_array($v)) {
        return array_values(array_filter($v, function ($x) {
            return is_array($x) || trim((string) $x) !== '';
        }));
    }
    return $v === '' ? [] : [$v];
}

/** Replace {name} placeholders from settings-derived variables. */
function bm_fill(string $text, array $vars = []): string
{
    if (strpos($text, '{') === false) {
        return $text;
    }
    $vars = $vars + bm_text_vars();
    return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars) {
        return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
    }, $text);
}

/** Variables available to every text. */
function bm_text_vars(): array
{
    $s = bm_settings();
    return [
        'hotline' => $s['contact']['hotline'] ?? '',
        'whatsapp_general' => $s['contact']['whatsapp_general'] ?? '',
        'whatsapp_legal' => $s['contact']['whatsapp_legal'] ?? '',
        'registration_number' => $s['legal']['registration_number'] ?? '',
        'registration_year' => $s['legal']['registration_year'] ?? '',
        'year' => date('Y'),
    ];
}

/** Paragraphs from a string (blank-line separated) or a list. */
function paragraphs($value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('strval', $value), 'strlen'));
    }
    $parts = preg_split('/\R\s*\R/u', trim((string) $value)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), 'strlen'));
}

/** Render paragraphs as <p> elements (escaped; single newlines become <br>). */
function p_html($value, string $class = ''): string
{
    $out = '';
    foreach (paragraphs($value) as $p) {
        $out .= '<p' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . nl2br(e($p), false) . '</p>';
    }
    return $out;
}

/* ------------------------------------------------------------------------ */
/* Icons                                                                     */
/* ------------------------------------------------------------------------ */

/** Inline SVG icon (decorative by default). */
function icon(string $name, string $class = '', string $label = ''): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = require BM_APP_DIR . '/lib/icons-data.php';
    }
    if (!isset($icons[$name])) {
        return '';
    }
    [$type, $body] = $icons[$name];
    $paint = $type === 'f'
        ? 'fill="currentColor"'
        : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    $a11y = $label !== ''
        ? 'role="img" aria-label="' . e($label) . '"'
        : 'aria-hidden="true" focusable="false"';
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" width="24" height="24" '
        . $paint . ' ' . $a11y . '>' . $body . '</svg>';
}

/* ------------------------------------------------------------------------ */
/* Phones, dates, numbers                                                    */
/* ------------------------------------------------------------------------ */

function digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

/** Egyptian local number (01xxxxxxxxx) to international digits (201xxxxxxxxx). */
function intl_phone(string $local): string
{
    $d = digits($local);
    if ($d === '') {
        return '';
    }
    if (strpos($d, '00') === 0) {
        return substr($d, 2);
    }
    if (strpos($d, '20') === 0 && strlen($d) === 12) {
        return $d;
    }
    if ($d[0] === '0') {
        return '20' . substr($d, 1);
    }
    return $d;
}

function tel_href(string $local): string
{
    $i = intl_phone($local);
    return $i === '' ? '' : 'tel:+' . $i;
}

function wa_href(string $local, string $message = ''): string
{
    $i = intl_phone($local);
    if ($i === '') {
        return '';
    }
    return 'https://wa.me/' . $i . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/** Phone number for display, isolated as LTR inside Arabic text. */
function phone_html(string $local): string
{
    return '<bdi class="num" dir="ltr">' . e($local) . '</bdi>';
}

function ar_month(int $m): string
{
    $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    return $months[max(1, min(12, $m)) - 1];
}

/** "2026-10-23" → "23 أكتوبر 2026"; "2026-10" → "أكتوبر 2026"; other strings pass through. */
function ar_date(?string $iso): string
{
    $iso = trim((string) $iso);
    if ($iso === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return (int) $m[3] . ' ' . ar_month((int) $m[2]) . ' ' . $m[1];
    }
    if (preg_match('/^(\d{4})-(\d{2})$/', $iso, $m)) {
        return ar_month((int) $m[2]) . ' ' . $m[1];
    }
    return $iso;
}

/** Thousands-separated number; non-numeric values are shown as given. */
function fmt_number($value): string
{
    if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value))) {
        return number_format((float) $value, (float) $value == (int) $value ? 0 : 1, '.', ',');
    }
    return (string) $value;
}

/** Make an ASCII slug for file names; falls back to a short hash for Arabic-only input. */
function slugify(string $s): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '', '-'));
    return $slug !== '' ? substr($slug, 0, 60) : substr(md5($s), 0, 10);
}

/** Shorten text for cards. */
function excerpt(string $text, int $chars = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (bm_strlen($text) <= $chars) {
        return $text;
    }
    $cut = bm_substr($text, 0, $chars);
    $space = strrpos($cut, ' ');
    return rtrim($space !== false ? substr($cut, 0, $space) : $cut, '،,.؛ ') . '…';
}

/** Build an HTML attribute string from an array. */
function attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === false) {
            continue;
        }
        $out .= ' ' . e((string) $k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    }
    return $out;
}
