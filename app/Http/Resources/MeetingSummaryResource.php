<?php

namespace App\Http\Resources;

use App\Models\MeetingSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeetingSummary
 */
class MeetingSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'summary' => $this->summary,
            'decisions_made' => $this->decisions_made ?? [],
            'action_items' => $this->action_items ?? [],
            'assigned_tasks' => $this->assigned_tasks ?? [],
            'risks' => $this->risks ?? [],
            'next_steps' => $this->next_steps ?? [],
            'generated_by_ai' => (bool) $this->generated_by_ai,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
