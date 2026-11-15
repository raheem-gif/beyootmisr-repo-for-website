<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tests the live server against what the app needs (section 3.1).
 * Each check returns ['key', 'label', 'status' => pass|fail|warn, 'detail'].
 */
class InstallCheck
{
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'openssl', 'sodium', 'curl', 'fileinfo', 'zip', 'bcmath', 'exif'];

    /** @return list<array{key: string, label: string, status: string, detail: string}> */
    public function run(bool $network = true): array
    {
        $checks = [];
        $add = function (string $key, string $label, string $status, string $detail) use (&$checks) {
            $checks[] = compact('key', 'label', 'status', 'detail');
        };

        $add('php', 'PHP 8.2 or later', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'fail', PHP_VERSION);

        foreach (self::EXTENSIONS as $ext) {
            $add("ext_$ext", "PHP extension $ext", extension_loaded($ext) ? 'pass' : 'fail', extension_loaded($ext) ? 'loaded' : 'missing');
        }
        $image = extension_loaded('imagick') ? 'imagick' : (extension_loaded('gd') ? 'gd' : null);
        $add('ext_image', 'PHP extension gd or imagick', $image ? 'pass' : 'fail', $image ?? 'missing');

        $add('memory_limit', 'Memory limit at least 256M', $this->bytes(ini_get('memory_limit')) >= 256 * 1024 ** 2 || ini_get('memory_limit') === '-1' ? 'pass' : 'warn', (string) ini_get('memory_limit'));
        $upload = min($this->bytes(ini_get('upload_max_filesize')), $this->bytes(ini_get('post_max_size')));
        $add('upload_limit', 'Upload limit at least 20M', $upload >= 20 * 1024 ** 2 ? 'pass' : 'fail', ini_get('upload_max_filesize').' / post '.ini_get('post_max_size'));
        $add('max_execution_time', 'Max execution time', (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 60 ? 'pass' : 'warn', ini_get('max_execution_time').' s');
        $add('proc', 'proc_open allowed (queue worker from cron)', function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions')))) ? 'pass' : 'warn', function_exists('proc_open') ? 'available' : 'disabled');

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $version = DB::selectOne($driver === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v')->v;
            $add('database', 'Database connection', 'pass', "$driver $version");
            if (in_array($driver, ['mysql', 'mariadb'])) {
                $charset = DB::selectOne('select @@character_set_database as c')->c;
                $add('utf8mb4', 'Database charset utf8mb4', $charset === 'utf8mb4' ? 'pass' : 'fail', $charset);
            }
            $add('migrations', 'Migrations applied', Schema::hasTable('audit_logs') ? 'pass' : 'fail', Schema::hasTable('audit_logs') ? 'tables present' : 'run migrations');
        } catch (Throwable $e) {
            $add('database', 'Database connection', 'fail', $e->getMessage());
        }

        foreach (['storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $add('writable_'.str_replace('/', '_', $dir), "$dir writable", is_writable($path) ? 'pass' : 'fail', $path);
        }

        $env = base_path('.env');
        $public = public_path();
        $add('env_outside_webroot', '.env outside the web root', ! str_starts_with($env, $public.DIRECTORY_SEPARATOR) ? 'pass' : 'fail', 'public: '.$public);

        // This page is reached through index.php without /index.php in the URL, so rewrites work.
        $add('rewrites', '.htaccess rewrites', str_contains((string) request()->server('REQUEST_URI'), 'index.php') ? 'fail' : 'pass', 'reached /install/check without index.php');

        $add('https', 'Served over HTTPS', request()->isSecure() ? 'pass' : (app()->isLocal() ? 'warn' : 'fail'), request()->isSecure() ? 'yes' : 'no');

        foreach (['scheduler' => 'Cron runs schedule:run every minute', 'queue' => 'Queue worker processed a job'] as $name => $label) {
            $beat = Schema::hasTable('system_heartbeats') ? DB::table('system_heartbeats')->where('name', $name)->value('beat_at') : null;
            $fresh = $beat && now()->diffInMinutes($beat, true) <= 3;
            $add("heartbeat_$name", $label, $fresh ? 'pass' : 'fail', $beat ? "last at $beat" : 'never');
        }

        if ($network) {
            $add('outbound_https', 'Outbound HTTPS calls', $this->outbound('https://www.cloudflare.com/cdn-cgi/trace') ? 'pass' : 'fail', 'cloudflare.com trace');
            $add('outbound_paymob', 'Reach Paymob (accept.paymob.com)', $this->outbound('https://accept.paymob.com') ? 'pass' : 'warn', 'HTTPS');
            $add('outbound_meta', 'Reach WhatsApp Cloud API (graph.facebook.com)', $this->outbound('https://graph.facebook.com') ? 'pass' : 'warn', 'HTTPS');
            $country = $this->serverCountry();
            $add('data_centre', 'Server country (PDPC transfer licence needed outside Egypt)', $country === 'EG' ? 'pass' : 'warn', $country ?? 'unknown');
        }

        $add('webhook_url', 'Public inbound webhook URL', str_starts_with((string) config('app.url'), 'https://') ? 'pass' : 'warn', config('app.url').'/webhooks/... (confirm with a test callback from Paymob)');

        $add('mail_api', 'Email over HTTPS API (Resend or Brevo)', in_array(config('mail.default'), ['resend', 'brevo', 'log', 'array']) ? (config('mail.default') === 'log' ? 'warn' : 'pass') : 'warn', 'MAIL_MAILER='.config('mail.default'));

        $add('otp_whatsapp', 'WhatsApp OTP keys', filled(config('esnad.whatsapp.token')) ? 'pass' : 'warn', filled(config('esnad.whatsapp.token')) ? 'set' : 'not set');
        $add('otp_sms', 'SMS fallback driver', config('esnad.sms.driver') === 'log' ? (app()->isProduction() ? 'fail' : 'warn') : 'pass', (string) config('esnad.sms.driver'));
        $add('turnstile', 'Cloudflare Turnstile keys', Turnstile::enabled() ? 'pass' : (app()->isProduction() ? 'fail' : 'warn'), Turnstile::enabled() ? 'set' : 'not set');

        $missing = array_keys(array_filter(config('esnad.operator'), fn ($v) => blank($v)));
        $add('operator_identity', 'Operator identity for the footer', $missing === [] ? 'pass' : (app()->isProduction() ? 'fail' : 'warn'), $missing === [] ? 'complete' : 'missing: '.implode(', ', $missing));

        if (Schema::hasTable('pages')) {
            $unreviewed = DB::table('pages')->where('kind', 'legal')->whereNull('counsel_reviewed_on')->pluck('slug')->all();
            $add('legal_reviewed', 'Legal pages reviewed by Egyptian counsel', $unreviewed === [] ? 'pass' : (app()->isProduction() ? 'fail' : 'warn'), $unreviewed === [] ? 'all reviewed' : 'not reviewed: '.implode(', ', $unreviewed));
        }

        $add('debug_off', 'APP_DEBUG off in production', app()->isProduction() && config('app.debug') ? 'fail' : 'pass', config('app.debug') ? 'debug on' : 'debug off');
        $add('hosting_mode', 'Hosting mode', in_array(config('esnad.hosting_mode'), ['shared', 'vps']) ? 'pass' : 'fail', (string) config('esnad.hosting_mode'));

        return $checks;
    }

    private function outbound(string $url): bool
    {
        try {
            return Http::timeout(6)->get($url)->status() < 500;
        } catch (Throwable) {
            return false;
        }
    }

    private function serverCountry(): ?string
    {
        try {
            $trace = Http::timeout(6)->get('https://www.cloudflare.com/cdn-cgi/trace')->body();
            if (preg_match('/^loc=([A-Z]{2})$/m', $trace, $m)) {
                return $m[1];
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function bytes(string|false $value): int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
