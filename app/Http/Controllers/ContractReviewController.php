<?php

namespace App\Http\Controllers;

use App\Domain\Workflow\WorkflowAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractReviewController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->grants()->whereIn('role', ['manager', 'hr_admin'])->exists(), 403);
        $data = [];
        foreach (DB::table('contract_review_tasks')->where('status', '<>', 'resolved')->orderBy('id')->limit(500)->get() as $t) {
            $c = DB::table('employment_contracts')->find($t->employment_contract_id);
            if ($c && (WorkflowAccess::eligible($r->user(), 'manager', $c->employee_id) || WorkflowAccess::eligible($r->user(), 'hr_admin', $c->employee_id))) {
                $data[] = ['id' => $t->id, 'version' => $t->version, 'status' => $t->status, 'contract_id' => $c->id, 'employee_id' => $c->employee_id, 'contract_type' => $c->contract_type, 'starts_on' => $c->starts_on, 'ends_on' => $c->ends_on, 'renewal_sequence' => $c->renewal_sequence];
            }
        }

        return response()->json(['data' => $data])->header('Cache-Control', 'no-store, private');
    }
}
