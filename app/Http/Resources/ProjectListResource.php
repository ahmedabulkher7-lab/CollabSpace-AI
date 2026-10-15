<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectListResource extends JsonResource
{
    /**
     * Transform the resource into an array matching the Figma All Projects table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'progress_percentage' => (float) $this->progress_percentage,
            'files_count' => $this->files_count ?? $this->files()->count(),
            'teams' => $this->teams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
