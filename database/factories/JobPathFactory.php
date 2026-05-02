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
            'title' => 'Backend Laravel Remote',
            'goal' => 'Find senior Laravel backend roles with remote flexibility.',
            'target_roles' => ['Senior Backend Engineer', 'Laravel Developer'],
            'target_fields' => ['Backend Development', 'SaaS'],
            'preferred_locations' => ['Remote', 'UAE'],
            'work_modes' => ['remote'],
            'employment_types' => ['full-time'],
            'must_have_keywords' => ['Laravel', 'PHP', 'PostgreSQL'],
            'nice_to_have_keywords' => ['Redis', 'Docker', 'AWS'],
            'avoid_keywords' => ['translation', 'sales'],
            'min_fit_score' => 60,
            'min_apply_score' => 80,
            'is_active' => true,
            'auto_collect_enabled' => false,
            'scan_interval_hours' => null,
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
