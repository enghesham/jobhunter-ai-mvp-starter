<?php

namespace Tests\Unit;

use App\Services\AI\AiHealthInspector;
use App\Services\AI\AiProviderManager;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\LocalLlmProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\PythonMicroserviceProvider;
use Tests\TestCase;

class AiHealthInspectorTest extends TestCase
{
    public function test_it_reports_chain_provider_health_without_exposing_secrets(): void
    {
        config()->set('jobhunter.ai_enabled', true);
        config()->set('jobhunter.ai_provider', 'openai');
        config()->set('jobhunter.ai_provider_chain', ['gemini', 'groq']);
        config()->set('jobhunter.gemini.api_key', 'gemini-secret');
        config()->set('jobhunter.groq.api_key', '');
        config()->set('jobhunter.groq.base_url', 'https://api.groq.com/openai/v1');

        $report = (new AiHealthInspector($this->manager()))->inspect();

        $this->assertTrue($report['ai_enabled']);
        $this->assertSame('chain', $report['selection_mode']);
        $this->assertSame(['gemini', 'groq'], $report['configured_chain']);
        $this->assertSame('chain(gemini,groq)', $report['resolved_driver']);
        $this->assertSame('gemini', $report['providers'][0]['name']);
        $this->assertTrue($report['providers'][0]['ready']);
        $this->assertSame('groq', $report['providers'][1]['name']);
        $this->assertFalse($report['providers'][1]['ready']);
        $this->assertContains('Missing GROQ_API_KEY.', $report['providers'][1]['issues']);
    }

    private function manager(): AiProviderManager
    {
        return new AiProviderManager(
            new NullAiProvider(),
            app(OpenAiProvider::class),
            app(GeminiProvider::class),
            app(GroqProvider::class),
            app(LocalLlmProvider::class),
            app(PythonMicroserviceProvider::class),
            app(BedrockProvider::class),
        );
    }
}
