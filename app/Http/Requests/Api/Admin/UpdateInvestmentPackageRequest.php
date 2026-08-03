<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\InvestmentPackage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvestmentPackageRequest extends ApiFormRequest
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
        $requiredRule = $this->isMethod('PUT') ? 'required' : 'sometimes';

        return [
            'name' => [$requiredRule, 'string', 'max:120'],
            'short_pitch' => [$requiredRule, 'string', 'max:160'],
            'description' => [$requiredRule, 'string', 'max:5000'],
            'expected_return_percent' => [$requiredRule, 'numeric', 'gt:0', 'max:500'],
            'term_days' => [$requiredRule, 'integer', 'gt:0'],
            'minimum_amount_usd' => [$requiredRule, 'numeric', 'gt:0'],
            'maximum_amount_usd' => ['sometimes', 'nullable', 'numeric'],
            'max_participants' => [$requiredRule, 'integer', 'min:1'],
            'joined_count' => [$requiredRule, 'integer', 'min:0'],
            'risk_level' => [$requiredRule, 'string', Rule::in(InvestmentPackageRiskLevel::values())],
            'availability_status' => [$requiredRule, 'string', Rule::in(InvestmentPackageAvailabilityStatus::values())],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*' => ['string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var InvestmentPackage $investmentPackage */
            $investmentPackage = $this->route('investment_package');

            $joinedCount = $this->has('joined_count')
                ? (int) $this->input('joined_count')
                : $investmentPackage->joined_count;
            $maxParticipants = $this->has('max_participants')
                ? (int) $this->input('max_participants')
                : $investmentPackage->max_participants;
            $minimumAmountUsd = $this->has('minimum_amount_usd')
                ? (float) $this->input('minimum_amount_usd')
                : (float) $investmentPackage->minimum_amount_usd;
            $availabilityStatus = $this->has('availability_status')
                ? (string) $this->input('availability_status')
                : (string) $investmentPackage->availability_status;

            if ($joinedCount > $maxParticipants) {
                $validator->errors()->add(
                    'joined_count',
                    'The joined count must be less than or equal to max participants.',
                );
            }

            if ($maxParticipants < $joinedCount) {
                $validator->errors()->add(
                    'max_participants',
                    'The max participants cannot be less than the current joined count.',
                );
            }

            if ($this->exists('maximum_amount_usd') && $this->input('maximum_amount_usd') !== null) {
                if ((float) $this->input('maximum_amount_usd') < $minimumAmountUsd) {
                    $validator->errors()->add(
                        'maximum_amount_usd',
                        'The maximum amount must be greater than or equal to the minimum amount.',
                    );
                }
            }

            $statusEnum = InvestmentPackageAvailabilityStatus::tryFrom($availabilityStatus);

            if ($statusEnum?->isJoinableIntent() && $joinedCount >= $maxParticipants) {
                $validator->errors()->add(
                    'availability_status',
                    'This investment package is at capacity and cannot be set to open or limited.',
                );
            }
        });
    }
}
