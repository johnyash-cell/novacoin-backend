<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use App\Services\CryptoInvestment\ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts;
use Database\Factories\CryptoInvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'coingecko_asset_id',
    'asset_symbol',
    'asset_label',
    'amount_usd',
    'fee_type',
    'fee_value',
    'fee_charge_source',
    'fee_usd',
    'committed_usd',
    'entry_price_usd',
    'units',
    'current_escrow_usd',
    'last_price_usd',
    'max_loss_enabled',
    'max_loss_floor_usd',
    'term_days',
    'status',
    'started_at',
    'matures_at',
    'ended_at',
    'payout_completed_at',
])]
class CryptoInvestment extends Model
{
    /** @use HasFactory<CryptoInvestmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'float',
            'fee_value' => 'float',
            'fee_usd' => 'float',
            'committed_usd' => 'float',
            'entry_price_usd' => 'float',
            'units' => 'float',
            'current_escrow_usd' => 'float',
            'last_price_usd' => 'float',
            'max_loss_enabled' => 'boolean',
            'max_loss_floor_usd' => 'float',
            'term_days' => 'integer',
            'started_at' => 'datetime',
            'matures_at' => 'datetime',
            'ended_at' => 'datetime',
            'payout_completed_at' => 'datetime',
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
     * @return HasMany<CryptoInvestmentDailyValuation, $this>
     */
    public function dailyValuations(): HasMany
    {
        return $this->hasMany(CryptoInvestmentDailyValuation::class);
    }

    public function effectiveStatus(): string
    {
        $storedStatus = (string) ($this->status ?? '');

        if ($storedStatus === InvestmentStatus::Ended->value) {
            return InvestmentStatus::Ended->value;
        }

        if ($this->payout_completed_at !== null) {
            return InvestmentStatus::Ended->value;
        }

        if ($this->matures_at !== null && $this->matures_at->isPast()) {
            return InvestmentStatus::Ended->value;
        }

        return $storedStatus;
    }

    public function unrealizedPnlUsd(): float
    {
        return round((float) $this->current_escrow_usd - (float) $this->committed_usd, 2);
    }

    public function endIfDue(): bool
    {
        $result = app(ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts::class)
            ->processInvestment($this);

        return $result['payouts_completed'] > 0;
    }

    /**
     * @return array{valuations_created: int, payouts_completed: int}
     */
    public static function processAllDue(): array
    {
        return app(ProcessesCryptoInvestmentDailyMarkToMarketAndPayouts::class)->processAll();
    }

    /**
     * @param  Builder<CryptoInvestment>  $query
     * @return Builder<CryptoInvestment>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<CryptoInvestment>  $query
     * @return Builder<CryptoInvestment>
     */
    public function scopeWithStoredStatus(Builder $query, string $status): Builder
    {
        if ($status === InvestmentStatus::Active->value) {
            return $query
                ->where('status', InvestmentStatus::Active->value)
                ->whereNull('payout_completed_at')
                ->where(function (Builder $builder): void {
                    $builder
                        ->whereNull('matures_at')
                        ->orWhere('matures_at', '>', now());
                });
        }

        if ($status === InvestmentStatus::Ended->value) {
            return $query->where(function (Builder $builder): void {
                $builder
                    ->where('status', InvestmentStatus::Ended->value)
                    ->orWhereNotNull('payout_completed_at')
                    ->orWhere(function (Builder $endedByTermBuilder): void {
                        $endedByTermBuilder
                            ->where('status', InvestmentStatus::Active->value)
                            ->whereNotNull('matures_at')
                            ->where('matures_at', '<=', now());
                    });
            });
        }

        return $query;
    }
}
