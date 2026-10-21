<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\CalendarService;
use App\Domain\Identity\Access;
use App\Http\Controllers\Concerns\HandlesWorkspaceWrites;
use App\Http\Requests\OperationalRequest;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamCalendarWorkspaceController extends Controller
{
    use HandlesWorkspaceWrites;

    public function index(Request $r, CalendarService $calendar)
    {
        $d = $r->validate(['starts_on' => ['nullable', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d'], 'page' => ['nullable', 'integer', 'min:1']]);
        $start = $d['starts_on'] ?? today()->toDateString();
        $end = $d['ends_on'] ?? CarbonImmutable::parse($start)->addDays(6)->toDateString();
        if ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 31) {
            throw ValidationException::withMessages(['ends_on' => 'Pilih rentang berurutan, maksimal 31 hari.']);
        }
        $rows = $calendar->team($r->user(), ['starts_on' => $start, 'ends_on' => $end]);
        $page = (int) ($d['page'] ?? 1);
        $items = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 50, 50), count($rows), 50, $page, ['path' => route('workspace.team-calendar'), 'query' => $r->except('page')]);

        return view('workspace.calendar.team', ['items' => $items, 'start' => $start, 'end' => $end]);
    }

    public function dates(Request $r)
    {
        abort_unless(Access::companyPermission($r->user(), 'schedules.manage'), 403);
        $latest = DB::table('work_calendar_dates')->selectRaw('MAX(id)')->groupBy('branch_id', 'work_date');

        return view('workspace.calendar.dates', ['branches' => Branch::orderBy('name')->get(), 'dates' => DB::table('work_calendar_dates')->join('branches', 'branches.id', '=', 'work_calendar_dates.branch_id')->whereIn('work_calendar_dates.id', $latest)->orderByDesc('work_date')->select('work_calendar_dates.*', 'branches.name as branch_name')->paginate(30)]);
    }

    public function store(OperationalRequest $r, CalendarService $calendar)
    {
        abort_unless(Access::companyPermission($r->user(), 'schedules.manage'), 403);

        return $this->workspaceWrite(function () use ($r, $calendar) {
            $calendar->date($r->user(), $r->key(), $r->only(['branch_id', 'work_date', 'label', 'is_holiday']));

            return to_route('workspace.work-calendar')->with('status', 'Versi tanggal kalender disimpan. Roster kerja tetap perlu disusun secara terpisah.');
        });
    }
}
