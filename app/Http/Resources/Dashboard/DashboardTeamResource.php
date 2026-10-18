<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class DashboardTeamResource extends JsonResource
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
            'members_count' => (int) ($this->members_count ?? 0),
            'projects_count' => (int) ($this->projects_count ?? 0),
        ];
    }
}
