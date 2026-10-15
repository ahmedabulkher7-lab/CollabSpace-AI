<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'job_title' => $this->job_title,
            'years_of_experience' => $this->years_of_experience,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status,
            'roles' => $this->relationLoaded('roles') ? $this->roles->pluck('name') : $this->getRoleNames(),
            'permissions' => $this->relationLoaded('permissions') ? $this->permissions->pluck('name') : $this->getPermissionNames(),
            'google_linked' => ! empty($this->google_id),
            'facebook_linked' => ! empty($this->facebook_id),
            'linkedin_linked' => ! empty($this->linkedin_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
