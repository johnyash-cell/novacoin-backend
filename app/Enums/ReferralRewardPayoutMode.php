<?php

namespace App\Enums;

enum ReferralRewardPayoutMode: string
{
    case FirstApprovedDepositOnly = 'first_approved_deposit_only';
    case EveryApprovedDeposit = 'every_approved_deposit';

    public function label(): string
    {
        return match ($this) {
            self::FirstApprovedDepositOnly => 'First approved deposit only',
            self::EveryApprovedDeposit => 'Every approved deposit',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            self::cases(),
        );
    }
};
