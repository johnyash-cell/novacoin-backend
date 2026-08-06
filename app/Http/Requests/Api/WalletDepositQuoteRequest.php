<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class WalletDepositQuoteRequest extends ApiFormRequest
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
            'usd_amount' => ['required', 'numeric', 'gt:0'],
            'platform_crypto_wallet_id' => [
                'required',
                'integer',
                Rule::exists('platform_crypto_wallets', 'id')->where('is_available_for_funding', true),
            ],
        ];
    }
}
