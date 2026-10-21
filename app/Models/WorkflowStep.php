<?php

namespace App\Models;

class WorkflowStep extends OperationalModel
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approver_ids' => 'array', 'base_approver_ids' => 'array', 'deadline_snapshot' => 'array', 'delegation_snapshot' => 'array', 'activated_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'escalate_at' => 'immutable_datetime'];
    }
}
