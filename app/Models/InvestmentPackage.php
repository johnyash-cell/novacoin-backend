<?php

namespace App\Models;

use App\Enums\InvestmentPackageAvailabilityStatus;
use Database\Factories\InvestmentPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'short_pitch',
    'description',
    'expected_return_percent',
    'term_days',
    'minimum_amount_usd',
    'maximum_amount_usd',
    'max_participants',
    'joined_count',
    'risk_level',
    'availability_status',
    'expires_at',
    'is_featured',
    'highlights',
])]
class InvestmentPackage extends Model
{
    /** @use HasFactory<InvestmentPackageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_return_percent' => 'float',
            'term_days' => 'integer',
            'minimum_amount_usd' => 'float',
            'maximum_amount_usd' => 'float',
            'max_participants' => 'integer',
            'joined_count' => 'integer',
            'expires_at' => 'datetime',
            'is_featured' => 'boolean',
            'highlights' => 'array',
        ];
    }

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function isAtParticipantCapacity(): bool
    {
        return $this->joined_count >= $this->max_participants;
    }

    public function isJoinable(): bool
    {
        $effectiveStatus = InvestmentPackageAvailabilityStatus::tryFrom($this->effectiveAvailabilityStatus());

        if ($effectiveStatus === null || ! $effectiveStatus->isJoinableIntent()) {
            return false;
        }

        return ! $this->isAtParticipantCapacity();
    }

    public function remainingSeats(): int
    {
        return max(0, $this->max_participants - $this->joined_count);
    }

    public function effectiveAvailabilityStatus(): string
    {
        $storedStatus = (string) ($this->availability_status ?? '');

        if ($storedStatus === InvestmentPackageAvailabilityStatus::Expired->value) {
            return InvestmentPackageAvailabilityStatus::Expired->value;
        }

        if ($this->isAtParticipantCapacity()) {
            return InvestmentPackageAvailabilityStatus::Full->value;
        }

        return $storedStatus;
    }

    /**
     * Persist expiry when expires_at is due so status is durable, not virtual-only.
     */
    public function expireIfDue(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        if ($this->availability_status === InvestmentPackageAvailabilityStatus::Expired->value) {
            return false;
        }

        if ($this->expires_at->isFuture()) {
            return false;
        }

        $this->forceFill([
            'availability_status' => InvestmentPackageAvailabilityStatus::Expired->value,
        ])->save();

        return true;
    }

    public static function expireAllDue(): int
    {
        return static::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('availability_status', '!=', InvestmentPackageAvailabilityStatus::Expired->value)
            ->update([
                'availability_status' => InvestmentPackageAvailabilityStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  Builder<InvestmentPackage>  $query
     * @return Builder<InvestmentPackage>
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
                ->orWhere('short_pitch', 'like', $term);
        });
    }
}
