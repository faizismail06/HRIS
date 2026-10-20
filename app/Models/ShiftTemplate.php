<?php

namespace App\Models;

class ShiftTemplate extends OperationalModel
{
    protected function casts(): array
    {
        return ['unpaid_breaks' => 'array', 'is_active' => 'boolean'];
    }
}
