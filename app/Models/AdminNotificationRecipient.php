<?php

namespace App\Models;

use Database\Factories\AdminNotificationRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admin_notification_id',
    'user_id',
    'read_at',
])]
class AdminNotificationRecipient extends Model
{
    /** @use HasFactory<AdminNotificationRecipientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminNotification, $this>
     */
    public function adminNotification(): BelongsTo
    {
        return $this->belongsTo(AdminNotification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
