<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Jobs\Domain\Models\Job;

class JobPolicy
{
    public function view(User $user, Job $job): bool
    {
        return $job->user_id === $user->id;
    }
}
