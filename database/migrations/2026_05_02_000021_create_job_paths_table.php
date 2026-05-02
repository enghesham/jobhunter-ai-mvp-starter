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
            $table->string('title');
            $table->text('goal')->nullable();
            $table->json('target_roles')->nullable();
            $table->json('target_fields')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->json('work_modes')->nullable();
            $table->json('employment_types')->nullable();
            $table->json('must_have_keywords')->nullable();
            $table->json('nice_to_have_keywords')->nullable();
            $table->json('avoid_keywords')->nullable();
            $table->unsignedTinyInteger('min_fit_score')->default(60);
            $table->unsignedTinyInteger('min_apply_score')->default(80);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('career_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_paths');
    }
};
