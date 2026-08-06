<?php

namespace App\Http\Controllers\Api;

use App\Enums\WalletDepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexUserWalletDepositsRequest;
use App\Http\Requests\Api\StoreWalletDepositRequest;
use App\Http\Requests\Api\WalletDepositQuoteRequest;
use App\Http\Resources\WalletDepositResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\PlatformCryptoWallet;
use App\Models\User;
use App\Models\WalletDeposit;
use App\Services\Wallet\FetchesCoinGeckoUsdAssetPrice;
use App\Services\Wallet\StoresWalletDepositProofImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class WalletDepositController extends Controller
{
    use RespondsWithApiEnvelope;

    public function quote(
        WalletDepositQuoteRequest $request,
        FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
    ): JsonResponse {
        $validated = $request->validated();
        $wallet = PlatformCryptoWallet::query()
            ->availableForFunding()
            ->whereKey($validated['platform_crypto_wallet_id'])
            ->firstOrFail();

        try {
            $rate = $fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit($wallet->coingecko_asset_id);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 503,
            );
        }

        $usdAmount = (float) $validated['usd_amount'];
        $cryptoAmount = $usdAmount / $rate;

        return $this->successResponse(
            message: 'Deposit quote fetched successfully',
            data: [
                'platform_crypto_wallet_id' => $wallet->id,
                'asset_symbol' => $wallet->asset_symbol,
                'network_name' => $wallet->network_name,
                'wallet_address' => $wallet->wallet_address,
                'usd_amount' => $usdAmount,
                'conversion_rate_usd_per_unit' => $rate,
                'crypto_amount' => $cryptoAmount,
                'quoted_at' => now()->toISOString(),
            ],
        );
    }

    public function store(
        StoreWalletDepositRequest $request,
        FetchesCoinGeckoUsdAssetPrice $fetchesCoinGeckoUsdAssetPrice,
        StoresWalletDepositProofImage $storesWalletDepositProofImage,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();

        $wallet = PlatformCryptoWallet::query()
            ->availableForFunding()
            ->whereKey($validated['platform_crypto_wallet_id'])
            ->firstOrFail();

        try {
            $rate = $fetchesCoinGeckoUsdAssetPrice->fetchUsdPricePerUnit($wallet->coingecko_asset_id);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 503,
            );
        }

        $usdAmount = (float) $validated['usd_amount'];
        $proofPath = $storesWalletDepositProofImage->store($request->file('proof_image'));

        $deposit = WalletDeposit::query()->create([
            'user_id' => $user->id,
            'platform_crypto_wallet_id' => $wallet->id,
            'usd_amount' => $usdAmount,
            'crypto_amount_expected' => $usdAmount / $rate,
            'conversion_rate_usd_per_unit' => $rate,
            'quoted_at' => now(),
            'asset_symbol' => $wallet->asset_symbol,
            'network_name' => $wallet->network_name,
            'wallet_address' => $wallet->wallet_address,
            'proof_image_path' => $proofPath,
            'status' => WalletDepositStatus::PendingApproval->value,
        ]);

        return $this->successResponse(
            message: 'Wallet deposit submitted successfully',
            data: (new WalletDepositResource($deposit))->resolve(),
            statusCode: 201,
        );
    }

    public function index(IndexUserWalletDepositsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $deposits = WalletDeposit::query()
            ->where('user_id', $user->id)
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Wallet deposits fetched successfully',
            data: WalletDepositResource::collection($deposits->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $deposits->currentPage(),
                    'per_page' => $deposits->perPage(),
                    'total' => $deposits->total(),
                    'last_page' => $deposits->lastPage(),
                ],
                'filters' => [
                    'status' => $validated['status'] ?? null,
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }
}
