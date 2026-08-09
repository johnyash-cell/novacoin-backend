<?php

namespace App\Models;

use Database\Factories\CryptoInvestmentDailyValuationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crypto_investment_id',
    'valuation_date',
    'price_usd',
    'escrow_before_usd',
    'escrow_after_usd',
    'delta_usd',
    'was_clamped_by_max_loss',
    'created_at',
])]
class CryptoInvestmentDailyValuation extends Model
{
    /** @use HasFactory<CryptoInvestmentDailyValuationFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valuation_date' => 'date',
            'price_usd' => 'float',
            'escrow_before_usd' => 'float',
            'escrow_after_usd' => 'float',
            'delta_usd' => 'float',
            'was_clamped_by_max_loss' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CryptoInvestment, $this>
     */
    public function cryptoInvestment(): BelongsTo
    {
        return $this->belongsTo(CryptoInvestment::class);
    }
}
