<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Support\PlatformCryptoAssetCatalog;
use Illuminate\Validation\Rule;

class UpdatePlatformCryptoWalletRequest extends ApiFormRequest
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
        $requiredRule = $this->isMethod('PUT') ? 'required' : 'sometimes';

        return [
            'asset_key' => [$requiredRule, 'string', Rule::in(PlatformCryptoAssetCatalog::keys())],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'network_name' => [$requiredRule, 'string', 'max:64'],
            'wallet_address' => [$requiredRule, 'string', 'max:255'],
            'is_available_for_funding' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
