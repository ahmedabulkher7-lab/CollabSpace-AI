<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class DashboardProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasksTotal = $this->tasks ? $this->tasks->count() : 0;
        $tasksCompleted = $this->tasks ? $this->tasks->where('status', TaskStatus::Completed)->count() : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'progress' => (float) $this->progress_percentage,
            'tasks_total' => $tasksTotal > 0 ? $tasksTotal : null,
            'tasks_completed' => $tasksTotal > 0 ? $tasksCompleted : null,
        ];
    }
}
