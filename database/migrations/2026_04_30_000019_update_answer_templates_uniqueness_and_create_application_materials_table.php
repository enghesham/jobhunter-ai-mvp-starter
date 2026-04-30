<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_templates', function (Blueprint $table): void {
            $table->dropUnique('answer_templates_key_unique');
            $table->unique(['user_id', 'key']);
        });

        Schema::create('application_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('answer_template_id')->nullable()->constrained('answer_templates')->nullOnDelete();
            $table->string('material_type', 40);
            $table->string('key', 100);
            $table->string('title');
            $table->string('question')->nullable();
            $table->longText('content_text');
            $table->json('metadata')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->unsignedSmallInteger('ai_confidence_score')->nullable();
            $table->longText('ai_raw_response')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('input_hash')->nullable();
            $table->unsignedInteger('ai_duration_ms')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->timestamps();

            $table->unique(['application_id', 'key']);
            $table->index(['application_id', 'material_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_materials');

        Schema::table('answer_templates', function (Blueprint $table): void {
            $table->dropUnique('answer_templates_user_id_key_unique');
            $table->unique('key');
        });
    }
};
