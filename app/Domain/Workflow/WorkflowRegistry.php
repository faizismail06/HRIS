<?php

namespace App\Domain\Workflow;

use Illuminate\Validation\ValidationException;

class WorkflowRegistry
{
    private array $handlers = [];

    public function register(string $kind, string $handler): void
    {
        $this->handlers[$kind] = $handler;
    }

    public function kinds(): array
    {
        return array_keys($this->handlers);
    }

    public function handler(string $kind): WorkflowHandler
    {
        if (! isset($this->handlers[$kind])) {
            throw ValidationException::withMessages(['kind' => 'Jenis pengajuan ini belum diaktifkan.']);
        }

        return app($this->handlers[$kind]);
    }
}
