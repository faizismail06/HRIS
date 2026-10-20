<?php

namespace App\Domain\Attendance;

use App\Domain\Workflow\ParticipantWorkflow;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SwapHandler implements ParticipantWorkflow, WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['counterparty', 'manager', 'hr_admin'];
    }

    public function validateSteps(array $steps): void
    {
        abort_unless(count($steps) >= 2 && $steps[0]['role'] === 'counterparty' && in_array($steps[count($steps) - 1]['role'], ['manager', 'hr_admin']) && count(array_filter($steps, fn ($s) => $s['role'] === 'counterparty')) === 1, 422, 'Tukar shift wajib persetujuan rekan lebih dahulu, lalu manager/HR.');
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);
        $data = Validator::make($input, ['source_shift_id' => ['required', 'integer'], 'target_shift_id' => ['required', 'integer', 'different:source_shift_id'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $source = RosterShift::where('employee_id', $actor->employee_id)->where('status', 'published')->where('is_day_off', false)->findOrFail($data['source_shift_id']);
        $target = RosterShift::where('status', 'published')->where('is_day_off', false)->where('employee_id', '<>', $actor->employee_id)->findOrFail($data['target_shift_id']);
        abort_unless($source->snapshot['branch_id'] === $target->snapshot['branch_id'] && $source->snapshot['department_id'] === $target->snapshot['department_id'], 404);
        abort_unless($source->work_date > today()->toDateString() && $target->work_date > today()->toDateString(), 422, 'Tukar shift hanya untuk hari mendatang.');
        $this->changes($source, $target);

        return ['employee_id' => $actor->employee_id, 'payload' => $data, 'summary' => ['source_shift_id' => $source->id, 'target_shift_id' => $target->id, 'source_version' => $source->version, 'target_version' => $target->version, 'source_work_date' => $source->work_date, 'target_work_date' => $target->work_date, 'colleague_employee_id' => $target->employee_id]];
    }

    public function participants(WorkflowRequest $r, string $role): ?array
    {
        return $role === 'counterparty' ? User::where('employee_id', $r->summary['colleague_employee_id'])->where('is_active', true)->whereHas('grants', fn ($q) => $q->where('role', 'employee')->where('scope', 'self'))->pluck('id')->all() : null;
    }

    public function participantEligible(User $actor, WorkflowRequest $r, string $role): bool
    {
        return $role === 'counterparty' && $actor->employee_id === $r->summary['colleague_employee_id'] && $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists();
    }

    public function submitted(WorkflowRequest $r): void
    {
        $source = RosterShift::where('status', 'published')->findOrFail($r->payload['source_shift_id']);
        $target = RosterShift::where('status', 'published')->findOrFail($r->payload['target_shift_id']);
        abort_unless($source->version === $r->summary['source_version'] && $target->version === $r->summary['target_version'], 409);
        $this->changes($source, $target);
    }

    public function approved(WorkflowRequest $r): void
    {
        $source = RosterShift::where('status', 'published')->findOrFail($r->payload['source_shift_id']);
        $target = RosterShift::where('status', 'published')->findOrFail($r->payload['target_shift_id']);
        abort_unless($source->version === $r->summary['source_version'] && $target->version === $r->summary['target_version'], 409);
        [$old,$new] = $this->changes($source, $target);
        $checksum = hash('sha256', json_encode($new, JSON_THROW_ON_ERROR));
        $roster = RosterVersion::create(['status' => 'published', 'version' => 1, 'checksum' => $checksum, 'created_by' => $r->effectActorId(), 'published_at' => now(), 'supersedes_id' => $source->roster_version_id, 'reason' => 'Approved swap '.$r->public_id]);
        foreach ($old as $id) {
            $item = RosterShift::findOrFail($id);
            $item->update(['status' => 'superseded', 'version' => $item->version + 1]);
        }
        foreach ($new as $row) {
            RosterShift::create($row + ['roster_version_id' => $roster->id, 'status' => 'published', 'version' => 1]);
        }
    }

    public function released(WorkflowRequest $r): void {}

    private function changes(RosterShift $source, RosterShift $target): array
    {
        $old = [$source->id, $target->id];
        $fields = ['employee_id', 'work_date', 'is_day_off', 'starts_at', 'ends_at', 'timezone', 'snapshot'];
        $new = [];
        if ($source->work_date !== $target->work_date) {
            foreach ([[$source, $target], [$target, $source]] as [$from,$to]) {
                $off = RosterShift::where('employee_id', $from->employee_id)->where('work_date', $to->work_date)->where('status', 'published')->where('is_day_off', true)->first();
                abort_unless($off, 422, 'Hari penerima harus mempunyai roster libur, bukan shift lain atau jadwal kosong.');
                $old[] = $off->id;
                $new[] = array_replace($from->only($fields), ['is_day_off' => true, 'starts_at' => null, 'ends_at' => null]);
            }
        }
        $new[] = array_replace($target->only($fields), ['employee_id' => $source->employee_id]);
        $new[] = array_replace($source->only($fields), ['employee_id' => $target->employee_id]);
        foreach ($old as $id) {
            $shift = RosterShift::findOrFail($id);
            abort_if(DB::table('attendance_events')->where('roster_shift_id', $id)->exists() || DB::table('leave_segments')->where('roster_shift_id', $id)->whereIn('state', ['reserved', 'approved'])->exists() || DB::table('overtime_authorizations')->where('roster_shift_id', $id)->whereIn('status', ['pending', 'approved', 'verified'])->exists(), 422, 'Sumber sudah memiliki attendance/cuti/lembur; selesaikan pengecualian terlebih dahulu.');
            abort_if(app(AttendanceService::class)->closed($shift), 422);
        }
        foreach ($new as $row) {
            $person = Employee::findOrFail($row['employee_id']);
            $placement = $person->assignments()->at($row['work_date'])->first();
            abort_unless(in_array($person->statusAt($row['work_date']), ['ACTIVE', 'OFFBOARDING']) && $placement && $placement->branch_id === $row['snapshot']['branch_id'] && $placement->department_id === $row['snapshot']['department_id'], 422, 'Penerima tidak memenuhi penempatan/lokasi/tugas pada tanggal itu.');
            if (! $row['is_day_off']) {
                abort_unless(isset($row['snapshot']['job_id']) && $placement->job_id === $row['snapshot']['job_id'], 422, 'Jabatan penerima tidak memenuhi tugas shift ini.');
                abort_if(RosterShift::where('employee_id', $row['employee_id'])->where('status', 'published')->whereNotIn('id', $old)->where('starts_at', '<', $row['ends_at'])->where('ends_at', '>', $row['starts_at'])->exists(), 422, 'Pertukaran menimbulkan shift bertumpuk.');
            }
        }

        return [array_unique($old), $new];
    }
}
