<?php

namespace App\Models;

class WorkLocation extends OperationalModel
{
    protected function casts(): array
    {
        return ['requires_photo' => 'boolean', 'is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }
}
