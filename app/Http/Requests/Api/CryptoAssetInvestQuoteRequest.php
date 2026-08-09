<?php

namespace App\Http\Requests\Api;

use App\Enums\CryptoInvestmentFeeChargeSource;
use Illuminate\Validation\Rule;

class CryptoAssetInvestQuoteRequest extends ApiFormRequest
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
            'amount_usd' => ['required', 'numeric', 'gt:0'],
            'fee_charge_source' => ['required', 'string', Rule::in(CryptoInvestmentFeeChargeSource::values())],
        ];
    }
}
