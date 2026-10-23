<?php
/**
 * Shared helpers for the admin HTTP tests (http-test.php, http-content-test.php):
 * check()/section() reporting, a cookie-jar Browser that tracks the CSRF token, flash_text().
 * Expects $WORK (scratch folder next to the sandbox) to be set by the caller.
 */
declare(strict_types=1);

@mkdir($WORK, 0755, true);
$pass = 0;
$fail = 0;
function check(bool $cond, string $name, string $info = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ok   $name\n";
    } else {
        $fail++;
        echo "  FAIL $name" . ($info !== '' ? " — " . substr($info, 0, 400) : '') . "\n";
    }
}
function section(string $t): void
{
    echo "\n$t\n";
}
/** Content files are guarded JSON: a "<?php exit; ?>" first line, then the JSON. */
function strip_guard(string $raw): string
{
    if (strncmp($raw, '<?php', 5) === 0) {
        $raw = (string) substr($raw, (int) strpos($raw, "\n") + 1);
    }
    return $raw;
}
function json_file(string $f)
{
    return json_decode(strip_guard((string) file_get_contents($f)), true);
}
/** Flatten nested arrays for multipart bodies: ['a' => ['b' => 1]] → ['a[b]' => 1]. */
function flatten(array $a, string $prefix = ''): array
{
    $out = [];
    foreach ($a as $k => $v) {
        $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
        if (is_array($v)) {
            $out += flatten($v, $key);
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

final class Browser
{
    /** @var string */
    public $jar;
    /** @var array */
    public $last = ['status' => 0, 'headers' => [], 'body' => '', 'location' => ''];
    /** @var string */
    private $base;

    public function __construct(string $base)
    {
        $this->base = $base;
        $this->jar = (string) tempnam($GLOBALS['WORK'], 'jar');
    }

    public function request(string $method, string $path, $fields = null, array $headers = []): array
    {
        $url = preg_match('#^https?://#', $path) ? $path : $this->base . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($fields) && $this->hasFile($fields) ? flatten($fields) : (is_array($fields) ? http_build_query($fields) : (string) $fields));
        }
        $raw = (string) curl_exec($ch);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $headerText = substr($raw, 0, $hs);
        $hdrs = [];
        foreach (preg_split('/\r\n/', $headerText) ?: [] as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))][] = trim($v);
            }
        }
        $this->last = [
            'status' => $status,
            'headers' => $hdrs,
            'body' => (string) substr($raw, $hs),
            'location' => $hdrs['location'][0] ?? '',
        ];
        $this->csrf();
        clearstatcache(); // the server changes files behind our back
        return $this->last;
    }

    private function hasFile(array $a): bool
    {
        foreach ($a as $v) {
            if ($v instanceof CURLFile || (is_array($v) && $this->hasFile($v))) {
                return true;
            }
        }
        return false;
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST with the CSRF token from the last page (or $csrf). */
    public function post(string $path, array $fields, ?string $csrf = null, array $headers = []): array
    {
        $fields['_csrf'] = $csrf ?? $this->csrf();
        return $this->request('POST', $path, $fields, $headers);
    }

    /** @var string latest CSRF token seen (it stays the same for the whole session) */
    private $token = '';

    public function csrf(): string
    {
        if (preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->last['body'], $m)) {
            $this->token = $m[1];
        }
        return $this->token;
    }

    /** Follow one redirect (Location is relative to /admin/). */
    public function follow(): array
    {
        $loc = $this->last['location'];
        if ($loc === '') {
            return $this->last;
        }
        return $this->get(preg_match('#^https?://|^/#', $loc) ? $loc : '/admin/' . $loc);
    }

    public function cookie(): string
    {
        $c = (string) @file_get_contents($this->jar);
        return preg_match('/bm_admin\s+(\S+)\s*$/m', $c, $m) ? $m[1] : '';
    }
}

function flash_text(string $html): string
{
    preg_match_all('/<div class="adm-alert adm-alert--(\w+)"[^>]*>(?:(?!<div class="adm-privacy").)*?<p>(.*?)<\/p>/su', $html, $m, PREG_SET_ORDER);
    preg_match_all('/<p class="adm-error"[^>]*>.*?<span>(.*?)<\/span>/su', $html, $m2);
    foreach ($m2[1] as $x) {
        $m[] = ['', 'field', $x];
    }
    $out = [];
    foreach ($m as $x) {
        $out[] = $x[1] . ': ' . html_entity_decode(strip_tags($x[2]));
    }
    return implode(' | ', $out);
}
