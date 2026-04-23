<?php

namespace Database\Seeders;

use App\Modules\Candidate\Domain\Models\CandidateProfile;
use Illuminate\Database\Seeder;

class CandidateProfileSeeder extends Seeder
{
    public function run(): void
    {
        CandidateProfile::updateOrCreate(
            ['full_name' => 'JobHunter Candidate'],
            [
                'headline' => 'Senior Backend / Laravel / Python Developer',
                'base_summary' => 'Senior backend developer with 10+ years building production APIs, Laravel systems, Python services, queues, databases, cloud infrastructure, and CI/CD pipelines.',
                'years_experience' => 10,
                'preferred_roles' => [
                    'Senior Backend Engineer',
                    'Senior Laravel Developer',
                    'Python Developer',
                    'Backend API Engineer',
                    'Full Stack Engineer (Backend-Focused)',
                ],
                'preferred_locations' => ['remote', 'hybrid', 'israel', 'europe', 'mena'],
                'preferred_job_types' => ['full-time', 'contract', 'remote'],
                'core_skills' => [
                    'PHP',
                    'Laravel',
                    'Python',
                    'FastAPI',
                    'REST APIs',
                    'PostgreSQL',
                    'MySQL',
                    'Redis',
                    'OpenSearch',
                    'Docker',
                    'AWS',
                    'CI/CD',
                    'Queues',
                    'System Design',
                    'Clean Architecture',
                ],
                'nice_to_have_skills' => [
                    'Vue.js',
                    'JavaScript',
                    'Firebase',
                    'PHPUnit',
                    'Pest',
                    'AI integrations',
                ],
            ],
        );
    }
}
