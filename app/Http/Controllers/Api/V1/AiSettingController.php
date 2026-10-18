<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditLogCategory;
use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AiSettingController extends Controller
{
    /**
     * Get all AI configuration settings.
     */
    public function index(): JsonResponse
    {
        $settings = AiSetting::with('updater:id,name')->get();

        return response()->json([
            'success' => true,
            'data' => $settings->map(fn (AiSetting $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'value' => $s->type === 'json' ? json_decode($s->value, true) : ($s->type === 'boolean' ? filter_var($s->value, FILTER_VALIDATE_BOOLEAN) : $s->value),
                'type' => $s->type,
                'description' => $s->description,
                'updated_by' => $s->updater ? ['id' => $s->updater->id, 'name' => $s->updater->name] : null,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Update AI configuration settings (Administrator only).
     */
    public function update(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('Administrator')) {
            throw new AccessDeniedHttpException('Only Administrators may configure AI permissions and settings.');
        }

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'exists:ai_settings,key'],
            'settings.*.value' => ['required'],
        ]);

        $updatedKeys = [];
        foreach ($validated['settings'] as $item) {
            $setting = AiSetting::where('key', $item['key'])->first();
            if ($setting) {
                AiSetting::set($item['key'], $item['value'], $setting->type, $request->user()->id);
                $updatedKeys[] = $item['key'];
            }
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'category' => AuditLogCategory::Ai->value,
            'action' => 'ai_settings_updated',
            'description' => 'Administrator updated AI platform settings.',
            'payload' => $updatedKeys,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AI settings updated successfully.',
            'data' => [
                'updated_count' => count($updatedKeys),
            ],
        ]);
    }
}
