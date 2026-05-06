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

    public function test_it_counts_nice_to_have_profile_skills_as_available_skills(): void
    {
        $profile = new CandidateProfile([
            'headline' => 'Senior Python Backend Engineer',
            'years_experience' => 8,
            'preferred_roles' => ['Backend Engineer'],
            'preferred_locations' => ['remote'],
            'core_skills' => ['Python'],
            'nice_to_have_skills' => ['Django', 'PostgreSQL', 'Vue.js', 'AWS', 'Git'],
        ]);

        $job = new Job([
            'title' => 'Senior Software Engineer Python Django PostgreSQL and Vue.js',
            'location' => 'Remote',
            'remote_type' => 'remote',
        ]);

        $job->setRelation('analysis', new JobAnalysis([
            'required_skills' => ['Python', 'Django', 'PostgreSQL', 'Vue.js'],
            'preferred_skills' => ['AWS', 'Git'],
            'years_experience_min' => 6,
            'seniority' => 'senior',
            'role_type' => 'full_stack',
            'domain_tags' => ['software'],
        ]));

        $score = (new JobMatchScoringService())->score($profile, $job);

        $this->assertSame(100, $score['skill_score']);
        $this->assertSame([], $score['missing_required_skills']);
        $this->assertSame([], $score['nice_to_have_gaps']);
        $this->assertContains('django', $score['strength_areas']);
        $this->assertContains('postgresql', $score['strength_areas']);
    }
}
