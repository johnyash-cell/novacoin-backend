<?php

namespace App\Models;

use App\Services\Wallet\GeneratesWalletWithdrawalReferenceNumber;
use Database\Factories\WalletWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference_number',
    'user_id',
    'platform_crypto_wallet_id',
    'usd_amount',
    'crypto_amount_expected',
    'conversion_rate_usd_per_unit',
    'quoted_at',
    'asset_symbol',
    'network_name',
    'destination_wallet_address',
    'status',
    'decline_reason',
    'outbound_transaction_reference',
    'reviewed_by_admin_id',
    'reviewed_at',
])]
class WalletWithdrawal extends Model
{
    /** @use HasFactory<WalletWithdrawalFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WalletWithdrawal $walletWithdrawal): void {
            if (filled($walletWithdrawal->reference_number)) {
                return;
            }

            $walletWithdrawal->reference_number = (new GeneratesWalletWithdrawalReferenceNumber)->generate();
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
     * @param  Builder<WalletWithdrawal>  $query
     * @return Builder<WalletWithdrawal>
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
                ->orWhere('destination_wallet_address', 'like', $term)
                ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                    $userQuery
                        ->where('email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
        });
    }
}
