<?php

namespace App\Http\Requests\Api;

use App\Enums\CryptoInvestmentAssetPriceHistoryRange;
use Illuminate\Validation\Rule;

class CryptoAssetPriceHistoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('range') || $this->input('range') === null || $this->input('range') === '') {
            $this->merge([
                'range' => CryptoInvestmentAssetPriceHistoryRange::Days7->value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'range' => ['required', 'string', Rule::in(CryptoInvestmentAssetPriceHistoryRange::values())],
        ];
    }

    public function priceHistoryRange(): CryptoInvestmentAssetPriceHistoryRange
    {
        return CryptoInvestmentAssetPriceHistoryRange::from((string) $this->validated('range'));
    }
}
