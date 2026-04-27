<?php

namespace App\Services\Candidate;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CandidateProfilePersistenceService
{
    public function create(array $payload): CandidateProfile
    {
        return DB::transaction(function () use ($payload): CandidateProfile {
            $profile = CandidateProfile::create($this->extractProfileAttributes($payload));
            $this->syncRelations($profile, $payload);

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    public function update(CandidateProfile $profile, array $payload): CandidateProfile
    {
        return DB::transaction(function () use ($profile, $payload): CandidateProfile {
            $profile->update($this->extractProfileAttributes($payload));
            $this->syncRelations($profile, $payload);

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    public function import(int $userId, array $payload): CandidateProfile
    {
        return DB::transaction(function () use ($userId, $payload): CandidateProfile {
            $profile = CandidateProfile::updateOrCreate(
                ['full_name' => $payload['full_name'], 'user_id' => $userId],
                $this->extractProfileAttributes($payload + ['user_id' => $userId])
            );

            $this->syncRelations($profile, $payload);

            return $profile->fresh(['experiences', 'projects']);
        });
    }

    private function extractProfileAttributes(array $payload): array
    {
        return Arr::except($payload, ['experiences', 'projects']);
    }

    private function syncRelations(CandidateProfile $profile, array $payload): void
    {
        $profile->experiences()->delete();
        foreach ($payload['experiences'] ?? [] as $experience) {
            $profile->experiences()->create([
                'company' => $experience['company'],
                'title' => $experience['title'],
                'start_date' => $experience['start_date'] ?? null,
                'end_date' => $experience['end_date'] ?? null,
                'description' => $experience['description'],
            ]);
        }

        $profile->projects()->delete();
        foreach ($payload['projects'] ?? [] as $project) {
            $profile->projects()->create([
                'name' => $project['name'],
                'description' => $project['description'],
                'tech_stack' => $project['skills'] ?? [],
            ]);
        }
    }
}
