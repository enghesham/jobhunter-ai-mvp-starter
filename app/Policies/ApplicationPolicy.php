<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Applications\Domain\Models\Application;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function update(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }
}
