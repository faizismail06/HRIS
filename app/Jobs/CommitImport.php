<?php

namespace App\Jobs;

use App\Domain\Imports\ImportService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CommitImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $actorId, public int $securityVersion, public int $batchId, public int $version, public string $key, public string $checksum) {}

    public function handle(ImportService $service): void
    {
        try {
            $actor = User::findOrFail($this->actorId);
            $actor->security_version = $this->securityVersion;
            $service->commit($actor, $this->batchId, $this->version, $this->key, $this->checksum);
        } catch (\Throwable $error) {
            DB::table('import_batches')->where('id', $this->batchId)->where('status', 'queued')->where('version', $this->version)->update(['status' => 'failed', 'version' => $this->version + 1, 'errors' => json_encode([['row' => null, 'column' => 'batch', 'reason' => 'Batch gagal; seluruh perubahan dibatalkan.', 'remedy' => 'Periksa izin dan referensi, lalu preview ulang.']]), 'error_count' => 1, 'updated_at' => now()]);
            // Never serialize SQL/provider errors or rows containing personal data into failed_jobs.
            throw new \RuntimeException('HRIS import failed atomically; inspect the authorized batch status.');
        }
    }
}
