<?php

namespace App\Enums;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Banned => 'Banned',
            self::Suspended => 'Suspended',
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
