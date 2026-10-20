<?php

namespace App\Domain\CoreHR;

use App\Models\Employee;
use App\Models\Job;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeFields
{
    public static function identity(?int $id = null): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'work_email')->ignore($id)]];
    }

    public static function assignment(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'job_id' => ['required', 'integer', Rule::exists('job_positions', 'id')->where('is_active', true)],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
        ];
    }

    public static function validatePlacement(array $data, string $date, ?int $employeeId = null): void
    {
        if (! Job::whereKey($data['job_id'])->where('department_id', $data['department_id'])->exists()) {
            throw ValidationException::withMessages(['job_id' => 'Jabatan harus berada pada departemen yang dipilih.']);
        }
        if ($manager = ($data['manager_employee_id'] ?? null)) {
            $person = Employee::findOrFail($manager);
            if ((int) $manager === $employeeId || ! in_array($person->statusAt($date), ['ACTIVE', 'OFFBOARDING'])) {
                throw ValidationException::withMessages(['manager_employee_id' => 'Atasan harus bekerja pada tanggal efektif dan bukan karyawan itu sendiri.']);
            }
        }
    }
}
