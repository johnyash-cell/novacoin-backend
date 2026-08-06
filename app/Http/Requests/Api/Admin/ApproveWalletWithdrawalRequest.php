<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class ApproveWalletWithdrawalRequest extends ApiFormRequest
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
            'outbound_transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
