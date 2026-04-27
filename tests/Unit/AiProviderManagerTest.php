<?php

namespace Tests\Unit;

use App\Services\AI\AiProviderManager;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;
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
            app(BedrockProvider::class),
        );

        $this->assertSame('null', $manager->driver()->name());
    }
}
