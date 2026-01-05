<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for User settings.
 *
 * Transforms the User model's settings into a consistent JSON structure for API responses.
 */
class UserSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $effectiveDefaultGroupId = $user->getDefaultGroupIdOrFirst();

        return [
            'default_group_id' => $user->default_group_id,
            'effective_default_group_id' => $effectiveDefaultGroupId,
            'theme_preference' => $user->theme_preference ?? User::THEME_SYSTEM,
            'play_notification_delay_hours' => $user->play_notification_delay_hours ?? 0,
        ];
    }
}
