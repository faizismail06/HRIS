<?php

namespace App\Models;

class RosterVersion extends OperationalModel
{
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
