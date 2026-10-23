<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Access;
use App\Domain\Workflow\WorkflowListing;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function home(Request $request)
    {
        $user = $request->user();
        $page = app(WorkflowListing::class)->page($user, 'in_review');
        $date = today()->toDateString();
        $people = Gate::allows('viewAny', Employee::class) ? EmployeeVisibility::query($user, $date) : null;
        $organization = Access::companyPermission($user, 'organization.manage') ? [
            'branches' => Branch::where('is_active', true)->count(),
            'departments' => Department::where('is_active', true)->count(),
            'jobs' => Job::where('is_active', true)->count(),
        ] : null;

        return view('workspace.home', [
            'user' => $user,
            'recent' => array_slice($page['data'], 0, 5),
            'moreReviews' => $page['has_more'] || count($page['data']) > 5,
            'employeeCount' => $people ? (clone $people)->count() : null,
            'team' => $people ? (clone $people)->with(['assignments' => fn ($q) => $q->at($date)->with(['department', 'job'])])->latest('id')->limit(5)->get() : collect(),
            'pendingChanges' => Access::companyPermission($user, 'employees.manage') ? EmployeeChangeRequest::where('status', 'pending')->count() : null,
            'organization' => $organization,
        ]);
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
