<?php

declare(strict_types=1);

namespace App\Http\Requests\BoardGamePlay;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardGamePlayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'board_game_id' => [
                'required',
                'exists:board_games,id',
                function ($attribute, $value, $fail) {
                    $boardGame = \App\Models\BoardGame::find($value);
                    if ($boardGame !== null && $boardGame->is_expansion) {
                        $fail('The selected board game must not be an expansion. Use a base game instead.');
                    }
                },
            ],
            'group_id' => ['nullable', 'exists:groups,id'],
            'played_at' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
            'game_length_minutes' => ['nullable', 'integer', 'min:1'],
            'source' => ['required', Rule::in(['website', 'boardgamegeek'])],
            'expansions' => ['nullable', 'array'],
            'expansions.*' => [
                'exists:board_games,id',
                function ($attribute, $value, $fail) {
                    $boardGame = \App\Models\BoardGame::find($value);
                    if ($boardGame !== null && !$boardGame->is_expansion) {
                        $fail('The selected expansion must be an expansion.');
                    }
                },
            ],
            'players' => ['required', 'array', 'min:1', 'max:30'],
            'players.*.user_id' => [
                'nullable',
                'required_without_all:players.*.board_game_geek_username,players.*.guest_name',
                'exists:users,id',
            ],
            'players.*.board_game_geek_username' => [
                'nullable',
                'required_without_all:players.*.user_id,players.*.guest_name',
                'string',
                'max:255',
            ],
            'players.*.guest_name' => [
                'nullable',
                'required_without_all:players.*.user_id,players.*.board_game_geek_username',
                'string',
                'max:255',
            ],
            'players.*.score' => ['nullable', 'numeric'],
            'players.*.is_winner' => ['nullable', 'boolean'],
            'players.*.position' => ['nullable', 'integer', 'min:1'],
            'sync_to_board_game_geek' => ['nullable', 'boolean'],
            'board_game_geek_username' => ['nullable', 'string', 'max:255'],
            'board_game_geek_password' => ['nullable', 'string'],
        ];
    }
}

