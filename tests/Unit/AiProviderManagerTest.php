<?php

namespace Tests\Unit;

use App\Services\AI\AiProviderManager;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\LocalLlmProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\PythonMicroserviceProvider;
use Tests\TestCase;

class AiProviderManagerTest extends TestCase
{
    public function test_it_returns_null_provider_when_ai_is_disabled(): void
    {
        config()->set('jobhunter.ai_enabled', false);
        config()->set('jobhunter.ai_provider', 'openai');

        $manager = new AiProviderManager(
            new NullAiProvider(),
            app(OpenAiProvider::class),
            app(GeminiProvider::class),
            app(LocalLlmProvider::class),
            app(PythonMicroserviceProvider::class),
            app(BedrockProvider::class),
        );

        $this->assertSame('null', $manager->driver()->name());
    }

    public function test_it_returns_gemini_provider_when_configured(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        config()->set('jobhunter.ai_provider', 'gemini');

        $manager = $this->makeManager();

        $this->assertSame('gemini', $manager->driver()->name());
    }

    public function test_it_returns_local_llm_provider_for_local_aliases(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        config()->set('jobhunter.ai_provider', 'ollama');

        $manager = $this->makeManager();

        $this->assertSame('local_llm', $manager->driver()->name());
    }

    public function test_it_returns_python_microservice_provider_for_python_aliases(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        config()->set('jobhunter.ai_provider', 'python_microservice');

        $manager = $this->makeManager();

        $this->assertSame('python_microservice', $manager->driver()->name());
    }

    private function makeManager(): AiProviderManager
    {
        return new AiProviderManager(
            new NullAiProvider(),
            app(OpenAiProvider::class),
            app(GeminiProvider::class),
            app(LocalLlmProvider::class),
            app(PythonMicroserviceProvider::class),
            app(BedrockProvider::class),
        );
    }
}
