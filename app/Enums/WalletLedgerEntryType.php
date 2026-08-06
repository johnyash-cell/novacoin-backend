<?php

namespace App\Enums;

enum WalletLedgerEntryType: string
{
    case DepositCredit = 'deposit_credit';
    case InvestmentDebit = 'investment_debit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
