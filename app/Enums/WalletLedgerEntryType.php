<?php

namespace App\Enums;

enum WalletLedgerEntryType: string
{
    case DepositCredit = 'deposit_credit';
    case InvestmentDebit = 'investment_debit';
    case InvestmentPayoutCredit = 'investment_payout_credit';
    case CryptoInvestmentDebit = 'crypto_investment_debit';
    case CryptoInvestmentFeeDebit = 'crypto_investment_fee_debit';
    case CryptoInvestmentPayoutCredit = 'crypto_investment_payout_credit';
    case WithdrawalDebit = 'withdrawal_debit';
    case WithdrawalRefundCredit = 'withdrawal_refund_credit';
    case ReferralCredit = 'referral_credit';
    case AdminBalanceAdjustment = 'admin_balance_adjustment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
