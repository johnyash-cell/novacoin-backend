<?php

namespace App\Http\Requests\Api;

use App\Enums\InvestmentStatus;
use Illuminate\Validation\Rule;

class IndexUserInvestmentsRequest extends ApiFormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::in(InvestmentStatus::values())],
        ];
    }
}
