<?php

namespace App\Models;

use Database\Factories\AdminNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admin_id',
    'title',
    'message',
    'audience_mode',
    'audience_count',
    'delivery',
    'sent_at',
])]
class AdminNotification extends Model
{
    /** @use HasFactory<AdminNotificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return HasMany<AdminNotificationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(AdminNotificationRecipient::class);
    }
}
