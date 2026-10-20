<?php

namespace App\Domain\Workflow;

interface WorkflowRouting
{
    public function validateSteps(array $steps): void;
}
