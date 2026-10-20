<?php

namespace App\Providers;

use App\Domain\Attendance\CorrectionHandler;
use App\Domain\Attendance\OvertimeHandler;
use App\Domain\Attendance\SwapHandler;
use App\Domain\Leave\LeaveCancellationHandler;
use App\Domain\Leave\LeaveHandler;
use App\Domain\Operations\Clock;
use App\Domain\Operations\SystemClock;
use App\Domain\Workflow\WorkflowRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkflowRegistry::class);
        $this->app->bind(Clock::class, SystemClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(WorkflowRegistry::class)->register('correction', CorrectionHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('leave', LeaveHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('leave_cancel', LeaveCancellationHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('overtime', OvertimeHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('swap', SwapHandler::class);
    }
}
