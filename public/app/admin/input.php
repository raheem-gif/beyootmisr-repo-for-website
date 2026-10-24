<?php
/**
 * Input cleaning and validation. Every value that reaches content JSON passes
 * through here: valid UTF-8, no control characters, length limits, whitelists.
 *
 * Plain functions (return '' / null for invalid input):
 *   adm_clean(string $s, bool $multiline = false): string   strip control chars, normalise newlines, trim
 *   adm_digits_latin(string $s): string                     ٠١٢٣ / ۰۱۲۳ → 0123
 *   adm_clean_url(string $s, bool $httpsOnly = false): string   http(s) (or https only); non-ASCII is percent-encoded; '' if invalid
 *   adm_clean_email(string $s): string
 *   adm_clean_phone(string $s): string                      digits, spaces, + - ( ); 6–15 digits
 *   adm_clean_slug(string $s, int $max = 60): string        a-z 0-9 - (ids used in URLs)
 *   adm_clean_date(string $s, bool $allowMonth = false): string   YYYY-MM-DD (or YYYY-MM); '' if invalid
 *   adm_lines(string $s, int $maxItems, int $maxLen): array one item per line, empties dropped
 *   adm_post(string $path, $default = null)                 read $_POST by dotted path ("contact.email")
 *   adm_path_get(array $a, string $path, $default) / adm_path_set(array &$a, string $path, $value)
 *
 * Validator (collects Arabic error messages keyed by dotted path):
 *   $in = new AdmInput($_POST);
 *   $title = $in->text('title', 'العنوان', ['max' => 120, 'required' => true]);
 *   $body  = $in->text('body', 'النص', ['max' => 5000, 'multiline' => true]);
 *   $link  = $in->url('link', 'الرابط');            $mail = $in->email('email', 'البريد');
 *   $pay   = $in->url('link', 'رابط التبرع', ['https_only' => true, 'https_error' => '…']);  // http:// refused
 *   $tel   = $in->phone('hotline', 'الخط الساخن');  $year = $in->year('year', 'السنة');
 *   $date  = $in->date('updated_at', 'التاريخ', ['month' => true]);
 *   $n     = $in->number('value', 'الرقم', ['min' => 0]);   // int|float|null
 *   $st    = $in->enum('status', 'الحالة', ['ongoing', 'done'], 'ongoing');
 *   $slug  = $in->slug('activity', 'النشاط', array_keys(bm_activity_registry()));  // whitelist
 *   $pub   = $in->bool('published');
 *   $list  = $in->lines('assistance', 'أنواع المساعدات', ['max_items' => 30, 'max' => 300]);
 *   if (!$in->ok()) { $errors = $in->errors(); }      // ['title' => 'رسالة', …]
 */
declare(strict_types=1);
defined('BM_APP') || exit;

function adm_digits_latin(string $s): string
{
    return strtr($s, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]);
}

function adm_valid_utf8(string $s): bool
{
    return $s === '' || preg_match('//u', $s) === 1;
}

/** Clean free text: valid UTF-8, no control characters, normalised newlines, trimmed. */
function adm_clean(string $s, bool $multiline = false): string
{
    if (!adm_valid_utf8($s)) {
        $s = function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8') : '';
        if (!adm_valid_utf8($s)) {
            return '';
        }
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = str_replace("\t", ' ', $s);
    // C0 (except \n), DEL, C1 controls, and the BOM.
    $s = (string) preg_replace('/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{FEFF}]/u', '', $s);
    if (!$multiline) {
        $s = (string) preg_replace('/\s*\n\s*/u', ' ', $s);
        $s = (string) preg_replace('/ {2,}/', ' ', $s);
        return trim($s);
    }
    $lines = array_map('rtrim', explode("\n", $s));
    $s = implode("\n", $lines);
    $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);
    return trim($s);
}

function adm_clean_url(string $s, bool $httpsOnly = false): string
{
    $s = trim(adm_clean($s));
    if ($s === '') {
        return '';
    }
    // Encode Arabic/other non-ASCII characters copied from the address bar.
    $s = (string) preg_replace_callback('/[^\x21-\x7E]/u', function ($m) {
        return rawurlencode($m[0]);
    }, $s);
    if (!preg_match($httpsOnly ? '#^https://#i' : '#^https?://#i', $s) || filter_var($s, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $host = (string) parse_url($s, PHP_URL_HOST);
    if ($host === '' || (strpos($host, '.') === false && $host !== 'localhost')) {
        return '';
    }
    return strlen($s) <= 500 ? $s : '';
}

function adm_clean_email(string $s): string
{
    $s = trim(adm_clean($s));
    if ($s === '' || strlen($s) > 120) {
        return '';
    }
    $v = filter_var($s, FILTER_VALIDATE_EMAIL);
    return is_string($v) ? $v : '';
}

function adm_clean_phone(string $s): string
{
    $s = trim(adm_digits_latin(adm_clean($s)));
    if ($s === '' || !preg_match('/^[0-9+\-\s()]{6,24}$/', $s)) {
        return '';
    }
    $d = strlen(digits($s));
    return ($d >= 6 && $d <= 15) ? (string) preg_replace('/\s+/', ' ', $s) : '';
}

function adm_clean_slug(string $s, int $max = 60): string
{
    $s = strtolower(trim($s));
    $s = (string) preg_replace('/[^a-z0-9-]+/', '-', $s);
    $s = trim((string) preg_replace('/-+/', '-', $s), '-');
    return substr($s, 0, $max);
}

function adm_clean_date(string $s, bool $allowMonth = false): string
{
    $s = trim(adm_digits_latin(adm_clean($s)));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $s;
    }
    if ($allowMonth && preg_match('/^(\d{4})-(\d{2})$/', $s, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
        return $s;
    }
    return '';
}

/** Split a textarea into a list: one item per line, trimmed, empties removed. */
function adm_lines(string $s, int $maxItems = 100, int $maxLen = 1000): array
{
    $out = [];
    foreach (explode("\n", adm_clean($s, true)) as $line) {
        $line = adm_clean($line);
        if ($line === '') {
            continue;
        }
        $out[] = bm_substr($line, 0, $maxLen);
        if (count($out) >= $maxItems) {
            break;
        }
    }
    return $out;
}

/** @return mixed */
function adm_path_get(array $src, string $path, $default = null)
{
    $node = $src;
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

/** Set a value in a nested array by dotted path (creates missing levels). */
function adm_path_set(array &$a, string $path, $value): void
{
    $node = &$a;
    foreach (explode('.', $path) as $part) {
        if (!isset($node[$part]) || !is_array($node[$part])) {
            $node[$part] = $node[$part] ?? [];
        }
        $node = &$node[$part];
    }
    $node = $value;
}

/** @return mixed */
function adm_post(string $path, $default = null)
{
    return adm_path_get($_POST, $path, $default);
}

/** Collects validated values and Arabic error messages. */
final class AdmInput
{
    /** @var array */
    private $src;
    /** @var array<string,string> */
    private $errors = [];

    public function __construct(array $src)
    {
        $this->src = $src;
    }

    /** Raw string at a dotted path ('' when missing or not a string). */
    public function raw(string $path): string
    {
        $v = adm_path_get($this->src, $path, '');
        return is_string($v) || is_int($v) || is_float($v) ? (string) $v : '';
    }

    /** @return mixed */
    public function get(string $path, $default = null)
    {
        return adm_path_get($this->src, $path, $default);
    }

    public function error(string $path, string $message): void
    {
        if (!isset($this->errors[$path])) {
            $this->errors[$path] = $message;
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function ok(): bool
    {
        return !$this->errors;
    }

    /** Options: max (default 300), min, required, multiline. */
    public function text(string $path, string $label, array $o = []): string
    {
        $raw = $this->raw($path);
        if (!adm_valid_utf8($raw)) {
            $this->error($path, '«' . $label . '»: يحتوي النص على رموز غير صالحة.');
            return '';
        }
        $v = adm_clean($raw, !empty($o['multiline']));
        $max = (int) ($o['max'] ?? 300);
        if ($v === '' && !empty($o['required'])) {
            $this->error($path, '«' . $label . '» مطلوب.');
        } elseif (bm_strlen($v) > $max) {
            $this->error($path, '«' . $label . '» طويل جدًا: الحد الأقصى ' . $max . ' حرف (الحالي ' . bm_strlen($v) . ').');
        } elseif ($v !== '' && isset($o['min']) && bm_strlen($v) < (int) $o['min']) {
            $this->error($path, '«' . $label . '» قصير جدًا: الحد الأدنى ' . (int) $o['min'] . ' أحرف.');
        }
        return $v;
    }

    /** Options: required, https_only (refuse http:// links), https_error (message for an http:// link). */
    public function url(string $path, string $label, array $o = []): string
    {
        $raw = trim($this->raw($path));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        $v = adm_clean_url($raw);
        if ($v === '') {
            $this->error($path, '«' . $label . '»: اكتب رابطًا كاملًا يبدأ بـ https:// (مثل https://www.facebook.com/…).');
        } elseif (!empty($o['https_only']) && adm_clean_url($v, true) === '') {
            $this->error($path, '«' . $label . '»: ' . (string) ($o['https_error'] ?? 'يُقبل الرابط الآمن فقط، أي الذي يبدأ بـ https:// وليس http://.'));
            return '';
        }
        return $v;
    }

    public function email(string $path, string $label, array $o = []): string
    {
        $raw = trim($this->raw($path));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        $v = adm_clean_email($raw);
        if ($v === '') {
            $this->error($path, '«' . $label . '»: البريد الإلكتروني غير صحيح (مثل info@example.org).');
        }
        return $v;
    }

    public function phone(string $path, string $label, array $o = []): string
    {
        $raw = trim($this->raw($path));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        $v = adm_clean_phone($raw);
        if ($v === '') {
            $this->error($path, '«' . $label . '»: رقم الهاتف غير صحيح؛ اكتب الأرقام فقط مثل 01211114662.');
        }
        return $v;
    }

    public function year(string $path, string $label, array $o = []): string
    {
        $raw = trim(adm_digits_latin($this->raw($path)));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        if (!preg_match('/^(19|20)\d{2}$/', $raw)) {
            $this->error($path, '«' . $label . '»: اكتب السنة بأربعة أرقام مثل 2020.');
            return '';
        }
        return $raw;
    }

    /** Options: month (allow YYYY-MM), required. */
    public function date(string $path, string $label, array $o = []): string
    {
        $raw = trim($this->raw($path));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        $v = adm_clean_date($raw, !empty($o['month']));
        if ($v === '') {
            $this->error($path, '«' . $label . '»: التاريخ غير صحيح' . (!empty($o['month']) ? ' (اكتب سنة-شهر مثل 2026-10 أو تاريخًا كاملًا).' : '.'));
        }
        return $v;
    }

    /**
     * Number (int or float), or null when empty. Options: min, max, required, int (integers only).
     * @return int|float|null
     */
    public function number(string $path, string $label, array $o = [])
    {
        $raw = str_replace([',', '٬', ' '], '', trim(adm_digits_latin($this->raw($path))));
        $raw = str_replace('٫', '.', $raw);
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return null;
        }
        $pattern = !empty($o['int']) ? '/^-?\d{1,12}$/' : '/^-?\d{1,12}(\.\d{1,4})?$/';
        if (!preg_match($pattern, $raw)) {
            $this->error($path, '«' . $label . '»: اكتب رقمًا صحيحًا' . (!empty($o['int']) ? ' بدون كسور.' : '.'));
            return null;
        }
        $n = strpos($raw, '.') !== false ? (float) $raw : (int) $raw;
        if (isset($o['min']) && $n < $o['min']) {
            $this->error($path, '«' . $label . '»: أقل قيمة مسموحة ' . $o['min'] . '.');
        }
        if (isset($o['max']) && $n > $o['max']) {
            $this->error($path, '«' . $label . '»: أكبر قيمة مسموحة ' . $o['max'] . '.');
        }
        return $n;
    }

    /** Value must be one of $allowed; otherwise $default (and an error when 'strict'). */
    public function enum(string $path, string $label, array $allowed, string $default = '', array $o = []): string
    {
        $raw = $this->raw($path);
        if (in_array($raw, $allowed, true)) {
            return $raw;
        }
        if ($raw !== '' || !empty($o['required'])) {
            $this->error($path, '«' . $label . '»: اختر قيمة من القائمة.');
        }
        return $default;
    }

    /** Slug (a-z0-9-). With $whitelist, it must be one of them (or '' when not required). */
    public function slug(string $path, string $label, array $whitelist = [], array $o = []): string
    {
        $raw = trim($this->raw($path));
        if ($raw === '') {
            if (!empty($o['required'])) {
                $this->error($path, '«' . $label . '» مطلوب.');
            }
            return '';
        }
        $v = adm_clean_slug($raw, (int) ($o['max'] ?? 60));
        if ($whitelist && !in_array($v, $whitelist, true)) {
            $this->error($path, '«' . $label . '»: اختر قيمة من القائمة.');
            return '';
        }
        if ($v === '') {
            $this->error($path, '«' . $label . '»: استخدم حروفًا إنجليزية صغيرة وأرقامًا وشرطة (-) فقط.');
        }
        return $v;
    }

    public function bool(string $path): bool
    {
        $v = adm_path_get($this->src, $path, '');
        return $v === '1' || $v === 'on' || $v === 1 || $v === true;
    }

    /** Textarea → list. Options: max_items (100), max (per item, 500), required. */
    public function lines(string $path, string $label, array $o = []): array
    {
        $raw = $this->raw($path);
        $maxItems = (int) ($o['max_items'] ?? 100);
        $max = (int) ($o['max'] ?? 500);
        $all = adm_lines($raw, 10000, 100000);
        if (count($all) > $maxItems) {
            $this->error($path, '«' . $label . '»: الحد الأقصى ' . $maxItems . ' عنصرًا.');
        }
        foreach ($all as $item) {
            if (bm_strlen($item) > $max) {
                $this->error($path, '«' . $label . '»: أحد الأسطر أطول من ' . $max . ' حرف.');
                break;
            }
        }
        if (!$all && !empty($o['required'])) {
            $this->error($path, '«' . $label . '» مطلوب.');
        }
        return array_slice($all, 0, $maxItems);
    }
}
