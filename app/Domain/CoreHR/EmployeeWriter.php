<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Audit;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmployeeWriter
{
    public function create(User $actor, array $data): Employee
    {
        return CoreWrite::run(function () use ($actor, $data) {
            $actor = CoreWrite::actor($actor, 'employees.manage');
            // Revalidate references while the organization write lock is held.
            Validator::make($data, EmployeeFields::identity() + EmployeeFields::assignment())->validate();
            EmployeeFields::validatePlacement($data, $data['starts_on']);
            $employee = Employee::create(array_intersect_key($data, array_flip(['name', 'work_email', 'employee_number'])) + ['version' => 1]);
            $period = $employee->periods()->create(['starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null]);
            $assignment = $employee->assignments()->create(array_intersect_key($data, EmployeeFields::assignment()) + ['employment_period_id' => $period->id, 'valid_from' => $data['starts_on'], 'valid_to' => $data['ends_on'] ?? null]);
            Audit::record($actor->id, 'employee.created', 'employee', $employee->id, ['identity' => $employee->only(['name', 'employee_number', 'work_email']), 'period' => $period->only(['starts_on', 'ends_on']), 'assignment' => $assignment->only(['branch_id', 'department_id', 'job_id', 'manager_employee_id'])]);

            return $employee;
        });
    }

    public function propose(User $actor, int $employeeId, array $data): EmployeeChangeRequest
    {
        return CoreWrite::run(function () use ($actor, $employeeId, $data) {
            $actor = CoreWrite::actor($actor, 'employees.manage');
            $employee = Employee::query()->lockForUpdate()->findOrFail($employeeId);
            CoreWrite::version($employee->version, $data['base_version']);
            $kind = $data['kind'];
            abort_unless(in_array($kind, ['identity', 'assignment']), 422);
            $rules = $kind === 'identity' ? EmployeeFields::identity($employeeId) : EmployeeFields::assignment();
            Validator::make($data, $rules)->validate();
            $defaults = $kind === 'identity' ? $employee->only(array_keys($rules)) : array_fill_keys(array_keys($rules), null);
            $payload = array_replace($defaults, array_intersect_key($data, $rules));
            $before = $employee->only(array_keys($rules));
            if ($kind === 'assignment') {
                EmployeeFields::validatePlacement($payload, $data['effective_on'], $employeeId);
                $before = $this->assignmentAt($employee, $data['effective_on'])->only(array_keys($rules));
            }
            $change = EmployeeChangeRequest::create(['employee_id' => $employeeId, 'kind' => $kind, 'payload' => $payload, 'before_payload' => $before, 'base_version' => $employee->version, 'effective_on' => $kind === 'assignment' ? $data['effective_on'] : null, 'reason' => $data['reason'], 'requested_by' => $actor->id, 'version' => 1]);
            Audit::record($actor->id, 'employee.change_requested', 'employee_change_request', $change->id, ['employee_id' => $employeeId, 'kind' => $kind, 'base_version' => $employee->version]);

            return $change;
        });
    }

    public function review(User $actor, int $id, array $data, ?int $workflowRequestId = null): EmployeeChangeRequest
    {
        return CoreWrite::run(function () use ($actor, $id, $data, $workflowRequestId) {
            $actor = CoreWrite::actor($actor, 'employees.review');
            $change = EmployeeChangeRequest::query()->lockForUpdate()->findOrFail($id);
            abort_if($change->workflow_request_id && $change->workflow_request_id !== $workflowRequestId, 409, 'Perubahan ini harus diputuskan melalui tahap workflow terkait.');
            CoreWrite::version($change->version, $data['version']);
            abort_unless($change->status === 'pending', 409, 'Pengajuan sudah diputuskan.');
            abort_if($actor->id === $change->requested_by || $actor->employee_id === $change->employee_id, 403, 'Pengajuan harus ditinjau orang lain.');
            $employee = Employee::query()->lockForUpdate()->findOrFail($change->employee_id);
            $approved = $data['decision'] === 'approve';
            $before = [];
            if ($approved) {
                CoreWrite::version($employee->version, $change->base_version);
                $rules = $change->kind === 'identity' ? EmployeeFields::identity($employee->id) : EmployeeFields::assignment();
                Validator::make($change->payload, $rules)->validate();
                if ($change->kind === 'identity') {
                    $before = $employee->only(array_keys($rules));
                    $employee->fill(array_intersect_key($change->payload, $rules));
                } else {
                    $date = $change->effective_on->toDateString();
                    EmployeeFields::validatePlacement($change->payload, $date, $employee->id);
                    $previous = $this->assignmentAt($employee, $date);
                    $before = $previous->only(['branch_id', 'department_id', 'job_id', 'manager_employee_id', 'valid_from', 'valid_to']);
                    $until = $previous->valid_to?->toDateString();
                    $previous->update(['valid_to' => $date]);
                    $employee->assignments()->create(array_intersect_key($change->payload, $rules) + ['employment_period_id' => $previous->employment_period_id, 'valid_from' => $date, 'valid_to' => $until]);
                }
                $employee->version++;
                $employee->save();
            }
            $change->fill(['status' => $approved ? 'approved' : 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'], 'version' => $change->version + 1])->save();
            Audit::record($actor->id, $approved ? 'employee.change_approved' : 'employee.change_rejected', 'employee_change_request', $change->id, ['employee_id' => $employee->id, 'kind' => $change->kind, 'effective_on' => $change->effective_on?->toDateString(), 'before' => $before, 'after' => $approved ? $change->payload : [], 'employee_version' => $employee->version]);

            return $change;
        });
    }

    private function assignmentAt(Employee $employee, string $date): mixed
    {
        if ($date <= today()->toDateString()) {
            throw ValidationException::withMessages(['effective_on' => 'Tanggal efektif harus setelah hari ini. Ajukan ulang jika tanggal pengajuan sudah terlewati.']);
        }
        $assignment = $employee->assignments()->at($date)->lockForUpdate()->first();
        if (! $assignment || $assignment->valid_from->toDateString() >= $date) {
            throw ValidationException::withMessages(['effective_on' => 'Tanggal harus berada di dalam penempatan yang ada, setelah tanggal mulainya.']);
        }

        return $assignment;
    }
}
