<?php

namespace App\Modules\Answers\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerTemplate extends Model
{
    protected $fillable = [
        'user_id', 'key', 'title', 'base_answer', 'tags'
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
