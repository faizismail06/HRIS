<?php

namespace App\Providers;

use App\Actions\RedirectIfTwoFactorRequired;
use App\Actions\ResetUserPassword;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Identity\Audit;
use App\Http\Responses\ResetLinkResponse;
use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfTwoFactorRequired::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, ResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, ResetLinkResponse::class);
    }

    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('auth.challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::authenticateUsing(function (Request $request) {
            return (new Timebox)->call(function () use ($request) {
                $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower((string) $request->input('email'))])->first();
                // A fixed dummy hash avoids skipping bcrypt for unknown accounts. Timebox applies to both paths.
                $hash = $user?->password ?? config('hashing.dummy_password');
                if (! $hash) {
                    $hash = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                }
                $valid = Hash::check((string) $request->input('password'), $hash);

                return $user && $valid && AccountSecurity::available($user) ? $user : null;
            }, 200000);
        });
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(hash('sha256', mb_strtolower((string) $r->input('email')).'|'.$r->ip())));
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id').'|'.$r->ip()));
        RateLimiter::for('identity', fn (Request $r) => $r->isMethod('GET') ? Limit::none() : Limit::perMinute(20)->by($r->ip()));
        Event::listen(TwoFactorAuthenticationChallenged::class, function ($event) {
            session()->put('login.security_version', $event->user->security_version);
        });
        Event::listen(Login::class, function (Login $event) {
            session()->put('auth.security_version', $event->user->security_version);
            session()->forget('auth.mfa_user');
            if (request()->routeIs('two-factor.login.store') && $event->user->hasEnabledTwoFactorAuthentication()) {
                session()->put('auth.mfa_user', $event->user->id);
            }
            Audit::record($event->user->id, 'account.login', 'user', $event->user->id);
        });
        foreach ([
            TwoFactorAuthenticationEnabled::class => 'account.mfa_enabled',
            TwoFactorAuthenticationConfirmed::class => 'account.mfa_confirmed',
            TwoFactorAuthenticationDisabled::class => 'account.mfa_disabled',
            RecoveryCodesGenerated::class => 'account.recovery_codes_generated',
            PasswordReset::class => 'account.password_reset',
        ] as $eventClass => $action) {
            Event::listen($eventClass, fn ($event) => AccountSecurity::changed($event->user, $action, $event instanceof TwoFactorAuthenticationConfirmed || ($event instanceof RecoveryCodesGenerated && session('auth.mfa_user') === $event->user->id)));
        }
        Event::listen(RecoveryCodeReplaced::class, fn ($event) => Audit::record($event->user->id, 'account.recovery_code_used', 'user', $event->user->id));
    }
}
