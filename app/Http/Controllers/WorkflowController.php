<?php

namespace App\Http\Controllers;

use App\Domain\Workflow\WorkflowAccess;
use App\Domain\Workflow\WorkflowRegistry;
use App\Domain\Workflow\WorkflowService;
use App\Http\Requests\OperationalRequest;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function definitions(OperationalRequest $r, WorkflowService $service)
    {
        return response()->json($service->define($r->user(), $r->key(), $r->except(['idempotency_key'])));
    }

    public function store(OperationalRequest $r, WorkflowService $service, WorkflowRegistry $registry)
    {
        $data = $r->validate(['kind' => ['required', Rule::in($registry->kinds())], 'payload' => ['required', 'array']]);

        return response()->json($service->draft($r->user(), $r->key(), $data['kind'], $data['payload']), 201);
    }

    public function index(Request $r)
    {
        $data = $r->validate(['status' => ['nullable', 'in:draft,in_review,blocked,approved,rejected,withdrawn'], 'cursor' => ['nullable', 'integer', 'min:0']]);
        // Cursor scans preserve permission filtering before returning any row or count.
        $query = WorkflowRequest::where('id', '>', (int) ($data['cursor'] ?? 0))->orderBy('id');
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        $rows = [];
        $cursor = (int) ($data['cursor'] ?? 0);
        foreach ($query->limit(200)->get() as $record) {
            $cursor = $record->id;
            if (WorkflowAccess::visible($r->user(), $record)) {
                $rows[] = $record;
            } if (count($rows) === 20) {
                break;
            }
        }

        return response()->json(['data' => $rows, 'next_cursor' => $cursor]);
    }

    public function show(Request $r, string $id)
    {
        $record = WorkflowRequest::findOrFail($id);
        abort_unless(WorkflowAccess::visible($r->user(), $record), 404);

        return response()->json(['request' => $record, 'steps' => $record->steps()->orderBy('position')->get()]);
    }

    public function submit(OperationalRequest $r, string $id, WorkflowService $s)
    {
        return response()->json($s->submit($r->user(), (int) $id, $r->version(), $r->key()));
    }

    public function decide(OperationalRequest $r, string $id, WorkflowService $s)
    {
        return response()->json($s->decide($r->user(), (int) $id, $r->version(), $r->key(), (string) $r->input('decision'), $r->input('reason')));
    }

    public function withdraw(OperationalRequest $r, string $id, WorkflowService $s)
    {
        return response()->json($s->withdraw($r->user(), (int) $id, $r->version(), $r->key(), (string) $r->input('reason')));
    }

    public function reassign(OperationalRequest $r, string $id, WorkflowService $s)
    {
        return response()->json($s->reassign($r->user(), (int) $id, $r->version(), $r->key(), (int) $r->input('replacement_id'), (string) $r->input('reason')));
    }
}
