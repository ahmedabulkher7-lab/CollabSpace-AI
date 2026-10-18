<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class DashboardTaskResource extends JsonResource
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
            'priority' => strtolower($this->priority?->value ?? $this->priority),
            'status' => strtolower(str_replace(' ', '_', $this->status?->value ?? $this->status)),
            'status_label' => $this->status?->value ?? $this->status,
            'due_date' => $this->due_date ? Carbon::parse($this->due_date)->toDateString() : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'avatar_url' => $this->assignee->avatar_url,
            ] : null,
        ];
    }
}
