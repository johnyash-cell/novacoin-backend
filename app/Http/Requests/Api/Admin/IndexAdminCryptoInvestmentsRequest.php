<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\InvestmentStatus;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexAdminCryptoInvestmentsRequest extends ApiFormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::in(InvestmentStatus::values())],
            'coingecko_asset_id' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
