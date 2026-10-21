<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\WorkflowWorkspaceController;
use App\Http\Controllers\WorkspaceActivityController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\PrivateAuthResponse;
use App\Http\Middleware\RequireMfa;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', PrivateAuthResponse::class])->group(function () {
    Route::get('/account/security', [WorkspaceController::class, 'security'])->middleware('password.confirm')->name('account.security');
    Route::middleware(RequireMfa::class)->prefix('app')->name('workspace.')->group(function () {
        require __DIR__.'/operations.php';
        Route::get('/', [WorkspaceController::class, 'home'])->name('home');
        Route::get('/notifications', [WorkspaceActivityController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}', [WorkspaceActivityController::class, 'updateNotification'])->whereNumber('id')->middleware('throttle:60,1')->name('notifications.update');
        Route::get('/search', [WorkspaceActivityController::class, 'search'])->middleware('throttle:60,1')->name('search');
        Route::get('/requests', [WorkflowWorkspaceController::class, 'index'])->name('requests');
        Route::get('/requests/create', [WorkflowWorkspaceController::class, 'create'])->name('requests.create');
        Route::post('/requests', [WorkflowWorkspaceController::class, 'store'])->middleware('throttle:60,1')->name('requests.store');
        Route::get('/requests/{id}', [WorkflowWorkspaceController::class, 'show'])->whereNumber('id')->name('requests.show');
        Route::post('/requests/{id}/attachments', [WorkflowWorkspaceController::class, 'upload'])->whereNumber('id')->middleware('throttle:30,1')->name('requests.upload');
        Route::post('/requests/{id}/{action}', [WorkflowWorkspaceController::class, 'action'])->whereNumber('id')->where('action', 'submit|withdraw|decide')->middleware('throttle:60,1')->name('requests.action');
        Route::get('/organization/{kind?}', [OrganizationController::class, 'index'])->where('kind', 'branches|departments|jobs')->name('organization');
        Route::get('/organization/{kind}/create', [OrganizationController::class, 'form'])->where('kind', 'branches|departments|jobs')->name('organization.create');
        Route::post('/organization/{kind}', [OrganizationController::class, 'store'])->where('kind', 'branches|departments|jobs')->name('organization.store');
        Route::get('/organization/{kind}/{record}/edit', [OrganizationController::class, 'form'])->where('kind', 'branches|departments|jobs')->whereNumber('record')->name('organization.edit');
        Route::put('/organization/{kind}/{record}', [OrganizationController::class, 'update'])->where('kind', 'branches|departments|jobs')->whereNumber('record')->name('organization.update');
        Route::get('/employees', [WorkspaceController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/change', [EmployeeController::class, 'changeForm'])->whereNumber('employee')->name('employees.change');
        Route::post('/employees/{employee}/changes', [EmployeeController::class, 'propose'])->whereNumber('employee')->name('employees.propose');
        Route::get('/employee-changes', [EmployeeController::class, 'changes'])->name('changes');
        Route::get('/employee-changes/{change}', [EmployeeController::class, 'change'])->whereNumber('change')->name('changes.show');
        Route::post('/employee-changes/{change}/review', [EmployeeController::class, 'review'])->whereNumber('change')->name('changes.review');
        Route::get('/employees/{employee}', [WorkspaceController::class, 'employees'])->whereNumber('employee')->name('employees.show');
    });
});
