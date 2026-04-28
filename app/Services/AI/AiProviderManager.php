<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\BedrockProvider;
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
        if (! config('jobhunter.ai_enabled', false)) {
            return $this->nullProvider;
        }

        return match ((string) config('jobhunter.ai_provider', 'null')) {
            'openai' => $this->openAiProvider,
            'gemini' => $this->geminiProvider,
            'groq' => $this->groqProvider,
            'local', 'local_llm', 'ollama' => $this->localLlmProvider,
            'python', 'python_microservice', 'fastapi' => $this->pythonMicroserviceProvider,
            'bedrock' => $this->bedrockProvider,
            default => $this->nullProvider,
        };
    }
}
