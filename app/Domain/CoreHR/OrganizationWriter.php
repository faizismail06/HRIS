<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Audit;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Job;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrganizationWriter
{
    public function save(User $actor, string $kind, ?int $id, array $data): mixed
    {
        return CoreWrite::run(function () use ($actor, $kind, $id, $data) {
            $actor = CoreWrite::actor($actor, 'organization.manage');
            $type = OrganizationCatalog::type($kind);
            $record = $id ? $type['model']::query()->lockForUpdate()->findOrFail($id) : new ($type['model']);
            if ($id) {
                CoreWrite::version($record->version, $data['version']);
            }
            $fields = ['code', 'name', 'is_active'];
            $fields[] = match ($kind) {
                'branches' => 'timezone', 'departments' => 'parent_id', 'jobs' => 'department_id'
            };
            $values = array_intersect_key($data, array_flip($fields));
            if ($kind === 'departments' && ($values['parent_id'] ?? null)) {
                $parent = Department::findOrFail($values['parent_id']);
                $preservedArchive = $id && ! $values['is_active'] && (int) $record->parent_id === $parent->id;
                if ((! $parent->is_active && ! $preservedArchive) || $parent->id === $id) {
                    throw ValidationException::withMessages(['parent_id' => 'Pilih departemen induk aktif selain dirinya sendiri.']);
                }
            }
            if ($kind === 'jobs') {
                $department = Department::findOrFail($values['department_id']);
                $preservedArchive = $id && ! $values['is_active'] && (int) $record->department_id === $department->id;
                if (! $department->is_active && ! $preservedArchive) {
                    throw ValidationException::withMessages(['department_id' => 'Departemen harus aktif.']);
                }
            }
            if ($id && ! $values['is_active']) {
                $this->canArchive($kind, $id);
            }
            $before = $record->only($fields);
            if (! $id && $kind !== 'jobs') {
                $record->company_id = 1;
            }
            $record->fill($values);
            $record->version = $id ? $record->version + 1 : 1;
            $record->save();
            Audit::record($actor->id, $id ? 'organization.updated' : 'organization.created', $kind, $record->id, ['before' => $before, 'after' => $record->only($fields)]);

            return $record;
        });
    }

    private function canArchive(string $kind, int $id): void
    {
        $column = match ($kind) {
            'branches' => 'branch_id', 'departments' => 'department_id', 'jobs' => 'job_id'
        };
        $referenced = Assignment::where($column, $id)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', today()->toDateString()))->exists();
        if ($kind === 'departments') {
            $referenced = $referenced || Department::where('parent_id', $id)->where('is_active', true)->exists() || Job::where('department_id', $id)->where('is_active', true)->exists();
        }
        if ($referenced) {
            throw ValidationException::withMessages(['is_active' => 'Masih dipakai penempatan sekarang/mendatang atau master aktif. Pindahkan referensinya sebelum mengarsipkan.']);
        }
    }
}
