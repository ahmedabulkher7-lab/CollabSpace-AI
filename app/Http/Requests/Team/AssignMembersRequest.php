<?php

namespace App\Http\Requests\Team;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignMembersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teams.assign-members') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:1'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role_in_team' => ['nullable', 'string', 'max:100'],
        ];
    }
}
