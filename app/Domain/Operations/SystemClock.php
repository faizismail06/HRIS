<?php

namespace App\Domain\Operations;

use Carbon\CarbonImmutable;

class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }
}
