<?php

namespace App\Models;

use Database\Factories\ReferralRewardPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'referrer_user_id',
    'referred_user_id',
    'wallet_deposit_id',
    'amount',
    'created_at',
])]
class ReferralRewardPayout extends Model
{
    /** @use HasFactory<ReferralRewardPayoutFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /**
     * @return BelongsTo<WalletDeposit, $this>
     */
    public function walletDeposit(): BelongsTo
    {
        return $this->belongsTo(WalletDeposit::class);
    }

    /**
     * @param  Builder<ReferralRewardPayout>  $query
     * @return Builder<ReferralRewardPayout>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder
                ->whereHas('referrerUser', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('email', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('referral_code', 'like', $like);
                })
                ->orWhereHas('referredUser', function (Builder $userQuery) use ($like): void {
                    $userQuery
                        ->where('email', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('referral_code', 'like', $like);
                });
        });
    }
}
