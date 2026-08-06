<?php

namespace App\Models;

use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;

    public const REFERRAL_REWARD_AMOUNT_USD = 'referral_reward_amount_usd';

    public const REFERRAL_REWARD_PAYOUT_MODE = 'referral_reward_payout_mode';
}
