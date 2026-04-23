<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidate_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('tech_stack')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_projects');
    }
};
