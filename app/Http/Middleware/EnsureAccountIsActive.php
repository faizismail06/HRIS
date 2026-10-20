<?php

namespace App\Http\Middleware;

use App\Domain\Identity\AccountSecurity;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $pending = ! $user && $request->session()->has('login.id');
        if ($user || $pending) {
            $user = $user ? $user->fresh() : User::find($request->session()->get('login.id'));
            $version = $request->session()->get($pending ? 'login.security_version' : 'auth.security_version', 0);
            if (! $user || ! AccountSecurity::available($user) || (int) $version !== (int) $user->security_version) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Akses sesi berakhir.'], 401) : redirect()->route('login')->withErrors(['email' => 'Akses sesi berakhir. Silakan masuk kembali.']);
            }
            if (! $pending) {
                Auth::guard('web')->setUser($user);
            }
        }

        return $next($request);
    }
}
