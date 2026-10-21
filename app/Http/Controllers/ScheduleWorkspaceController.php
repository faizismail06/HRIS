<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\ScheduleService;
use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Http\Requests\OperationalRequest;
use App\Models\Employee;
use App\Models\RosterShift;
use App\Models\RosterVersion;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ScheduleWorkspaceController extends Controller
{
    public function index(Request $r)
    {
        $this->authorizeScheduling($r);
        $input = $r->validate(['cursor' => ['nullable', 'integer', 'min:0']]);
        $cursor = (int) ($input['cursor'] ?? 0);
        $candidates = RosterVersion::where('id', '>', $cursor)->orderBy('id')->limit(30)->get();
        $rows = [];
        foreach ($candidates as $record) {
            $cursor = $record->id;
            if (! $this->visible($r, $record)) {
                continue;
            }
            $shifts = RosterShift::where('roster_version_id', $record->id);
            $rows[] = ['record' => $record, 'count' => $shifts->count(), 'starts_on' => (clone $shifts)->min('work_date'), 'ends_on' => (clone $shifts)->max('work_date')];
            if (count($rows) === 10) {
                break;
            }
        }

        return view('workspace.schedules.index', ['rows' => $rows, 'cursor' => $cursor, 'hasMore' => count($rows) === 10 || $candidates->count() === 30]);
    }

    public function create(Request $r)
    {
        $this->authorizeScheduling($r);
        $input = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'as_of' => ['nullable', 'date_format:Y-m-d']]);
        $date = $input['as_of'] ?? today()->toDateString();
        $query = EmployeeVisibility::query($r->user(), $date, $this->roles($r));
        if ($search = ($input['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('employee_number', 'ilike', '%'.$search.'%'));
        }
        // The same scoped catalog reader supplies template choices to API and HTML.
        $templates = app(OperationalCatalogController::class)->index($r, 'shift-templates')->getData(true);

        return view('workspace.schedules.create', ['people' => $query->orderBy('name')->limit(50)->get(), 'templates' => collect($templates['data'])->where('is_active', true), 'templatePage' => $templates, 'date' => $date, 'q' => $input['q'] ?? '']);
    }

    public function store(OperationalRequest $r, ScheduleService $s)
    {
        $this->authorizeScheduling($r);
        $pattern = $r->validate(['employee_ids' => ['required', 'array', 'min:1', 'max:200'], 'employee_ids.*' => ['required', 'integer', 'distinct'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'working_days' => ['nullable', 'array', 'max:7'], 'working_days.*' => ['integer', 'between:1,7', 'distinct'], 'shift_template_id' => ['required', 'integer']]);
        $pattern['working_days'] ??= [];
        if (! $pattern['working_days']) {
            $r->validate(['confirm_all_off' => ['required', 'accepted']]);
        }

        return $this->mutate(function () use ($r, $s, $pattern) {
            $draft = $s->draft($r->user(), $r->key(), ['pattern' => $pattern]);

            return to_route('workspace.schedules.show', $draft['id'])->with('status', 'Draf roster tersimpan. Tinjau jadwal dan peringatan sebelum dipublikasikan.');
        });
    }

    public function show(Request $r, string $id)
    {
        $this->authorizeScheduling($r);
        $record = RosterVersion::findOrFail($id);
        abort_unless($this->visible($r, $record), 404);
        $shifts = RosterShift::where('roster_version_id', $record->id)->orderBy('work_date')->orderBy('employee_id')->paginate(50);
        $people = Employee::whereIn('id', $shifts->pluck('employee_id'))->pluck('name', 'id');

        return view('workspace.schedules.show', ['record' => $record, 'shifts' => $shifts, 'people' => $people, 'warnings' => $record->staffing_snapshot['warnings'] ?? []]);
    }

    public function publish(OperationalRequest $r, string $id, ScheduleService $s)
    {
        $this->authorizeScheduling($r);
        $record = RosterVersion::findOrFail($id);
        abort_unless($this->visible($r, $record), 404);
        $r->validate(['checksum' => ['required', 'string', 'size:64'], 'acknowledge_warnings' => ['sometimes', 'boolean']]);

        return $this->mutate(function () use ($r, $id, $s) {
            $s->publish($r->user(), (int) $id, $r->version(), $r->key(), $r->input('checksum'), $r->boolean('acknowledge_warnings'));

            return to_route('workspace.schedules.show', $id)->with('status', 'Roster berhasil dipublikasikan dan tersedia bagi karyawan terkait.');
        });
    }

    public function replacementForm(Request $r, string $id)
    {
        $this->authorizeScheduling($r);
        $shift = $this->replacementSource($r, $id);
        $catalog = app(OperationalCatalogController::class)->index($r, 'shift-templates')->getData(true);

        return view('workspace.schedules.replace', ['shift' => $shift, 'person' => Employee::findOrFail($shift->employee_id), 'templates' => collect($catalog['data'])->where('is_active', true), 'catalog' => $catalog]);
    }

    public function replace(OperationalRequest $r, string $id, ScheduleService $s)
    {
        $this->authorizeScheduling($r);
        $shift = $this->replacementSource($r, $id);
        $data = $r->validate(['is_day_off' => ['required', 'boolean'], 'shift_template_id' => ['required_if:is_day_off,0', 'nullable', 'integer'], 'replacement_reason' => ['required', 'string', 'max:1000']]);

        return $this->mutate(function () use ($r, $s, $shift, $data) {
            $draft = $s->draft($r->user(), $r->key(), ['rows' => [['employee_id' => $shift->employee_id, 'work_date' => $shift->work_date, 'is_day_off' => $r->boolean('is_day_off'), 'shift_template_id' => $data['shift_template_id'] ?? null]], 'replacements' => [['id' => $shift->id, 'version' => $r->version()]], 'replacement_reason' => $data['replacement_reason']]);

            return to_route('workspace.schedules.show', $draft['id'])->with('status', 'Draf pengganti dibuat. Jadwal lama tetap berlaku sampai draf dipublikasikan.');
        });
    }

    private function replacementSource(Request $r, string $id): RosterShift
    {
        $shift = RosterShift::where('status', 'published')->findOrFail($id);
        abort_unless(EmployeeVisibility::query($r->user(), $shift->work_date, $this->roles($r))->whereKey($shift->employee_id)->exists(), 404);

        return $shift;
    }

    private function authorizeScheduling(Request $r): void
    {
        abort_unless(AttendanceWorkspaceController::canSchedule($r), 403);
    }

    private function roles(Request $r): array
    {
        return Access::companyPermission($r->user(), 'schedules.manage') ? ['hr_admin', 'hr_officer'] : ['manager'];
    }

    private function visible(Request $r, RosterVersion $record): bool
    {
        if (Access::companyPermission($r->user(), 'schedules.manage')) {
            return true;
        }
        // A mixed-scope batch is never partially disclosed or published from this screen.
        $any = false;
        foreach (RosterShift::where('roster_version_id', $record->id)->select('employee_id', 'work_date')->cursor() as $shift) {
            $any = true;
            if (! EmployeeVisibility::query($r->user(), $shift->work_date, $this->roles($r))->whereKey($shift->employee_id)->exists()) {
                return false;
            }
        }

        return $any;
    }

    private function mutate(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['roster' => $e->getMessage() ?: 'Roster belum dapat diproses. Periksa konflik jadwal, versi data dan konfigurasi, lalu muat ulang.']);
        }
    }
}
