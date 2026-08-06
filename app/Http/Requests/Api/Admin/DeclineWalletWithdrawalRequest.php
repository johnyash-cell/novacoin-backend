<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\Admin\Concerns\ValidatesOptionalMemberNotifyFlags;
use App\Http\Requests\Api\ApiFormRequest;

class DeclineWalletWithdrawalRequest extends ApiFormRequest
{
    use ValidatesOptionalMemberNotifyFlags;

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
            ...$this->optionalMemberNotifyFlagRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareOptionalMemberNotifyFlagsForValidation();
    }
}
