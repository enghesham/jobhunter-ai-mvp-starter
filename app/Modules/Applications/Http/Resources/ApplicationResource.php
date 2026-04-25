<?php

namespace App\Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'profile_id' => $this->profile_id,
            'tailored_resume_id' => $this->tailored_resume_id,
            'status' => $this->status,
            'applied_at' => $this->applied_at?->toISOString(),
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'notes' => $this->notes,
            'company_response' => $this->company_response,
            'interview_date' => $this->interview_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
