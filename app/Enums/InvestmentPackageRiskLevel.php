<?php

namespace App\Enums;

enum InvestmentPackageRiskLevel: string
{
    case Conservative = 'conservative';
    case Balanced = 'balanced';
    case Growth = 'growth';

    public function label(): string
    {
        return match ($this) {
            self::Conservative => 'Conservative',
            self::Balanced => 'Balanced',
            self::Growth => 'Growth',
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
