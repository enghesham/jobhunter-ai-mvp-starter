<?php

namespace App\Modules\Answers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'key' => ['required', 'string', 'max:100', 'unique:answer_templates,key'],
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
            'title' => ['sometimes', 'string', 'max:255'],
            'base_answer' => ['sometimes', 'string'],
            'tags' => ['nullable', 'array'],
        ]));

        return ApiResponse::success($answerTemplate->fresh());
    }

    public function destroy(AnswerTemplate $answerTemplate): JsonResponse
    {
        $this->authorize('delete', $answerTemplate);
        $answerTemplate->delete();
        return ApiResponse::success(['message' => 'Answer template deleted']);
    }
}
