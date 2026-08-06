<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserProfileResource extends JsonResource
{
    /**
     * @param  array{
     *     wallet_available_balance: float,
     *     wallet_currency_code: string,
     *     total_deposits_usd: float,
     *     total_withdrawals_usd: float,
     *     active_investments_count: int
     * }  $profileSummary
     */
    public function __construct(
        User $resource,
        private readonly array $profileSummary,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new AdminUserResource($this->resource))->resolve(),
            'wallet' => [
                'available_balance' => $this->profileSummary['wallet_available_balance'],
                'currency_code' => $this->profileSummary['wallet_currency_code'],
            ],
            'total_deposits_usd' => $this->profileSummary['total_deposits_usd'],
            'total_withdrawals_usd' => $this->profileSummary['total_withdrawals_usd'],
            'active_investments_count' => $this->profileSummary['active_investments_count'],
        ];
    }
}
