<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Users can only update their own settings
        // Authorization is handled by the route middleware (auth:sanctum)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        
        return [
            'default_group_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
                function ($attribute, $value, $fail) use ($user): void {
                    if ($value !== null && $user) {
                        $isMember = $user->groups()->where('groups.id', $value)->exists();
                        if (!$isMember) {
                            $fail('The selected group does not exist or you are not a member of it.');
                        }
                    }
                },
            ],
            'theme_preference' => [
                'nullable',
                'string',
                Rule::in([User::THEME_LIGHT, User::THEME_DARK, User::THEME_SYSTEM]),
            ],
            'play_notification_delay_hours' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . User::MAX_PLAY_NOTIFICATION_DELAY_HOURS,
            ],
            'board_game_geek_username' => [
                'nullable',
                'string',
                'max:255',
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
            'default_group_id.exists' => 'The selected group does not exist.',
            'theme_preference.in' => 'The theme preference must be one of: light, dark, or system.',
            'play_notification_delay_hours.min' => 'The play notification delay must be at least 0 hours.',
            'play_notification_delay_hours.max' => 'The play notification delay must not exceed ' . User::MAX_PLAY_NOTIFICATION_DELAY_HOURS . ' hours.',
        ];
    }
}
