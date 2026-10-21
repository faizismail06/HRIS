<?php

namespace App\Domain\Documents;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractCancellationHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'documents.manage'), 403);
        $p = Validator::make($input, ['contract_id' => ['required', 'integer'], 'contract_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000']])->validate();
        $c = $this->contract($p);

        return ['employee_id' => $c->employee_id, 'payload' => $p, 'summary' => ['contract_id' => $c->id, 'starts_on' => $c->starts_on, 'ends_on' => $c->ends_on]];
    }

    private function contract(array $p): object
    {
        $c = DB::table('employment_contracts')->where('id', $p['contract_id'])->firstOrFail();
        CoreWrite::version($c->version, $p['contract_version']);
        abort_unless($c->status === 'approved' && $c->starts_on > today()->toDateString(), 422, 'Pembatalan versi ini hanya untuk kontrak terjadwal yang belum mulai. Perubahan kontrak aktif memerlukan amendemen atau keputusan employment terpisah.');
        abort_if(DB::table('employment_contracts')->where('status', 'approved')->where(fn ($q) => $q->where('amends_id', $c->id)->orWhere('renews_id', $c->id))->exists(), 422, 'Selesaikan amendemen/renewal terkait sebelum membatalkan kontrak sumber.');

        return $c;
    }

    public function submitted(WorkflowRequest $r): void
    {
        $this->contract($r->payload);
    }

    public function approved(WorkflowRequest $r): void
    {
        $c = $this->contract($r->payload);
        DB::table('employment_contracts')->where('id', $c->id)->update(['status' => 'cancelled', 'version' => $c->version + 1, 'updated_at' => now()]);
        DB::table('contract_review_tasks')->where('employment_contract_id', $c->id)->update(['status' => 'resolved', 'resolved_by' => $r->effectActorId(), 'updated_at' => now()]);
        Audit::record($r->effectActorId(), 'contract.scheduled_version_cancelled', 'employment_contract', $c->id, ['request_id' => $r->id, 'issued_document_retained' => true]);
    }

    public function released(WorkflowRequest $r): void {}
}
