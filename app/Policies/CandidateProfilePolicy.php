<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Candidate\Domain\Models\CandidateProfile;

class CandidateProfilePolicy
{
    public function view(User $user, CandidateProfile $candidateProfile): bool
    {
        return $candidateProfile->user_id === $user->id;
    }

    public function update(User $user, CandidateProfile $candidateProfile): bool
    {
        return $candidateProfile->user_id === $user->id;
    }

    public function delete(User $user, CandidateProfile $candidateProfile): bool
    {
        return $candidateProfile->user_id === $user->id;
    }
}
