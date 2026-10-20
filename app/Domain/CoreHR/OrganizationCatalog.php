<?php

namespace App\Domain\CoreHR;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Job;

class OrganizationCatalog
{
    public const TYPES = [
        'branches' => ['model' => Branch::class, 'label' => 'Cabang'],
        'departments' => ['model' => Department::class, 'label' => 'Departemen'],
        'jobs' => ['model' => Job::class, 'label' => 'Jabatan'],
    ];

    public static function type(string $kind): array
    {
        abort_unless(isset(self::TYPES[$kind]), 404);

        return self::TYPES[$kind];
    }
}
