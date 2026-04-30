<?php

namespace Tests\Unit;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Services\Matching\JobMatchScoringService;
use PHPUnit\Framework\TestCase;

class JobMatchScoringServiceTest extends TestCase
{
    public function test_it_scores_backend_laravel_roles_as_strong_matches(): void
    {
        $profile = new CandidateProfile([
            'headline' => 'Senior Backend / Laravel / Python Developer',
            'years_experience' => 10,
            'preferred_roles' => ['Senior Backend Engineer', 'Senior Laravel Developer'],
            'preferred_locations' => ['remote'],
            'core_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis', 'Docker', 'AWS', 'Queues'],
        ]);

        $job = new Job([
            'title' => 'Senior Backend Engineer',
            'location' => 'Remote',
            'remote_type' => 'remote',
        ]);

        $job->setRelation('analysis', new JobAnalysis([
            'required_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis'],
            'preferred_skills' => ['Docker', 'AWS'],
            'years_experience_min' => 8,
            'seniority' => 'senior',
            'role_type' => 'backend',
            'domain_tags' => ['saas', 'cloud'],
        ]));

        $score = (new JobMatchScoringService())->score($profile, $job);

        $this->assertGreaterThanOrEqual(85, $score['overall_score']);
        $this->assertSame('strong_match', $score['recommendation']);
        $this->assertSame('apply', $score['recommendation_action']);
        $this->assertGreaterThanOrEqual(80, $score['experience_score']);
        $this->assertSame([], $score['missing_required_skills']);
        $this->assertSame([], $score['nice_to_have_gaps']);
    }
}
