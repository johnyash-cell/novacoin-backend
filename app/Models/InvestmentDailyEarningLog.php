<?php

namespace App\Models;

use Database\Factories\InvestmentDailyEarningLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id',
    'earning_date',
    'amount_usd',
    'accrued_return_after_usd',
    'created_at',
])]
class InvestmentDailyEarningLog extends Model
{
    /** @use HasFactory<InvestmentDailyEarningLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earning_date' => 'date',
            'amount_usd' => 'float',
            'accrued_return_after_usd' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Investment, $this>
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
