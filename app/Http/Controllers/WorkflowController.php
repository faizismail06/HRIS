<?php

namespace App\Http\Controllers;

use App\Domain\Workflow\WorkflowAccess;
use App\Domain\Workflow\WorkflowListing;
use App\Domain\Workflow\WorkflowRegistry;
use App\Domain\Workflow\WorkflowService;
use App\Http\Requests\OperationalRequest;
use App\Models\WorkflowRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WorkflowController extends Controller
{
    public function bulkDecide(OperationalRequest $r, WorkflowService $s)
    {
        $data = $r->validate(['items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.id' => ['required', 'integer', 'distinct'], 'items.*.expected_version' => ['required', 'integer', 'min:1'], 'decision' => ['required', 'in:approve,reject'], 'reason' => [$r->input('decision') === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $results = [];
        foreach ($data['items'] as $item) {
            $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $r->key().'|'.$item['id']);
            try {
                $result = $s->decide($r->user(), $item['id'], $item['expected_version'], $key, $data['decision'], $data['reason'] ?? null);
                $results[] = ['id' => $item['id'], 'http_status' => 200, 'result' => $result];
            } catch (ValidationException $e) {
                $results[] = ['id' => $item['id'], 'http_status' => 422, 'message' => 'Validasi domain gagal; periksa pengajuan sebelum mengulang.'];
            } catch (HttpExceptionInterface $e) {
                $results[] = ['id' => $item['id'], 'http_status' => $e->getStatusCode(), 'message' => 'Pengajuan tidak dapat diputuskan dengan izin/versi ini.'];
            } catch (ModelNotFoundException $e) {
                $results[] = ['id' => $item['id'], 'http_status' => 404, 'message' => 'Pengajuan tidak tersedia.'];
            }
        }

        return response()->json(['data' => $results]);
    }

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
        $page = app(WorkflowListing::class)->page($r->user(), $data['status'] ?? null, (int) ($data['cursor'] ?? 0));

        return response()->json(['data' => $page['data'], 'next_cursor' => $page['next_cursor']]);
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
