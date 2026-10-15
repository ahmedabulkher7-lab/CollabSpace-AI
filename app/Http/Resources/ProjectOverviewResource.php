<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array matching the Figma Project Overview screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = Carbon::now();
        $startDate = $this->start_date ? Carbon::parse($this->start_date) : $now;
        $deadline = $this->deadline ? Carbon::parse($this->deadline) : $now;

        $totalDays = max(1, $startDate->diffInDays($deadline));
        $daysRemaining = $now->diffInDays($deadline, false);
        $isOverdue = $now->isAfter($deadline) && $this->status->value !== 'Completed';

        // Tasks breakdown
        $totalTasks = $this->tasks()->count();
        $pendingTasks = $this->tasks()->where('status', TaskStatus::Pending->value)->count();
        $inProgressTasks = $this->tasks()->where('status', TaskStatus::InProgress->value)->count();
        $inReviewTasks = $this->tasks()->where('status', TaskStatus::InReview->value)->count();
        $completedTasks = $this->tasks()->where('status', TaskStatus::Completed->value)->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'progress_percentage' => (float) $this->progress_percentage,
            'due_date_indicator' => [
                'start_date' => $startDate->toDateString(),
                'deadline' => $deadline->toDateString(),
                'total_days' => $totalDays,
                'days_remaining' => (int) $daysRemaining,
                'is_overdue' => $isOverdue,
            ],
            'tasks_statistics' => [
                'total' => $totalTasks,
                'pending' => $pendingTasks,
                'in_progress' => $inProgressTasks,
                'in_review' => $inReviewTasks,
                'completed' => $completedTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0,
            ],
            'counts' => [
                'files' => $this->files()->count(),
                'meetings' => $this->meetings()->count(),
                'messages' => $this->messages()->count(),
                'teams' => $this->teams()->count(),
                'direct_members' => $this->directMembers()->count(),
            ],
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'direct_members' => UserResource::collection($this->whenLoaded('directMembers')),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
