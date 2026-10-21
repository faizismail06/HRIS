<?php

namespace App\Domain\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoBoundary
{
    public static function enabled(): bool
    {
        return (bool) config('hris.demo_features_enabled');
    }

    public static function configuration(): void
    {
        if (! self::enabled()) {
            throw_if(app()->environment('demo'), \RuntimeException::class, 'Demo installation requires DEMO_FEATURES_ENABLED.');

            return;
        }
        throw_unless(app()->environment(['demo', 'testing', 'local']), \RuntimeException::class, 'Simulation is forbidden in this environment.');
        throw_unless(Str::isUuid(config('hris.demo_scenario_id', '')), \RuntimeException::class, 'Demo scenario UUID is required.');
        throw_unless(strlen((string) config('hris.demo_operator_token')) >= 32, \RuntimeException::class, 'Demo operator token must contain at least 32 characters.');
        throw_unless(config('mail.default') === 'array', \RuntimeException::class, 'Demo requires the array mail sink.');
        throw_unless(config('queue.default') === 'database' && ! config('queue.connections.database.connection'), \RuntimeException::class, 'Demo queue must use the isolated default database.');
        throw_unless(config('session.driver') === 'database' && ! config('session.connection'), \RuntimeException::class, 'Demo sessions must use the isolated default database.');
        throw_unless(config('cache.default') === 'database' && ! config('cache.stores.database.connection'), \RuntimeException::class, 'Demo cache must use the isolated default database.');
        throw_if(config('cache.stores.database.lock_connection'), \RuntimeException::class, 'Demo cache locks must use the isolated default database.');
        throw_unless(config('queue.failed.database') === config('database.default') && config('queue.batching.database') === config('database.default'), \RuntimeException::class, 'Demo failed jobs and batches must use the isolated default database.');
    }

    public static function database(bool $requireMarker = true): void
    {
        self::configuration();
        if (self::enabled()) {
            $expected = 'hris_demo_'.str_replace('-', '', config('hris.demo_scenario_id'));
            throw_unless(DB::connection()->getDatabaseName() === $expected, \RuntimeException::class, 'Demo database name does not match its scenario.');
            throw_unless(config('filesystems.disks.local.root') === storage_path('app/demo/'.config('hris.demo_scenario_id').'/private'), \RuntimeException::class, 'Demo storage must use its private scenario directory.');
        }
        if (! Schema::hasTable('installation_boundary')) {
            throw_if($requireMarker, \RuntimeException::class, 'Installation boundary is missing; run migrations before serving traffic.');

            return;
        }
        $marker = DB::table('installation_boundary')->where('id', 1)->first();
        throw_unless($marker && ($marker->kind === (self::enabled() ? 'demo' : 'production')), \RuntimeException::class, 'Database environment marker mismatch. Demo data cannot run in production.');
        if (self::enabled()) {
            throw_unless($marker->scenario_id === config('hris.demo_scenario_id'), \RuntimeException::class, 'Database belongs to another demo scenario.');
        }
    }

    public static function operator(?string $token): void
    {
        abort_unless(self::enabled() && is_string($token) && hash_equals(hash('sha256', (string) config('hris.demo_operator_token')), hash('sha256', $token)), 403);
        self::database();
    }
}
