<?php

use App\Http\Controllers\AccountAdministrationController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttendanceCloseController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LifecycleController;
use App\Http\Controllers\OperationsHealthController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\PrivateProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkspaceSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->middleware('throttle:120,1')->group(function () {
    Route::get('/search', WorkspaceSearchController::class);
    Route::get('/reports/{kind}', [ReportController::class, 'show']);
    Route::get('/attendance-snapshots/{id}', [ReportController::class, 'snapshot'])->whereNumber('id');
    Route::post('/reports/{kind}/exports', [ReportController::class, 'export']);
    Route::get('/report-exports/{id}', [ReportController::class, 'status'])->whereNumber('id');
    Route::get('/report-exports/{id}/download', [ReportController::class, 'download'])->whereNumber('id');
    Route::post('/imports', [ImportController::class, 'upload']);
    Route::get('/imports/{id}', [ImportController::class, 'show'])->whereNumber('id');
    foreach (['map', 'preview', 'commit'] as $action) {
        Route::post('/imports/{id}/'.$action, [ImportController::class, $action])->whereNumber('id');
    }
    Route::post('/expense-policies', [ExpenseController::class, 'policy']);
    Route::get('/expense-claims/{id}', [ExpenseController::class, 'detail'])->whereNumber('id');
    Route::post('/expense-claims/{id}/allocate', [ExpenseController::class, 'allocate'])->whereNumber('id');
    Route::post('/expense-allocations/{id}/settle', [ExpenseController::class, 'settle'])->whereNumber('id');
    Route::post('/payroll-policies', [PayrollController::class, 'policy']);
    Route::get('/payroll-policies/{id}', [PayrollController::class, 'policyDetail'])->whereNumber('id');
    Route::get('/requests/{id}/proposed-compensation', [PayrollController::class, 'proposedCompensation'])->whereNumber('id');
    Route::post('/payroll-policies/{id}/approve', [PayrollController::class, 'approvePolicy'])->whereNumber('id');
    Route::post('/payroll-batches', [PayrollController::class, 'create']);
    Route::get('/payroll-batches/{id}', [PayrollController::class, 'show'])->whereNumber('id');
    Route::post('/payroll-batches/{id}/calculate', [PayrollController::class, 'calculate'])->whereNumber('id');
    Route::post('/payroll-batches/{id}/{action}', [PayrollController::class, 'transition'])->whereNumber('id')->where('action', 'review|approve|finalize');
    Route::get('/payslips/{id}/download', [PayrollController::class, 'payslip'])->whereNumber('id');
    Route::post('/bank-export-templates', [PayrollController::class, 'bankTemplate']);
    Route::post('/bank-export-templates/{id}/confirm', [PayrollController::class, 'confirmTemplate'])->whereNumber('id');
    Route::post('/payroll-batches/{id}/bank-export', [PayrollController::class, 'export'])->whereNumber('id');
    Route::post('/bank-exports/{id}/download', [PayrollController::class, 'downloadExport'])->whereNumber('id');
    Route::post('/payroll-rows/{id}/payment-outcomes', [PayrollController::class, 'outcome'])->whereNumber('id');
    Route::post('/lifecycle-templates', [LifecycleController::class, 'template']);
    Route::post('/onboarding-cases', [LifecycleController::class, 'onboarding']);
    Route::get('/lifecycle-cases/{id}', [LifecycleController::class, 'show'])->whereNumber('id');
    Route::post('/lifecycle-tasks/{id}', [LifecycleController::class, 'task'])->whereNumber('id');
    Route::get('/assets', [AssetController::class, 'index']);
    Route::post('/assets', [AssetController::class, 'store']);
    Route::get('/my-assets', [AssetController::class, 'mine']);
    Route::post('/assets/{id}/issue', [AssetController::class, 'issue'])->whereNumber('id');
    Route::post('/asset-custodies/{id}/accept', [AssetController::class, 'accept'])->whereNumber('id');
    Route::post('/asset-custodies/{id}/return', [AssetController::class, 'returnAsset'])->whereNumber('id');
    Route::post('/consumable-issues', [AssetController::class, 'consumable']);
    Route::post('/document-templates', [DocumentController::class, 'template']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->whereNumber('id');
    Route::get('/employees/{id}/contracts', [DocumentController::class, 'contracts'])->whereNumber('id');
    Route::post('/accounts/{id}/access', [AccountAdministrationController::class, 'access'])->whereNumber('id');
    Route::post('/accounts/{id}/suspensions', [AccountAdministrationController::class, 'suspend'])->whereNumber('id');
    Route::post('/account-suspensions/{id}/revoke', [AccountAdministrationController::class, 'revoke'])->whereNumber('id');
    Route::get('/employees/{id}/private-profile/{category}', [PrivateProfileController::class, 'show'])->whereNumber('id');
    Route::get('/requests/{id}/proposed-profile', [PrivateProfileController::class, 'proposed'])->whereNumber('id');
    Route::post('/profile-fields', [PrivateProfileController::class, 'field']);
    Route::get('/operations/health', [OperationsHealthController::class, 'index'])->name('operations.health');
    Route::post('/operations/deliveries/{id}/retry', [OperationsHealthController::class, 'retry'])->whereNumber('id')->name('operations.retry');
    Route::post('/attendance-periods', [AttendanceCloseController::class, 'create'])->name('attendance-periods.store');
    foreach (['review', 'close', 'reopen'] as $action) {
        Route::post('/attendance-periods/{id}/'.$action, [AttendanceCloseController::class, $action])->whereNumber('id')->name('attendance-periods.'.$action);
    }
    Route::post('/overtime/{id}/actual', [AttendanceCloseController::class, 'actual'])->whereNumber('id')->name('overtime.actual');
    Route::post('/overtime/{id}/verify', [AttendanceCloseController::class, 'verify'])->whereNumber('id')->name('overtime.verify');
    Route::post('/leave-types', [LeaveController::class, 'type'])->name('leave.types.store');
    Route::get('/leave-types', [LeaveController::class, 'types'])->name('leave.types');
    Route::post('/leave-credits', [LeaveController::class, 'grant'])->name('leave.grant');
    Route::get('/my-leave-balances', [LeaveController::class, 'balances'])->name('leave.balances');
    Route::post('/work-locations', [AttendanceController::class, 'location'])->name('locations.store');
    Route::post('/work-calendar-dates', [AttendanceController::class, 'calendarDate'])->name('calendar.store');
    Route::get('/team-calendar', [AttendanceController::class, 'teamCalendar'])->name('calendar.team');
    Route::post('/shift-templates', [AttendanceController::class, 'template'])->name('shifts.store');
    Route::post('/rosters/preview', [AttendanceController::class, 'preview'])->name('rosters.preview');
    Route::post('/rosters', [AttendanceController::class, 'draft'])->name('rosters.store');
    Route::post('/rosters/{id}/publish', [AttendanceController::class, 'publish'])->whereNumber('id')->name('rosters.publish');
    Route::get('/my-schedule', [AttendanceController::class, 'mine'])->name('schedule.mine');
    Route::post('/attendance/clock-{direction}', [AttendanceController::class, 'clock'])->where('direction', 'in|out')->name('attendance.clock');
    Route::get('/attendance/{id}', [AttendanceController::class, 'result'])->whereNumber('id')->name('attendance.show');
    Route::post('/attendance/{id}/photo', [PrivateFileController::class, 'shiftPhoto'])->whereNumber('id')->name('attendance.photo');
    Route::post('/workflow-definitions', [WorkflowController::class, 'definitions'])->name('workflow.define');
    Route::get('/requests', [WorkflowController::class, 'index'])->name('requests');
    Route::post('/requests/bulk-decisions', [WorkflowController::class, 'bulkDecide'])->name('requests.bulk-decide');
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
