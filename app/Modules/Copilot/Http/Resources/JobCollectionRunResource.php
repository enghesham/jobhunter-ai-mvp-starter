<?php

namespace App\Modules\Copilot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobCollectionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_path_id' => $this->job_path_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'source_count' => $this->source_count,
            'fetched_count' => $this->fetched_count,
            'accepted_count' => $this->accepted_count,
            'created_count' => $this->created_count,
            'updated_count' => $this->updated_count,
            'duplicate_count' => $this->duplicate_count,
            'filtered_count' => $this->filtered_count,
            'failed_count' => $this->failed_count,
            'opportunities_created' => $this->opportunities_created,
            'opportunities_updated' => $this->opportunities_updated,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],
            'job_path' => $this->whenLoaded('jobPath', fn () => [
                'id' => $this->jobPath?->id,
                'name' => $this->jobPath?->name,
                'min_relevance_score' => $this->jobPath?->min_relevance_score,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
