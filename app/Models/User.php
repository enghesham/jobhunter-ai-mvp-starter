<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Copilot\Domain\Models\JobCollectionRun;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Copilot\Domain\Models\UserOnboardingState;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function jobSources(): HasMany
    {
        return $this->hasMany(JobSource::class);
    }

    public function candidateProfiles(): HasMany
    {
        return $this->hasMany(CandidateProfile::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function jobMatches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(TailoredResume::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function jobPaths(): HasMany
    {
        return $this->hasMany(JobPath::class);
    }

    public function jobCollectionRuns(): HasMany
    {
        return $this->hasMany(JobCollectionRun::class);
    }

    public function onboardingState(): HasOne
    {
        return $this->hasOne(UserOnboardingState::class);
    }
}
