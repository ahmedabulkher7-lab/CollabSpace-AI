<?php

namespace App\Http\Resources;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'meeting_date' => $this->meeting_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'project_id' => $this->project_id,
            'created_by' => $this->created_by,
            'project' => $this->relationLoaded('project') && $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,
            'participants_count' => $this->participants_count ?? ($this->relationLoaded('participants') ? $this->participants->count() : 0),
            'participants' => $this->relationLoaded('participants') ? $this->participants->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'job_title' => $user->job_title,
                'attended' => (bool) ($user->pivot->attended ?? false),
            ]) : null,
            'summary' => $this->relationLoaded('summary') && $this->summary ? new MeetingSummaryResource($this->summary) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
