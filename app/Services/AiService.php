<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Generate structured meeting summary from transcript.
     *
     * @param  array<string>  $memberNames
     * @return array<string, mixed>
     */
    public function generateMeetingSummary(string $transcript, string $projectName, array $memberNames = []): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        if (! empty($apiKey) && ! app()->environment('testing')) {
            try {
                $response = $this->callGeminiApi($apiKey, $model, $transcript, $projectName, $memberNames);
                if ($response) {
                    return $response;
                }
            } catch (Exception $e) {
                Log::warning('Gemini API call failed, falling back to local extractor: '.$e->getMessage());
            }
        }

        return $this->generateLocalStructuredSummary($transcript, $projectName, $memberNames);
    }

    /**
     * Call Google Gemini API for structured JSON generation.
     *
     * @param  array<string>  $memberNames
     * @return array<string, mixed>|null
     */
    protected function callGeminiApi(string $apiKey, string $model, string $transcript, string $projectName, array $memberNames): ?array
    {
        $membersList = implode(', ', $memberNames);
        $prompt = <<<PROMPT
You are an expert AI project assistant for the CollabSpace platform.
Analyze the following meeting discussion for the project: "{$projectName}".
Team members involved: [{$membersList}].

Meeting transcript / discussion:
"""
{$transcript}
"""

Extract an executive summary, key decisions, action items, risks, and next steps.
You MUST output ONLY valid JSON adhering strictly to this schema:
{
  "summary": "Executive paragraph summarizing the key points of the meeting.",
  "decisions_made": ["Decision 1", "Decision 2"],
  "action_items": [
    {
      "title": "Action title",
      "suggested_assignee": "Member Name or null",
      "priority": "High | Medium | Low"
    }
  ],
  "assigned_tasks": {
    "Member Name": "Task description"
  },
  "risks": ["Risk or blocker 1"],
  "next_steps": ["Upcoming step or deliverable 1"]
}
PROMPT;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $res = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.2,
            ],
        ]);

        if (! $res->successful()) {
            return null;
        }

        $body = $res->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (! $text) {
            return null;
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded) || empty($decoded['summary'])) {
            return null;
        }

        $tokensUsed = $body['usageMetadata']['totalTokenCount'] ?? (int) ceil(strlen($transcript) / 3.5);
        $decoded['tokens_used'] = $tokensUsed;

        return $decoded;
    }

    /**
     * Local intelligent generator when offline or in test environments.
     * Parses the transcript text dynamically to extract decisions, action items, and risks.
     *
     * @param  array<string>  $memberNames
     * @return array<string, mixed>
     */
    protected function generateLocalStructuredSummary(string $transcript, string $projectName, array $memberNames): array
    {
        $lines = preg_split('/(?<=[.?!;])\s+|\r\n|\n|\r/', trim($transcript));
        $lines = array_filter(array_map('trim', $lines ?: []), fn ($line) => strlen($line) > 5);
        $lines = array_values($lines);

        $firstMember = ! empty($memberNames) ? $memberNames[0] : 'Lead Engineer';

        $decisions = [];
        $actionItems = [];
        $risks = [];
        $nextSteps = [];

        foreach ($lines as $line) {
            $lower = mb_strtolower($line);

            // 1. Detect decisions
            if (str_contains($lower, 'decid') || str_contains($lower, 'agree') || str_contains($lower, 'adopt') || str_contains($lower, 'approv') || str_contains($lower, 'قرار') || str_contains($lower, 'اتفق') || str_contains($lower, 'اعتماد')) {
                $decisions[] = $line;

                continue;
            }

            // 2. Detect risks and blockers
            if (str_contains($lower, 'risk') || str_contains($lower, 'delay') || str_contains($lower, 'block') || str_contains($lower, 'challenge') || str_contains($lower, 'خطر') || str_contains($lower, 'مشكلة') || str_contains($lower, 'تأخير') || str_contains($lower, 'عائق')) {
                $risks[] = $line;

                continue;
            }

            // 3. Detect next steps
            if (str_contains($lower, 'next') || str_contains($lower, 'schedule') || str_contains($lower, 'tomorrow') || str_contains($lower, 'upcoming') || str_contains($lower, 'الخطوة') || str_contains($lower, 'القادم') || str_contains($lower, 'غدا') || str_contains($lower, 'موعد')) {
                $nextSteps[] = $line;

                continue;
            }

            // 4. Action items (check if any team member is mentioned in the sentence)
            $matchedMember = null;
            foreach ($memberNames as $member) {
                if (stripos($line, $member) !== false) {
                    $matchedMember = $member;
                    break;
                }
            }

            $priority = (str_contains($lower, 'urgent') || str_contains($lower, 'critical') || str_contains($lower, 'asap') || str_contains($lower, 'عاجل')) ? 'High' : 'Medium';

            $actionItems[] = [
                'title' => $line,
                'suggested_assignee' => $matchedMember ?? $firstMember,
                'priority' => $priority,
            ];
        }

        // Sensible fallbacks if specific categories were not found in short transcript
        if (empty($decisions)) {
            $decisions = [
                "Agreed on sprint deliverables and technical standards for '{$projectName}'.",
            ];
        }

        if (empty($actionItems)) {
            $actionItems = [
                [
                    'title' => ! empty($lines[0]) ? $lines[0] : "Implement sprint features for '{$projectName}'",
                    'suggested_assignee' => $firstMember,
                    'priority' => 'High',
                ],
            ];
        }

        if (empty($risks)) {
            $risks = [
                'Tight timeline requiring active cross-functional coordination.',
            ];
        }

        if (empty($nextSteps)) {
            $nextSteps = [
                'Daily team standup and code review checkpoint.',
            ];
        }

        $summary = count($lines) > 0
            ? "Meeting overview for project '{$projectName}': ".implode(' ', array_slice($lines, 0, 3))
            : "The team convened to review deliverables, architecture, and milestones for project '{$projectName}'.";

        $assignedTasks = [];
        foreach ($actionItems as $item) {
            $assignee = $item['suggested_assignee'] ?? $firstMember;
            $assignedTasks[$assignee] = $item['title'];
        }

        $tokens = (int) max(150, (int) ceil(strlen($transcript) / 4));

        return [
            'summary' => $summary,
            'decisions_made' => array_values(array_slice($decisions, 0, 5)),
            'action_items' => array_values(array_slice($actionItems, 0, 5)),
            'assigned_tasks' => $assignedTasks,
            'risks' => array_values(array_slice($risks, 0, 4)),
            'next_steps' => array_values(array_slice($nextSteps, 0, 4)),
            'tokens_used' => $tokens,
        ];
    }

    /**
     * Answer natural language query about projects, tasks, and deadlines.
     *
     * @return array<string, mixed>
     */
    public function askAssistant(User $user, string $query, ?int $projectId = null): array
    {
        $isAdmin = $user->hasRole('Administrator');

        // Gather Projects Context
        $projectsQuery = Project::query();
        if ($projectId) {
            $projectsQuery->where('id', $projectId);
        } elseif (! $isAdmin) {
            $projectsQuery->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('directMembers', fn (Builder $sub) => $sub->where('users.id', $user->id))
                    ->orWhereHas('teams.members', fn (Builder $sub) => $sub->where('users.id', $user->id));
            });
        }

        $projects = $projectsQuery->with(['directMembers', 'tasks.assignee', 'meetings'])->get();
        $projectIds = $projects->pluck('id')->toArray();

        // Tasks Context
        $tasks = Task::whereIn('project_id', $projectIds)->with(['assignee', 'project'])->get();
        $overdueTasks = $tasks->filter(fn (Task $t) => $t->status !== TaskStatus::Completed && ! empty($t->due_date) && Carbon::parse($t->due_date)->isPast());

        // Upcoming Meetings Context
        $meetings = Meeting::whereIn('project_id', $projectIds)
            ->where('meeting_date', '>=', Carbon::today())
            ->orderBy('meeting_date')
            ->limit(5)
            ->get();

        $contextData = [
            'projects' => $projects,
            'tasks' => $tasks,
            'overdue_tasks' => $overdueTasks,
            'meetings' => $meetings,
            'user' => $user,
            'project_id' => $projectId,
        ];

        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        if (! empty($apiKey) && ! app()->environment('testing')) {
            try {
                $contextString = $this->formatContextForPrompt($contextData);
                $answer = $this->callGeminiAssistantApi($apiKey, $model, $query, $contextString);
                if ($answer) {
                    return [
                        'query' => $query,
                        'answer' => trim($answer),
                        'project_id' => $projectId,
                        'context_summary' => [
                            'projects_count' => $projects->count(),
                            'tasks_count' => $tasks->count(),
                            'overdue_tasks_count' => $overdueTasks->count(),
                            'upcoming_meetings_count' => $meetings->count(),
                        ],
                        'suggested_actions' => [
                            'View project Kanban board',
                            'Review high priority tasks',
                        ],
                    ];
                }
            } catch (Exception $e) {
                Log::warning('Gemini Assistant API call failed, falling back to local NLP: '.$e->getMessage());
            }
        }

        return $this->generateLocalAssistantAnswer($query, $contextData);
    }

    /**
     * Format contextual workspace data into text for LLM prompt.
     *
     * @param  array<string, mixed>  $context
     */
    protected function formatContextForPrompt(array $context): string
    {
        $lines = [];
        $lines[] = '=== Projects ===';
        foreach ($context['projects'] as $p) {
            $lines[] = "- Project #{$p->id}: '{$p->name}' | Progress: {$p->progress_percentage}% | Status: {$p->status}";
        }

        $lines[] = '=== Tasks ===';
        foreach ($context['tasks'] as $t) {
            $assigneeName = $t->assignee?->name ?? 'Unassigned';
            $dueDate = $t->due_date ? Carbon::parse($t->due_date)->toDateString() : 'No due date';
            $lines[] = "- Task: '{$t->name}' | Priority: {$t->priority->value} | Status: {$t->status->value} | Due: {$dueDate} | Assignee: {$assigneeName}";
        }

        $lines[] = '=== Upcoming Meetings ===';
        foreach ($context['meetings'] as $m) {
            $lines[] = "- Meeting: '{$m->title}' on {$m->meeting_date->toDateString()} at {$m->start_time} | Link: {$m->meeting_link}";
        }

        return implode("\n", $lines);
    }

    /**
     * Call Gemini Assistant for natural language answer.
     */
    protected function callGeminiAssistantApi(string $apiKey, string $model, string $query, string $context): ?string
    {
        $prompt = <<<PROMPT
You are CollabSpace AI Assistant, an expert project management AI assistant.
Answer the user's question clearly, concisely, and accurately based ONLY on the following project context data.

Context:
\"\"\"
{$context}
\"\"\"

User Question:
\"{$query}\"

Answer the user directly with actionable clarity:
PROMPT;

        $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ]);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }

        return null;
    }

    /**
     * Intelligent local NLP answer generator for testing and offline fallback.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function generateLocalAssistantAnswer(string $query, array $context): array
    {
        $lower = strtolower($query);
        $tasks = $context['tasks'];
        $overdue = $context['overdue_tasks'];
        $meetings = $context['meetings'];
        $projects = $context['projects'];
        $projectId = $context['project_id'];

        $suggestedActions = [];

        if (str_contains($lower, 'overdue') || str_contains($lower, 'late') || str_contains($lower, 'متأخر')) {
            $count = $overdue->count();
            if ($count === 0) {
                $answer = 'Great news! There are currently no overdue tasks in your active workspace.';
                $suggestedActions = ['Keep up the sprint momentum', 'Review upcoming milestones'];
            } else {
                $taskTitles = $overdue->take(3)->map(fn ($t) => "'{$t->name}' (due: ".Carbon::parse($t->due_date)->toDateString().')')->implode(', ');
                $answer = "There are currently {$count} overdue task(s) requiring attention: {$taskTitles}.";
                $suggestedActions = ['Reassign overdue tasks', 'Update sprint due dates'];
            }
        } elseif (str_contains($lower, 'meeting') || str_contains($lower, 'اجتماع')) {
            $count = $meetings->count();
            if ($count === 0) {
                $answer = 'You have no upcoming meetings scheduled in the near future.';
                $suggestedActions = ['Schedule a team standup', 'Create a milestone review meeting'];
            } else {
                $meetingTitles = $meetings->map(fn ($m) => "'{$m->title}' on {$m->meeting_date->toDateString()} at {$m->start_time}")->implode('; ');
                $answer = "You have {$count} upcoming meeting(s): {$meetingTitles}.";
                $suggestedActions = ['Prepare meeting agenda', 'Join via meeting link'];
            }
        } elseif (str_contains($lower, 'priority') || str_contains($lower, 'critical') || str_contains($lower, 'عاجل') || str_contains($lower, 'مهم')) {
            $highTasks = $tasks->filter(fn ($t) => in_array($t->priority->value ?? $t->priority, ['High', 'Critical'], true) && ($t->status->value ?? $t->status) !== 'Completed');
            $count = $highTasks->count();
            if ($count === 0) {
                $answer = 'There are no open high or critical priority tasks right now.';
                $suggestedActions = ['Check pending backlog items', 'Verify sprint goals'];
            } else {
                $titles = $highTasks->take(3)->map(fn ($t) => "'{$t->name}' [{$t->priority->value}] assigned to ".($t->assignee?->name ?? 'Unassigned'))->implode(', ');
                $answer = "There are {$count} urgent priority task(s): {$titles}.";
                $suggestedActions = ['Prioritize sprint blocker tasks', 'Assign unassigned critical tasks'];
            }
        } elseif (str_contains($lower, 'progress') || str_contains($lower, 'summary') || str_contains($lower, 'status') || str_contains($lower, 'ملخص') || str_contains($lower, 'حالة')) {
            $completedCount = $tasks->where('status', TaskStatus::Completed)->count();
            $totalCount = $tasks->count();
            $ratio = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0;

            if ($projectId && $project = $projects->first()) {
                $answer = "Project '{$project->name}' is at {$project->progress_percentage}% overall progress with {$completedCount}/{$totalCount} completed tasks.";
            } else {
                $projectCount = $projects->count();
                $answer = "Across {$projectCount} active project(s), your workspace has {$completedCount}/{$totalCount} completed tasks ({$ratio}% completion rate).";
            }
            $suggestedActions = ['View Kanban board', 'Check team performance'];
        } else {
            $totalTasksCount = $tasks->count();
            $openTasksCount = $tasks->where('status', '!=', TaskStatus::Completed)->count();
            $overdueCount = $overdue->count();
            $meetingsCount = $meetings->count();

            $answer = "Workspace Summary: You have {$projects->count()} accessible project(s) with {$openTasksCount} active task(s), {$overdueCount} overdue item(s), and {$meetingsCount} upcoming meeting(s). How can I assist you with your sprint today?";
            $suggestedActions = ['Ask about overdue tasks', 'Ask about upcoming meetings', 'Check sprint progress'];
        }

        return [
            'query' => $query,
            'answer' => $answer,
            'project_id' => $projectId,
            'context_summary' => [
                'projects_count' => $projects->count(),
                'tasks_count' => $tasks->count(),
                'overdue_tasks_count' => $overdue->count(),
                'upcoming_meetings_count' => $meetings->count(),
            ],
            'suggested_actions' => $suggestedActions,
        ];
    }
}
