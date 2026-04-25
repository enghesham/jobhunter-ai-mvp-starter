<?php

namespace App\Modules\Jobs\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'base_url', 'company_name', 'is_active', 'meta'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->base_url;
    }

    public function getConfigAttribute(): ?array
    {
        return $this->meta;
    }
}
