<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiFormRequest;
use App\Support\PlatformCryptoAssetCatalog;
use Illuminate\Validation\Rule;

class StorePlatformCryptoWalletRequest extends ApiFormRequest
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
            'asset_key' => ['required', 'string', Rule::in(PlatformCryptoAssetCatalog::keys())],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'network_name' => ['required', 'string', 'max:64'],
            'wallet_address' => ['required', 'string', 'max:255'],
            'is_available_for_funding' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
