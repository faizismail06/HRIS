<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceService;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Operations\Clock;
use App\Domain\Operations\PrivateFiles;
use App\Http\Requests\OperationalRequest;
use App\Models\Employee;
use App\Models\RosterShift;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AttendanceWorkspaceController extends Controller
{
    public const STATUSES = ['DAY_OFF' => 'Hari libur', 'PRESENT' => 'Hadir', 'IN_PROGRESS' => 'Sedang bekerja', 'INCOMPLETE' => 'Belum lengkap', 'ABSENT' => 'Tanpa catatan kehadiran', 'SCHEDULED' => 'Terjadwal', 'ON_LEAVE' => 'Cuti', 'LEAVE_CONFLICT' => 'Kehadiran bertumpuk dengan cuti'];

    public function index(Request $r, AttendanceService $attendance)
    {
        $d = $r->validate(['starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d'], 'cursor' => ['nullable', 'integer', 'min:0'], 'view' => ['nullable', 'in:mine,team']]);
        $start = $d['starts_on'] ?? today()->subDay()->toDateString();
        $end = $d['ends_on'] ?? today()->addDays(7)->toDateString();
        if ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 31) {
            throw ValidationException::withMessages(['ends_on' => 'Pilih rentang berurutan, maksimal 31 hari.']);
        }
        $view = $d['view'] ?? ($r->user()->employee_id ? 'mine' : 'team');
        $query = RosterShift::where('status', 'published')->whereBetween('work_date', [$start, $end])->where('id', '>', (int) ($d['cursor'] ?? 0))->orderBy('id');
        if ($view === 'mine') {
            $query->where('employee_id', $r->user()->employee_id ?? 0);
        }
        $rows = [];
        $cursor = (int) ($d['cursor'] ?? 0);
        $scanned = 0;
        foreach ($query->limit(200)->get() as $shift) {
            $cursor = $shift->id;
            $scanned++;
            if (! EmployeeVisibility::query($r->user(), $shift->work_date)->whereKey($shift->employee_id)->exists()) {
                continue;
            }
            $rows[] = ['shift' => $shift, 'person' => Employee::find($shift->employee_id), 'result' => $attendance->recalculate($shift)];
            if (count($rows) === 20) {
                break;
            }
        }

        return view('workspace.attendance.index', ['rows' => $rows, 'start' => $start, 'end' => $end, 'view' => $view, 'cursor' => $cursor, 'hasMore' => count($rows) === 20 || $scanned === 200, 'statuses' => self::STATUSES, 'canSchedule' => self::canSchedule($r)]);
    }

    public static function canSchedule(Request $r): bool
    {
        return Access::companyPermission($r->user(), 'schedules.manage') || $r->user()->grants()->where('role', 'manager')->where('scope', 'team')->exists();
    }

    public function show(Request $r, string $id, AttendanceService $attendance, Clock $clock)
    {
        $shift = $this->visible($r, $id);
        $own = $r->user()->employee_id === $shift->employee_id;
        $events = DB::table('attendance_events')->where('roster_shift_id', $shift->id)->orderBy('occurred_at')->get(['direction', 'occurred_at', 'provenance']);
        $closed = (bool) $attendance->closed($shift);
        $now = $clock->now();
        $working = ! $shift->is_day_off && ! $closed;
        $hasIn = $events->contains('direction', 'in');
        $canIn = $own && $working && ! $hasIn && $now >= $shift->starts_at->subMinutes($shift->snapshot['clock_in_before_minutes']) && $now <= $shift->ends_at;
        $canOut = $own && $working && $hasIn && ! $events->contains('direction', 'out') && $now >= $shift->starts_at && $now <= $shift->ends_at->addMinutes($shift->snapshot['clock_out_after_minutes']);

        return view('workspace.attendance.show', ['shift' => $shift, 'person' => Employee::findOrFail($shift->employee_id), 'result' => $attendance->recalculate($shift), 'events' => $events, 'statuses' => self::STATUSES, 'own' => $own, 'closed' => $closed, 'canIn' => $canIn, 'canOut' => $canOut, 'mode' => $shift->snapshot['location']['attendance_mode'] ?? 'onsite', 'requiresPhoto' => (bool) ($shift->snapshot['location']['requires_photo'] ?? false)]);
    }

    public function clock(OperationalRequest $r, string $id, string $direction, AttendanceService $attendance)
    {
        $shift = $this->visible($r, $id);
        abort_unless($r->user()->employee_id === $shift->employee_id, 404);
        // Reject identity/time/simulation overrides with the same validator as the API.
        $r->merge(['roster_shift_id' => $shift->id]);
        $result = $attendance->clock($r->user(), $direction, $r->key(), $r->except(['_token', 'idempotency_key']));
        if ($r->expectsJson()) {
            return response()->json($result, isset($result['rejected']) ? 422 : 200);
        }
        if (isset($result['rejected'])) {
            return to_route('workspace.attendance.show', $id)->withErrors(['clock' => $result['message']]);
        }

        return to_route('workspace.attendance.show', $id)->with('status', $direction === 'in' ? 'Waktu masuk berhasil dicatat.' : 'Waktu pulang berhasil dicatat.');
    }

    public function photo(OperationalRequest $r, string $id, PrivateFiles $files)
    {
        $shift = $this->visible($r, $id);
        abort_unless($r->user()->employee_id === $shift->employee_id, 404);
        $r->validate(['file' => ['required', 'file']]);
        try {
            $files->shiftPhoto($r->user(), $shift->id, $r->file('file'), $r->key());
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }

            return to_route('workspace.attendance.show', $id)->withErrors(['file' => $e->getMessage() ?: 'Foto belum dapat diterima. Periksa format dan jendela shift.']);
        }

        return to_route('workspace.attendance.show', $id)->with('status', 'Foto tersimpan secara privat. Catat waktu dalam 15 menit agar bukti tetap berlaku.');
    }

    private function visible(Request $r, string $id): RosterShift
    {
        $shift = RosterShift::where('status', 'published')->findOrFail($id);
        abort_unless(EmployeeVisibility::query($r->user(), $shift->work_date)->whereKey($shift->employee_id)->exists(), 404);

        return $shift;
    }
}
