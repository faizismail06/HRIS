<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Access;
use App\Domain\Workflow\ApproverPolicy;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CareerHandler implements ApproverPolicy, WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function mayApprove(User $actor, WorkflowRequest $request, string $role): bool
    {
        return Access::companyPermission($actor, 'employees.review');
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.manage'), 403);
        $p = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'base_version' => ['required', 'integer', 'min:1'], 'effective_on' => ['required', 'date_format:Y-m-d', 'after:today'], 'reason' => ['required', 'string', 'max:1000']] + EmployeeFields::assignment())->validate();
        $employee = Employee::findOrFail($p['employee_id']);
        CoreWrite::version($employee->version, $p['base_version']);
        $before = $employee->assignments()->at($p['effective_on'])->firstOrFail();
        abort_unless($before->valid_from->toDateString() < $p['effective_on'], 422);
        EmployeeFields::validatePlacement($p, $p['effective_on'], $employee->id);

        return ['employee_id' => $employee->id, 'payload' => $p, 'summary' => ['effective_on' => $p['effective_on'], 'before' => $before->only(['branch_id', 'department_id', 'job_id', 'manager_employee_id']), 'after' => array_intersect_key($p, EmployeeFields::assignment())]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        abort_unless(DB::table('private_attachments')->where('subject_type', 'workflow_request')->where('subject_id', $r->id)->exists(), 422, 'Dokumen pendukung perubahan karier diperlukan.');
        $data = $r->payload;
        $data['kind'] = 'assignment';
        $change = app(EmployeeWriter::class)->propose(User::findOrFail($r->submitted_by), $r->employee_id, $data);
        $change->update(['workflow_request_id' => $r->id]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $change = EmployeeChangeRequest::where('workflow_request_id', $r->id)->firstOrFail();
        app(EmployeeWriter::class)->review(User::findOrFail($r->effectActorId()), $change->id, ['version' => $change->version, 'decision' => 'approve', 'review_note' => 'Approved through career workflow #'.$r->id], $r->id);
    }

    public function released(WorkflowRequest $r): void
    {
        $change = EmployeeChangeRequest::where('workflow_request_id', $r->id)->first();
        if ($change && $change->status === 'pending') {
            $change->update(['status' => 'rejected', 'version' => $change->version + 1]);
        }
    }
}
