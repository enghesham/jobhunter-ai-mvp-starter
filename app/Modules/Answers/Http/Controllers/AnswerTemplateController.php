<?php

namespace App\Modules\Answers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Answers\Domain\Models\AnswerTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnswerTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AnswerTemplate::latest()->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $template = AnswerTemplate::create($request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:answer_templates,key'],
            'title' => ['required', 'string', 'max:255'],
            'base_answer' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
        ]));

        return response()->json($template, 201);
    }

    public function show(AnswerTemplate $answerTemplate): JsonResponse
    {
        return response()->json($answerTemplate);
    }

    public function update(Request $request, AnswerTemplate $answerTemplate): JsonResponse
    {
        $answerTemplate->update($request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'base_answer' => ['sometimes', 'string'],
            'tags' => ['nullable', 'array'],
        ]));

        return response()->json($answerTemplate->fresh());
    }

    public function destroy(AnswerTemplate $answerTemplate): JsonResponse
    {
        $answerTemplate->delete();
        return response()->json(['message' => 'Answer template deleted']);
    }
}
