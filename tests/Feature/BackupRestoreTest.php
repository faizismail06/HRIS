<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    public function test_native_postgresql_dump_restore_preserves_synthetic_records_and_audit_protection(): void
    {
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $original = config('database.default');
        $base = config('database.connections.'.$original);
        $resolved = DB::connection()->getConfig();
        $suffix = bin2hex(random_bytes(8));
        $source = 'hris_backup_test_'.$suffix;
        $target = 'hris_restore_test_'.$suffix;
        $dump = tempnam(sys_get_temp_dir(), 'hris_synthetic_dump_');
        DB::statement('CREATE DATABASE "'.$source.'"');
        DB::statement('CREATE DATABASE "'.$target.'"');
        try {
            foreach (['backup_fixture' => $source, 'restore_fixture' => $target] as $connection => $name) {
                config(['database.connections.'.$connection => array_replace($base, ['database' => $name, 'url' => null])]);
                $this->assertSame($name, DB::connection($connection)->getDatabaseName());
            }
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'backup_fixture', '--force' => true]));
            $db = DB::connection('backup_fixture');
            $db->table('companies')->insert(['name' => 'Synthetic restore fixture', 'created_at' => now(), 'updated_at' => now()]);
            $db->table('audit_entries')->insert(['action' => 'synthetic.restore_test', 'subject_type' => 'test', 'changes' => '{}', 'created_at' => now()]);
            $db->statement('CREATE TABLE synthetic_encrypted_fixture (value text NOT NULL)');
            $db->table('synthetic_encrypted_fixture')->insert(['value' => encrypt('SYNTHETIC PRIVATE FIXTURE')]);
            $env = ['PGPASSWORD' => (string) $resolved['password'], 'PGSSLMODE' => $resolved['sslmode'] ?? 'prefer'];
            $args = ['--host='.$resolved['host'], '--port='.$resolved['port'], '--username='.$resolved['username'], '--no-password'];
            $dumpProcess = new Process(array_merge([getenv('HRIS_PG_DUMP_BINARY') ?: 'pg_dump'], $args, ['--format=custom', '--no-owner', '--no-privileges', '--file='.$dump, '--dbname='.$source]), base_path(), $env, null, 60);
            $dumpProcess->run();
            $this->assertTrue($dumpProcess->isSuccessful(), 'pg_dump failed for the synthetic disposable database.');
            $this->assertGreaterThan(0, filesize($dump));
            $restoreProcess = new Process(array_merge([getenv('HRIS_PG_RESTORE_BINARY') ?: 'pg_restore'], $args, ['--no-owner', '--no-privileges', '--exit-on-error', '--dbname='.$target, $dump]), base_path(), $env, null, 60);
            $restoreProcess->run();
            $this->assertTrue($restoreProcess->isSuccessful(), 'pg_restore failed for the synthetic disposable database.');
            $restored = DB::connection('restore_fixture');
            $this->assertSame('Synthetic restore fixture', $restored->table('companies')->value('name'));
            $this->assertSame($db->table('migrations')->count(), $restored->table('migrations')->count());
            $this->assertSame('production', $restored->table('installation_boundary')->value('kind'));
            $this->assertSame(0, $restored->table('users')->count());
            $this->assertSame('SYNTHETIC PRIVATE FIXTURE', decrypt($restored->table('synthetic_encrypted_fixture')->value('value')));
            try {
                $restored->table('audit_entries')->update(['action' => 'tampered']);
                $this->fail('Restored audit trigger must remain active.');
            } catch (QueryException $e) {
                $this->assertSame('P0001', $e->getCode());
            }
        } finally {
            config(['database.default' => $original]);
            DB::purge('backup_fixture');
            DB::purge('restore_fixture');
            // Exact generated names only; no production/test workspace database is dropped.
            DB::connection($original)->statement('DROP DATABASE "'.$target.'"');
            DB::connection($original)->statement('DROP DATABASE "'.$source.'"');
            unlink($dump);
        }
    }
}
