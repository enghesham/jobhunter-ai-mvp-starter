<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->unique()->constrained('jobs')->cascadeOnDelete();
            $table->json('required_skills')->nullable();
            $table->json('preferred_skills')->nullable();
            $table->string('seniority')->nullable();
            $table->string('role_type')->nullable();
            $table->json('domain_tags')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('analyzed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_analyses');
    }
};
