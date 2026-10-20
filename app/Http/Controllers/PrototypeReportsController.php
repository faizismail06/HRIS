<?php

namespace App\Http\Controllers;

use App\Support\PrototypeAttendance;
use App\Support\PrototypePeople;
use App\Support\PrototypeReports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeReportsController extends Controller
{
    public function report(Request $request): View
    {
        $input = $request->validate([
            'week' => ['nullable', Rule::in(['2026-10-12', '2026-10-19'])],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'department' => ['nullable', Rule::in(array_keys(PrototypePeople::departments()))],
            'employee' => ['nullable', Rule::in(PrototypeAttendance::employeeIds())],
        ]);
        $filters = array_merge(['week' => '2026-10-19', 'branch' => '', 'department' => '', 'employee' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
        $end = CarbonImmutable::parse($filters['week'])->addDays(6)->toDateString();
        $records = collect(PrototypeAttendance::records())->whereBetween('date', [$filters['week'], $end])->filter(fn ($record) => (! $filters['branch'] || $record['person']['branch'] === $filters['branch']) &&
            (! $filters['department'] || $record['person']['department'] === $filters['department']) &&
            (! $filters['employee'] || $record['person']['id'] === $filters['employee'])
        );
        $rows = $records->groupBy('person.id')->map(fn ($group) => ['person' => $group->first()['person']] + PrototypeReports::summary($group->all()));

        return view('reports.attendance', compact('filters', 'records', 'rows', 'end') + ['summary' => PrototypeReports::summary($records->all()), 'employees' => PrototypeAttendance::employees(), 'departments' => PrototypePeople::departments()]);
    }

    public function periods(): View
    {
        return view('reports.periods', ['items' => PrototypeReports::periods(), 'filters' => []]);
    }

    public function period(string $period): View
    {
        $item = PrototypeReports::period($period);
        abort_unless($item, 404);

        return view('reports.period', compact('item') + ['checks' => PrototypeReports::checks($item), 'filters' => []]);
    }
}
