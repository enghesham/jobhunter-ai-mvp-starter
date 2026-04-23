<?php

namespace App\Providers;

use App\Services\JobAnalysis\BasicKeywordJobAnalysisService;
use App\Services\JobAnalysis\Contracts\JobAnalysisServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JobAnalysisServiceInterface::class, BasicKeywordJobAnalysisService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
