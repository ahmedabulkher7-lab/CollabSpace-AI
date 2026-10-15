<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'project_id' => $this->project_id,
            'team_id' => $this->team_id,
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'project' => $this->relationLoaded('project') && $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'team' => $this->relationLoaded('team') && $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null,
            'assignee' => $this->relationLoaded('assignee') && $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
                'avatar_url' => $this->assignee->avatar_url,
                'job_title' => $this->assignee->job_title,
            ] : null,
            'creator' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,
            'attachments_count' => $this->attachments_count ?? ($this->relationLoaded('attachments') ? $this->attachments->count() : 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
