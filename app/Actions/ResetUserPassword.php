<?php

namespace App\Actions;

use App\Rules\AccountPassword;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'string', new AccountPassword, Password::min(12)->mixedCase()->numbers(), 'confirmed']])->validate();
        $user->forceFill(['password' => $input['password']])->save();
    }
}
