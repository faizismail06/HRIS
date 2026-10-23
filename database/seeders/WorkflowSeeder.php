<?php

namespace Database\Seeders;

use App\Domain\Workflow\WorkflowService;
use App\Models\WorkflowDefinition;
use Illuminate\Support\Str;

class WorkflowSeeder extends LocalSeeder
{
    protected array $dependencies = [UserSeeder::class];

    protected function seedLocal(): void
    {
        foreach (['leave', 'leave_exception', 'leave_cancel', 'correction', 'overtime', 'swap'] as $kind) {
            // Preserve any existing routing for this kind instead of competing with company workflows.
            if (WorkflowDefinition::where('kind', $kind)->exists()) {
                continue;
            }
            $steps = $kind === 'swap' ? [['role' => 'counterparty'], ['role' => 'hr_admin']] : [['role' => 'hr_admin']];
            app(WorkflowService::class)->define($this->hr(), (string) Str::uuid(), ['name' => 'CONTOH TESTING - '.$kind, 'kind' => $kind, 'priority' => 0, 'min_amount_minor' => 0, 'steps' => $steps]);
        }
    }
}
