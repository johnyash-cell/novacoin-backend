<?php

namespace App\Enums;

enum WalletLedgerEntryType: string
{
    case DepositCredit = 'deposit_credit';
    case InvestmentDebit = 'investment_debit';
    case WithdrawalDebit = 'withdrawal_debit';
    case WithdrawalRefundCredit = 'withdrawal_refund_credit';
    case ReferralCredit = 'referral_credit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
