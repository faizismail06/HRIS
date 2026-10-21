<?php

namespace App\Providers;

use App\Domain\Attendance\CorrectionHandler;
use App\Domain\Attendance\OvertimeHandler;
use App\Domain\Attendance\SwapHandler;
use App\Domain\CoreHR\CareerHandler;
use App\Domain\CoreHR\ProfileChangeHandler;
use App\Domain\CoreHR\SensitiveProfiles;
use App\Domain\Demo\DemoBoundary;
use App\Domain\Demo\ScenarioClock;
use App\Domain\Documents\ContractCancellationHandler;
use App\Domain\Documents\ContractHandler;
use App\Domain\Documents\LetterHandler;
use App\Domain\Expenses\ExpenseHandler;
use App\Domain\Leave\LeaveCancellationHandler;
use App\Domain\Leave\LeaveHandler;
use App\Domain\Lifecycle\OffboardingHandler;
use App\Domain\Lifecycle\OnboardingHandler;
use App\Domain\Operations\Clock;
use App\Domain\Operations\SystemClock;
use App\Domain\Payroll\CompensationHandler;
use App\Domain\Workflow\WorkflowRegistry;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        DemoBoundary::configuration();
        $this->app->singleton(WorkflowRegistry::class);
        $this->app->bind(Clock::class, DemoBoundary::enabled() ? ScenarioClock::class : SystemClock::class);
        if (DemoBoundary::enabled()) {
            config(['filesystems.disks.local.root' => storage_path('app/demo/'.config('hris.demo_scenario_id').'/private')]);
            config(['session.cookie' => 'hris_demo_'.str_replace('-', '', config('hris.demo_scenario_id'))]);
        }
        foreach (['general', 'identity', 'bank', 'tax'] as $category) {
            $this->app->bind('workflow.profile.'.$category, fn ($app) => new ProfileChangeHandler($app->make(SensitiveProfiles::class), $category));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function ($event) {
            if (str_starts_with((string) $event->command, 'hris:') || str_starts_with((string) $event->command, 'queue:')) {
                DemoBoundary::database();
            } elseif (DemoBoundary::enabled() && str_starts_with((string) $event->command, 'migrate')) {
                DemoBoundary::database(false);
            }
        });
        $this->app->make(WorkflowRegistry::class)->register('correction', CorrectionHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('leave', LeaveHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('leave_cancel', LeaveCancellationHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('overtime', OvertimeHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('swap', SwapHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('letter', LetterHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('contract', ContractHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('contract_cancel', ContractCancellationHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('career', CareerHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('onboarding', OnboardingHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('offboarding', OffboardingHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('compensation', CompensationHandler::class);
        $this->app->make(WorkflowRegistry::class)->register('expense', ExpenseHandler::class);
        foreach (['profile_change' => 'general', 'identity_change' => 'identity', 'bank_change' => 'bank', 'tax_change' => 'tax'] as $kind => $category) {
            $this->app->make(WorkflowRegistry::class)->register($kind, 'workflow.profile.'.$category);
        }
    }
}
