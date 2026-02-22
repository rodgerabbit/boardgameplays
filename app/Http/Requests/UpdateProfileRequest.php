<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for profile update validation.
 *
 * Validates name, biography, and optional profile picture upload.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Maximum file size for profile picture in kilobytes.
     */
    private const PROFILE_PICTURE_MAX_KB = 2048;

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
            'name' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:' . self::PROFILE_PICTURE_MAX_KB,
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
            'name.required' => 'Your name is required.',
            'name.max' => 'Your name must not exceed 255 characters.',
            'biography.max' => 'The biography must not exceed 2000 characters.',
            'profile_picture.image' => 'The profile picture must be an image.',
            'profile_picture.mimes' => 'The profile picture must be a JPEG, PNG, GIF, or WebP image.',
            'profile_picture.max' => 'The profile picture must not exceed 2 MB.',
        ];
    }
}
