<?php

namespace App\Domain\Workflow;

use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;

interface StageWorkflow
{
    public function stageApproved(WorkflowRequest $request, WorkflowStep $step): void;
}
