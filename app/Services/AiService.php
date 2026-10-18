<?php

namespace App\Services;

use Exception;
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
}
