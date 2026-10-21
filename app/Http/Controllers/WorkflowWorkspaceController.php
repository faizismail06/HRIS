<?php

namespace App\Http\Controllers;

use App\Domain\Operations\PrivateFiles;
use App\Domain\Workflow\WorkflowAccess;
use App\Domain\Workflow\WorkflowListing;
use App\Domain\Workflow\WorkflowService;
use App\Http\Requests\OperationalRequest;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\RosterShift;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class WorkflowWorkspaceController extends Controller
{
    public const KINDS = ['leave' => 'Cuti', 'leave_cancel' => 'Pembatalan cuti', 'overtime' => 'Lembur', 'correction' => 'Koreksi kehadiran'];

    public const STATUSES = ['draft' => 'Draf', 'in_review' => 'Dalam peninjauan', 'blocked' => 'Menunggu penetapan peninjau', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'withdrawn' => 'Ditarik'];

    public function index(Request $r, WorkflowListing $listing)
    {
        $data = $r->validate(['status' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))], 'cursor' => ['nullable', 'integer', 'min:0']]);

        return view('workspace.requests.index', $listing->page($r->user(), $data['status'] ?? null, (int) ($data['cursor'] ?? 0)) + ['statuses' => self::STATUSES, 'kinds' => self::KINDS, 'status' => $data['status'] ?? '']);
    }

    public function create(Request $r)
    {
        abort_unless($r->user()->employee_id, 403);
        $data = $r->validate(['kind' => ['nullable', 'in:'.implode(',', array_keys(self::KINDS))]]);
        $kind = $data['kind'] ?? 'leave';
        $shifts = RosterShift::where('employee_id', $r->user()->employee_id)->where('status', 'published')->whereBetween('work_date', [today()->subDays(8)->toDateString(), today()->addDays(90)->toDateString()])->orderBy('work_date')->limit(200)->get();

        return view('workspace.requests.create', ['kind' => $kind, 'kinds' => self::KINDS, 'types' => LeaveType::where('is_active', true)->orderBy('name')->get(), 'shifts' => $shifts, 'originals' => WorkflowRequest::where('employee_id', $r->user()->employee_id)->whereIn('kind', ['leave', 'leave_exception'])->where('status', 'approved')->orderByDesc('id')->limit(100)->get()]);
    }

    public function store(OperationalRequest $r, WorkflowService $service)
    {
        abort_unless($r->user()->employee_id, 403);
        $data = $r->validate(['kind' => ['required', 'in:'.implode(',', array_keys(self::KINDS))]]);
        $kind = $data['kind'];
        $payload = $r->only(match ($kind) {
            'leave' => ['leave_type_id', 'starts_on', 'ends_on', 'segment', 'reason'],
            'leave_cancel' => ['original_request_id', 'reason'],
            'overtime' => ['roster_shift_id', 'planned_start', 'planned_end', 'task', 'reason'],
            'correction' => ['roster_shift_id', 'effective_in', 'effective_out', 'reason'],
        });
        if (in_array($kind, ['overtime', 'correction'])) {
            $r->validate(['roster_shift_id' => ['required', 'integer']]);
            $shift = RosterShift::where('employee_id', $r->user()->employee_id)->where('status', 'published')->findOrFail($r->integer('roster_shift_id'));
            $fields = $kind === 'overtime' ? ['planned_start', 'planned_end'] : ['effective_in', 'effective_out'];
            foreach ($fields as $field) {
                $r->validate([$field => ['required', 'date_format:Y-m-d\TH:i']]);
                $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $r->input($field), $shift->timezone);
                $payload[$field] = $local->toIso8601String();
            }
        }

        return $this->mutate(function () use ($r, $service, $kind, $payload) {
            $result = $service->draft($r->user(), $r->key(), $kind, $payload);

            return to_route('workspace.requests.show', $result['id'])->with('status', 'Draf disimpan. Periksa rincian dan lengkapi bukti sebelum dikirim.');
        });
    }

    public function show(Request $r, string $id)
    {
        $record = WorkflowRequest::findOrFail($id);
        abort_unless(WorkflowAccess::visible($r->user(), $record), 404);
        $steps = $record->steps()->orderBy('position')->get();
        $supported = array_key_exists($record->kind, self::KINDS);
        $medical = in_array($record->kind, ['leave', 'leave_cancel']);
        $canReadMedical = $medical && app(PrivateFiles::class)->canReadMedical($r->user(), $record);
        $details = $supported ? $record->payload : [];
        if ($medical && ! $canReadMedical) {
            unset($details['reason']);
        }
        $person = $record->employee_id ? Employee::find($record->employee_id) : null;
        $employeeLabel = $person ? $person->name.' · '.$person->employee_number : null;
        $leaveType = $record->kind === 'leave' ? LeaveType::find($record->payload['leave_type_id'])?->name : null;
        $overtimeId = $record->kind === 'overtime' ? DB::table('overtime_authorizations')->where('workflow_request_id', $record->id)->value('id') : null;
        $canDecide = $supported && $record->status === 'in_review' && $record->submitted_by !== $r->user()->id && $record->employee_id !== $r->user()->employee_id && $steps->contains(fn ($step) => $step->status === 'pending' && in_array($r->user()->id, $step->approver_ids, true) && WorkflowAccess::stepEligible($r->user(), $record, $step));

        return view('workspace.requests.show', ['record' => $record, 'steps' => $steps, 'statuses' => self::STATUSES, 'kinds' => self::KINDS, 'employeeLabel' => $employeeLabel, 'leaveType' => $leaveType, 'overtimeId' => $overtimeId, 'canDecide' => $canDecide, 'supported' => $supported, 'owned' => $record->submitted_by === $r->user()->id, 'details' => $details, 'attachments' => $supported ? DB::table('private_attachments')->where('subject_type', 'workflow_request')->where('subject_id', $record->id)->when(! $canReadMedical, fn ($q) => $q->where('classification', '!=', 'medical'))->select('id', 'mime', 'size')->get() : collect()]);
    }

    public function action(OperationalRequest $r, string $id, string $action, WorkflowService $service)
    {
        $record = WorkflowRequest::findOrFail($id);
        abort_unless(WorkflowAccess::visible($r->user(), $record), 404);
        abort_unless(array_key_exists($record->kind, self::KINDS), 403);

        return $this->mutate(function () use ($r, $id, $action, $service) {
            $version = $r->version();
            match ($action) {
                'submit' => $service->submit($r->user(), (int) $id, $version, $r->key()),
                'withdraw' => $service->withdraw($r->user(), (int) $id, $version, $r->key(), (string) $r->input('withdrawal_reason')),
                'decide' => $service->decide($r->user(), (int) $id, $version, $r->key(), (string) $r->input('decision'), $r->input('reason')),
            };

            return to_route('workspace.requests.show', $id)->with('status', 'Perubahan pengajuan berhasil disimpan.');
        });
    }

    public function upload(OperationalRequest $r, string $id, PrivateFiles $files)
    {
        $r->validate(['file' => ['required', 'file']]);

        return $this->mutate(function () use ($r, $id, $files) {
            $files->upload($r->user(), (int) $id, $r->file('file'), $r->key());

            return to_route('workspace.requests.show', $id)->with('status', 'Bukti berhasil diunggah.');
        });
    }

    private function mutate(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['operation' => $e->getStatusCode() === 409 ? 'Data sudah berubah atau tindakan tidak lagi tersedia. Muat ulang halaman, periksa status terbaru, lalu ulangi tindakan.' : ($e->getMessage() ?: 'Pengajuan belum memenuhi aturan. Periksa rincian, jadwal, bukti dan konfigurasi alur persetujuan.')]);
        }
    }
}
