<?php

namespace App\Domain\Identity;

use Illuminate\Support\Facades\DB;

class Audit
{
    /** Callers pass only explicitly selected, non-secret domain values. */
    public static function record(?int $actor, string $action, string $type, ?int $id = null, array $changes = []): void
    {
        DB::table('audit_entries')->insert(['actor_id' => $actor, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => json_encode($changes), 'created_at' => now()]);
    }
}
