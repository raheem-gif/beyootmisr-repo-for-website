<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::table('system_heartbeats')->upsert([['name' => 'queue', 'beat_at' => now()]], ['name'], ['beat_at']);
    }
}
