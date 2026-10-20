<?php

namespace App\Http\Controllers;

use App\Support\PrototypeFinance;
use App\Support\PrototypePeople;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeFinanceController extends Controller
{
    private function filters(Request $request, bool $claims): array
    {
        $rules = ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys($claims ? PrototypeFinance::claimStates() : PrototypeFinance::payrollStates()))]];
        $rules += $claims ? ['employee' => ['nullable', Rule::in(array_column(PrototypePeople::employees(), 'id'))], 'branch' => ['nullable', Rule::in(['Jakarta', 'Bandung', 'Surabaya'])]] : ['period' => ['nullable', Rule::in(['2026-09', '2026-10'])]];
        $defaults = $claims ? ['q' => '', 'status' => '', 'employee' => '', 'branch' => ''] : ['q' => '', 'status' => '', 'period' => ''];

        return array_merge($defaults, array_filter($request->validate($rules), fn ($value) => $value !== null && $value !== ''));
    }

    public function payrolls(Request $request): View
    {
        $filters = $this->filters($request, false);
        $items = collect(PrototypeFinance::payrolls())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['label']), mb_strtolower($filters['q']))) &&
            (! $filters['status'] || $item['status'] === $filters['status']) && (! $filters['period'] || $item['period'] === $filters['period'])
        );

        return view('finance.payrolls', compact('filters', 'items') + ['states' => PrototypeFinance::payrollStates()]);
    }

    public function payroll(Request $request, string $batch): View
    {
        $item = PrototypeFinance::payroll($batch);
        abort_unless($item, 404);

        return view('finance.payroll', compact('item') + ['filters' => $this->filters($request, false)]);
    }

    public function payslip(Request $request, string $batch, string $employee): View
    {
        $item = PrototypeFinance::payroll($batch);
        abort_unless($item && PrototypeFinance::released($item), 404);
        $payee = collect($item['employees'])->firstWhere('id', $employee);
        abort_unless($payee, 404);

        return view('finance.payslip', compact('item', 'payee') + ['filters' => $this->filters($request, false)]);
    }

    public function claims(Request $request): View
    {
        $filters = $this->filters($request, true);
        $items = collect(PrototypeFinance::claims())->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['title'].' '.$item['person']['name']), mb_strtolower($filters['q']))) &&
            (! $filters['status'] || $item['status'] === $filters['status']) && (! $filters['employee'] || $item['employee'] === $filters['employee']) &&
            (! $filters['branch'] || $item['person']['branch'] === $filters['branch'])
        );

        return view('finance.claims', compact('filters', 'items') + ['states' => PrototypeFinance::claimStates(), 'employees' => PrototypePeople::employees()]);
    }

    public function claim(Request $request, string $claim): View
    {
        $item = PrototypeFinance::claim($claim);
        abort_unless($item, 404);

        return view('finance.claim', compact('item') + ['filters' => $this->filters($request, true)]);
    }
}
