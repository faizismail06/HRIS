<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret || (int) $request->session()->get('auth.mfa_user', 0) !== $user->id) {
            return $request->expectsJson() ? response()->json(['message' => 'Verifikasi dua langkah diperlukan.'], 403) : redirect()->route('account.security');
        }

        return $next($request);
    }
}
