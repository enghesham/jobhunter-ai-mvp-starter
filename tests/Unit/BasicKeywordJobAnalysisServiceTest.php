<?php

namespace Tests\Unit;

use App\Modules\Jobs\Domain\Models\Job;
use App\Services\JobAnalysis\BasicKeywordJobAnalysisService;
use PHPUnit\Framework\TestCase;

class BasicKeywordJobAnalysisServiceTest extends TestCase
{
    public function test_it_extracts_backend_skills_and_seniority(): void
    {
        $job = new Job([
            'company_name' => 'Acme',
            'title' => 'Senior Backend Laravel Engineer',
            'description_clean' => 'Build REST APIs with PHP, Laravel, PostgreSQL, Redis, Docker, AWS, queues, and clean architecture.',
        ]);

        $analysis = (new BasicKeywordJobAnalysisService())->analyze($job);

        $this->assertSame('senior', $analysis['seniority']);
        $this->assertSame('backend', $analysis['role_type']);
        $this->assertContains('Laravel', $analysis['required_skills']);
        $this->assertContains('PostgreSQL', $analysis['required_skills']);
        $this->assertContains('AWS', $analysis['required_skills']);
    }
}
