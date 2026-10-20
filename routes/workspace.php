<?php

use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\PrivateAuthResponse;
use App\Http\Middleware\RequireMfa;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', PrivateAuthResponse::class])->group(function () {
    Route::get('/account/security', [WorkspaceController::class, 'security'])->middleware('password.confirm')->name('account.security');
    Route::middleware(RequireMfa::class)->prefix('app')->name('workspace.')->group(function () {
        Route::get('/', [WorkspaceController::class, 'home'])->name('home');
        Route::get('/employees', [WorkspaceController::class, 'employees'])->name('employees');
        Route::get('/employees/{employee}', [WorkspaceController::class, 'employees'])->whereNumber('employee')->name('employees.show');
    });
});
