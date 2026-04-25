<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tailored_resumes', function (Blueprint $table) {
            $table->string('headline_text')->nullable()->after('version_name');
            $table->longText('projects_text')->nullable()->after('experience_text');
        });
    }

    public function down(): void
    {
        Schema::table('tailored_resumes', function (Blueprint $table) {
            $table->dropColumn(['headline_text', 'projects_text']);
        });
    }
};
