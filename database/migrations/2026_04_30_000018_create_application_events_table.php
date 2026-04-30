<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['application_id', 'created_at']);
            $table->index(['application_id', 'type']);
        });

        DB::table('applications')
            ->where('status', 'interview')
            ->update(['status' => 'interviewing']);
    }

    public function down(): void
    {
        DB::table('applications')
            ->where('status', 'interviewing')
            ->update(['status' => 'interview']);

        Schema::dropIfExists('application_events');
    }
};
