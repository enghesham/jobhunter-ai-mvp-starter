<?php

namespace Database\Seeders;

use App\Modules\Answers\Domain\Models\AnswerTemplate;
use Illuminate\Database\Seeder;

class AnswerTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'tell_me_about_yourself' => 'I am a senior backend developer focused on Laravel, PHP, Python, APIs, databases, queues, cloud infrastructure, and building reliable production systems.',
            'why_this_role' => 'This role matches my strongest work: backend architecture, API design, database performance, queue-driven systems, and practical product delivery.',
            'why_this_company' => 'I am interested in teams that value strong engineering foundations, ownership, measurable impact, and pragmatic use of technology.',
            'biggest_project' => 'One of my strongest projects involved designing backend services, improving database/query performance, and integrating reliable asynchronous workflows.',
            'backend_architecture' => 'I usually structure backend systems around clear boundaries, thin controllers, service/action layers, queue-friendly workflows, observability, and explicit data contracts.',
            'scaling_story' => 'I approach scaling by measuring bottlenecks, improving database indexes and queries, introducing caching where justified, and moving expensive work to queues.',
            'mentoring_story' => 'I mentor by improving code review quality, documenting patterns, pairing on complex work, and helping developers make better architectural tradeoffs.',
        ];

        foreach ($templates as $key => $answer) {
            AnswerTemplate::updateOrCreate(
                ['key' => $key],
                [
                    'title' => str($key)->replace('_', ' ')->title()->toString(),
                    'base_answer' => $answer,
                    'tags' => ['interview', 'backend', 'jobhunter'],
                ],
            );
        }
    }
}
