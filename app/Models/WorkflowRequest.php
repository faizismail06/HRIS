<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public function effectActorId(): int
    {
        return (int) (DB::table('workflow_decisions')->join('workflow_steps', 'workflow_steps.id', '=', 'workflow_decisions.workflow_step_id')->where('workflow_steps.workflow_request_id', $this->id)->orderByDesc('workflow_decisions.id')->value('workflow_decisions.actor_id') ?? $this->submitted_by);
    }
}
