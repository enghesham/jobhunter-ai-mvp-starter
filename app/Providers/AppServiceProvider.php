<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Services\AI\AiProviderManager;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\JobAnalysis\AiAwareJobAnalysisService;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use App\Policies\AnswerTemplatePolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\CandidateProfilePolicy;
use App\Policies\JobPolicy;
use App\Policies\JobSourcePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderManager::class);
        $this->app->bind(AiProviderInterface::class, fn () => app(AiProviderManager::class)->driver());

        $this->app->bind(JobAnalysisServiceInterface::class, AiAwareJobAnalysisService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(JobSource::class, JobSourcePolicy::class);
        Gate::policy(CandidateProfile::class, CandidateProfilePolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(AnswerTemplate::class, AnswerTemplatePolicy::class);

        RateLimiter::for('ai-heavy', function (Request $request) {
            /** @var User|null $user */
            $user = $request->user();

            return [
                Limit::perMinute(20)->by($user?->id ?: $request->ip()),
            ];
        });
    }
}
