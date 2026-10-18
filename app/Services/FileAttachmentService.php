<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Models\AuditLog;
use App\Models\FileAttachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileAttachmentService
{
    /**
     * Get paginated files with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<FileAttachment>
     */
    public function getAll(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->getBaseQuery($user);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Upload and store a new file attachment.
     */
    public function upload(
        User $user,
        UploadedFile $file,
        ?int $projectId = null,
        ?int $taskId = null,
        ?string $customName = null
    ): FileAttachment {
        if (! $projectId && $taskId) {
            $task = Task::find($taskId);
            $projectId = $task?->project_id;
        }

        $folder = $projectId ? "attachments/projects/{$projectId}" : 'attachments/general';
        $storedPath = $file->store($folder, 'public');

        $fileName = $customName ?: $file->getClientOriginalName();
        $fileSize = $file->getSize() ?: 0;
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType();

        return DB::transaction(function () use ($user, $fileName, $storedPath, $fileSize, $mimeType, $projectId, $taskId) {
            /** @var FileAttachment $attachment */
            $attachment = FileAttachment::create([
                'name' => $fileName,
                'file_path' => $storedPath,
                'disk' => 'public',
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $user->id,
                'project_id' => $projectId,
                'task_id' => $taskId,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::File->value,
                'action' => 'uploaded',
                'description' => "File '{$fileName}' uploaded.",
                'payload' => [
                    'file_id' => $attachment->id,
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'size' => $fileSize,
                ],
            ]);

            return $attachment->load(['project', 'task', 'uploader']);
        });
    }

    /**
     * Stream download file.
     */
    public function download(FileAttachment $attachment): StreamedResponse
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->file_path)) {
            abort(404, 'The requested file was not found on disk.');
        }

        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->name);
    }

    /**
     * Delete file attachment from disk and database.
     */
    public function delete(User $user, FileAttachment $attachment): void
    {
        DB::transaction(function () use ($user, $attachment) {
            $fileId = $attachment->id;
            $fileName = $attachment->name;

            // Remove file from storage disk if exists
            if (Storage::disk($attachment->disk)->exists($attachment->file_path)) {
                Storage::disk($attachment->disk)->delete($attachment->file_path);
            }

            $attachment->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::File->value,
                'action' => 'deleted',
                'description' => "File '{$fileName}' deleted.",
                'payload' => ['file_id' => $fileId],
            ]);
        });
    }

    /**
     * Build base query with eager loading and user access scopes.
     *
     * @return Builder<FileAttachment>
     */
    protected function getBaseQuery(User $user): Builder
    {
        $query = FileAttachment::query()
            ->with(['project', 'task', 'uploader']);

        // Admin sees all files; regular users see files for projects they have access to or uploaded by them
        if (! $user->hasRole('Administrator') && ! $user->can('projects.view-all')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('uploaded_by', $user->id)
                    ->orWhereHas('project', function (Builder $projectQuery) use ($user) {
                        $projectQuery->where('created_by', $user->id)
                            ->orWhereHas('directMembers', fn ($m) => $m->where('users.id', $user->id))
                            ->orWhereHas('teams.members', fn ($m) => $m->where('users.id', $user->id));
                    });
            });
        }

        return $query;
    }

    /**
     * Apply filter parameters to file query.
     *
     * @param  Builder<FileAttachment>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (! empty($filters['mime_type'])) {
            $query->where('mime_type', 'like', "%{$filters['mime_type']}%");
        }
    }
}
