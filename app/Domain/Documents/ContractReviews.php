<?php

namespace App\Domain\Documents;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractReviews
{
    public function recommend(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['decision' => ['required', 'in:renew,amend,end,hr_review'], 'reason' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'contract.recommend', $key, compact('id', 'version', 'd'), function ($u) use ($id, $d) {
            $t = DB::table('contract_review_tasks')->where('id', $id)->firstOrFail();
            $c = DB::table('employment_contracts')->find($t->employment_contract_id);
            abort_unless($c && WorkflowAccess::eligible($u, $d['decision'] === 'hr_review' ? 'hr_admin' : 'manager', $c->employee_id), 403);
        }, function ($u) use ($id, $version, $d) {
            $t = DB::table('contract_review_tasks')->where('id', $id)->firstOrFail();
            CoreWrite::version($t->version, $version);
            abort_if($t->status === 'resolved', 409);
            if ($d['decision'] === 'hr_review') {
                $contract = DB::table('employment_contracts')->find($t->employment_contract_id);
                $manager = Employee::findOrFail($contract->employee_id)->assignments()->at(today()->toDateString())->value('manager_employee_id');
                abort_if($manager && ! DB::table('contract_recommendations')->where('contract_review_task_id', $id)->whereIn('decision', ['renew', 'amend', 'end'])->exists(), 422, 'Rekomendasi manager diperlukan sebelum review HR.');
                if (! $manager) {
                    Audit::record($u->id, 'contract.manager_exception_recorded', 'contract_review_task', $id, ['reason_provided' => true]);
                }
            }
            DB::table('contract_recommendations')->insert(['contract_review_task_id' => $id, 'actor_id' => $u->id, 'decision' => $d['decision'], 'reason' => encrypt($d['reason']), 'created_at' => now()]);
            DB::table('contract_review_tasks')->where('id', $id)->update(['status' => $d['decision'] === 'hr_review' ? 'hr_reviewed' : 'manager_recommended', 'recommendation' => encrypt($d), 'version' => $version + 1, 'updated_at' => now()]);
            Audit::record($u->id, 'contract.recommendation_recorded', 'contract_review_task', $id, ['decision' => $d['decision']]);

            return ['id' => $id, 'version' => $version + 1];
        });
    }
}
