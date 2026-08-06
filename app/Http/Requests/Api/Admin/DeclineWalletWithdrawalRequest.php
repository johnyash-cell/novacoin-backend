<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;

class DeclineWalletWithdrawalRequest extends ApiFormRequest
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
            'decline_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
