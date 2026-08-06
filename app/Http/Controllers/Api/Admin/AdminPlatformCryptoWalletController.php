<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\MapsPlatformCryptoWalletAssetKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexPlatformCryptoWalletsRequest;
use App\Http\Requests\Api\Admin\StorePlatformCryptoWalletRequest;
use App\Http\Requests\Api\Admin\UpdatePlatformCryptoWalletRequest;
use App\Http\Resources\PlatformCryptoWalletResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\PlatformCryptoWallet;
use App\Support\PlatformCryptoAssetCatalog;
use Illuminate\Http\JsonResponse;

class AdminPlatformCryptoWalletController extends Controller
{
    use MapsPlatformCryptoWalletAssetKey;
    use RespondsWithApiEnvelope;

    public function assetOptions(): JsonResponse
    {
        return $this->successResponse(
            message: 'Platform crypto asset options retrieved successfully',
            data: [
                'assets' => PlatformCryptoAssetCatalog::optionsForAdminSelect(),
                'total_available_assets' => count(PlatformCryptoAssetCatalog::keys()),
            ],
        );
    }

    public function filterOptions(): JsonResponse
    {
        $availabilityOptions = [
            ['value' => 'true', 'label' => 'Available'],
            ['value' => 'false', 'label' => 'Unavailable'],
        ];

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'is_available_for_funding',
                        'label' => 'Available for funding',
                        'description' => 'Filter by whether members can use this wallet on Fund Account',
                        'type' => 'single-select',
                        'options' => $availabilityOptions,
                    ],
                    [
                        'key' => 'is_available_for_withdrawal',
                        'label' => 'Available for withdrawal',
                        'description' => 'Filter by whether members can use this wallet on Withdraw',
                        'type' => 'single-select',
                        'options' => $availabilityOptions,
                    ],
                ],
                'total_available_filters' => 2,
            ],
        );
    }

    public function index(IndexPlatformCryptoWalletsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $wallets = PlatformCryptoWallet::query()
            ->search($validated['search'] ?? null)
            ->when(
                array_key_exists('is_available_for_funding', $validated) && $validated['is_available_for_funding'] !== null,
                fn ($query) => $query->where('is_available_for_funding', $validated['is_available_for_funding']),
            )
            ->when(
                array_key_exists('is_available_for_withdrawal', $validated) && $validated['is_available_for_withdrawal'] !== null,
                fn ($query) => $query->where('is_available_for_withdrawal', $validated['is_available_for_withdrawal']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Platform crypto wallets fetched successfully',
            data: collect($wallets->items())
                ->map(fn (PlatformCryptoWallet $wallet) => (new PlatformCryptoWalletResource($wallet, true))->resolve())
                ->all(),
            meta: [
                'pagination' => [
                    'current_page' => $wallets->currentPage(),
                    'per_page' => $wallets->perPage(),
                    'total' => $wallets->total(),
                    'last_page' => $wallets->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'is_available_for_funding' => $validated['is_available_for_funding'] ?? null,
                    'is_available_for_withdrawal' => $validated['is_available_for_withdrawal'] ?? null,
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }

    public function store(StorePlatformCryptoWalletRequest $request): JsonResponse
    {
        $validated = $this->mapValidatedPlatformCryptoWalletPayload($request->validated());
        $validated['is_available_for_funding'] = $validated['is_available_for_funding'] ?? true;
        $validated['is_available_for_withdrawal'] = $validated['is_available_for_withdrawal'] ?? false;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $wallet = PlatformCryptoWallet::query()->create($validated);

        return $this->successResponse(
            message: 'Platform crypto wallet created successfully',
            data: (new PlatformCryptoWalletResource($wallet, true))->resolve(),
            statusCode: 201,
        );
    }

    public function show(PlatformCryptoWallet $platformCryptoWallet): JsonResponse
    {
        return $this->successResponse(
            message: 'Platform crypto wallet fetched successfully',
            data: (new PlatformCryptoWalletResource($platformCryptoWallet, true))->resolve(),
        );
    }

    public function update(
        UpdatePlatformCryptoWalletRequest $request,
        PlatformCryptoWallet $platformCryptoWallet,
    ): JsonResponse {
        $validated = $this->mapValidatedPlatformCryptoWalletPayload($request->validated());

        $platformCryptoWallet->update($validated);

        return $this->successResponse(
            message: 'Platform crypto wallet updated successfully',
            data: (new PlatformCryptoWalletResource($platformCryptoWallet->fresh(), true))->resolve(),
        );
    }

    public function destroy(PlatformCryptoWallet $platformCryptoWallet): JsonResponse
    {
        if ($platformCryptoWallet->walletDeposits()->exists()) {
            return $this->errorResponse(
                message: 'This platform crypto wallet cannot be deleted because deposits already reference it',
                statusCode: 422,
            );
        }

        if ($platformCryptoWallet->walletWithdrawals()->exists()) {
            return $this->errorResponse(
                message: 'This platform crypto wallet cannot be deleted because withdrawals already reference it',
                statusCode: 422,
            );
        }

        $platformCryptoWallet->delete();

        return $this->successResponse(
            message: 'Platform crypto wallet deleted successfully',
            data: null,
        );
    }
}
