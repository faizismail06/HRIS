<?php

namespace App\Jobs;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Operations\RuntimeHealth;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordWorkerHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public int $tries = 1;

    public int $timeout = 30;

    public function uniqueId(): string
    {
        return 'hris-worker-heartbeat';
    }

    public function handle(): void
    {
        DemoBoundary::database();
        RuntimeHealth::record('worker');
    }
}
