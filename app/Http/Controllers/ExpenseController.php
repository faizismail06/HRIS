<?php

namespace App\Http\Controllers;

use App\Domain\Expenses\ExpenseService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function policy(OperationalRequest $r, ExpenseService $s)
    {
        return response()->json($s->policy($r->user(), $r->key(), $r->all()), 201);
    }

    public function allocate(OperationalRequest $r, int $id, ExpenseService $s)
    {
        return response()->json($s->allocate($r->user(), $id, $r->key(), $r->all()), 201);
    }

    public function settle(OperationalRequest $r, int $id, ExpenseService $s)
    {
        return response()->json($s->settle($r->user(), $id, $r->key(), $r->all()));
    }

    public function detail(Request $r, int $id, ExpenseService $s)
    {
        return response()->json($s->detail($r->user(), $id))->header('Cache-Control', 'no-store, private');
    }
}
