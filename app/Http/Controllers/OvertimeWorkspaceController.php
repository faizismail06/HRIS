<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Attendance\OvertimeService;
use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowAccess;
use App\Http\Requests\OperationalRequest;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OvertimeWorkspaceController extends Controller
{
    public const STATUSES = ['pending' => 'Otorisasi menunggu keputusan', 'approved' => 'Otorisasi disetujui', 'verified' => 'Aktual diverifikasi', 'released' => 'Dilepas', 'cancelled' => 'Dibatalkan'];

    public function index(Request $r)
    {
        $d = $r->validate(['cursor' => ['nullable', 'integer', 'min:0'], 'status' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))], 'roster_shift_id' => ['nullable', 'integer', 'min:1']]);
        $cursor = (int) ($d['cursor'] ?? 0);
        $query = DB::table('overtime_authorizations')->where('id', '>', $cursor)->orderBy('id');
        if (! empty($d['status'])) {
            $query->where('status', $d['status']);
        }
        if (! empty($d['roster_shift_id'])) {
            $query->where('roster_shift_id', $d['roster_shift_id']);
        }
        $rows = [];
        $scanned = 0;
        foreach ($query->limit(200)->get() as $record) {
            $cursor = $record->id;
            $scanned++;
            if (! $this->visible($r, $record)) {
                continue;
            }
            $rows[] = ['record' => $record, 'person' => Employee::findOrFail($record->employee_id), 'shift' => RosterShift::findOrFail($record->roster_shift_id)];
            if (count($rows) === 20) {
                break;
            }
        }

        return view('workspace.overtime.index', ['rows' => $rows, 'statuses' => self::STATUSES, 'status' => $d['status'] ?? '', 'shiftId' => $d['roster_shift_id'] ?? null, 'cursor' => $cursor, 'hasMore' => count($rows) === 20 || $scanned === 200]);
    }

    public function show(Request $r, string $id, AttendanceService $attendance)
    {
        $record = DB::table('overtime_authorizations')->where('id', $id)->firstOrFail();
        abort_unless($this->visible($r, $record), 404);
        $shift = RosterShift::findOrFail($record->roster_shift_id);
        $result = $attendance->recalculate($shift);
        $own = $r->user()->employee_id === $record->employee_id;
        $closed = (bool) $attendance->closed($shift);
        $canVerify = ! $own && (Access::companyPermission($r->user(), 'attendance.close') || WorkflowAccess::eligible($r->user(), 'manager', $record->employee_id));

        return view('workspace.overtime.show', ['record' => $record, 'shift' => $shift, 'person' => Employee::findOrFail($record->employee_id), 'result' => $result, 'statuses' => self::STATUSES, 'own' => $own, 'closed' => $closed, 'canVerify' => $canVerify, 'canExcept' => Access::companyPermission($r->user(), 'attendance.close'), 'hasException' => (bool) $record->exception_reason, 'stale' => $record->status === 'verified' && $record->attendance_version !== $result->version]);
    }

    public function actual(OperationalRequest $r, string $id, OvertimeService $s)
    {
        $record = DB::table('overtime_authorizations')->where('id', $id)->firstOrFail();
        abort_unless($r->user()->employee_id === $record->employee_id, 404);
        $shift = RosterShift::findOrFail($record->roster_shift_id);
        $data = $r->validate(['actual_start' => ['required', 'date_format:Y-m-d\TH:i'], 'actual_end' => ['required', 'date_format:Y-m-d\TH:i', 'after:actual_start']]);
        foreach ($data as $key => $value) {
            $data[$key] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $shift->timezone)->toIso8601String();
        }

        return $this->mutate(function () use ($r, $id, $s, $data) {
            $s->actual($r->user(), (int) $id, $r->version(), $r->key(), $data);

            return to_route('workspace.overtime.show', $id)->with('status', 'Aktual lembur tersimpan. Menunggu verifikasi atasan atau HR; durasi belum menjadi hak bayar.');
        });
    }

    public function verify(OperationalRequest $r, string $id, OvertimeService $s)
    {
        $record = DB::table('overtime_authorizations')->where('id', $id)->firstOrFail();
        abort_unless($this->visible($r, $record), 404);
        $r->validate(['exception_reason' => ['nullable', 'string', 'max:1000']]);
        if ($r->filled('exception_reason')) {
            abort_unless(Access::companyPermission($r->user(), 'attendance.close'), 403);
        }

        return $this->mutate(function () use ($r, $id, $s) {
            $s->verify($r->user(), (int) $id, $r->version(), $r->key(), $r->input('exception_reason'));

            return to_route('workspace.overtime.show', $id)->with('status', 'Lembur aktual diverifikasi. Durasi yang dapat dibayar mengikuti hasil pemeriksaan dan pengurangan istirahat.');
        });
    }

    private function visible(Request $r, object $record): bool
    {
        if ($r->user()->employee_id === $record->employee_id || Access::companyPermission($r->user(), 'attendance.close') || WorkflowAccess::eligible($r->user(), 'manager', $record->employee_id)) {
            return true;
        }
        $workflow = WorkflowRequest::find($record->workflow_request_id);

        return $workflow && WorkflowAccess::visible($r->user(), $workflow);
    }

    private function mutate(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['overtime' => $e->getMessage() ?: 'Lembur belum dapat diproses. Muat ulang, periksa versi, otorisasi dan kehadiran.']);
        }
    }
}
