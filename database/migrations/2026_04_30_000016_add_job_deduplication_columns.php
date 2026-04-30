<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('job_fingerprint', 64)->nullable()->after('hash');
            $table->string('source_hash', 64)->nullable()->after('job_fingerprint');
            $table->index('source_hash');
        });

        $this->populateHashes();
        $this->mergeDuplicates();

        Schema::table('jobs', function (Blueprint $table) {
            $table->unique(['user_id', 'job_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'job_fingerprint']);
            $table->dropIndex(['source_hash']);
            $table->dropColumn(['job_fingerprint', 'source_hash']);
        });
    }

    private function populateHashes(): void
    {
        DB::table('jobs')
            ->orderBy('id')
            ->chunkById(100, function (Collection $jobs): void {
                foreach ($jobs as $job) {
                    DB::table('jobs')
                        ->where('id', $job->id)
                        ->update([
                            'job_fingerprint' => $this->jobFingerprint(
                                $job->company_name,
                                $job->title,
                                $job->location,
                            ),
                            'source_hash' => $this->sourceHash(
                                $job->company_name,
                                $job->title,
                                $job->location,
                                $job->apply_url,
                            ),
                        ]);
                }
            });
    }

    private function mergeDuplicates(): void
    {
        $duplicateGroups = DB::table('jobs')
            ->select('user_id', 'job_fingerprint', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('user_id')
            ->whereNotNull('job_fingerprint')
            ->groupBy('user_id', 'job_fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $jobs = DB::table('jobs')
                ->where('user_id', $group->user_id)
                ->where('job_fingerprint', $group->job_fingerprint)
                ->orderBy('id')
                ->get();

            $canonical = $jobs->first();

            if (! $canonical) {
                continue;
            }

            foreach ($jobs->slice(1) as $duplicate) {
                $this->repointRelations((int) $duplicate->id, (int) $canonical->id);
                DB::table('jobs')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function repointRelations(int $duplicateJobId, int $canonicalJobId): void
    {
        $hasCanonicalAnalysis = DB::table('job_analyses')
            ->where('job_id', $canonicalJobId)
            ->exists();

        if ($hasCanonicalAnalysis) {
            DB::table('job_analyses')->where('job_id', $duplicateJobId)->delete();
        } else {
            DB::table('job_analyses')
                ->where('job_id', $duplicateJobId)
                ->update(['job_id' => $canonicalJobId]);
        }

        DB::table('job_matches')
            ->where('job_id', $duplicateJobId)
            ->update(['job_id' => $canonicalJobId]);

        DB::table('tailored_resumes')
            ->where('job_id', $duplicateJobId)
            ->update(['job_id' => $canonicalJobId]);

        DB::table('applications')
            ->where('job_id', $duplicateJobId)
            ->update(['job_id' => $canonicalJobId]);
    }

    private function jobFingerprint(?string $company, ?string $title, ?string $location): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($company),
            $this->normalize($title),
            $this->normalize($location),
        ]));
    }

    private function sourceHash(?string $company, ?string $title, ?string $location, ?string $applyUrl): string
    {
        return hash('sha256', implode('|', [
            $this->normalize($company),
            $this->normalize($title),
            $this->normalize($location),
            $this->normalize($applyUrl),
        ]));
    }

    private function normalize(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
};
