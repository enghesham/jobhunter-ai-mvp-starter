<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Answers\Domain\Models\AnswerTemplate;

class AnswerTemplatePolicy
{
    public function view(User $user, AnswerTemplate $answerTemplate): bool
    {
        return $answerTemplate->user_id === $user->id;
    }

    public function update(User $user, AnswerTemplate $answerTemplate): bool
    {
        return $answerTemplate->user_id === $user->id;
    }

    public function delete(User $user, AnswerTemplate $answerTemplate): bool
    {
        return $answerTemplate->user_id === $user->id;
    }
}
