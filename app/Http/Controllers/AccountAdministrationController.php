<?php

namespace App\Http\Controllers;

use App\Domain\Identity\AccountAdministration;
use App\Http\Requests\OperationalRequest;

class AccountAdministrationController extends Controller
{
    public function access(OperationalRequest $r, int $id, AccountAdministration $service)
    {
        return response()->json($service->access($r->user(), $id, $r->key(), $r->version(), $r->all()));
    }

    public function suspend(OperationalRequest $r, int $id, AccountAdministration $service)
    {
        return response()->json($service->suspend($r->user(), $id, $r->key(), $r->version(), $r->all()), 201);
    }

    public function revoke(OperationalRequest $r, int $id, AccountAdministration $service)
    {
        $r->validate(['reason' => ['required', 'string', 'max:1000']]);

        return response()->json($service->revokeSuspension($r->user(), $id, $r->key(), $r->input('reason')));
    }
}
