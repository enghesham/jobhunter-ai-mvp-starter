<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('experience_score')->default(0)->after('skill_score');
            $table->json('missing_required_skills')->nullable()->after('missing_skills');
            $table->json('nice_to_have_gaps')->nullable()->after('missing_required_skills');
            $table->string('recommendation_action')->nullable()->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->dropColumn([
                'experience_score',
                'missing_required_skills',
                'nice_to_have_gaps',
                'recommendation_action',
            ]);
        });
    }
};
