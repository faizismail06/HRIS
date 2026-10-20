<?php

use App\Http\Controllers\PrototypeDashboardController;
use App\Http\Middleware\EnsureUiPreviewIsEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment(['local', 'testing']) && config('hris.ui_preview_enabled')) {
        return redirect()->route('prototype.dashboard');
    }

    return view('welcome');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/prototype/dashboard', PrototypeDashboardController::class)
        ->middleware(EnsureUiPreviewIsEnabled::class)
        ->name('prototype.dashboard');
}
