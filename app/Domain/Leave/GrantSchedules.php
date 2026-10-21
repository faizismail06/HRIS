<?php

namespace App\Domain\Leave;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GrantSchedules
{
    public function create(User $actor, string $key, array $input): array
    {
        $d = Validator::make($input, [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'name' => ['required', 'string', 'max:120'],
            'cadence' => ['required', 'in:annual,monthly'],
            'expiry_rule' => ['required', 'in:calendar_year_end'],
            'first_grant_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:first_grant_on'],
            'minutes' => ['required', 'integer', 'between:1,1000000'],
            'minimum_tenure_days' => ['required', 'integer', 'between:0,36500'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'evidence' => ['required', 'string', 'max:4000'],
        ])->validate();
        $first = CarbonImmutable::parse($d['first_grant_on']);
        abort_unless($first->day <= 28 && $first->addYears(2)->toDateString() >= $d['ends_on'], 422, 'Gunakan tanggal 1–28 dan rentang maksimal dua tahun.');
        $d['employee_ids'] = array_map('intval', $d['employee_ids']);
        sort($d['employee_ids']);

        return Mutation::run($actor, 'leave.schedule.create', $key, $d, fn ($u) => $this->authorize($u), function ($u) use ($d) {
            $this->type($d['leave_type_id']);
            $id = DB::table('leave_grant_schedules')->insertGetId(array_replace(array_diff_key($d, ['expiry_rule' => true]), ['employee_ids' => json_encode($d['employee_ids']), 'evidence' => encrypt($d['evidence']), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::record($u->id, 'leave.schedule_drafted', 'leave_grant_schedule', $id, ['employee_count' => count($d['employee_ids'])]);

            return ['id' => $id, 'version' => 1, 'status' => 'draft'];
        });
    }

    public function decide(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['action' => ['required', 'in:approve,stop'], 'reason' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'leave.schedule.decide', $key, compact('id', 'version', 'd'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $d) {
            $s = DB::table('leave_grant_schedules')->where('id', $id)->firstOrFail();
            CoreWrite::version($s->version, $version);
            if ($d['action'] === 'approve') {
                abort_unless($s->status === 'draft', 409);
                abort_if($s->created_by === $u->id, 403, 'Jadwal hak harus disahkan HR Admin lain.');
                abort_if($s->first_grant_on < today()->toDateString(), 422, 'Tanggal awal sudah terlewati; buat draft baru.');
                $type = $this->type($s->leave_type_id);
                $employees = json_decode($s->employee_ids, true);
                abort_if($u->employee_id && in_array($u->employee_id, $employees, true), 403, 'Pengesah tidak boleh menjadi penerima jadwal.');
                foreach (DB::table('leave_grant_schedules')->join('leave_types', 'leave_types.id', '=', 'leave_grant_schedules.leave_type_id')->where('leave_types.code', $type->code)->whereIn('status', ['approved', 'stopped'])->where('first_grant_on', '<', $s->ends_on)->where('ends_on', '>', $s->first_grant_on)->get(['leave_grant_schedules.employee_ids']) as $other) {
                    abort_if(array_intersect($employees, json_decode($other->employee_ids, true)), 422, 'Jadwal hak untuk penerima/jenis/periode ini sudah disahkan.');
                }
                $changes = ['status' => 'approved', 'approved_by' => $u->id, 'approved_at' => now(), 'evidence' => encrypt(decrypt($s->evidence)."\nApproval: ".$d['reason'])];
            } else {
                abort_unless($s->status === 'approved', 409);
                $changes = ['status' => 'stopped', 'stopped_at' => now(), 'stop_reason' => encrypt($d['reason'])];
            }
            DB::table('leave_grant_schedules')->where('id', $id)->update($changes + ['version' => $version + 1, 'updated_at' => now()]);
            Audit::record($u->id, 'leave.schedule_'.$d['action'], 'leave_grant_schedule', $id, ['reason_provided' => true]);

            return ['id' => $id, 'status' => $changes['status'], 'version' => $version + 1];
        });
    }

    public function dispatch(): void
    {
        foreach (DB::table('leave_grant_schedules')->where('status', 'approved')->orderBy('id')->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
                $s = DB::table('leave_grant_schedules')->find($id);
                if ($s->status !== 'approved') {
                    return;
                }
                $approver = User::find($s->approved_by);
                $type = LeaveType::find($s->leave_type_id);
                if (! $approver || ! AccountSecurity::available($approver) || ! Access::companyPermission($approver, 'leave.manage') || ! $type?->is_active || ! $type->deducts_balance) {
                    return; // A revoked authority or superseded type never creates new entitlement.
                }
                $until = min($s->ends_on, today()->addDay()->toDateString());
                $executed = DB::table('leave_grant_runs')->where('leave_grant_schedule_id', $id)->get(['employee_id', 'grant_on'])->mapWithKeys(fn ($run) => [$run->employee_id.':'.$run->grant_on => true])->all();
                for ($date = CarbonImmutable::parse($s->first_grant_on); $date->toDateString() < $until; $date = $date->addMonths($s->cadence === 'annual' ? 12 : 1)) {
                    foreach (json_decode($s->employee_ids, true) as $employeeId) {
                        if (isset($executed[$employeeId.':'.$date->toDateString()])) {
                            continue;
                        }
                        $employee = Employee::findOrFail($employeeId);
                        $period = $employee->periods()->where('starts_on', '<=', $date->toDateString())->where('activation_state', 'active')->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $date->toDateString()))->first();
                        $eligible = $period && CarbonImmutable::parse($period->starts_on)->diffInDays($date) >= max($s->minimum_tenure_days, $type->minimum_tenure_days);
                        $lot = null;
                        if ($eligible) {
                            $lot = DB::table('leave_credit_lots')->insertGetId(['employee_id' => $employeeId, 'type_code' => $type->code, 'entitlement_year' => $date->year, 'valid_from' => $date->toDateString(), 'valid_until' => $date->startOfYear()->addYear()->toDateString(), 'minutes' => $s->minutes, 'source_key' => 'schedule:'.$id.':'.$employeeId.':'.$date->toDateString(), 'source_type' => $s->cadence === 'annual' ? 'grant' : 'accrual', 'granted_by' => $approver->id, 'created_at' => now()]);
                            DB::table('leave_ledger')->insert(['employee_id' => $employeeId, 'leave_credit_lot_id' => $lot, 'kind' => $s->cadence === 'annual' ? 'grant' : 'accrual', 'minutes' => $s->minutes, 'business_key' => 'credit:'.$lot, 'actor_id' => $approver->id, 'created_at' => now()]);
                        }
                        $runId = DB::table('leave_grant_runs')->insertGetId(['leave_grant_schedule_id' => $id, 'employee_id' => $employeeId, 'grant_on' => $date->toDateString(), 'leave_credit_lot_id' => $lot, 'outcome' => $eligible ? 'granted' : 'ineligible', 'created_at' => now()]);
                        Audit::record(null, 'leave.schedule_executed', 'leave_grant_run', $runId, ['schedule_id' => $id, 'employee_id' => $employeeId, 'grant_on' => $date->toDateString(), 'outcome' => $eligible ? 'granted' : 'ineligible']);
                    }
                }
            }, 3);
        }
    }

    private function authorize(User $actor): void
    {
        abort_unless(Access::companyPermission($actor, 'leave.manage'), 403);
    }

    private function type(int $id): LeaveType
    {
        $type = LeaveType::findOrFail($id);
        abort_unless($type->is_active && $type->deducts_balance, 422);

        return $type;
    }
}
