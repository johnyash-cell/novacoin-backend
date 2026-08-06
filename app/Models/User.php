<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone', 'google_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'actor_type' => 'user',
        ];
    }

    public function hasGoogleLinked(): bool
    {
        return filled($this->google_id);
    }

    public function hasPasswordSet(): bool
    {
        return filled($this->password);
    }

    public function adminBackofficeAccount(): HasOne
    {
        return $this->hasOne(Admin::class, 'email', 'email');
    }

    public function hasAdminBackofficeAccess(): bool
    {
        if ($this->relationLoaded('adminBackofficeAccount')) {
            return $this->adminBackofficeAccount !== null;
        }

        return $this->adminBackofficeAccount()->exists();
    }

    public function pageVisitLogs(): HasMany
    {
        return $this->hasMany(UserPageVisitLog::class);
    }

    /**
     * @return HasOne<UserWallet, $this>
     */
    public function userWallet(): HasOne
    {
        return $this->hasOne(UserWallet::class);
    }

    /**
     * @return HasMany<WalletDeposit, $this>
     */
    public function walletDeposits(): HasMany
    {
        return $this->hasMany(WalletDeposit::class);
    }

    /**
     * @return HasMany<WalletWithdrawal, $this>
     */
    public function walletWithdrawals(): HasMany
    {
        return $this->hasMany(WalletWithdrawal::class);
    }

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}
