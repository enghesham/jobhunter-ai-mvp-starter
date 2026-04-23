<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->unsignedTinyInteger('title_score')->default(0);
            $table->unsignedTinyInteger('skill_score')->default(0);
            $table->unsignedTinyInteger('seniority_score')->default(0);
            $table->unsignedTinyInteger('location_score')->default(0);
            $table->unsignedTinyInteger('backend_focus_score')->default(0);
            $table->unsignedTinyInteger('domain_score')->default(0);
            $table->text('notes')->nullable();
            $table->string('recommendation')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
    }
};
