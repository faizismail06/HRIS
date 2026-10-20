<?php

namespace App\Domain\Attendance;

use App\Domain\Workflow\WorkflowHandler;
use App\Models\RosterShift;
use App\Models\User;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OvertimeHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['manager', 'hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);
        $data = Validator::make($input, ['roster_shift_id' => ['required', 'integer'], 'planned_start' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'planned_end' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:planned_start'], 'task' => ['required', 'string', 'max:500'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        $shift = RosterShift::where('employee_id', $actor->employee_id)->where('status', 'published')->findOrFail($data['roster_shift_id']);
        $start = CarbonImmutable::parse($data['planned_start']);
        $end = CarbonImmutable::parse($data['planned_end']);
        abort_unless($start->setTimezone($shift->timezone)->toDateString() === $shift->work_date && $end->getTimestamp() - $start->getTimestamp() <= 86400, 422, 'Lembur harus merujuk hari kerja roster dan durasi paling lama24jam untuk satu pengajuan.');
        abort_if(app(AttendanceService::class)->closed($shift), 422, 'Periode sudah ditutup; gunakan penyesuaian terpisah.');

        return ['employee_id' => $actor->employee_id, 'payload' => $data, 'summary' => ['roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'planned_start' => $data['planned_start'], 'planned_end' => $data['planned_end'], 'task' => $data['task']]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        abort_if(DB::table('overtime_authorizations')->where('employee_id', $r->employee_id)->whereIn('status', ['pending', 'approved', 'verified'])->where('planned_start', '<', $r->payload['planned_end'])->where('planned_end', '>', $r->payload['planned_start'])->exists(), 422, 'Rentang lembur bertumpuk.');
        DB::table('overtime_authorizations')->insert(['workflow_request_id' => $r->id, 'employee_id' => $r->employee_id, 'roster_shift_id' => $r->payload['roster_shift_id'], 'planned_start' => $r->payload['planned_start'], 'planned_end' => $r->payload['planned_end'], 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
    }

    public function approved(WorkflowRequest $r): void
    {
        $shift = RosterShift::findOrFail($r->payload['roster_shift_id']);
        abort_if(app(AttendanceService::class)->closed($shift), 409, 'Periode sudah ditutup; otorisasi memerlukan penyesuaian terpisah.');
        DB::table('overtime_authorizations')->where('workflow_request_id', $r->id)->update(['status' => 'approved', 'updated_at' => now()->toIso8601String()]);
    }

    public function released(WorkflowRequest $r): void
    {
        DB::table('overtime_authorizations')->where('workflow_request_id', $r->id)->update(['status' => 'released', 'updated_at' => now()->toIso8601String()]);
    }
}
