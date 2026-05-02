<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    protected $model = CandidateProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => $this->faker->name(),
            'headline' => 'Backend Engineer',
            'base_summary' => $this->faker->paragraph(),
            'primary_role' => 'Backend Developer',
            'seniority_level' => 'mid',
            'years_experience' => $this->faker->numberBetween(1, 12),
            'preferred_roles' => ['Backend Developer'],
            'preferred_locations' => ['Remote'],
            'preferred_job_types' => ['full-time'],
            'preferred_workplace_type' => 'remote',
            'core_skills' => ['PHP', 'Laravel', 'PostgreSQL'],
            'nice_to_have_skills' => ['Vue.js', 'Docker'],
            'tools' => ['Git', 'Docker'],
            'industries' => ['SaaS'],
            'education' => [],
            'certifications' => [],
            'languages' => ['English'],
            'salary_expectation' => null,
            'salary_currency' => null,
            'source' => 'manual',
            'is_primary' => false,
            'metadata' => [],
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
        ]);
    }
}
