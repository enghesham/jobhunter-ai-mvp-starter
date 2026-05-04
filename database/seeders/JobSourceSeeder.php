<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Jobs\Domain\Models\JobSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JobSourceSeeder extends Seeder
{
    public function run(): void
    {
        $user = $this->demoUser();

        foreach ($this->sources() as $source) {
            JobSource::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $source['name'],
                ],
                $source + ['user_id' => $user->id],
            );
        }

        $updatedPaths = JobPath::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'auto_collect_enabled' => true,
                'scan_interval_hours' => 6,
                'next_scan_at' => now()->subMinute(),
            ]);

        $this->command?->info('Live-safe job sources seeded for '.$user->email.'.');
        $this->command?->line("Enabled auto collection for {$updatedPaths} active Job Path(s).");
        $this->command?->line('Run: php artisan jobhunter:collect-jobs --user='.$user->id.' --sync');
        $this->command?->line('Or force active paths now: php artisan jobhunter:collect-jobs --user='.$user->id.' --all-active --sync');
    }

    private function demoUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => DemoScenarioSeeder::DEMO_EMAIL],
            [
                'name' => 'Demo JobHunter User',
                'email_verified_at' => now(),
                'password' => Hash::make(DemoScenarioSeeder::DEMO_PASSWORD),
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            [
                'name' => 'Jobicy Remote Engineering RSS',
                'type' => 'rss',
                'base_url' => 'https://jobicy.com/feed/job_feed?job_categories=dev&job_types=full-time&search_region=emea',
                'company_name' => null,
                'is_active' => true,
                'meta' => [
                    'default_location' => 'Remote',
                    'notes' => 'Public RSS feed. Job Path filtering will keep relevant software roles only.',
                    'source_category' => 'remote_jobs_rss',
                ],
            ],
            [
                'name' => 'RemoteOK Remote Dev RSS',
                'type' => 'rss',
                'base_url' => 'https://remoteok.com/remote-dev-jobs.rss',
                'company_name' => null,
                'is_active' => true,
                'meta' => [
                    'default_location' => 'Remote',
                    'notes' => 'Public remote developer RSS feed. Use with Job Path filtering to avoid irrelevant roles.',
                    'source_category' => 'remote_jobs_rss',
                ],
            ],
            [
                'name' => 'RemoteYeah Jobs RSS',
                'type' => 'rss',
                'base_url' => 'https://remoteyeah.com/rss.xml',
                'company_name' => null,
                'is_active' => true,
                'meta' => [
                    'default_location' => 'Remote',
                    'notes' => 'Public RSS feed for remote jobs. The collector stores only jobs above the Job Path relevance threshold.',
                    'source_category' => 'remote_jobs_rss',
                ],
            ],
            [
                'name' => 'Greenhouse Template',
                'type' => 'greenhouse',
                'base_url' => 'https://boards.greenhouse.io/example',
                'company_name' => 'Replace With Company Name',
                'is_active' => false,
                'meta' => [
                    'board_token' => 'example',
                    'notes' => 'Template only. Replace board_token/base_url with a real Greenhouse public board, then activate.',
                    'source_category' => 'company_board_template',
                ],
            ],
            [
                'name' => 'Lever Template',
                'type' => 'lever',
                'base_url' => 'https://jobs.lever.co/example',
                'company_name' => 'Replace With Company Name',
                'is_active' => false,
                'meta' => [
                    'site' => 'example',
                    'notes' => 'Template only. Replace site/base_url with a real Lever public postings site, then activate.',
                    'source_category' => 'company_board_template',
                ],
            ],
        ];
    }
}
