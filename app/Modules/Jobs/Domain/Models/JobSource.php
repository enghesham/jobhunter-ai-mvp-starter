<?php

namespace App\Modules\Jobs\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    protected $fillable = [
        'name', 'type', 'base_url', 'company_name', 'is_active', 'meta'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'source_id');
    }
}
