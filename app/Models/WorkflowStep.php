<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approver_ids' => 'array', 'activated_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'escalate_at' => 'immutable_datetime'];
    }
}
