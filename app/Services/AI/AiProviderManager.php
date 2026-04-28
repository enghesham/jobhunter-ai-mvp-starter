<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\ChainAiProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\LocalLlmProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\PythonMicroserviceProvider;

class AiProviderManager
{
    public function __construct(
        private readonly NullAiProvider $nullProvider,
        private readonly OpenAiProvider $openAiProvider,
        private readonly GeminiProvider $geminiProvider,
        private readonly GroqProvider $groqProvider,
        private readonly LocalLlmProvider $localLlmProvider,
        private readonly PythonMicroserviceProvider $pythonMicroserviceProvider,
        private readonly BedrockProvider $bedrockProvider,
    ) {
    }

    public function driver(): AiProviderInterface
    {
        $providers = $this->activeProviders();

        if (count($providers) > 1) {
            return new ChainAiProvider($providers);
        }

        return $providers[0] ?? $this->nullProvider;
    }

    /**
     * @return array<int, AiProviderInterface>
     */
    public function activeProviders(): array
    {
        if (! config('jobhunter.ai_enabled', false)) {
            return [$this->nullProvider];
        }

        $chain = $this->configuredChain();

        if ($chain !== []) {
            return $chain;
        }

        return [$this->providerFor((string) config('jobhunter.ai_provider', 'null')) ?? $this->nullProvider];
    }

    /**
     * @return array<int, AiProviderInterface>
     */
    private function configuredChain(): array
    {
        $keys = config('jobhunter.ai_provider_chain', []);

        if (! is_array($keys) || $keys === []) {
            return [];
        }

        return collect($keys)
            ->map(fn (mixed $key): ?AiProviderInterface => $this->providerFor((string) $key))
            ->filter()
            ->reject(fn (AiProviderInterface $provider): bool => $provider->name() === 'null')
            ->unique(fn (AiProviderInterface $provider): string => $provider->name())
            ->values()
            ->all();
    }

    public function providerFor(string $key): ?AiProviderInterface
    {
        return match ($key) {
            'openai' => $this->openAiProvider,
            'gemini' => $this->geminiProvider,
            'groq' => $this->groqProvider,
            'local', 'local_llm', 'ollama' => $this->localLlmProvider,
            'python', 'python_microservice', 'fastapi' => $this->pythonMicroserviceProvider,
            'bedrock' => $this->bedrockProvider,
            'null' => $this->nullProvider,
            default => null,
        };
    }
}
