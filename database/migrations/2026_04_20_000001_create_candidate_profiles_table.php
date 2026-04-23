<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('headline')->nullable();
            $table->text('base_summary')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0);
            $table->json('preferred_roles')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->json('preferred_job_types')->nullable();
            $table->json('core_skills')->nullable();
            $table->json('nice_to_have_skills')->nullable();
            $table->string('resume_master_path')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
