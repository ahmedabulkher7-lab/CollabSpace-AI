<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,'.$userId],
            'job_title' => ['nullable', 'string', 'max:255'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
