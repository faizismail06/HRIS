<?php

namespace Tests\Support;

use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\Validator;

class TestWorkflowHandler implements WorkflowHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin', 'manager'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless($actor->employee_id, 403);
        $data = Validator::make($input, ['reason' => ['required', 'string', 'max:500']])->validate();

        return ['employee_id' => $actor->employee_id, 'payload' => $data, 'summary' => ['label' => 'Synthetic test request']];
    }

    public function submitted(WorkflowRequest $request): void
    {
        Audit::record($request->submitted_by, 'test.reserved', 'workflow_request', $request->id);
    }

    public function approved(WorkflowRequest $request): void
    {
        Audit::record($request->submitted_by, 'test.effect', 'workflow_request', $request->id);
    }

    public function released(WorkflowRequest $request): void
    {
        Audit::record($request->submitted_by, 'test.released', 'workflow_request', $request->id);
    }
}
