<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use App\Services\Investment\ProcessesInvestmentDailyAccrualAndMaturityPayouts;
use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'investment_package_id',
    'package_name',
    'amount_usd',
    'expected_return_percent',
    'term_days',
    'expected_return_amount_usd',
    'expected_payout_amount_usd',
    'accrued_return_usd',
    'status',
    'started_at',
    'matures_at',
    'ended_at',
    'payout_completed_at',
])]
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'float',
            'expected_return_percent' => 'float',
            'term_days' => 'integer',
            'expected_return_amount_usd' => 'float',
            'expected_payout_amount_usd' => 'float',
            'accrued_return_usd' => 'float',
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
     * @return BelongsTo<InvestmentPackage, $this>
     */
    public function investmentPackage(): BelongsTo
    {
        return $this->belongsTo(InvestmentPackage::class);
    }

    /**
     * @return HasMany<InvestmentDailyEarningLog, $this>
     */
    public function dailyEarningLogs(): HasMany
    {
        return $this->hasMany(InvestmentDailyEarningLog::class);
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

    /**
     * Accrue due daily returns and settle maturity payout when eligible.
     */
    public function endIfDue(): bool
    {
        $result = app(ProcessesInvestmentDailyAccrualAndMaturityPayouts::class)
            ->processInvestment($this);

        return $result['payouts_completed'] > 0;
    }

    /**
     * Accrue and settle all unpaid investments. Returns how many payouts completed.
     */
    public static function endAllDue(): int
    {
        $result = app(ProcessesInvestmentDailyAccrualAndMaturityPayouts::class)->processAll();

        return $result['payouts_completed'];
    }

    /**
     * @param  Builder<Investment>  $query
     * @return Builder<Investment>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Investment>  $query
     * @return Builder<Investment>
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
