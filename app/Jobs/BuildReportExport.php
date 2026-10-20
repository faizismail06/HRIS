<?php

namespace App\Jobs;

use App\Domain\Reports\ReportExports;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class BuildReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $exportId) {}

    public function handle(ReportExports $exports): void
    {
        try {
            $exports->build($this->exportId);
        } catch (\Throwable $error) {
            DB::table('report_exports')->where('id', $this->exportId)->where('status', 'queued')->update(['status' => 'failed', 'updated_at' => now()]);
            throw new \RuntimeException('HRIS report export failed; inspect authorized export status.');
        }
    }
}
