<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class ResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $message = 'Jika email terdaftar, tautan pemulihan akan dikirim. Periksa kotak masuk dan folder spam.';

        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}
