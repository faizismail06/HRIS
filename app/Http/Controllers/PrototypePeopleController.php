<?php

namespace App\Http\Controllers;

use App\Support\PrototypePeople;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypePeopleController extends Controller
{
    private function filters(Request $request): array
    {
        return array_merge(['q' => '', 'department' => '', 'branch' => '', 'status' => '', 'sort' => 'name'], $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', Rule::in(array_keys(PrototypePeople::departments()))],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'status' => ['nullable', Rule::in(array_keys(PrototypePeople::statuses()))],
            'sort' => ['nullable', Rule::in(['name', 'name-desc', 'joined'])],
        ]));
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $all = collect(PrototypePeople::employees());
        $employees = $all->filter(fn ($person) => (! $filters['q'] || str_contains(mb_strtolower($person['name'].' '.$person['id'].' '.$person['job']), mb_strtolower($filters['q']))) &&
            (! $filters['department'] || $person['department'] === $filters['department']) &&
            (! $filters['branch'] || $person['branch'] === $filters['branch']) &&
            (! $filters['status'] || $person['status'] === $filters['status'])
        );
        $employees = match ($filters['sort']) {
            'name-desc' => $employees->sortByDesc('name'),
            'joined' => $employees->sortByDesc('joined'),
            default => $employees->sortBy('name'),
        };

        return view('people.index', [
            'employees' => $employees, 'filters' => $filters,
            'departments' => PrototypePeople::departments(), 'statuses' => PrototypePeople::statuses(),
            'total' => $all->count(), 'statusCounts' => $all->countBy('status'),
        ]);
    }

    public function show(Request $request, string $employee): View
    {
        $person = PrototypePeople::find($employee);
        abort_unless($person, 404);

        return view('people.show', [
            'person' => $person, 'filters' => $this->filters($request),
            'manager' => $person['manager'] ? PrototypePeople::find($person['manager']) : null,
            'reports' => collect(PrototypePeople::employees())->where('manager', $employee)
                ->whereIn('status', ['ACTIVE', 'OFFBOARDING']),
        ]);
    }

    public function organization(Request $request): View
    {
        $input = $request->validate(['branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])]]);
        $branch = $input['branch'] ?? '';
        $working = collect(PrototypePeople::employees())->whereIn('status', ['ACTIVE', 'OFFBOARDING']);
        if ($branch) {
            $working = $working->where('branch', $branch);
        }

        return view('people.organization', [
            'branch' => $branch, 'working' => $working,
            'departments' => PrototypePeople::departments(),
            'leads' => collect(PrototypePeople::employees())->keyBy('id'),
        ]);
    }
}
