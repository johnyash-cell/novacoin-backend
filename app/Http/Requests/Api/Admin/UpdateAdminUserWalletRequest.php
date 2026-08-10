<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateAdminUserWalletRequest extends ApiFormRequest
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
            'available_balance' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'available_balance.required' => 'Enter an available balance.',
            'available_balance.numeric' => 'Available balance must be a number.',
            'available_balance.gte' => 'Available balance cannot be negative.',
            'available_balance.decimal' => 'Available balance may have at most 2 decimal places.',
        ];
    }
}
