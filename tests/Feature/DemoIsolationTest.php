<?php

namespace Tests\Feature;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Demo\DemoSeeder;
use App\Domain\Demo\ScenarioClock;
use App\Domain\Operations\Clock;
use App\Http\Controllers\DemoController;
use App\Models\RosterShift;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    public function test_production_cannot_enable_simulation_and_demo_routes_are_absent_by_default(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(fn ($route) => str_starts_with($route->uri(), 'demo/')));
        config(['hris.demo_features_enabled' => true]);
        $old = app()['env'];
        app()['env'] = 'production';
        try {
            DemoBoundary::configuration();
            $this->fail('Production must reject simulation flags.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forbidden', $e->getMessage());
        } finally {
            app()['env'] = $old;
        }
    }

    public function test_synthetic_seed_simulation_and_reset_stay_in_the_exact_disposable_scenario_database(): void
    {
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        $original = config('database.default');
        $scenario = (string) Str::uuid();
        $database = 'hris_demo_'.str_replace('-', '', $scenario);
        DB::connection($original)->statement('CREATE DATABASE "'.$database.'"');
        try {
            config(['database.connections.demo_test' => array_replace(config('database.connections.'.$original), ['database' => $database, 'url' => null]), 'database.default' => 'demo_test', 'hris.demo_features_enabled' => true, 'hris.demo_scenario_id' => $scenario, 'hris.demo_operator_token' => str_repeat('synthetic', 8), 'mail.default' => 'array', 'queue.default' => 'database', 'queue.connections.database.connection' => null, 'queue.failed.database' => 'demo_test', 'queue.batching.database' => 'demo_test', 'session.driver' => 'database', 'session.connection' => null, 'cache.default' => 'database', 'cache.stores.database.connection' => null, 'cache.stores.database.lock_connection' => null]);
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            config(['filesystems.disks.local.root' => storage_path('app/demo/'.$scenario.'/private')]);
            DemoBoundary::database();
            $this->assertSame($database, DB::connection()->getDatabaseName());
            app()->bind(Clock::class, ScenarioClock::class);
            try {
                app(DemoSeeder::class)->run();
            } catch (\Throwable $e) {
                $frames = array_filter($e->getTrace(), fn ($f) => isset($f['file']) && str_contains($f['file'], DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR));
                $this->fail(get_class($e).': '.$e->getMessage().' '.json_encode(array_map(fn ($f) => [$f['file'], $f['line']], $frames)));
            }
            $this->assertSame(8, DB::table('demo_personas')->count());
            $this->assertSame(0, DB::connection($original)->table('demo_personas')->count());
            Route::middleware('web')->prefix('demo')->group(function () {
                Route::post('/persona', [DemoController::class, 'login']);
                Route::post('/clock', [DemoController::class, 'clock']);
                Route::post('/time', [DemoController::class, 'time']);
            });
            $this->postJson('/demo/persona', ['persona' => 'employee'])->assertForbidden();
            $this->withHeader('X-Demo-Operator-Token', config('hris.demo_operator_token'))->postJson('/demo/persona', ['persona' => 'employee', 'employee_id' => 999])->assertUnprocessable();
            $this->postJson('/demo/persona', ['persona' => 'employee'])->assertOk()->assertJsonPath('environment', 'DEMO');
            $user = User::findOrFail(DB::table('demo_personas')->where('code', 'employee')->value('user_id'));
            $this->actingAs($user);
            $shift = RosterShift::orderBy('id')->firstOrFail();
            $input = ['idempotency_key' => (string) Str::uuid(), 'roster_shift_id' => $shift->id, 'direction' => 'in', 'mode' => 'outside'];
            $this->postJson('/demo/clock', $input)->assertUnprocessable()->assertJsonPath('code', 'outside_geofence');
            $input['idempotency_key'] = (string) Str::uuid();
            $input['mode'] = 'poor_accuracy';
            $this->postJson('/demo/clock', $input)->assertUnprocessable()->assertJsonPath('code', 'poor_accuracy');
            $input['idempotency_key'] = (string) Str::uuid();
            $input['mode'] = 'inside';
            $this->postJson('/demo/clock', $input)->assertOk()->assertHeader('X-HRIS-Environment', 'DEMO');
            $event = DB::table('attendance_events')->sole();
            $this->assertSame('SIMULATED', $event->provenance);
            $this->assertSame($scenario, $event->scenario_id);
            $this->assertSame($user->id, $event->actor_id);
            config(['hris.ui_preview_enabled' => false]);
            $this->get('/')->assertOk()->assertSee('DEMO · DATA SINTETIS', false);
            $this->postJson('/demo/persona', ['persona' => 'finance_approver'])->assertOk();
            $this->postJson('/app/api/payroll-batches/999/bank-export', ['idempotency_key' => (string) Str::uuid(), 'template_id' => 1, 'reason' => 'Demo must never produce a bank instruction'])->assertForbidden();
            $before = now()->toIso8601String();
            $this->postJson('/demo/time', ['time' => today()->setTime(17, 0)->toIso8601String()])->assertOk();
            $this->assertSame(17, app(Clock::class)->now()->hour);
            $this->assertLessThanOrEqual(1, abs(now()->diffInSeconds($before)));
            config(['hris.demo_features_enabled' => false]);
            try {
                DemoBoundary::database();
                $this->fail('Restoring a demo database into operational mode must fail.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('marker mismatch', $e->getMessage());
            }
            config(['hris.demo_features_enabled' => true]);
            $this->assertSame(0, Artisan::call('hris:demo', ['action' => 'reset', 'scenario' => $scenario]));
            $this->assertSame(0, DB::table('attendance_events')->count());
            $this->assertSame(8, DB::table('demo_personas')->count());
            $this->assertSame(0, DB::connection($original)->table('demo_personas')->count());
        } finally {
            config(['hris.demo_features_enabled' => false, 'database.default' => $original]);
            DB::purge('demo_test');
            DB::connection($original)->statement('DROP DATABASE "'.$database.'"');
        }
    }
}
