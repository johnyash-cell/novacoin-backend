<?php

namespace App\Http\Controllers\Api;

use App\Enums\WalletWithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexUserWalletWithdrawalsRequest;
use App\Http\Requests\Api\StoreWalletWithdrawalRequest;
use App\Http\Resources\WalletWithdrawalResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\SubmitsWalletWithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WalletWithdrawalController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $statusOptions = collect(WalletWithdrawalStatus::cases())
            ->map(fn (WalletWithdrawalStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();

        return $this->successResponse(
            message: 'Filter options retrieved successfully',
            data: [
                'filters' => [
                    [
                        'key' => 'status',
                        'label' => 'Status',
                        'description' => 'Filter withdrawals by review status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function store(
        StoreWalletWithdrawalRequest $request,
        SubmitsWalletWithdrawalRequest $submitsWalletWithdrawalRequest,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();

        $withdrawal = $submitsWalletWithdrawalRequest->submit(
            user: $user,
            usdAmount: (float) $validated['usd_amount'],
            platformCryptoWalletId: (int) $validated['platform_crypto_wallet_id'],
            destinationWalletAddress: (string) $validated['destination_wallet_address'],
        );

        return $this->successResponse(
            message: 'Wallet withdrawal submitted successfully',
            data: (new WalletWithdrawalResource($withdrawal))->resolve(),
            statusCode: 201,
        );
    }

    public function index(IndexUserWalletWithdrawalsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $withdrawals = WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Wallet withdrawals fetched successfully',
            data: WalletWithdrawalResource::collection($withdrawals->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                    'last_page' => $withdrawals->lastPage(),
                ],
                'filters' => [
                    'status' => $validated['status'] ?? null,
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }
}
