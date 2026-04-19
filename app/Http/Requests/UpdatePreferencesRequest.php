<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * Normalize form values before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('default_group_id') && $this->input('default_group_id') === '') {
            $this->merge(['default_group_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'theme_preference' => [
                'required',
                'string',
                Rule::in([User::THEME_LIGHT, User::THEME_DARK, User::THEME_SYSTEM]),
            ],
            'is_profile_public' => ['sometimes', 'boolean'],
            'default_group_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($value === null || ! $user instanceof User) {
                        return;
                    }
                    if (! $user->groups()->where('groups.id', (int) $value)->exists()) {
                        $fail('The selected group does not exist or you are not a member of it.');
                    }
                },
            ],
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
            'default_group_id.exists' => 'The selected group does not exist.',
        ];
    }
}
