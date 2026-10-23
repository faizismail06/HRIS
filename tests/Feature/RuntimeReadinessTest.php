<?php

namespace Tests\Feature;

use App\Domain\Operations\ProductionMail;
use App\Domain\Operations\RuntimeHealth;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AttendanceScenarioCase;

class RuntimeReadinessTest extends AttendanceScenarioCase
{
    public function test_secure_production_settings_and_recent_services_pass_but_placeholders_do_not(): void
    {
        $old = app()['env'];
        app()['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://hr.company.test', 'hris.ui_preview_enabled' => false, 'hris.demo_features_enabled' => false, 'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'queue.default' => 'database', 'queue.connections.database.retry_after' => 390, 'mail.default' => 'smtp', 'mail.from.address' => 'hr@company.test', 'mail.mailers.smtp.host' => 'smtp.company.test', 'mail.mailers.smtp.port' => 465]);
        try {
            RuntimeHealth::record('scheduler');
            RuntimeHealth::record('worker');
            $this->artisan('hris:check', ['--production' => true, '--services' => true])->assertSuccessful();
            config(['app.url' => 'https://replace-with-your-domain.invalid']);
            $this->artisan('hris:check', ['--production' => true])->expectsOutputToContain('FAIL HTTPS application URL')->assertFailed();
        } finally {
            app()['env'] = $old;
        }
    }

    public function test_heartbeats_measure_processed_work_not_merely_queued_work_and_expire(): void
    {
        Queue::fake([RecordWorkerHeartbeat::class]);
        $this->artisan('hris:dispatch-operations')->assertSuccessful();
        Queue::assertPushed(RecordWorkerHeartbeat::class);
        $this->assertTrue(RuntimeHealth::status()['scheduler']['healthy']);
        $this->assertFalse(RuntimeHealth::status()['worker']['healthy']);
        (new RecordWorkerHeartbeat)->handle();
        $this->artisan('hris:check', ['--services' => true])->assertSuccessful();
        $this->travel(181)->seconds();
        $this->artisan('hris:check', ['--services' => true])->expectsOutputToContain('FAIL Recent worker heartbeat')->assertFailed();
        $this->login($this->hr)->getJson('/app/api/operations/health')->assertForbidden();
        $this->login($this->user('platform_admin'))->getJson('/app/api/operations/health')->assertOk()->assertJsonPath('services.worker.healthy', false);
    }

    public function test_production_mail_rejects_sink_fallbacks_cycles_and_empty_smtp_settings(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'hr@company.test', 'mail.mailers.smtp.host' => 'smtp.company.test', 'mail.mailers.smtp.port' => 465]);
        $this->assertTrue(ProductionMail::configured());
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
        $this->assertFalse(ProductionMail::configured());
        config(['mail.mailers.failover.mailers' => ['failover']]);
        $this->assertFalse(ProductionMail::configured());
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '']);
        $this->assertFalse(ProductionMail::configured());
    }

    public function test_json_checks_never_print_configured_secrets_and_backup_context_is_read_only(): void
    {
        config(['mail.mailers.smtp.password' => 'SYNTHETIC-SECRET-DO-NOT-PRINT']);
        $this->artisan('hris:check', ['--production' => true, '--json' => true])->doesntExpectOutputToContain('SYNTHETIC-SECRET-DO-NOT-PRINT')->assertFailed();
        $before = DB::table('audit_entries')->count();
        $this->artisan('hris:backup-context')->expectsOutputToContain('"database":"hris_testing"')->doesntExpectOutputToContain('password')->assertSuccessful();
        $this->assertSame($before, DB::table('audit_entries')->count());
    }
}
