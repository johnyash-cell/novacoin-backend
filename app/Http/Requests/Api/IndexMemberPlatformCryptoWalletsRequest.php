<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class IndexMemberPlatformCryptoWalletsRequest extends ApiFormRequest
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
            'purpose' => ['sometimes', 'string', Rule::in(['funding', 'withdrawal'])],
        ];
    }
}
