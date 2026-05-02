<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPath>
 */
class JobPathFactory extends Factory
{
    protected $model = JobPath::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'career_profile_id' => null,
            'name' => 'Backend Laravel Remote',
            'description' => 'Find senior Laravel backend roles with remote flexibility.',
            'target_roles' => ['Senior Backend Engineer', 'Laravel Developer'],
            'target_domains' => ['Backend Development', 'SaaS'],
            'include_keywords' => ['Laravel', 'PHP', 'API'],
            'exclude_keywords' => ['translation', 'sales'],
            'required_skills' => ['Laravel', 'PHP', 'PostgreSQL'],
            'optional_skills' => ['Redis', 'Docker', 'AWS'],
            'seniority_levels' => ['senior'],
            'preferred_locations' => ['Remote', 'UAE'],
            'preferred_countries' => ['UAE'],
            'preferred_job_types' => ['full-time'],
            'remote_preference' => 'remote',
            'min_relevance_score' => 60,
            'min_match_score' => 75,
            'salary_min' => null,
            'salary_currency' => null,
            'is_active' => true,
            'auto_collect_enabled' => false,
            'notifications_enabled' => false,
            'scan_interval_hours' => null,
            'last_scanned_at' => null,
            'next_scan_at' => null,
            'metadata' => [],
        ];
    }

    public function forCareerProfile(CandidateProfile $profile): static
    {
        return $this->state(fn (): array => [
            'user_id' => $profile->user_id,
            'career_profile_id' => $profile->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function autoCollect(int $hours = 6): static
    {
        return $this->state(fn (): array => [
            'auto_collect_enabled' => true,
            'scan_interval_hours' => $hours,
            'next_scan_at' => now()->addHours($hours),
        ]);
    }
}
