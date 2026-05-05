<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('clinics', 'slug')],
            'contact_email' => ['required', 'email', 'max:255'],
            'plan' => ['required', Rule::in(['Starter', 'Professional', 'Enterprise'])],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_username' => ['required', 'string', 'max:100'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
