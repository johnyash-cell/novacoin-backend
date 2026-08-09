<?php

namespace App\Models;

use Database\Factories\UserAccountRestrictionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'action',
    'previous_account_status',
    'new_account_status',
    'reason',
    'suspended_until',
    'performed_by_admin_id',
    'created_at',
])]
class UserAccountRestrictionLog extends Model
{
    /** @use HasFactory<UserAccountRestrictionLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suspended_until' => 'datetime',
            'created_at' => 'datetime',
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
     * @param  Builder<UserAccountRestrictionLog>  $query
     * @return Builder<UserAccountRestrictionLog>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
