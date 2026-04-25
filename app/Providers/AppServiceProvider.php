<?php

namespace App\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\JobAnalysis\AiAwareJobAnalysisService;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            return match ((string) config('jobhunter.ai_provider', 'null')) {
                'openai' => app(OpenAiProvider::class),
                'bedrock' => app(BedrockProvider::class),
                default => app(NullAiProvider::class),
            };
        });

        $this->app->bind(JobAnalysisServiceInterface::class, AiAwareJobAnalysisService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
