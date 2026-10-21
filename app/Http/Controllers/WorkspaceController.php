<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Workflow\WorkflowListing;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function home(Request $request)
    {
        $page = app(WorkflowListing::class)->page($request->user(), 'in_review');

        return view('workspace.home', ['user' => $request->user(), 'recent' => array_slice($page['data'], 0, 5)]);
    }

    public function employees(Request $request, ?string $employee = null)
    {
        Gate::authorize('viewAny', Employee::class);
        $data = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'q' => ['nullable', 'string', 'max:100']]);
        $date = $data['as_of'] ?? today()->toDateString();
        $query = EmployeeVisibility::query($request->user(), $date)->with(['assignments' => fn ($q) => $q->at($date)->with(['branch', 'department', 'job'])]);
        if ($employee !== null) {
            $record = $query->whereKey($employee)->firstOrFail();

            return view('workspace.employee', ['employee' => $record, 'date' => $date]);
        }
        if ($search = ($data['q'] ?? null)) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('employee_number', 'ilike', '%'.$search.'%'));
        }

        return view('workspace.employees', ['employees' => $query->orderBy('name')->paginate(20)->withQueryString(), 'date' => $date]);
    }

    public function security(Request $request)
    {
        return view('auth.security', ['user' => $request->user()]);
    }
}
