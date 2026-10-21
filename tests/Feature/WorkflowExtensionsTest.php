<?php

namespace Tests\Feature;

use App\Domain\Operations\Outbox;
use App\Domain\Workflow\ReconcileApprovers;
use App\Jobs\DeliverOutbox;
use App\Models\Branch;
use App\Models\User;
use App\Models\WorkflowStep;
use Carbon\Carbon;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Tests\Support\AttendanceScenarioCase;

class WorkflowExtensionsTest extends AttendanceScenarioCase
{
    private function define(?array $calendar = null): void
    {
        $this->login($this->hr)->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Synthetic profile routing', 'kind' => 'profile_change', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']], 'deadline_calendar' => $calendar])->assertOk();
    }

    private function draft(): int
    {
        return $this->login($this->worker)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'profile_change', 'payload' => ['employee_id' => $this->worker->employee_id, 'category' => 'general', 'base_version' => 0, 'data' => ['phone' => 'SYNTHETIC-PHONE'], 'reason' => 'Private synthetic reason']])->assertCreated()->json('id');
    }

    private function scopedHr(int $branch): User
    {
        $u = $this->user('hr_admin');
        $u->grants()->delete();
        $u->grants()->create(['role' => 'hr_admin', 'scope' => 'branch', 'branch_id' => $branch]);

        return $u;
    }

    public function test_delegation_is_scoped_snapshot_audited_and_cannot_be_chained_or_used_after_revoke(): void
    {
        $this->define();
        $a = $this->worker->employee->assignments()->sole()->branch_id;
        $b = Branch::create(['company_id' => 1, 'code' => 'OTHER', 'name' => 'Synthetic Other Branch']);
        $delegator = $this->scopedHr($a);
        $delegate = $this->scopedHr($b->id);
        $input = ['idempotency_key' => (string) Str::uuid(), 'delegate_id' => $delegate->id, 'role' => 'hr_admin', 'starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'employee_ids' => [$this->worker->employee_id], 'reason' => 'Synthetic cover'];
        $delegation = $this->login($delegator)->postJson('/app/api/workflow-delegations', $input)->assertCreated()->json('id');
        $id = $this->draft();
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($delegate)->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertOk()->assertJsonPath('data.phone', 'SYNTHETIC-PHONE');
        $this->getJson('/app/api/employees/'.$this->worker->employee_id.'/private-profile/general')->assertNotFound();
        $chain = array_replace($input, ['idempotency_key' => (string) Str::uuid(), 'delegate_id' => $this->hr->id]);
        $this->postJson('/app/api/workflow-delegations', $chain)->assertForbidden();
        $this->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('workflow_decisions', ['actor_id' => $delegate->id, 'workflow_delegation_id' => $delegation]);
        $this->login($delegator)->postJson('/app/api/workflow-delegations/'.$delegation.'/revoke', ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->login($delegate)->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertNotFound();
    }

    public function test_working_deadline_preserves_timezone_and_skips_weekend_and_versioned_holiday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 16:30:00', 'Asia/Makassar'));
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $this->login($this->hr)->postJson('/app/api/work-calendar-dates', ['idempotency_key' => (string) Str::uuid(), 'branch_id' => $branch, 'work_date' => '2026-10-26', 'label' => 'Synthetic manual holiday', 'is_holiday' => true])->assertCreated();
        $this->define(['timezone' => 'Asia/Makassar', 'branch_id' => $branch, 'working_days' => [1, 2, 3, 4, 5], 'start_local' => '09:00', 'end_local' => '17:00', 'due_minutes' => 60, 'escalate_minutes' => 120]);
        $id = $this->draft();
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $step = WorkflowStep::where('workflow_request_id', $id)->sole();
        $this->assertSame('2026-10-27 09:30', $step->due_at->setTimezone('Asia/Makassar')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-27 10:30', $step->escalate_at->setTimezone('Asia/Makassar')->format('Y-m-d H:i'));
        $this->assertArrayHasKey('2026-10-26', $step->deadline_snapshot['due_holidays']);
    }

    private function preference(array $extra = []): void
    {
        $this->login($this->worker)->postJson('/app/api/notification-preferences', array_replace(['idempotency_key' => (string) Str::uuid(), 'email_enabled' => true, 'in_app_enabled' => true, 'timezone' => 'Asia/Jakarta', 'delivery_mode' => 'immediate', 'digest_time' => '09:00', 'quiet_start' => null, 'quiet_end' => null], $extra))->assertOk();
    }

    private function mockMail(string $idPart): void
    {
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($body, $callback) use ($idPart) {
            $this->assertStringNotContainsString('Private synthetic reason', $body);
            $message = new Message(new Email);
            $callback($message);
            $this->assertStringContainsString($idPart, $message->getSymfonyMessage()->getHeaders()->get('Message-ID')->getBodyAsString());
        });
    }

    public function test_quiet_hours_defer_without_consuming_attempts_and_opt_out_keeps_workflow_truth(): void
    {
        $id = $this->draft();
        $this->preference(['quiet_start' => '22:00', 'quiet_end' => '07:00']);
        $this->travelTo(Carbon::parse('2026-10-19 23:00:00', 'Asia/Jakarta'));
        Outbox::notify([$this->worker->id], 'request.synthetic', 'workflow_request', $id, 'quiet-test');
        $delivery = DB::table('notification_deliveries')->sole();
        (new DeliverOutbox($delivery->id))->handle();
        $this->assertSame(0, DB::table('notification_deliveries')->sole()->attempts);
        $this->assertSame('2026-10-20 07:00', Carbon::parse(DB::table('notification_deliveries')->sole()->available_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->travelTo(Carbon::parse('2026-10-20 07:01:00', 'Asia/Jakarta'));
        $this->mockMail('hris-event-');
        (new DeliverOutbox($delivery->id))->handle();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'attempts' => 1]);
        $this->preference(['email_enabled' => false, 'in_app_enabled' => false]);
        Outbox::notify([$this->worker->id], 'request.synthetic', 'workflow_request', $id, 'optout-test');
        $this->assertDatabaseCount('inbox_messages', 1);
        $this->assertDatabaseHas('workflow_requests', ['id' => $id, 'status' => 'draft']);
    }

    public function test_daily_digest_groups_events_and_sends_one_generic_message(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 08:00:00', 'Asia/Jakarta'));
        $id = $this->draft();
        $this->preference(['delivery_mode' => 'daily']);
        Outbox::notify([$this->worker->id], 'request.one', 'workflow_request', $id, 'digest-one');
        Outbox::notify([$this->worker->id], 'request.two', 'workflow_request', $id, 'digest-two');
        $deliveries = DB::table('notification_deliveries')->orderBy('id')->get();
        $this->travelTo(Carbon::parse('2026-10-19 09:00:00', 'Asia/Jakarta'));
        $this->mockMail('hris-digest-');
        foreach ($deliveries as $delivery) {
            (new DeliverOutbox($delivery->id))->handle();
        }
        $this->assertSame(2, DB::table('notification_deliveries')->where('status', 'sent')->count());
        $this->assertDatabaseCount('notification_digest_runs', 1);
        $this->assertDatabaseHas('notification_digest_runs', ['user_id' => $this->worker->id, 'status' => 'sent']);
    }

    public function test_future_delegation_joins_pending_step_with_audited_version_and_expiry_is_checked_at_runtime(): void
    {
        $this->define();
        $branch = $this->worker->employee->assignments()->sole()->branch_id;
        $other = Branch::create(['company_id' => 1, 'code' => 'FUTURE', 'name' => 'Synthetic Other Branch']);
        $delegator = $this->scopedHr($branch);
        $delegate = $this->scopedHr($other->id);
        $this->login($delegator)->postJson('/app/api/workflow-delegations', ['idempotency_key' => (string) Str::uuid(), 'delegate_id' => $delegate->id, 'role' => 'hr_admin', 'employee_ids' => [$this->worker->employee_id], 'starts_at' => now()->addHour()->toIso8601String(), 'ends_at' => now()->addHours(2)->toIso8601String(), 'reason' => 'Synthetic future cover'])->assertCreated();
        $id = $this->draft();
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($delegate)->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertNotFound();
        $this->travel(61)->minutes();
        app(ReconcileApprovers::class)->run();
        $this->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertOk();
        $this->assertDatabaseHas('workflow_requests', ['id' => $id, 'version' => 3]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'workflow.approvers_reconciled']);
        $this->travel(60)->minutes();
        $this->getJson('/app/api/requests/'.$id.'/proposed-profile')->assertNotFound();
    }
}
