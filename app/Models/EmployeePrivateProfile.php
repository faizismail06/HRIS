<?php

namespace App\Models;

class EmployeePrivateProfile extends OperationalModel
{
    protected $hidden = ['data', 'national_id_hash'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'verified_at' => 'immutable_datetime'];
    }
}
