<?php

namespace App\Domain\Operations;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
