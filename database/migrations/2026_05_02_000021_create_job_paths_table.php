<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_paths', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('career_profile_id')->nullable()->constrained('candidate_profiles')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('target_roles')->nullable();
            $table->json('target_domains')->nullable();
            $table->json('include_keywords')->nullable();
            $table->json('exclude_keywords')->nullable();
            $table->json('required_skills')->nullable();
            $table->json('optional_skills')->nullable();
            $table->json('seniority_levels')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->json('preferred_countries')->nullable();
            $table->json('preferred_job_types')->nullable();
            $table->string('remote_preference')->nullable();
            $table->unsignedTinyInteger('min_relevance_score')->default(60);
            $table->unsignedTinyInteger('min_match_score')->default(75);
            $table->unsignedInteger('salary_min')->nullable();
            $table->string('salary_currency', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_collect_enabled')->default(false);
            $table->boolean('notifications_enabled')->default(false);
            $table->unsignedSmallInteger('scan_interval_hours')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('next_scan_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_active', 'auto_collect_enabled']);
            $table->index(['user_id', 'min_relevance_score']);
            $table->index(['user_id', 'min_match_score']);
            $table->index(['user_id', 'remote_preference']);
            $table->index('next_scan_at');
            $table->index('career_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_paths');
    }
};
