<?php

namespace App\Http\Controllers;

use App\Support\PrototypeAdministration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeAdministrationController extends Controller
{
    public function settings(): View
    {
        return view('administration.settings', ['roles' => PrototypeAdministration::roles(), 'kind' => 'settings', 'filters' => []]);
    }

    public function audit(Request $request): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'module' => ['nullable', Rule::in(array_keys(PrototypeAdministration::modules()))], 'result' => ['nullable', Rule::in(array_keys(PrototypeAdministration::results()))]]);
        $filters = array_merge(['q' => '', 'module' => '', 'result' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
        $items = collect(PrototypeAdministration::audit())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['actor'].' '.$item['action'].' '.$item['subject']), mb_strtolower($filters['q']))) &&
            (! $filters['module'] || $filters['module'] === $item['module']) && (! $filters['result'] || $filters['result'] === $item['result'])
        );

        return view('administration.audit', compact('items', 'filters') + ['kind' => 'audit', 'modules' => PrototypeAdministration::modules(), 'results' => PrototypeAdministration::results()]);
    }

    private function importFilters(Request $request): array
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys(PrototypeAdministration::importStates()))], 'type' => ['nullable', Rule::in(array_keys(PrototypeAdministration::importTypes()))]]);

        return array_merge(['q' => '', 'status' => '', 'type' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    public function imports(Request $request): View
    {
        $filters = $this->importFilters($request);
        $items = collect(PrototypeAdministration::imports())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['file']), mb_strtolower($filters['q']))) &&
            (! $filters['status'] || $filters['status'] === $item['status']) && (! $filters['type'] || $filters['type'] === $item['type'])
        );

        return view('administration.imports', compact('items', 'filters') + ['kind' => 'imports', 'types' => PrototypeAdministration::importTypes(), 'states' => PrototypeAdministration::importStates()]);
    }

    public function import(Request $request, string $batch): View
    {
        $item = PrototypeAdministration::import($batch);
        abort_unless($item, 404);

        return view('administration.import', compact('item') + ['kind' => 'imports', 'filters' => $this->importFilters($request)]);
    }
}
