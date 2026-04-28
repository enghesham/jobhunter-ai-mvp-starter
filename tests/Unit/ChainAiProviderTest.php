<?php

namespace Tests\Unit;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Services\AI\Contracts\AiProviderException;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\ChainAiProvider;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ChainAiProviderTest extends TestCase
{
    public function test_it_uses_the_first_provider_that_returns_a_payload(): void
    {
        $provider = new ChainAiProvider([
            new FakeAiProvider('gemini', 'gemini-model', null),
            new FakeAiProvider('groq', 'groq-model', ['required_skills' => ['PHP']]),
        ]);

        $response = $provider->analyzeJob($this->job(), 'prompt');

        $this->assertSame(['required_skills' => ['PHP']], $response);
        $this->assertSame('groq', $provider->name());
        $this->assertSame('groq-model', $provider->model());
    }

    public function test_it_continues_to_next_provider_when_previous_one_throws(): void
    {
        Log::spy();

        $provider = new ChainAiProvider([
            new FakeAiProvider('gemini', 'gemini-model', exception: new AiProviderException('rate limit')),
            new FakeAiProvider('groq', 'groq-model', ['why_matched' => 'Good fit']),
        ]);

        $response = $provider->explainMatch($this->profile(), $this->job(), [], 'prompt');

        $this->assertSame(['why_matched' => 'Good fit'], $response);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_throws_last_exception_when_all_providers_fail(): void
    {
        $provider = new ChainAiProvider([
            new FakeAiProvider('gemini', 'gemini-model', exception: new AiProviderException('quota')),
            new FakeAiProvider('groq', 'groq-model', exception: new AiProviderException('timeout')),
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('timeout');

        $provider->tailorResume($this->profile(), $this->job(), [], 'prompt');
    }

    private function job(): Job
    {
        return new Job(['id' => 1, 'title' => 'Senior Backend Engineer']);
    }

    private function profile(): CandidateProfile
    {
        return new CandidateProfile(['id' => 2, 'full_name' => 'Hesham']);
    }
}

class FakeAiProvider implements AiProviderInterface
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        private readonly string $providerName,
        private readonly ?string $providerModel,
        private readonly ?array $payload = null,
        private readonly ?AiProviderException $exception = null,
    ) {
    }

    public function analyzeJob(Job $job, string $prompt): ?array
    {
        return $this->respond();
    }

    public function explainMatch(CandidateProfile $profile, Job $job, array $scoreBreakdown, string $prompt): ?array
    {
        return $this->respond();
    }

    public function tailorResume(CandidateProfile $profile, Job $job, array $resumeContext, string $prompt): ?array
    {
        return $this->respond();
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(): ?string
    {
        return $this->providerModel;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function respond(): ?array
    {
        if ($this->exception) {
            throw $this->exception;
        }

        return $this->payload;
    }
}
