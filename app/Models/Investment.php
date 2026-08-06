<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'investment_package_id',
    'package_name',
    'amount_usd',
    'expected_return_percent',
    'term_days',
    'expected_return_amount_usd',
    'expected_payout_amount_usd',
    'status',
    'started_at',
    'matures_at',
    'ended_at',
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
            'started_at' => 'datetime',
            'matures_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function effectiveStatus(): string
    {
        $storedStatus = (string) ($this->status ?? '');

        if ($storedStatus === InvestmentStatus::Ended->value) {
            return InvestmentStatus::Ended->value;
        }

        if ($this->matures_at !== null && $this->matures_at->isPast()) {
            return InvestmentStatus::Ended->value;
        }

        return $storedStatus;
    }

    /**
     * Persist term completion so status is durable, not virtual-only.
     */
    public function endIfDue(): bool
    {
        if ($this->status === InvestmentStatus::Ended->value) {
            return false;
        }

        if ($this->matures_at === null || $this->matures_at->isFuture()) {
            return false;
        }

        $this->forceFill([
            'status' => InvestmentStatus::Ended->value,
            'ended_at' => $this->ended_at ?? now(),
        ])->save();

        return true;
    }

    public static function endAllDue(): int
    {
        return static::query()
            ->where('status', InvestmentStatus::Active->value)
            ->whereNotNull('matures_at')
            ->where('matures_at', '<=', now())
            ->update([
                'status' => InvestmentStatus::Ended->value,
                'ended_at' => now(),
                'updated_at' => now(),
            ]);
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
