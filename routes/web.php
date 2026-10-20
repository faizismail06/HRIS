<?php

use App\Http\Controllers\PrototypeAdministrationController;
use App\Http\Controllers\PrototypeAttendanceController;
use App\Http\Controllers\PrototypeDashboardController;
use App\Http\Controllers\PrototypeEmploymentController;
use App\Http\Controllers\PrototypeFinanceController;
use App\Http\Controllers\PrototypeLifecycleController;
use App\Http\Controllers\PrototypePeopleController;
use App\Http\Controllers\PrototypePortalController;
use App\Http\Controllers\PrototypeReportsController;
use App\Http\Controllers\PrototypeRequestsController;
use App\Http\Controllers\PrototypeScreensController;
use App\Http\Controllers\PrototypeWorkspaceController;
use App\Http\Middleware\EnsureUiPreviewIsEnabled;
use Illuminate\Support\Facades\Route;

require __DIR__.'/workspace.php';

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
        Route::get('/attendance', [PrototypeAttendanceController::class, 'index'])->name('attendance');
        Route::get('/attendance/{record}', [PrototypeAttendanceController::class, 'show'])->name('attendance.show');
        Route::get('/schedules', [PrototypeAttendanceController::class, 'schedules'])->name('schedules');
        Route::get('/requests', [PrototypeRequestsController::class, 'index'])->name('requests');
        Route::get('/requests/{requestId}', [PrototypeRequestsController::class, 'show'])->name('requests.show');
        Route::get('/leave-balances', [PrototypeRequestsController::class, 'balances'])->name('leave-balances');
        Route::get('/team-calendar', [PrototypeRequestsController::class, 'calendar'])->name('team-calendar');
        Route::get('/lifecycle', [PrototypeLifecycleController::class, 'index'])->name('lifecycle');
        Route::get('/lifecycle/{record}', [PrototypeLifecycleController::class, 'show'])->name('lifecycle.show');
        Route::get('/assets', [PrototypeLifecycleController::class, 'assets'])->name('assets');
        Route::get('/assets/{record}', [PrototypeLifecycleController::class, 'asset'])->name('assets.show');
        Route::get('/payroll', [PrototypeFinanceController::class, 'payrolls'])->name('payroll');
        Route::get('/payroll/{batch}', [PrototypeFinanceController::class, 'payroll'])->name('payroll.show');
        Route::get('/payroll/{batch}/payslips/{employee}', [PrototypeFinanceController::class, 'payslip'])->name('payroll.payslip');
        Route::get('/reimbursements', [PrototypeFinanceController::class, 'claims'])->name('reimbursements');
        Route::get('/reimbursements/{claim}', [PrototypeFinanceController::class, 'claim'])->name('reimbursements.show');
        Route::get('/attendance-reports', [PrototypeReportsController::class, 'report'])->name('attendance-reports');
        Route::get('/attendance-periods', [PrototypeReportsController::class, 'periods'])->name('attendance-periods');
        Route::get('/attendance-periods/{period}', [PrototypeReportsController::class, 'period'])->name('attendance-periods.show');
        Route::get('/settings', [PrototypeAdministrationController::class, 'settings'])->name('settings');
        Route::get('/audit', [PrototypeAdministrationController::class, 'audit'])->name('audit');
        Route::get('/imports', [PrototypeAdministrationController::class, 'imports'])->name('imports');
        Route::get('/imports/{batch}', [PrototypeAdministrationController::class, 'import'])->name('imports.show');
        Route::get('/notifications', [PrototypeWorkspaceController::class, 'notifications'])->name('notifications');
        Route::get('/notifications/{notification}', [PrototypeWorkspaceController::class, 'notification'])->name('notifications.show');
        Route::get('/search', [PrototypeWorkspaceController::class, 'search'])->name('search');
        Route::get('/portals/{persona}', PrototypePortalController::class)->name('portal');
        Route::get('/screens', [PrototypeScreensController::class, 'index'])->name('screens');
        Route::get('/screens/{screen}', [PrototypeScreensController::class, 'form'])->name('screen');
        Route::get('/states/{state}', [PrototypeScreensController::class, 'state'])->name('state');
        foreach (['contracts', 'letters', 'career-changes'] as $kind) {
            Route::get('/'.$kind, [PrototypeEmploymentController::class, 'index'])->defaults('kind', $kind)->name($kind);
            Route::get('/'.$kind.'/{record}', [PrototypeEmploymentController::class, 'show'])->defaults('kind', $kind)->name($kind.'.show');
        }
    });
}
