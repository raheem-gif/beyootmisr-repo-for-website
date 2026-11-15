<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Records that the scheduler ran, and queues a job that records that the queue ran. */
class Heartbeat extends Command
{
    protected $signature = 'esnad:heartbeat';

    protected $description = 'Record scheduler and queue heartbeats for /install/check';

    public function handle(): int
    {
        DB::table('system_heartbeats')->upsert([['name' => 'scheduler', 'beat_at' => now()]], ['name'], ['beat_at']);
        QueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
