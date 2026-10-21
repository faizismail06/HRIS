<?php

namespace App\Domain\Assets;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetSettlements
{
    public function resolve(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['resolution' => ['required', 'in:no_charge,separate_agreement'], 'evidence' => ['required', 'string', 'max:4000']])->validate();

        return Mutation::run($actor, 'asset.settlement.resolve', $key, compact('id', 'version', 'd'), fn ($u) => abort_unless(Access::companyPermission($u, 'payroll.approve'), 403), function ($u) use ($id, $version, $d) {
            $s = DB::table('asset_settlement_cases')->where('id', $id)->firstOrFail();
            $owner = DB::table('asset_custodies')->where('id', $s->asset_custody_id)->value('employee_id');
            abort_if($u->employee_id && $u->employee_id === $owner, 403, 'Settlement aset sendiri memerlukan Finance lain.');
            CoreWrite::version($s->version, $version);
            abort_unless($s->status === 'open', 409);
            DB::table('asset_settlement_cases')->where('id', $id)->update(['status' => 'resolved', 'resolution' => $d['resolution'], 'resolution_evidence' => encrypt($d['evidence']), 'resolved_by' => $u->id, 'resolved_at' => now(), 'version' => $version + 1, 'updated_at' => now()]);
            Audit::record($u->id, 'asset.settlement_resolved', 'asset_settlement_case', $id, ['resolution' => $d['resolution'], 'evidence_provided' => true, 'automatic_salary_deduction' => false]);

            return ['id' => $id, 'version' => $version + 1, 'status' => 'resolved'];
        });
    }
}
