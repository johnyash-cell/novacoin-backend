<?php

namespace App\Models;

use Database\Factories\WalletLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_wallet_id',
    'entry_type',
    'amount',
    'balance_after',
    'wallet_deposit_id',
    'investment_id',
    'description',
    'created_by_admin_id',
    'created_at',
])]
class WalletLedgerEntry extends Model
{
    /** @use HasFactory<WalletLedgerEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UserWallet, $this>
     */
    public function userWallet(): BelongsTo
    {
        return $this->belongsTo(UserWallet::class);
    }

    /**
     * @return BelongsTo<WalletDeposit, $this>
     */
    public function walletDeposit(): BelongsTo
    {
        return $this->belongsTo(WalletDeposit::class);
    }

    /**
     * @return BelongsTo<Investment, $this>
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
