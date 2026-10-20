<?php

namespace App\Models;

class LeaveType extends OperationalModel
{
    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'deducts_balance' => 'boolean', 'allows_half_day' => 'boolean', 'requires_evidence' => 'boolean', 'is_active' => 'boolean'];
    }
}
