<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditWorkspaceController extends Controller
{
    public function index(Request $r)
    {
        // Company audit mandate is explicit; technical administration alone grants none.
        abort_unless($r->user()->grants()->where('role', 'auditor')->where('scope', 'company')->exists(), 403);
        $d = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);
        $q = DB::table('audit_entries')->select('id', 'actor_id', 'action', 'subject_type', 'subject_id', 'created_at')->orderByDesc('id');
        if (! empty($d['q'])) {
            $q->where('action', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $d['q']).'%');
        }
        if (! empty($d['starts_on'])) {
            $q->whereDate('created_at', '>=', $d['starts_on']);
        }
        if (! empty($d['ends_on'])) {
            $q->whereDate('created_at', '<=', $d['ends_on']);
        }
        $entries = $q->paginate(40)->withQueryString();
        Audit::record($r->user()->id, 'audit.metadata_read', 'audit_entries');

        return view('workspace.audit', compact('entries'));
    }
}
