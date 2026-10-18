<?php

namespace App\Http\Resources;

use App\Models\FileAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FileAttachment
 */
class FileAttachmentResource extends JsonResource
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
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize($this->file_size),
            'mime_type' => $this->mime_type,
            'url' => url("/api/v1/files/{$this->id}/download"),
            'project_id' => $this->project_id,
            'task_id' => $this->task_id,
            'uploaded_by' => $this->uploaded_by,
            'project' => $this->relationLoaded('project') && $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'task' => $this->relationLoaded('task') && $this->task ? [
                'id' => $this->task->id,
                'name' => $this->task->name,
            ] : null,
            'uploader' => $this->relationLoaded('uploader') && $this->uploader ? [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'email' => $this->uploader->email,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Helper to format file size in human-readable units.
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
