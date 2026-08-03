<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexInvestmentPackagesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'string', 'in:newest,oldest'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'availability_status' => ['sometimes', 'nullable', 'string', Rule::in(InvestmentPackageAvailabilityStatus::values())],
            'risk_level' => ['sometimes', 'nullable', 'string', Rule::in(InvestmentPackageRiskLevel::values())],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_featured') && is_string($this->input('is_featured'))) {
            $this->merge([
                'is_featured' => filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
