<?php

namespace App\Http\Controllers;

use App\Support\PrototypeAttendance;
use App\Support\PrototypePeople;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeAttendanceController extends Controller
{
    private function filters(Request $request): array
    {
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2026-10-12', 'before_or_equal:2026-10-25'],
            'week' => ['nullable', Rule::in(['2026-10-12', '2026-10-19'])],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'employee' => ['nullable', Rule::in(PrototypeAttendance::employeeIds())],
            'status' => ['nullable', Rule::in(array_keys(PrototypeAttendance::statuses()))],
            'from' => ['nullable', Rule::in(['attendance', 'schedules', 'reports'])],
            'report_week' => ['nullable', Rule::in(['2026-10-12', '2026-10-19'])],
            'department' => ['nullable', Rule::in(array_keys(PrototypePeople::departments()))],
        ]);

        return array_merge(['date' => '2026-10-19', 'week' => '2026-10-19', 'branch' => '', 'employee' => '', 'status' => '', 'from' => 'attendance', 'report_week' => '2026-10-19', 'department' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    private function common(array $filters): array
    {
        return ['filters' => $filters, 'employees' => PrototypeAttendance::employees(), 'statuses' => PrototypeAttendance::statuses()];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $records = collect(PrototypeAttendance::records())->where('date', $filters['date'])
            ->filter(fn ($record) => (! $filters['branch'] || $record['person']['branch'] === $filters['branch']) && (! $filters['employee'] || $record['person']['id'] === $filters['employee']));
        $counts = $records->countBy('status');
        if ($filters['status']) {
            $records = $records->where('status', $filters['status']);
        }

        return view('attendance.index', array_merge($this->common($filters), [
            'records' => $records, 'counts' => $counts,
            'dateLabel' => CarbonImmutable::parse($filters['date'])->locale('id')->translatedFormat('l, j F Y'),
        ]));
    }

    public function schedules(Request $request): View
    {
        $filters = $this->filters($request);
        $first = CarbonImmutable::parse($filters['week'], 'Asia/Jakarta');
        $days = collect(range(0, 6))->map(fn ($offset) => $first->addDays($offset));
        $people = collect(PrototypeAttendance::employees())->filter(fn ($person) => (! $filters['branch'] || $person['branch'] === $filters['branch']) && (! $filters['employee'] || $person['id'] === $filters['employee']));

        return view('attendance.schedules', array_merge($this->common($filters), [
            'days' => $days, 'people' => $people,
            'records' => collect(PrototypeAttendance::records())->keyBy('id'),
            'weekLabel' => $first->locale('id')->translatedFormat('j M').' – '.$first->addDays(6)->locale('id')->translatedFormat('j M Y'),
        ]));
    }

    public function show(Request $request, string $record): View
    {
        $item = PrototypeAttendance::find($record);
        abort_unless($item, 404);
        $filters = $this->filters($request);
        $fromSchedule = $filters['from'] === 'schedules';

        if ($filters['from'] === 'reports') {
            return view('attendance.show', [
                'record' => $item, 'backRoute' => 'prototype.attendance-reports',
                'backFilters' => ['week' => $filters['report_week'], 'branch' => $filters['branch'], 'department' => $filters['department'], 'employee' => $filters['employee']],
                'backLabel' => 'Kembali ke laporan',
            ]);
        }

        return view('attendance.show', [
            'record' => $item,
            'backRoute' => $fromSchedule ? 'prototype.schedules' : 'prototype.attendance',
            'backFilters' => array_intersect_key($filters, array_flip($fromSchedule ? ['week', 'branch', 'employee'] : ['date', 'branch', 'employee', 'status'])),
            'backLabel' => $fromSchedule ? 'Kembali ke jadwal' : 'Kembali ke rekap',
        ]);
    }
}
