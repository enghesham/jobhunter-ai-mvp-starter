<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table): void {
            $table->foreignId('job_path_id')->nullable()->after('profile_id')->constrained('job_paths')->nullOnDelete();
            $table->string('context_key', 80)->default('primary')->after('job_path_id');
            $table->unsignedTinyInteger('path_relevance_score')->nullable()->after('domain_score');
            $table->json('path_relevance_reasons')->nullable()->after('path_relevance_score');
        });

        Schema::table('job_matches', function (Blueprint $table): void {
            $table->dropUnique('job_matches_job_id_profile_id_unique');
            $table->unique(['job_id', 'profile_id', 'context_key'], 'job_matches_context_unique');
        });

        Schema::create('job_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('job_path_id')->nullable()->constrained('job_paths')->cascadeOnDelete();
            $table->foreignId('career_profile_id')->nullable()->constrained('candidate_profiles')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('job_matches')->nullOnDelete();
            $table->string('context_key', 80);
            $table->unsignedTinyInteger('quick_relevance_score')->default(0);
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->string('status', 32)->default('needs_review');
            $table->string('recommendation')->nullable();
            $table->json('reasons')->nullable();
            $table->json('matched_keywords')->nullable();
            $table->json('missing_keywords')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_id', 'context_key'], 'job_opportunities_context_unique');
            $table->index(['user_id', 'status', 'quick_relevance_score'], 'job_opportunities_status_score_index');
            $table->index(['user_id', 'job_path_id', 'status'], 'job_opportunities_path_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_opportunities');

        Schema::table('job_matches', function (Blueprint $table): void {
            $table->dropUnique('job_matches_context_unique');
        });

        Schema::table('job_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_path_id');
            $table->dropColumn([
                'context_key',
                'path_relevance_score',
                'path_relevance_reasons',
            ]);
            $table->unique(['job_id', 'profile_id']);
        });
    }
};
