<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating BoardGameGeek settings on the Settings page.
 */
class UpdateBoardGameGeekSettingsRequest extends FormRequest
{
    /**
     * Maximum length for BoardGameGeek username.
     */
    private const BOARD_GAME_GEEK_USERNAME_MAX_LENGTH = 100;

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
        $user = $this->user();
        $userId = $user?->id;

        return [
            'board_game_geek_username' => [
                'nullable',
                'string',
                'max:' . self::BOARD_GAME_GEEK_USERNAME_MAX_LENGTH,
                Rule::unique('users', 'board_game_geek_username')->ignore($userId),
            ],
            'sync_plays_to_board_game_geek' => ['sometimes', 'boolean'],
            'use_generic_user_for_bgg_plays' => ['sometimes', 'boolean'],
            'board_game_geek_password' => [
                Rule::requiredIf(function (): bool {
                    $user = $this->user();
                    return $this->boolean('sync_plays_to_board_game_geek')
                        && ! $this->boolean('use_generic_user_for_bgg_plays')
                        && ($user === null || $user->board_game_geek_password_encrypted === null);
                }),
                'nullable',
                'string',
                'min:1',
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
            'board_game_geek_username.unique' => 'This BoardGameGeek account is already linked to another user.',
            'board_game_geek_password.required' => 'Your BoardGameGeek password is required when logging plays with your own account.',
        ];
    }

    /**
     * Prepare the data for validation (trim username, empty string -> null).
     */
    protected function prepareForValidation(): void
    {
        $username = $this->input('board_game_geek_username');
        if (is_string($username)) {
            $trimmed = trim($username);
            $this->merge(['board_game_geek_username' => $trimmed === '' ? null : $trimmed]);
        }
    }
}
