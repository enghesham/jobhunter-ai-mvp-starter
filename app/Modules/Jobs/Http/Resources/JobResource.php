<?php

namespace App\Modules\Jobs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'company_name' => $this->company_name,
            'title' => $this->title,
            'location' => $this->location,
            'is_remote' => $this->is_remote,
            'remote_type' => $this->remote_type,
            'employment_type' => $this->employment_type,
            'description_clean' => $this->description_clean,
            'description_raw' => $this->description_raw,
            'url' => $this->apply_url,
            'raw_payload' => $this->raw_payload,
            'salary_text' => $this->salary_text,
            'posted_at' => $this->posted_at?->toISOString(),
            'status' => $this->status,
            'source' => new JobSourceResource($this->whenLoaded('source')),
            'analysis' => new JobAnalysisResource($this->whenLoaded('analysis')),
            'matches' => JobMatchResource::collection($this->whenLoaded('matches')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
