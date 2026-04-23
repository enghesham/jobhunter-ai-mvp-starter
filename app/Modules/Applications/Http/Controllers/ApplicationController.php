<?php

namespace App\Modules\Applications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Applications\Domain\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Application::latest()->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $application = Application::create($request->validate([
            'job_id' => ['required', 'integer'],
            'profile_id' => ['required', 'integer'],
            'tailored_resume_id' => ['nullable', 'integer'],
            'status' => ['required', 'string'],
            'applied_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_response' => ['nullable', 'string'],
            'interview_date' => ['nullable', 'date'],
        ]));

        return response()->json($application, 201);
    }

    public function show(Application $application): JsonResponse
    {
        return response()->json($application);
    }

    public function update(Request $request, Application $application): JsonResponse
    {
        $application->update($request->validate([
            'status' => ['sometimes', 'string'],
            'applied_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_response' => ['nullable', 'string'],
            'interview_date' => ['nullable', 'date'],
        ]));

        return response()->json($application->fresh());
    }
}
