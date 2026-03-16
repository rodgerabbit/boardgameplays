<?php

declare(strict_types=1);

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
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
            'friendly_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'group_location' => ['nullable', 'string', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:255'],
            'discord_link' => ['nullable', 'url', 'max:255'],
            'slack_link' => ['nullable', 'url', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'visibility' => ['nullable', 'string', 'in:private,viewable,publicly_joinable'],
            'group_settings' => ['nullable', 'array'],
            'group_settings.location_aliases' => ['nullable', 'array'],
            'group_settings.location_aliases.*.display_name' => ['required_with:group_settings.location_aliases.*', 'string', 'max:255'],
            'group_settings.location_aliases.*.raw_locations' => ['required_with:group_settings.location_aliases.*', 'array'],
            'group_settings.location_aliases.*.raw_locations.*' => ['string', 'max:255'],
            'group_settings.game_groups' => ['nullable', 'array'],
            'group_settings.game_groups.*.name' => ['required_with:group_settings.game_groups.*', 'string', 'max:255'],
            'group_settings.game_groups.*.board_game_ids' => ['required_with:group_settings.game_groups.*', 'array'],
            'group_settings.game_groups.*.board_game_ids.*' => ['integer', 'exists:board_games,id'],
        ];
    }
}
