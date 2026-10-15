<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AssignMembersRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TeamService $teamService
    ) {}

    /**
     * Display a listing of teams.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Team::class);

        $teams = $this->teamService->getAll(
            filters: $request->only(['search']),
            perPage: (int) $request->input('per_page', 15)
        );

        return $this->successResponse(
            TeamResource::collection($teams)->response()->getData(true),
            'Teams retrieved successfully.'
        );
    }

    /**
     * Store a newly created team.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        Gate::authorize('create', Team::class);

        $team = $this->teamService->create(
            data: $request->validated(),
            creator: $request->user()
        );

        return $this->successResponse(
            new TeamResource($team),
            'Team created successfully.',
            201
        );
    }

    /**
     * Display the specified team.
     */
    public function show(Team $team): JsonResponse
    {
        Gate::authorize('view', $team);

        $team->load(['creator', 'members'])->loadCount(['members', 'projects']);

        return $this->successResponse(
            new TeamResource($team),
            'Team retrieved successfully.'
        );
    }

    /**
     * Update the specified team.
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        Gate::authorize('update', $team);

        $updatedTeam = $this->teamService->update(
            team: $team,
            data: $request->validated(),
            user: $request->user()
        );

        return $this->successResponse(
            new TeamResource($updatedTeam),
            'Team updated successfully.'
        );
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Team $team, Request $request): JsonResponse
    {
        Gate::authorize('delete', $team);

        $this->teamService->delete(
            team: $team,
            user: $request->user()
        );

        return $this->successResponse(null, 'Team deleted successfully.');
    }

    /**
     * Assign or update members in the team.
     */
    public function assignMembers(AssignMembersRequest $request, Team $team): JsonResponse
    {
        Gate::authorize('assignMembers', $team);

        $updatedTeam = $this->teamService->assignMembers(
            team: $team,
            members: $request->validated('members'),
            user: $request->user()
        );

        return $this->successResponse(
            new TeamResource($updatedTeam),
            'Members assigned to team successfully.'
        );
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(Team $team, User $user, Request $request): JsonResponse
    {
        Gate::authorize('assignMembers', $team);

        $updatedTeam = $this->teamService->removeMember(
            team: $team,
            userId: $user->id,
            user: $request->user()
        );

        return $this->successResponse(
            new TeamResource($updatedTeam),
            'Member removed from team successfully.'
        );
    }
}
