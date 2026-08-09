<?php

namespace App\Enums;

enum CryptoInvestmentFeeChargeSource: string
{
    case FromInvestAmount = 'from_invest_amount';
    case FromWallet = 'from_wallet';

    public function label(): string
    {
        return match ($this) {
            self::FromInvestAmount => 'From invest amount',
            self::FromWallet => 'From main wallet',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
