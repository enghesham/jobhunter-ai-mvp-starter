<?php

namespace App\Modules\Answers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnswerTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            AnswerTemplate::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->paginate()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $template = AnswerTemplate::create([
            ...$request->validate([
            'key' => ['required', 'string', 'max:100', Rule::unique('answer_templates', 'key')->where('user_id', auth()->id())],
            'title' => ['required', 'string', 'max:255'],
            'base_answer' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
        ]),
            'user_id' => auth()->id(),
        ]);

        return ApiResponse::success($template, 201);
    }

    public function show(AnswerTemplate $answerTemplate): JsonResponse
    {
        $this->authorize('view', $answerTemplate);

        return ApiResponse::success($answerTemplate);
    }

    public function update(Request $request, AnswerTemplate $answerTemplate): JsonResponse
    {
        $this->authorize('update', $answerTemplate);

        $answerTemplate->update($request->validate([
            'key' => ['sometimes', 'string', 'max:100', Rule::unique('answer_templates', 'key')->ignore($answerTemplate->id)->where('user_id', auth()->id())],
            'title' => ['sometimes', 'string', 'max:255'],
            'base_answer' => ['sometimes', 'string'],
            'tags' => ['nullable', 'array'],
        ]));

        return ApiResponse::success($answerTemplate->fresh());
    }

    public function bootstrapDefaults(): JsonResponse
    {
        $defaults = [
            [
                'key' => 'cover_letter',
                'title' => 'Default Cover Letter',
                'base_answer' => "Dear Hiring Team,\n\nI am interested in the {{job_title}} position at {{company_name}}. My background is centered on {{headline}}, and I bring {{years_experience}} years of experience delivering work around {{strength_areas}}.\n\nThis role stands out because of its focus on {{required_skills}}, and I believe my experience can support the team effectively.\n\nBest regards,\n{{full_name}}",
                'tags' => ['application', 'cover-letter'],
            ],
            [
                'key' => 'why_interested',
                'title' => 'Why Interested',
                'base_answer' => 'I am interested in this role because it aligns with my background in {{headline}} and with my experience across {{strength_areas}}. The scope around {{required_skills}} is especially relevant to the kind of work I want to keep building.',
                'tags' => ['application', 'motivation'],
            ],
            [
                'key' => 'about_me',
                'title' => 'Tell Us About Yourself',
                'base_answer' => 'I am {{full_name}}, a {{headline}} with {{years_experience}} years of experience. My background has focused on {{base_summary}}, with particular strength in {{strength_areas}}.',
                'tags' => ['application', 'intro'],
            ],
            [
                'key' => 'salary_expectation',
                'title' => 'Salary Expectation',
                'base_answer' => 'I am open to discussing a compensation package that is aligned with the responsibilities, scope, and market range of this role.',
                'tags' => ['application', 'salary'],
            ],
            [
                'key' => 'notice_period',
                'title' => 'Notice Period',
                'base_answer' => 'My notice period can be confirmed based on the final offer and my current commitments. I can share exact timing during the interview process.',
                'tags' => ['application', 'availability'],
            ],
            [
                'key' => 'work_authorization',
                'title' => 'Work Authorization',
                'base_answer' => 'I can clarify my current work authorization status and any location-specific requirements during the application process.',
                'tags' => ['application', 'authorization'],
            ],
        ];

        foreach ($defaults as $definition) {
            AnswerTemplate::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'key' => $definition['key'],
                ],
                $definition + ['user_id' => auth()->id()]
            );
        }

        return ApiResponse::success(
            AnswerTemplate::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->paginate()
        );
    }

    public function destroy(AnswerTemplate $answerTemplate): JsonResponse
    {
        $this->authorize('delete', $answerTemplate);
        $answerTemplate->delete();
        return ApiResponse::success(['message' => 'Answer template deleted']);
    }
}
