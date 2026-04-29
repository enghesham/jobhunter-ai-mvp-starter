<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_analyses', function (Blueprint $table): void {
            $table->string('prompt_version')->nullable()->after('ai_raw_response');
            $table->string('input_hash', 64)->nullable()->after('prompt_version');
            $table->unsignedInteger('ai_duration_ms')->nullable()->after('input_hash');
            $table->boolean('fallback_used')->default(false)->after('ai_duration_ms');
        });

        Schema::table('job_matches', function (Blueprint $table): void {
            $table->string('prompt_version')->nullable()->after('ai_raw_response');
            $table->string('input_hash', 64)->nullable()->after('prompt_version');
            $table->unsignedInteger('ai_duration_ms')->nullable()->after('input_hash');
            $table->boolean('fallback_used')->default(false)->after('ai_duration_ms');
        });

        Schema::table('tailored_resumes', function (Blueprint $table): void {
            $table->string('prompt_version')->nullable()->after('ai_raw_response');
            $table->string('input_hash', 64)->nullable()->after('prompt_version');
            $table->unsignedInteger('ai_duration_ms')->nullable()->after('input_hash');
            $table->boolean('fallback_used')->default(false)->after('ai_duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('job_analyses', function (Blueprint $table): void {
            $table->dropColumn(['prompt_version', 'input_hash', 'ai_duration_ms', 'fallback_used']);
        });

        Schema::table('job_matches', function (Blueprint $table): void {
            $table->dropColumn(['prompt_version', 'input_hash', 'ai_duration_ms', 'fallback_used']);
        });

        Schema::table('tailored_resumes', function (Blueprint $table): void {
            $table->dropColumn(['prompt_version', 'input_hash', 'ai_duration_ms', 'fallback_used']);
        });
    }
};
