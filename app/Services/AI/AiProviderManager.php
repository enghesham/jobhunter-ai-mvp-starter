<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\BedrockProvider;
use App\Services\AI\Providers\NullAiProvider;
use App\Services\AI\Providers\OpenAiProvider;

class AiProviderManager
{
    public function __construct(
        private readonly NullAiProvider $nullProvider,
        private readonly OpenAiProvider $openAiProvider,
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
            'bedrock' => $this->bedrockProvider,
            default => $this->nullProvider,
        };
    }
}
