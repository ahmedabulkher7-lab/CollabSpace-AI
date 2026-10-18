<?php

namespace App\Http\Resources;

use App\Models\ProjectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectMessage
 */
class ProjectMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'message' => $this->message,
            'is_edited' => (bool) $this->is_edited,
            'sender' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar_url' => $this->user->avatar_url,
                'job_title' => $this->user->job_title,
            ] : null,
            'parent' => $this->relationLoaded('parent') && $this->parent ? [
                'id' => $this->parent->id,
                'message' => $this->parent->message,
                'sender_name' => $this->parent->user?->name,
            ] : null,
            'attachment' => $this->relationLoaded('attachment') && $this->attachment ? [
                'id' => $this->attachment->id,
                'name' => $this->attachment->name,
                'mime_type' => $this->attachment->mime_type,
                'file_size' => $this->attachment->file_size,
                'url' => url("/api/v1/files/{$this->attachment->id}/download"),
            ] : null,
            'mentions' => $this->relationLoaded('mentions') ? $this->mentions->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]) : [],
            'is_read_by_me' => $this->relationLoaded('readers') && $currentUserId
                ? $this->readers->contains('id', $currentUserId)
                : false,
            'readers_count' => $this->relationLoaded('readers') ? $this->readers->count() : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
