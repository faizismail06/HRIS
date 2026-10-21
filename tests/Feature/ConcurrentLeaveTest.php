<?php

namespace Tests\Feature;

use App\Domain\Leave\LeaveService;
use App\Domain\Workflow\WorkflowService;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Job;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentLeaveTest extends TestCase
{
    public function test_two_php_processes_cannot_reserve_the_same_remaining_entitlement(): void
    {
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $original = config('database.default');
        $database = 'hris_concurrency_test_'.bin2hex(random_bytes(8));
        $base = DB::connection()->getConfig();
        DB::statement('CREATE DATABASE "'.$database.'"');
        $barrier = tempnam(sys_get_temp_dir(), 'hris_barrier_');
        $processes = [];
        try {
            config(['database.connections.concurrency_test' => array_replace(array_diff_key($base, ['name' => true]), ['database' => $database, 'url' => null]), 'database.default' => 'concurrency_test']);
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $this->assertSame('concurrency_test', DB::connection()->getName());
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'concurrency_test', '--force' => true]));
            Company::create(['name' => 'Synthetic concurrency fixture']);
            $branch = Branch::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Synthetic branch']);
            $department = Department::create(['company_id' => 1, 'code' => 'TEST', 'name' => 'Synthetic department']);
            $job = Job::create(['department_id' => $department->id, 'code' => 'TEST', 'name' => 'Synthetic job']);
            $person = Employee::create(['employee_number' => 'TEST', 'name' => 'Synthetic employee']);
            $period = $person->periods()->create(['starts_on' => today()->subYears(2)->toDateString()]);
            $person->assignments()->create(['employment_period_id' => $period->id, 'branch_id' => $branch->id, 'department_id' => $department->id, 'job_id' => $job->id, 'valid_from' => $period->starts_on]);
            $hr = User::factory()->create();
            $hr->forceFill(['is_active' => true])->save();
            $hr->grants()->create(['role' => 'hr_admin', 'scope' => 'company']);
            $worker = User::factory()->create();
            $worker->forceFill(['is_active' => true, 'employee_id' => $person->id])->save();
            $worker->grants()->create(['role' => 'employee', 'scope' => 'self']);
            $leave = app(LeaveService::class);
            $firstDate = CarbonImmutable::today(config('app.timezone'))->addDay();
            if ($firstDate->year !== $firstDate->addDay()->year) {
                $firstDate = $firstDate->addDays(2);
            }
            $type = $leave->type($hr->fresh(), (string) Str::uuid(), ['code' => 'TEST', 'name' => 'Synthetic leave', 'is_paid' => true, 'deducts_balance' => true, 'allows_half_day' => true, 'requires_evidence' => false, 'notice_days' => 0, 'minimum_tenure_days' => 0]);
            $leave->grant($hr->fresh(), (string) Str::uuid(), ['employee_id' => $person->id, 'type_code' => 'TEST', 'entitlement_year' => $firstDate->year, 'valid_from' => $firstDate->startOfYear()->toDateString(), 'valid_until' => $firstDate->addYear()->startOfYear()->toDateString(), 'minutes' => 480, 'source_key' => 'SYNTHETIC-RACE', 'source_type' => 'grant', 'reason' => 'Synthetic fixture']);
            $workflow = app(WorkflowService::class);
            $workflow->define($hr->fresh(), (string) Str::uuid(), ['name' => 'Synthetic leave review', 'kind' => 'leave', 'priority' => 10, 'min_amount_minor' => 0, 'steps' => [['role' => 'hr_admin']]]);
            $requests = [];
            $keys = [];
            foreach ([0, 1] as $offset) {
                $date = $firstDate->addDays($offset)->toDateString();
                $roster = RosterVersion::create(['status' => 'published', 'version' => 1, 'created_by' => $hr->id, 'checksum' => str_repeat('a', 64)]);
                RosterShift::create(['roster_version_id' => $roster->id, 'employee_id' => $person->id, 'work_date' => $date, 'starts_at' => $date.'T09:00:00+07:00', 'ends_at' => $date.'T17:00:00+07:00', 'timezone' => 'Asia/Jakarta', 'is_day_off' => false, 'status' => 'published', 'snapshot' => ['branch_id' => $branch->id, 'department_id' => $department->id, 'breaks' => [], 'half_day_split_minute' => 240]]);
                $request = $workflow->draft($worker->fresh(), (string) Str::uuid(), 'leave', ['leave_type_id' => $type['id'], 'starts_on' => $date, 'ends_on' => $date, 'segment' => 'full', 'reason' => 'Synthetic competing request']);
                $requests[] = $request['id'];
                $keys[$request['id']] = (string) Str::uuid();
            }
            $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database, 'DB_HOST' => (string) $base['host'], 'DB_PORT' => (string) $base['port'], 'DB_USERNAME' => $base['username'], 'DB_PASSWORD' => $base['password'], 'HRIS_CONCURRENCY_DATABASE' => $database, 'DEMO_FEATURES_ENABLED' => 'false', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
            foreach ($requests as $id) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/concurrent_leave_submit.php'), (string) $worker->id, (string) $id, $barrier, $keys[$id]], base_path(), $env, null, 30);
                $p->start();
                $processes[] = $p;
            }
            $deadline = microtime(true) + 15;
            while (collect($processes)->contains(fn ($p) => ! str_contains($p->getOutput(), 'READY'))) {
                throw_if(microtime(true) >= $deadline, \RuntimeException::class, 'Both workers must reach the start barrier.');
                usleep(10000);
            }
            unlink($barrier);
            $outcomes = [];
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), 'Synthetic worker failed: '.$p->getErrorOutput());
                $lines = explode("\n", trim($p->getOutput()));
                $outcomes[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            $statuses = array_column($outcomes, 'status');
            sort($statuses);
            $this->assertSame([200, 422], $statuses);
            $this->assertSame(480, (int) DB::table('leave_allocations')->where('state', 'reserved')->sum('minutes'));
            $winner = collect($outcomes)->firstWhere('status', 200)['request_id'];
            $workflow->submit($worker->fresh(), $winner, 1, $keys[$winner]);
            $this->assertSame(1, DB::table('leave_ledger')->where('kind', 'reservation')->count());
            $this->assertSame(0, $leave->balances($worker->fresh())[0]['available_minutes']);
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop(1);
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
            config(['database.default' => $original]);
            DB::purge('concurrency_test');
            DB::connection($original)->statement('DROP DATABASE "'.$database.'"');
        }
    }
}
