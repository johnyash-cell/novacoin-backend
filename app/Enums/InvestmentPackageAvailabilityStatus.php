<?php

namespace App\Enums;

enum InvestmentPackageAvailabilityStatus: string
{
    case Open = 'open';
    case Limited = 'limited';
    case Full = 'full';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Limited => 'Limited',
            self::Full => 'Full',
            self::Expired => 'Expired',
        };
    }

    public function isJoinableIntent(): bool
    {
        return $this === self::Open || $this === self::Limited;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
