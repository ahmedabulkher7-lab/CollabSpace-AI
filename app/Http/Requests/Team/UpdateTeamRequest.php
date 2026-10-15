<?php

namespace App\Http\Requests\Team;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('teams.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->route('team')?->id ?? $this->route('team');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:teams,name,'.$teamId],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
