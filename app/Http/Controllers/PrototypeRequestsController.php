<?php

namespace App\Http\Controllers;

use App\Support\PrototypeAttendance;
use App\Support\PrototypePeople;
use App\Support\PrototypeRequests;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeRequestsController extends Controller
{
    private function filters(Request $request): array
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(PrototypeRequests::types()))],
            'status' => ['nullable', Rule::in(array_keys(PrototypeRequests::statuses()))],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'employee' => ['nullable', Rule::in(array_column(PrototypeRequests::employees(), 'id'))],
            'week' => ['nullable', Rule::in(['2026-10-12', '2026-10-19'])],
            'from' => ['nullable', Rule::in(['requests', 'balances'])],
        ]);

        return array_merge(['q' => '', 'type' => '', 'status' => '', 'branch' => '', 'employee' => '', 'week' => '2026-10-19', 'from' => 'requests'], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $items = collect(PrototypeRequests::all())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['person']['name'].' '.$item['type_label']), mb_strtolower($filters['q']))) &&
            (! $filters['type'] || $item['type'] === $filters['type']) && (! $filters['status'] || $item['status'] === $filters['status']) &&
            (! $filters['branch'] || $item['person']['branch'] === $filters['branch']) && (! $filters['employee'] || $item['employee'] === $filters['employee'])
        )->sortByDesc('submitted');

        return view('requests.index', ['filters' => $filters, 'items' => $items, 'types' => PrototypeRequests::types(), 'statuses' => PrototypeRequests::statuses(), 'employees' => PrototypeRequests::employees()]);
    }

    public function show(Request $request, string $requestId): View
    {
        $item = PrototypeRequests::find($requestId);
        abort_unless($item, 404);
        $filters = $this->filters($request);
        $backRoute = $filters['from'] === 'balances' ? 'prototype.leave-balances' : 'prototype.requests';
        $backFilters = $filters['from'] === 'balances' ? ['employee' => $filters['employee'] ?: $item['employee']] : array_intersect_key($filters, array_flip(['q', 'type', 'status', 'branch', 'employee']));

        return view('requests.show', compact('item', 'filters', 'backRoute', 'backFilters') + ['ledger' => $item['type'] === 'LEAVE' ? PrototypeRequests::ledger($item['employee']) : null]);
    }

    public function balances(Request $request): View
    {
        $filters = $this->filters($request);
        $employee = $filters['employee'] ?: 'EMP-004';

        return view('requests.balances', ['filters' => array_merge($filters, ['employee' => $employee]), 'person' => PrototypePeople::find($employee), 'ledger' => PrototypeRequests::ledger($employee), 'employees' => PrototypeRequests::employees()]);
    }

    public function calendar(Request $request): View
    {
        $filters = $this->filters($request);
        $first = CarbonImmutable::parse($filters['week'], 'Asia/Jakarta');
        $days = collect(range(0, 6))->map(fn ($offset) => $first->addDays($offset));
        $people = collect(PrototypeRequests::employees())->filter(fn ($person) => (! $filters['branch'] || $person['branch'] === $filters['branch']) && (! $filters['employee'] || $person['id'] === $filters['employee']));
        // Only roster/approved availability is sent to this view; no request payload or private reasons.
        $availability = collect(PrototypeAttendance::records())->mapWithKeys(fn ($record) => [$record['id'] => match ($record['status']) {
            'LEAVE' => 'UNAVAILABLE', 'DAY_OFF' => 'DAY_OFF', default => 'WORK'
        }]);

        return view('requests.calendar', compact('filters', 'days', 'people', 'availability') + ['employees' => PrototypeRequests::employees()]);
    }
}
