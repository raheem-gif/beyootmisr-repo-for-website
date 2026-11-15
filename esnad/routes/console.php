<?php

use App\Models\AccessLog;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Schedule;

// One cron line every minute on every host: * * * * * php artisan schedule:run
Schedule::command('esnad:heartbeat')->everyMinute();

// Shared hosting has no supervisor: work the database queue from the
// scheduler for at most 50 seconds, then stop (section 3.4).
if (config('esnad.hosting_mode') === 'shared') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);
}

// Access logs are kept 180 days, then deleted (Anti-Cybercrime Law).
Schedule::command('model:prune', ['--model' => [AccessLog::class]])->dailyAt('03:10');

// Expired OTP codes.
Schedule::call(fn () => OtpCode::query()->where('expires_at', '<', now()->subDay())->delete())->dailyAt('03:20');
