<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->string('primary_role')->nullable();
            $table->string('seniority_level')->nullable();
            $table->json('tools')->nullable();
            $table->json('industries')->nullable();
            $table->json('education')->nullable();
            $table->json('certifications')->nullable();
            $table->json('languages')->nullable();
            $table->string('preferred_workplace_type')->nullable();
            $table->decimal('salary_expectation', 12, 2)->nullable();
            $table->string('salary_currency', 12)->nullable();
            $table->longText('raw_cv_text')->nullable();
            $table->json('parsed_cv_data')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_primary']);
            $table->dropColumn([
                'primary_role',
                'seniority_level',
                'tools',
                'industries',
                'education',
                'certifications',
                'languages',
                'preferred_workplace_type',
                'salary_expectation',
                'salary_currency',
                'raw_cv_text',
                'parsed_cv_data',
                'source',
                'is_primary',
                'metadata',
            ]);
        });
    }
};
