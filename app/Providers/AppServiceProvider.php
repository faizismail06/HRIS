<?php

namespace App\Providers;

use App\Domain\Attendance\CorrectionHandler;
use App\Domain\Attendance\OvertimeHandler;
use App\Domain\Attendance\SwapHandler;
use App\Domain\CoreHR\ProfileChangeHandler;
use App\Domain\CoreHR\SensitiveProfiles;
use App\Domain\Documents\ContractHandler;
use App\Domain\Documents\LetterHandler;
use App\Domain\Leave\LeaveCancellationHandler;
use App\Domain\Leave\LeaveHandler;
use App\Domain\Lifecycle\OffboardingHandler;
use App\Domain\Lifecycle\OnboardingHandler;
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
        foreach (['general', 'identity', 'bank', 'tax'] as $category) {
            $this->app->bind('workflow.profile.'.$category, fn ($app) => new ProfileChangeHandler($app->make(SensitiveProfiles::class), $category));
        }
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
        $this->app->make(WorkflowRegistry::class)->register('letter', LetterHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('contract', ContractHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('onboarding', OnboardingHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('offboarding', OffboardingHandler::class);
        foreach (['profile_change' => 'general', 'identity_change' => 'identity', 'bank_change' => 'bank', 'tax_change' => 'tax'] as $kind => $category) {
            $this->app->make(WorkflowRegistry::class)->register($kind, 'workflow.profile.'.$category);
        }
    }
}
