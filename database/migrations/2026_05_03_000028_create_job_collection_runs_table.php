<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_collection_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_path_id')->nullable()->constrained('job_paths')->nullOnDelete();
            $table->string('status', 32)->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('source_count')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('filtered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('opportunities_created')->default(0);
            $table->unsignedInteger('opportunities_updated')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'job_path_id']);
            $table->index(['job_path_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_collection_runs');
    }
};
