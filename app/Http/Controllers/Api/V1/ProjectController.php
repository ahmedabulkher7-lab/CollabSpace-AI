<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AssignTeamsRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectOverviewResource;
use App\Http\Resources\ProjectTeamProgressResource;
use App\Models\Project;
use App\Models\Team;
use App\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Display a listing of projects.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $this->projectService->getAll(
            filters: $request->only(['search', 'status', 'priority', 'sort']),
            perPage: (int) $request->input('per_page', 15)
        );

        return $this->successResponse(
            ProjectListResource::collection($projects)->response()->getData(true),
            'Projects retrieved successfully.'
        );
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $project = $this->projectService->create(
            data: $request->validated(),
            creator: $request->user()
        );

        return $this->successResponse(
            new ProjectOverviewResource($project),
            'Project created successfully.',
            201
        );
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $project->load(['teams', 'directMembers', 'creator'])->loadCount(['files', 'tasks', 'meetings']);

        return $this->successResponse(
            new ProjectOverviewResource($project),
            'Project retrieved successfully.'
        );
    }

    /**
     * Retrieve complete project overview for Figma Projects / Alpha / Overview.
     */
    public function overview(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $project->load(['teams', 'directMembers', 'creator'])->loadCount(['files', 'tasks', 'meetings']);

        return $this->successResponse(
            new ProjectOverviewResource($project),
            'Project overview retrieved successfully.'
        );
    }

    /**
     * Retrieve project teams with task progress and member ratios for Figma All Projects / Alpha / teams.
     */
    public function teams(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $teamsWithProgress = $this->projectService->getProjectTeamsWithProgress($project);

        return $this->successResponse(
            ProjectTeamProgressResource::collection($teamsWithProgress),
            'Project teams and progress retrieved successfully.'
        );
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $updatedProject = $this->projectService->update(
            project: $project,
            data: $request->validated(),
            user: $request->user()
        );

        return $this->successResponse(
            new ProjectOverviewResource($updatedProject),
            'Project updated successfully.'
        );
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('delete', $project);

        $this->projectService->delete(
            project: $project,
            user: $request->user()
        );

        return $this->successResponse(null, 'Project deleted successfully.');
    }

    /**
     * Assign teams to a project.
     */
    public function assignTeams(AssignTeamsRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $updatedProject = $this->projectService->assignTeams(
            project: $project,
            teamIds: $request->validated('team_ids'),
            user: $request->user()
        );

        return $this->successResponse(
            new ProjectOverviewResource($updatedProject),
            'Teams assigned to project successfully.'
        );
    }

    /**
     * Remove a team from a project.
     */
    public function removeTeam(Project $project, Team $team, Request $request): JsonResponse
    {
        Gate::authorize('update', $project);

        $updatedProject = $this->projectService->removeTeam(
            project: $project,
            teamId: $team->id,
            user: $request->user()
        );

        return $this->successResponse(
            new ProjectOverviewResource($updatedProject),
            'Team removed from project successfully.'
        );
    }
}
