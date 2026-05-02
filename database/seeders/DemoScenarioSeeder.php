<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Modules\Applications\Domain\Models\Application;
use App\Modules\Applications\Domain\Models\ApplicationEvent;
use App\Modules\Applications\Domain\Models\ApplicationMaterial;
use App\Modules\Candidate\Domain\Models\CandidateExperience;
use App\Modules\Candidate\Domain\Models\CandidateProfile;
use App\Modules\Candidate\Domain\Models\CandidateProject;
use App\Modules\Copilot\Domain\Models\JobPath;
use App\Modules\Copilot\Domain\Models\UserOnboardingState;
use App\Modules\Jobs\Domain\Models\Job;
use App\Modules\Jobs\Domain\Models\JobAnalysis;
use App\Modules\Jobs\Domain\Models\JobSource;
use App\Modules\Matching\Domain\Models\JobMatch;
use App\Modules\Resume\Domain\Models\TailoredResume;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoScenarioSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@jobhunter.test';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $user = $this->seedUser();
        $profile = $this->seedCandidateProfile($user);
        $jobPath = $this->seedJobPath($user, $profile);
        $this->seedOnboardingState($user, $profile, $jobPath);
        $source = $this->seedJobSource($user);
        $jobs = $this->seedJobs($user, $source);

        $this->seedAnalyses($jobs);

        $this->seedMatches($user, $profile, $jobs);
        $resumes = $this->seedResumes($user, $profile, $jobs);

        $this->seedAnswerTemplates($user);
        $this->seedApplications($user, $profile, $jobs, $resumes);

        $this->command?->info('Demo scenario ready.');
        $this->command?->line('Login: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD);
    }

    private function seedUser(): User
    {
        return User::updateOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Demo JobHunter User',
                'email_verified_at' => now(),
                'password' => Hash::make(self::DEMO_PASSWORD),
            ],
        );
    }

    private function seedCandidateProfile(User $user): CandidateProfile
    {
        $profile = CandidateProfile::updateOrCreate(
            ['user_id' => $user->id, 'full_name' => 'Hesham Demo Candidate'],
            [
                'headline' => 'Senior Laravel Backend Engineer | APIs | Queues | Search',
                'base_summary' => 'Senior backend engineer with 10+ years building Laravel APIs, queue-driven systems, PostgreSQL data models, Redis workflows, OpenSearch search platforms, and pragmatic AI integrations.',
                'primary_role' => 'Backend Developer',
                'seniority_level' => 'senior',
                'years_experience' => 10,
                'preferred_roles' => ['Senior Backend Engineer', 'Senior Laravel Developer', 'Backend Platform Engineer'],
                'preferred_locations' => ['Remote', 'UAE', 'Saudi Arabia', 'Europe'],
                'preferred_job_types' => ['remote', 'hybrid', 'full-time'],
                'preferred_workplace_type' => 'remote',
                'core_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis', 'OpenSearch', 'Docker', 'AWS', 'Queues', 'Testing', 'Clean Architecture'],
                'nice_to_have_skills' => ['Vue.js', 'Kubernetes', 'Terraform', 'LLM Integrations', 'CI/CD'],
                'tools' => ['Git', 'Docker', 'Postman', 'Redis CLI', 'OpenSearch Dashboards'],
                'industries' => ['SaaS', 'Recruitment', 'Fintech'],
                'education' => [],
                'certifications' => [],
                'languages' => ['Arabic', 'English'],
                'salary_currency' => 'USD',
                'source' => 'manual',
                'is_primary' => true,
                'metadata' => ['demo_ready' => true, 'product_label' => 'My Career Profile'],
                'linkedin_url' => 'https://linkedin.example.com/in/demo-candidate',
                'github_url' => 'https://github.example.com/demo-candidate',
                'portfolio_url' => 'https://portfolio.example.com/demo-candidate',
            ],
        );

        $profile->experiences()->delete();
        $profile->projects()->delete();

        CandidateExperience::create([
            'profile_id' => $profile->id,
            'company' => 'Reach Digital Hub',
            'title' => 'Senior PHP Developer',
            'start_date' => '2023-07-01',
            'end_date' => null,
            'description' => 'Built scalable Laravel APIs, AI-driven scoring workflows, OpenSearch-backed search, queue orchestration, and cloud integrations for production products.',
            'achievements' => [
                'Designed queue-based processing for expensive backend workflows.',
                'Improved search relevance and indexing reliability using OpenSearch.',
                'Integrated AI provider fallback paths with safe logging and metadata tracking.',
            ],
            'tech_stack' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'OpenSearch', 'Docker', 'AWS', 'Queues'],
        ]);

        CandidateExperience::create([
            'profile_id' => $profile->id,
            'company' => 'Voybee Labs',
            'title' => 'Backend Engineer',
            'start_date' => '2020-01-01',
            'end_date' => '2023-06-30',
            'description' => 'Delivered REST APIs, database-heavy features, integrations, and operational tooling for marketplace and travel products.',
            'achievements' => [
                'Reduced API response time by optimizing database access patterns.',
                'Built maintainable service layers and integration boundaries.',
                'Added automated tests around high-risk backend workflows.',
            ],
            'tech_stack' => ['PHP', 'Laravel', 'MySQL', 'Redis', 'REST APIs', 'PHPUnit', 'Docker'],
        ]);

        CandidateProject::create([
            'profile_id' => $profile->id,
            'name' => 'AI Job Platform',
            'description' => 'Job search workflow with job ingestion, candidate matching, tailored resume generation, application tracking, and AI quality dashboards.',
            'tech_stack' => ['Laravel', 'Vue.js', 'OpenSearch', 'Redis', 'PostgreSQL', 'Queues', 'AI Providers'],
            'url' => 'https://demo.example.com/ai-job-platform',
        ]);

        CandidateProject::create([
            'profile_id' => $profile->id,
            'name' => 'Recruitment Search Engine',
            'description' => 'Candidate and job search system with relevance tuning, filters, indexing, and recruiter workflow automation.',
            'tech_stack' => ['Laravel', 'OpenSearch', 'PostgreSQL', 'Redis', 'Docker'],
            'url' => 'https://demo.example.com/recruitment-search',
        ]);

        return $profile->fresh(['experiences', 'projects']);
    }

    private function seedJobPath(User $user, CandidateProfile $profile): JobPath
    {
        return JobPath::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Senior Laravel Backend Remote'],
            [
                'career_profile_id' => $profile->id,
                'description' => 'Find strong remote backend roles that match Laravel, APIs, queues, databases, search, and pragmatic AI integration experience.',
                'target_roles' => ['Senior Backend Engineer', 'Senior Laravel Developer', 'Backend Platform Engineer'],
                'target_domains' => ['Backend Development', 'SaaS', 'Recruitment Tech', 'Fintech'],
                'include_keywords' => ['Laravel', 'PHP', 'REST APIs', 'PostgreSQL', 'Redis', 'Queues'],
                'exclude_keywords' => ['translation', 'sales', 'cold calling'],
                'required_skills' => ['Laravel', 'PHP', 'REST APIs', 'PostgreSQL'],
                'optional_skills' => ['Redis', 'OpenSearch', 'Docker', 'AWS', 'Queues', 'AI Integrations'],
                'seniority_levels' => ['senior', 'lead'],
                'preferred_locations' => ['Remote', 'UAE', 'Saudi Arabia', 'Europe'],
                'preferred_countries' => ['UAE', 'Saudi Arabia'],
                'preferred_job_types' => ['full-time', 'contract'],
                'remote_preference' => 'remote',
                'min_relevance_score' => 65,
                'min_match_score' => 82,
                'salary_min' => null,
                'salary_currency' => 'USD',
                'is_active' => true,
                'auto_collect_enabled' => false,
                'notifications_enabled' => true,
                'scan_interval_hours' => null,
                'last_scanned_at' => null,
                'next_scan_at' => null,
                'metadata' => ['demo_ready' => true, 'product_label' => 'Job Path'],
            ],
        );
    }

    private function seedOnboardingState(User $user, CandidateProfile $profile, JobPath $jobPath): UserOnboardingState
    {
        return UserOnboardingState::updateOrCreate(
            ['user_id' => $user->id],
            [
                'current_step' => 'done',
                'completed_at' => now()->subDay(),
                'metadata' => [
                    'career_profile_id' => $profile->id,
                    'selected_job_path_ids' => [$jobPath->id],
                    'completed_by' => 'demo_seed',
                ],
            ],
        );
    }

    private function seedJobSource(User $user): JobSource
    {
        return JobSource::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Demo Curated Backend Roles'],
            [
                'type' => 'custom',
                'base_url' => 'https://careers.demo.test/backend',
                'company_name' => 'Curated Demo Companies',
                'is_active' => true,
                'meta' => [
                    'mode' => 'demo',
                    'notes' => 'Seeded source for stabilization and demo readiness.',
                ],
            ],
        );
    }

    /**
     * @return array<string, Job>
     */
    private function seedJobs(User $user, JobSource $source): array
    {
        $definitions = [
            'platform_engineer' => [
                'external_id' => 'demo-platform-engineer',
                'company_name' => 'Nimbus Cloud Systems',
                'title' => 'Senior Laravel Platform Engineer',
                'location' => 'Remote - EMEA',
                'is_remote' => true,
                'remote_type' => 'remote',
                'employment_type' => 'full-time',
                'salary_text' => '$85,000 - $115,000',
                'status' => 'resume_generated',
                'posted_at' => now()->subDays(2),
                'apply_url' => 'https://careers.demo.test/jobs/platform-engineer',
                'description' => 'We need a senior Laravel backend engineer to build REST APIs, queue workflows, Redis caching, PostgreSQL data models, Dockerized services, and OpenSearch-powered search. Required: PHP, Laravel, REST APIs, PostgreSQL, Redis, Docker. Nice to have: AWS, OpenSearch, Vue.js, AI integrations. Remote across EMEA time zones.',
            ],
            'api_engineer' => [
                'external_id' => 'demo-api-engineer',
                'company_name' => 'LedgerFlow',
                'title' => 'Backend API Engineer',
                'location' => 'Dubai, UAE - Hybrid',
                'is_remote' => false,
                'remote_type' => 'hybrid',
                'employment_type' => 'full-time',
                'salary_text' => 'AED 28,000 - AED 35,000 monthly',
                'status' => 'matched',
                'posted_at' => now()->subDays(4),
                'apply_url' => 'https://careers.demo.test/jobs/api-engineer',
                'description' => 'Backend API role focused on Laravel, payment integrations, PostgreSQL, queue workers, Redis, automated testing, and clean API contracts. Required: PHP, Laravel, REST APIs, PostgreSQL, testing. Preferred: AWS, Docker, fintech domain knowledge.',
            ],
            'fullstack_engineer' => [
                'external_id' => 'demo-fullstack-engineer',
                'company_name' => 'TalentBridge AI',
                'title' => 'Full Stack Product Engineer',
                'location' => 'Remote',
                'is_remote' => true,
                'remote_type' => 'remote',
                'employment_type' => 'contract',
                'salary_text' => '$60 - $80 hourly',
                'status' => 'analyzed',
                'posted_at' => now()->subDays(6),
                'apply_url' => 'https://careers.demo.test/jobs/fullstack-product-engineer',
                'description' => 'Product engineer role combining Laravel APIs, Vue.js UI work, database modeling, AI-assisted features, CI/CD, and product discovery. Must have Laravel, Vue.js, PostgreSQL, REST APIs. Bonus: OpenSearch, LLM integrations, recruitment domain.',
            ],
            'devops_heavy' => [
                'external_id' => 'demo-devops-heavy',
                'company_name' => 'ScaleOps Europe',
                'title' => 'Senior Cloud Infrastructure Engineer',
                'location' => 'Berlin, Germany',
                'is_remote' => false,
                'remote_type' => 'onsite',
                'employment_type' => 'full-time',
                'salary_text' => 'EUR 90,000 - EUR 120,000',
                'status' => 'analyzed',
                'posted_at' => now()->subDays(8),
                'apply_url' => 'https://careers.demo.test/jobs/cloud-infrastructure-engineer',
                'description' => 'Infrastructure-heavy role requiring Kubernetes, Terraform, AWS networking, incident response, observability, and platform reliability. Preferred backend experience with PHP or Laravel. This is primarily a cloud operations role.',
            ],
        ];

        $jobs = [];

        foreach ($definitions as $key => $definition) {
            $fingerprint = $this->jobFingerprint($definition['company_name'], $definition['title'], $definition['location']);

            $jobs[$key] = Job::updateOrCreate(
                ['user_id' => $user->id, 'job_fingerprint' => $fingerprint],
                [
                    'external_id' => $definition['external_id'],
                    'source_id' => $source->id,
                    'company_name' => $definition['company_name'],
                    'title' => $definition['title'],
                    'location' => $definition['location'],
                    'is_remote' => $definition['is_remote'],
                    'remote_type' => $definition['remote_type'],
                    'employment_type' => $definition['employment_type'],
                    'description_raw' => $definition['description'],
                    'description_clean' => $definition['description'],
                    'apply_url' => $definition['apply_url'],
                    'raw_payload' => [
                        'seeded' => true,
                        'source' => 'DemoScenarioSeeder',
                        'scenario_key' => $key,
                    ],
                    'salary_text' => $definition['salary_text'],
                    'posted_at' => $definition['posted_at'],
                    'hash' => sha1($user->id.'|'.$source->id.'|'.$definition['external_id']),
                    'job_fingerprint' => $fingerprint,
                    'source_hash' => $this->sourceHash($definition['company_name'], $definition['title'], $definition['location'], $definition['apply_url']),
                    'status' => $definition['status'],
                ],
            );
        }

        return $jobs;
    }

    /**
     * @param array<string, Job> $jobs
     */
    private function seedAnalyses(array $jobs): void
    {
        $payloads = [
            'platform_engineer' => [
                'required_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis', 'Docker'],
                'preferred_skills' => ['AWS', 'OpenSearch', 'Vue.js', 'AI Integrations'],
                'must_have_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Redis'],
                'nice_to_have_skills' => ['AWS', 'OpenSearch', 'Vue.js', 'AI Integrations'],
                'seniority' => 'senior',
                'role_type' => 'backend_platform',
                'years_experience_min' => 7,
                'years_experience_max' => 12,
                'workplace_type' => 'remote',
                'salary_text' => '$85,000 - $115,000',
                'salary_min' => 85000,
                'salary_max' => 115000,
                'salary_currency' => 'USD',
                'location_hint' => 'Remote - EMEA',
                'timezone_hint' => 'EMEA',
                'domain_tags' => ['cloud', 'platform', 'search'],
                'tech_stack' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker', 'OpenSearch', 'AWS'],
                'skill_categories' => [
                    'backend' => ['PHP', 'Laravel', 'REST APIs'],
                    'database' => ['PostgreSQL'],
                    'devops' => ['Docker'],
                    'cloud' => ['AWS'],
                    'search' => ['OpenSearch'],
                ],
                'responsibilities' => ['Build Laravel APIs', 'Design queue workflows', 'Improve search relevance', 'Operate backend services'],
                'company_context' => 'Cloud platform company hiring for backend scalability and search-heavy workflows.',
                'ai_summary' => 'Strong backend Laravel platform role with queue, cache, database, and search requirements.',
                'confidence_score' => 92,
                'ai_provider' => 'openrouter',
                'ai_model' => 'openrouter/auto',
                'ai_confidence_score' => 91,
                'ai_duration_ms' => 1850,
                'fallback_used' => false,
            ],
            'api_engineer' => [
                'required_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL', 'Testing'],
                'preferred_skills' => ['AWS', 'Docker', 'Fintech'],
                'must_have_skills' => ['PHP', 'Laravel', 'REST APIs', 'PostgreSQL'],
                'nice_to_have_skills' => ['AWS', 'Docker', 'Fintech'],
                'seniority' => 'mid_senior',
                'role_type' => 'backend_api',
                'years_experience_min' => 5,
                'years_experience_max' => 9,
                'workplace_type' => 'hybrid',
                'salary_text' => 'AED 28,000 - AED 35,000 monthly',
                'salary_min' => 28000,
                'salary_max' => 35000,
                'salary_currency' => 'AED',
                'location_hint' => 'Dubai, UAE',
                'timezone_hint' => 'GST',
                'domain_tags' => ['fintech', 'payments', 'apis'],
                'tech_stack' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Queues', 'Testing'],
                'skill_categories' => [
                    'backend' => ['PHP', 'Laravel', 'REST APIs'],
                    'database' => ['PostgreSQL'],
                    'soft_skills' => ['API ownership', 'Product delivery'],
                ],
                'responsibilities' => ['Build API contracts', 'Integrate payments', 'Maintain queue workers', 'Write automated tests'],
                'company_context' => 'Fintech product company emphasizing reliable API delivery.',
                'ai_summary' => 'Backend API role with strong Laravel and testing requirements.',
                'confidence_score' => 86,
                'ai_provider' => null,
                'ai_model' => null,
                'ai_confidence_score' => null,
                'ai_duration_ms' => 38,
                'fallback_used' => true,
            ],
            'fullstack_engineer' => [
                'required_skills' => ['Laravel', 'Vue.js', 'PostgreSQL', 'REST APIs'],
                'preferred_skills' => ['OpenSearch', 'LLM Integrations', 'Recruitment'],
                'must_have_skills' => ['Laravel', 'Vue.js', 'REST APIs'],
                'nice_to_have_skills' => ['OpenSearch', 'LLM Integrations'],
                'seniority' => 'senior',
                'role_type' => 'fullstack_product',
                'years_experience_min' => 6,
                'years_experience_max' => 10,
                'workplace_type' => 'remote',
                'salary_text' => '$60 - $80 hourly',
                'salary_min' => 60,
                'salary_max' => 80,
                'salary_currency' => 'USD_HOURLY',
                'location_hint' => 'Remote',
                'timezone_hint' => 'Flexible',
                'domain_tags' => ['product', 'ai', 'recruitment'],
                'tech_stack' => ['Laravel', 'Vue.js', 'PostgreSQL', 'CI/CD', 'LLM Integrations'],
                'skill_categories' => [
                    'backend' => ['Laravel', 'REST APIs'],
                    'frontend' => ['Vue.js'],
                    'database' => ['PostgreSQL'],
                    'ai' => ['LLM Integrations'],
                ],
                'responsibilities' => ['Ship product features', 'Build Vue screens', 'Design APIs', 'Integrate AI-assisted workflows'],
                'company_context' => 'AI recruitment product team looking for full-stack product ownership.',
                'ai_summary' => 'Good product role but requires more frontend focus than the candidate profile emphasizes.',
                'confidence_score' => 82,
                'ai_provider' => 'gemini',
                'ai_model' => 'gemini-2.5-flash',
                'ai_confidence_score' => 82,
                'ai_duration_ms' => 2410,
                'fallback_used' => false,
            ],
            'devops_heavy' => [
                'required_skills' => ['Kubernetes', 'Terraform', 'AWS Networking', 'Observability', 'Incident Response'],
                'preferred_skills' => ['PHP', 'Laravel'],
                'must_have_skills' => ['Kubernetes', 'Terraform', 'AWS Networking'],
                'nice_to_have_skills' => ['PHP', 'Laravel'],
                'seniority' => 'senior',
                'role_type' => 'cloud_infrastructure',
                'years_experience_min' => 8,
                'years_experience_max' => 12,
                'workplace_type' => 'onsite',
                'salary_text' => 'EUR 90,000 - EUR 120,000',
                'salary_min' => 90000,
                'salary_max' => 120000,
                'salary_currency' => 'EUR',
                'location_hint' => 'Berlin, Germany',
                'timezone_hint' => 'CET',
                'domain_tags' => ['infrastructure', 'cloud', 'operations'],
                'tech_stack' => ['Kubernetes', 'Terraform', 'AWS', 'Observability'],
                'skill_categories' => [
                    'devops' => ['Kubernetes', 'Terraform', 'Observability'],
                    'cloud' => ['AWS Networking'],
                    'backend' => ['PHP', 'Laravel'],
                ],
                'responsibilities' => ['Own infrastructure reliability', 'Manage Kubernetes clusters', 'Drive incident response'],
                'company_context' => 'Infrastructure organization with strong DevOps ownership needs.',
                'ai_summary' => 'Infrastructure-heavy role with weaker alignment to Laravel backend strengths.',
                'confidence_score' => 78,
                'ai_provider' => 'groq',
                'ai_model' => 'llama-3.3-70b-versatile',
                'ai_confidence_score' => 78,
                'ai_duration_ms' => 1680,
                'fallback_used' => false,
            ],
        ];

        foreach ($payloads as $key => $payload) {
            JobAnalysis::updateOrCreate(
                ['job_id' => $jobs[$key]->id],
                $payload + [
                    'prompt_version' => 'demo-v1',
                    'input_hash' => hash('sha256', $key.'|analysis|demo-v1'),
                    'analyzed_at' => now()->subHours(12),
                    'ai_generated_at' => $payload['ai_provider'] ? now()->subHours(12) : null,
                ],
            );
        }
    }

    /**
     * @param array<string, Job> $jobs
     * @return array<string, JobMatch>
     */
    private function seedMatches(User $user, CandidateProfile $profile, array $jobs): array
    {
        $definitions = [
            'platform_engineer' => [
                'overall_score' => 91,
                'title_score' => 94,
                'skill_score' => 93,
                'experience_score' => 90,
                'seniority_score' => 92,
                'location_score' => 88,
                'backend_focus_score' => 95,
                'domain_score' => 86,
                'recommendation' => 'Excellent fit. Prioritize this application.',
                'recommendation_action' => 'apply',
                'notes' => 'Strong alignment across Laravel, APIs, queues, Redis, PostgreSQL, Docker, and search.',
                'why_matched' => 'The candidate has direct production experience with Laravel APIs, queue workflows, Redis, PostgreSQL, Docker, and OpenSearch, which are central requirements for this role.',
                'missing_skills' => ['Advanced AWS platform ownership'],
                'missing_required_skills' => [],
                'nice_to_have_gaps' => ['Deeper AWS architecture examples could strengthen the pitch.'],
                'strength_areas' => ['Laravel API architecture', 'Queue-driven workflows', 'OpenSearch integrations', 'Backend scalability'],
                'risk_flags' => ['Cloud platform depth should be framed carefully.'],
                'resume_focus_points' => ['Highlight queue architecture', 'Show OpenSearch relevance work', 'Mention API ownership and tests'],
                'ai_recommendation_summary' => 'Apply with a tailored resume focused on Laravel platform work, queue systems, and search-heavy backend delivery.',
                'ai_provider' => 'openrouter',
                'ai_model' => 'openrouter/auto',
                'ai_confidence_score' => 88,
                'ai_duration_ms' => 1540,
                'fallback_used' => false,
            ],
            'api_engineer' => [
                'overall_score' => 84,
                'title_score' => 82,
                'skill_score' => 88,
                'experience_score' => 86,
                'seniority_score' => 82,
                'location_score' => 76,
                'backend_focus_score' => 90,
                'domain_score' => 72,
                'recommendation' => 'Good fit. Apply if hybrid Dubai is acceptable.',
                'recommendation_action' => 'apply',
                'notes' => 'Strong backend API alignment with some location and fintech-domain questions.',
                'why_matched' => 'The candidate matches Laravel, REST APIs, PostgreSQL, testing, Redis, and queue responsibilities.',
                'missing_skills' => ['Fintech domain examples'],
                'missing_required_skills' => [],
                'nice_to_have_gaps' => ['Payment integration examples should be added if available.'],
                'strength_areas' => ['API design', 'Laravel delivery', 'Database-backed workflows', 'Testing'],
                'risk_flags' => ['Hybrid location may require relocation or travel clarity.'],
                'resume_focus_points' => ['Emphasize API contracts', 'Mention reliability and test coverage'],
                'ai_recommendation_summary' => 'Good application target with a backend API-focused resume.',
                'ai_provider' => null,
                'ai_model' => null,
                'ai_confidence_score' => 60,
                'ai_duration_ms' => 24,
                'fallback_used' => true,
            ],
            'fullstack_engineer' => [
                'overall_score' => 76,
                'title_score' => 70,
                'skill_score' => 78,
                'experience_score' => 82,
                'seniority_score' => 80,
                'location_score' => 92,
                'backend_focus_score' => 72,
                'domain_score' => 78,
                'recommendation' => 'Consider applying if the product/frontend balance is acceptable.',
                'recommendation_action' => 'consider',
                'notes' => 'Strong backend and AI workflow alignment, but frontend ownership is more prominent.',
                'why_matched' => 'The candidate has Laravel, recruitment search, and AI integration experience, which match key product needs.',
                'missing_skills' => ['Recent Vue.js ownership depth'],
                'missing_required_skills' => ['Vue.js production ownership'],
                'nice_to_have_gaps' => ['More product discovery examples would help.'],
                'strength_areas' => ['Laravel backend', 'Recruitment domain', 'AI workflow integration'],
                'risk_flags' => ['Role may require more frontend delivery than the candidate wants.'],
                'resume_focus_points' => ['Include AI Job Platform project', 'Mention Vue exposure without overstating'],
                'ai_recommendation_summary' => 'Apply only if frontend scope is acceptable; tailor around product delivery and AI recruitment experience.',
                'ai_provider' => 'gemini',
                'ai_model' => 'gemini-2.5-flash',
                'ai_confidence_score' => 79,
                'ai_duration_ms' => 2180,
                'fallback_used' => false,
            ],
            'devops_heavy' => [
                'overall_score' => 54,
                'title_score' => 48,
                'skill_score' => 45,
                'experience_score' => 68,
                'seniority_score' => 80,
                'location_score' => 40,
                'backend_focus_score' => 42,
                'domain_score' => 50,
                'recommendation' => 'Skip unless the role can be reframed as backend platform engineering.',
                'recommendation_action' => 'skip',
                'notes' => 'The role is infrastructure-first and misses several core required skills.',
                'why_matched' => 'There is some overlap around Docker, AWS, and platform thinking, but the required Kubernetes/Terraform depth is not clearly present.',
                'missing_skills' => ['Kubernetes', 'Terraform', 'AWS networking', 'Observability ownership'],
                'missing_required_skills' => ['Kubernetes', 'Terraform', 'AWS networking'],
                'nice_to_have_gaps' => [],
                'strength_areas' => ['Backend platform context', 'Docker', 'AWS exposure'],
                'risk_flags' => ['Onsite Berlin', 'Infrastructure-heavy responsibilities', 'Missing required DevOps stack'],
                'resume_focus_points' => ['Only apply if backend platform scope is confirmed'],
                'ai_recommendation_summary' => 'Not a priority application. Keep as a comparison case in the demo.',
                'ai_provider' => 'groq',
                'ai_model' => 'llama-3.3-70b-versatile',
                'ai_confidence_score' => 82,
                'ai_duration_ms' => 1620,
                'fallback_used' => false,
            ],
        ];

        $matches = [];

        foreach ($definitions as $key => $payload) {
            $matches[$key] = JobMatch::updateOrCreate(
                ['job_id' => $jobs[$key]->id, 'profile_id' => $profile->id],
                $payload + [
                    'user_id' => $user->id,
                    'prompt_version' => 'demo-v1',
                    'input_hash' => hash('sha256', $key.'|match|demo-v1'),
                    'matched_at' => now()->subHours(8),
                    'ai_generated_at' => $payload['ai_provider'] ? now()->subHours(8) : null,
                ],
            );
        }

        return $matches;
    }

    /**
     * @param array<string, Job> $jobs
     * @return array<string, TailoredResume>
     */
    private function seedResumes(User $user, CandidateProfile $profile, array $jobs): array
    {
        $definitions = [
            'platform_engineer' => [
                'version_name' => 'demo-platform-v1',
                'headline_text' => 'Senior Laravel Platform Engineer focused on APIs, queues, Redis, PostgreSQL, Docker, and search-heavy systems.',
                'summary_text' => 'Backend engineer with 10+ years delivering Laravel APIs, queue-driven workflows, database-backed systems, and OpenSearch integrations for production platforms.',
                'skills_text' => "PHP\nLaravel\nREST APIs\nPostgreSQL\nRedis\nOpenSearch\nDocker\nAWS\nQueues\nTesting",
                'experience_text' => "Built scalable Laravel APIs and queue workflows for production systems.\nImproved search indexing and relevance using OpenSearch.\nIntegrated AI provider fallback paths with safe metadata and logging.\nOptimized database-backed workflows and API response reliability.",
                'projects_text' => "AI Job Platform\nRecruitment Search Engine",
                'ats_keywords' => ['Laravel', 'PHP', 'REST APIs', 'PostgreSQL', 'Redis', 'OpenSearch', 'Docker', 'Queues', 'AWS'],
                'warnings_or_gaps' => ['Add one concrete AWS architecture example if available.'],
                'ai_provider' => 'openrouter',
                'ai_model' => 'openrouter/auto',
                'ai_confidence_score' => 87,
                'ai_duration_ms' => 2650,
                'fallback_used' => false,
            ],
            'api_engineer' => [
                'version_name' => 'demo-api-v1',
                'headline_text' => 'Backend API Engineer with Laravel, PostgreSQL, queues, testing, and integration experience.',
                'summary_text' => 'Laravel backend engineer experienced in API contracts, data modeling, asynchronous workflows, automated tests, and production integration delivery.',
                'skills_text' => "PHP\nLaravel\nREST APIs\nPostgreSQL\nRedis\nQueues\nTesting\nDocker",
                'experience_text' => "Delivered maintainable REST APIs and backend service boundaries.\nImproved database access patterns and response times.\nAdded automated tests around high-risk backend workflows.",
                'projects_text' => "AI Job Platform",
                'ats_keywords' => ['Laravel', 'REST APIs', 'PostgreSQL', 'Testing', 'Redis', 'Queues'],
                'warnings_or_gaps' => ['Fintech/payment-specific experience is not explicit in the profile.'],
                'ai_provider' => null,
                'ai_model' => null,
                'ai_confidence_score' => 60,
                'ai_duration_ms' => 35,
                'fallback_used' => true,
            ],
        ];

        $resumes = [];

        foreach ($definitions as $key => $payload) {
            $htmlPath = $this->writeResumeHtml($profile, $jobs[$key], $payload);

            $resumes[$key] = TailoredResume::updateOrCreate(
                [
                    'job_id' => $jobs[$key]->id,
                    'profile_id' => $profile->id,
                    'version_name' => $payload['version_name'],
                ],
                [
                    'user_id' => $user->id,
                    'headline_text' => $payload['headline_text'],
                    'summary_text' => $payload['summary_text'],
                    'skills_text' => $payload['skills_text'],
                    'experience_text' => $payload['experience_text'],
                    'projects_text' => $payload['projects_text'],
                    'ats_keywords' => $payload['ats_keywords'],
                    'warnings_or_gaps' => $payload['warnings_or_gaps'],
                    'ai_provider' => $payload['ai_provider'],
                    'ai_model' => $payload['ai_model'],
                    'ai_generated_at' => $payload['ai_provider'] ? now()->subHours(5) : null,
                    'ai_confidence_score' => $payload['ai_confidence_score'],
                    'prompt_version' => 'demo-v1',
                    'input_hash' => hash('sha256', $key.'|resume|demo-v1'),
                    'ai_duration_ms' => $payload['ai_duration_ms'],
                    'fallback_used' => $payload['fallback_used'],
                    'html_path' => $htmlPath,
                    'pdf_path' => null,
                ],
            );
        }

        return $resumes;
    }

    private function seedAnswerTemplates(User $user): void
    {
        $templates = [
            'cover_letter' => [
                'title' => 'Demo Cover Letter',
                'base_answer' => "Dear Hiring Team,\n\nI am interested in the {{ job_title }} role at {{ company_name }} because it aligns with my backend platform experience in {{ required_skills }}. My background includes Laravel APIs, queue-driven systems, search workflows, and production delivery.\n\nBest regards,\n{{ full_name }}",
                'tags' => ['demo', 'cover-letter'],
            ],
            'why_interested' => [
                'title' => 'Why Interested',
                'base_answer' => 'I am interested because the role combines {{ role_type }}, backend ownership, and practical delivery in areas where I have already built production systems.',
                'tags' => ['demo', 'application-answer'],
            ],
            'about_me' => [
                'title' => 'About Me',
                'base_answer' => 'I am {{ full_name }}, a {{ headline }} with {{ years_experience }} years of experience building backend systems, APIs, search workflows, and integrations.',
                'tags' => ['demo', 'application-answer'],
            ],
            'salary_expectation' => [
                'title' => 'Salary Expectation',
                'base_answer' => 'I am open to discussing a package aligned with the role scope, seniority, and market range.',
                'tags' => ['demo', 'application-answer'],
            ],
            'notice_period' => [
                'title' => 'Notice Period',
                'base_answer' => 'My notice period can be confirmed during the process based on the final offer and current commitments.',
                'tags' => ['demo', 'application-answer'],
            ],
            'work_authorization' => [
                'title' => 'Work Authorization',
                'base_answer' => 'I can clarify work authorization and location-specific requirements during the interview process.',
                'tags' => ['demo', 'application-answer'],
            ],
        ];

        foreach ($templates as $key => $template) {
            AnswerTemplate::updateOrCreate(
                ['user_id' => $user->id, 'key' => $key],
                $template,
            );
        }
    }

    /**
     * @param array<string, Job> $jobs
     * @param array<string, TailoredResume> $resumes
     */
    private function seedApplications(User $user, CandidateProfile $profile, array $jobs, array $resumes): void
    {
        $definitions = [
            'platform_engineer' => [
                'status' => 'ready_to_apply',
                'tailored_resume_id' => $resumes['platform_engineer']->id,
                'notes' => 'Demo application created from a strong match and generated tailored resume.',
                'follow_up_date' => now()->addDays(5)->toDateString(),
                'events' => [
                    ['type' => 'match_created', 'note' => 'Strong match generated with 91 overall score.', 'hours_ago' => 8],
                    ['type' => 'resume_generated', 'note' => 'Tailored resume generated for platform engineering angle.', 'hours_ago' => 5],
                    ['type' => 'materials_generated', 'note' => 'Cover letter and application answers are ready.', 'hours_ago' => 4],
                ],
            ],
            'api_engineer' => [
                'status' => 'applied',
                'tailored_resume_id' => $resumes['api_engineer']->id,
                'notes' => 'Submitted manually after reviewing hybrid location constraints.',
                'applied_at' => now()->subDay(),
                'follow_up_date' => now()->addDays(6)->toDateString(),
                'events' => [
                    ['type' => 'resume_generated', 'note' => 'Backend API-focused resume prepared.', 'hours_ago' => 30],
                    ['type' => 'applied_manually', 'note' => 'Application submitted through company careers page.', 'hours_ago' => 24],
                ],
            ],
            'fullstack_engineer' => [
                'status' => 'interviewing',
                'tailored_resume_id' => null,
                'notes' => 'Good role for product/backend discussion. Frontend scope should be clarified.',
                'applied_at' => now()->subDays(3),
                'follow_up_date' => now()->addDays(2)->toDateString(),
                'interview_date' => now()->addDays(4)->setTime(14, 0),
                'company_response' => 'Recruiter requested an intro call to discuss product ownership and Vue expectations.',
                'events' => [
                    ['type' => 'applied_manually', 'note' => 'Applied with general backend resume.', 'hours_ago' => 72],
                    ['type' => 'company_response', 'note' => 'Recruiter replied and requested a call.', 'hours_ago' => 30],
                    ['type' => 'interview_scheduled', 'note' => 'Intro call scheduled.', 'hours_ago' => 20],
                ],
            ],
        ];

        foreach ($definitions as $key => $payload) {
            $application = Application::updateOrCreate(
                ['user_id' => $user->id, 'job_id' => $jobs[$key]->id, 'profile_id' => $profile->id],
                [
                    'tailored_resume_id' => $payload['tailored_resume_id'],
                    'status' => $payload['status'],
                    'applied_at' => $payload['applied_at'] ?? null,
                    'follow_up_date' => $payload['follow_up_date'] ?? null,
                    'notes' => $payload['notes'],
                    'company_response' => $payload['company_response'] ?? null,
                    'interview_date' => $payload['interview_date'] ?? null,
                ],
            );

            $application->events()->delete();

            foreach ($payload['events'] as $event) {
                ApplicationEvent::create([
                    'application_id' => $application->id,
                    'user_id' => $user->id,
                    'type' => $event['type'],
                    'note' => $event['note'],
                    'metadata' => ['seeded' => true],
                    'occurred_at' => now()->subHours($event['hours_ago']),
                    'created_at' => now()->subHours($event['hours_ago']),
                ]);
            }

            if ($key === 'platform_engineer') {
                $this->seedApplicationMaterials($user, $profile, $jobs[$key], $application);
            }
        }
    }

    private function seedApplicationMaterials(User $user, CandidateProfile $profile, Job $job, Application $application): void
    {
        $materials = [
            'cover_letter' => [
                'material_type' => 'cover_letter',
                'title' => 'Cover Letter',
                'question' => null,
                'content_text' => "Dear Hiring Team,\n\nI am interested in the {$job->title} role at {$job->company_name}. My background maps closely to your Laravel platform needs: APIs, queues, Redis, PostgreSQL, Docker, and search workflows. I have delivered these capabilities in production systems and can contribute quickly to backend reliability and delivery quality.\n\nBest regards,\n{$profile->full_name}",
            ],
            'why_interested' => [
                'material_type' => 'application_answer',
                'title' => 'Why are you interested?',
                'question' => 'Why are you interested in this role?',
                'content_text' => 'This role aligns with my strongest backend work: Laravel APIs, queue architecture, database-backed systems, and search-heavy platform features.',
            ],
            'about_me' => [
                'material_type' => 'application_answer',
                'title' => 'Tell us about yourself',
                'question' => 'Tell us about yourself.',
                'content_text' => 'I am a senior Laravel backend engineer focused on production APIs, queues, Redis, PostgreSQL, OpenSearch, and practical AI integrations.',
            ],
        ];

        foreach ($materials as $key => $material) {
            $template = AnswerTemplate::query()
                ->where('user_id', $user->id)
                ->where('key', $key)
                ->first();

            ApplicationMaterial::updateOrCreate(
                ['application_id' => $application->id, 'key' => $key],
                $material + [
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'profile_id' => $profile->id,
                    'answer_template_id' => $template?->id,
                    'metadata' => ['seeded' => true, 'demo_ready' => true],
                    'ai_provider' => null,
                    'ai_model' => null,
                    'ai_generated_at' => now()->subHours(4),
                    'ai_confidence_score' => 60,
                    'prompt_version' => 'demo-v1',
                    'input_hash' => hash('sha256', $application->id.'|'.$key.'|demo-v1'),
                    'ai_duration_ms' => 18,
                    'fallback_used' => true,
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeResumeHtml(CandidateProfile $profile, Job $job, array $payload): string
    {
        $path = 'resumes/tailored/demo_'.Str::slug($job->title).'_'.$profile->id.'.html';
        $skills = collect(explode("\n", $payload['skills_text']))
            ->filter()
            ->map(fn (string $skill): string => '<li>'.e($skill).'</li>')
            ->implode('');
        $bullets = collect(explode("\n", $payload['experience_text']))
            ->filter()
            ->map(fn (string $bullet): string => '<li>'.e($bullet).'</li>')
            ->implode('');

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$profile->full_name} - {$job->title}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; line-height: 1.55; max-width: 780px; margin: 40px auto; }
        h1 { font-size: 28px; margin-bottom: 4px; }
        h2 { border-bottom: 1px solid #d1d5db; padding-bottom: 4px; margin-top: 26px; }
        .muted { color: #4b5563; }
    </style>
</head>
<body>
    <h1>{$profile->full_name}</h1>
    <p class="muted">{$payload['headline_text']}</p>
    <h2>Summary</h2>
    <p>{$payload['summary_text']}</p>
    <h2>Selected Skills</h2>
    <ul>{$skills}</ul>
    <h2>Relevant Experience</h2>
    <ul>{$bullets}</ul>
</body>
</html>
HTML;

        Storage::disk('public')->put($path, $html);

        return $path;
    }

    private function jobFingerprint(?string $company, ?string $title, ?string $location): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($company),
            $this->normalize($title),
            $this->normalize($location),
        ]));
    }

    private function sourceHash(?string $company, ?string $title, ?string $location, ?string $applyUrl): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($company),
            $this->normalize($title),
            $this->normalize($location),
            $this->normalize($applyUrl),
        ]));
    }

    private function normalize(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }
}
