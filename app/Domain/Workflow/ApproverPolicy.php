<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowRequest;

interface ApproverPolicy
{
    public function mayApprove(User $actor, WorkflowRequest $request, string $role): bool;
}
