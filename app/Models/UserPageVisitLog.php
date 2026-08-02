<?php

namespace App\Models;

use Database\Factories\UserPageVisitLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'page_path',
    'page_title',
    'ip_address',
    'user_agent',
    'device_type',
    'traffic_source',
    'visited_at',
])]
class UserPageVisitLog extends Model
{
    /** @use HasFactory<UserPageVisitLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
