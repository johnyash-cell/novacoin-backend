<?php

namespace App\Services\Wallet;

use App\Models\WalletWithdrawal;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratesWalletWithdrawalReferenceNumber
{
    private const string Prefix = 'WW';

    private const int RandomSegmentLength = 8;

    private const int MaxCollisionRetries = 10;

    /**
     * Format: WW-YYYYMMDD-XXXXXXXX (e.g. WW-20260806-A7K3X9B2).
     */
    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MaxCollisionRetries; $attempt++) {
            $referenceNumber = sprintf(
                '%s-%s-%s',
                self::Prefix,
                now()->format('Ymd'),
                Str::upper(Str::random(self::RandomSegmentLength)),
            );

            $alreadyExists = WalletWithdrawal::query()
                ->where('reference_number', $referenceNumber)
                ->exists();

            if (! $alreadyExists) {
                return $referenceNumber;
            }
        }

        throw new RuntimeException('Unable to generate a unique wallet withdrawal reference number');
    }
}
