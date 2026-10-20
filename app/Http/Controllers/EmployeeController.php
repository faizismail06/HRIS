<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeWriter;
use App\Domain\Identity\Access;
use App\Http\Requests\EmployeeChangeRequest;
use App\Http\Requests\EmployeeCreateRequest;
use App\Http\Requests\EmployeeReviewRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest as Change;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    private function options(): array
    {
        return ['branches' => Branch::where('is_active', true)->orderBy('name')->get(), 'departments' => Department::where('is_active', true)->orderBy('name')->get(), 'jobs' => Job::where('is_active', true)->orderBy('name')->get(), 'managers' => Employee::orderBy('name')->get(['id', 'name', 'employee_number'])];
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);

        return view('employees.create', $this->options());
    }

    public function store(EmployeeCreateRequest $request, EmployeeWriter $writer)
    {
        $employee = $writer->create($request->user(), $request->validated());

        return redirect()->route('workspace.employees.show', $employee->id)->with('status', 'Karyawan dan penempatan awal berhasil ditambahkan. Akun login dibuat terpisah.');
    }

    public function changeForm(Request $request, string $employee)
    {
        $record = Employee::findOrFail($employee);
        Gate::authorize('update', $record);
        $data = $request->validate(['kind' => ['nullable', 'in:identity,assignment']]);

        return view('employees.change', $this->options() + ['employee' => $record, 'kind' => $data['kind'] ?? 'identity', 'placement' => $record->assignments()->at(today()->toDateString())->first()]);
    }

    public function propose(EmployeeChangeRequest $request, string $employee, EmployeeWriter $writer)
    {
        $change = $writer->propose($request->user(), (int) $employee, $request->validated());

        return redirect()->route('workspace.changes.show', $change->id)->with('status', 'Pengajuan dikirim. Data karyawan berubah setelah disetujui HR Admin lain.');
    }

    public function changes(Request $request)
    {
        abort_unless(Access::companyPermission($request->user(), 'employees.manage'), 403);
        $data = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected']]);
        $status = $data['status'] ?? 'pending';

        return view('employees.changes', ['changes' => Change::with(['employee', 'requester'])->where('status', $status)->latest('id')->paginate(20)->withQueryString(), 'status' => $status]);
    }

    public function change(Request $request, string $change)
    {
        abort_unless(Access::companyPermission($request->user(), 'employees.manage'), 403);
        $record = Change::with(['employee', 'requester', 'reviewer'])->findOrFail($change);
        $payload = $record->payload;
        $comparison = [];
        if ($record->kind === 'identity') {
            foreach (['name' => 'Nama', 'work_email' => 'Email kerja'] as $key => $label) {
                $comparison[] = ['label' => $label, 'before' => $record->before_payload[$key] ?? 'Belum diisi', 'after' => $payload[$key] ?? 'Belum diisi'];
            }
        } else {
            foreach (['branch_id' => [Branch::class, 'Cabang'], 'department_id' => [Department::class, 'Departemen'], 'job_id' => [Job::class, 'Jabatan'], 'manager_employee_id' => [Employee::class, 'Atasan']] as $key => [$model, $label]) {
                $comparison[] = ['label' => $label, 'before' => $model::find($record->before_payload[$key] ?? null)?->name ?? 'Belum diisi', 'after' => $model::find($payload[$key] ?? null)?->name ?? 'Tanpa atasan'];
            }
        }

        return view('employees.change-detail', ['change' => $record, 'comparison' => $comparison]);
    }

    public function review(EmployeeReviewRequest $request, string $change, EmployeeWriter $writer)
    {
        $record = $writer->review($request->user(), (int) $change, $request->validated());

        return redirect()->route('workspace.changes.show', $record->id)->with('status', $record->status === 'approved' ? 'Pengajuan disetujui. Penempatan mengikuti tanggal efektifnya.' : 'Pengajuan ditolak. Data karyawan tidak berubah.');
    }
}
