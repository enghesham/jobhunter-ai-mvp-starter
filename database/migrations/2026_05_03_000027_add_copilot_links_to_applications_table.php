<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('job_path_id')->nullable()->after('profile_id')->constrained('job_paths')->nullOnDelete();
            $table->foreignId('apply_package_id')->nullable()->after('job_path_id')->constrained('apply_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('apply_package_id');
            $table->dropConstrainedForeignId('job_path_id');
        });
    }
};
