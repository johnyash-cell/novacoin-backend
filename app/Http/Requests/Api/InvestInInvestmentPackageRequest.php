<?php

namespace App\Http\Requests\Api;

class InvestInInvestmentPackageRequest extends ApiFormRequest
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
            'amount_usd' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
