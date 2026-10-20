<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Attendance\CalendarService;
use App\Domain\Attendance\ScheduleService;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Http\Requests\OperationalRequest;
use App\Models\RosterShift;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function calendarDate(OperationalRequest $r, CalendarService $s)
    {
        return response()->json($s->date($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function teamCalendar(Request $r, CalendarService $s)
    {
        return response()->json($s->team($r->user(), $r->all()));
    }

    public function location(OperationalRequest $r, ScheduleService $s)
    {
        return response()->json($s->location($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function template(OperationalRequest $r, ScheduleService $s)
    {
        return response()->json($s->template($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function preview(Request $r, ScheduleService $s)
    {
        return response()->json($s->preview($r->user(), $r->all()));
    }

    public function draft(OperationalRequest $r, ScheduleService $s)
    {
        return response()->json($s->draft($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function publish(OperationalRequest $r, string $id, ScheduleService $s)
    {
        $data = $r->validate(['checksum' => ['required', 'string', 'size:64']]);

        return response()->json($s->publish($r->user(), (int) $id, $r->version(), $r->key(), $data['checksum']));
    }

    public function clock(OperationalRequest $r, string $direction, AttendanceService $s)
    {
        $result = $s->clock($r->user(), $direction, $r->key(), $r->except(['idempotency_key']));

        return response()->json($result, isset($result['rejected']) ? 422 : 200);
    }

    public function mine(Request $r, AttendanceService $s)
    {
        abort_unless($r->user()->employee_id, 403);
        $data = $r->validate(['starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);
        $start = $data['starts_on'] ?? today()->subDay()->toDateString();
        $end = $data['ends_on'] ?? today()->addDays(7)->toDateString();
        abort_unless(CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) <= 31, 422);
        $shifts = RosterShift::where('employee_id', $r->user()->employee_id)->where('status', 'published')->whereBetween('work_date', [$start, $end])->orderBy('starts_at')->get();

        return response()->json($shifts->map(fn ($shift) => ['shift' => $shift, 'result' => $s->recalculate($shift)]));
    }

    public function result(Request $r, string $id, AttendanceService $s)
    {
        $shift = RosterShift::where('status', 'published')->findOrFail($id);
        abort_unless(EmployeeVisibility::query($r->user(), $shift->work_date)->whereKey($shift->employee_id)->exists(), 404);

        return response()->json(['roster_shift_id' => $shift->id, 'work_date' => $shift->work_date, 'timezone' => $shift->timezone, 'result' => $s->recalculate($shift)]);
    }
}
