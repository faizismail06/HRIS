<?php

namespace App\Http\Controllers;

use App\Support\PrototypeLifecycle;
use App\Support\PrototypePeople;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeLifecycleController extends Controller
{
    private function filters(Request $request, bool $assets): array
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys($assets ? PrototypeLifecycle::assetStates() : PrototypeLifecycle::states()))],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'employee' => ['nullable', Rule::in(array_column(PrototypePeople::employees(), 'id'))],
        ]);

        return array_merge(['q' => '', 'status' => '', 'branch' => '', 'employee' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    public function index(Request $request): View
    {
        return $this->listing($request, false);
    }

    public function assets(Request $request): View
    {
        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $assets): View
    {
        $filters = $this->filters($request, $assets);
        $items = collect($assets ? PrototypeLifecycle::assets() : PrototypeLifecycle::cases())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.($item['name'] ?? $item['title']).' '.$item['person']['name'].' '.($item['serial'] ?? '')), mb_strtolower($filters['q']))) &&
            (! $filters['status'] || $item['status'] === $filters['status']) &&
            (! $filters['branch'] || ($assets ? $item['branch'] : $item['person']['branch']) === $filters['branch']) &&
            (! $filters['employee'] || $item['employee'] === $filters['employee'])
        );

        return view('lifecycle.index', compact('assets', 'filters', 'items') + ['states' => $assets ? PrototypeLifecycle::assetStates() : PrototypeLifecycle::states(), 'employees' => PrototypePeople::employees(), 'kind' => $assets ? 'assets' : 'lifecycle']);
    }

    public function show(Request $request, string $record): View
    {
        $item = PrototypeLifecycle::find($record);
        abort_unless($item, 404);

        return view('lifecycle.show', compact('item') + ['kind' => 'lifecycle', 'filters' => $this->filters($request, false)]);
    }

    public function asset(Request $request, string $record): View
    {
        $item = PrototypeLifecycle::asset($record);
        abort_unless($item, 404);

        return view('lifecycle.asset', compact('item') + ['kind' => 'assets', 'filters' => $this->filters($request, true)]);
    }
}
