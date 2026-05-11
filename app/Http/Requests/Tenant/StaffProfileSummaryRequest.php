<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffProfileSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, (\Closure(string): bool)|string>>
     */
    public function rules(): array
    {
        return [
            'treatments_period' => ['sometimes', Rule::in(['month', 'year', 'all'])],
            'revenue_period' => ['sometimes', Rule::in(['month', 'year', 'all'])],
            'appointments_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('treatments_period')) {
            $this->merge(['treatments_period' => 'month']);
        }

        if (! $this->has('appointments_limit')) {
            $this->merge(['appointments_limit' => 50]);
        }
    }
}
