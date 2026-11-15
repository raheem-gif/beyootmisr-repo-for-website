<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\InstallCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * /install/check prints a pass or fail list for the live server.
 * /install/migrate runs migrations once on hosts without SSH, then locks itself.
 * Both need ?token= equal to INSTALL_TOKEN; with no token set they return 404.
 */
class InstallController extends Controller
{
    public function check(Request $request, InstallCheck $checker): View
    {
        $this->authorizeToken($request);
        $checks = $checker->run(network: ! $request->boolean('offline'));

        return view('install.check', [
            'checks' => $checks,
            'failed' => collect($checks)->where('status', 'fail')->count(),
            'warned' => collect($checks)->where('status', 'warn')->count(),
        ]);
    }

    public function migrate(Request $request): Response
    {
        $this->authorizeToken($request);
        $lock = storage_path('app/install-migrate.lock');
        abort_if(file_exists($lock), 410, 'This route has already been used. Remove storage/app/install-migrate.lock to run it again.');

        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        if ($request->boolean('seed')) {
            Artisan::call('db:seed', ['--force' => true]);
            $output .= Artisan::output();
        }
        file_put_contents($lock, now()->toIso8601String());
        Audit::record('install.migrated', null, null, ['seeded' => $request->boolean('seed')]);

        return response($output, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function authorizeToken(Request $request): void
    {
        $token = (string) config('esnad.install.token');
        abort_if($token === '' || strlen($token) < 32, 404);
        abort_unless(hash_equals($token, (string) $request->query('token')), 404);
    }
}
