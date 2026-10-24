<?php
/**
 * Admin security: response headers, hardened sessions, CSRF, login rate limiting.
 *
 *   adm_send_headers(): void                 CSP, X-Frame-Options, nosniff, Referrer-Policy, no-store…
 *   adm_is_https(): bool
 *   adm_session_start(): void                custom name, strict mode, HttpOnly + SameSite=Strict (+Secure on HTTPS),
 *                                            sessions stored as guarded .php files in app/storage/sessions
 *   adm_session_login(array $user): void     regenerate id, bind user + password version, new CSRF token
 *   adm_session_end(): void                  destroy session + cookie
 *   adm_session_user(): ?array               current user, enforcing idle (30 min) and absolute (8 h) timeouts
 *   adm_csrf_token(): string / adm_csrf_valid(): bool
 *   adm_origin_ok(): bool                    same-origin check for POSTs (when the browser sends Origin/Referer)
 *   adm_post_too_large(): bool               detects uploads larger than post_max_size (PHP drops $_POST)
 *   adm_rl_keys(string $username): array     rate-limit keys (HMAC of IP and of username)
 *   adm_rl_locked(array $keys): bool / adm_rl_fail(array $keys): void / adm_rl_clear(array $keys): void
 *   adm_client_hash(): string                short HMAC of the client IP for the audit log (no raw IPs stored)
 */
declare(strict_types=1);
defined('BM_APP') || exit;

const ADM_SESSION_NAME = 'bm_admin';
const ADM_IDLE_TIMEOUT = 1800;      // 30 minutes
const ADM_ABSOLUTE_TIMEOUT = 28800; // 8 hours
const ADM_RL_MAX = 5;               // failures…
const ADM_RL_WINDOW = 900;          // …within 15 minutes
const ADM_RL_LOCK = 900;            // → locked for 15 minutes

/* ------------------------------------------------------------------------ */
/* Headers                                                                   */
/* ------------------------------------------------------------------------ */

function adm_send_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (adm_is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

function adm_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

function adm_is_local(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

/* ------------------------------------------------------------------------ */
/* Sessions                                                                  */
/* ------------------------------------------------------------------------ */

/**
 * Session files live in app/storage/sessions as "<sha256(id)>.php" behind an
 * exit guard: not readable over HTTP and a directory listing never reveals ids.
 */
final class AdmSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** @var string */
    private $dir;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    private function file($id): string
    {
        return $this->dir . '/' . hash('sha256', (string) $id) . '.php';
    }

    private static function validId($id): bool
    {
        return is_string($id) && preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $id) === 1;
    }

    public function open($path, $name): bool
    {
        return is_dir($this->dir);
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        if (!self::validId($id)) {
            return '';
        }
        $f = $this->file($id);
        if (!is_file($f)) {
            return '';
        }
        if (filemtime($f) < time() - ADM_ABSOLUTE_TIMEOUT - 60) {
            @unlink($f);
            return '';
        }
        $raw = @file_get_contents($f);
        return $raw === false ? '' : adm_strip_guard($raw);
    }

    public function write($id, $data): bool
    {
        if (!self::validId($id)) {
            return false;
        }
        try {
            adm_write_protected($this->file($id), ADM_GUARD . (string) $data);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function destroy($id): bool
    {
        if (self::validId($id)) {
            $f = $this->file($id);
            if (is_file($f)) {
                @unlink($f);
            }
        }
        return true;
    }

    public function gc($max_lifetime): int
    {
        $n = 0;
        $limit = time() - max((int) $max_lifetime, ADM_ABSOLUTE_TIMEOUT);
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            if (@filemtime($f) < $limit && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    public function validateId($id): bool
    {
        return self::validId($id) && is_file($this->file($id));
    }

    public function updateTimestamp($id, $data): bool
    {
        if (!self::validId($id)) {
            return false;
        }
        $f = $this->file($id);
        return is_file($f) ? @touch($f) : $this->write($id, $data);
    }
}

/** Cookie path = the admin folder ("/admin/"), so the cookie never goes to public pages. */
function adm_cookie_path(): string
{
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')));
    $dir = rtrim($dir, '/');
    return ($dir === '' ? '' : $dir) . '/';
}

function adm_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Given to session_start() instead of ini_set(): this still works where the host disables
    // ini_set() (since PHP 8 a disabled function is a fatal "undefined function", even with "@").
    $options = [
        'use_strict_mode' => '1',
        'use_cookies' => '1',
        'use_only_cookies' => '1',
        'use_trans_sid' => '0',
        'cookie_httponly' => '1',
        'gc_maxlifetime' => (string) ADM_ABSOLUTE_TIMEOUT,
        'gc_probability' => '1',
        'gc_divisor' => '50',
    ];
    session_cache_limiter('');
    session_name(ADM_SESSION_NAME);

    $handlerOk = false;
    try {
        $dir = adm_storage_dir('sessions');
        if (is_writable($dir)) {
            session_set_save_handler(new AdmSessionHandler($dir), true);
            $handlerOk = true;
        }
    } catch (Throwable $e) {
        $handlerOk = false;
    }
    $GLOBALS['adm_session_fallback'] = !$handlerOk;

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => adm_cookie_path(),
        'secure' => adm_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!@session_start($options)) {
        throw new RuntimeException('Could not start the admin session.');
    }
}

/** Log a user in: fresh session id, bound to the user and their password version. */
function adm_session_login(array $user): void
{
    session_regenerate_id(true);
    $now = time();
    $_SESSION = [
        'adm' => [
            'uid' => $user['username'],
            'pwv' => (int) ($user['pwv'] ?? 1),
            'created' => $now,
            'last' => $now,
        ],
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

function adm_session_end(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        session_destroy();
    }
}

/**
 * The logged-in user, or null. Ends the session on idle/absolute timeout, when
 * the account was deleted, or when its password was changed elsewhere.
 * Sets $GLOBALS['adm_session_expired'] = true on timeout.
 */
function adm_session_user(): ?array
{
    $s = $_SESSION['adm'] ?? null;
    if (!is_array($s) || empty($s['uid'])) {
        return null;
    }
    $now = time();
    if ($now - (int) ($s['last'] ?? 0) > ADM_IDLE_TIMEOUT || $now - (int) ($s['created'] ?? 0) > ADM_ABSOLUTE_TIMEOUT) {
        adm_session_end();
        adm_session_start();
        $GLOBALS['adm_session_expired'] = true;
        return null;
    }
    $user = adm_user_get((string) $s['uid']);
    if ($user === null || (int) ($user['pwv'] ?? 1) !== (int) ($s['pwv'] ?? 0)) {
        adm_session_end();
        adm_session_start();
        $GLOBALS['adm_session_expired'] = true;
        return null;
    }
    $_SESSION['adm']['last'] = $now;
    return $user;
}

/* ------------------------------------------------------------------------ */
/* CSRF & origin                                                             */
/* ------------------------------------------------------------------------ */

function adm_csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function adm_csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    $token = $_SESSION['csrf'] ?? '';
    return is_string($sent) && is_string($token) && $token !== '' && hash_equals($token, $sent);
}

/** Reject cross-site POSTs when the browser tells us where the request came from. */
function adm_origin_ok(): bool
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '' || $origin === 'null') {
        $origin = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($origin === '') {
            return true; // Some privacy tools strip both; the CSRF token still protects us.
        }
    }
    $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $port = parse_url($origin, PHP_URL_PORT);
    if ($host === '') {
        return false;
    }
    $sent = $host . ($port ? ':' . $port : '');
    // Same host as this request, or the site address set in «الإعدادات» (for hosting behind a proxy
    // that rewrites Host). X-Forwarded-Host is sent by the client, so it is never trusted here.
    $candidates = [(string) ($_SERVER['HTTP_HOST'] ?? ''), (string) ($_SERVER['SERVER_NAME'] ?? '')];
    try {
        $candidates[] = (string) parse_url((string) (bm_settings()['site_url'] ?? ''), PHP_URL_HOST);
    } catch (Throwable $e) {
        // Unreadable settings: the request's own host is still accepted.
    }
    $allowed = [];
    foreach ($candidates as $h) {
        $h = strtolower(trim($h));
        if ($h !== '') {
            $allowed[] = $h;
            $allowed[] = preg_replace('/:(80|443)$/', '', $h);
        }
    }
    return in_array($sent, $allowed, true) || in_array($host, $allowed, true);
}

/** True when the request body exceeded post_max_size (PHP then empties $_POST and $_FILES). */
function adm_post_too_large(): bool
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $len > 0 && empty($_POST) && empty($_FILES) && $len > adm_ini_bytes((string) ini_get('post_max_size'));
}

function adm_ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g':
            $n *= 1024;
            // no break
        case 'm':
            $n *= 1024;
            // no break
        case 'k':
            $n *= 1024;
    }
    return $n;
}

/* ------------------------------------------------------------------------ */
/* Login rate limiting                                                       */
/* ------------------------------------------------------------------------ */

function adm_client_ip(): string
{
    // REMOTE_ADDR only: forwarded headers are set by the client and can be forged.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function adm_client_hash(): string
{
    return substr(hash_hmac('sha256', adm_client_ip(), adm_secret()), 0, 12);
}

function adm_rl_keys(string $username): array
{
    $keys = ['ip:' . substr(hash_hmac('sha256', adm_client_ip(), adm_secret()), 0, 32)];
    $username = strtolower(trim($username));
    if ($username !== '') {
        $keys[] = 'u:' . substr(hash_hmac('sha256', $username, adm_secret()), 0, 32);
    }
    return $keys;
}

function adm_rl_file(): string
{
    return adm_storage('ratelimit.php');
}

function adm_rl_locked(array $keys): bool
{
    $data = (array) adm_store_json_read(adm_rl_file(), []);
    $now = time();
    foreach ($keys as $k) {
        if ((int) ($data[$k]['until'] ?? 0) > $now) {
            return true;
        }
    }
    return false;
}

function adm_rl_fail(array $keys): void
{
    adm_with_lock('ratelimit', function () use ($keys) {
        $data = (array) adm_store_json_read(adm_rl_file(), []);
        $now = time();
        foreach ($keys as $k) {
            $e = is_array($data[$k] ?? null) ? $data[$k] : ['n' => 0, 'first' => $now, 'until' => 0];
            if ($now - (int) $e['first'] > ADM_RL_WINDOW) {
                $e = ['n' => 0, 'first' => $now, 'until' => 0];
            }
            $e['n'] = (int) $e['n'] + 1;
            if ($e['n'] >= ADM_RL_MAX) {
                $e = ['n' => 0, 'first' => $now, 'until' => $now + ADM_RL_LOCK];
            }
            $data[$k] = $e;
        }
        foreach ($data as $k => $e) {
            if (!is_array($e) || ((int) ($e['until'] ?? 0) < $now && $now - (int) ($e['first'] ?? 0) > ADM_RL_WINDOW)) {
                unset($data[$k]);
            }
        }
        adm_store_json_write(adm_rl_file(), $data);
    });
}

function adm_rl_clear(array $keys): void
{
    adm_with_lock('ratelimit', function () use ($keys) {
        $data = (array) adm_store_json_read(adm_rl_file(), []);
        $changed = false;
        foreach ($keys as $k) {
            if (isset($data[$k]) && (int) ($data[$k]['until'] ?? 0) <= time()) {
                unset($data[$k]);
                $changed = true;
            }
        }
        if ($changed) {
            adm_store_json_write(adm_rl_file(), $data);
        }
    });
}
