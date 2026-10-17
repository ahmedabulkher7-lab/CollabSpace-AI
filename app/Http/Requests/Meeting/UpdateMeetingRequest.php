<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'meeting_link' => ['sometimes', 'required', 'url'],
            'status' => ['nullable', 'string', Rule::in(MeetingStatus::values())],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer', 'exists:users,id'],
        ];
    }
}
