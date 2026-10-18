<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\File\UploadFileRequest;
use App\Http\Resources\FileAttachmentResource;
use App\Models\FileAttachment;
use App\Services\FileAttachmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileAttachmentService $fileService
    ) {}

    /**
     * Display a listing of files with filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FileAttachment::class);

        $files = $this->fileService->getAll(
            user: $request->user(),
            filters: $request->only(['search', 'project_id', 'task_id', 'uploaded_by', 'mime_type']),
            perPage: (int) $request->input('per_page', 15)
        );

        return $this->successResponse(
            FileAttachmentResource::collection($files)->response()->getData(true),
            'Files retrieved successfully.'
        );
    }

    /**
     * Upload and store a new file attachment.
     */
    public function store(UploadFileRequest $request): JsonResponse
    {
        Gate::authorize('upload', FileAttachment::class);

        $attachment = $this->fileService->upload(
            user: $request->user(),
            file: $request->file('file'),
            projectId: $request->input('project_id') ? (int) $request->input('project_id') : null,
            taskId: $request->input('task_id') ? (int) $request->input('task_id') : null,
            customName: $request->input('name')
        );

        return $this->successResponse(
            new FileAttachmentResource($attachment),
            'File uploaded successfully.',
            201
        );
    }

    /**
     * Display the specified file attachment metadata.
     */
    public function show(FileAttachment $file): JsonResponse
    {
        Gate::authorize('view', $file);

        $file->load(['project', 'task', 'uploader']);

        return $this->successResponse(
            new FileAttachmentResource($file),
            'File details retrieved successfully.'
        );
    }

    /**
     * Download the specified file.
     */
    public function download(FileAttachment $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        return $this->fileService->download($file);
    }

    /**
     * Remove the specified file attachment from storage and DB.
     */
    public function destroy(FileAttachment $file): JsonResponse
    {
        Gate::authorize('delete', $file);

        $this->fileService->delete(
            user: request()->user(),
            attachment: $file
        );

        return $this->successResponse(
            null,
            'File deleted successfully.'
        );
    }
}
