<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\SensitiveProfiles;
use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowAccess;
use App\Http\Requests\OperationalRequest;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;

class PrivateProfileController extends Controller
{
    public function show(Request $request, int $id, string $category, SensitiveProfiles $profiles)
    {
        return response()->json($profiles->read($request->user(), $id, $category))->header('Cache-Control', 'no-store, private');
    }

    public function proposed(Request $request, int $id)
    {
        $r = WorkflowRequest::findOrFail($id);
        abort_unless(in_array($r->kind, ['profile_change', 'identity_change', 'bank_change', 'tax_change']) && WorkflowAccess::visible($request->user(), $r), 404);
        $stepAccess = $r->steps()->get()->contains(fn ($s) => in_array($request->user()->id, $s->approver_ids, true) && WorkflowAccess::stepEligible($request->user(), $r, $s));
        abort_unless(SensitiveProfiles::allowed($request->user(), $r->employee_id, $r->payload['category']) || $stepAccess, 404);
        Audit::record($request->user()->id, 'profile.proposed_read', 'workflow_request', $id, ['category' => $r->payload['category']]);

        return response()->json(['id' => $id, 'category' => $r->payload['category'], 'data' => $r->payload['data'], 'reason' => $r->payload['reason'], 'base_version' => $r->payload['base_version']])->header('Cache-Control', 'no-store, private');
    }

    public function field(OperationalRequest $request, SensitiveProfiles $profiles)
    {
        return response()->json($profiles->fields($request->user(), $request->key(), $request->all()), 201);
    }
}
