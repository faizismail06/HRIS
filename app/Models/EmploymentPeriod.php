<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentPeriod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'offboarding' => 'boolean'];
    }
}
