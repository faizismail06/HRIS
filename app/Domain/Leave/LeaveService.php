<?php

namespace App\Domain\Leave;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LeaveService
{
    public function type(User $actor, string $key, array $input): array
    {
        $data = Validator::make($input, ['code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:120'], 'is_paid' => ['required', 'boolean'], 'deducts_balance' => ['required', 'boolean'], 'allows_half_day' => ['required', 'boolean'], 'requires_evidence' => ['required', 'boolean'], 'notice_days' => ['required', 'integer', 'between:0,365'], 'minimum_tenure_days' => ['required', 'integer', 'between:0,3650']])->validate();

        return Mutation::run($actor, 'leave.type', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'leave.manage'), 403), function ($u) use ($data) {
            $version = (int) LeaveType::where('code', $data['code'])->max('version') + 1;
            LeaveType::where('code', $data['code'])->update(['is_active' => false]);
            $type = LeaveType::create($data + ['version' => $version, 'approved_by' => $u->id]);
            Audit::record($u->id, 'leave.type_approved', 'leave_type', $type->id, ['version' => $version]);

            return ['id' => $type->id, 'version' => $version];
        });
    }

    public function grant(User $actor, string $key, array $input): array
    {
        $data = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'type_code' => ['required', 'string', Rule::exists('leave_types', 'code')->where('is_active', true)], 'entitlement_year' => ['required', 'integer', 'between:2000,2200'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after:valid_from'], 'minutes' => ['required', 'integer', 'between:1,1000000'], 'source_key' => ['required', 'string', 'max:100'], 'source_type' => ['required', Rule::in(['grant', 'accrual', 'adjustment'])], 'reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'leave.grant', $key, $data, fn ($u) => abort_unless(Access::companyPermission($u, 'leave.manage'), 403), function ($u) use ($data) {
            abort_if($u->employee_id && $u->employee_id === (int) $data['employee_id'], 403, 'Hak cuti sendiri harus disahkan HR lain.');
            $existing = DB::table('leave_credit_lots')->where('source_key', $data['source_key'])->first();
            abort_if($existing, 409, 'Sumber kredit sudah diproses. Gunakan kunci retry yang sama.');
            $id = DB::table('leave_credit_lots')->insertGetId(array_diff_key($data, ['reason' => true]) + ['granted_by' => $u->id, 'created_at' => now()->toIso8601String()]);
            DB::table('leave_ledger')->insert(['employee_id' => $data['employee_id'], 'leave_credit_lot_id' => $id, 'kind' => $data['source_type'], 'minutes' => $data['minutes'], 'business_key' => 'credit:'.$id, 'actor_id' => $u->id, 'created_at' => now()->toIso8601String()]);
            Audit::record($u->id, 'leave.credit_granted', 'leave_credit_lot', $id, ['employee_id' => $data['employee_id'], 'minutes' => $data['minutes'], 'reason_provided' => true, 'reason_encrypted' => encrypt($data['reason'])]);

            return ['id' => $id, 'minutes' => $data['minutes']];
        });
    }

    public function balances(User $actor): array
    {
        abort_unless($actor->employee_id, 403);
        $date = today()->toDateString();
        $result = [];
        foreach (DB::table('leave_credit_lots')->where('employee_id', $actor->employee_id)->orderBy('valid_from')->get() as $lot) {
            $reserved = (int) DB::table('leave_allocations')->where('leave_credit_lot_id', $lot->id)->where('state', 'reserved')->sum('minutes');
            $used = (int) DB::table('leave_allocations')->where('leave_credit_lot_id', $lot->id)->where('state', 'used')->sum('minutes');
            $valid = $lot->valid_from <= $date && $lot->valid_until > $date;
            $result[] = ['lot_id' => $lot->id, 'type_code' => $lot->type_code, 'entitlement_year' => $lot->entitlement_year, 'valid_from' => $lot->valid_from, 'valid_until' => $lot->valid_until, 'credited_minutes' => $lot->minutes, 'adjusted_credit_minutes' => LotAccounting::capacity($lot), 'reserved_minutes' => $reserved, 'used_minutes' => $used, 'available_minutes' => $valid ? max(0, LotAccounting::capacity($lot) - $reserved - $used) : 0, 'state' => $valid ? 'valid' : ($lot->valid_from > $date ? 'future' : 'expired')];
        }

        return $result;
    }
}
