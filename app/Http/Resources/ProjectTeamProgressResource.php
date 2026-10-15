<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class ProjectTeamProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array matching the Figma Project Teams cards.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $projectId = $request->route('project')?->id ?? $request->route('project');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'completion_percentage' => $this->completion_percentage ?? 0.0,
            'total_tasks' => $this->total_tasks ?? 0,
            'completed_tasks' => $this->completed_tasks ?? 0,
            'members' => $this->members_progress ?? [],
        ];
    }
}
