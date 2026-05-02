<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Copilot\Domain\Models\JobPath;

class JobPathPolicy
{
    public function view(User $user, JobPath $jobPath): bool
    {
        return $jobPath->user_id === $user->id;
    }

    public function update(User $user, JobPath $jobPath): bool
    {
        return $jobPath->user_id === $user->id;
    }

    public function delete(User $user, JobPath $jobPath): bool
    {
        return $jobPath->user_id === $user->id;
    }
}
