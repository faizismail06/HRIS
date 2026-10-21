<?php

namespace App\Http\Controllers;

use App\Domain\Leave\LeaveService;
use App\Domain\Leave\LotAccounting;
use App\Http\Requests\OperationalRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function change(OperationalRequest $r, int $id, LotAccounting $s)
    {
        return response()->json($s->change($r->user(), $id, $r->key(), $r->all()), 201);
    }

    public function type(OperationalRequest $r, LeaveService $s)
    {
        return response()->json($s->type($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function grant(OperationalRequest $r, LeaveService $s)
    {
        return response()->json($s->grant($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function balances(Request $r, LeaveService $s)
    {
        return response()->json($s->balances($r->user()));
    }

    public function types(Request $r)
    {
        abort_unless($r->user()->employee_id, 403);

        return response()->json(LeaveType::where('is_active', true)->get(['id', 'code', 'name', 'version', 'is_paid', 'deducts_balance', 'allows_half_day', 'requires_evidence', 'notice_days']));
    }
}
