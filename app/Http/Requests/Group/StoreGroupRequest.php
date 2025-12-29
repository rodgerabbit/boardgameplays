<?php

declare(strict_types=1);

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
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
            'friendly_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'group_location' => ['nullable', 'string', 'max:255'],
            'website_link' => ['nullable', 'url', 'max:255'],
            'discord_link' => ['nullable', 'url', 'max:255'],
            'slack_link' => ['nullable', 'url', 'max:255'],
        ];
    }
}
