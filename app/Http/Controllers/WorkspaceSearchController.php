<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Documents\DocumentService;
use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceSearchController extends Controller
{
    public function __invoke(Request $r)
    {
        $d = $r->validate(['q' => ['required', 'string', 'min:2', 'max:80'], 'as_of' => ['nullable', 'date_format:Y-m-d']]);
        $actor = $r->user();
        $date = $d['as_of'] ?? today()->toDateString();
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $d['q']).'%';
        $results = [];
        foreach (EmployeeVisibility::query($actor, $date)->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('employee_number', 'ilike', $like))->orderBy('id')->limit(10)->get() as $employee) {
            $results[] = ['type' => 'employee', 'id' => $employee->id, 'label' => $employee->name, 'reference' => $employee->employee_number, 'path' => '/app/employees/'.$employee->id];
        }
        foreach (WorkflowRequest::where(fn ($q) => $q->whereRaw('summary::text ILIKE ?', [$like])->orWhereRaw('public_id::text ILIKE ?', [$like])->orWhere('kind', 'ilike', $like))->orderByDesc('id')->limit(500)->get()->filter(fn ($w) => WorkflowAccess::visible($actor, $w))->take(10) as $workflow) {
            $results[] = ['type' => 'request', 'id' => $workflow->id, 'label' => $workflow->kind, 'reference' => $workflow->public_id, 'status' => $workflow->status, 'path' => '/app/api/requests/'.$workflow->id];
        }
        foreach (DB::table('issued_documents')->where('number', 'ilike', $like)->orderByDesc('id')->limit(500)->get(['id', 'employee_id', 'number', 'kind']) as $doc) {
            if (! DocumentService::visible($actor, $doc->employee_id)) {
                continue;
            }
            $results[] = ['type' => 'document', 'id' => $doc->id, 'label' => $doc->kind, 'reference' => $doc->number, 'path' => '/app/api/documents/'.$doc->id.'/download'];
            if (count(array_filter($results, fn ($v) => $v['type'] === 'document')) >= 10) {
                break;
            }
        }
        $assets = DB::table('assets')->where(fn ($q) => $q->where('code', 'ilike', $like)->orWhere('serial', 'ilike', $like))->orderBy('id');
        if (! Access::companyPermission($actor, 'assets.manage')) {
            if (! $actor->employee_id || ! $actor->grants()->where('role', 'employee')->where('scope', 'self')->exists()) {
                $assets->whereRaw('1=0');
            } else {
                $assets->whereIn('id', DB::table('asset_custodies')->where('employee_id', $actor->employee_id)->whereNull('returned_at')->select('asset_id'));
            }
        }
        foreach ($assets->limit(10)->get(['id', 'code', 'category']) as $asset) {
            $results[] = ['type' => 'asset', 'id' => $asset->id, 'label' => $asset->category, 'reference' => $asset->code, 'path' => Access::companyPermission($actor, 'assets.manage') ? '/app/api/assets' : '/app/api/my-assets'];
        }

        return response()->json(['q' => $d['q'], 'as_of' => $date, 'data' => $results, 'limit_per_type' => 10, 'candidate_scan_limit' => 500])->header('Cache-Control', 'no-store, private');
    }
}
