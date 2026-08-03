<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Http\Requests\Api\Admin\Concerns\ValidatesInvestmentPackagePayload;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvestmentPackageRequest extends ApiFormRequest
{
    use ValidatesInvestmentPackagePayload;

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
            'name' => ['required', 'string', 'max:120'],
            'short_pitch' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'expected_return_percent' => ['required', 'numeric', 'gt:0', 'max:500'],
            'term_days' => ['required', 'integer', 'gt:0'],
            'minimum_amount_usd' => ['required', 'numeric', 'gt:0'],
            'maximum_amount_usd' => ['sometimes', 'nullable', 'numeric', 'gte:minimum_amount_usd'],
            'max_participants' => ['required', 'integer', 'min:1'],
            'joined_count' => ['required', 'integer', 'min:0', 'lte:max_participants'],
            'risk_level' => ['required', 'string', Rule::in(InvestmentPackageRiskLevel::values())],
            'availability_status' => ['required', 'string', Rule::in(InvestmentPackageAvailabilityStatus::values())],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'is_featured' => ['sometimes', 'boolean'],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*' => ['string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateInvestmentPackageCapacityAgainstAvailability($validator);
    }
}
