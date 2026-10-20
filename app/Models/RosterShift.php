<?php

namespace App\Models;

class RosterShift extends OperationalModel
{
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'is_day_off' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
