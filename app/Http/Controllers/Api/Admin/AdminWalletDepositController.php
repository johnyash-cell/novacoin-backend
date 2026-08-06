<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\WalletDepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\DeclineWalletDepositRequest;
use App\Http\Requests\Api\Admin\IndexAdminWalletDepositsRequest;
use App\Http\Resources\AdminWalletDepositResource;
use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\Admin;
use App\Models\WalletDeposit;
use App\Services\Wallet\CreditsUserWalletFromApprovedDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AdminWalletDepositController extends Controller
{
    use RespondsWithApiEnvelope;

    public function filterOptions(): JsonResponse
    {
        $statusOptions = collect(WalletDepositStatus::cases())
            ->map(fn (WalletDepositStatus $status) => [
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
                        'description' => 'Filter deposits by review status',
                        'type' => 'single-select',
                        'options' => $statusOptions,
                    ],
                ],
                'total_available_filters' => 1,
            ],
        );
    }

    public function index(IndexAdminWalletDepositsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = $validated['per_page'] ?? 10;

        $deposits = WalletDeposit::query()
            ->with(['user', 'platformCryptoWallet'])
            ->search($validated['search'] ?? null)
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('created_at', $sortBy === 'newest' ? 'desc' : 'asc')
            ->paginate($perPage);

        return $this->successResponse(
            message: 'Wallet deposits fetched successfully',
            data: AdminWalletDepositResource::collection($deposits->items())->resolve(),
            meta: [
                'pagination' => [
                    'current_page' => $deposits->currentPage(),
                    'per_page' => $deposits->perPage(),
                    'total' => $deposits->total(),
                    'last_page' => $deposits->lastPage(),
                ],
                'filters' => [
                    'search' => $validated['search'] ?? null,
                    'status' => $validated['status'] ?? null,
                    'sort_by' => $sortBy,
                ],
            ],
        );
    }

    public function show(WalletDeposit $walletDeposit): JsonResponse
    {
        $walletDeposit->load(['user', 'platformCryptoWallet', 'reviewedByAdmin']);

        return $this->successResponse(
            message: 'Wallet deposit fetched successfully',
            data: (new AdminWalletDepositResource($walletDeposit))->resolve(),
        );
    }

    public function approve(
        WalletDeposit $walletDeposit,
        CreditsUserWalletFromApprovedDeposit $creditsUserWalletFromApprovedDeposit,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $approvedDeposit = $creditsUserWalletFromApprovedDeposit->approve($walletDeposit, $admin);
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: 422,
            );
        }

        return $this->successResponse(
            message: 'Wallet deposit approved successfully',
            data: (new AdminWalletDepositResource($approvedDeposit))->resolve(),
        );
    }

    public function decline(
        DeclineWalletDepositRequest $request,
        WalletDeposit $walletDeposit,
    ): JsonResponse {
        // if ($walletDeposit->status === WalletDepositStatus::Approved->value) {
        //     return $this->errorResponse(
        //         message: 'Approved deposits cannot be declined',
        //         statusCode: 422,
        //     );
        // }

        if ($walletDeposit->status === WalletDepositStatus::Declined->value) {
            return $this->successResponse(
                message: 'Wallet deposit declined successfully',
                data: (new AdminWalletDepositResource($walletDeposit->load(['user', 'platformCryptoWallet'])))->resolve(),
            );
        }

        // if ($walletDeposit->status !== WalletDepositStatus::PendingApproval->value) {
        //     return $this->errorResponse(
        //         message: 'Only pending deposits can be declined',
        //         statusCode: 422,
        //     );
        // }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $walletDeposit->update([
            'status' => WalletDepositStatus::Declined->value,
            'decline_reason' => $request->validated('decline_reason'),
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $this->successResponse(
            message: 'Wallet deposit declined successfully',
            data: (new AdminWalletDepositResource($walletDeposit->fresh(['user', 'platformCryptoWallet'])))->resolve(),
        );
    }
}
