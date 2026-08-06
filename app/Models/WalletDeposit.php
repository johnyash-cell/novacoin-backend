<?php

namespace App\Models;

use App\Services\Wallet\GeneratesWalletDepositReferenceNumber;
use Database\Factories\WalletDepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'platform_crypto_wallet_id',
    'reference_number',
    'usd_amount',
    'crypto_amount_expected',
    'conversion_rate_usd_per_unit',
    'quoted_at',
    'asset_symbol',
    'network_name',
    'wallet_address',
    'proof_image_path',
    'status',
    'decline_reason',
    'reviewed_by_admin_id',
    'reviewed_at',
])]
class WalletDeposit extends Model
{
    /** @use HasFactory<WalletDepositFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WalletDeposit $walletDeposit): void {
            if (filled($walletDeposit->reference_number)) {
                return;
            }

            $walletDeposit->reference_number = (new GeneratesWalletDepositReferenceNumber)->generate();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usd_amount' => 'float',
            'crypto_amount_expected' => 'float',
            'conversion_rate_usd_per_unit' => 'float',
            'quoted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PlatformCryptoWallet, $this>
     */
    public function platformCryptoWallet(): BelongsTo
    {
        return $this->belongsTo(PlatformCryptoWallet::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    /**
     * @param  Builder<WalletDeposit>  $query
     * @return Builder<WalletDeposit>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! filled($search)) {
            return $query;
        }

        $term = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('reference_number', 'like', $term)
                ->orWhere('asset_symbol', 'like', $term)
                ->orWhere('wallet_address', 'like', $term)
                ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                    $userQuery
                        ->where('email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
        });
    }
}
