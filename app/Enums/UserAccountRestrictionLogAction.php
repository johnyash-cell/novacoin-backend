<?php

namespace App\Enums;

enum UserAccountRestrictionLogAction: string
{
    case Ban = 'ban';
    case Suspend = 'suspend';
    case Unsuspend = 'unsuspend';
    case Reactivate = 'reactivate';
    case SuspensionExpired = 'suspension_expired';

    public function label(): string
    {
        return match ($this) {
            self::Ban => 'Banned',
            self::Suspend => 'Suspended',
            self::Unsuspend => 'Unsuspended',
            self::Reactivate => 'Reactivated',
            self::SuspensionExpired => 'Suspension expired',
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
