<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Lifecycle\LifecycleService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LifecycleController extends Controller
{
    public function template(OperationalRequest $r, LifecycleService $service)
    {
        return response()->json($service->template($r->user(), $r->key(), $r->all()), 201);
    }

    public function onboarding(OperationalRequest $r, LifecycleService $service)
    {
        return response()->json($service->onboarding($r->user(), $r->key(), $r->version(), $r->all()), 201);
    }

    public function task(OperationalRequest $r, int $id, LifecycleService $service)
    {
        return response()->json($service->task($r->user(), $id, $r->key(), $r->version(), $r->all()));
    }

    public function show(Request $r, int $id)
    {
        $case = DB::table('lifecycle_cases')->where('id', $id)->firstOrFail();
        $hr = EmployeeVisibility::query($r->user(), $case->last_working_on ?? $case->effective_on, ['hr_admin', 'hr_officer'])->whereKey($case->employee_id)->exists();
        $tasks = DB::table('lifecycle_tasks')->where('lifecycle_case_id', $id);
        abort_unless($hr || $r->user()->employee_id === $case->employee_id || (clone $tasks)->where('owner_id', $r->user()->id)->exists(), 404);
        if (! $hr && $r->user()->employee_id !== $case->employee_id) {
            $tasks->where('owner_id', $r->user()->id);
        }

        return response()->json(['id' => $id, 'employee_id' => $case->employee_id, 'employment_period_id' => $case->employment_period_id, 'kind' => $case->kind, 'status' => $case->status, 'effective_on' => $case->effective_on, 'version' => $case->version, 'tasks' => $tasks->orderBy('id')->get(['id', 'code', 'label', 'role', 'owner_id', 'mandatory', 'dependencies', 'due_on', 'status', 'version'])])->header('Cache-Control', 'no-store, private');
    }
}
