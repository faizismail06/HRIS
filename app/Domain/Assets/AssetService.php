<?php

namespace App\Domain\Assets;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssetService
{
    private function authorize(User $u): void
    {
        abort_unless(Access::companyPermission($u, 'assets.manage'), 403);
    }

    public function create(User $actor, string $key, array $input): array
    {
        $this->authorize($actor);
        $d = Validator::make($input, ['code' => ['required', 'string', 'max:50'], 'serial' => ['nullable', 'string', 'max:100'], 'category' => ['required', 'string', 'max:80'], 'branch_id' => ['required', 'integer', 'exists:branches,id'], 'condition' => ['required', Rule::in(['new', 'good', 'worn', 'damaged'])]])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'asset.create', $key, $d, fn ($u) => $this->authorize($u), function ($u) use ($d) {
            $id = DB::table('assets')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'asset.registered', 'asset', $id);

            return ['id' => $id, 'version' => 1];
        }));
    }

    public function issue(User $actor, int $id, int $version, string $key, array $input): array
    {
        $this->authorize($actor);
        $d = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'evidence' => ['required', 'string', 'max:2000']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'asset.issue', $key, compact('id', 'version', 'd'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $d) {
            $a = DB::table('assets')->where('id', $id)->firstOrFail();
            CoreWrite::version($a->version, $version);
            abort_unless($a->status === 'available' && $a->condition !== 'damaged', 409, 'Aset tidak tersedia untuk serah terima.');
            abort_unless(in_array(Employee::findOrFail($d['employee_id'])->statusAt(today()->toDateString()), ['PREBOARDING', 'ACTIVE', 'OFFBOARDING']), 422, 'Custodian harus mempunyai periode kerja.');
            $custody = DB::table('asset_custodies')->insertGetId(['asset_id' => $id, 'employee_id' => $d['employee_id'], 'issued_at' => now(), 'issued_by' => $u->id, 'issue_evidence' => encrypt($d['evidence']), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('assets')->where('id', $id)->update(['status' => 'issued', 'version' => $version + 1, 'updated_at' => now()]);
            Audit::record($u->id, 'asset.issued', 'asset_custody', $custody, ['asset_id' => $id, 'employee_id' => $d['employee_id']]);

            return ['id' => $custody, 'version' => 1, 'asset_version' => $version + 1];
        }));
    }

    public function accept(User $actor, int $id, int $version, string $key): array
    {
        return Mutation::run($actor, 'asset.accept', $key, compact('id', 'version'), function ($u) use ($id) {
            $c = DB::table('asset_custodies')->where('id', $id)->firstOrFail();
            abort_unless($c->employee_id === $u->employee_id && $u->grants()->where('role', 'employee')->where('scope', 'self')->exists(), 404);
        }, function ($u) use ($id, $version) {
            $c = DB::table('asset_custodies')->where('id', $id)->firstOrFail();
            CoreWrite::version($c->version, $version);
            abort_if($c->returned_at || $c->accepted_at, 409);
            DB::table('asset_custodies')->where('id', $id)->update(['accepted_at' => now(), 'version' => $version + 1, 'updated_at' => now()]);
            Audit::record($u->id, 'asset.custody_accepted', 'asset_custody', $id);

            return ['id' => $id, 'version' => $version + 1];
        });
    }

    public function returnAsset(User $actor, int $id, int $version, string $key, array $input): array
    {
        $this->authorize($actor);
        $d = Validator::make($input, ['condition' => ['required', Rule::in(['good', 'worn', 'damaged', 'lost'])], 'evidence' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'asset.return', $key, compact('id', 'version', 'd'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $d) {
            $c = DB::table('asset_custodies')->where('id', $id)->firstOrFail();
            CoreWrite::version($c->version, $version);
            abort_if($c->returned_at, 409);
            DB::table('asset_custodies')->where('id', $id)->update(['returned_at' => now(), 'returned_by' => $u->id, 'return_evidence' => encrypt($d['evidence']), 'version' => $version + 1, 'updated_at' => now()]);
            $status = match ($d['condition']) {
                'damaged' => 'repair', 'lost' => 'lost', default => 'available'
            };
            DB::table('assets')->where('id', $c->asset_id)->update(['status' => $status, 'condition' => $d['condition'], 'updated_at' => now()]);
            DB::table('assets')->where('id', $c->asset_id)->increment('version');
            if (in_array($d['condition'], ['damaged', 'lost'])) {
                DB::table('asset_settlement_cases')->insert(['asset_custody_id' => $id, 'classification' => $d['condition'], 'evidence' => encrypt($d['evidence']), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            Audit::record($u->id, 'asset.return_recorded', 'asset_custody', $id, ['condition' => $d['condition'], 'settlement_required' => in_array($d['condition'], ['damaged', 'lost'])]);

            return ['id' => $id, 'version' => $version + 1, 'status' => $status];
        });
    }

    public function consumable(User $actor, string $key, array $input): array
    {
        $this->authorize($actor);
        $d = Validator::make($input, ['item_code' => ['required', 'string', 'max:80'], 'employee_id' => ['required', 'integer', 'exists:employees,id'], 'quantity' => ['required', 'integer', 'between:1,10000'], 'evidence' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'asset.consumable', $key, $d, fn ($u) => $this->authorize($u), function ($u) use ($d) {
            $id = DB::table('consumable_issues')->insertGetId(array_replace($d, ['evidence' => encrypt($d['evidence']), 'issued_by' => $u->id, 'issued_at' => now()]));
            Audit::record($u->id, 'asset.consumable_issued', 'consumable_issue', $id, ['quantity' => $d['quantity']]);

            return ['id' => $id];
        });
    }
}
