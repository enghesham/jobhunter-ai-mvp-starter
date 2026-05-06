<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_opportunity_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('default_min_relevance_score')->nullable();
            $table->unsignedTinyInteger('default_min_match_score')->nullable();
            $table->unsignedTinyInteger('quick_recommended_score')->nullable();
            $table->boolean('store_below_threshold')->nullable();
            $table->boolean('show_below_threshold')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_opportunity_preferences');
    }
};
