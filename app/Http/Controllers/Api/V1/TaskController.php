<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Display a listing of tasks with filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        // If client requests Kanban board view for a specific project
        if ($request->query('view') === 'kanban' && $request->filled('project_id')) {
            $board = $this->taskService->getKanbanBoard($request->user(), (int) $request->project_id);

            // Transform each column's tasks using TaskResource
            foreach ($board['columns'] as $status => $column) {
                $board['columns'][$status]['tasks'] = TaskResource::collection($column['tasks']);
            }

            return $this->successResponse($board, 'Kanban board retrieved successfully.');
        }

        $tasks = $this->taskService->getAll(
            user: $request->user(),
            filters: $request->only([
                'search', 'project_id', 'team_id', 'assigned_to',
                'status', 'priority', 'due_date_from', 'due_date_to',
                'sort_by', 'sort_direction',
            ]),
            perPage: (int) $request->input('per_page', 15)
        );

        return $this->successResponse(
            TaskResource::collection($tasks)->response()->getData(true),
            'Tasks retrieved successfully.'
        );
    }

    /**
     * Get Kanban board view for a specific project.
     */
    public function kanban(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('view', $project);

        $board = $this->taskService->getKanbanBoard($request->user(), $project->id);

        foreach ($board['columns'] as $status => $column) {
            $board['columns'][$status]['tasks'] = TaskResource::collection($column['tasks']);
        }

        return $this->successResponse($board, "Kanban board for project '{$project->name}' retrieved successfully.");
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $task = $this->taskService->create(
            user: $request->user(),
            data: $request->validated()
        );

        return $this->successResponse(
            new TaskResource($task),
            'Task created successfully.',
            201
        );
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $task->load(['project', 'team', 'assignee', 'creator'])->loadCount('attachments');

        return $this->successResponse(
            new TaskResource($task),
            'Task details retrieved successfully.'
        );
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $updatedTask = $this->taskService->update(
            user: $request->user(),
            task: $task,
            data: $request->validated()
        );

        return $this->successResponse(
            new TaskResource($updatedTask),
            'Task updated successfully.'
        );
    }

    /**
     * Quick status update for Kanban drag-and-drop.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('updateStatus', $task);

        $updatedTask = $this->taskService->updateStatus(
            user: $request->user(),
            task: $task,
            status: $request->validated('status')
        );

        return $this->successResponse(
            new TaskResource($updatedTask),
            "Task status successfully updated to '{$request->validated('status')}'.",
            200
        );
    }

    /**
     * Assign or reassign a task.
     */
    public function assign(AssignTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $updatedTask = $this->taskService->assign(
            user: $request->user(),
            task: $task,
            assignedTo: $request->input('assigned_to'),
            teamId: $request->input('team_id')
        );

        return $this->successResponse(
            new TaskResource($updatedTask),
            'Task assignment updated successfully.'
        );
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task, Request $request): JsonResponse
    {
        Gate::authorize('delete', $task);

        $this->taskService->delete(
            user: $request->user(),
            task: $task
        );

        return $this->successResponse(
            null,
            'Task deleted successfully.'
        );
    }
}
