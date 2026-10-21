<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\ScheduleService;
use App\Domain\Identity\Access;
use App\Http\Requests\OperationalRequest;
use App\Models\Branch;
use App\Models\ShiftTemplate;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ScheduleSettingsController extends Controller
{
    public function form(Request $r, string $kind)
    {
        abort_unless(Access::companyPermission($r->user(), 'schedules.manage'), 403);

        return view('workspace.schedules.settings', ['kind' => $kind, 'branches' => Branch::where('is_active', true)->orderBy('name')->get(), 'locations' => WorkLocation::where('is_active', true)->orderBy('name')->get(), 'records' => ($kind === 'locations' ? WorkLocation::query() : ShiftTemplate::query())->orderByDesc('id')->paginate(20)]);
    }

    public function store(OperationalRequest $r, string $kind, ScheduleService $service)
    {
        abort_unless(Access::companyPermission($r->user(), 'schedules.manage'), 403);
        if ($kind === 'locations') {
            $r->validate(['attendance_mode' => ['required', 'in:onsite,field,remote'], 'requires_photo' => ['sometimes', 'boolean']]);
        }
        $input = $kind === 'locations'
            ? $r->only(['branch_id', 'code', 'name', 'timezone', 'attendance_mode', 'latitude', 'longitude', 'radius_m', 'max_accuracy_m']) + ['requires_photo' => $r->boolean('requires_photo')]
            : $r->only(['code', 'name', 'work_location_id', 'starts_local', 'ends_local', 'end_day_offset', 'half_day_split_minute', 'tolerance_minutes', 'clock_in_before_minutes', 'clock_out_after_minutes']);
        if ($kind === 'templates') {
            $r->validate(['break_start' => ['nullable', 'array', 'max:8'], 'break_end' => ['nullable', 'array', 'max:8'], 'break_start.*' => ['nullable', 'integer', 'between:0,1440'], 'break_end.*' => ['nullable', 'integer', 'between:1,1440']]);
            $breaks = [];
            for ($i = 0; $i < 8; $i++) {
                if ($r->input('break_start.'.$i) === null && $r->input('break_end.'.$i) === null) {
                    continue;
                }
                $r->validate(['break_start.'.$i => ['required', 'integer'], 'break_end.'.$i => ['required', 'integer']], ['required' => 'Isi awal dan akhir setiap rentang istirahat.']);
                $breaks[] = ['start' => (int) $r->input('break_start.'.$i), 'end' => (int) $r->input('break_end.'.$i)];
            }
            $input['unpaid_breaks'] = $breaks;
        }
        try {
            if ($kind === 'locations') {
                $service->location($r->user(), $r->key(), $input);
            } else {
                $service->template($r->user(), $r->key(), $input);
            }
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['settings' => $e->getMessage() ?: 'Konfigurasi belum dapat disimpan. Periksa kode dan rentang waktunya.']);
        }

        return to_route('workspace.schedule-settings', $kind)->with('status', $kind === 'locations' ? 'Lokasi kerja berhasil ditambahkan.' : 'Versi template shift berhasil disimpan. Roster yang sudah ada tetap memakai snapshot sebelumnya.');
    }
}
