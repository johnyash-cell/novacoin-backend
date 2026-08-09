<?php

namespace App\Services\CryptoInvestment;

use App\Enums\CryptoInvestmentFeeChargeSource;
use App\Enums\InvestmentStatus;
use App\Enums\WalletLedgerEntryType;
use App\Models\CryptoInvestment;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedgerEntry;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use App\Services\Wallet\ResolvesUserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebitsUserWalletForCryptoAssetInvestment
{
    public function __construct(
        private ResolvesUserWallet $resolvesUserWallet,
        private CalculatesCryptoInvestmentFeeAndExposure $calculatesCryptoInvestmentFeeAndExposure,
        private FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
        private ResolvesCryptoInvestmentProgramSettings $resolvesCryptoInvestmentProgramSettings,
    ) {}

    public function invest(
        User $user,
        string $coingeckoAssetId,
        float $amountUsd,
        string $feeChargeSource,
    ): CryptoInvestment {
        return DB::transaction(function () use ($user, $coingeckoAssetId, $amountUsd, $feeChargeSource): CryptoInvestment {
            $settings = $this->resolvesCryptoInvestmentProgramSettings;
            $settings->assertInvestingIsEnabled();

            $asset = $settings->requireSupportedAsset($coingeckoAssetId);

            if ($amountUsd < $settings->minimumAmountUsd()) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Investment amount must be at least '.$settings->minimumAmountUsd().' USD.'],
                ]);
            }

            $maximumAmountUsd = $settings->maximumAmountUsd();

            if ($maximumAmountUsd !== null && $amountUsd > $maximumAmountUsd) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Investment amount must not exceed '.$maximumAmountUsd.' USD.'],
                ]);
            }

            $feeChargeSourceEnum = CryptoInvestmentFeeChargeSource::tryFrom($feeChargeSource);

            if ($feeChargeSourceEnum === null) {
                throw ValidationException::withMessages([
                    'fee_charge_source' => ['Fee charge source is invalid.'],
                ]);
            }

            $exposure = $this->calculatesCryptoInvestmentFeeAndExposure->calculate(
                amountUsd: $amountUsd,
                feeType: $settings->feeType(),
                feeValue: $settings->feeValue(),
                feeChargeSource: $feeChargeSourceEnum->value,
                maxLossEnabled: $settings->isMaxLossEnabled(),
                maxLossPercent: $settings->maxLossPercent(),
            );

            $userWallet = $this->resolvesUserWallet->resolveForUser($user);
            /** @var UserWallet $lockedWallet */
            $lockedWallet = UserWallet::query()
                ->whereKey($userWallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $lockedWallet->available_balance < $exposure['total_debit_usd']) {
                throw ValidationException::withMessages([
                    'amount_usd' => ['Insufficient wallet balance for this investment and fee.'],
                ]);
            }

            $entryPriceUsd = $this->fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit(
                $asset['coingecko_asset_id'],
            );

            if ($entryPriceUsd <= 0) {
                throw ValidationException::withMessages([
                    'coingecko_asset_id' => ['Live asset price is unavailable. Please try again shortly.'],
                ]);
            }

            $units = $exposure['committed_usd'] / $entryPriceUsd;
            $termDays = $settings->termDays();
            $startedAt = now();
            $maturesAt = $startedAt->copy()->addDays($termDays);

            $holdingAttributes = [
                'user_id' => $user->id,
                'coingecko_asset_id' => $asset['coingecko_asset_id'],
                'asset_symbol' => $asset['asset_symbol'],
                'asset_label' => $asset['asset_label'],
                'amount_usd' => $amountUsd,
                'fee_type' => $settings->feeType(),
                'fee_value' => $settings->feeValue(),
                'fee_charge_source' => $feeChargeSourceEnum->value,
                'fee_usd' => $exposure['fee_usd'],
                'committed_usd' => $exposure['committed_usd'],
                'entry_price_usd' => $entryPriceUsd,
                'units' => $units,
                'current_escrow_usd' => $exposure['committed_usd'],
                'last_price_usd' => $entryPriceUsd,
                'max_loss_enabled' => $settings->isMaxLossEnabled(),
                'max_loss_floor_usd' => $exposure['max_loss_floor_usd'],
                'term_days' => $termDays,
                'status' => InvestmentStatus::Active->value,
                'started_at' => $startedAt,
                'matures_at' => $maturesAt,
                'payout_completed_at' => null,
            ];

            if ($feeChargeSourceEnum === CryptoInvestmentFeeChargeSource::FromInvestAmount) {
                $balanceAfter = round((float) $lockedWallet->available_balance - $exposure['total_debit_usd'], 2);

                $lockedWallet->forceFill([
                    'available_balance' => $balanceAfter,
                ])->save();

                $cryptoInvestment = CryptoInvestment::query()->create($holdingAttributes);

                WalletLedgerEntry::query()->create([
                    'user_wallet_id' => $lockedWallet->id,
                    'entry_type' => WalletLedgerEntryType::CryptoInvestmentDebit->value,
                    'amount' => -abs($exposure['total_debit_usd']),
                    'balance_after' => $balanceAfter,
                    'crypto_investment_id' => $cryptoInvestment->id,
                    'description' => 'Crypto investment in '.$asset['asset_label'],
                    'created_at' => now(),
                ]);
            } else {
                $balanceAfterPrincipal = round(
                    (float) $lockedWallet->available_balance - $exposure['committed_usd'],
                    2,
                );

                $lockedWallet->forceFill([
                    'available_balance' => $balanceAfterPrincipal,
                ])->save();

                $cryptoInvestment = CryptoInvestment::query()->create($holdingAttributes);

                WalletLedgerEntry::query()->create([
                    'user_wallet_id' => $lockedWallet->id,
                    'entry_type' => WalletLedgerEntryType::CryptoInvestmentDebit->value,
                    'amount' => -abs($exposure['committed_usd']),
                    'balance_after' => $balanceAfterPrincipal,
                    'crypto_investment_id' => $cryptoInvestment->id,
                    'description' => 'Crypto investment in '.$asset['asset_label'],
                    'created_at' => now(),
                ]);

                if ($exposure['fee_usd'] > 0) {
                    $balanceAfterFee = round($balanceAfterPrincipal - $exposure['fee_usd'], 2);

                    $lockedWallet->forceFill([
                        'available_balance' => $balanceAfterFee,
                    ])->save();

                    WalletLedgerEntry::query()->create([
                        'user_wallet_id' => $lockedWallet->id,
                        'entry_type' => WalletLedgerEntryType::CryptoInvestmentFeeDebit->value,
                        'amount' => -abs($exposure['fee_usd']),
                        'balance_after' => $balanceAfterFee,
                        'crypto_investment_id' => $cryptoInvestment->id,
                        'description' => 'Crypto investment fee for '.$asset['asset_label'],
                        'created_at' => now(),
                    ]);
                }
            }

            return $cryptoInvestment->fresh() ?? $cryptoInvestment;
        });
    }
}
