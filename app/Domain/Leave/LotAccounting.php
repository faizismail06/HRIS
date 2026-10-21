<?php

namespace App\Domain\Leave;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LotAccounting
{
    public static function capacity(object $lot, ?string $before = null): int
    {
        return $lot->minutes - (int) DB::table('leave_lot_changes')->where('leave_credit_lot_id', $lot->id)->when($before, fn ($q) => $q->where('created_at', '<', $before))->sum('minutes');
    }

    public static function remaining(object $lot): int
    {
        return max(0, self::capacity($lot) - (int) DB::table('leave_allocations')->where('leave_credit_lot_id', $lot->id)->whereIn('state', ['reserved', 'used'])->sum('minutes'));
    }

    public function change(User $actor, int $id, string $key, array $input): array
    {
        $d = Validator::make($input, ['kind' => ['required', 'in:debit,carry_out'], 'minutes' => ['required', 'integer', 'between:1,1000000'], 'reason' => ['required', 'string', 'max:2000'], 'valid_from' => ['required_if:kind,carry_out', 'date_format:Y-m-d'], 'valid_until' => ['required_if:kind,carry_out', 'date_format:Y-m-d', 'after:valid_from']])->validate();

        return Mutation::run($actor, 'leave.lot.change', $key, compact('id', 'd'), fn ($u) => abort_unless(Access::companyPermission($u, 'leave.manage'), 403), function ($u) use ($id, $d) {
            $lot = DB::table('leave_credit_lots')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_if($u->employee_id && $u->employee_id === $lot->employee_id, 403, 'Adjustment hak sendiri harus dicatat HR lain.');
            abort_if($d['minutes'] > self::remaining($lot), 422, 'Kredit bebas tidak cukup; reservasi dan penggunaan tidak boleh diambil.');
            $target = null;
            if ($d['kind'] === 'carry_out') {
                abort_unless($lot->valid_until <= today()->toDateString() && $d['valid_from'] >= $lot->valid_until && (int) substr($d['valid_from'], 0, 4) > $lot->entitlement_year, 422, 'Carry-over berasal dari lot kedaluwarsa ke tahun hak berikutnya.');
                abort_if(DB::table('leave_lot_changes')->where('leave_credit_lot_id', $id)->where('kind', 'carry_out')->exists(), 409, 'Lot sumber sudah ditransfer.');
                abort_unless(LeaveType::where('code', $lot->type_code)->where('is_active', true)->where('deducts_balance', true)->exists(), 422);
                $target = DB::table('leave_credit_lots')->insertGetId(['employee_id' => $lot->employee_id, 'type_code' => $lot->type_code, 'entitlement_year' => (int) substr($d['valid_from'], 0, 4), 'valid_from' => $d['valid_from'], 'valid_until' => $d['valid_until'], 'minutes' => $d['minutes'], 'source_key' => 'carry:'.$id, 'source_type' => 'carry_over', 'granted_by' => $u->id, 'created_at' => now()]);
                DB::table('leave_ledger')->insert(['employee_id' => $lot->employee_id, 'leave_credit_lot_id' => $target, 'kind' => 'carry_over', 'minutes' => $d['minutes'], 'business_key' => 'credit:'.$target, 'actor_id' => $u->id, 'created_at' => now()]);
            } else {
                abort_unless($lot->valid_from <= today()->toDateString() && $lot->valid_until > today()->toDateString(), 422, 'Debit adjustment hanya untuk lot yang masih berlaku.');
            }
            $change = DB::table('leave_lot_changes')->insertGetId(['leave_credit_lot_id' => $id, 'target_lot_id' => $target, 'kind' => $d['kind'], 'minutes' => $d['minutes'], 'reason' => encrypt($d['reason']), 'actor_id' => $u->id, 'created_at' => now()]);
            DB::table('leave_ledger')->insert(['employee_id' => $lot->employee_id, 'leave_credit_lot_id' => $id, 'kind' => $d['kind'] === 'debit' ? 'adjustment_debit' : 'carry_out', 'minutes' => -$d['minutes'], 'business_key' => 'lot-change:'.$change, 'actor_id' => $u->id, 'created_at' => now()]);
            $this->expire($lot);
            Audit::record($u->id, 'leave.lot_'.$d['kind'], 'leave_lot_change', $change, ['source_lot_id' => $id, 'target_lot_id' => $target, 'minutes' => $d['minutes'], 'reason_provided' => true]);

            return ['id' => $change, 'target_lot_id' => $target];
        });
    }

    public function reconcileExpiry(): void
    {
        DB::table('leave_credit_lots')->where('valid_until', '<=', today()->toDateString())->orderBy('id')->chunkById(200, function ($lots) {
            foreach ($lots as $lot) {
                DB::transaction(function () use ($lot) {
                    DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
                    $this->expire($lot);
                }, 3);
            }
        });
    }

    private function expire(object $lot): void
    {
        if ($lot->valid_until > today()->toDateString()) {
            return;
        }
        // Pending reservations retain their entitlement for the original work date.
        // Releases/cancellations after expiry are reconciled by a new entry, never an edit.
        $entries = DB::table('leave_ledger')->where('leave_credit_lot_id', $lot->id)->whereIn('kind', ['expiry', 'expiry_reversal']);
        $delta = self::remaining($lot) + (int) (clone $entries)->sum('minutes');
        if ($delta === 0) {
            return;
        }
        DB::table('leave_ledger')->insert(['employee_id' => $lot->employee_id, 'leave_credit_lot_id' => $lot->id, 'kind' => $delta > 0 ? 'expiry' : 'expiry_reversal', 'minutes' => -$delta, 'business_key' => 'expiry:'.$lot->id.':'.((clone $entries)->count() + 1), 'actor_id' => null, 'created_at' => now()]);
    }
}
