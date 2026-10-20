<?php

use App\Http\Middleware\PrivateAuthResponse;
use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/app',
    'views' => true,
    'middleware' => ['web', PrivateAuthResponse::class, 'throttle:identity'],
    'auth_middleware' => 'auth',
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => [
        Features::resetPasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
