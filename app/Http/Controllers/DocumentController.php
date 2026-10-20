<?php

namespace App\Http\Controllers;

use App\Domain\Documents\DocumentService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function template(OperationalRequest $r, DocumentService $service)
    {
        return response()->json($service->template($r->user(), $r->key(), $r->all()), 201);
    }

    public function download(Request $r, int $id, DocumentService $service)
    {
        return $service->download($r->user(), $id);
    }

    public function contracts(Request $r, int $id)
    {
        abort_unless(DocumentService::visible($r->user(), $id), 404);
        $r->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);
        $date = $r->input('as_of', today()->toDateString());
        $rows = DB::table('employment_contracts')->where('employee_id', $id)->orderByDesc('starts_on')->limit(100)->get(['id', 'employee_id', 'contract_type', 'renewal_sequence', 'starts_on', 'ends_on', 'status', 'amends_id', 'issued_document_id', 'version'])->map(function ($c) use ($date) {
            $c->effective_status = $c->status !== 'approved' ? strtoupper($c->status) : ($c->starts_on > $date ? 'SCHEDULED' : ($c->ends_on <= $date ? 'EXPIRED' : 'ACTIVE'));

            return $c;
        });

        return response()->json(['data' => $rows])->header('Cache-Control', 'no-store, private');
    }
}
