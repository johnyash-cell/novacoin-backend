<?php

namespace App\Models;

use Database\Factories\AuthenticationLoginLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_type',
    'actor_id',
    'email',
    'ip_address',
    'user_agent',
    'login_method',
    'was_successful',
    'failure_reason',
])]
class AuthenticationLoginLog extends Model
{
    /** @use HasFactory<AuthenticationLoginLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'was_successful' => 'boolean',
        ];
    }
}
