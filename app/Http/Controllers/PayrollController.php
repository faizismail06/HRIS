<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Payroll\PaymentService;
use App\Domain\Payroll\PayrollPolicyService;
use App\Domain\Payroll\PayrollService;
use App\Domain\Workflow\WorkflowAccess;
use App\Http\Requests\OperationalRequest;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function policyDetail(Request $r, int $id)
    {
        abort_unless(Access::companyPermission($r->user(), 'payroll.prepare') || Access::companyPermission($r->user(), 'payroll.approve'), 403);
        $p = DB::table('payroll_policies')->where('id', $id)->firstOrFail(['id', 'code', 'version', 'valid_from', 'valid_until', 'rules', 'official_sources', 'status', 'created_by', 'approved_by']);

        return response()->json($p)->header('Cache-Control', 'no-store, private');
    }

    public function proposedCompensation(Request $r, int $id)
    {
        $workflow = WorkflowRequest::where('kind', 'compensation')->findOrFail($id);
        abort_unless(WorkflowAccess::visible($r->user(), $workflow), 404);
        abort_unless(Access::companyPermission($r->user(), 'payroll.prepare') || WorkflowAccess::eligible($r->user(), 'finance_approver', $workflow->employee_id), 404);
        Audit::record($r->user()->id, 'compensation.proposed_read', 'workflow_request', $id);

        return response()->json(['id' => $id, 'data' => $workflow->payload])->header('Cache-Control', 'no-store, private');
    }

    public function policy(OperationalRequest $r, PayrollPolicyService $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function approvePolicy(OperationalRequest $r, int $id, PayrollPolicyService $s)
    {
        $r->validate(['evidence' => ['required', 'string', 'max:4000']]);

        return response()->json($s->approve($r->user(), $id, $r->key(), $r->input('evidence')));
    }

    public function create(OperationalRequest $r, PayrollService $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function calculate(OperationalRequest $r, int $id, PayrollService $s)
    {
        return response()->json($s->calculate($r->user(), $id, $r->version(), $r->key()));
    }

    public function transition(OperationalRequest $r, int $id, string $action, PayrollService $s)
    {
        $result = $s->transition($r->user(), $id, $r->version(), $r->key(), $action, $r->except(['idempotency_key', 'expected_version']));

        return response()->json($result, $result['http_status'] ?? 200);
    }

    public function show(Request $r, int $id, PayrollService $s)
    {
        return response()->json($s->show($r->user(), $id))->header('Cache-Control', 'no-store, private');
    }

    public function payslip(Request $r, int $id, PayrollService $s)
    {
        return $s->payslip($r->user(), $id);
    }

    public function bankTemplate(OperationalRequest $r, PaymentService $s)
    {
        return response()->json($s->template($r->user(), $r->key(), $r->all()), 201);
    }

    public function confirmTemplate(OperationalRequest $r, int $id, PaymentService $s)
    {
        return response()->json($s->confirm($r->user(), $id, $r->key()));
    }

    public function export(OperationalRequest $r, int $id, PaymentService $s)
    {
        $r->validate(['template_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);

        return response()->json($s->export($r->user(), $id, (int) $r->input('template_id'), $r->key(), $r->input('reason')), 201);
    }

    public function downloadExport(Request $r, int $id, PaymentService $s)
    {
        $r->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $s->download($r->user(), $id, $r->input('reason'));
    }

    public function outcome(OperationalRequest $r, int $id, PaymentService $s)
    {
        return response()->json($s->outcome($r->user(), $id, $r->key(), $r->all()));
    }
}
