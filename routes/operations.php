<?php

use App\Http\Controllers\InboxController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->middleware('throttle:120,1')->group(function () {
    Route::post('/workflow-definitions', [WorkflowController::class, 'definitions'])->name('workflow.define');
    Route::get('/requests', [WorkflowController::class, 'index'])->name('requests');
    Route::post('/requests', [WorkflowController::class, 'store'])->name('requests.store');
    Route::get('/requests/{id}', [WorkflowController::class, 'show'])->whereNumber('id')->name('requests.show');
    foreach (['submit', 'decide', 'withdraw', 'reassign'] as $action) {
        Route::post('/requests/{id}/'.$action, [WorkflowController::class, $action])->whereNumber('id')->name('requests.'.$action);
    }
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::patch('/inbox/{id}', [InboxController::class, 'update'])->whereNumber('id')->name('inbox.update');
    Route::post('/requests/{id}/attachments', [PrivateFileController::class, 'store'])->whereNumber('id')->name('attachments.store');
    Route::get('/attachments/{id}/download', [PrivateFileController::class, 'download'])->whereNumber('id')->name('attachments.download');
});
