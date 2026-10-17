<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('meetings.update') || $this->user()?->hasRole('Administrator');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string'],
            'decisions_made' => ['nullable', 'array'],
            'decisions_made.*' => ['string'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.title' => ['required_with:action_items', 'string'],
            'action_items.*.suggested_assignee' => ['nullable', 'string'],
            'action_items.*.priority' => ['nullable', 'string'],
            'assigned_tasks' => ['nullable', 'array'],
            'risks' => ['nullable', 'array'],
            'risks.*' => ['string'],
            'next_steps' => ['nullable', 'array'],
            'next_steps.*' => ['string'],
        ];
    }
}
