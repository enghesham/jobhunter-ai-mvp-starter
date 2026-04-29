<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;

class AiHealthInspector
{
    public function __construct(private readonly AiProviderManager $manager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $enabled = (bool) config('jobhunter.ai_enabled', false);
        $chain = config('jobhunter.ai_provider_chain', []);
        $chain = is_array($chain) ? array_values(array_filter(array_map('strval', $chain))) : [];
        $providers = $this->manager->activeProviders();

        return [
            'ai_enabled' => $enabled,
            'selection_mode' => ! $enabled ? 'disabled' : ($chain !== [] ? 'chain' : 'single'),
            'configured_provider' => (string) config('jobhunter.ai_provider', 'null'),
            'configured_chain' => $chain,
            'resolved_driver' => $this->manager->driver()->name(),
            'providers' => array_map(fn (AiProviderInterface $provider): array => $this->providerHealth($provider), $providers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerHealth(AiProviderInterface $provider): array
    {
        $name = $provider->name();
        $issues = $this->issuesFor($name);

        return [
            'name' => $name,
            'model' => $provider->model(),
            'ready' => $issues === [],
            'issues' => $issues,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function issuesFor(string $provider): array
    {
        return match ($provider) {
            'openai' => $this->missing((string) config('jobhunter.openai_api_key', ''), 'OPENAI_API_KEY'),
            'openrouter' => $this->openRouterIssues(),
            'gemini' => $this->missing((string) config('jobhunter.gemini.api_key', ''), 'GEMINI_API_KEY'),
            'groq' => $this->groqIssues(),
            'local_llm' => $this->missing((string) config('jobhunter.local_llm.base_url', ''), 'JOBHUNTER_LOCAL_LLM_BASE_URL'),
            'python_microservice' => $this->missing((string) config('jobhunter.python_microservice.base_url', ''), 'JOBHUNTER_PYTHON_AI_SERVICE_URL'),
            'bedrock' => array_merge(
                $this->missing((string) config('services.bedrock.key', ''), 'AWS_ACCESS_KEY_ID'),
                $this->missing((string) config('services.bedrock.secret', ''), 'AWS_SECRET_ACCESS_KEY'),
                $this->missing((string) config('services.bedrock.region', ''), 'AWS_DEFAULT_REGION'),
                ['BedrockProvider is still a safe stub and not implemented.']
            ),
            'null' => ['AI is disabled or null provider is selected.'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function groqIssues(): array
    {
        return array_merge(
            $this->missing((string) config('jobhunter.groq.api_key', ''), 'GROQ_API_KEY'),
            $this->missing((string) config('jobhunter.groq.base_url', ''), 'JOBHUNTER_GROQ_BASE_URL')
        );
    }

    /**
     * @return array<int, string>
     */
    private function openRouterIssues(): array
    {
        return array_merge(
            $this->missing((string) config('jobhunter.openrouter.api_key', ''), 'OPENROUTER_API_KEY'),
            $this->missing((string) config('jobhunter.openrouter.base_url', ''), 'JOBHUNTER_OPENROUTER_BASE_URL')
        );
    }

    /**
     * @return array<int, string>
     */
    private function missing(string $value, string $variable): array
    {
        return trim($value) === '' ? ["Missing {$variable}."] : [];
    }
}
