<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRequest extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'summary' => 'array', 'submitted_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }
}
