<?php

namespace App\Http\Controllers;

use App\Support\PrototypeEmployment;
use App\Support\PrototypePeople;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeEmploymentController extends Controller
{
    private function filters(Request $request, string $kind): array
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(PrototypeEmployment::definitions()[$kind]['states']))],
            'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])],
            'employee' => ['nullable', Rule::in(array_column(PrototypePeople::employees(), 'id'))],
        ]);

        return array_merge(['q' => '', 'status' => '', 'branch' => '', 'employee' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    public function index(Request $request, string $kind): View
    {
        $definition = PrototypeEmployment::definitions()[$kind];
        $filters = $this->filters($request, $kind);
        $items = collect(PrototypeEmployment::records($kind))->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['title'].' '.$item['person']['name'].' '.$item['number']), mb_strtolower($filters['q']))) &&
            (! $filters['status'] || $item['status'] === $filters['status']) && (! $filters['branch'] || $item['person']['branch'] === $filters['branch']) &&
            (! $filters['employee'] || $item['employee'] === $filters['employee'])
        );

        return view('employment.index', compact('kind', 'definition', 'filters', 'items') + ['employees' => PrototypePeople::employees()]);
    }

    public function show(Request $request, string $record, string $kind): View
    {
        $item = PrototypeEmployment::find($kind, $record);
        abort_unless($item, 404);
        $filters = $this->filters($request, $kind);
        $reminders = [];
        if ($kind === 'contracts' && $item['status'] === 'ACTIVE') {
            foreach ([60, 30, 14, 7] as $days) {
                $date = CarbonImmutable::parse($item['end'], 'Asia/Jakarta')->subDays($days);
                $reminders[] = ['days' => $days, 'date' => $date, 'past' => $date->isBefore(CarbonImmutable::parse('2026-10-20', 'Asia/Jakarta'))];
            }
        }

        return view('employment.show', compact('kind', 'item', 'filters', 'reminders') + ['definition' => PrototypeEmployment::definitions()[$kind]]);
    }
}
