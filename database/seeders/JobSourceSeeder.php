<?php

namespace Database\Seeders;

use App\Modules\Jobs\Domain\Models\JobSource;
use Illuminate\Database\Seeder;

class JobSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Greenhouse Example',
                'type' => 'greenhouse',
                'base_url' => 'https://boards.greenhouse.io/example',
                'company_name' => 'Example Greenhouse Company',
                'is_active' => false,
                'meta' => ['board_token' => 'example'],
            ],
            [
                'name' => 'Lever Example',
                'type' => 'lever',
                'base_url' => 'https://jobs.lever.co/example',
                'company_name' => 'Example Lever Company',
                'is_active' => false,
                'meta' => ['site' => 'example'],
            ],
            [
                'name' => 'Custom Company Placeholder',
                'type' => 'custom',
                'base_url' => 'https://careers.example.com',
                'company_name' => 'Custom Company',
                'is_active' => false,
                'meta' => ['notes' => 'Add a custom fetcher before enabling this source.'],
            ],
        ];

        foreach ($sources as $source) {
            JobSource::updateOrCreate(
                ['name' => $source['name'], 'type' => $source['type']],
                $source,
            );
        }
    }
}
