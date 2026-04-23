<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('tailored_resume_id')->nullable()->constrained('tailored_resumes')->nullOnDelete();
            $table->string('status')->default('new');
            $table->timestamp('applied_at')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('company_response')->nullable();
            $table->timestamp('interview_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
