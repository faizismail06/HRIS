<?php

namespace App\Actions;

use App\Domain\Identity\MfaPolicy;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;

class RedirectIfTwoFactorRequired extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        return MfaPolicy::skippedForLocalTesting()
            ? $next($request)
            : parent::handle($request, $next);
    }
}
