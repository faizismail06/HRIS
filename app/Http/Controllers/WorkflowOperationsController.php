<?php

namespace App\Http\Controllers;

use App\Domain\Operations\NotificationPreferences;
use App\Domain\Workflow\Delegations;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;

class WorkflowOperationsController extends Controller
{
    public function delegate(OperationalRequest $r, Delegations $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function revoke(OperationalRequest $r, int $id, Delegations $s)
    {
        return response()->json($s->revoke($r->user(), $id, $r->key()));
    }

    public function preferences(Request $r)
    {
        return response()->json(NotificationPreferences::get($r->user()->id))->header('Cache-Control', 'no-store, private');
    }

    public function updatePreferences(OperationalRequest $r, NotificationPreferences $s)
    {
        return response()->json($s->update($r->user(), $r->key(), $r->all()));
    }
}
