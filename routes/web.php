<?php

use App\Http\Controllers\PrototypeDashboardController;
use App\Http\Controllers\PrototypePeopleController;
use App\Http\Middleware\EnsureUiPreviewIsEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment(['local', 'testing']) && config('hris.ui_preview_enabled')) {
        return redirect()->route('prototype.dashboard');
    }

    return view('welcome');
});

if (app()->environment(['local', 'testing'])) {
    Route::middleware(EnsureUiPreviewIsEnabled::class)->prefix('prototype')->name('prototype.')->group(function () {
        Route::get('/dashboard', PrototypeDashboardController::class)->name('dashboard');
        Route::get('/employees', [PrototypePeopleController::class, 'index'])->name('employees');
        Route::get('/employees/{employee}', [PrototypePeopleController::class, 'show'])->name('employees.show');
        Route::get('/organization', [PrototypePeopleController::class, 'organization'])->name('organization');
    });
}
