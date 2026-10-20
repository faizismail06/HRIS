<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationFreshTest extends TestCase
{
    public function test_fresh_seed_can_run_twice_when_postgresql_functions_survive_table_drops(): void
    {
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        // Only this randomly created disposable database is ever reset or dropped.
        $database = 'hris_migration_test_'.bin2hex(random_bytes(8));
        $connection = 'migration_regression';
        DB::statement('CREATE DATABASE "'.$database.'"');
        try {
            config(['database.connections.'.$connection => array_merge(config('database.connections.pgsql'), ['database' => $database, 'url' => null])]);
            for ($run = 0; $run < 2; $run++) {
                $this->assertSame(0, Artisan::call('migrate:fresh', ['--database' => $connection, '--seed' => true, '--force' => true]));
                $db = DB::connection($connection);
                $this->assertSame($database, $db->getDatabaseName());
                $this->assertGreaterThanOrEqual(5, $db->table('migrations')->count());
                $this->assertSame(0, $db->table('users')->count());
                $functions = $db->select("SELECT proname FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' AND proname IN ('protect_hris_audit', 'guard_hris_department', 'guard_hris_assignment', 'guard_hris_period')");
                $this->assertCount(4, $functions);
            }
            $db->table('audit_entries')->insert(['action' => 'test.audit', 'subject_type' => 'migration']);
            try {
                $db->table('audit_entries')->update(['action' => 'tampered']);
                $this->fail('Audit trigger must remain active after migrate:fresh.');
            } catch (QueryException $exception) {
                $this->assertSame('P0001', $exception->getCode());
            }
        } finally {
            DB::purge($connection);
            DB::statement('DROP DATABASE "'.$database.'"');
        }
    }
}
