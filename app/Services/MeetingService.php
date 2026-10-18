<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\MeetingStatus;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingScheduledNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    /**
     * Get paginated meetings with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Meeting>
     */
    public function getAll(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->getBaseQuery($user);

        $this->applyFilters($query, $filters);

        // Sort: upcoming meetings earliest first; past meetings newest first
        if (! empty($filters['time_filter']) && $filters['time_filter'] === 'past') {
            $query->orderBy('meeting_date', 'desc')->orderBy('start_time', 'desc');
        } else {
            $query->orderBy('meeting_date', 'asc')->orderBy('start_time', 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new meeting and attach invited participants.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Meeting
    {
        return DB::transaction(function () use ($user, $data) {
            $participants = $data['participants'] ?? [];
            unset($data['participants']);

            $data['created_by'] = $user->id;
            $data['status'] = $data['status'] ?? MeetingStatus::Scheduled->value;

            /** @var Meeting $meeting */
            $meeting = Meeting::create($data);

            if (! empty($participants)) {
                $meeting->participants()->sync($participants);

                $invitedUsers = User::whereIn('id', $participants)
                    ->where('id', '!=', $user->id)
                    ->get();

                foreach ($invitedUsers as $invitedUser) {
                    $invitedUser->notify(new MeetingScheduledNotification($meeting, $meeting->project, $user));
                }
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Meeting->value,
                'action' => 'created',
                'description' => "Meeting '{$meeting->title}' scheduled for {$meeting->meeting_date->toDateString()}.",
                'payload' => [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                    'participants_count' => count($participants),
                ],
            ]);

            return $meeting->load(['project', 'creator', 'participants', 'summary']);
        });
    }

    /**
     * Update an existing meeting.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($user, $meeting, $data) {
            $participants = $data['participants'] ?? null;
            unset($data['participants']);

            $meeting->update($data);

            if ($participants !== null) {
                $meeting->participants()->sync($participants);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Meeting->value,
                'action' => 'updated',
                'description' => "Meeting '{$meeting->title}' updated.",
                'payload' => array_keys($data),
            ]);

            return $meeting->fresh(['project', 'creator', 'participants', 'summary']);
        });
    }

    /**
     * Mark attendance for meeting participants.
     *
     * @param  array<array{user_id: int, attended: bool}>  $attendanceList
     */
    public function markAttendance(User $user, Meeting $meeting, array $attendanceList): Meeting
    {
        return DB::transaction(function () use ($user, $meeting, $attendanceList) {
            foreach ($attendanceList as $record) {
                $meeting->participants()->updateExistingPivot($record['user_id'], [
                    'attended' => (bool) $record['attended'],
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Meeting->value,
                'action' => 'attendance_marked',
                'description' => "Attendance updated for meeting '{$meeting->title}'.",
                'payload' => ['meeting_id' => $meeting->id, 'count' => count($attendanceList)],
            ]);

            return $meeting->fresh(['project', 'creator', 'participants', 'summary']);
        });
    }

    /**
     * Delete a meeting.
     */
    public function delete(User $user, Meeting $meeting): void
    {
        DB::transaction(function () use ($user, $meeting) {
            $meetingId = $meeting->id;
            $title = $meeting->title;

            $meeting->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Meeting->value,
                'action' => 'deleted',
                'description' => "Meeting '{$title}' deleted.",
                'payload' => ['meeting_id' => $meetingId],
            ]);
        });
    }

    /**
     * Build base query with eager loading and user access scopes.
     *
     * @return Builder<Meeting>
     */
    protected function getBaseQuery(User $user): Builder
    {
        $query = Meeting::query()
            ->with(['project', 'creator', 'participants', 'summary']);

        // Admin sees all; regular users only see meetings for projects they have access to
        if (! $user->hasRole('Administrator') && ! $user->can('projects.view-all')) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user) {
                $projectQuery->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn ($q) => $q->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn ($q) => $q->where('users.id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Apply filter parameters to meeting query.
     *
     * @param  Builder<Meeting>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], MeetingStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['meeting_date'])) {
            $query->whereDate('meeting_date', $filters['meeting_date']);
        }

        if (! empty($filters['time_filter'])) {
            $today = Carbon::today()->toDateString();
            if ($filters['time_filter'] === 'upcoming') {
                $query->whereDate('meeting_date', '>=', $today);
            } elseif ($filters['time_filter'] === 'past') {
                $query->whereDate('meeting_date', '<', $today);
            }
        }
    }
}
