<?php

namespace App\Domain\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RuntimeHealth
{
    public static function record(string $service): void
    {
        abort_unless(in_array($service, ['scheduler', 'worker'], true), 422);
        DB::table('operational_heartbeats')->updateOrInsert(['service' => $service], ['seen_at' => now()]);
    }

    public static function status(): array
    {
        $times = DB::table('operational_heartbeats')->pluck('seen_at', 'service');
        $status = [];
        foreach (['scheduler', 'worker'] as $service) {
            $seen = isset($times[$service]) ? CarbonImmutable::parse($times[$service]) : null;
            $age = $seen ? (int) $seen->diffInSeconds(now(), false) : null;
            $status[$service] = ['healthy' => $age !== null && $age >= 0 && $age <= 180, 'seen_at' => $seen?->toIso8601String(), 'age_seconds' => $age];
        }

        return $status;
    }
}
