<?php

namespace Tests\Feature;

use App\Domain\CoreHR\EmployeeWriter;
use App\Models\EmployeeChangeRequest;
use App\Models\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AttendanceScenarioCase;

class CareerAdministrationTest extends AttendanceScenarioCase
{
    public function test_career_requires_evidence_and_workflow_approval_and_preserves_dated_placement(): void
    {
        Storage::fake('local');
        $old = $this->worker->employee->assignments()->sole();
        $job = Job::create(['department_id' => $old->department_id, 'code' => 'PROMOTION', 'name' => 'Synthetic next job']);
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Career review', 'kind' => 'career', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin']]])->assertOk();
        $officer = $this->user('hr_officer');
        $id = $this->login($officer)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'career', 'payload' => ['employee_id' => $this->worker->employee_id, 'base_version' => 1, 'effective_on' => '2026-10-25', 'reason' => 'Synthetic promotion', 'branch_id' => $old->branch_id, 'department_id' => $old->department_id, 'job_id' => $job->id]])->assertCreated()->json('id');
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertUnprocessable();
        $this->post('/app/api/requests/'.$id.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('evidence.png')], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/app/api/requests/'.$id.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $change = EmployeeChangeRequest::where('workflow_request_id', $id)->sole();
        try {
            app(EmployeeWriter::class)->review($this->hr, $change->id, ['version' => 1, 'decision' => 'approve', 'review_note' => 'Bypass attempt']);
            $this->fail('Legacy review must not bypass workflow.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(1, $this->worker->employee->fresh()->version);
        $this->login($this->hr)->postJson('/app/api/requests/'.$id.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve'])->assertOk();
        $this->assertSame($old->job_id, $this->worker->employee->assignments()->at('2026-10-24')->sole()->job_id);
        $this->assertSame($job->id, $this->worker->employee->assignments()->at('2026-10-25')->sole()->job_id);
        $this->assertSame(2, $this->worker->employee->fresh()->version);
    }

    public function test_retrospective_review_preserves_historical_placement_and_requires_distinct_hr_and_finance(): void
    {
        Storage::fake('local');
        $old = $this->worker->employee->assignments()->sole();
        $job = Job::create(['department_id' => $old->department_id, 'code' => 'REVIEWED', 'name' => 'Synthetic corrected future job']);
        $this->hr->grants()->create(['role' => 'finance_approver', 'scope' => 'company']);
        $finance = $this->user('finance_approver');
        $this->postJson('/app/api/workflow-definitions', ['idempotency_key' => (string) Str::uuid(), 'name' => 'Historical career review', 'kind' => 'career_retrospective', 'min_amount_minor' => 0, 'priority' => 10, 'steps' => [['role' => 'hr_admin'], ['role' => 'finance_approver']]])->assertOk();
        $officer = $this->user('hr_officer');
        $request = $this->login($officer)->postJson('/app/api/requests', ['idempotency_key' => (string) Str::uuid(), 'kind' => 'career_retrospective', 'payload' => ['employee_id' => $this->worker->employee_id, 'base_version' => 1, 'retrospective_on' => '2026-10-01', 'effective_on' => '2026-10-25', 'reason' => 'Synthetic historical review; do not overwrite closed history', 'branch_id' => $old->branch_id, 'department_id' => $old->department_id, 'job_id' => $job->id]])->assertCreated()->json('id');
        $this->post('/app/api/requests/'.$request.'/attachments', ['idempotency_key' => (string) Str::uuid(), 'file' => UploadedFile::fake()->image('historical-evidence.png')], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/app/api/requests/'.$request.'/submit', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1])->assertOk();
        $this->login($this->hr)->postJson('/app/api/requests/'.$request.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'decision' => 'approve', 'reason' => 'Synthetic HR evidence review'])->assertOk();
        $this->postJson('/app/api/requests/'.$request.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'decision' => 'approve', 'reason' => 'Cannot also be the Finance reviewer'])->assertForbidden();
        $this->assertSame(1, $this->worker->employee->fresh()->version);
        $this->login($finance)->postJson('/app/api/requests/'.$request.'/decide', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 3, 'decision' => 'approve', 'reason' => 'Synthetic Finance review; any net correction needs separate approved amount'])->assertOk();
        $this->assertSame($old->job_id, $this->worker->employee->assignments()->at('2026-10-01')->sole()->job_id);
        $this->assertSame($job->id, $this->worker->employee->assignments()->at('2026-10-25')->sole()->job_id);
        $this->assertDatabaseHas('career_retrospectives', ['employee_id' => $this->worker->employee_id, 'hr_reviewer_id' => $this->hr->id, 'finance_reviewer_id' => $finance->id]);
    }
}
