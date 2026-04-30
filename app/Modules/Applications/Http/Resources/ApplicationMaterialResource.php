<?php

namespace App\Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'job_id' => $this->job_id,
            'profile_id' => $this->profile_id,
            'answer_template_id' => $this->answer_template_id,
            'material_type' => $this->material_type,
            'key' => $this->key,
            'title' => $this->title,
            'question' => $this->question,
            'content' => $this->content_text,
            'metadata' => $this->metadata ?? [],
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->ai_generated_at?->toISOString(),
            'ai_confidence_score' => $this->ai_confidence_score,
            'prompt_version' => $this->prompt_version,
            'ai_duration_ms' => $this->ai_duration_ms,
            'fallback_used' => (bool) $this->fallback_used,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
