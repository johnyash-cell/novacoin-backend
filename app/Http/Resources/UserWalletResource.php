<?php

namespace App\Http\Resources;

use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserWallet
 */
class UserWalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available_balance' => (float) $this->available_balance,
            'currency_code' => $this->currency_code,
        ];
    }
}
