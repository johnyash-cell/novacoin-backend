<?php

namespace App\Models;

use Database\Factories\PlatformCryptoWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'asset_symbol',
    'coingecko_asset_id',
    'network_name',
    'wallet_address',
    'is_available_for_funding',
    'is_available_for_withdrawal',
    'sort_order',
    'notes',
])]
class PlatformCryptoWallet extends Model
{
    /** @use HasFactory<PlatformCryptoWalletFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available_for_funding' => 'boolean',
            'is_available_for_withdrawal' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<WalletDeposit, $this>
     */
    public function walletDeposits(): HasMany
    {
        return $this->hasMany(WalletDeposit::class);
    }

    /**
     * @param  Builder<PlatformCryptoWallet>  $query
     * @return Builder<PlatformCryptoWallet>
     */
    public function scopeAvailableForFunding(Builder $query): Builder
    {
        return $query->where('is_available_for_funding', true);
    }

    /**
     * @param  Builder<PlatformCryptoWallet>  $query
     * @return Builder<PlatformCryptoWallet>
     */
    public function scopeAvailableForWithdrawal(Builder $query): Builder
    {
        return $query->where('is_available_for_withdrawal', true);
    }

    /**
     * @param  Builder<PlatformCryptoWallet>  $query
     * @return Builder<PlatformCryptoWallet>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! filled($search)) {
            return $query;
        }

        $term = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name', 'like', $term)
                ->orWhere('asset_symbol', 'like', $term)
                ->orWhere('network_name', 'like', $term)
                ->orWhere('wallet_address', 'like', $term);
        });
    }
}
