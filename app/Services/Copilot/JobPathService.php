<?php

namespace App\Services\Copilot;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobPathService
{
    public function create(User $user, array $payload): JobPath
    {
        return DB::transaction(function () use ($user, $payload): JobPath {
            $this->assertCareerProfileBelongsToUser($user, $payload['career_profile_id'] ?? null);

            $path = new JobPath($this->attributes($payload));
            $path->user_id = $user->id;
            $this->applyScanSchedule($path);
            $path->save();

            return $path->fresh(['careerProfile']);
        });
    }

    public function update(JobPath $path, array $payload): JobPath
    {
        return DB::transaction(function () use ($path, $payload): JobPath {
            if (array_key_exists('career_profile_id', $payload)) {
                $this->assertCareerProfileBelongsToUser($path->user, $payload['career_profile_id']);
            }

            $path->fill($this->attributes($payload));
            $this->applyScanSchedule($path);
            $path->save();

            return $path->fresh(['careerProfile']);
        });
    }

    private function attributes(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'career_profile_id',
            'title',
            'goal',
            'target_roles',
            'target_fields',
            'preferred_locations',
            'work_modes',
            'employment_types',
            'must_have_keywords',
            'nice_to_have_keywords',
            'avoid_keywords',
            'min_fit_score',
            'min_apply_score',
            'is_active',
            'auto_collect_enabled',
            'scan_interval_hours',
            'metadata',
        ]));
    }

    private function applyScanSchedule(JobPath $path): void
    {
        if (! $path->is_active || ! $path->auto_collect_enabled) {
            $path->next_scan_at = null;

            return;
        }

        $path->scan_interval_hours = $path->scan_interval_hours ?: (int) config('jobhunter.scan_hours', 6);

        if ($path->next_scan_at === null || $path->isDirty(['is_active', 'auto_collect_enabled', 'scan_interval_hours'])) {
            $path->next_scan_at = $path->calculateNextScanAt();
        }
    }

    private function assertCareerProfileBelongsToUser(User $user, mixed $careerProfileId): void
    {
        if ($careerProfileId === null) {
            return;
        }

        $belongsToUser = CandidateProfile::query()
            ->whereKey($careerProfileId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $belongsToUser) {
            throw ValidationException::withMessages([
                'career_profile_id' => 'The selected career profile is invalid.',
            ]);
        }
    }
}
