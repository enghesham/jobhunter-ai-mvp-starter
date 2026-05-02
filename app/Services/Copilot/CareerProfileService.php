<?php

namespace App\Services\Copilot;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CareerProfileService
{
    public function create(User $user, array $payload): CandidateProfile
    {
        return DB::transaction(function () use ($user, $payload): CandidateProfile {
            $profile = new CandidateProfile($this->profileAttributes($payload));
            $profile->user_id = $user->id;
            $profile->full_name = $payload['display_name'] ?? $user->name;
            $profile->source = $payload['source'] ?? 'manual';
            $profile->is_primary = false;
            $profile->save();

            $this->syncRelations($profile, $payload);

            $shouldBePrimary = (bool) ($payload['is_primary'] ?? ! $user->candidateProfiles()->where('id', '!=', $profile->id)->exists());
            if ($shouldBePrimary) {
                $this->makePrimary($profile);
            }

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    public function update(CandidateProfile $profile, array $payload): CandidateProfile
    {
        return DB::transaction(function () use ($profile, $payload): CandidateProfile {
            $profile->fill($this->profileAttributes($payload));

            if (array_key_exists('display_name', $payload)) {
                $profile->full_name = $payload['display_name'] ?: $profile->full_name;
            }

            if (array_key_exists('source', $payload)) {
                $profile->source = $payload['source'];
            }

            if (array_key_exists('is_primary', $payload) && ! $payload['is_primary']) {
                $profile->is_primary = false;
            }

            $profile->save();
            $this->syncRelations($profile, $payload);

            if ((bool) ($payload['is_primary'] ?? false)) {
                $this->makePrimary($profile);
            }

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    public function makePrimary(CandidateProfile $profile): CandidateProfile
    {
        return DB::transaction(function () use ($profile): CandidateProfile {
            CandidateProfile::query()
                ->where('user_id', $profile->user_id)
                ->whereKeyNot($profile->id)
                ->update(['is_primary' => false]);

            $profile->forceFill(['is_primary' => true])->save();

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    private function profileAttributes(array $payload): array
    {
        $attributes = [];

        $map = [
            'title' => 'headline',
            'professional_summary' => 'base_summary',
            'years_of_experience' => 'years_experience',
            'skills' => 'core_skills',
            'secondary_skills' => 'nice_to_have_skills',
            'primary_role' => 'primary_role',
            'seniority_level' => 'seniority_level',
            'tools' => 'tools',
            'industries' => 'industries',
            'education' => 'education',
            'certifications' => 'certifications',
            'languages' => 'languages',
            'preferred_workplace_type' => 'preferred_workplace_type',
            'preferred_locations' => 'preferred_locations',
            'salary_expectation' => 'salary_expectation',
            'salary_currency' => 'salary_currency',
            'raw_cv_text' => 'raw_cv_text',
            'parsed_cv_data' => 'parsed_cv_data',
            'metadata' => 'metadata',
            'linkedin_url' => 'linkedin_url',
            'github_url' => 'github_url',
            'portfolio_url' => 'portfolio_url',
        ];

        foreach ($map as $inputKey => $attributeKey) {
            if (array_key_exists($inputKey, $payload)) {
                $attributes[$attributeKey] = $payload[$inputKey];
            }
        }

        return $attributes;
    }

    private function syncRelations(CandidateProfile $profile, array $payload): void
    {
        if (array_key_exists('experiences', $payload)) {
            $profile->experiences()->delete();

            foreach ($payload['experiences'] ?? [] as $experience) {
                $profile->experiences()->create([
                    'company' => $experience['company'],
                    'title' => $experience['title'],
                    'start_date' => $experience['start_date'] ?? null,
                    'end_date' => $experience['end_date'] ?? null,
                    'description' => $experience['description'],
                    'achievements' => $experience['achievements'] ?? [],
                    'tech_stack' => Arr::get($experience, 'skills', []),
                ]);
            }
        }

        if (array_key_exists('projects', $payload)) {
            $profile->projects()->delete();

            foreach ($payload['projects'] ?? [] as $project) {
                $profile->projects()->create([
                    'name' => $project['name'],
                    'description' => $project['description'],
                    'tech_stack' => Arr::get($project, 'skills', []),
                    'url' => $project['url'] ?? null,
                ]);
            }
        }
    }
}
