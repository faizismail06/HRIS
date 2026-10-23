<?php

namespace App\Domain\Identity;

class MfaPolicy
{
    public static function skippedForLocalTesting(): bool
    {
        return app()->environment('local')
            && ! config('hris.demo_features_enabled')
            && (bool) config('hris.local_skip_mfa');
    }
}
