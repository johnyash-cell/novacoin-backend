<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\CryptoInvestmentFeeType;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCryptoInvestmentSettingsRequest extends ApiFormRequest
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
            'is_enabled' => ['sometimes', 'boolean'],
            'term_days' => ['sometimes', 'integer', 'gte:1'],
            'minimum_amount_usd' => ['sometimes', 'numeric', 'gt:0'],
            'maximum_amount_usd' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'fee_type' => ['sometimes', 'string', Rule::in(CryptoInvestmentFeeType::values())],
            'fee_value' => ['sometimes', 'numeric', 'gte:0'],
            'max_loss_enabled' => ['sometimes', 'boolean'],
            'max_loss_percent' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lte:50'],
            'supported_asset_ids' => ['sometimes', 'array', 'min:1'],
            'supported_asset_ids.*' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = [
                'is_enabled',
                'term_days',
                'minimum_amount_usd',
                'maximum_amount_usd',
                'fee_type',
                'fee_value',
                'max_loss_enabled',
                'max_loss_percent',
                'supported_asset_ids',
            ];

            $hasAny = false;

            foreach ($fields as $field) {
                if ($this->exists($field)) {
                    $hasAny = true;
                    break;
                }
            }

            if (! $hasAny) {
                $validator->errors()->add(
                    'is_enabled',
                    'Provide at least one crypto investment setting to update.',
                );
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $maxLossEnabled = $this->exists('max_loss_enabled')
                ? filter_var($this->input('max_loss_enabled'), FILTER_VALIDATE_BOOLEAN)
                : null;

            if ($maxLossEnabled === true && ! $this->filled('max_loss_percent')) {
                // Allow omitting when percent already stored; Updates service keeps existing percent.
            }

            if (
                $this->exists('minimum_amount_usd')
                && $this->exists('maximum_amount_usd')
                && $this->input('maximum_amount_usd') !== null
                && (float) $this->input('maximum_amount_usd') < (float) $this->input('minimum_amount_usd')
            ) {
                $validator->errors()->add(
                    'maximum_amount_usd',
                    'The maximum amount must be greater than or equal to the minimum amount.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_enabled', 'max_loss_enabled'] as $booleanField) {
            if ($this->has($booleanField) && is_string($this->input($booleanField))) {
                $this->merge([
                    $booleanField => filter_var($this->input($booleanField), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
    }
}
