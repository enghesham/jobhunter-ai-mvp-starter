<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('apply_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('career_profile_id')->nullable()->constrained('candidate_profiles')->nullOnDelete();
            $table->foreignId('job_path_id')->nullable()->constrained('job_paths')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained('tailored_resumes')->nullOnDelete();
            $table->longText('cover_letter')->nullable();
            $table->json('application_answers')->nullable();
            $table->text('salary_answer')->nullable();
            $table->text('notice_period_answer')->nullable();
            $table->text('interest_answer')->nullable();
            $table->json('strengths')->nullable();
            $table->json('gaps')->nullable();
            $table->json('interview_questions')->nullable();
            $table->text('follow_up_email')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->unsignedTinyInteger('ai_confidence_score')->nullable();
            $table->unsignedInteger('ai_duration_ms')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->string('status', 32)->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apply_packages');
    }
};
