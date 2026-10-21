<?php

namespace App\Domain\Demo;

use App\Domain\Operations\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ScenarioClock implements Clock
{
    public function now(): CarbonImmutable
    {
        DemoBoundary::database();
        $time = DB::table('installation_boundary')->where('id', 1)->value('scenario_time');

        return $time ? CarbonImmutable::parse($time)->setTimezone(config('app.timezone')) : CarbonImmutable::now(config('app.timezone'));
    }
}
