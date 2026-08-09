<?php

namespace App\Models;

use App\Enums\UserAccountStatus;
use App\Services\Referral\GeneratesUniqueUserReferralCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone', 'google_id', 'email_verified_at', 'referral_code', 'referred_by_user_id'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (filled($user->referral_code)) {
                return;
            }

            $user->referral_code = app(GeneratesUniqueUserReferralCode::class)->generate();
        });
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'account_status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'account_status_changed_at' => 'datetime',
            'suspended_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accountStatusValue(): string
    {
        return (string) ($this->account_status ?? 'active');
    }

    public function accountStatusLabel(): string
    {
        $status = $this->accountStatusValue();

        return UserAccountStatus::tryFrom($status)?->label()
            ?? ucfirst(str_replace('_', ' ', $status));
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
     * @return HasMany<UserAccountRestrictionLog, $this>
     */
    public function accountRestrictionLogs(): HasMany
    {
        return $this->hasMany(UserAccountRestrictionLog::class);
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    /**
     * @return HasMany<ReferralRewardPayout, $this>
     */
    public function referralRewardPayoutsAsReferrer(): HasMany
    {
        return $this->hasMany(ReferralRewardPayout::class, 'referrer_user_id');
    }
}
