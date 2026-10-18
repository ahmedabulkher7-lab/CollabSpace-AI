<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AskAssistantRequest;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Answer natural language query about projects, tasks, sprints, and meetings.
     */
    public function ask(AskAssistantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->aiService->askAssistant(
            $request->user(),
            $validated['query'],
            $validated['project_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
