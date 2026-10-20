<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowRequest;

interface ParticipantWorkflow extends WorkflowRouting
{
    public function participants(WorkflowRequest $request, string $role): ?array;

    public function participantEligible(User $actor, WorkflowRequest $request, string $role): bool;

    public function validateSteps(array $steps): void;
}
