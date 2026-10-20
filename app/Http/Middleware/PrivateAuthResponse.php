<?php

namespace App\Http\Middleware;

use App\Domain\Identity\AccountSecurity;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrivateAuthResponse
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(app()->environment('production') && ! $request->isSecure(), 403, 'HTTPS diperlukan.');
        if ($request->routeIs('password.email')) {
            abort_if(app()->environment('production') && in_array(config('mail.default'), ['log', 'array']), 503, 'Pengiriman pemulihan belum dikonfigurasi.');
        }
        $run = function () use ($request, $next) {
            $pending = ! $request->user() && $request->session()->has('login.id');
            $id = $request->user()?->id ?? $request->session()->get('login.id');
            if ($id && ! $request->isMethod('GET')) {
                // Serialize challenges/settings so two concurrent requests cannot consume one recovery code.
                $user = User::query()->lockForUpdate()->find($id);
                $version = $request->session()->get($pending ? 'login.security_version' : 'auth.security_version', 0);
                abort_unless($user && AccountSecurity::available($user) && (int) $version === (int) $user->security_version, 401);
                if (! $pending) {
                    Auth::guard('web')->setUser($user);
                }
            }

            return $next($request);
        };
        $response = $request->isMethod('GET') ? $run() : DB::transaction($run);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
