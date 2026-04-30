<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_analyses', function (Blueprint $table) {
            $table->unsignedTinyInteger('years_experience_min')->nullable()->after('role_type');
            $table->unsignedTinyInteger('years_experience_max')->nullable()->after('years_experience_min');
            $table->string('workplace_type')->nullable()->after('years_experience_max');
            $table->string('salary_text')->nullable()->after('workplace_type');
            $table->unsignedInteger('salary_min')->nullable()->after('salary_text');
            $table->unsignedInteger('salary_max')->nullable()->after('salary_min');
            $table->string('salary_currency', 12)->nullable()->after('salary_max');
            $table->string('location_hint')->nullable()->after('salary_currency');
            $table->string('timezone_hint')->nullable()->after('location_hint');
            $table->json('skill_categories')->nullable()->after('tech_stack');
        });
    }

    public function down(): void
    {
        Schema::table('job_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'years_experience_min',
                'years_experience_max',
                'workplace_type',
                'salary_text',
                'salary_min',
                'salary_max',
                'salary_currency',
                'location_hint',
                'timezone_hint',
                'skill_categories',
            ]);
        });
    }
};
