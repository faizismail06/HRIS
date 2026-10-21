<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoBoundary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckInstallation extends Command
{
    protected $signature = 'hris:check {--production : Evaluate strict operational deployment settings}';

    protected $description = 'Read-only installation checks; never displays secrets, sends mail or performs payment';

    public function handle(): int
    {
        $strict = (bool) $this->option('production');
        $checks = ['PostgreSQL PDO driver' => in_array('pgsql', \PDO::getAvailableDrivers(), true), 'GD image validation' => extension_loaded('gd'), 'ZIP spreadsheet support' => extension_loaded('zip'), 'Application encryption key configured' => filled(config('app.key'))];
        try {
            DemoBoundary::database();
            $checks['Database environment marker matches'] = true;
            $checks['PostgreSQL connection'] = DB::connection()->getDriverName() === 'pgsql';
            $checks['Migrations current'] = Schema::hasTable('migrations') && count(glob(database_path('migrations/*.php'))) === DB::table('migrations')->count();
            $checks['Append-only audit trigger installed'] = (bool) DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_trigger WHERE tgname='protect_hris_audit' AND tgenabled <> 'D') AS installed")->installed;
            $failed = DB::table('notification_deliveries')->where('status', 'failed')->count();
            $this->line('Notification deliveries awaiting investigation: '.$failed);
        } catch (\Throwable $e) {
            $checks['Database/environment/migration checks'] = false;
        }
        if ($strict) {
            $checks['Production environment'] = app()->environment('production');
            $checks['Debug disabled'] = ! config('app.debug');
            $checks['Simulation and UI preview disabled'] = ! DemoBoundary::enabled() && ! config('hris.ui_preview_enabled');
            $checks['HTTPS application URL'] = str_starts_with((string) config('app.url'), 'https://');
            $checks['Secure session cookies'] = config('session.secure') === true;
            $checks['Durable asynchronous queue'] = in_array(config('queue.default'), ['database', 'redis'], true);
            $checks['Queue retry interval exceeds import timeout'] = (int) config('queue.connections.'.config('queue.default').'.retry_after', 0) > 300;
            $checks['Operational mail transport configured'] = ! in_array(config('mail.default'), ['log', 'array'], true);
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS' : 'FAIL').' '.$label);
        }
        $this->line('Scheduler/worker supervision, real SMTP delivery, bank layout, approved policies, TLS/ACLs and off-host encrypted backup/UAT require operator verification.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
