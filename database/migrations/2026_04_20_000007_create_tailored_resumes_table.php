<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tailored_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->string('version_name')->default('v1');
            $table->text('summary_text')->nullable();
            $table->text('skills_text')->nullable();
            $table->longText('experience_text')->nullable();
            $table->json('ats_keywords')->nullable();
            $table->string('html_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tailored_resumes');
    }
};
