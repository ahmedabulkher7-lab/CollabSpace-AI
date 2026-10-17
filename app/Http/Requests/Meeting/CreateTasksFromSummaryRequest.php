<?php

namespace App\Http\Requests\Meeting;

use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTasksFromSummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tasks.create') || $this->user()?->hasRole('Administrator');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action_item_indices' => ['nullable', 'array'],
            'action_item_indices.*' => ['integer', 'min:0'],
            'default_priority' => ['nullable', 'string', Rule::in(TaskPriority::values())],
            'default_due_date' => ['nullable', 'date'],
        ];
    }
}
