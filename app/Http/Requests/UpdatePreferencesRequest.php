<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating user preferences on the Settings page.
 */
class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme_preference' => [
                'required',
                'string',
                Rule::in([User::THEME_LIGHT, User::THEME_DARK, User::THEME_SYSTEM]),
            ],
            'is_profile_public' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme_preference.in' => 'The theme must be one of: System preference, Dark, or Light.',
        ];
    }
}
