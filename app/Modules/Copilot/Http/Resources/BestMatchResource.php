<?php

namespace App\Modules\Copilot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BestMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'match_id' => $this['match_id'],
            'job' => $this['job'],
            'fit_score' => $this['fit_score'],
            'profile_score' => $this['profile_score'],
            'path_score' => $this['path_score'],
            'recommendation' => $this['recommendation'],
            'should_apply' => $this['should_apply'],
            'why_this_fits' => $this['why_this_fits'],
            'strengths' => $this['strengths'],
            'gaps' => $this['gaps'],
            'risks' => $this['risks'],
            'apply_focus' => $this['apply_focus'],
            'matched_at' => $this['matched_at'],
        ];
    }
}
