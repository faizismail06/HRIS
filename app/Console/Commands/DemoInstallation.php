<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Demo\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoInstallation extends Command
{
    protected $signature = 'hris:demo {action : init or reset} {scenario : Exact configured scenario UUID}';

    protected $description = 'Initialize or reset ONLY the marked, isolated database matching this demo scenario';

    public function handle(): int
    {
        abort_unless(DemoBoundary::enabled() && $this->argument('scenario') === config('hris.demo_scenario_id'), 403);
        DemoBoundary::database();
        abort_unless(in_array($this->argument('action'), ['init', 'reset']), 422);
        if ($this->argument('action') === 'reset') {
            // Full database name AND durable marker are checked above, before any destructive operation.
            // Production and another scenario cannot reach migrate:fresh through this command.
            $paths = DB::table('private_attachments')->pluck('path')->filter(fn ($path) => preg_match('/^(evidence|attendance)\/[a-f0-9-]+\.(jpg|png|pdf)$/D', $path))->all();
            Artisan::call('migrate:fresh', ['--force' => true]);
            Storage::disk('local')->delete($paths);
        }
        app(DemoSeeder::class)->run();
        $this->info('Isolated synthetic demo initialized. No usable production passwords or bank instructions created.');

        return self::SUCCESS;
    }
}
