<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowRequest;

interface WorkflowHandler
{
    public function approverRoles(): array;

    /** Validate and whitelist fields; derive the subject from the session or an explicitly authorized staff action. */
    public function prepare(User $actor, array $input): array;

    public function submitted(WorkflowRequest $request): void;

    public function approved(WorkflowRequest $request): void;

    public function released(WorkflowRequest $request): void;
}
