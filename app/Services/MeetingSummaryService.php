<?php

namespace App\Services;

use App\Enums\AuditLogCategory;
use App\Enums\MeetingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AiInteraction;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MeetingSummaryService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Generate AI-powered summary for a meeting.
     */
    public function generate(User $user, Meeting $meeting, ?string $transcript = null): MeetingSummary
    {
        return DB::transaction(function () use ($user, $meeting, $transcript) {
            $effectiveTranscript = $transcript ?: ($meeting->description ?: "Meeting '{$meeting->title}' with team members to discuss project deliverables and sprint goals.");

            // Collect member names from project and participants
            $meeting->loadMissing(['project.directMembers', 'participants']);
            $memberNames = $meeting->participants->pluck('name')
                ->merge($meeting->project->directMembers->pluck('name'))
                ->unique()
                ->values()
                ->toArray();

            $aiResult = $this->aiService->generateMeetingSummary(
                transcript: $effectiveTranscript,
                projectName: $meeting->project->name,
                memberNames: $memberNames
            );

            /** @var MeetingSummary $summary */
            $summary = MeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                [
                    'summary' => $aiResult['summary'],
                    'decisions_made' => $aiResult['decisions_made'] ?? [],
                    'action_items' => $aiResult['action_items'] ?? [],
                    'assigned_tasks' => $aiResult['assigned_tasks'] ?? [],
                    'risks' => $aiResult['risks'] ?? [],
                    'next_steps' => $aiResult['next_steps'] ?? [],
                    'generated_by_ai' => true,
                ]
            );

            // Mark meeting as completed
            $meeting->update(['status' => MeetingStatus::Completed->value]);

            // Track AI interaction
            AiInteraction::create([
                'user_id' => $user->id,
                'project_id' => $meeting->project_id,
                'type' => 'meeting_summary',
                'query' => $effectiveTranscript,
                'response' => json_encode($aiResult),
                'context_metadata' => [
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                ],
                'tokens_used' => $aiResult['tokens_used'] ?? 150,
            ]);

            // Audit log
            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Ai->value,
                'action' => 'meeting_summary_generated',
                'description' => "AI summary generated for meeting '{$meeting->title}'.",
                'payload' => [
                    'meeting_id' => $meeting->id,
                    'tokens_used' => $aiResult['tokens_used'] ?? 150,
                ],
            ]);

            return $summary;
        });
    }

    /**
     * Manually update or refine a meeting summary.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Meeting $meeting, array $data): MeetingSummary
    {
        return DB::transaction(function () use ($user, $meeting, $data) {
            /** @var MeetingSummary $summary */
            $summary = MeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                $data
            );

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Meeting->value,
                'action' => 'summary_updated',
                'description' => "Meeting summary updated for '{$meeting->title}'.",
                'payload' => ['meeting_id' => $meeting->id],
            ]);

            return $summary;
        });
    }

    /**
     * Convert AI action items into official Kanban tasks.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, Task>
     */
    public function createTasksFromSummary(User $user, Meeting $meeting, array $options = []): Collection
    {
        return DB::transaction(function () use ($user, $meeting, $options) {
            $summary = $meeting->summary;
            if (! $summary || empty($summary->action_items)) {
                return collect();
            }

            $meeting->loadMissing(['project.directMembers', 'participants']);
            $allMembers = $meeting->project->directMembers
                ->merge($meeting->participants)
                ->unique('id');

            $indices = $options['action_item_indices'] ?? null;
            $actionItems = $summary->action_items;

            $createdTasks = collect();

            foreach ($actionItems as $index => $item) {
                if ($indices !== null && ! in_array($index, $indices, true)) {
                    continue;
                }

                $title = is_array($item) ? ($item['title'] ?? 'Task from meeting') : (string) $item;
                $assigneeName = is_array($item) ? ($item['suggested_assignee'] ?? null) : null;
                $priority = is_array($item) ? ($item['priority'] ?? null) : null;

                // Match suggested assignee name to actual user ID
                $assignedUserId = null;
                if (! empty($assigneeName)) {
                    $matchedUser = $allMembers->first(function ($member) use ($assigneeName) {
                        return stripos($member->name, $assigneeName) !== false;
                    });
                    $assignedUserId = $matchedUser?->id;
                }

                if (! in_array($priority, TaskPriority::values(), true)) {
                    $priority = $options['default_priority'] ?? TaskPriority::Medium->value;
                }

                $dueDate = $options['default_due_date'] ?? Carbon::now()->addDays(7)->toDateString();

                $task = Task::create([
                    'name' => $title,
                    'description' => "Auto-generated from AI meeting summary: '{$meeting->title}'.",
                    'project_id' => $meeting->project_id,
                    'assigned_to' => $assignedUserId,
                    'created_by' => $user->id,
                    'priority' => $priority,
                    'status' => TaskStatus::Pending->value,
                    'due_date' => $dueDate,
                ]);

                $createdTasks->push($task);
            }

            // Recalculate project progress
            $meeting->project->updateProgress();

            AuditLog::create([
                'user_id' => $user->id,
                'category' => AuditLogCategory::Task->value,
                'action' => 'tasks_created_from_summary',
                'description' => "Created {$createdTasks->count()} tasks from meeting summary '{$meeting->title}'.",
                'payload' => [
                    'meeting_id' => $meeting->id,
                    'tasks_count' => $createdTasks->count(),
                ],
            ]);

            return $createdTasks;
        });
    }
}
