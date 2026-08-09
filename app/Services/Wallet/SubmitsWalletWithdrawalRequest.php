<?php

namespace App\Services\Wallet;

use App\Enums\WalletLedgerEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Models\WalletWithdrawal;
use App\Services\Mail\ComposesMemberLifecycleEmailCopy;
use App\Services\Mail\SendsMemberTransactionalEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubmitsWalletWithdrawalRequest
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
        private FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
        private ComposesMemberLifecycleEmailCopy $composesMemberLifecycleEmailCopy,
        private SendsMemberTransactionalEmail $sendsMemberTransactionalEmail,
    ) {}

    public function submit(
        User $user,
        float $usdAmount,
        int $platformCryptoWalletId,
        string $destinationWalletAddress,
    ): WalletWithdrawal {
        $withdrawal = DB::transaction(function () use ($user, $usdAmount, $platformCryptoWalletId, $destinationWalletAddress): WalletWithdrawal {
            /** @var PlatformCryptoWallet|null $platformCryptoWallet */
            $platformCryptoWallet = PlatformCryptoWallet::query()
                ->availableForWithdrawal()
                ->whereKey($platformCryptoWalletId)
                ->lockForUpdate()
                ->first();

            if ($platformCryptoWallet === null) {
                throw ValidationException::withMessages([
                    'platform_crypto_wallet_id' => ['Select a payout method that is available for withdrawal.'],
                ]);
            }

            try {
                $rate = $this->fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit(
                    $platformCryptoWallet->coingecko_asset_id,
                );
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'platform_crypto_wallet_id' => [$exception->getMessage()],
                ]);
            }

            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $lockedWallet->available_balance < $usdAmount) {
                throw ValidationException::withMessages([
                    'usd_amount' => ['Insufficient wallet balance for this withdrawal amount.'],
                ]);
            }

            $quotedAt = now();
            $balanceAfter = round((float) $lockedWallet->available_balance - $usdAmount, 2);

            $lockedWallet->forceFill([
                'available_balance' => $balanceAfter,
            ])->save();

            $withdrawal = WalletWithdrawal::query()->create([
                'user_id' => $user->id,
                'platform_crypto_wallet_id' => $platformCryptoWallet->id,
                'usd_amount' => $usdAmount,
                'crypto_amount_expected' => $usdAmount / $rate,
                'conversion_rate_usd_per_unit' => $rate,
                'quoted_at' => $quotedAt,
                'asset_symbol' => $platformCryptoWallet->asset_symbol,
                'network_name' => $platformCryptoWallet->network_name,
                'destination_wallet_address' => $destinationWalletAddress,
                'status' => WalletWithdrawalStatus::PendingApproval->value,
            ]);

            WalletLedgerEntry::query()->create([
                'user_wallet_id' => $lockedWallet->id,
                'entry_type' => WalletLedgerEntryType::WithdrawalDebit->value,
                'amount' => -abs($usdAmount),
                'balance_after' => $balanceAfter,
                'wallet_withdrawal_id' => $withdrawal->id,
                'description' => 'Withdrawal requested and held pending approval',
                'created_at' => now(),
            ]);

            return $withdrawal->fresh(['platformCryptoWallet']) ?? $withdrawal;
        });

        $this->sendsMemberTransactionalEmail->sendCopy(
            $user,
            $this->composesMemberLifecycleEmailCopy->withdrawalSubmitted($withdrawal),
        );

        return $withdrawal;
    }
}
