<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupContext extends Command
{
    protected $signature = 'hris:backup-context';

    protected $description = 'Read-only non-secret metadata for the encrypted backup runner';

    public function handle(): int
    {
        $marker = DB::table('installation_boundary')->where('id', 1)->firstOrFail();
        $this->line(json_encode(['database' => DB::connection()->getDatabaseName(), 'driver' => DB::connection()->getDriverName(), 'maintenance' => app()->isDownForMaintenance(), 'private_storage' => config('filesystems.disks.local.root'), 'installation' => $marker->kind], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
