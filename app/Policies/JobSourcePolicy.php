<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Jobs\Domain\Models\JobSource;

class JobSourcePolicy
{
    public function view(User $user, JobSource $jobSource): bool
    {
        return $jobSource->user_id === $user->id;
    }

    public function update(User $user, JobSource $jobSource): bool
    {
        return $jobSource->user_id === $user->id;
    }

    public function delete(User $user, JobSource $jobSource): bool
    {
        return $jobSource->user_id === $user->id;
    }
}
