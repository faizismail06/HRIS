<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Access;
use App\Domain\Identity\AccountAdministration;
use App\Domain\Identity\Audit;
use App\Http\Requests\OperationalRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AccountAdministrationController extends Controller
{
    public function show(Request $r, int $id)
    {
        abort_unless(Access::companyPermission($r->user(), 'accounts.manage'), 403);
        $target = User::findOrFail($id);
        Audit::record($r->user()->id, 'account.access_read', 'user', $id);

        return response()->json(['id' => $target->id, 'name' => $target->name, 'email' => $target->email, 'employee_id' => $target->employee_id, 'is_active' => $target->is_active, 'version' => $target->security_version, 'auditor_export_enabled' => $target->auditor_export_enabled, 'grants' => $target->grants()->get(['role', 'scope', 'branch_id', 'department_id'])])->header('Cache-Control', 'no-store, private');
    }

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
