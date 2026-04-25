<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->foreignId('source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('title');
            $table->string('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->string('remote_type')->nullable();
            $table->string('employment_type')->nullable();
            $table->longText('description_raw')->nullable();
            $table->longText('description_clean')->nullable();
            $table->string('apply_url', 2048)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('salary_text')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('hash')->unique();
            $table->string('status')->default('new');
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['company_name', 'title']);
            $table->index(['apply_url']);
            $table->index(['posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
