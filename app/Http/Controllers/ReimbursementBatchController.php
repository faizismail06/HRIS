<?php

namespace App\Http\Controllers;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Expenses\ReimbursementBatches;
use App\Domain\Identity\Audit;
use App\Domain\Payroll\PayrollService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReimbursementBatchController extends Controller
{
    public function store(OperationalRequest $r, ReimbursementBatches $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function decide(OperationalRequest $r, int $id, ReimbursementBatches $s)
    {
        return response()->json($s->decide($r->user(), $id, $r->version(), $r->key(), $r->all()));
    }

    public function export(OperationalRequest $r, int $id, ReimbursementBatches $s)
    {
        return response()->json($s->export($r->user(), $id, $r->key(), $r->all()), 201);
    }

    public function outcome(OperationalRequest $r, int $id, ReimbursementBatches $s)
    {
        return response()->json($s->outcome($r->user(), $id, $r->key(), $r->all()));
    }

    public function show(Request $r, int $id)
    {
        PayrollService::authorize($r->user(), true);
        $b = DB::table('expense_batches')->where('id', $id)->firstOrFail();
        Audit::record($r->user()->id, 'expense.batch_read', 'expense_batch', $id);
        $instructions = DB::table('expense_bank_instructions')->where('expense_batch_id', $id)->get(['id', 'public_id', 'sha256', 'created_at']);

        return response()->json(['id' => $id, 'version' => $b->version, 'status' => $b->status, 'rows' => decrypt($b->snapshot), 'instructions' => $instructions, 'instruction_items' => DB::table('expense_instruction_items')->whereIn('expense_bank_instruction_id', $instructions->pluck('id'))->get(), 'outcomes' => DB::table('expense_instruction_outcomes')->whereIn('expense_instruction_item_id', DB::table('expense_instruction_items')->whereIn('expense_bank_instruction_id', $instructions->pluck('id'))->select('id'))->get()])->header('Cache-Control', 'no-store, private');
    }

    public function download(Request $r, int $id)
    {
        PayrollService::authorize($r->user(), true);
        abort_if(DemoBoundary::enabled() || app()->environment('demo'), 403);
        $r->validate(['reason' => ['required', 'string', 'max:2000']]);
        $instruction = DB::table('expense_bank_instructions')->where('id', $id)->firstOrFail();
        $bytes = base64_decode(decrypt($instruction->content), true);
        abort_unless($bytes !== false && hash_equals($instruction->sha256, hash('sha256', $bytes)), 503);
        Audit::record($r->user()->id, 'expense.bank_instruction_downloaded', 'expense_bank_instruction', $id, ['reason_provided' => true, 'checksum' => $instruction->sha256]);

        return response($bytes)->header('Content-Type', 'text/csv; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="reimbursement-'.$instruction->public_id.'.csv"')->header('Cache-Control', 'no-store, private');
    }
}
