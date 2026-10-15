<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleInTeam = $this->pivot?->role_in_team ?? 'Member';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'avatar_url' => $this->avatar_url,
            'role_in_team' => $roleInTeam,
            'is_leader' => strcasecmp($roleInTeam, 'Leader') === 0 || strcasecmp($roleInTeam, 'Lead') === 0 || strcasecmp($roleInTeam, 'Lead Engineer') === 0,
            'joined_at' => $this->pivot?->created_at?->toIso8601String(),
        ];
    }
}
