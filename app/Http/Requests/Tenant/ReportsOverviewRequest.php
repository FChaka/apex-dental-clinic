<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ReportsOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:3m,6m,12m'],
            'dentist_id' => ['sometimes', 'nullable', 'integer', 'exists:staff_members,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('period')) {
            $this->merge(['period' => '6m']);
        }
    }
}
