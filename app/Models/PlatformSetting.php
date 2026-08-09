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

    public const CRYPTO_INVESTMENT_IS_ENABLED = 'crypto_investment_is_enabled';

    public const CRYPTO_INVESTMENT_TERM_DAYS = 'crypto_investment_term_days';

    public const CRYPTO_INVESTMENT_MINIMUM_AMOUNT_USD = 'crypto_investment_minimum_amount_usd';

    public const CRYPTO_INVESTMENT_MAXIMUM_AMOUNT_USD = 'crypto_investment_maximum_amount_usd';

    public const CRYPTO_INVESTMENT_FEE_TYPE = 'crypto_investment_fee_type';

    public const CRYPTO_INVESTMENT_FEE_VALUE = 'crypto_investment_fee_value';

    public const CRYPTO_INVESTMENT_MAX_LOSS_ENABLED = 'crypto_investment_max_loss_enabled';

    public const CRYPTO_INVESTMENT_MAX_LOSS_PERCENT = 'crypto_investment_max_loss_percent';

    public const CRYPTO_INVESTMENT_SUPPORTED_ASSETS = 'crypto_investment_supported_assets';
}
