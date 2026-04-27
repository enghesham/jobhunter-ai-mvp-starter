<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_analyses', function (Blueprint $table) {
            $table->json('must_have_skills')->nullable()->after('preferred_skills');
            $table->json('nice_to_have_skills')->nullable()->after('must_have_skills');
            $table->json('tech_stack')->nullable()->after('domain_tags');
            $table->json('responsibilities')->nullable()->after('tech_stack');
            $table->text('company_context')->nullable()->after('responsibilities');
            $table->unsignedTinyInteger('confidence_score')->nullable()->after('ai_summary');
            $table->string('ai_provider')->nullable()->after('confidence_score');
            $table->string('ai_model')->nullable()->after('ai_provider');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_model');
            $table->unsignedTinyInteger('ai_confidence_score')->nullable()->after('ai_generated_at');
            $table->longText('ai_raw_response')->nullable()->after('ai_confidence_score');
        });

        Schema::table('job_matches', function (Blueprint $table) {
            $table->text('why_matched')->nullable()->after('notes');
            $table->json('missing_skills')->nullable()->after('why_matched');
            $table->json('strength_areas')->nullable()->after('missing_skills');
            $table->json('risk_flags')->nullable()->after('strength_areas');
            $table->json('resume_focus_points')->nullable()->after('risk_flags');
            $table->text('ai_recommendation_summary')->nullable()->after('resume_focus_points');
            $table->string('ai_provider')->nullable()->after('ai_recommendation_summary');
            $table->string('ai_model')->nullable()->after('ai_provider');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_model');
            $table->unsignedTinyInteger('ai_confidence_score')->nullable()->after('ai_generated_at');
            $table->longText('ai_raw_response')->nullable()->after('ai_confidence_score');
        });

        Schema::table('tailored_resumes', function (Blueprint $table) {
            $table->json('warnings_or_gaps')->nullable()->after('ats_keywords');
            $table->string('ai_provider')->nullable()->after('warnings_or_gaps');
            $table->string('ai_model')->nullable()->after('ai_provider');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_model');
            $table->unsignedTinyInteger('ai_confidence_score')->nullable()->after('ai_generated_at');
            $table->longText('ai_raw_response')->nullable()->after('ai_confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('tailored_resumes', function (Blueprint $table) {
            $table->dropColumn([
                'warnings_or_gaps',
                'ai_provider',
                'ai_model',
                'ai_generated_at',
                'ai_confidence_score',
                'ai_raw_response',
            ]);
        });

        Schema::table('job_matches', function (Blueprint $table) {
            $table->dropColumn([
                'why_matched',
                'missing_skills',
                'strength_areas',
                'risk_flags',
                'resume_focus_points',
                'ai_recommendation_summary',
                'ai_provider',
                'ai_model',
                'ai_generated_at',
                'ai_confidence_score',
                'ai_raw_response',
            ]);
        });

        Schema::table('job_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'must_have_skills',
                'nice_to_have_skills',
                'tech_stack',
                'responsibilities',
                'company_context',
                'confidence_score',
                'ai_provider',
                'ai_model',
                'ai_generated_at',
                'ai_confidence_score',
                'ai_raw_response',
            ]);
        });
    }
};
