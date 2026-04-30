<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemoScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_scenario_seeds_a_login_ready_end_to_end_dataset(): void
    {
        $this->seed(DemoScenarioSeeder::class);

        $user = User::query()->where('email', DemoScenarioSeeder::DEMO_EMAIL)->firstOrFail();

        $this->postJson('/api/auth/login', [
            'email' => DemoScenarioSeeder::DEMO_EMAIL,
            'password' => DemoScenarioSeeder::DEMO_PASSWORD,
        ])->assertOk()
            ->assertJsonPath('data.user.email', DemoScenarioSeeder::DEMO_EMAIL);

        Sanctum::actingAs($user);

        $this->assertSame(4, Job::query()->where('user_id', $user->id)->count());
        $this->assertSame(4, JobAnalysis::query()->whereHas('job', fn ($query) => $query->where('user_id', $user->id))->count());
        $this->assertSame(4, JobMatch::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, TailoredResume::query()->where('user_id', $user->id)->count());
        $this->assertSame(3, Application::query()->where('user_id', $user->id)->count());
        $this->assertSame(3, ApplicationMaterial::query()->where('user_id', $user->id)->count());

        $resume = TailoredResume::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($resume->html_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $resume->html_path));

        $this->getJson('/api/jobhunter/jobs')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 4);

        $this->getJson('/api/jobhunter/matches')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 4);

        $this->getJson('/api/jobhunter/resumes')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/jobhunter/applications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3);

        $this->getJson('/api/jobhunter/ai-quality')
            ->assertOk()
            ->assertJsonPath('data.summary.total_runs', 13);
    }
}
